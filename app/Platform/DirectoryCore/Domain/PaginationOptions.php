<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Domain;

use InvalidArgumentException;

final readonly class PaginationOptions
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }

        if ($this->perPage <= 0) {
            throw new InvalidArgumentException('Items per page must be greater than 0.');
        }
    }
}
