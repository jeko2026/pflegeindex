<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facility;
use App\Services\DataQuality\FacilityContactNormalizer;
use Illuminate\Support\Facades\DB;

final class DataQualityNormalizeCommand extends Command
{
    protected $signature = 'data-quality:normalize 
                            {--dry-run : Explicitly run in preview mode (default)} 
                            {--apply : Execute updates to the database}';

    protected $description = 'Safely normalize whitespace and control characters in facility contacts';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $apply = $this->option('apply');

        // If BOTH are specified
        if ($dryRun && $apply) {
            $this->error("Cannot specify both --apply and --dry-run.");
            return 1;
        }

        // Default to dry-run unless --apply is explicitly specified
        $isDryRun = !$apply;

        if ($isDryRun) {
            $this->comment("Running in DRY-RUN mode. No changes will be saved to the database.\n");
        } else {
            $this->warn("Running in APPLY mode! Changes will be written to the database.\n");
        }

        $normalizer = new FacilityContactNormalizer();
        $facilities = Facility::all();
        $totalChangesCount = 0;
        $recordsToChange = [];

        foreach ($facilities as $f) {
            $changes = $normalizer->getChanges($f);
            if (!empty($changes)) {
                $recordsToChange[] = [
                    'facility' => $f,
                    'changes' => $changes,
                ];
                $totalChangesCount += count($changes);
            }
        }

        if (empty($recordsToChange)) {
            $this->info("All facility contact data is already normalized. No changes needed.");
            return 0;
        }

        $rows = [];
        foreach ($recordsToChange as $item) {
            $f = $item['facility'];
            foreach ($item['changes'] as $field => $data) {
                $rows[] = [
                    $f->id,
                    $f->name,
                    $field,
                    var_export($data['old'], true),
                    var_export($data['new'], true),
                ];
            }
        }

        $this->table(['ID', 'Name', 'Field', 'Old Value', 'New Value'], $rows);
        $this->line("\nTotal normalization changes proposed/detected: {$totalChangesCount}");

        if (!$isDryRun) {
            if (!$this->confirm("Are you sure you want to apply these {$totalChangesCount} changes inside a database transaction?", false)) {
                $this->comment("Operation cancelled.");
                return 0;
            }

            try {
                DB::transaction(function () use ($recordsToChange, $normalizer) {
                    foreach ($recordsToChange as $item) {
                        $f = $item['facility'];
                        $normalizer->apply($f);
                    }
                });

                $this->info("Successfully applied {$totalChangesCount} changes to the database inside a transaction.");
            } catch (\Throwable $e) {
                $this->error("Transaction failed! No changes were saved. Error: " . $e->getMessage());
                return 1;
            }
        }

        return 0;
    }
}
