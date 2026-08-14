<?php

namespace App\Console\Commands;

use App\Services\DataQuality\ManualContactReviewImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class ImportManualContactReview extends Command
{
    protected $signature = 'data-quality:import-manual-contact-review
                            {sheet1 : Comma-delimited manual review CSV without facility IDs}
                            {sheet2 : Semicolon-delimited manual review CSV with facility_id}
                            {--apply : Apply only unambiguous validated contact changes}
                            {--report= : Report directory}';

    protected $description = 'Audit and safely import the two July 2026 manual contact review CSV files';

    public function handle(ManualContactReviewImportService $importer): int
    {
        $directory = $this->reportDirectory();
        File::ensureDirectoryExists($directory);

        try {
            $payload = $importer->read((string) $this->argument('sheet1'), (string) $this->argument('sheet2'));
            $qualityBefore = $importer->qualitySnapshot();
            $integrityBefore = $importer->integritySnapshot();
            $databaseBefore = $this->databaseFingerprint();
            $plan = $importer->plan($payload);
            $this->writePlanReports($directory, $payload, $plan);
            $this->renderPlan($plan);

            if (! (bool) $this->option('apply')) {
                $this->writeSummary($directory, 'dry-run', $payload, $plan, $qualityBefore, $qualityBefore, $integrityBefore, $integrityBefore, $databaseBefore, $databaseBefore, null, null);
                $this->info('DRY-RUN complete. SQLite was not changed.');
                $this->line('Report: '.$directory);

                return self::SUCCESS;
            }

            $backup = $this->createVerifiedBackup($directory);
            try {
                $applied = $importer->apply($plan);
                $qualityAfter = $importer->qualitySnapshot();
                $integrityAfter = $importer->integritySnapshot();
                $databaseAfter = $this->databaseFingerprint();
                $this->assertIntegrity($integrityBefore, $integrityAfter);
            } catch (Throwable $exception) {
                $this->restoreBackup($backup['path']);
                throw $exception;
            }
            $this->writeSummary($directory, 'apply', $payload, $plan, $qualityBefore, $qualityAfter, $integrityBefore, $integrityAfter, $databaseBefore, $databaseAfter, $backup, $applied);
            $this->info('Applied facilities: '.$applied['facilities'].'; fields: '.$applied['fields'].'.');
            $this->line('Backup: '.$backup['path']);
            $this->line('Report: '.$directory);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function reportDirectory(): string
    {
        $requested = trim((string) ($this->option('report') ?? ''));
        if ($requested !== '') {
            return preg_match('/^[A-Za-z]:[\\\\\/]/', $requested) ? rtrim($requested, '\\/') : base_path($requested);
        }

        return storage_path('app/data-quality/manual-review-imports/'.now()->format('Ymd-His'));
    }

    /** @param array<string,mixed> $plan */
    private function renderPlan(array $plan): void
    {
        $summary = $plan['summary'];
        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());
        $this->table(['Field', 'Updates'], collect($plan['field_updates'])->map(fn ($value, $key): array => [$key, $value])->values()->all());
        if ($plan['issues'] !== []) {
            $this->warn(count($plan['issues']).' rows were rejected or require manual review; they will not be applied.');
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $plan */
    private function writePlanReports(string $directory, array $payload, array $plan): void
    {
        $this->writeCsv($directory.'/planned-changes.csv', ['facility_id', 'sheet', 'line', 'field', 'old_value', 'new_value'], $plan['changes']);
        $this->writeCsv($directory.'/issues.csv', ['sheet', 'line', 'facility_id', 'current_value', 'new_value', 'code', 'message'], $plan['issues']);
        $this->writeCsv($directory.'/row-results.csv', ['sheet', 'line', 'facility_id', 'name', 'city', 'status', 'message', 'warnings'], $plan['rows']);
        foreach ($payload['files'] as $index => $file) {
            File::copy($file['path'], $directory.'/source-sheet-'.($index + 1).'.csv');
        }
    }

    /** @param list<string> $headers @param list<array<string,mixed>> $rows */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write report: '.$path);
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): mixed => $row[$header] ?? null, $headers), ';');
        }
        fclose($handle);
    }

    /** @return array{path:string,size:int,sha256:string} */
    private function createVerifiedBackup(string $directory): array
    {
        $database = (string) config('database.connections.sqlite.database');
        if (! is_file($database)) {
            throw new RuntimeException('The configured SQLite database file does not exist.');
        }
        $backup = $directory.'/database-before-import.sqlite';
        if (File::exists($backup)) {
            throw new RuntimeException('Refusing to overwrite an existing backup.');
        }
        DB::statement('PRAGMA wal_checkpoint(FULL)');
        DB::disconnect('sqlite');
        clearstatcache(true, $database);
        if (! File::copy($database, $backup)) {
            throw new RuntimeException('Unable to create the SQLite backup.');
        }
        clearstatcache(true, $backup);
        $sourceSize = filesize($database);
        $backupSize = filesize($backup);
        $sourceHash = hash_file('sha256', $database);
        $backupHash = hash_file('sha256', $backup);
        DB::reconnect('sqlite');
        if ($sourceSize !== $backupSize || $sourceHash !== $backupHash) {
            throw new RuntimeException('Backup verification failed: size or SHA-256 differs.');
        }

        return ['path' => $backup, 'size' => (int) $backupSize, 'sha256' => $backupHash];
    }

    private function restoreBackup(string $backup): void
    {
        $database = (string) config('database.connections.sqlite.database');
        DB::disconnect('sqlite');
        foreach ([$database.'-wal', $database.'-shm'] as $sidecar) {
            if (File::exists($sidecar)) {
                File::delete($sidecar);
            }
        }
        if (! File::copy($backup, $database)) {
            throw new RuntimeException('Critical failure while restoring the verified SQLite backup.');
        }
        DB::reconnect('sqlite');
    }

    /** @param array<string,int|string> $before @param array<string,int|string> $after */
    private function assertIntegrity(array $before, array $after): void
    {
        foreach (['facilities_total', 'cities_total', 'geocore_mapping_count', 'contact_suggestions'] as $key) {
            if ($before[$key] !== $after[$key]) {
                throw new RuntimeException('Integrity check failed: '.$key.' changed.');
            }
        }
        if ($after['sqlite_integrity'] !== 'ok' || $after['invalid_foreign_keys'] !== 0 || $after['source_id_duplicates'] !== 0 || $after['orphan_records'] !== 0) {
            throw new RuntimeException('Post-import database integrity checks failed.');
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $plan @param array<string,mixed> $qualityBefore @param array<string,mixed> $qualityAfter @param array<string,mixed> $integrityBefore @param array<string,mixed> $integrityAfter @param array<string,mixed>|null $backup @param array<string,mixed>|null $applied */
    private function writeSummary(string $directory, string $mode, array $payload, array $plan, array $qualityBefore, array $qualityAfter, array $integrityBefore, array $integrityAfter, string $databaseBefore, string $databaseAfter, ?array $backup, ?array $applied): void
    {
        $summary = [
            'mode' => $mode, 'finished_at' => now()->toIso8601String(), 'input_files' => $payload['files'],
            'validation_and_dry_run' => $plan['summary'], 'field_updates' => $plan['field_updates'],
            'applied' => $applied ?? ['facilities' => 0, 'fields' => 0], 'backup' => $backup,
            'database_sha256_before' => $databaseBefore, 'database_sha256_after' => $databaseAfter,
            'data_quality_before' => $qualityBefore, 'data_quality_after' => $qualityAfter,
            'integrity_before' => $integrityBefore, 'integrity_after' => $integrityAfter,
        ];
        File::put($directory.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    private function databaseFingerprint(): string
    {
        $database = (string) config('database.connections.sqlite.database');

        return is_file($database) ? hash_file('sha256', $database) : '';
    }
}
