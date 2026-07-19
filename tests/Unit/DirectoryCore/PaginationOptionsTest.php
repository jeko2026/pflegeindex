<?php

declare(strict_types=1);

namespace Tests\Unit\DirectoryCore;

use App\Platform\DirectoryCore\Domain\PaginationOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PaginationOptionsTest extends TestCase
{
    public function test_it_accepts_valid_pagination_values(): void
    {
        $pagination = new PaginationOptions(page: 2, perPage: 24);

        $this->assertSame(2, $pagination->page);
        $this->assertSame(24, $pagination->perPage);
    }

    public function test_it_rejects_a_page_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaginationOptions(page: 0, perPage: 24);
    }

    public function test_it_rejects_a_non_positive_page_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaginationOptions(page: 1, perPage: 0);
    }
}
