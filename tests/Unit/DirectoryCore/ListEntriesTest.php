<?php

declare(strict_types=1);

namespace Tests\Unit\DirectoryCore;

use App\Platform\DirectoryCore\Application\ListEntries;
use App\Platform\DirectoryCore\Contracts\EntryRepository;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\PaginationOptions;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;
use PHPUnit\Framework\TestCase;

class ListEntriesTest extends TestCase
{
    public function test_it_passes_the_same_criteria_to_the_repository_and_returns_its_result(): void
    {
        $criteria = new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
        );
        $expectedResult = new ListingResult([], currentPage: 1, perPage: 24, total: 0);
        $repository = new class($expectedResult) implements EntryRepository
        {
            public ?ListingCriteria $receivedCriteria = null;

            public function __construct(private readonly ListingResult $result) {}

            public function list(ListingCriteria $criteria): ListingResult
            {
                $this->receivedCriteria = $criteria;

                return $this->result;
            }
        };

        $result = (new ListEntries($repository))->execute($criteria);

        $this->assertSame($criteria, $repository->receivedCriteria);
        $this->assertSame($expectedResult, $result);
    }
}
