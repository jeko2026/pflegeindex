<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Platform\DirectoryCore\Application\ListEntries;
use App\Platform\DirectoryCore\Contracts\EntryRepository;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\PaginationOptions;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Projects\FuneralIndex\Directory\FuneralEntryRepository;
use App\Projects\PflegeIndex\Directory\PflegeEntryRepository;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class SecondProjectAdapterTest extends TestCase
{
    public function test_both_project_adapters_resolve_against_the_same_platform_contract(): void
    {
        $pflegeIndex = $this->app->make(PflegeEntryRepository::class);
        $funeralIndex = $this->app->make(FuneralEntryRepository::class);

        $this->assertInstanceOf(EntryRepository::class, $pflegeIndex);
        $this->assertInstanceOf(EntryRepository::class, $funeralIndex);
        $this->assertNotSame($pflegeIndex::class, $funeralIndex::class);
    }

    public function test_funeral_adapter_executes_the_existing_list_entries_use_case(): void
    {
        $repository = $this->app->make(FuneralEntryRepository::class);
        $result = (new ListEntries($repository))->execute(new ListingCriteria(
            pagination: new PaginationOptions(page: 1, perPage: 24),
            sort: EntrySort::Default,
            locationScope: LocationScope::state('brandenburg'),
        ));

        $this->assertTrue($result->isEmpty());
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(24, $result->perPage);
        $this->assertSame(0, $result->total);
        $this->assertSame('FuneralIndex', FuneralEntryRepository::PROJECT_NAME);
        $this->assertSame('bestatter', FuneralEntryRepository::ROUTE_PREFIX);
        $this->assertSame('Bestatter', FuneralEntryRepository::ENTRY_LABEL);
        $this->assertSame('Bestatter finden', FuneralEntryRepository::NAVIGATION_LABEL);
    }

    public function test_platform_contains_no_project_specific_conditions(): void
    {
        $source = '';
        $platformPath = dirname(__DIR__, 3).'/app/Platform';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $platformPath,
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getPathname());

                if (is_string($contents)) {
                    $source .= $contents;
                }
            }
        }

        $this->assertStringNotContainsString('PflegeIndex', $source);
        $this->assertStringNotContainsString('FuneralIndex', $source);
        $this->assertStringNotContainsString('App\\Projects', $source);
    }
}
