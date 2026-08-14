<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Services\DataQuality\ManualContactReviewImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class ManualContactReviewImportTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/manual-review-'.uniqid());
        File::ensureDirectoryExists($this->directory);
        $this->city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_matches_sheet_one_by_name_and_city_and_updates_one_field(): void
    {
        $facility = $this->facility(['phone' => null]);
        $plan = $this->plan([
            $this->sheetOneRow(['Номер телефону' => '0331 123456']),
        ]);

        $this->assertSame(1, $plan['summary']['to_update']);
        $this->assertSame(['phone'], array_column($plan['changes'], 'field'));

        app(ManualContactReviewImportService::class)->apply($plan);
        $this->assertSame('+49 331 123 456', $facility->fresh()->phone);
    }

    public function test_matches_sheet_two_by_id_and_updates_multiple_fields(): void
    {
        $facility = $this->facility(['phone' => null, 'email' => null, 'website' => null]);
        $plan = $this->plan([], [
            $this->sheetTwoRow($facility, ['current_phone(+49..)' => '+49 331 111222', 'current_email' => 'kontakt@example.de', 'current_website(https://)' => 'https://example.de']),
        ]);

        $this->assertSame(1, $plan['summary']['to_update']);
        $this->assertSame(['phone', 'email', 'website'], array_column($plan['changes'], 'field'));
    }

    public function test_unchanged_row_produces_no_updates(): void
    {
        $facility = $this->facility(['phone' => '+49 331 123456', 'email' => 'kontakt@example.de', 'website' => 'https://example.de', 'contact_source' => 'https://example.de/kontakt']);
        $plan = $this->plan([], [$this->sheetTwoRow($facility)]);

        $this->assertSame(1, $plan['summary']['unchanged']);
        $this->assertSame([], $plan['changes']);
    }

    public function test_unknown_id_is_not_found(): void
    {
        $plan = $this->plan([], [$this->sheetTwoRow(null, ['facility_id' => 999999])]);

        $this->assertSame(1, $plan['summary']['not_found']);
        $this->assertSame('facility_not_found', $plan['issues'][0]['code']);
    }

    public function test_duplicate_input_id_is_a_conflict(): void
    {
        $facility = $this->facility();
        $row = $this->sheetTwoRow($facility);
        $plan = $this->plan([], [$row, $row]);

        $this->assertSame(2, $plan['summary']['conflicts']);
        $this->assertSame([], $plan['changes']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $facility = $this->facility();
        $plan = $this->plan([], [$this->sheetTwoRow($facility, ['current_email' => 'not-an-email'])]);

        $this->assertSame(1, $plan['summary']['invalid']);
        $this->assertSame('invalid_email', $plan['issues'][0]['code']);
    }

    public function test_invalid_website_is_rejected(): void
    {
        $facility = $this->facility();
        $plan = $this->plan([], [$this->sheetTwoRow($facility, ['current_website(https://)' => 'example.de'])]);

        $this->assertSame(1, $plan['summary']['invalid']);
        $this->assertSame('invalid_website', $plan['issues'][0]['code']);
    }

    public function test_empty_and_sentinel_values_never_replace_existing_contacts(): void
    {
        $facility = $this->facility(['phone' => '+49 331 123456', 'email' => 'kontakt@example.de', 'website' => 'https://example.de', 'contact_source' => 'https://example.de/kontakt']);
        $plan = $this->plan([], [$this->sheetTwoRow($facility, ['current_phone(+49..)' => '', 'current_email' => 'NO_EMAIL', 'current_website(https://)' => 'NO_WEBSITE'])]);

        $this->assertSame([], $plan['changes']);
        $this->assertSame('kontakt@example.de', $facility->fresh()->email);
    }

    public function test_locked_contact_is_never_overwritten(): void
    {
        $facility = $this->facility(['phone' => '+49 331 123456', 'contact_locked' => true]);
        $plan = $this->plan([], [$this->sheetTwoRow($facility, ['current_phone(+49..)' => '+49 331 999999'])]);

        $this->assertSame(1, $plan['summary']['conflicts']);
        $this->assertSame('contact_locked', $plan['issues'][0]['code']);
    }

    public function test_apply_rolls_back_all_rows_on_concurrent_change(): void
    {
        $first = $this->facility(['source_id' => 'first', 'slug' => 'first', 'name' => 'First', 'phone' => null]);
        $second = $this->facility(['source_id' => 'second', 'slug' => 'second', 'name' => 'Second', 'phone' => null]);
        $plan = $this->plan([], [
            $this->sheetTwoRow($first, ['current_phone(+49..)' => '+49 331 111111']),
            $this->sheetTwoRow($second, ['current_phone(+49..)' => '+49 331 222222']),
        ]);
        $second->update(['phone' => '+49 331 333333']);

        try {
            app(ManualContactReviewImportService::class)->apply($plan);
            $this->fail('Expected concurrent change failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Concurrent change', $exception->getMessage());
        }
        $this->assertNull($first->fresh()->phone);
    }

    public function test_repeated_import_is_idempotent(): void
    {
        $facility = $this->facility(['phone' => null, 'contact_source' => null]);
        [$sheetOne, $sheetTwo] = $this->writeSheets([], [$this->sheetTwoRow($facility, ['current_phone(+49..)' => '+49 331 111222'])]);
        $importer = app(ManualContactReviewImportService::class);
        $first = $importer->plan($importer->read($sheetOne, $sheetTwo));
        $importer->apply($first);
        $second = $importer->plan($importer->read($sheetOne, $sheetTwo));

        $this->assertSame(0, $second['summary']['to_update']);
        $this->assertSame([], $second['changes']);
    }

    private function facility(array $overrides = []): Facility
    {
        return Facility::create(array_merge([
            'source_id' => 'facility-'.uniqid(), 'city_id' => $this->city->id, 'name' => 'Pflege Beispiel',
            'slug' => 'pflege-beispiel-'.uniqid(), 'postal_code' => '14467', 'address' => 'Teststraße 1',
            'type' => 'Ambulante Pflege', 'phone' => '+49 331 123456', 'email' => 'kontakt@example.de',
            'website' => 'https://example.de', 'contact_source' => 'https://example.de/kontakt', 'contact_locked' => false,
        ], $overrides));
    }

    private function plan(array $sheetOneRows = [], array $sheetTwoRows = []): array
    {
        [$sheetOne, $sheetTwo] = $this->writeSheets($sheetOneRows, $sheetTwoRows);
        $importer = app(ManualContactReviewImportService::class);

        return $importer->plan($importer->read($sheetOne, $sheetTwo));
    }

    /** @return array{string,string} */
    private function writeSheets(array $sheetOneRows, array $sheetTwoRows): array
    {
        $sheetOne = $this->directory.'/sheet1-'.uniqid().'.csv';
        $sheetTwo = $this->directory.'/sheet2-'.uniqid().'.csv';
        $this->writeCsv($sheetOne, array_keys($this->sheetOneRow()), $sheetOneRows, ',');
        $this->writeCsv($sheetTwo, array_keys($this->sheetTwoRow()), $sheetTwoRows, ';');

        return [$sheetOne, $sheetTwo];
    }

    private function writeCsv(string $path, array $headers, array $rows, string $delimiter): void
    {
        $handle = fopen($path, 'wb');
        fputcsv($handle, $headers, $delimiter);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers), $delimiter);
        }
        fclose($handle);
    }

    private function sheetOneRow(array $overrides = []): array
    {
        return array_merge([
            'Назва закладу' => 'Pflege Beispiel', 'Місто' => 'Potsdam', 'Веб сайт Офіційний' => 'https://example.de',
            'Вулиця' => 'Teststraße', 'Номер буд' => '1', 'Поштовий індекс' => '14467',
            'Номер телефону' => '+49 331 123456', 'Email' => 'kontakt@example.de',
            'URL-адреса(и) джерела' => 'https://example.de/kontakt', 'Примітки' => '',
        ], $overrides);
    }

    private function sheetTwoRow(?Facility $facility = null, array $overrides = []): array
    {
        return array_merge([
            'facility_id' => $facility?->id ?? 1, 'current_name' => $facility?->name ?? 'Pflege Beispiel',
            'current_address' => $facility?->address ?? 'Teststraße 1', 'current_postal_code' => $facility?->postal_code ?? '14467',
            'current_city' => $facility?->city?->name ?? 'Potsdam', 'current_phone(+49..)' => $facility?->phone ?? '',
            'current_email' => $facility?->email ?? '', 'current_website(https://)' => $facility?->website ?? '',
            'data source(url)' => $facility?->contact_source ?? 'https://example.de/kontakt', 'Notes' => '', 'Column1' => '',
        ], $overrides);
    }
}
