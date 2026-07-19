<?php

declare(strict_types=1);

namespace Tests\Unit\DirectoryCore;

use App\Platform\DirectoryCore\Domain\EntryIdentifier;
use App\Platform\DirectoryCore\ReadModel\EntrySummary;
use App\Platform\DirectoryCore\ReadModel\ListingResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

class ListingResultTest extends TestCase
{
    public function test_it_calculates_pagination_state(): void
    {
        $result = new ListingResult([$this->entry()], currentPage: 2, perPage: 10, total: 21);

        $this->assertSame(3, $result->lastPage());
        $this->assertTrue($result->hasNextPage());
        $this->assertTrue($result->hasPreviousPage());
        $this->assertFalse($result->isEmpty());
    }

    public function test_an_empty_result_has_one_last_page_and_no_next_page(): void
    {
        $result = new ListingResult([], currentPage: 1, perPage: 24, total: 0);

        $this->assertSame(1, $result->lastPage());
        $this->assertFalse($result->hasNextPage());
        $this->assertFalse($result->hasPreviousPage());
        $this->assertTrue($result->isEmpty());
    }

    public function test_it_rejects_invalid_pagination_metadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ListingResult([], currentPage: 0, perPage: 24, total: 0);
    }

    public function test_it_rejects_non_summary_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ListingResult([new stdClass], currentPage: 1, perPage: 24, total: 1);
    }

    private function entry(): EntrySummary
    {
        return new EntrySummary(
            id: new EntryIdentifier(1),
            name: 'Beispiel Pflegezentrum',
            slug: 'beispiel-pflegezentrum',
            categoryIdentifier: 'Ambulante Pflege',
            categoryLabel: 'Ambulante Pflege',
            locationIdentifier: 'potsdam',
            locationName: 'Potsdam',
            address: 'Musterstraße 1',
            postalCode: '14467',
            telephone: null,
        );
    }
}
