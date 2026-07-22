<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ApprovedGeoCoreMappingDryRunTest extends TestCase
{
    private static ?string $fixtureDatabase = null;

    private string $originalDatabase;

    private string $sourceDatabase;

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

    public function test_command_requires_explicit_dry_run_option(): void
    {
        $this->artisan('geocore:apply-approved-mapping')
            ->assertFailed()
            ->expectsOutputToContain('This command is dry-run only');
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
