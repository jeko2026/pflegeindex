<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class DataQualityBatchCommandTest extends TestCase
{
    private string $directory;
    private string $originalStoragePath;
    private string $queuePath;
    private string $temporaryStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = app()->storagePath();
        $this->temporaryStoragePath = $this->originalStoragePath.'/framework/testing/data-quality-batch-'.str_replace('.', '', uniqid('', true));
        app()->useStoragePath($this->temporaryStoragePath);

        $this->directory = storage_path('app/data-quality/batches');
        $this->queuePath = storage_path('app/data-quality/review-queues/missing-email.csv');
        File::ensureDirectoryExists(dirname($this->queuePath));
        File::ensureDirectoryExists($this->directory);
        File::put($this->queuePath, "\xEF\xBB\xBFfacility_id,name,type,city,postcode,address,phone,website,reviewed_email,reviewed_source_url,review_result\n1,Alpha,Ambulante Pflege,Potsdam,14467,Teststraße 1,,,,,\n2,Beta,Ambulante Pflege,Potsdam,14467,Teststraße 2,,,,,\n3,Gamma,Ambulante Pflege,Berlin,10115,Teststraße 3,,,,,\n");
        File::put($this->directory.'/index.json', json_encode(['batches' => []]));
    }

    protected function tearDown(): void
    {
        try {
            File::deleteDirectory($this->temporaryStoragePath);
        } finally {
            app()->useStoragePath($this->originalStoragePath);
            parent::tearDown();
        }
    }

    public function test_next_batch_assigns_records_once_and_complete_updates_metadata_only(): void
    {
        $this->artisan('data-quality:next-batch', ['--queue' => 'missing-email', '--size' => 2])->assertExitCode(0);
        $this->artisan('data-quality:next-batch', ['--queue' => 'missing-email', '--size' => 2])->assertExitCode(0);
        $index = json_decode(File::get($this->directory.'/index.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(2, $index['batches']);
        $this->assertSame(2, $index['batches'][0]['size']);
        $this->assertSame(1, $index['batches'][1]['size']);
        $this->assertFileExists($this->directory.'/batch-0001-missing-email.csv');
        $this->assertFileExists($this->directory.'/batch-0002-missing-email.csv');

        $this->artisan('data-quality:complete-batch', ['batch' => 'batch-0001'])->assertExitCode(0);
        $index = json_decode(File::get($this->directory.'/index.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('completed', $index['batches'][0]['status']);
        $this->assertNotNull($index['batches'][0]['completed_at']);
        $this->assertSame('open', $index['batches'][1]['status']);
    }
}
