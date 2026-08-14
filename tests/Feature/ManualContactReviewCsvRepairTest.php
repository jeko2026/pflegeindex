<?php

namespace Tests\Feature;

use App\Services\DataQuality\ManualContactReviewCsvRepairService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ManualContactReviewCsvRepairTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/csv-repair-'.uniqid());
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_exact_row_and_quoted_delimiter_remain_unchanged(): void
    {
        $row = $this->sheetTwoRow(['current_name' => 'Pflege, Betreuung; Haus']);
        $summary = $this->repair([$this->sheetOneRow()], [$row]);

        $this->assertSame(0, $summary['repairs']['rows']);
        $this->assertSame('Pflege, Betreuung; Haus', $this->cleanSheetTwo()[0][1]);
    }

    public function test_shifted_by_plus_one_is_safely_repaired(): void
    {
        $row = $this->sheetTwoRow([
            'current_phone(+49..)' => '', 'current_email' => '+49 331 123456',
            'current_website(https://)' => 'kontakt@example.de', 'data source(url)' => 'https://example.de',
            'Notes' => 'https://example.de/kontakt',
        ]);
        $summary = $this->repair([$this->sheetOneRow()], [$row]);
        $clean = $this->cleanSheetTwo()[0];

        $this->assertSame(1, $summary['repairs']['shifted_rows']);
        $this->assertSame('+49 331 123456', $clean[5]);
        $this->assertSame('kontakt@example.de', $clean[6]);
        $this->assertSame('https://example.de', $clean[7]);
        $this->assertSame('https://example.de/kontakt', $clean[8]);
    }

    public function test_shifted_by_minus_one_is_safely_repaired(): void
    {
        $row = $this->sheetTwoRow([
            'current_phone(+49..)' => 'kontakt@example.de', 'current_email' => 'https://example.de',
            'current_website(https://)' => 'https://example.de/kontakt', 'data source(url)' => '', 'Notes' => '',
        ]);
        $summary = $this->repair([$this->sheetOneRow()], [$row]);
        $clean = $this->cleanSheetTwo()[0];

        $this->assertSame(1, $summary['repairs']['shifted_rows']);
        $this->assertSame('', $clean[5]);
        $this->assertSame('kontakt@example.de', $clean[6]);
        $this->assertSame('https://example.de', $clean[7]);
        $this->assertSame('https://example.de/kontakt', $clean[8]);
    }

    public function test_url_email_and_phone_type_detection_sends_unsafe_row_to_manual_review(): void
    {
        $row = $this->sheetTwoRow(['current_phone(+49..)' => 'kontakt@example.de', 'current_email' => 'https://one.example', 'current_website(https://)' => '+49 331 123456']);
        $summary = $this->repair([$this->sheetOneRow()], [$row]);

        $this->assertSame(1, $summary['detected_shifted_rows']['manual_review']);
        $this->assertSame(0, $summary['repairs']['rows']);
    }

    public function test_malformed_row_is_excluded_from_clean_csv(): void
    {
        [$sheetOne, $sheetTwo] = $this->paths();
        $this->writeCsv($sheetOne, array_keys($this->sheetOneRow()), [$this->sheetOneRow()], ',');
        file_put_contents($sheetTwo, implode(';', array_keys($this->sheetTwoRow()))."\n1;Too few\n");

        $summary = app(ManualContactReviewCsvRepairService::class)->repair($sheetOne, $sheetTwo, $this->directory.'/output');

        $this->assertSame(1, $summary['files']['sheet2']['too_few_columns']);
        $this->assertSame(0, $summary['files']['sheet2']['clean_rows']);
    }

    public function test_ambiguous_repair_is_not_applied(): void
    {
        $row = $this->sheetTwoRow(['current_phone(+49..)' => 'https://one.example', 'current_email' => '', 'current_website(https://)' => 'https://two.example', 'data source(url)' => '', 'Notes' => '']);
        $summary = $this->repair([$this->sheetOneRow()], [$row]);

        $this->assertSame(1, $summary['detected_shifted_rows']['manual_review']);
        $this->assertSame(0, $summary['repairs']['rows']);
    }

    public function test_duplicate_facility_id_is_preserved_for_importer_conflict_handling(): void
    {
        $row = $this->sheetTwoRow();
        $summary = $this->repair([$this->sheetOneRow()], [$row, $row]);

        $this->assertSame(2, $summary['files']['sheet2']['clean_rows']);
        $this->assertSame(0, $summary['repairs']['rows']);
    }

    public function test_excel_phone_prefix_is_a_safe_auto_repair(): void
    {
        $summary = $this->repair([$this->sheetOneRow()], [$this->sheetTwoRow(['current_phone(+49..)' => "'+49 331 123456"])]);

        $this->assertSame(1, $summary['repairs']['excel_text_prefixes']);
        $this->assertSame('+49 331 123456', $this->cleanSheetTwo()[0][5]);
    }

    public function test_unsafe_row_goes_to_manual_review_without_value_changes(): void
    {
        $row = $this->sheetTwoRow(['current_phone(+49..)' => 'kontakt@example.de']);
        $summary = $this->repair([$this->sheetOneRow()], [$row]);

        $this->assertSame(1, $summary['detected_shifted_rows']['manual_review']);
        $this->assertSame('kontakt@example.de', $this->cleanSheetTwo()[0][5]);
    }

    private function repair(array $sheetOneRows, array $sheetTwoRows): array
    {
        [$sheetOne, $sheetTwo] = $this->paths();
        $this->writeCsv($sheetOne, array_keys($this->sheetOneRow()), $sheetOneRows, ',');
        $this->writeCsv($sheetTwo, array_keys($this->sheetTwoRow()), $sheetTwoRows, ';');

        return app(ManualContactReviewCsvRepairService::class)->repair($sheetOne, $sheetTwo, $this->directory.'/output');
    }

    private function paths(): array
    {
        return [$this->directory.'/sheet1.csv', $this->directory.'/sheet2.csv'];
    }

    private function cleanSheetTwo(): array
    {
        $handle = fopen($this->directory.'/output/manual-review-sheet2-clean.csv', 'rb');
        fgetcsv($handle, 0, ';');
        $rows = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function writeCsv(string $path, array $headers, array $rows, string $delimiter): void
    {
        $handle = fopen($path, 'wb');
        fputcsv($handle, $headers, $delimiter, '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers), $delimiter, '"', '');
        }
        fclose($handle);
    }

    private function sheetOneRow(array $overrides = []): array
    {
        return array_merge([
            'Назва закладу' => 'Pflege Beispiel', 'Місто' => 'Potsdam', 'Веб сайт Офіційний' => 'https://example.de',
            'Вулиця' => 'Teststraße', 'Номер буд' => '1', 'Поштовий індекс' => '14467', 'Номер телефону' => '+49 331 123456',
            'Email' => 'kontakt@example.de', 'URL-адреса(и) джерела' => 'https://example.de/kontakt', 'Примітки' => '',
        ], $overrides);
    }

    private function sheetTwoRow(array $overrides = []): array
    {
        return array_merge([
            'facility_id' => '1', 'current_name' => 'Pflege Beispiel', 'current_address' => 'Teststraße 1',
            'current_postal_code' => '14467', 'current_city' => 'Potsdam', 'current_phone(+49..)' => '+49 331 123456',
            'current_email' => 'kontakt@example.de', 'current_website(https://)' => 'https://example.de',
            'data source(url)' => 'https://example.de/kontakt', 'Notes' => '', 'Column1' => '',
        ], $overrides);
    }
}
