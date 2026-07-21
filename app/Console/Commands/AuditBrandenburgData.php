<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use JsonException;
use RuntimeException;
use Throwable;

class AuditBrandenburgData extends Command
{
    protected $signature = 'pflegeindex:audit
                            {path? : Path to the normalized facilities JSON file}';

    protected $description = 'Audit Brandenburg source data against the PflegeIndex database';

    public function handle(): int
    {
        try {
            $records = $this->readRecords($this->resolvePath());
            $facilities = Facility::query()->with('city')->get()->keyBy('source_id');
            $audit = $this->audit($records, $facilities);

            $this->table(
                ['Prüfung', 'Ergebnis', 'Status'],
                $audit['checks'],
            );

            if ($audit['mismatches']->isNotEmpty()) {
                $this->newLine();
                $this->components->warn('Abweichungen zwischen Quelldatei und Datenbank');
                $this->table(
                    ['Quell-ID', 'Abweichende Felder'],
                    $audit['mismatches']->take(25)->map(fn (array $item): array => [
                        $item['source_id'],
                        implode(', ', $item['fields']),
                    ])->all(),
                );
            }

            if ($audit['shared_locations']->isNotEmpty()) {
                $this->newLine();
                $this->components->info('Mehrere offizielle Datensätze am selben Standort');
                $this->table(
                    ['Einrichtung und Anschrift', 'Sektoren'],
                    $audit['shared_locations']->map(fn (Collection $group): array => [
                        $group->first()['name'].' · '.$group->first()['address'].' · '.$group->first()['postalCode'].' '.$group->first()['city'],
                        $group->pluck('type')->unique()->implode(' / '),
                    ])->all(),
                );
            }

            $this->newLine();
            $this->table(
                ['Kontaktstatus', 'Anzahl'],
                [
                    ['Geprüft', $audit['contacts']['verified']],
                    ['Nicht gefunden', $audit['contacts']['not_found']],
                    ['Noch offen', $audit['contacts']['open']],
                    ['Telefon vorhanden', $audit['contacts']['phone']],
                    ['E-Mail vorhanden', $audit['contacts']['email']],
                    ['Website vorhanden', $audit['contacts']['website']],
                ],
            );

            if ($audit['critical'] > 0) {
                $this->components->error("Audit fehlgeschlagen: {$audit['critical']} kritische Abweichungen.");

                return self::FAILURE;
            }

            $this->components->info('Audit erfolgreich: Die offiziellen Basisdaten stimmen mit der Datenbank überein.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePath(): string
    {
        $path = $this->argument('path') ?: base_path('../mvp/data/facilities.json');
        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException("Audit file not found: {$path}");
        }

        return $resolved;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readRecords(string $path): Collection
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read audit file: {$path}");
        }

        $records = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($records) || ! array_is_list($records)) {
            throw new RuntimeException('The audit file must contain a JSON array.');
        }

        return collect($records);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @param  Collection<string, Facility>  $facilities
     * @return array{
     *     checks: list<array{string, int|string, string}>,
     *     mismatches: Collection<int, array{source_id: string, fields: list<string>}>,
     *     shared_locations: Collection<string, Collection<int, array<string, mixed>>>,
     *     contacts: array{verified: int, not_found: int, open: int, phone: int, email: int, website: int},
     *     critical: int
     * }
     */
    private function audit(Collection $records, Collection $facilities): array
    {
        $requiredFields = ['id', 'name', 'city', 'citySlug', 'slug', 'postalCode', 'address', 'type'];
        $allowedTypes = ['Ambulante Pflege', 'Stationäre/teilstationäre Pflege', 'Krankenhaus'];
        $invalidRequired = $records->filter(function (array $record) use ($requiredFields): bool {
            return collect($requiredFields)->contains(
                fn (string $field): bool => ! isset($record[$field]) || trim((string) $record[$field]) === '',
            );
        })->count();
        $invalidPostcodes = $records->filter(
            fn (array $record): bool => preg_match('/^\d{5}$/', (string) ($record['postalCode'] ?? '')) !== 1,
        )->count();
        $invalidTypes = $records->filter(
            fn (array $record): bool => ! in_array((string) ($record['type'] ?? ''), $allowedTypes, true),
        )->count();
        $duplicateSourceIds = $records->groupBy('id')->filter(fn (Collection $group): bool => $group->count() > 1)->count();
        $duplicateRoutes = $records
            ->groupBy(fn (array $record): string => ($record['citySlug'] ?? '').'/'.($record['slug'] ?? ''))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->count();

        $sourceIds = $records->pluck('id')->map(fn (mixed $id): string => (string) $id);
        $missingInDatabase = $sourceIds->diff($facilities->keys())->count();
        $extraInDatabase = $facilities->keys()->diff($sourceIds)->count();
        $mismatches = collect();

        foreach ($records as $record) {
            $sourceId = (string) ($record['id'] ?? '');
            $facility = $facilities->get($sourceId);

            if ($facility === null) {
                continue;
            }

            $expected = [
                'name' => (string) ($record['name'] ?? ''),
                'slug' => (string) ($record['slug'] ?? ''),
                'postal_code' => (string) ($record['postalCode'] ?? ''),
                'address' => (string) ($record['address'] ?? ''),
                'type' => (string) ($record['type'] ?? ''),
                'source_sector' => $record['sourceSector'] ?? null,
                'city.name' => (string) ($record['city'] ?? ''),
                'city.slug' => (string) ($record['citySlug'] ?? ''),
            ];
            $differentFields = collect($expected)->filter(function (mixed $value, string $field) use ($facility): bool {
                $actual = str_starts_with($field, 'city.')
                    ? data_get($facility, $field)
                    : $facility->{$field};

                return $actual !== $value;
            })->keys()->values()->all();

            if ($differentFields !== []) {
                $mismatches->push(['source_id' => $sourceId, 'fields' => $differentFields]);
            }
        }

        $verifiedWithoutContact = $facilities->filter(
            fn (Facility $facility): bool => $facility->contact_status === 'verified'
                && blank($facility->phone)
                && blank($facility->email)
                && blank($facility->website),
        )->count();
        $notFoundWithContact = $facilities->filter(
            fn (Facility $facility): bool => $facility->contact_status === 'not_found'
                && (filled($facility->phone) || filled($facility->email) || filled($facility->website)),
        )->count();
        $invalidEmails = $facilities->filter(
            fn (Facility $facility): bool => filled($facility->email)
                && filter_var($facility->email, FILTER_VALIDATE_EMAIL) === false,
        )->count();
        $invalidWebsites = $facilities->filter(function (Facility $facility): bool {
            if (blank($facility->website)) {
                return false;
            }

            return ! HttpUrl::isValid($facility->website);
        })->count();
        $sharedLocations = $records
            ->groupBy(fn (array $record): string => mb_strtolower(implode('|', [
                trim((string) ($record['name'] ?? '')),
                trim((string) ($record['address'] ?? '')),
                trim((string) ($record['postalCode'] ?? '')),
                trim((string) ($record['city'] ?? '')),
            ])))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        $checks = [
            $this->checkRow('Offizielle Quelldatensätze', $records->count(), $records->count() > 0),
            $this->checkRow('Datensätze in der Datenbank', $facilities->count(), $facilities->count() === $records->count()),
            $this->checkRow('Fehlende Pflichtfelder', $invalidRequired, $invalidRequired === 0),
            $this->checkRow('Ungültige Postleitzahlen', $invalidPostcodes, $invalidPostcodes === 0),
            $this->checkRow('Unbekannte Einrichtungsarten', $invalidTypes, $invalidTypes === 0),
            $this->checkRow('Doppelte Quell-IDs', $duplicateSourceIds, $duplicateSourceIds === 0),
            $this->checkRow('Doppelte öffentliche URLs', $duplicateRoutes, $duplicateRoutes === 0),
            $this->checkRow('In der Datenbank fehlend', $missingInDatabase, $missingInDatabase === 0),
            $this->checkRow('Zusätzliche Datenbankeinträge', $extraInDatabase, $extraInDatabase === 0),
            $this->checkRow('Abweichende Basisdaten', $mismatches->count(), $mismatches->isEmpty()),
            $this->checkRow('Geprüft ohne Kontakt', $verifiedWithoutContact, $verifiedWithoutContact === 0),
            $this->checkRow('Nicht gefunden mit Kontakt', $notFoundWithContact, $notFoundWithContact === 0),
            $this->checkRow('Ungültige E-Mail-Adressen', $invalidEmails, $invalidEmails === 0),
            $this->checkRow('Ungültige Websites', $invalidWebsites, $invalidWebsites === 0),
        ];
        $critical = collect($checks)->where(2, 'FEHLER')->count();

        return [
            'checks' => $checks,
            'mismatches' => $mismatches,
            'shared_locations' => $sharedLocations,
            'contacts' => [
                'verified' => $facilities->where('contact_status', 'verified')->count(),
                'not_found' => $facilities->where('contact_status', 'not_found')->count(),
                'open' => $facilities->whereNull('contact_status')->count(),
                'phone' => $facilities->filter(fn (Facility $facility): bool => filled($facility->phone))->count(),
                'email' => $facilities->filter(fn (Facility $facility): bool => filled($facility->email))->count(),
                'website' => $facilities->filter(fn (Facility $facility): bool => filled($facility->website))->count(),
            ],
            'critical' => $critical,
        ];
    }

    /** @return array{string, int|string, string} */
    private function checkRow(string $label, int|string $result, bool $successful): array
    {
        return [$label, $result, $successful ? 'OK' : 'FEHLER'];
    }
}
