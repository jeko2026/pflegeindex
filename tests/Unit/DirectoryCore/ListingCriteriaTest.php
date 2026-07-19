<?php

declare(strict_types=1);

namespace Tests\Unit\DirectoryCore;

use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\PaginationOptions;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use PHPUnit\Framework\TestCase;

class ListingCriteriaTest extends TestCase
{
    public function test_it_normalizes_optional_string_criteria(): void
    {
        $criteria = new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
            searchQuery: '  Pflege  ',
            locationIdentifier: '  potsdam  ',
            categoryIdentifier: '   ',
        );

        $this->assertSame('Pflege', $criteria->searchQuery);
        $this->assertSame('potsdam', $criteria->locationIdentifier);
        $this->assertNull($criteria->categoryIdentifier);
    }
}
