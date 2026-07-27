<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FacilitiesExportOutsourcingCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('app/exports-test-'.bin2hex(random_bytes(4)));
        File::deleteDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_dry_run_reports_only_incomplete_facilities_without_creating_a_file(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $base = ['city_id' => $city->id, 'type' => 'Ambulante Pflege', 'postal_code' => '14467', 'address' => 'Teststraße 1', 'source_id' => 'export-1', 'slug' => 'alpha'];
        Facility::create($base + ['name' => 'Alpha', 'phone' => null, 'email' => 'a@example.de', 'website' => 'https://a.example.de']);
        Facility::create(['city_id' => $city->id, 'type' => 'Ambulante Pflege', 'postal_code' => '14467', 'address' => 'Teststraße 2', 'source_id' => 'export-2', 'slug' => 'beta', 'name' => 'Beta', 'contact_status' => 'verified', 'contact_source' => 'https://b.example.de', 'contact_checked_at' => now(), 'phone' => '+49 331 123456', 'email' => 'b@example.de', 'website' => 'https://b.example.de']);

        $this->artisan('facilities:export-outsourcing', ['--dry-run' => true, '--output' => $this->directory])
            ->expectsOutputToContain('requires review: 1')
            ->expectsOutputToContain('export rows: 1')
            ->assertExitCode(0);
        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function test_export_is_semicolon_bom_sorted_and_excludes_confirmed_absences(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $base = ['city_id' => $city->id, 'type' => 'Ambulante Pflege', 'postal_code' => '14467', 'address' => 'Teststraße 1', 'contact_status' => null, 'phone' => null, 'website' => null, 'email' => null];
        Facility::create($base + ['source_id' => 'export-1', 'slug' => 'zeta', 'name' => 'Zeta']);
        Facility::create($base + ['source_id' => 'export-2', 'slug' => 'alpha', 'name' => 'Alpha', 'official_email_absent' => true]);

        $this->artisan('facilities:export-outsourcing', ['--output' => $this->directory])->assertExitCode(0);
        $csv = collect(File::files($this->directory))->first(fn ($file) => str_ends_with($file->getFilename(), '.csv'));
        $this->assertNotNull($csv);
        $contents = File::get($csv->getPathname());
        $this->assertSame("\xEF\xBB\xBF", substr($contents, 0, 3));
        $this->assertStringContainsString('facility_id;current_name;current_address;', $contents);
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', substr($contents, 3)), fn (string $line): bool => trim($line) !== ''));
        $rows = array_map(fn (string $line): array => str_getcsv($line, ';'), $lines);
        $this->assertCount(3, $rows);
        $this->assertSame('Alpha', $rows[1][1]);
        $this->assertSame('Zeta', $rows[2][1]);
        $this->assertFileExists($this->directory.'/facilities_outsourcing_README.txt');
        $this->assertStringContainsString('current_*-Spalten', File::get($this->directory.'/facilities_outsourcing_README.txt'));
    }
}
