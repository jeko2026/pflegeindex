<?php

namespace App\Console\Commands;

use App\Services\DataQuality\ManualContactReviewCsvRepairService;
use Illuminate\Console\Command;
use Throwable;

final class RepairManualContactReviewCsv extends Command
{
    protected $signature = 'data-quality:repair-manual-contact-review-csv
                            {sheet1 : Original comma-delimited review CSV}
                            {sheet2 : Original semicolon-delimited review CSV}
                            {--output= : Output directory for CLEAN CSV and audit files}';

    protected $description = 'Audit and safely repair unambiguous structural defects in manual contact review CSV files';

    public function handle(ManualContactReviewCsvRepairService $repair): int
    {
        $output = trim((string) $this->option('output'));
        $output = $output !== '' ? $output : storage_path('app/data-quality/manual-review-revalidation/'.now()->format('Ymd-His'));
        if (! preg_match('/^[A-Za-z]:[\\\\\/]/', $output)) {
            $output = base_path($output);
        }

        try {
            $summary = $repair->repair((string) $this->argument('sheet1'), (string) $this->argument('sheet2'), rtrim($output, '\\/'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Category', 'Rows'], [
            ['SAFE_AUTO_REPAIR', $summary['detected_shifted_rows']['safe_auto_repair']],
            ['MANUAL_REVIEW', $summary['detected_shifted_rows']['manual_review']],
            ['INVALID_ROW', $summary['detected_shifted_rows']['invalid']],
        ]);
        $this->info('CSV repair audit complete. Original files were not modified.');
        $this->line('Output: '.$output);

        return self::SUCCESS;
    }
}
