<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\ReadModel;

use InvalidArgumentException;

final readonly class ListingResult
{
    /**
     * @param  list<EntrySummary>  $entries
     */
    public function __construct(
        public array $entries,
        public int $currentPage,
        public int $perPage,
        public int $total,
    ) {
        if (! array_is_list($this->entries)) {
            throw new InvalidArgumentException('Entries must be a list.');
        }

        foreach ($this->entries as $entry) {
            if (! $entry instanceof EntrySummary) {
                throw new InvalidArgumentException('Every entry must be an EntrySummary.');
            }
        }

        if ($this->currentPage < 1) {
            throw new InvalidArgumentException('Current page must be at least 1.');
        }

        if ($this->perPage <= 0) {
            throw new InvalidArgumentException('Items per page must be greater than 0.');
        }

        if ($this->total < 0) {
            throw new InvalidArgumentException('Total cannot be negative.');
        }
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
