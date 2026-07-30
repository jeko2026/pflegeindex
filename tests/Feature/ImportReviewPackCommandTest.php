<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportReviewPackCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/import-review-'.str_replace('.', '', uniqid('', true)));
        File::ensureDirectoryExists($this->directory);
    }

    public function test_default_dry_run_does_not_change_database_and_skips_empty_rows(): void
    {
        $facility = $this->facility(['contact_status' => 'pending', 'email' => 'old@example.org']);
        $before = $facility->fresh()->getRawOriginal();
        $path = $this->pack([$this->row($facility, ['review_result' => 'partially_verified']), $this->row($facility, ['facility_id' => $facility->id, 'review_result' => ''])]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--report' => $this->directory.'/dry-run'])
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertSame($before, $facility->fresh()->getRawOriginal());
        $summary = json_decode(File::get($this->directory.'/dry-run/import-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $summary['rows_total']);
        $this->assertSame(1, $summary['rows_empty_skipped']);
        $this->assertFileExists($this->directory.'/dry-run/source-review-pack.csv');
        $this->assertFileExists($this->directory.'/dry-run/import-changes.csv');
    }

    public function test_verified_row_with_source_is_ready_and_apply_creates_backup_and_updates_only_reviewed_values(): void
    {
        $facility = $this->facility(['contact_status' => 'pending', 'phone' => '+49 331 111111', 'email' => null]);
        $path = $this->pack([$this->row($facility, [
            'review_result' => 'verified', 'reviewed_phone' => '+49 331 222222', 'reviewed_email' => 'new@pflegeindex.de',
            'reviewed_source_url' => 'https://official.example.org/location',
        ])]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--apply' => true, '--report' => $this->directory.'/apply'])
            ->expectsOutputToContain('Applied facilities: 1')
            ->assertExitCode(0);

        $fresh = $facility->fresh();
        $this->assertSame('+49 331 222222', $fresh->phone);
        $this->assertSame('new@pflegeindex.de', $fresh->email);
        $this->assertSame('verified', $fresh->contact_status);
        $this->assertSame('https://official.example.org/location', $fresh->contact_source);
        $this->assertFileExists($this->directory.'/apply/database-before-import.sqlite');
        $this->assertFileExists($this->directory.'/apply/applied-review-pack.csv');
        $summary = json_decode(File::get($this->directory.'/apply/import-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('apply', $summary['mode']);
        $this->assertSame('ok', $summary['integrity_result']);
        $this->assertSame(0, $summary['foreign_key_errors']);
    }

    public function test_verified_without_source_is_rejected_without_apply(): void
    {
        $facility = $this->facility(['contact_status' => 'pending', 'contact_source' => null]);
        $path = $this->pack([$this->row($facility, ['review_result' => 'verified', 'reviewed_phone' => '+49 331 222222'])]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--apply' => true, '--report' => $this->directory.'/invalid'])
            ->expectsOutputToContain('verified_without_source')
            ->assertExitCode(1);

        $this->assertSame('pending', $facility->fresh()->contact_status);
        $this->assertFileDoesNotExist($this->directory.'/invalid/database-before-import.sqlite');
    }

    public function test_invalid_email_and_url_are_rejected(): void
    {
        $facility = $this->facility();
        $path = $this->pack([$this->row($facility, [
            'review_result' => 'partially_verified', 'reviewed_email' => 'wrong@', 'reviewed_website' => 'javascript:alert(1)',
        ])]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--report' => $this->directory.'/invalid-fields'])
            ->expectsOutputToContain('invalid_email')
            ->expectsOutputToContain('invalid_website')
            ->assertExitCode(1);
        $this->assertSame('pending', $facility->fresh()->contact_status);
    }

    public function test_empty_reviewed_value_never_deletes_existing_value(): void
    {
        $facility = $this->facility(['phone' => '+49 331 123456', 'email' => 'keep@example.org']);
        $path = $this->pack([$this->row($facility, ['review_result' => 'partially_verified', 'reviewed_phone' => '', 'reviewed_email' => ''])]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--apply' => true, '--report' => $this->directory.'/empty'])
            ->assertExitCode(0);
        $fresh = $facility->fresh();
        $this->assertSame('+49 331 123456', $fresh->phone);
        $this->assertSame('keep@example.org', $fresh->email);
    }

    public function test_stale_row_is_rejected_unless_explicit_override_is_supplied(): void
    {
        $facility = $this->facility(['name' => 'Original Name']);
        $row = $this->row($facility, ['review_result' => 'partially_verified']);
        $facility->update(['name' => 'Changed After Pack']);
        $path = $this->pack([$row]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--report' => $this->directory.'/stale'])
            ->expectsOutputToContain('stale_review_row')
            ->assertExitCode(1);
        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--allow-stale' => true, '--report' => $this->directory.'/stale-allowed'])
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);
    }

    public function test_duplicate_id_unknown_id_and_unsupported_closed_are_rejected(): void
    {
        $facility = $this->facility();
        $row = $this->row($facility, ['review_result' => 'closed']);
        $unknown = $this->row($facility, ['facility_id' => 999999, 'review_result' => 'verified']);
        $path = $this->pack([$row, $row, $unknown]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--report' => $this->directory.'/structural'])
            ->expectsOutputToContain('duplicate_facility_id')
            ->expectsOutputToContain('facility_not_found')
            ->assertExitCode(1);
    }

    public function test_atomic_apply_does_not_apply_valid_row_when_another_row_is_rejected(): void
    {
        $valid = $this->facility(['phone' => '+49 331 100000']);
        $invalid = $this->facility(['email' => 'old@example.org']);
        $path = $this->pack([
            $this->row($valid, ['review_result' => 'partially_verified', 'reviewed_phone' => '+49 331 200000']),
            $this->row($invalid, ['review_result' => 'partially_verified', 'reviewed_email' => 'bad@']),
        ]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--apply' => true, '--report' => $this->directory.'/atomic'])
            ->expectsOutputToContain('invalid_email')
            ->assertExitCode(1);
        $this->assertSame('+49 331 100000', $valid->fresh()->phone);
        $this->assertSame('old@example.org', $invalid->fresh()->email);
    }

    public function test_closed_does_not_delete_facility_and_id_520_has_no_false_change(): void
    {
        $facility = $this->facility(['id' => 520]);
        $path = $this->pack([$this->row($facility, ['review_result' => 'closed'])]);

        $this->artisan('data-quality:import-review-pack', ['file' => $path, '--apply' => true, '--report' => $this->directory.'/closed'])
            ->assertExitCode(1);
        $this->assertDatabaseHas('facilities', ['id' => 520]);
    }

    private function facility(array $overrides = []): Facility
    {
        $city = City::firstOrCreate(['slug' => 'potsdam'], ['name' => 'Potsdam', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);
        $data = array_merge([
            'source_id' => 'import-'.uniqid(), 'city_id' => $city->id, 'name' => 'Import Pflege Beispiel', 'slug' => 'import-pflege-'.uniqid(),
            'postal_code' => '14467', 'street' => 'Teststraße', 'house_number' => '1', 'address' => 'Teststraße 1',
            'type' => 'Ambulante Pflege', 'care_types' => ['Ambulante Pflege'], 'features' => [], 'phone' => '+49 331 123456',
            'email' => 'kontakt@example.org', 'website' => 'https://example.org/location', 'contact_source' => 'https://example.org/location',
            'contact_status' => 'pending', 'contact_checked_at' => now()->subMonth(), 'contact_locked' => false,
        ], $overrides);

        return Facility::unguarded(fn (): Facility => Facility::create($data));
    }

    /** @param array<string, string|int|null> $overrides */
    private function row(Facility $facility, array $overrides = []): array
    {
        return array_merge([
            'queue_position' => '1', 'facility_id' => (string) $facility->id, 'name' => $facility->name, 'type' => $facility->type,
            'city' => $facility->city?->name, 'address' => $facility->address, 'postal_code' => $facility->postal_code,
            'phone' => $facility->phone, 'email' => $facility->email, 'website' => $facility->website,
            'current_verification_status' => $facility->contact_status, 'current_provenance' => $facility->contact_source,
            'current_verified_at' => $facility->contact_checked_at?->toIso8601String(), 'review_result' => '',
            'reviewed_name' => '', 'reviewed_type' => '', 'reviewed_address' => '', 'reviewed_postal_code' => '',
            'reviewed_phone' => '', 'reviewed_email' => '', 'reviewed_website' => '', 'reviewed_source_url' => '',
            'official_source_url' => '', 'review_notes' => '',
        ], $overrides);
    }

    /** @param array<int, array<string, string|int|null>> $rows */
    private function pack(array $rows): string
    {
        $path = $this->directory.'/review-pack.csv';
        $headers = array_keys($rows[0]);
        $stream = fopen($path, 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers));
        }
        fclose($stream);

        return $path;
    }
}
