<?php

namespace App\Services\DataQuality;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class BatchManager
{
    public const QUEUES = ['missing-email', 'missing-website', 'missing-phone', 'address-review', 'name-review', 'type-review'];

    public function directory(): string
    {
        return storage_path('app/data-quality/batches');
    }

    /** @return array<int, array<string, mixed>> */
    public function index(): array
    {
        $path = $this->directory().'/index.json';
        if (! File::exists($path)) {
            return [];
        }
        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Batch index.json is not valid JSON.');
        }
        return array_values($decoded['batches'] ?? $decoded);
    }

    /** @param array<int, array<string, mixed>> $batches */
    public function writeIndex(array $batches): void
    {
        File::ensureDirectoryExists($this->directory());
        File::put($this->directory().'/index.json', json_encode(['batches' => array_values($batches)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<string, string>>} */
    public function readQueue(string $queue): array
    {
        $path = storage_path('app/data-quality/review-queues/'.$queue.'.csv');
        if (! File::isReadable($path)) {
            throw new RuntimeException('Queue file is not readable: '.$queue.'.csv');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Queue file could not be opened.');
        }
        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('Queue file has no header.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null] || count(array_filter($values, static fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = (string) ($values[$i] ?? '');
            }
            $rows[] = $row;
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @param array<int, array<string, mixed>> $batches */
    public function assignedIds(array $batches): array
    {
        $ids = [];
        foreach ($batches as $batch) {
            $path = $this->directory().'/'.($batch['filename'] ?? '');
            if (! is_file($path)) {
                continue;
            }
            $data = $this->readCsv($path);
            $idColumn = array_search('facility_id', $data['headers'], true);
            if ($idColumn === false) {
                continue;
            }
            foreach ($data['rows'] as $row) {
                $id = trim((string) ($row[$idColumn] ?? ''));
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }
        return $ids;
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<int, string>>} */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = fgetcsv($handle);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }
}
