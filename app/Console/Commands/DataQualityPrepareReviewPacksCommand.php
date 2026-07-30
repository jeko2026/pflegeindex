<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\DataQuality\ReviewPackPreparer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class DataQualityPrepareReviewPacksCommand extends Command
{
    private const REVIEW_HEADERS = [
        'queue_position', 'facility_id', 'name', 'type', 'city', 'address', 'postal_code', 'phone', 'email', 'website',
        'current_verification_status', 'current_provenance', 'current_verified_at', 'issue_codes', 'main_issue',
        'duplicate_classification', 'duplicate_related_ids', 'recommended_search_query', 'official_source_url',
        'reviewed_name', 'reviewed_type', 'reviewed_address', 'reviewed_postal_code', 'reviewed_phone', 'reviewed_email',
        'reviewed_website', 'reviewed_source_url', 'review_result', 'review_notes',
    ];

    private const INDEX_HEADERS = [
        'pack_number', 'filename', 'city', 'facilities_total', 'high_count', 'medium_count', 'low_count',
        'not_reviewed_count', 'duplicate_candidates', 'reviewed_count', 'remaining_count', 'status',
    ];

    private const DUPLICATE_HEADERS = [
        'group_id', 'match_type', 'rule', 'facility_ids', 'facility_names', 'cities', 'matching_fields', 'matching_value',
        'confidence', 'recommendation', 'classification', 'triage_priority', 'triage_reason',
    ];

    private const GEOGRAPHY_HEADERS = [
        'facility_id', 'name', 'current_city', 'city_slug', 'postal_code', 'possible_gemeinde', 'landkreis',
        'unresolved_reason', 'manual_decision',
    ];

    protected $signature = 'data-quality:prepare-review-packs
                            {--pack-size=30 : Approximate number of facilities per main review pack}
                            {--city= : Limit preparation to one city slug}
                            {--output=storage/app/data-quality/review-packs : Output directory}';

    protected $description = 'Triage duplicate candidates and prepare read-only manual review packs';

    public function handle(ReviewPackPreparer $preparer): int
    {
        $packSize = filter_var($this->option('pack-size'), FILTER_VALIDATE_INT);
        if (! is_int($packSize) || $packSize < 1 || $packSize > 100) {
            $this->error('Invalid --pack-size. Use an integer from 1 to 100.');

            return self::FAILURE;
        }

        $citySlug = $this->option('city');
        if (is_string($citySlug) && $citySlug !== '' && ! City::where('slug', $citySlug)->exists()) {
            $this->error("City with slug '{$citySlug}' not found.");

            return self::FAILURE;
        }

        $databaseBefore = $this->databaseFingerprint();
        $result = $preparer->prepare($packSize, is_string($citySlug) && $citySlug !== '' ? $citySlug : null);
        $directory = $this->outputDirectory();
        File::ensureDirectoryExists($directory);

        foreach (File::glob($directory.'/review-pack-*.csv') ?: [] as $stalePack) {
            File::delete($stalePack);
        }
        foreach ($result['packs'] as $pack) {
            File::put($directory.'/'.$pack['filename'], $this->csv($pack['rows'], self::REVIEW_HEADERS));
        }
        File::put($directory.'/index.csv', $this->csv($result['index'], self::INDEX_HEADERS));
        File::put($directory.'/duplicate-triage.csv', $this->csv($result['duplicate_triage'], self::DUPLICATE_HEADERS));
        File::put($directory.'/priority-high-manual-review.csv', $this->csv($result['priority_high'], self::REVIEW_HEADERS));
        File::put($directory.'/geography-manual-review.csv', $this->csv($result['geography'], self::GEOGRAPHY_HEADERS));
        File::put($directory.'/triage-summary.json', json_encode($result['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        File::put($directory.'/README.md', $this->instructions());

        if ($databaseBefore !== $this->databaseFingerprint()) {
            $this->error('Safety check failed: the SQLite database changed while preparing review packs.');

            return self::FAILURE;
        }

        $this->info('=== PflegeIndex Manual Review Packs (read-only) ===');
        $this->table(['Metric', 'Value'], [
            ['Review packs', $result['summary']['packs_total']],
            ['Queue records', $result['summary']['queue_records']],
            ['Average pack size', $result['summary']['average_pack_size']],
            ['Duplicate groups triaged', $result['summary']['duplicate_groups_before_triage']],
            ['High records after triage', $result['summary']['high_records_after_false_positive_removal']],
            ['Not reviewed', $result['summary']['not_reviewed_total']],
            ['Unresolved geography rows', $result['summary']['unresolved_geography_total']],
        ]);
        $this->line('Output: '.$directory);

        return self::SUCCESS;
    }

    private function outputDirectory(): string
    {
        $output = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim((string) $this->option('output')));

        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output)
            ? rtrim($output, DIRECTORY_SEPARATOR)
            : base_path($output);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $headers
     */
    private function csv(array $rows, array $headers): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header): mixed => $row[$header] ?? null, $headers));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }

    private function databaseFingerprint(): string
    {
        $path = (string) config('database.connections.sqlite.database');
        clearstatcache(true, $path);

        return is_file($path) ? hash_file('sha256', $path) : 'non-file-database';
    }

    private function instructions(): string
    {
        return <<<'MARKDOWN'
# PflegeIndex – Anleitung zur manuellen Prüfung

Die Dateien in diesem Ordner sind Arbeitskopien. Das Erzeugen der Pakete verändert keine Einrichtungsdaten.

## Zulässige Werte für `review_result`

- `verified` – Name, Standort und wesentliche Kontakte wurden anhand einer offiziellen Quelle bestätigt.
- `partially_verified` – Nur ein Teil der Angaben konnte bestätigt werden.
- `needs_review` – Die Recherche ist noch nicht eindeutig abgeschlossen.
- `conflict` – Verlässliche Quellen widersprechen sich; Details in `review_notes` dokumentieren.
- `closed` – Die Schließung wurde durch eine nachvollziehbare Quelle belegt.
- `duplicate` – Die Einrichtung ist nach manueller Prüfung eine Dublette; Haupt-ID in `review_notes` nennen.
- `not_found` – Keine belastbare offizielle Einrichtungsquelle gefunden.

Diese Werte sind nur für die Review-Dateien bestimmt und erweitern keine Datenbank-Enums.

## Vorgehen

1. `recommended_search_query` als Ausgangspunkt verwenden.
2. Offizielle Einrichtungs- oder Betreiberseite und möglichst eine amtliche Quelle prüfen.
3. Die verwendete exakte URL in `reviewed_source_url` eintragen.
4. Nur bestätigte Werte in `reviewed_*` eintragen; unbekannte Werte leer lassen.
5. Bei Dubletten nichts zusammenführen. Entscheidung und Haupt-ID in `review_notes` dokumentieren.
6. Google Maps, Such-Snippets und Verzeichnisse nur zur Suche, nicht als ungeprüfte Hauptquelle verwenden.

`official_source_url`, alle `reviewed_*`-Felder, `review_result` und `review_notes` sind absichtlich leer vorbereitet.
MARKDOWN;
    }
}
