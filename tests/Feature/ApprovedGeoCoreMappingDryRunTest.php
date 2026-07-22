<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyApprovedGeoCoreMapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ApprovedGeoCoreMappingDryRunTest extends TestCase
{
    private static ?string $fixtureDatabase = null;

    private string $originalDatabase;

    private string $sourceDatabase;

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = (string) config('database.connections.sqlite.database');

        if (self::$fixtureDatabase === null) {
            self::$fixtureDatabase = $this->createFixtureDatabase();
        }

        $source = tempnam(sys_get_temp_dir(), 'geocore-approved-source-');

        if ($source === false || ! copy(self::$fixtureDatabase, $source)) {
            throw new RuntimeException('Unable to create GeoCore approved mapping test database.');
        }

        $this->sourceDatabase = $source;
        $this->backupDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'geocore-approved-backups-'.bin2hex(random_bytes(6));

        if (! mkdir($this->backupDirectory, 0700)) {
            throw new RuntimeException('Unable to create GeoCore backup test directory.');
        }

        config(['database.connections.sqlite.database' => $this->sourceDatabase]);
        DB::purge('sqlite');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        config(['database.connections.sqlite.database' => $this->originalDatabase]);
        DB::purge('sqlite');

        foreach ([$this->sourceDatabase.'-wal', $this->sourceDatabase.'-shm', $this->sourceDatabase.'-journal', $this->sourceDatabase] as $path) {
            if (isset($this->sourceDatabase) && is_file($path)) {
                unlink($path);
            }
        }

        foreach (glob($this->backupDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->backupDirectory)) {
            rmdir($this->backupDirectory);
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$fixtureDatabase !== null && is_file(self::$fixtureDatabase)) {
            unlink(self::$fixtureDatabase);
        }

        self::$fixtureDatabase = null;
        parent::tearDownAfterClass();
    }

    public function test_dry_run_applies_expected_mapping_only_to_copy_and_removes_it(): void
    {
        $beforeHash = hash_file('sha256', $this->sourceDatabase);

        $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Mapped cities')
            ->expectsOutputToContain('APPROVED applied: 75')
            ->expectsOutputToContain('REVIEW skipped: 2')
            ->expectsOutputToContain('Temporary copy removed: yes')
            ->expectsOutputToContain('PASS: approved GeoCore mapping dry-run completed');

        $this->assertSame($beforeHash, hash_file('sha256', $this->sourceDatabase));
        $this->assertSame(180, DB::table('cities')->whereNotNull('geo_municipality_id')->count());
        $this->assertSame(77, DB::table('cities')->whereNull('geo_municipality_id')->count());
    }

    public function test_dry_run_reports_expected_facility_district_and_sitemap_results(): void
    {
        $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Facilities on district pages')
            ->expectsOutputToContain('Cottbus, Stadt')
            ->expectsOutputToContain('Sitemap URL inventory: unchanged')
            ->expectsOutputToContain('New district pages: 0')
            ->expectsOutputToContain('Facility duplicates on district pages: 0');
    }

    public function test_dry_run_proves_second_pass_is_idempotent(): void
    {
        $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('First pass updated: 75')
            ->expectsOutputToContain('Second pass updated: 0')
            ->expectsOutputToContain('Second pass already mapped: 75');
    }

    public function test_command_requires_one_explicit_mode_and_does_not_change_database(): void
    {
        $beforeHash = hash_file('sha256', $this->sourceDatabase);

        $this->artisan('geocore:apply-approved-mapping')
            ->assertFailed()
            ->expectsOutputToContain('Pass exactly one explicit mode');

        $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true, '--apply' => true])
            ->assertFailed()
            ->expectsOutputToContain('Pass exactly one explicit mode');
        $this->assertSame($beforeHash, hash_file('sha256', $this->sourceDatabase));
        $this->assertSame([], $this->backupFiles());
    }

    public function test_production_environment_is_blocked(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true])
                ->assertFailed()
                ->expectsOutputToContain('blocked in production');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_duplicate_city_id_fails_without_writing_source_database(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            $rows[1]['city_id'] = $rows[0]['city_id'];
        });

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'Duplicate city_id');
    }

    public function test_unknown_city_fails_without_writing_source_database(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            $rows[0]['city_id'] = '999999';
        });

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'Unknown city_id');
    }

    public function test_unknown_municipality_fails_without_writing_source_database(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            $rows[0]['municipality_ags'] = '12999999';
        });

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'Unknown municipality AGS');
    }

    public function test_wrong_district_relation_fails_without_writing_source_database(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            $rows[0]['district_ags'] = '12999';
        });

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'District mismatch');
    }

    public function test_missing_column_fails_before_database_copy(): void
    {
        $mapping = $this->temporaryMapping(
            static function (array &$rows): void {
                foreach ($rows as &$row) {
                    unset($row['evidence_code']);
                }
            },
        );

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'columns do not match');
    }

    public function test_invalid_status_fails_before_database_copy(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            $rows[0]['decision'] = 'INVALID';
        });

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'Invalid decision');
    }

    public function test_wrong_row_count_fails_before_database_copy(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            array_pop($rows);
        });

        $this->assertMappingFailureLeavesSourceUntouched($mapping, 'exactly 77 data rows');
    }

    public function test_conflicting_existing_mapping_is_not_overwritten(): void
    {
        $expectedMunicipality = DB::table('geo_municipalities')->where('ags', '12068320')->value('id');
        $differentMunicipality = DB::table('geo_municipalities')->where('id', '!=', $expectedMunicipality)->value('id');
        $this->assertNotNull($differentMunicipality);
        DB::table('cities')->where('id', 2)->update(['geo_municipality_id' => $differentMunicipality]);
        DB::disconnect('sqlite');
        $beforeHash = hash_file('sha256', $this->sourceDatabase);

        $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true])
            ->assertFailed()
            ->expectsOutputToContain('Conflicting existing mapping for city_id 2');

        $this->assertSame($beforeHash, hash_file('sha256', $this->sourceDatabase));
        $this->assertSame((int) $differentMunicipality, (int) DB::table('cities')->where('id', 2)->value('geo_municipality_id'));
    }

    public function test_review_rows_remain_unmapped_in_the_working_database(): void
    {
        $this->artisan('geocore:apply-approved-mapping', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('REVIEW skipped: 2');

        $reviewCities = DB::table('cities')->whereIn('id', [99, 190])->orderBy('id')->get();
        $this->assertCount(2, $reviewCities);

        foreach ($reviewCities as $city) {
            $this->assertNull($city->geo_municipality_id);
            $this->assertSame(1, (int) $city->geo_requires_manual_review);
        }
    }

    public function test_isolated_copy_preserves_public_urls_breadcrumbs_and_city_ordering(): void
    {
        $copiesBefore = glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'pflegeindex-geocore-*', GLOB_ONLYDIR) ?: [];
        $exitCode = Artisan::call('geocore:apply-approved-mapping', [
            '--dry-run' => true,
            '--keep-copy' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());
        $copiesAfter = glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'pflegeindex-geocore-*', GLOB_ONLYDIR) ?: [];
        $newCopies = array_values(array_diff($copiesAfter, $copiesBefore));
        $this->assertCount(1, $newCopies);
        $copyDirectory = $newCopies[0];
        $copyPath = $copyDirectory.DIRECTORY_SEPARATOR.'database.sqlite';

        try {
            config(['database.connections.sqlite.database' => $copyPath]);
            DB::purge('sqlite');

            $cottbus = DB::table('cities')->where('id', 44)->first();
            $cottbusDistrict = DB::table('geo_districts')->where('ags', '12052')->first();
            $this->assertNotNull($cottbus);
            $this->assertNotNull($cottbusDistrict);

            $cityUrl = route('cities.show', $cottbus->slug);
            $districtUrl = route('districts.show', $cottbusDistrict->slug);
            $this->get($cityUrl)
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.$cityUrl.'">', false)
                ->assertSee($districtUrl, false)
                ->assertSee('BreadcrumbList');
            $this->get($districtUrl)
                ->assertOk()
                ->assertSee($cityUrl, false)
                ->assertSee('72 Pflegeeinrichtungen')
                ->assertSee('"identifier":"12052"', false);

            $barnimDistrict = DB::table('geo_districts')->where('ags', '12060')->first();
            $barnimCities = DB::table('cities')
                ->join('geo_municipalities', 'geo_municipalities.id', '=', 'cities.geo_municipality_id')
                ->where('geo_municipalities.district_id', $barnimDistrict->id)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')->from('facilities')->whereColumn('facilities.city_id', 'cities.id');
                })
                ->orderBy('cities.name')
                ->orderBy('cities.id')
                ->get(['cities.name', 'cities.slug']);
            $districtHtml = $this->get(route('districts.show', $barnimDistrict->slug))->assertOk()->getContent();
            $previousPosition = -1;

            foreach ($barnimCities as $city) {
                $position = strpos($districtHtml, 'href="'.route('cities.show', $city->slug).'"');
                $this->assertNotFalse($position, "Missing city link for {$city->name}");
                $this->assertGreaterThan($previousPosition, $position, "City links are not ordered at {$city->name}");
                $previousPosition = $position;
            }

            $this->get(route('sitemap'))->assertOk()->assertSee($cityUrl, false)->assertSee($districtUrl, false);
        } finally {
            DB::disconnect('sqlite');
            config(['database.connections.sqlite.database' => $this->sourceDatabase]);
            DB::purge('sqlite');
            $this->removeRetainedCopy($copyDirectory, $copyPath);
        }
    }

    public function test_apply_creates_verified_backup_and_applies_only_approved_rows(): void
    {
        $csvHash = hash_file('sha256', base_path('docs/GEOCORE_APPROVED_MAPPING.csv'));

        $this->artisan('geocore:apply-approved-mapping', $this->applyArguments())
            ->assertSuccessful()
            ->expectsOutputToContain('Applied: 75')
            ->expectsOutputToContain('Already mapped: 0')
            ->expectsOutputToContain('Skipped REVIEW: 2')
            ->expectsOutputToContain('Conflicts: 0')
            ->expectsOutputToContain('Errors: 0')
            ->expectsOutputToContain('Rollback: not required')
            ->expectsOutputToContain('PASS: approved GeoCore mappings were applied');

        $this->assertSame(255, DB::table('cities')->whereNotNull('geo_municipality_id')->count());
        $this->assertSame(2, DB::table('cities')->whereNull('geo_municipality_id')->count());
        $this->assertSame(1552, DB::table('facilities')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->whereNotNull('cities.geo_municipality_id')
            ->count());
        $this->assertSame(0, DB::table('cities')->where('geo_match_status', 'unmatched')->count());
        $this->assertNull(DB::table('cities')->where('id', 99)->value('geo_municipality_id'));
        $this->assertNull(DB::table('cities')->where('id', 190)->value('geo_municipality_id'));
        $this->assertSame($csvHash, hash_file('sha256', base_path('docs/GEOCORE_APPROVED_MAPPING.csv')));

        $backups = $this->backupFiles();
        $this->assertCount(1, $backups);
        $this->assertStringNotContainsString(str_replace('\\', '/', public_path()), str_replace('\\', '/', $backups[0]));
        $pdo = $this->readOnlyTestPdo($backups[0]);
        $this->assertSame('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertSame(180, (int) $pdo->query('SELECT COUNT(*) FROM cities WHERE geo_municipality_id IS NOT NULL')->fetchColumn());
        $this->assertSame(1557, (int) $pdo->query('SELECT COUNT(*) FROM facilities')->fetchColumn());
    }

    public function test_apply_is_idempotent_and_does_not_create_a_second_backup(): void
    {
        $this->artisan('geocore:apply-approved-mapping', $this->applyArguments())->assertSuccessful();
        DB::disconnect('sqlite');
        $afterFirstHash = hash_file('sha256', $this->sourceDatabase);
        $backups = $this->backupFiles();

        $this->artisan('geocore:apply-approved-mapping', $this->applyArguments())
            ->assertSuccessful()
            ->expectsOutputToContain('Applied: 0')
            ->expectsOutputToContain('Already mapped: 75')
            ->expectsOutputToContain('Skipped REVIEW: 2')
            ->expectsOutputToContain('already applied');

        DB::disconnect('sqlite');
        $this->assertSame($afterFirstHash, hash_file('sha256', $this->sourceDatabase));
        $this->assertSame($backups, $this->backupFiles());
    }

    public function test_unknown_initial_state_blocks_apply_before_backup(): void
    {
        DB::table('cities')->where('id', 1)->update(['geo_municipality_id' => null]);
        DB::disconnect('sqlite');
        $beforeHash = hash_file('sha256', $this->sourceDatabase);

        $this->artisan('geocore:apply-approved-mapping', $this->applyArguments())
            ->assertFailed()
            ->expectsOutputToContain('Unknown or intermediate GeoCore mapping state');

        DB::disconnect('sqlite');
        $this->assertSame($beforeHash, hash_file('sha256', $this->sourceDatabase));
        $this->assertSame([], $this->backupFiles());
    }

    public function test_outdated_migration_state_blocks_apply_before_backup(): void
    {
        DB::table('migrations')->orderByDesc('id')->limit(1)->delete();
        DB::disconnect('sqlite');

        $this->artisan('geocore:apply-approved-mapping', $this->applyArguments())
            ->assertFailed()
            ->expectsOutputToContain('migrations are not in the expected current state');

        $this->assertSame([], $this->backupFiles());
        $this->assertSame(180, DB::table('cities')->whereNotNull('geo_municipality_id')->count());
    }

    public function test_post_check_failure_automatically_restores_verified_backup(): void
    {
        $mapping = $this->temporaryMapping(function (array &$rows): void {
            $approvedIndex = array_key_first(array_filter($rows, static fn (array $row): bool => $row['decision'] === 'APPROVED'));
            $reviewIndex = array_key_first(array_filter($rows, static fn (array $row): bool => $row['decision'] === 'REVIEW'));
            $rows[$approvedIndex]['decision'] = 'REVIEW';
            $rows[$reviewIndex]['decision'] = 'APPROVED';
        });

        try {
            $arguments = $this->applyArguments();
            $arguments['--mapping'] = $mapping;
            $this->artisan('geocore:apply-approved-mapping', $arguments)
                ->assertFailed()
                ->expectsOutputToContain('working SQLite was restored from the verified backup');

            $backups = $this->backupFiles();
            $this->assertCount(1, $backups);
            DB::disconnect('sqlite');
            $this->assertSame(hash_file('sha256', $backups[0]), hash_file('sha256', $this->sourceDatabase));
            $this->assertSame(180, DB::table('cities')->whereNotNull('geo_municipality_id')->count());
            $this->assertSame(77, DB::table('cities')->whereNull('geo_municipality_id')->count());
            $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);
        } finally {
            if (is_file($mapping)) {
                unlink($mapping);
            }
        }
    }

    public function test_corrupted_or_mismatching_backup_is_rejected(): void
    {
        $corrupted = $this->backupDirectory.DIRECTORY_SEPARATOR.'corrupted.sqlite';
        file_put_contents($corrupted, 'not a sqlite database');
        $command = app(ApplyApprovedGeoCoreMapping::class);
        $method = new \ReflectionMethod($command, 'verifyBackup');
        $snapshot = [
            'mapped_cities' => 180,
            'facilities_total' => 1557,
        ];

        $this->expectException(\Throwable::class);
        $method->invoke($command, $corrupted, $snapshot);
    }

    public function test_valid_sqlite_backup_with_wrong_counts_is_rejected(): void
    {
        $mismatch = $this->backupDirectory.DIRECTORY_SEPARATOR.'mismatch.sqlite';
        $this->assertTrue(copy($this->sourceDatabase, $mismatch));
        $pdo = new \PDO('sqlite:'.$mismatch);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('UPDATE cities SET geo_municipality_id = NULL WHERE geo_municipality_id IS NOT NULL AND id = (SELECT MIN(id) FROM cities WHERE geo_municipality_id IS NOT NULL)');
        $pdo = null;
        $command = app(ApplyApprovedGeoCoreMapping::class);
        $method = new \ReflectionMethod($command, 'verifyBackup');
        $snapshot = [
            'mapped_cities' => 180,
            'facilities_total' => 1557,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup key counts do not match');
        $method->invoke($command, $mismatch, $snapshot);
    }

    public function test_applied_database_keeps_public_pages_and_sitemap_valid(): void
    {
        $this->artisan('geocore:apply-approved-mapping', $this->applyArguments())->assertSuccessful();
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        $cottbus = DB::table('cities')->where('id', 44)->first();
        $district = DB::table('geo_districts')->where('ags', '12052')->first();
        $facility = DB::table('facilities')->where('city_id', 44)->first();
        $cityUrl = route('cities.show', $cottbus->slug);
        $districtUrl = route('districts.show', $district->slug);

        $this->get(route('region.show'))->assertOk();
        $this->get($cityUrl)->assertOk()->assertSee($districtUrl, false)->assertSee('BreadcrumbList');
        $this->get($districtUrl)
            ->assertOk()
            ->assertSee('72 Pflegeeinrichtungen')
            ->assertSee($cityUrl, false)
            ->assertSee('"identifier":"12052"', false);
        $this->get(route('facilities.show', [$cottbus->slug, $facility->slug]))
            ->assertOk()
            ->assertSee($facility->name)
            ->assertSee($cityUrl, false);
        $this->assertSame(257, DB::table('cities')->where('state_slug', 'brandenburg')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('facilities')->whereColumn('facilities.city_id', 'cities.id');
        })->count());
        $this->assertSame(1557, DB::table('facilities')->count());
        $this->assertSame(18, DB::table('geo_districts')->count());
    }

    private function createFixtureDatabase(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'geocore-approved-fixture-');

        if ($path === false) {
            throw new RuntimeException('Unable to create GeoCore fixture database path.');
        }

        config(['database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');
        $exitCode = Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        $this->assertSame(0, $exitCode, Artisan::output());
        $this->seedCurrentDataset();
        $exitCode = Artisan::call('geocore:import-brandenburg');
        $this->assertSame(0, $exitCode, Artisan::output());
        DB::disconnect('sqlite');
        config(['database.connections.sqlite.database' => $this->originalDatabase]);
        DB::purge('sqlite');

        return $path;
    }

    private function seedCurrentDataset(): void
    {
        $mapping = $this->readCsv(storage_path('app/geocore/brandenburg/pflegeindex-city-mapping.csv'));
        $now = now();
        $cityRows = [];
        $facilityRows = [];
        $facilityNumber = 0;

        foreach ($mapping as $row) {
            $cityId = (int) $row['current_city_id'];
            $cityRows[] = [
                'id' => $cityId,
                'name' => $row['current_city_name'],
                'slug' => $row['current_city_slug'],
                'state' => $row['current_state'],
                'state_slug' => $row['current_state_slug'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            for ($localNumber = 1; $localNumber <= (int) $row['facility_count']; $localNumber++) {
                $facilityNumber++;
                $facilityRows[] = [
                    'source_id' => "approved-dry-run-{$facilityNumber}",
                    'city_id' => $cityId,
                    'name' => "Approved Dry Run Einrichtung {$facilityNumber}",
                    'slug' => "approved-dry-run-einrichtung-{$facilityNumber}",
                    'postal_code' => explode(' | ', $row['current_postal_codes'])[0],
                    'address' => "Teststrasse {$localNumber}",
                    'type' => 'Ambulante Pflege',
                    'care_types' => '[]',
                    'features' => '[]',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('cities')->insert($cityRows);

        foreach (array_chunk($facilityRows, 300) as $chunk) {
            DB::table('facilities')->insert($chunk);
        }

        $this->assertSame(257, DB::table('cities')->count());
        $this->assertSame(1557, DB::table('facilities')->count());
    }

    /**
     * @param  callable(list<array<string, string>>&): void  $mutator
     */
    private function temporaryMapping(callable $mutator): string
    {
        $rows = $this->readCsv(base_path('docs/GEOCORE_APPROVED_MAPPING.csv'));
        $mutator($rows);
        $path = tempnam(sys_get_temp_dir(), 'geocore-approved-csv-');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary approved mapping CSV.');
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to write temporary approved mapping CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($rows[0]), ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        fclose($handle);

        return $path;
    }

    private function assertMappingFailureLeavesSourceUntouched(string $mapping, string $message): void
    {
        $beforeHash = hash_file('sha256', $this->sourceDatabase);
        $copiesBefore = glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'pflegeindex-geocore-*', GLOB_ONLYDIR) ?: [];

        try {
            $this->artisan('geocore:apply-approved-mapping', [
                '--dry-run' => true,
                '--mapping' => $mapping,
            ])->assertFailed()->expectsOutputToContain($message);

            $this->assertSame($beforeHash, hash_file('sha256', $this->sourceDatabase));
            $copiesAfter = glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'pflegeindex-geocore-*', GLOB_ONLYDIR) ?: [];
            $this->assertSame($copiesBefore, $copiesAfter);
        } finally {
            if (is_file($mapping)) {
                unlink($mapping);
            }
        }
    }

    private function removeRetainedCopy(string $directory, string $databasePath): void
    {
        foreach ([$databasePath.'-wal', $databasePath.'-shm', $databasePath.'-journal', $databasePath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    /** @return array<string, mixed> */
    private function applyArguments(): array
    {
        return [
            '--apply' => true,
            '--backup-dir' => $this->backupDirectory,
        ];
    }

    /** @return list<string> */
    private function backupFiles(): array
    {
        $files = glob($this->backupDirectory.DIRECTORY_SEPARATOR.'*.sqlite') ?: [];
        sort($files);

        return array_values($files);
    }

    private function readOnlyTestPdo(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:file:'.str_replace('\\', '/', $path).'?mode=ro');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA query_only=ON');

        return $pdo;
    }

    /** @return list<array<string, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to read CSV: {$path}");
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, null, ',', '"', '\\');

        if (! is_array($header)) {
            throw new RuntimeException("Unable to read CSV header: {$path}");
        }

        $rows = [];

        while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $row = array_combine($header, $values);

            if (! is_array($row)) {
                throw new RuntimeException("Unable to parse CSV row: {$path}");
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
