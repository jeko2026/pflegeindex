<?php

namespace App\Console\Commands;

use App\Services\DataQuality\BatchManager;
use Illuminate\Console\Command;

final class DataQualityCompleteBatchCommand extends Command
{
    protected $signature = 'data-quality:complete-batch {batch : Batch ID, for example batch-0001}';

    protected $description = 'Mark a review batch as completed in metadata';

    public function handle(BatchManager $manager): int
    {
        $id = trim((string) $this->argument('batch'));
        $batches = $manager->index();
        foreach ($batches as &$batch) {
            if (($batch['id'] ?? null) !== $id) {
                continue;
            }
            if (($batch['status'] ?? null) === 'completed') {
                $this->info($id.' is already completed.');
                return self::SUCCESS;
            }
            $batch['status'] = 'completed';
            $batch['completed_at'] = now()->toIso8601String();
            $manager->writeIndex($batches);
            $this->info('Completed '.$id.'.');
            return self::SUCCESS;
        }
        $this->error('Batch not found: '.$id);
        return self::FAILURE;
    }
}
