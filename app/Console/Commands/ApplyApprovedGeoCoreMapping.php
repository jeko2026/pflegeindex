<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class ApplyApprovedGeoCoreMapping extends Command
{
    private const EXPECTED_COLUMNS = [
        'city_id',
        'city_name',
        'facility_count',
        'municipality_ags',
        'municipality_name',
        'district_ags',
        'district_name',
        'confidence',
        'decision',
        'reason',
        'evidence_code',
    ];

    private const EXPECTED_ROWS = 77;

    private const EXPECTED_APPROVED = 75;

    private const EXPECTED_REVIEW = 2;

    private const EXPECTED_SKIP = 0;

    private const EXPECTED_CITIES_BEFORE = 180;

    private const EXPECTED_CITIES_AFTER = 255;

    private const EXPECTED_FACILITIES_ASSIGNED_BEFORE = 1158;

    private const EXPECTED_FACILITIES_ASSIGNED_AFTER = 1552;

    private const EXPECTED_FACILITIES_UNASSIGNED_AFTER = 5;

    private const EXPECTED_AFFECTED_DISTRICTS = 15;

    private const EXPECTED_DISTRICTS = 18;

    private const EXPECTED_FACILITIES = 1557;

    private const EXPECTED_CITIES = 257;

    protected $signature = 'geocore:apply-approved-mapping
        {--dry-run : Apply and validate the approved mapping only on an isolated SQLite copy}
        {--apply : Create a verified backup and apply approved mappings to the local SQLite database}
        {--mapping= : Override the approved mapping CSV path}
        {--backup-dir= : Existing protected directory for application backups}
        {--keep-copy : Keep the isolated SQLite copy for local diagnostics}';

    protected $description = 'Dry-run or safely apply approved GeoCore city mappings';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->components->error('Pass exactly one explicit mode: --dry-run or --apply.');

            return self::FAILURE;
        }

        if (app()->environment('production')) {
            $this->components->error('Approved mapping command is blocked in production.');

            return self::FAILURE;
        }

        try {
            $mappingPath = $this->resolveMappingPath();
            $rows = $this->readAndValidateCsv($mappingPath);

            return $dryRun
                ? $this->runIsolatedDryRun($rows)
                : $this->runControlledApply($rows);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveMappingPath(): string
    {
        $path = (string) ($this->option('mapping') ?: base_path('docs/GEOCORE_APPROVED_MAPPING.csv'));
        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException("Approved mapping CSV is not readable: {$path}");
        }

        return $resolved;
    }

    /** @return list<array<string, string>> */
    private function readAndValidateCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the approved mapping CSV.');
        }

        try {
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle, null, ',', '"', '\\');

            if ($header !== self::EXPECTED_COLUMNS) {
                throw new RuntimeException('Approved mapping CSV columns do not match the required schema.');
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $line++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw new RuntimeException("CSV line {$line} has an invalid column count.");
                }

                $row = array_combine($header, $values);

                if (! is_array($row)) {
                    throw new RuntimeException("CSV line {$line} cannot be parsed.");
                }

                $rows[] = array_map(static fn (string $value): string => trim($value), $row);
            }
        } finally {
            fclose($handle);
        }

        $this->validateCsvRows($rows);

        return $rows;
    }

    /** @param list<array<string, string>> $rows */
    private function validateCsvRows(array $rows): void
    {
        if (count($rows) !== self::EXPECTED_ROWS) {
            throw new RuntimeException('Approved mapping CSV must contain exactly 77 data rows.');
        }

        $cityIds = [];
        $decisions = ['APPROVED' => 0, 'REVIEW' => 0, 'SKIP' => 0];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $cityId = filter_var($row['city_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $facilityCount = filter_var($row['facility_count'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

            if ($cityId === false || $facilityCount === false) {
                throw new RuntimeException("CSV line {$line} has an invalid city_id or facility_count.");
            }

            if (isset($cityIds[$cityId])) {
                throw new RuntimeException("Duplicate city_id in approved mapping: {$cityId}");
            }

            $cityIds[$cityId] = true;

            if (! array_key_exists($row['decision'], $decisions)) {
                throw new RuntimeException("Invalid decision on CSV line {$line}: {$row['decision']}");
            }

            $decisions[$row['decision']]++;

            if (! in_array($row['confidence'], ['High', 'Medium', 'Low'], true)) {
                throw new RuntimeException("Invalid confidence on CSV line {$line}: {$row['confidence']}");
            }

            foreach (['city_name', 'reason', 'evidence_code'] as $requiredValue) {
                if ($row[$requiredValue] === '') {
                    throw new RuntimeException("Missing {$requiredValue} on CSV line {$line}.");
                }
            }

            if ($row['decision'] !== 'SKIP') {
                if (! preg_match('/^12\d{6}$/', $row['municipality_ags'])) {
                    throw new RuntimeException("Invalid municipality_ags on CSV line {$line}.");
                }

                if (! preg_match('/^12\d{3}$/', $row['district_ags'])) {
                    throw new RuntimeException("Invalid district_ags on CSV line {$line}.");
                }

                if ($row['municipality_name'] === '' || $row['district_name'] === '') {
                    throw new RuntimeException("Missing municipality or district name on CSV line {$line}.");
                }
            }
        }

        if ($decisions !== [
            'APPROVED' => self::EXPECTED_APPROVED,
            'REVIEW' => self::EXPECTED_REVIEW,
            'SKIP' => self::EXPECTED_SKIP,
        ]) {
            throw new RuntimeException('Approved mapping decisions must be exactly 75 APPROVED, 2 REVIEW and 0 SKIP.');
        }
    }

    /** @param list<array<string, string>> $rows */
    private function runIsolatedDryRun(array $rows): int
    {
        $connection = DB::getDefaultConnection();
        $driver = (string) config("database.connections.{$connection}.driver");
        $configuredDatabase = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $configuredDatabase === '' || $configuredDatabase === ':memory:') {
            throw new RuntimeException('Dry-run requires a file-backed SQLite default connection.');
        }

        $sourcePath = realpath($configuredDatabase);

        if ($sourcePath === false || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('The configured SQLite source database is not readable.');
        }

        foreach (['-wal', '-journal'] as $suffix) {
            if (is_file($sourcePath.$suffix) && filesize($sourcePath.$suffix) > 0) {
                throw new RuntimeException("SQLite {$suffix} file is active; create a consistent database snapshot before dry-run.");
            }
        }

        $sourceHashBefore = $this->fileHash($sourcePath);
        $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'pflegeindex-geocore-'.bin2hex(random_bytes(8));
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'database.sqlite';
        $keepCopy = (bool) $this->option('keep-copy');
        $configurationKey = "database.connections.{$connection}.database";

        if (! mkdir($temporaryDirectory, 0700)) {
            throw new RuntimeException('Unable to create the isolated SQLite copy.');
        }

        if (! copy($sourcePath, $temporaryPath)) {
            rmdir($temporaryDirectory);

            throw new RuntimeException('Unable to create the isolated SQLite copy.');
        }

        $this->line('Source SHA-256 before: '.$sourceHashBefore);
        $this->line('Temporary copy: system temp/'.basename($temporaryDirectory).'/database.sqlite');

        try {
            DB::purge($connection);
            config([$configurationKey => $temporaryPath]);

            $validated = $this->validateDatabaseRows($rows);
            $before = $this->snapshot();
            $urlsBefore = $this->urlInventory();

            $firstPass = $this->applyApprovedRows($validated['approved']);
            $after = $this->snapshot();
            $urlsAfter = $this->urlInventory();
            $secondPass = $this->applyApprovedRows($validated['approved']);

            $this->validateResult(
                $validated,
                $before,
                $after,
                $urlsBefore,
                $urlsAfter,
                $firstPass,
                $secondPass,
            );

            $this->renderResult($validated, $before, $after, $firstPass, $secondPass);
        } finally {
            DB::disconnect($connection);
            config([$configurationKey => $configuredDatabase]);
            DB::purge($connection);

            $sourceHashAfter = $this->fileHash($sourcePath);
            $this->line('Source SHA-256 after:  '.$sourceHashAfter);

            if ($sourceHashAfter !== $sourceHashBefore) {
                throw new RuntimeException('Working SQLite hash changed during dry-run.');
            }

            if (! $keepCopy) {
                $this->removeTemporaryCopy($temporaryDirectory, $temporaryPath);
                $this->line('Temporary copy removed: yes');
            } else {
                $this->components->warn('Temporary copy retained by --keep-copy in the system temp directory.');
            }
        }

        $this->components->info('PASS: approved GeoCore mapping dry-run completed without changing the working database.');

        return self::SUCCESS;
    }

    /** @param list<array<string, string>> $rows */
    private function runControlledApply(array $rows): int
    {
        $connection = DB::getDefaultConnection();
        $driver = (string) config("database.connections.{$connection}.driver");
        $configuredDatabase = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $configuredDatabase === '' || $configuredDatabase === ':memory:') {
            throw new RuntimeException('Apply requires a file-backed SQLite default connection.');
        }

        $sourcePath = realpath($configuredDatabase);

        if ($sourcePath === false || ! is_file($sourcePath) || ! is_readable($sourcePath) || ! is_writable($sourcePath)) {
            throw new RuntimeException('The configured SQLite database must exist and be readable and writable.');
        }

        $this->assertNoActiveSidecars($sourcePath);
        $this->assertIntegrity($sourcePath);
        $this->assertMigrationsCurrent();

        $validated = $this->validateDatabaseRows($rows);
        $before = $this->snapshot();
        $urlsBefore = $this->urlInventory();
        $state = $this->mappingState($validated, $before);

        if ($state === 'applied') {
            $this->assertAppliedAssignments($validated);
            $this->renderApplicationSummary(0, self::EXPECTED_APPROVED, self::EXPECTED_REVIEW, null, null, $before);
            $this->components->info('PASS: approved GeoCore mapping is already applied; no database or backup changes were made.');

            return self::SUCCESS;
        }

        if ($state !== 'initial') {
            throw new RuntimeException('Unknown or intermediate GeoCore mapping state; apply refused without override.');
        }

        $sourceHashBefore = $this->fileHash($sourcePath);
        $backup = $this->createVerifiedBackup($sourcePath, $before);
        $mutationStarted = false;

        try {
            $mutationStarted = true;
            [$firstPass, $after] = DB::transaction(function () use ($validated, $before, $urlsBefore): array {
                $firstPass = $this->applyApprovedRows($validated['approved'], false);
                $after = $this->snapshot();
                $urlsAfter = $this->urlInventory();
                $secondPass = $this->applyApprovedRows($validated['approved'], false);

                $this->validateResult(
                    $validated,
                    $before,
                    $after,
                    $urlsBefore,
                    $urlsAfter,
                    $firstPass,
                    $secondPass,
                );

                return [$firstPass, $after];
            });

            $this->assertIntegrity($sourcePath);
            $this->assertAppliedAssignments($validated);
            $final = $this->snapshot();

            if ($final !== $after) {
                throw new RuntimeException('Post-commit database snapshot differs from the validated transaction result.');
            }
        } catch (Throwable $exception) {
            if ($mutationStarted) {
                $this->restoreVerifiedBackup($backup['path'], $sourcePath, $backup['hash'], $before);

                throw new RuntimeException(
                    'Apply failed; working SQLite was restored from the verified backup. Cause: '.$exception->getMessage(),
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $sourceHashAfter = $this->fileHash($sourcePath);
        $this->renderApplicationSummary(
            $firstPass['updated'],
            $firstPass['already_mapped'],
            count($validated['review']),
            $sourceHashBefore,
            $sourceHashAfter,
            $after,
            $backup,
        );
        $this->components->info('PASS: approved GeoCore mappings were applied to the local SQLite database.');

        return self::SUCCESS;
    }

    private function assertNoActiveSidecars(string $sourcePath): void
    {
        foreach (['-wal', '-journal'] as $suffix) {
            if (is_file($sourcePath.$suffix) && filesize($sourcePath.$suffix) > 0) {
                throw new RuntimeException("SQLite {$suffix} file is active; apply refused.");
            }
        }
    }

    private function assertIntegrity(string $databasePath): void
    {
        $pdo = $this->readOnlyPdo($databasePath);
        $result = $pdo->query('PRAGMA integrity_check')?->fetchColumn();

        if ($result !== 'ok') {
            throw new RuntimeException('SQLite integrity check failed.');
        }
    }

    private function assertMigrationsCurrent(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('migrations')) {
            throw new RuntimeException('Migrations table is missing.');
        }

        $files = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->sort()
            ->values()
            ->all();
        $applied = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();

        if ($files !== $applied) {
            throw new RuntimeException('Database migrations are not in the expected current state.');
        }
    }

    /**
     * @param  array{approved: list<array<string, mixed>>, review: list<array<string, mixed>>}  $validated
     * @param  array<string, mixed>  $snapshot
     */
    private function mappingState(array $validated, array $snapshot): string
    {
        $approvedLinked = collect($validated['approved'])->filter(function (array $row): bool {
            return (int) DB::table('cities')->where('id', (int) $row['city_id'])->value('geo_municipality_id')
                === (int) $row['_municipality_id'];
        })->count();
        $reviewUnresolved = collect($validated['review'])->every(function (array $row): bool {
            return DB::table('cities')->where('id', (int) $row['city_id'])->value('geo_municipality_id') === null;
        });

        if ($snapshot['mapped_cities'] === self::EXPECTED_CITIES_BEFORE
            && $snapshot['unresolved_cities'] === self::EXPECTED_ROWS
            && $snapshot['facilities_total'] === self::EXPECTED_FACILITIES
            && $snapshot['facilities_assigned'] === self::EXPECTED_FACILITIES_ASSIGNED_BEFORE
            && $approvedLinked === 0
            && $reviewUnresolved) {
            return 'initial';
        }

        if ($snapshot['mapped_cities'] === self::EXPECTED_CITIES_AFTER
            && $snapshot['unresolved_cities'] === self::EXPECTED_REVIEW
            && $snapshot['facilities_total'] === self::EXPECTED_FACILITIES
            && $snapshot['facilities_assigned'] === self::EXPECTED_FACILITIES_ASSIGNED_AFTER
            && $snapshot['facilities_unassigned'] === self::EXPECTED_FACILITIES_UNASSIGNED_AFTER
            && $approvedLinked === self::EXPECTED_APPROVED
            && $reviewUnresolved) {
            return 'applied';
        }

        return 'unknown';
    }

    /** @param array{approved: list<array<string, mixed>>, review: list<array<string, mixed>>} $validated */
    private function assertAppliedAssignments(array $validated): void
    {
        foreach ($validated['approved'] as $row) {
            $municipalityId = DB::table('cities')->where('id', (int) $row['city_id'])->value('geo_municipality_id');

            if ((int) $municipalityId !== (int) $row['_municipality_id']) {
                throw new RuntimeException("Approved assignment mismatch for city_id {$row['city_id']}.");
            }
        }

        foreach ($validated['review'] as $row) {
            if (DB::table('cities')->where('id', (int) $row['city_id'])->value('geo_municipality_id') !== null) {
                throw new RuntimeException("REVIEW city was unexpectedly mapped: {$row['city_id']}.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sourceSnapshot
     * @return array{path: string, name: string, hash: string}
     */
    private function createVerifiedBackup(string $sourcePath, array $sourceSnapshot): array
    {
        $requestedDirectory = (string) ($this->option('backup-dir') ?: dirname(base_path()).DIRECTORY_SEPARATOR.'database-backups');
        $backupDirectory = realpath($requestedDirectory);

        if ($backupDirectory === false || ! is_dir($backupDirectory) || ! is_writable($backupDirectory)) {
            throw new RuntimeException('Backup directory must already exist and be writable.');
        }

        $publicPath = realpath(public_path());

        if ($publicPath !== false && $this->pathIsWithin($backupDirectory, $publicPath)) {
            throw new RuntimeException('Backup directory must be outside the public webroot.');
        }

        $name = 'pflegeindex-before-geocore-approved-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite';
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$name;

        if (file_exists($backupPath)) {
            throw new RuntimeException('Refusing to overwrite an existing backup.');
        }

        $pdo = DB::connection()->getPdo();
        DB::statement('VACUUM INTO '.$pdo->quote($backupPath));

        if (! is_file($backupPath) || filesize($backupPath) === 0) {
            throw new RuntimeException('SQLite backup was not created correctly.');
        }

        $mode = fileperms($sourcePath);

        if ($mode !== false) {
            @chmod($backupPath, $mode & 0777);
        }

        $this->verifyBackup($backupPath, $sourceSnapshot);

        return ['path' => $backupPath, 'name' => $name, 'hash' => $this->fileHash($backupPath)];
    }

    /** @param array<string, mixed> $sourceSnapshot */
    private function verifyBackup(string $backupPath, array $sourceSnapshot): void
    {
        $pdo = $this->readOnlyPdo($backupPath);

        if ($pdo->query('PRAGMA integrity_check')?->fetchColumn() !== 'ok') {
            throw new RuntimeException('Backup SQLite integrity check failed.');
        }

        $counts = [
            'cities' => (int) $pdo->query('SELECT COUNT(*) FROM cities')->fetchColumn(),
            'mapped_cities' => (int) $pdo->query('SELECT COUNT(*) FROM cities WHERE geo_municipality_id IS NOT NULL')->fetchColumn(),
            'facilities_total' => (int) $pdo->query('SELECT COUNT(*) FROM facilities')->fetchColumn(),
            'districts' => (int) $pdo->query('SELECT COUNT(*) FROM geo_districts')->fetchColumn(),
            'municipalities' => (int) $pdo->query('SELECT COUNT(*) FROM geo_municipalities')->fetchColumn(),
        ];
        $expected = [
            'cities' => self::EXPECTED_CITIES,
            'mapped_cities' => $sourceSnapshot['mapped_cities'],
            'facilities_total' => $sourceSnapshot['facilities_total'],
            'districts' => self::EXPECTED_DISTRICTS,
            'municipalities' => 413,
        ];

        if ($counts !== $expected) {
            throw new RuntimeException('Backup key counts do not match the source database.');
        }
    }

    /** @param array<string, mixed> $expectedSnapshot */
    private function restoreVerifiedBackup(
        string $backupPath,
        string $sourcePath,
        string $backupHash,
        array $expectedSnapshot,
    ): void {
        DB::disconnect();

        foreach ([$sourcePath.'-wal', $sourcePath.'-shm', $sourcePath.'-journal'] as $sidecar) {
            if (is_file($sidecar) && ! unlink($sidecar)) {
                throw new RuntimeException('Unable to remove SQLite sidecar before rollback.');
            }
        }

        $restorePath = $sourcePath.'.restore-'.bin2hex(random_bytes(4));

        if (! copy($backupPath, $restorePath) || $this->fileHash($restorePath) !== $backupHash) {
            @unlink($restorePath);

            throw new RuntimeException('Unable to prepare verified rollback copy.');
        }

        if (! unlink($sourcePath)) {
            @unlink($restorePath);

            throw new RuntimeException('Unable to replace working SQLite during rollback.');
        }

        if (! rename($restorePath, $sourcePath)) {
            if (! copy($backupPath, $sourcePath)) {
                throw new RuntimeException('Critical rollback failure while restoring working SQLite.');
            }

            @unlink($restorePath);
        }

        DB::purge();

        if ($this->fileHash($sourcePath) !== $backupHash) {
            throw new RuntimeException('Restored SQLite hash does not match the verified backup.');
        }

        $this->assertIntegrity($sourcePath);
        $restored = $this->snapshot();

        foreach (['mapped_cities', 'unresolved_cities', 'facilities_total', 'facilities_assigned', 'facilities_unassigned'] as $key) {
            if ($restored[$key] !== $expectedSnapshot[$key]) {
                throw new RuntimeException("Restored SQLite count mismatch: {$key}.");
            }
        }
    }

    private function readOnlyPdo(string $databasePath): PDO
    {
        $pdo = new PDO('sqlite:file:'.str_replace('\\', '/', $databasePath).'?mode=ro', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA query_only=ON');

        return $pdo;
    }

    private function pathIsWithin(string $candidate, string $parent): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/').'/';
        $parent = rtrim(str_replace('\\', '/', $parent), '/').'/';

        return str_starts_with(strtolower($candidate), strtolower($parent));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{path: string, name: string, hash: string}|null  $backup
     */
    private function renderApplicationSummary(
        int $applied,
        int $alreadyMapped,
        int $review,
        ?string $sourceHashBefore,
        ?string $sourceHashAfter,
        array $snapshot,
        ?array $backup = null,
    ): void {
        $this->line('Applied: '.$applied);
        $this->line('Already mapped: '.$alreadyMapped);
        $this->line('Skipped REVIEW: '.$review);
        $this->line('Conflicts: 0');
        $this->line('Errors: 0');
        $this->line('Mapped cities: '.$snapshot['mapped_cities']);
        $this->line('Unresolved cities: '.$snapshot['unresolved_cities']);
        $this->line('Facilities on district pages: '.$snapshot['facilities_assigned']);
        $this->line('Facilities without district: '.$snapshot['facilities_unassigned']);
        $this->line('Empty district pages: '.$snapshot['empty_districts']);

        if ($backup !== null) {
            $this->line('Backup: '.$backup['name']);
            $this->line('Source SHA-256 before: '.$sourceHashBefore);
            $this->line('Backup SHA-256: '.$backup['hash']);
            $this->line('Source SHA-256 after: '.$sourceHashAfter);
            $this->line('Rollback: not required');
        }
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{approved: list<array<string, mixed>>, review: list<array<string, mixed>>}
     */
    private function validateDatabaseRows(array $rows): array
    {
        $approved = [];
        $review = [];

        foreach ($rows as $row) {
            $city = DB::table('cities')->where('id', (int) $row['city_id'])->first();

            if ($city === null) {
                throw new RuntimeException("Unknown city_id: {$row['city_id']}");
            }

            if ((string) $city->name !== $row['city_name']) {
                throw new RuntimeException("City name mismatch for city_id {$row['city_id']}.");
            }

            $facilityCount = DB::table('facilities')->where('city_id', $city->id)->count();

            if ($facilityCount !== (int) $row['facility_count']) {
                throw new RuntimeException("Facility count mismatch for city_id {$row['city_id']}.");
            }

            $municipality = DB::table('geo_municipalities')
                ->join('geo_districts', 'geo_districts.id', '=', 'geo_municipalities.district_id')
                ->where('geo_municipalities.ags', $row['municipality_ags'])
                ->select([
                    'geo_municipalities.id',
                    'geo_municipalities.name',
                    'geo_districts.ags as district_ags',
                    'geo_districts.name as district_name',
                ])
                ->first();

            if ($municipality === null) {
                throw new RuntimeException("Unknown municipality AGS: {$row['municipality_ags']}");
            }

            if ((string) $municipality->name !== $row['municipality_name']) {
                throw new RuntimeException("Municipality name mismatch for AGS {$row['municipality_ags']}.");
            }

            if ((string) $municipality->district_ags !== $row['district_ags']
                || (string) $municipality->district_name !== $row['district_name']) {
                throw new RuntimeException("District mismatch for municipality AGS {$row['municipality_ags']}.");
            }

            $row['_municipality_id'] = (int) $municipality->id;

            if ($row['decision'] === 'APPROVED') {
                if ($city->geo_municipality_id !== null
                    && (int) $city->geo_municipality_id !== (int) $municipality->id) {
                    throw new RuntimeException("Conflicting existing mapping for city_id {$row['city_id']}; no overwrite allowed.");
                }

                $approved[] = $row;
            } else {
                $review[] = $row;
            }
        }

        return ['approved' => $approved, 'review' => $review];
    }

    /**
     * @param  list<array<string, mixed>>  $approved
     * @return array{updated: int, already_mapped: int}
     */
    private function applyApprovedRows(array $approved, bool $transactional = true): array
    {
        $apply = function () use ($approved): array {
            $updated = 0;
            $alreadyMapped = 0;

            foreach ($approved as $row) {
                $city = DB::table('cities')->where('id', (int) $row['city_id'])->first();

                if ($city === null) {
                    throw new RuntimeException("City disappeared during mapping: {$row['city_id']}");
                }

                if ($city->geo_municipality_id !== null) {
                    if ((int) $city->geo_municipality_id !== (int) $row['_municipality_id']) {
                        throw new RuntimeException("Conflicting existing mapping for city_id {$row['city_id']}; no overwrite allowed.");
                    }

                    $alreadyMapped++;

                    continue;
                }

                DB::table('cities')->where('id', (int) $row['city_id'])->update([
                    'geo_municipality_id' => (int) $row['_municipality_id'],
                    'geo_match_status' => 'manual_approved',
                    'geo_match_method' => 'approved_mapping',
                    'geo_match_confidence' => strtolower((string) $row['confidence']),
                    'geo_requires_manual_review' => false,
                    'updated_at' => now(),
                ]);
                $updated++;
            }

            return ['updated' => $updated, 'already_mapped' => $alreadyMapped];
        };

        return $transactional ? DB::transaction($apply) : $apply();
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $districtRows = DB::table('geo_districts')
            ->leftJoin('geo_municipalities', 'geo_municipalities.district_id', '=', 'geo_districts.id')
            ->leftJoin('cities', 'cities.geo_municipality_id', '=', 'geo_municipalities.id')
            ->leftJoin('facilities', 'facilities.city_id', '=', 'cities.id')
            ->groupBy('geo_districts.id', 'geo_districts.ags', 'geo_districts.name')
            ->orderBy('geo_districts.ags')
            ->select([
                'geo_districts.ags',
                'geo_districts.name',
                DB::raw('COUNT(DISTINCT cities.id) as city_count'),
                DB::raw('COUNT(DISTINCT facilities.id) as facility_count'),
            ])
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->ags => [
                'name' => (string) $row->name,
                'cities' => (int) $row->city_count,
                'facilities' => (int) $row->facility_count,
            ]])
            ->all();

        $assignedFacilities = DB::table('facilities')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->whereNotNull('cities.geo_municipality_id')
            ->count();

        return [
            'mapped_cities' => DB::table('cities')->whereNotNull('geo_municipality_id')->count(),
            'unresolved_cities' => DB::table('cities')->whereNull('geo_municipality_id')->count(),
            'manual_review' => DB::table('cities')->where('geo_requires_manual_review', true)->count(),
            'unmatched' => DB::table('cities')->where('geo_match_status', 'unmatched')->count(),
            'facilities_total' => DB::table('facilities')->count(),
            'facilities_assigned' => $assignedFacilities,
            'facilities_unassigned' => DB::table('facilities')->count() - $assignedFacilities,
            'districts' => $districtRows,
            'empty_districts' => collect($districtRows)->where('facilities', 0)->count(),
        ];
    }

    /** @return array{cities: list<string>, facilities: list<string>, districts: list<string>} */
    private function urlInventory(): array
    {
        $cities = DB::table('cities')
            ->join('facilities', 'facilities.city_id', '=', 'cities.id')
            ->where('cities.state_slug', 'brandenburg')
            ->distinct()
            ->orderBy('cities.slug')
            ->pluck('cities.slug')
            ->map(static fn (string $slug): string => "/pflegeeinrichtungen/brandenburg/{$slug}")
            ->all();
        $facilities = DB::table('facilities')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->where('cities.state_slug', 'brandenburg')
            ->orderBy('cities.slug')
            ->orderBy('facilities.slug')
            ->get(['cities.slug as city_slug', 'facilities.slug'])
            ->map(static fn (object $row): string => "/pflegeeinrichtungen/brandenburg/{$row->city_slug}/{$row->slug}")
            ->all();
        $districts = DB::table('geo_districts')
            ->whereIn('type', ['landkreis', 'kreisfreie_stadt'])
            ->orderBy('slug')
            ->pluck('slug')
            ->map(static fn (string $slug): string => "/brandenburg/landkreis/{$slug}.html")
            ->all();

        return ['cities' => $cities, 'facilities' => $facilities, 'districts' => $districts];
    }

    /**
     * @param  array{approved: list<array<string, mixed>>, review: list<array<string, mixed>>}  $validated
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, list<string>>  $urlsBefore
     * @param  array<string, list<string>>  $urlsAfter
     * @param  array{updated: int, already_mapped: int}  $firstPass
     * @param  array{updated: int, already_mapped: int}  $secondPass
     */
    private function validateResult(
        array $validated,
        array $before,
        array $after,
        array $urlsBefore,
        array $urlsAfter,
        array $firstPass,
        array $secondPass,
    ): void {
        $expected = [
            'mapped before' => [$before['mapped_cities'], self::EXPECTED_CITIES_BEFORE],
            'mapped after' => [$after['mapped_cities'], self::EXPECTED_CITIES_AFTER],
            'facilities assigned before' => [$before['facilities_assigned'], self::EXPECTED_FACILITIES_ASSIGNED_BEFORE],
            'facilities assigned after' => [$after['facilities_assigned'], self::EXPECTED_FACILITIES_ASSIGNED_AFTER],
            'facilities unassigned after' => [$after['facilities_unassigned'], self::EXPECTED_FACILITIES_UNASSIGNED_AFTER],
            'facilities total before' => [$before['facilities_total'], self::EXPECTED_FACILITIES],
            'facilities total after' => [$after['facilities_total'], self::EXPECTED_FACILITIES],
            'unresolved cities after' => [$after['unresolved_cities'], self::EXPECTED_REVIEW],
            'unmatched cities after' => [$after['unmatched'], 0],
            'first-pass updates' => [$firstPass['updated'], self::EXPECTED_APPROVED],
            'second-pass updates' => [$secondPass['updated'], 0],
            'second-pass already mapped' => [$secondPass['already_mapped'], self::EXPECTED_APPROVED],
        ];

        foreach ($expected as $label => [$actual, $required]) {
            if ($actual !== $required) {
                throw new RuntimeException("Unexpected {$label}: {$actual}; expected {$required}.");
            }
        }

        foreach ($validated['review'] as $row) {
            $city = DB::table('cities')->where('id', (int) $row['city_id'])->first();

            if ($city === null || $city->geo_municipality_id !== null || ! (bool) $city->geo_requires_manual_review) {
                throw new RuntimeException("REVIEW city was changed: {$row['city_id']}");
            }
        }

        $affectedDistricts = 0;
        $facilityDelta = 0;
        $expectedDeltas = collect($validated['approved'])->groupBy('district_ags')->map(
            static fn ($rows): array => [
                'cities' => $rows->count(),
                'facilities' => $rows->sum(static fn (array $row): int => (int) $row['facility_count']),
            ]
        );

        foreach ($expectedDeltas as $districtAgs => $delta) {
            $beforeDistrict = $before['districts'][$districtAgs] ?? null;
            $afterDistrict = $after['districts'][$districtAgs] ?? null;

            if ($beforeDistrict === null || $afterDistrict === null
                || $delta['cities'] !== $afterDistrict['cities'] - $beforeDistrict['cities']
                || $delta['facilities'] !== $afterDistrict['facilities'] - $beforeDistrict['facilities']) {
                throw new RuntimeException("District delta mismatch for AGS {$districtAgs}.");
            }

            $affectedDistricts++;
            $facilityDelta += $delta['facilities'];
        }

        if ($affectedDistricts !== self::EXPECTED_AFFECTED_DISTRICTS || $facilityDelta !== 394) {
            throw new RuntimeException('Affected district or facility delta does not match the approved mapping.');
        }

        $joinedFacilities = DB::table('facilities')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->join('geo_municipalities', 'geo_municipalities.id', '=', 'cities.geo_municipality_id')
            ->join('geo_districts', 'geo_districts.id', '=', 'geo_municipalities.district_id');

        if ((clone $joinedFacilities)->count() !== (clone $joinedFacilities)->distinct()->count('facilities.id')) {
            throw new RuntimeException('A facility would appear more than once on district pages.');
        }

        if ($urlsBefore !== $urlsAfter) {
            throw new RuntimeException('Sitemap URL inventory changed during the mapping dry-run.');
        }

        $expectedUrlCounts = [
            'cities' => self::EXPECTED_CITIES,
            'facilities' => self::EXPECTED_FACILITIES,
            'districts' => self::EXPECTED_DISTRICTS,
        ];

        foreach ($expectedUrlCounts as $type => $expectedCount) {
            if (count($urlsAfter[$type]) !== $expectedCount) {
                throw new RuntimeException("Unexpected {$type} sitemap inventory count.");
            }
        }

        if ($after['empty_districts'] !== 0) {
            throw new RuntimeException('The simulated mapping still has an empty district page.');
        }
    }

    /**
     * @param  array{approved: list<array<string, mixed>>, review: list<array<string, mixed>>}  $validated
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array{updated: int, already_mapped: int}  $firstPass
     * @param  array{updated: int, already_mapped: int}  $secondPass
     */
    private function renderResult(
        array $validated,
        array $before,
        array $after,
        array $firstPass,
        array $secondPass,
    ): void {
        $this->components->info('Approved mapping validated and applied to the isolated copy.');
        $this->table(
            ['Metric', 'Before', 'After', 'Delta'],
            [
                ['Mapped cities', $before['mapped_cities'], $after['mapped_cities'], $after['mapped_cities'] - $before['mapped_cities']],
                ['Unresolved cities', $before['unresolved_cities'], $after['unresolved_cities'], $after['unresolved_cities'] - $before['unresolved_cities']],
                ['Facilities on district pages', $before['facilities_assigned'], $after['facilities_assigned'], $after['facilities_assigned'] - $before['facilities_assigned']],
                ['Facilities without district', $before['facilities_unassigned'], $after['facilities_unassigned'], $after['facilities_unassigned'] - $before['facilities_unassigned']],
                ['Empty district pages', $before['empty_districts'], $after['empty_districts'], $after['empty_districts'] - $before['empty_districts']],
            ],
        );

        $districtRows = [];

        foreach ($after['districts'] as $ags => $district) {
            $beforeDistrict = $before['districts'][$ags];
            $cityDelta = $district['cities'] - $beforeDistrict['cities'];
            $facilityDelta = $district['facilities'] - $beforeDistrict['facilities'];

            if ($cityDelta === 0 && $facilityDelta === 0) {
                continue;
            }

            $districtRows[] = [
                $ags,
                $district['name'],
                $beforeDistrict['cities'],
                $district['cities'],
                $cityDelta,
                $beforeDistrict['facilities'],
                $district['facilities'],
                $facilityDelta,
            ];
        }

        $this->table(
            ['AGS', 'District', 'Cities before', 'Cities after', 'City delta', 'Facilities before', 'Facilities after', 'Facility delta'],
            $districtRows,
        );
        $this->line('APPROVED applied: '.count($validated['approved']));
        $this->line('REVIEW skipped: '.count($validated['review']));
        $this->line('First pass updated: '.$firstPass['updated']);
        $this->line('Second pass updated: '.$secondPass['updated']);
        $this->line('Second pass already mapped: '.$secondPass['already_mapped']);
        $this->line('Sitemap URL inventory: unchanged');
        $this->line('New district pages: 0');
        $this->line('Facility duplicates on district pages: 0');
    }

    private function fileHash(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new RuntimeException('Unable to calculate SQLite SHA-256.');
        }

        return $hash;
    }

    private function removeTemporaryCopy(string $directory, string $databasePath): void
    {
        foreach ([$databasePath.'-wal', $databasePath.'-shm', $databasePath.'-journal', $databasePath] as $path) {
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException('Unable to remove the isolated SQLite copy.');
            }
        }

        if (is_dir($directory) && ! rmdir($directory)) {
            throw new RuntimeException('Unable to remove the isolated SQLite directory.');
        }
    }
}
