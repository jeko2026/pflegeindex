<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\DataQuality\FullDataAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class DataQualityFullAuditCommand extends Command
{
    private const REPORT_HEADERS = [
        'issues' => ['issue_code', 'facility_id', 'facility_name', 'city', 'field', 'current_value', 'normalized_value', 'issue', 'category', 'priority', 'confidence', 'recommended_action', 'verification_status', 'source', 'source_url', 'detected_at'],
        'facilities' => ['facility_id', 'name', 'slug', 'city', 'gemeinde', 'landkreis', 'type', 'phone', 'email', 'website', 'verification_status', 'provenance', 'verified_at', 'issues_total', 'critical_count', 'high_count', 'medium_count', 'low_count', 'overall_audit_status', 'manual_review_required'],
        'duplicates' => ['group_id', 'match_type', 'rule', 'facility_ids', 'facility_names', 'cities', 'matching_fields', 'matching_value', 'confidence', 'recommendation'],
        'geography' => ['city_id', 'city', 'city_slug', 'facilities_count', 'gemeinde_id', 'gemeinde', 'landkreis_id', 'landkreis', 'state', 'state_slug', 'geo_match_status', 'manual_review_required', 'issue_codes'],
        'verification_queue' => ['queue_position', 'priority', 'facility_id', 'name', 'city', 'type', 'address', 'phone', 'email', 'website', 'verification_status', 'provenance', 'main_issue', 'all_issue_codes', 'recommended_action', 'manual_review_required', 'review_status', 'review_notes', 'verified_name', 'verified_address', 'verified_phone', 'verified_email', 'verified_website', 'verified_source_url', 'final_status'],
    ];

    protected $signature = 'data-quality:full-audit
                            {--format=console : Output format: console, json or csv}
                            {--output= : Report directory; defaults to storage/app/data-quality}
                            {--city= : Filter by city slug}
                            {--priority= : Filter issue and queue rows: critical, high, medium or low}';

    protected $description = 'Create a complete read-only data audit and manual verification queue';

    public function handle(FullDataAudit $auditor): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['console', 'json', 'csv'], true)) {
            $this->error("Invalid format '{$format}'. Allowed: console, json, csv.");

            return self::FAILURE;
        }

        $priority = $this->option('priority');
        if (! FullDataAudit::validPriority(is_string($priority) ? $priority : null)) {
            $this->error("Invalid priority '{$priority}'. Allowed: critical, high, medium, low.");

            return self::FAILURE;
        }

        $city = $this->option('city');
        if (is_string($city) && $city !== '' && ! City::where('slug', $city)->exists()) {
            $this->error("City with slug '{$city}' not found.");

            return self::FAILURE;
        }

        $before = $this->databaseFingerprint();
        $result = $auditor->run(is_string($city) && $city !== '' ? $city : null, is_string($priority) && $priority !== '' ? $priority : null);
        $directory = $this->reportDirectory();
        File::ensureDirectoryExists($directory);

        $paths = [
            'summary' => $directory.'/full-audit-summary.json',
            'issues' => $directory.'/full-audit-issues.csv',
            'facilities' => $directory.'/full-audit-facilities.csv',
            'duplicates' => $directory.'/full-audit-duplicates.csv',
            'geography' => $directory.'/full-audit-geography.csv',
            'verification_queue' => $directory.'/full-audit-verification-queue.csv',
        ];
        File::put($paths['summary'], json_encode($result['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        foreach (['issues', 'facilities', 'duplicates', 'geography', 'verification_queue'] as $name) {
            File::put($paths[$name], $this->csv($result[$name], self::REPORT_HEADERS[$name]));
        }

        if ($before !== $this->databaseFingerprint()) {
            $this->error('Safety check failed: the SQLite database changed during the audit.');

            return self::FAILURE;
        }

        if ($format === 'json') {
            $this->output->write(json_encode($result['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } elseif ($format === 'csv') {
            $this->output->write($this->csv($result['issues'], self::REPORT_HEADERS['issues']));
        } else {
            $this->info('=== PflegeIndex Full Data Audit (read-only) ===');
            $this->table(['Metric', 'Value'], [
                ['Facilities total', $result['summary']['facilities_total']],
                ['Facilities clean', $result['summary']['facilities_clean']],
                ['Facilities with issues', $result['summary']['facilities_with_issues']],
                ['Critical issues', $result['summary']['issues_by_priority']['critical']],
                ['High issues', $result['summary']['issues_by_priority']['high']],
                ['Potential duplicate groups', $result['summary']['potential_duplicate_groups']],
                ['Unresolved geography', $result['summary']['unresolved_geography']],
            ]);
            $this->line('Reports: '.$directory);
        }

        return self::SUCCESS;
    }

    private function reportDirectory(): string
    {
        $output = $this->option('output');
        if (! is_string($output) || trim($output) === '') {
            return storage_path('app/data-quality');
        }
        $output = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($output));

        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output) ? rtrim($output, DIRECTORY_SEPARATOR) : base_path($output);
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
}
