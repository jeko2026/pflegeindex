<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\ContactSuggestion;
use App\Models\Facility;
use App\Models\GeoCountry;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use App\Models\GeoState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeoCoreImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_geocore_schema_and_preserve_leading_zero_ags(): void
    {
        foreach (['geo_countries', 'geo_states', 'geo_districts', 'geo_municipalities'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasColumns('cities', [
            'geo_municipality_id',
            'geo_match_status',
            'geo_match_method',
            'geo_match_confidence',
            'geo_requires_manual_review',
        ]));

        $country = GeoCountry::create([
            'iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Deutschland', 'slug' => 'deutschland',
        ]);
        $state = GeoState::create([
            'country_id' => $country->id, 'ags' => '01', 'name' => 'Schleswig-Holstein', 'slug' => 'schleswig-holstein',
        ]);
        $district = GeoDistrict::create([
            'state_id' => $state->id, 'ags' => '01001', 'name' => 'Flensburg, Stadt', 'slug' => 'flensburg', 'type' => 'kreisfreie_stadt',
        ]);
        $municipality = GeoMunicipality::create([
            'district_id' => $district->id,
            'ags' => '01001000',
            'name' => 'Flensburg, Stadt',
            'normalized_name' => 'flensburg',
            'slug' => 'flensburg',
            'municipality_type' => 'kreisfreie_stadt',
            'postal_code_official' => '24937',
            'source_name' => 'Test',
        ]);
        $city = City::create([
            'name' => 'Flensburg',
            'slug' => 'flensburg',
            'state' => 'Schleswig-Holstein',
            'state_slug' => 'schleswig-holstein',
            'geo_municipality_id' => $municipality->id,
        ]);

        $this->assertSame('01', $state->fresh()->ags);
        $this->assertSame('01001', $district->fresh()->ags);
        $this->assertSame('01001000', $municipality->fresh()->ags);
        $this->assertTrue($municipality->is($city->geoMunicipality));

        $municipality->delete();

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'geo_municipality_id' => null]);
    }

    public function test_dry_run_validates_full_dataset_without_changing_rows_or_timestamps(): void
    {
        $this->seedCurrentDataset();
        $before = City::query()->orderBy('id')->get()->mapWithKeys(
            static fn (City $city): array => [$city->id => $city->getRawOriginal('updated_at')]
        )->all();

        $this->artisan('geocore:import-brandenburg', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry-run completed');

        $this->assertSame(0, GeoCountry::count());
        $this->assertSame(0, GeoMunicipality::count());
        $this->assertSame(0, City::query()->whereNotNull('geo_municipality_id')->count());
        $after = City::query()->orderBy('id')->get()->mapWithKeys(
            static fn (City $city): array => [$city->id => $city->getRawOriginal('updated_at')]
        )->all();
        $this->assertSame($before, $after);
    }

    public function test_import_creates_expected_hierarchy_links_only_safe_cities_and_is_idempotent(): void
    {
        $protected = $this->seedCurrentDataset(withProtectedRecords: true);
        $facilitySnapshot = Facility::query()->orderBy('id')->get([
            'id', 'source_id', 'city_id', 'name', 'slug', 'postal_code', 'address', 'type',
        ])->toJson();

        $this->artisan('geocore:import-brandenburg')->assertSuccessful();

        $this->assertSame(1, GeoCountry::count());
        $this->assertSame(1, GeoState::count());
        $this->assertSame(18, GeoDistrict::count());
        $this->assertSame(413, GeoMunicipality::count());
        $this->assertSame(14, GeoDistrict::query()->where('type', 'landkreis')->count());
        $this->assertSame(4, GeoDistrict::query()->where('type', 'kreisfreie_stadt')->count());
        $this->assertSame(180, City::query()->whereNotNull('geo_municipality_id')->count());
        $this->assertSame(77, City::query()->whereNull('geo_municipality_id')->count());
        $this->assertSame(77, City::query()->where('geo_requires_manual_review', true)->count());
        $this->assertSame(180, City::query()
            ->whereNotNull('geo_municipality_id')
            ->where('geo_match_confidence', 'high')
            ->count());
        $this->assertSame(0, City::query()
            ->whereIn('geo_match_status', ['partial', 'locality', 'ambiguous'])
            ->whereNotNull('geo_municipality_id')
            ->count());

        $linked = City::query()->whereNotNull('geo_municipality_id')->firstOrFail();
        $this->assertNotNull($linked->geoMunicipality);
        $this->assertNotNull($linked->geoMunicipality->district);
        $this->assertNotNull($linked->geoMunicipality->district->state);
        $this->assertNotNull($linked->geoMunicipality->district->state->country);

        $counts = $this->geoCounts();
        $this->artisan('geocore:import-brandenburg')->assertSuccessful();
        $this->assertSame($counts, $this->geoCounts());

        $this->assertSame($facilitySnapshot, Facility::query()->orderBy('id')->get([
            'id', 'source_id', 'city_id', 'name', 'slug', 'postal_code', 'address', 'type',
        ])->toJson());
        $this->assertDatabaseHas('users', ['id' => $protected['user_id']]);
        $this->assertDatabaseHas('contact_suggestions', ['id' => $protected['suggestion_id']]);
        $this->assertSame(1557, Facility::count());

        $city = City::query()->findOrFail(1);
        $facility = Facility::query()->where('city_id', $city->id)->firstOrFail();
        $this->get(route('cities.show', $city))->assertOk()->assertSee($facility->name);
        $this->get(route('facilities.show', [$city, $facility]))->assertOk()->assertSee($facility->name);
    }

    public function test_unknown_mapping_ags_stops_before_any_changes(): void
    {
        $this->seedCurrentDataset();
        $mapping = $this->temporaryCsvCopy('pflegeindex-city-mapping.csv', function (array &$rows): void {
            $rows[1]['official_municipality_ags'] = '12999999';
        });

        try {
            $this->artisan('geocore:import-brandenburg', ['--mapping' => $mapping])->assertFailed();
            $this->assertSame(0, GeoCountry::count());
            $this->assertSame(0, City::query()->whereNotNull('geo_municipality_id')->count());
        } finally {
            @unlink($mapping);
        }
    }

    public function test_database_error_rolls_back_the_whole_import_transaction(): void
    {
        $this->seedCurrentDataset();
        $official = $this->temporaryCsvCopy('official-municipalities.csv', function (array &$rows): void {
            $rows[6]['municipality_slug'] = $rows[5]['municipality_slug'];
        });

        try {
            $this->artisan('geocore:import-brandenburg', ['--official' => $official])->assertFailed();
            $this->assertSame(0, GeoCountry::count());
            $this->assertSame(0, GeoState::count());
            $this->assertSame(0, GeoDistrict::count());
            $this->assertSame(0, GeoMunicipality::count());
            $this->assertSame(0, City::query()->whereNotNull('geo_municipality_id')->count());
        } finally {
            @unlink($official);
        }
    }

    public function test_production_environment_is_always_blocked(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->artisan('geocore:import-brandenburg', ['--dry-run' => true, '--force-local' => true])
                ->assertFailed()
                ->expectsOutputToContain('blocked in production');
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, GeoCountry::count());
    }

    /** @return array{user_id?: int, suggestion_id?: int} */
    private function seedCurrentDataset(bool $withProtectedRecords = false): array
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

            foreach (range(1, (int) $row['facility_count']) as $localNumber) {
                $facilityNumber++;
                $facilityRows[] = [
                    'source_id' => "geocore-test-{$facilityNumber}",
                    'city_id' => $cityId,
                    'name' => "GeoCore Test Einrichtung {$facilityNumber}",
                    'slug' => "geocore-test-einrichtung-{$facilityNumber}",
                    'postal_code' => explode(' | ', $row['current_postal_codes'])[0],
                    'address' => "Teststraße {$localNumber}",
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

        $this->assertSame(257, City::count());
        $this->assertSame(1557, Facility::count());

        if (! $withProtectedRecords) {
            return [];
        }

        $user = User::create([
            'name' => 'GeoCore Test Admin',
            'email' => 'geocore-test@example.com',
            'password' => Hash::make('test-password'),
            'is_admin' => true,
        ]);
        $suggestion = ContactSuggestion::create([
            'facility_id' => 1,
            'fingerprint' => str_repeat('a', 64),
            'parser_status' => 'verified',
            'decision' => 'pending',
        ]);

        return ['user_id' => $user->id, 'suggestion_id' => $suggestion->id];
    }

    /** @return list<array<string, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $this->assertIsResource($handle);
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = fgetcsv($handle, null, ',', '"', '\\');
        $this->assertIsArray($header);
        $rows = [];

        while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $row = array_combine($header, $values);
            $this->assertIsArray($row);
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /** @param callable(list<array<string, string>>&): void $mutator */
    private function temporaryCsvCopy(string $sourceName, callable $mutator): string
    {
        $rows = $this->readCsv(storage_path('app/geocore/brandenburg/'.$sourceName));
        $mutator($rows);
        $path = tempnam(sys_get_temp_dir(), 'geocore-test-');
        $this->assertIsString($path);
        $handle = fopen($path, 'wb');
        $this->assertIsResource($handle);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($rows[0]), ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        fclose($handle);

        return $path;
    }

    /** @return array<string, int> */
    private function geoCounts(): array
    {
        return [
            'countries' => GeoCountry::count(),
            'states' => GeoState::count(),
            'districts' => GeoDistrict::count(),
            'municipalities' => GeoMunicipality::count(),
            'linked' => City::query()->whereNotNull('geo_municipality_id')->count(),
            'manual' => City::query()->where('geo_requires_manual_review', true)->count(),
        ];
    }
}
