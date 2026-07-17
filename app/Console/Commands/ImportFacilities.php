<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class ImportFacilities extends Command
{
    protected $signature = 'pflegeindex:import
                            {path? : Path to the normalized facilities JSON file}';

    protected $description = 'Import PflegeIndex cities and facilities into the database';

    public function handle(): int
    {
        try {
            $path = $this->resolvePath();
            $records = $this->readRecords($path);

            DB::transaction(function () use ($records): void {
                $this->importRecords($records);
            });

            $this->components->info('PflegeIndex data imported successfully.');
            $this->table(
                ['Cities', 'Facilities', 'Verified phones'],
                [[City::count(), Facility::count(), Facility::whereNotNull('phone')->count()]],
            );

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
            throw new RuntimeException("Import file not found: {$path}");
        }

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readRecords(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read import file: {$path}");
        }

        $records = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($records) || ! array_is_list($records)) {
            throw new RuntimeException('The import file must contain a JSON array.');
        }

        return $records;
    }

    /** @param list<array<string, mixed>> $records */
    private function importRecords(array $records): void
    {
        $now = now();
        $cityRows = [];

        foreach ($records as $record) {
            $slug = (string) ($record['citySlug'] ?? '');
            $name = (string) ($record['city'] ?? '');

            if ($slug === '' || $name === '') {
                throw new RuntimeException('Every facility must have a city and citySlug.');
            }

            $cityRows[$slug] = [
                'name' => $name,
                'slug' => $slug,
                'state' => 'Brandenburg',
                'state_slug' => 'brandenburg',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        City::upsert(
            array_values($cityRows),
            ['slug'],
            ['name', 'state', 'state_slug', 'updated_at'],
        );

        $cityIds = City::query()->pluck('id', 'slug');
        $manualDescriptions = Facility::query()
            ->whereNotNull('description')
            ->pluck('description', 'source_id');
        $lockedContacts = Facility::query()
            ->where('contact_locked', true)
            ->get([
                'source_id', 'phone', 'email', 'website', 'contact_source',
                'contact_status', 'contact_checked_at',
            ])
            ->keyBy('source_id');
        $sourceIds = [];
        $facilityRows = [];

        foreach ($records as $record) {
            $sourceId = (string) ($record['id'] ?? '');
            $citySlug = (string) ($record['citySlug'] ?? '');

            if ($sourceId === '' || ! isset($cityIds[$citySlug])) {
                throw new RuntimeException('Every facility must have an id and a known citySlug.');
            }

            $sourceIds[] = $sourceId;
            $lockedContact = $lockedContacts->get($sourceId);
            $facilityRows[] = [
                'source_id' => $sourceId,
                'city_id' => $cityIds[$citySlug],
                'name' => (string) ($record['name'] ?? ''),
                'slug' => (string) ($record['slug'] ?? ''),
                'postal_code' => (string) ($record['postalCode'] ?? ''),
                'street' => $record['street'] ?? null,
                'house_number' => $record['houseNumber'] ?? null,
                'address' => (string) ($record['address'] ?? ''),
                'type' => (string) ($record['type'] ?? ''),
                'source_sector' => $record['sourceSector'] ?? null,
                'description' => $manualDescriptions->get($sourceId) ?? ($record['description'] ?? null),
                'phone' => $lockedContact !== null ? $lockedContact->phone : ($record['phone'] ?? null),
                'email' => $lockedContact !== null ? $lockedContact->email : ($record['email'] ?? null),
                'website' => $lockedContact !== null ? $lockedContact->website : ($record['website'] ?? null),
                'contact_source' => $lockedContact !== null ? $lockedContact->contact_source : ($record['contactSource'] ?? null),
                'contact_status' => $lockedContact !== null ? $lockedContact->contact_status : ($record['contactStatus'] ?? null),
                'contact_checked_at' => $lockedContact !== null
                    ? $lockedContact->contact_checked_at?->format('Y-m-d H:i:s')
                    : $this->dateOrNull($record['contactCheckedAt'] ?? null),
                'contact_locked' => $lockedContact !== null || (bool) ($record['contactLocked'] ?? false),
                'care_types' => json_encode($record['careTypes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'features' => json_encode($record['features'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($facilityRows, 400) as $chunk) {
            Facility::upsert(
                $chunk,
                ['source_id'],
                [
                    'city_id', 'name', 'slug', 'postal_code', 'street', 'house_number',
                    'address', 'type', 'source_sector', 'description', 'phone', 'email',
                    'website', 'contact_source', 'contact_status', 'contact_checked_at',
                    'contact_locked', 'care_types', 'features', 'updated_at',
                ],
            );
        }

        Facility::query()->whereNotIn('source_id', $sourceIds)->delete();
        City::query()->whereDoesntHave('facilities')->delete();
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
