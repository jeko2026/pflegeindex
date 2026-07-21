<?php

namespace App\Console\Commands;

use App\Services\ContactSuggestionImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportContactSuggestions extends Command
{
    protected $signature = 'pflegeindex:import-suggestions
                            {path? : Path to a parser result JSON file}';

    protected $description = 'Import parser results into the contact review queue';

    public function __construct(private readonly ContactSuggestionImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $path = $this->resolvePath();
            $summary = $this->importer->import($path);

            $this->components->info('Parser results imported into the review queue.');
            $this->table(
                ['Created', 'Updated', 'Unknown facilities', 'Rejected URLs', 'Pending review'],
                [[$summary['created'], $summary['updated'], $summary['unknown'], $summary['rejected_urls'], $summary['pending']]],
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePath(): string
    {
        $path = $this->argument('path') ?: base_path('../mvp/data/enrichment/potsdam-contacts.json');
        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException("Parser result file not found: {$path}");
        }

        return $resolved;
    }
}
