<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Services\DataQuality\FullDataAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class DataQualityGenerateReviewQueuesCommand extends Command
{
    protected $signature = 'data-quality:generate-review-queues';

    protected $description = 'Generate read-only specialist queues for manual data enrichment';

    private const HEADERS = [
        'facility_id', 'name', 'type', 'city', 'postcode', 'address', 'phone', 'website',
        'reviewed_email', 'reviewed_source_url', 'review_result',
    ];

    public function handle(FullDataAudit $auditor): int
    {
        $started = microtime(true);
        $directory = storage_path('app/data-quality/review-queues');
        File::ensureDirectoryExists($directory);

        $facilities = Facility::query()->with('city')->orderBy('id')->get();
        $audit = $auditor->run();
        $issuesByFacility = collect($audit['issues'] ?? [])->groupBy('facility_id');
        $duplicateIds = collect($audit['duplicates'] ?? [])->flatMap(function (array $duplicate): array {
            return collect(explode('|', (string) ($duplicate['facility_ids'] ?? '')))
                ->filter(fn (string $id): bool => ctype_digit($id))
                ->map(fn (string $id): int => (int) $id)->all();
        })->unique()->flip();

        $queues = [
            'missing_email' => $facilities->filter(fn (Facility $f): bool => blank($f->email)),
            'missing_website' => $facilities->filter(fn (Facility $f): bool => blank($f->website) && ! $f->official_website_absent),
            'missing_phone' => $facilities->filter(fn (Facility $f): bool => blank($f->phone)),
            'address_review' => $this->addressCandidates($facilities, $issuesByFacility),
            'name_review' => $this->nameCandidates($facilities, $issuesByFacility, $duplicateIds),
            'type_review' => $this->typeCandidates($facilities, $issuesByFacility),
        ];

        foreach ($queues as $name => $items) {
            $this->writeCsv($directory.'/'.str_replace('_', '-', $name).'.csv', $this->sort($items));
        }

        $summary = array_map(static fn (Collection $items): int => $items->count(), $queues);
        File::put($directory.'/queue-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        File::put($directory.'/README.md', $this->readme($summary));

        $elapsed = microtime(true) - $started;
        $this->info('Generated specialist review queues (read-only).');
        foreach ($summary as $name => $count) {
            $this->line(sprintf('%-18s %d', $name.'.csv', $count));
        }
        $this->line(sprintf('Execution time: %.3f seconds', $elapsed));

        return self::SUCCESS;
    }

    private function byIssueCategory(Collection $facilities, Collection $issues, string $category): Collection
    {
        $ids = $issues->filter(fn (Collection $items): bool => $items->contains('category', $category))->keys();

        return $facilities->filter(fn (Facility $facility): bool => $ids->contains((int) $facility->id));
    }

    private function byIssueField(Collection $facilities, Collection $issues, string $field): Collection
    {
        $ids = $issues->filter(fn (Collection $items): bool => $items->contains('field', $field))->keys();

        return $facilities->filter(fn (Facility $facility): bool => $ids->contains((int) $facility->id));
    }

    private function addressCandidates(Collection $facilities, Collection $issues): Collection
    {
        $fromAudit = $this->byIssueCategory($facilities, $issues, 'address');

        return $facilities->filter(function (Facility $facility) use ($fromAudit): bool {
            $address = (string) ($facility->address ?? '');
            $postcode = (string) ($facility->postal_code ?? '');
            return $fromAudit->contains(fn (Facility $item): bool => $item->id === $facility->id)
                || blank($postcode)
                || ! preg_match('/^\d{5}$/', $postcode)
                || (preg_match('/\s{2,}/u', $address) === 1)
                || ($address !== strip_tags($address));
        });
    }

    private function typeCandidates(Collection $facilities, Collection $issues): Collection
    {
        $allowed = ['Altenpflegeheim', 'Ambulante Pflege', 'Betreutes Wohnen', 'Kurzzeitpflege', 'Tagespflege', 'Verhinderungspflege', 'Pflege-Wohngemeinschaft'];
        return $facilities->filter(function (Facility $facility) use ($issues, $allowed): bool {
            $items = $issues->get($facility->id, collect());
            return blank($facility->type) || ! in_array($facility->type, $allowed, true) || $items->contains('field', 'type');
        });
    }

    private function nameCandidates(Collection $facilities, Collection $issues, Collection $duplicateIds): Collection
    {
        $ids = $issues->filter(function (Collection $items) use ($duplicateIds): bool {
            return $items->contains(fn (array $issue): bool => str_starts_with((string) ($issue['issue_code'] ?? ''), 'BASIC_NAME_')
                || (($issue['category'] ?? '') === 'basic' && ($issue['field'] ?? '') === 'name')
                || (($issue['category'] ?? '') === 'duplicate' && ($issue['field'] ?? '') === 'duplicate_candidate'));
        })->keys()->merge($duplicateIds->keys());

        return $facilities->filter(fn (Facility $facility): bool => $ids->contains((int) $facility->id));
    }

    private function sort(Collection $facilities): Collection
    {
        return $facilities->sortBy(fn (Facility $f): string => mb_strtolower((string) ($f->city?->name ?? '')).'|'.mb_strtolower((string) $f->name).'|'.$f->id)->values();
    }

    private function writeCsv(string $path, Collection $facilities): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Cannot write queue: '.$path);
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS);
        foreach ($facilities as $facility) {
            fputcsv($handle, [$facility->id, $facility->name, $facility->type, $facility->city?->name, $facility->postal_code, $facility->address, $facility->phone, $facility->website, '', '', '']);
        }
        fclose($handle);
    }

    private function readme(array $summary): string
    {
        return "# Specialistische Prüfwarteschlangen\n\n".
            "Diese Dateien wurden read-only aus der aktuellen Datenbank erzeugt. Es wurden keine Datensätze geändert und keine Review-Ergebnisse angewendet. Sortierung: Ort, Name.\n\n".
            "- `missing-email.csv` – Einrichtungen ohne E-Mail-Adresse.\n".
            "- `missing-website.csv` – Einrichtungen ohne Website und ohne bestätigte Feststellung, dass kein offizieller Internetauftritt existiert.\n".
            "- `missing-phone.csv` – Einrichtungen ohne Telefonnummer.\n".
            "- `address-review.csv` – fehlende oder auffällige Postleitzahl, Adresse und Normalisierungsbefunde.\n".
            "- `name-review.csv` – ungewöhnliche/zu kurze oder lange Namen und Dubletten-Kandidaten.\n".
            "- `type-review.csv` – fehlende oder unbekannte Einrichtungsart.\n\n".
            "## Anzahl\n\n".implode('', array_map(static fn (string $key, int $count): string => '- `'.$key.'`: '.$count."\n", array_keys($summary), array_values($summary))).
            "\n`reviewed_*`, `reviewed_source_url` und `review_result` sind absichtlich leer und dienen nur der manuellen Bearbeitung.\n";
    }
}
