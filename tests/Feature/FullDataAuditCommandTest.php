<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoCountry;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use App\Models\GeoState;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FullDataAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDirectory = storage_path('framework/testing/full-audit-'.str_replace('.', '', uniqid('', true)));
    }

    public function test_command_generates_json_and_csv_reports_without_changing_facilities_or_timestamps(): void
    {
        [$city, $facility] = $this->validFacility();
        $before = $facility->fresh()->getRawOriginal();

        $this->artisan('data-quality:full-audit', ['--format' => 'json', '--output' => $this->outputDirectory])
            ->expectsOutputToContain('"facilities_total": 1')
            ->assertExitCode(0);

        $this->assertSame($before, $facility->fresh()->getRawOriginal());
        foreach (['summary.json', 'issues.csv', 'facilities.csv', 'duplicates.csv', 'geography.csv', 'verification-queue.csv'] as $suffix) {
            $this->assertFileExists($this->outputDirectory.'/full-audit-'.$suffix);
        }
        $this->assertSame(1, json_decode(File::get($this->outputDirectory.'/full-audit-summary.json'), true, flags: JSON_THROW_ON_ERROR)['facilities_total']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", File::get($this->outputDirectory.'/full-audit-facilities.csv'));
        $this->assertStringContainsString('Pflegezentrum Grün', File::get($this->outputDirectory.'/full-audit-facilities.csv'));

        $this->artisan('data-quality:full-audit', ['--format' => 'csv', '--output' => $this->outputDirectory])
            ->assertExitCode(0);
        $this->assertSame($before, $facility->fresh()->getRawOriginal());
        $this->assertSame('brandenburg', $city->fresh()->state_slug);
    }

    public function test_city_and_priority_filters_are_applied(): void
    {
        $this->validFacility();
        $city = City::create(['name' => 'Cottbus', 'slug' => 'cottbus', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);
        $this->facility($city, ['source_id' => 'cottbus-invalid', 'name' => 'Cottbus Pflege', 'slug' => 'cottbus-pflege', 'email' => 'not-an-email']);

        $this->artisan('data-quality:full-audit', [
            '--format' => 'json', '--city' => 'cottbus', '--priority' => 'high', '--output' => $this->outputDirectory,
        ])->expectsOutputToContain('"facilities_total": 1')->assertExitCode(0);

        $csv = File::get($this->outputDirectory.'/full-audit-issues.csv');
        $this->assertStringContainsString('EMAIL_INVALID', $csv);
        $this->assertStringNotContainsString('PHONE_MISSING', $csv);
        $this->assertStringNotContainsString('Pflegezentrum Grün', $csv);

        $this->artisan('data-quality:full-audit', [
            '--format' => 'csv', '--city' => 'cottbus', '--priority' => 'critical', '--output' => $this->outputDirectory,
        ])->expectsOutputToContain('issue_code,facility_id,facility_name')->assertExitCode(0);
    }

    public function test_invalid_email_website_and_verified_without_source_or_date_are_reported(): void
    {
        [$city] = $this->validFacility();
        $facility = $this->facility($city, [
            'source_id' => 'bad-contact', 'name' => 'Kontakt Test', 'slug' => 'kontakt-test',
            'email' => 'invalid@', 'website' => 'example dot invalid',
            'contact_status' => 'verified', 'contact_source' => null, 'contact_checked_at' => null,
        ]);

        $this->runAudit();
        $csv = File::get($this->outputDirectory.'/full-audit-issues.csv');
        foreach (['EMAIL_INVALID', 'WEBSITE_INVALID', 'TRUST_VERIFIED_WITHOUT_SOURCE', 'TRUST_VERIFIED_WITHOUT_DATE'] as $code) {
            $this->assertStringContainsString($code, $csv);
        }
        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'email' => 'invalid@']);
    }

    public function test_orphan_geography_and_duplicate_candidate_are_reported(): void
    {
        $city = City::create(['name' => 'Hennickendorf', 'slug' => 'hennickendorf', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);
        $this->facility($city, ['source_id' => 'duplicate-a', 'name' => 'Pflege am See', 'slug' => 'pflege-am-see-a', 'phone' => '+49 3341 123456']);
        $this->facility($city, ['source_id' => 'duplicate-b', 'name' => 'Pflege am See GmbH', 'slug' => 'pflege-am-see-b', 'phone' => '+49 3341 123456']);

        $this->runAudit();
        $issues = File::get($this->outputDirectory.'/full-audit-issues.csv');
        $duplicates = File::get($this->outputDirectory.'/full-audit-duplicates.csv');
        $geography = File::get($this->outputDirectory.'/full-audit-geography.csv');
        $this->assertStringContainsString('GEO_MUNICIPALITY_MISSING', $issues);
        $this->assertStringContainsString('DUPLICATE_', $issues);
        $this->assertStringContainsString('shared_phone', $duplicates);
        $this->assertStringContainsString('GEO_NAMED_MANUAL_CHECK', $geography);
    }

    public function test_valid_facility_and_regression_id_520_have_no_false_critical_or_high_issue(): void
    {
        [$city, $facility] = $this->validFacility(['id' => 520, 'slug' => 'pflegezentrum-gruen-14467']);
        $this->assertSame(520, $facility->id);

        $this->runAudit();
        $rows = $this->csvRows($this->outputDirectory.'/full-audit-issues.csv');
        $serious = array_filter($rows, fn (array $row): bool => ($row['facility_id'] ?? null) === '520' && in_array($row['priority'] ?? null, ['critical', 'high'], true));
        $this->assertSame([], array_values($serious));
    }

    public function test_verification_queue_has_one_row_per_facility_and_empty_review_fields(): void
    {
        [$city] = $this->validFacility();
        $facility = $this->facility($city, ['source_id' => 'queue-one', 'name' => 'Queue Pflege', 'slug' => 'queue-pflege', 'email' => null, 'website' => null]);

        $this->runAudit();
        $rows = array_values(array_filter($this->csvRows($this->outputDirectory.'/full-audit-verification-queue.csv'), fn ($row) => ($row['facility_id'] ?? null) === (string) $facility->id));
        $this->assertCount(1, $rows);
        foreach (['review_status', 'review_notes', 'verified_name', 'verified_address', 'verified_phone', 'verified_email', 'verified_website', 'verified_source_url', 'final_status'] as $field) {
            $this->assertSame('', $rows[0][$field]);
        }
    }

    public function test_database_constraint_rejects_duplicate_primary_slug(): void
    {
        [$city] = $this->validFacility();
        $this->facility($city, ['source_id' => 'slug-one', 'name' => 'Slug Eins', 'slug' => 'gleicher-slug']);
        $this->expectException(QueryException::class);
        $this->facility($city, ['source_id' => 'slug-two', 'name' => 'Slug Zwei', 'slug' => 'gleicher-slug']);
    }

    private function runAudit(): void
    {
        $exit = Artisan::call('data-quality:full-audit', ['--output' => $this->outputDirectory]);
        $this->assertSame(0, $exit, Artisan::output());
    }

    /** @return array{City, Facility} */
    private function validFacility(array $overrides = []): array
    {
        $country = GeoCountry::create(['iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Deutschland', 'slug' => 'deutschland']);
        $state = GeoState::create(['country_id' => $country->id, 'ags' => '12', 'name' => 'Brandenburg', 'slug' => 'brandenburg']);
        $district = GeoDistrict::create(['state_id' => $state->id, 'ags' => '12054', 'name' => 'Potsdam, Stadt', 'slug' => 'potsdam', 'type' => 'kreisfreie_stadt']);
        $municipality = GeoMunicipality::create([
            'district_id' => $district->id, 'ags' => '12054000', 'name' => 'Potsdam', 'normalized_name' => 'potsdam',
            'slug' => 'potsdam', 'municipality_type' => 'stadt', 'postal_code_official' => '14467',
            'source_name' => 'Amtliche Quelle', 'source_date' => '2026-07-01', 'source_url' => 'https://example.org/geografie',
        ]);
        $city = City::create([
            'name' => 'Potsdam', 'slug' => 'potsdam', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg',
            'geo_municipality_id' => $municipality->id, 'geo_match_status' => 'exact', 'geo_match_method' => 'ags',
            'geo_match_confidence' => 'high', 'geo_requires_manual_review' => false,
        ]);

        return [$city, $this->facility($city, array_merge([
            'source_id' => 'valid-facility', 'name' => 'Pflegezentrum Grün', 'slug' => 'pflegezentrum-gruen',
            'contact_status' => 'verified', 'contact_source' => 'https://pflegezentrum.example/einrichtung',
            'contact_checked_at' => now()->subMonth(),
        ], $overrides))];
    }

    private function facility(City $city, array $overrides = []): Facility
    {
        $data = array_merge([
            'source_id' => 'facility-'.uniqid(), 'city_id' => $city->id, 'name' => 'Pflege Beispiel',
            'slug' => 'pflege-beispiel', 'postal_code' => '14467', 'street' => 'Lindenstraße', 'house_number' => '4',
            'address' => 'Lindenstraße 4', 'type' => 'Ambulante Pflege', 'care_types' => ['Ambulante Pflege'], 'features' => [],
            'phone' => '+49 331 123456', 'email' => 'info@pflege.example', 'website' => 'https://pflege.example/einrichtung',
            'contact_status' => 'pending', 'contact_source' => 'https://pflege.example/einrichtung', 'contact_checked_at' => now()->subMonth(),
        ], $overrides);

        return Facility::unguarded(fn (): Facility => Facility::create($data));
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $path): array
    {
        $stream = fopen($path, 'r');
        $headers = fgetcsv($stream);
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);

        return $rows;
    }
}
