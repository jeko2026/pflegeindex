<?php

namespace App\Console\Commands;

use App\Services\DataQuality\FullDataAudit;
use App\Services\DataQuality\ReviewPackImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class DataQualityImportReviewPackCommand extends Command
{
    private const CHANGE_HEADERS = ['facility_id', 'name', 'review_result', 'field', 'old_value', 'new_value', 'validation_status', 'warning', 'action'];

    private const ERROR_HEADERS = ['line', 'facility_id', 'code', 'message'];

    protected $signature = 'data-quality:import-review-pack
                            {file : Filled review pack CSV}
                            {--apply : Apply validated changes atomically}
                            {--only-reviewed : Process only rows with a non-empty review_result (default safety behavior)}
                            {--facility-id= : Restrict processing to one facility ID}
                            {--backup : Accepted for explicitness; --apply always creates a backup}
                            {--skip-unchanged : Do not include rows with no effective changes}
                            {--report= : Import report directory}
                            {--allow-stale : Allow rows whose snapshot is stale (strong warning on apply)}
                            {--allow-partial : Explicitly allow applying valid rows when other rows are rejected}';

    protected $description = 'Dry-run or safely apply manually reviewed facility data';

    public function handle(ReviewPackImportService $importer, FullDataAudit $auditor): int
    {
        $path = $this->resolveFile((string) $this->argument('file'));
        $facilityId = $this->option('facility-id');
        if ($facilityId !== null && filter_var($facilityId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('--facility-id must be a positive integer.');

            return self::FAILURE;
        }
        $facilityId = $facilityId === null ? null : (int) $facilityId;
        $reportDirectory = $this->resolveReportDirectory();
        File::ensureDirectoryExists($reportDirectory);
        $databaseBefore = $this->databaseFingerprint();
        $startedAt = now();
        $payload = null;

        try {
            $payload = $importer->read($path);
            $plan = $importer->plan(
                $payload,
                (bool) $this->option('allow-stale'),
                (bool) $this->option('skip-unchanged'),
                $facilityId,
            );
            $allowPartial = (bool) $this->option('allow-partial');

            if ($plan['errors'] !== [] && ! $allowPartial) {
                $this->writeReports($reportDirectory, $payload, $plan, $databaseBefore, $databaseBefore, 'dry-run', $startedAt, null, null);
                $this->renderDryRun($plan, false);

                return self::FAILURE;
            }

            if (! (bool) $this->option('apply')) {
                $this->writeReports($reportDirectory, $payload, $plan, $databaseBefore, $databaseBefore, 'dry-run', $startedAt, null, null);
                $this->renderDryRun($plan, $plan['errors'] === []);

                return self::SUCCESS;
            }

            if ((bool) $this->option('allow-stale')) {
                $this->warn('WARNING: --allow-stale was supplied; snapshot conflicts may be overwritten field-by-field.');
            }
            if ($allowPartial) {
                $this->warn('WARNING: --allow-partial was supplied; valid rows may apply while other rows remain rejected.');
            }

            $auditBefore = $auditor->run()['summary'];
            $backupPath = $this->createBackup($reportDirectory);
            try {
                $applied = $importer->apply($plan);
            } catch (Throwable $exception) {
                $this->restoreBackup($backupPath);
                throw $exception;
            }
            $databaseAfter = $this->databaseFingerprint();
            Artisan::call('data-quality:full-audit', ['--format' => 'json']);
            $auditAfter = json_decode(Artisan::output(), true);
            if (! is_array($auditAfter)) {
                // Nested Artisan calls may not expose buffered output in tests;
                // the service result is the same audit summary in that case.
                $auditAfter = $auditor->run()['summary'];
            }
            $this->writeReports($reportDirectory, $payload, $plan, $databaseBefore, $databaseAfter, 'apply', $startedAt, $backupPath, $applied, $auditBefore, $auditAfter);
            $this->renderDryRun($plan, true);
            $this->info('Applied facilities: '.$applied['facilities'].'; fields: '.$applied['fields'].'.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            if ($payload !== null && ! File::exists($reportDirectory.'/import-summary.json')) {
                $this->writeReports($reportDirectory, ['headers' => [], 'rows' => [], 'source_path' => $path], [
                    'rows_total' => 0, 'rows_empty_skipped' => 0, 'rows_reviewed' => 0, 'facilities_found' => 0, 'facilities_missing' => 0,
                    'changes' => [], 'errors' => [['line' => null, 'facility_id' => null, 'code' => 'fatal_import_error', 'message' => $exception->getMessage()]], 'warnings' => [], 'conflicts' => 0, 'unchanged_fields' => 0,
                ], $databaseBefore, $this->databaseFingerprint(), 'dry-run', $startedAt, null, null);
            }

            return self::FAILURE;
        }
    }

    private function resolveFile(string $file): string
    {
        $resolved = realpath($file) ?: realpath(base_path($file));
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException('Review pack is not readable: '.$file);
        }

        return $resolved;
    }

    private function resolveReportDirectory(): string
    {
        $requested = $this->option('report');
        if (is_string($requested) && trim($requested) !== '') {
            $requested = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($requested));

            return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $requested) ? rtrim($requested, DIRECTORY_SEPARATOR) : base_path($requested);
        }

        return storage_path('app/data-quality/imports/'.now()->format('Ymd-His'));
    }

    private function createBackup(string $reportDirectory): string
    {
        $backup = $reportDirectory.'/database-before-import.sqlite';
        if (File::exists($backup)) {
            throw new RuntimeException('Refusing to overwrite an existing import backup.');
        }
        $pdo = DB::connection()->getPdo();
        try {
            DB::statement('VACUUM INTO '.$pdo->quote($backup));
        } catch (\Throwable $exception) {
            // RefreshDatabase keeps in-memory SQLite inside a transaction; export a
            // faithful SQLite snapshot for tests and other ephemeral databases.
            if ((string) config('database.connections.sqlite.database') !== ':memory:') {
                throw $exception;
            }
            $snapshot = new \SQLite3($backup);
            foreach (DB::select("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND type IN ('table','index','trigger','view') ORDER BY type='table' DESC") as $object) {
                $sql = trim((string) ($object->sql ?? ''));
                if ($sql !== '') {
                    @$snapshot->exec($sql);
                }
            }
            foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $tableRow) {
                $table = (string) $tableRow->name;
                $columns = DB::select("PRAGMA table_info(\"{$table}\")");
                $names = array_map(static fn ($column) => (string) $column->name, $columns);
                foreach (DB::select("SELECT * FROM \"{$table}\"") as $row) {
                    $values = array_map(static function ($name) use ($row, $snapshot) {
                        $value = $row->{$name} ?? null;
                        return $value === null ? 'NULL' : "'".$snapshot->escapeString((string) $value)."'";
                    }, $names);
                    @$snapshot->exec('INSERT INTO "'.$table.'" ("'.implode('","', $names).'") VALUES ('.implode(',', $values).')');
                }
            }
            $snapshot->close();
        }
        if (! File::exists($backup) || filesize($backup) === 0) {
            throw new RuntimeException('SQLite backup was not created correctly.');
        }

        if ((string) (DB::selectOne('PRAGMA integrity_check')->integrity_check ?? '') !== 'ok') {
            throw new RuntimeException('The live SQLite database failed integrity_check before apply.');
        }

        return $backup;
    }

    private function restoreBackup(?string $backupPath): void
    {
        if ($backupPath === null || ! is_file($backupPath)) {
            return;
        }
        $database = (string) config('database.connections.sqlite.database');
        if (! is_file($database)) {
            return;
        }
        foreach ([$database.'-wal', $database.'-shm'] as $sidecar) {
            if (is_file($sidecar)) {
                File::delete($sidecar);
            }
        }
        if (! File::copy($backupPath, $database)) {
            throw new RuntimeException('Critical rollback failure while restoring the SQLite backup.');
        }
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function databaseFingerprint(): string
    {
        $database = (string) config('database.connections.sqlite.database');
        if (is_file($database)) {
            clearstatcache(true, $database);

            return hash_file('sha256', $database);
        }

        return hash('sha256', json_encode(DB::table('facilities')->orderBy('id')->get()->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $plan @param array<string, mixed>|null $applied @param array<string, mixed>|null $auditBefore @param array<string, mixed>|null $auditAfter */
    private function writeReports(string $directory, array $payload, array $plan, string $before, string $after, string $mode, mixed $startedAt, ?string $backupPath, ?array $applied, ?array $auditBefore = null, ?array $auditAfter = null): void
    {
        File::ensureDirectoryExists($directory);
        $this->writeCsv($directory.'/import-changes.csv', self::CHANGE_HEADERS, $plan['changes'] ?? []);
        $this->writeCsv($directory.'/import-errors.csv', self::ERROR_HEADERS, $plan['errors'] ?? []);
        File::copy($payload['source_path'], $directory.'/source-review-pack.csv');

        if ($mode === 'apply') {
            $this->writeAppliedCopy($payload, $directory, $plan, $applied);
        }
        $summary = [
            'source_path' => $payload['source_path'], 'started_at' => $startedAt->toIso8601String(), 'finished_at' => now()->toIso8601String(),
            'branch' => $this->gitBranch(), 'head' => $this->gitHead(), 'mode' => $mode,
            'database_sha256_before' => $before, 'database_sha256_after' => $after,
            'rows_total' => $plan['rows_total'] ?? 0, 'rows_empty_skipped' => $plan['rows_empty_skipped'] ?? 0, 'rows_reviewed' => $plan['rows_reviewed'] ?? 0,
            'facilities_found' => $plan['facilities_found'] ?? 0, 'facilities_missing' => $plan['facilities_missing'] ?? 0,
            'changed_fields' => count($plan['changes'] ?? []), 'applied_facilities' => $applied['facilities'] ?? 0,
            'skipped_rows' => $plan['rows_empty_skipped'] ?? 0, 'rejected_rows' => count($plan['errors'] ?? []),
            'warnings' => count($plan['warnings'] ?? []), 'unchanged_fields' => $plan['unchanged_fields'] ?? 0, 'conflicts' => $plan['conflicts'] ?? 0,
            'ready_to_apply' => ($plan['errors'] ?? []) === [], 'backup' => $backupPath,
            'integrity_result' => $applied['integrity'] ?? 'not_run', 'foreign_key_errors' => $applied['foreign_keys'] ?? null,
            'audit_before' => $auditBefore, 'audit_after' => $auditAfter,
        ];
        File::put($directory.'/import-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $headers */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header): mixed => $row[$header] ?? null, $headers));
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        File::put($path, "\xEF\xBB\xBF".$contents);
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $plan @param array<string, mixed>|null $applied */
    private function writeAppliedCopy(array $payload, string $directory, array $plan, ?array $applied): void
    {
        $headers = [...$payload['headers'], 'import_status', 'imported_at', 'import_message'];
        $appliedIds = collect($plan['changes'] ?? [])->pluck('facility_id')->unique()->flip();
        $errorIds = collect($plan['errors'] ?? [])->pluck('facility_id')->filter()->unique()->flip();
        $rows = [];
        foreach ($payload['rows'] as $row) {
            $id = (int) ($row['facility_id'] ?? 0);
            $row['import_status'] = $errorIds->has($id) ? 'rejected' : ($appliedIds->has($id) ? 'applied' : 'skipped');
            $row['imported_at'] = now()->toIso8601String();
            $row['import_message'] = $row['import_status'] === 'applied' ? 'Applied atomically.' : ($row['import_status'] === 'rejected' ? 'See import-errors.csv.' : 'No effective changes.');
            $rows[] = $row;
        }
        $this->writeCsv($directory.'/applied-review-pack.csv', $headers, $rows);
    }

    /** @param array<string, mixed> $plan */
    private function renderDryRun(array $plan, bool $ready): void
    {
        $this->info($ready ? 'DRY-RUN: review pack is valid and ready.' : 'DRY-RUN: review pack has rejected rows; no database changes were made.');
        $this->table(['Metric', 'Value'], [
            ['Rows total', $plan['rows_total'] ?? 0], ['Rows empty/skipped', $plan['rows_empty_skipped'] ?? 0], ['Rows reviewed', $plan['rows_reviewed'] ?? 0],
            ['Facilities found', $plan['facilities_found'] ?? 0], ['Facilities missing', $plan['facilities_missing'] ?? 0], ['Valid changes', count($plan['changes'] ?? [])],
            ['Unchanged fields', $plan['unchanged_fields'] ?? 0], ['Warnings', count($plan['warnings'] ?? [])], ['Rejected rows', count($plan['errors'] ?? [])],
            ['Conflicts', $plan['conflicts'] ?? 0], ['Ready to apply', $ready ? 'yes' : 'no'],
        ]);
        foreach ($plan['warnings'] ?? [] as $warning) {
            $this->warn($warning['code'].': '.$warning['message']);
        }
        foreach ($plan['errors'] ?? [] as $error) {
            $this->error($error['code'].': '.$error['message']);
        }
    }

    private function gitBranch(): ?string
    {
        $head = @file_get_contents(base_path('.git/HEAD'));
        if (! is_string($head)) {
            return null;
        }

        return str_starts_with(trim($head), 'ref: refs/heads/') ? substr(trim($head), 16) : null;
    }

    private function gitHead(): ?string
    {
        $head = @file_get_contents(base_path('.git/HEAD'));
        if (! is_string($head)) {
            return null;
        }
        $head = trim($head);
        if (! str_starts_with($head, 'ref: ')) {
            return $head;
        }

        $ref = substr($head, 5);
        $value = @file_get_contents(base_path('.git/'.$ref));

        return is_string($value) ? trim($value) : null;
    }
}
