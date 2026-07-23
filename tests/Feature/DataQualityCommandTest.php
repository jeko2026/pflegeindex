<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Services\DataQuality\FacilityContactNormalizer;
use App\Services\DataQuality\FacilityDataAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataQualityCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createEntry(array $facilityData = []): array
    {
        $city = City::create([
            'name' => 'Schöneiche',
            'slug' => 'schoeneiche',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);

        $defaultData = [
            'source_id' => 'test-12345',
            'city_id' => $city->id,
            'name' => ' Haus am Wald ',
            'slug' => 'haus-am-wald-12345',
            'postal_code' => '15566',
            'address' => ' Waldstraße 10 ',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
            'phone' => ' +49 3364 12345 ',
            'email' => ' info@hausamwald.de ',
            'website' => ' http://hausamwald.de/index.html?ref=test ',
            'description' => ' Schönes Haus im Grünen. ',
        ];

        $facility = Facility::create(array_merge($defaultData, $facilityData));

        return [$city, $facility];
    }

    public function test_audit_command_is_read_only(): void
    {
        [$city, $facility] = $this->createEntry();

        $this->artisan('data-quality:audit')
            ->assertExitCode(0);

        // Verify no changes to DB
        $fresh = $facility->fresh();
        $this->assertSame(' +49 3364 12345 ', $fresh->phone);
        $this->assertSame(' info@hausamwald.de ', $fresh->email);
    }

    public function test_normalize_dry_run_is_default_and_does_not_modify(): void
    {
        [$city, $facility] = $this->createEntry();

        $this->artisan('data-quality:normalize')
            ->expectsOutputToContain('Running in DRY-RUN mode')
            ->assertExitCode(0);

        $fresh = $facility->fresh();
        $this->assertSame(' +49 3364 12345 ', $fresh->phone);
    }

    public function test_normalize_both_apply_and_dry_run_fails(): void
    {
        [$city, $facility] = $this->createEntry();

        $this->artisan('data-quality:normalize --apply --dry-run')
            ->expectsOutputToContain('Cannot specify both --apply and --dry-run.')
            ->assertExitCode(1);

        $fresh = $facility->fresh();
        $this->assertSame(' +49 3364 12345 ', $fresh->phone);
    }

    public function test_normalize_cleanups_only_affects_contact_fields_and_does_not_modify_others(): void
    {
        [$city, $facility] = $this->createEntry();

        $this->artisan('data-quality:normalize --apply')
            ->expectsConfirmation('Are you sure you want to apply these 3 changes inside a database transaction?', 'yes')
            ->assertExitCode(0);

        $fresh = $facility->fresh();

        // Contact fields are normalized (trimmed)
        $this->assertSame('+49 3364 12345', $fresh->phone);
        $this->assertSame('info@hausamwald.de', $fresh->email);
        $this->assertSame('http://hausamwald.de/index.html?ref=test', $fresh->website);

        // Other fields are NOT modified
        $this->assertSame(' Haus am Wald ', $fresh->name);
        $this->assertSame(' Waldstraße 10 ', $fresh->address);
        $this->assertSame(' Schönes Haus im Grünen. ', $fresh->description);
    }

    public function test_normalize_preserves_url_protocols_and_paths_and_phones(): void
    {
        [$city, $facility] = $this->createEntry([
            'phone' => '+49 3364 123-45 (Ext 12)',
            'website' => 'http://test.com/path/index.html?a=b',
        ]);

        $this->artisan('data-quality:normalize --apply')
            ->expectsConfirmation('Are you sure you want to apply these 1 changes inside a database transaction?', 'yes')
            ->assertExitCode(0);

        $fresh = $facility->fresh();
        $this->assertSame('+49 3364 123-45 (Ext 12)', $fresh->phone);
        $this->assertSame('http://test.com/path/index.html?a=b', $fresh->website);
    }

    public function test_unicode_and_umlauts_are_not_corrupted(): void
    {
        [$city, $facility] = $this->createEntry([
            'phone' => "\xEF\xBB\xBF +49 3364 ÄÖÜäöüß ", // UTF-8 BOM + Umlauts
        ]);

        $this->artisan('data-quality:normalize --apply')
            ->expectsConfirmation('Are you sure you want to apply these 3 changes inside a database transaction?', 'yes')
            ->assertExitCode(0);

        $fresh = $facility->fresh();
        $this->assertSame('+49 3364 ÄÖÜäöüß', $fresh->phone);
    }

    public function test_corrupted_email_id_892_is_manual_review_and_not_auto_repaired(): void
    {
        $corrupted = 'geschlossen￼￼contacts￼123-456-7890￼info@email.com';
        [$city, $facility] = $this->createEntry([
            'email' => ' ' . $corrupted . ' ',
        ]);

        $this->assertSame('manual_review', FacilityDataAuditor::auditEmail($corrupted));

        $cleaned = FacilityContactNormalizer::clean(' ' . $corrupted . ' ');
        // Should be trimmed, but still contain corrupted text & replacement characters
        $this->assertSame($corrupted, $cleaned);
    }

    public function test_corrupted_email_not_rendered_as_mailto_but_general_links_exist(): void
    {
        $corrupted = 'geschlossen￼￼contacts￼123-456-7890￼info@email.com';
        [$city, $facility] = $this->createEntry([
            'email' => $corrupted,
        ]);

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();

        // Should NOT show mailto for the facility corrupted email
        $response->assertDontSee('mailto:' . $corrupted, false);
        $response->assertDontSee('E-Mail senden');

        // Should STILL show general feedback/error links using PflegeIndex email
        $response->assertSee('mailto:info@pflegeindex.com?subject=', false);
        $response->assertSee('Datenfehler melden');
    }

    public function test_coverage_report_counts_correctly(): void
    {
        // 1. Facility with contacts
        $this->createEntry();

        // 2. Facility without contacts
        $city2 = City::create([
            'name' => 'Cottbus',
            'slug' => 'cottbus',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        Facility::create([
            'source_id' => 'test-empty',
            'city_id' => $city2->id,
            'name' => 'Outpatient Care',
            'slug' => 'outpatient-care',
            'postal_code' => '03046',
            'address' => 'Feldstr. 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
            'phone' => null,
            'email' => null,
            'website' => null,
            'contact_status' => 'unverified',
        ]);

        $this->artisan('data-quality:coverage --limit=5')
            ->expectsOutputToContain('Contact Coverage Report')
            ->expectsOutputToContain('Cottbus')
            ->assertExitCode(0);
    }

    public function test_audit_formats_work(): void
    {
        $this->createEntry();

        // JSON format
        $this->artisan('data-quality:audit --format=json')
            ->expectsOutputToContain('"metrics"')
            ->assertExitCode(0);

        // CSV format
        $this->artisan('data-quality:audit --format=csv')
            ->expectsOutputToContain('id,name,city,field,value,issue_category,severity')
            ->assertExitCode(0);
    }
}
