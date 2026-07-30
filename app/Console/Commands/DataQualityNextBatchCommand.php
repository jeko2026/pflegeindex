<?php

namespace App\Console\Commands;

use App\Services\DataQuality\BatchManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class DataQualityNextBatchCommand extends Command
{
    protected $signature = 'data-quality:next-batch {--queue= : Queue filename without .csv} {--size=100 : Maximum records in the batch}';

    protected $description = 'Create the next unassigned manual-review batch';

    public function handle(BatchManager $manager): int
    {
        $queue = trim((string) $this->option('queue'));
        if (! in_array($queue, BatchManager::QUEUES, true)) {
            $this->error('--queue must be one of: '.implode(', ', BatchManager::QUEUES));
            return self::FAILURE;
        }
        $size = filter_var($this->option('size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($size === false) {
            $this->error('--size must be a positive integer.');
            return self::FAILURE;
        }
        $batches = $manager->index();
        $assigned = $manager->assignedIds($batches);
        $queueData = $manager->readQueue($queue);
        $idColumn = array_search('facility_id', $queueData['headers'], true);
        if ($idColumn === false) {
            throw new RuntimeException('Queue is missing facility_id.');
        }
        $available = array_values(array_filter($queueData['rows'], static fn (array $row): bool => ! isset($assigned[trim((string) ($row['facility_id'] ?? ''))])));
        $selected = array_slice($available, 0, $size);
        if ($selected === []) {
            $this->info('No unassigned records remain in '.$queue.'.');
            return self::SUCCESS;
        }
        $next = 1;
        foreach ($batches as $batch) {
            if (preg_match('/^batch-(\d+)/', (string) ($batch['id'] ?? ''), $match)) {
                $next = max($next, (int) $match[1] + 1);
            }
        }
        $id = sprintf('batch-%04d', $next);
        $filename = $id.'-'.$queue.'.csv';
        $path = $manager->directory().'/'.$filename;
        File::ensureDirectoryExists($manager->directory());
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $queueData['headers']);
        foreach ($selected as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $row[$header] ?? '', $queueData['headers']));
        }
        fclose($handle);
        $batches[] = ['id' => $id, 'queue' => $queue, 'filename' => $filename, 'size' => count($selected), 'created_at' => now()->toIso8601String(), 'completed_at' => null, 'status' => 'open'];
        $manager->writeIndex($batches);
        $this->info('Created '.$filename.' ('.count($selected).' records).');
        return self::SUCCESS;
    }
}
