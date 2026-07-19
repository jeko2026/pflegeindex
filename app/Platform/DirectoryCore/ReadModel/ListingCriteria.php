<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\ReadModel;

use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\PaginationOptions;

final readonly class ListingCriteria
{
    public ?string $searchQuery;

    public ?string $categoryIdentifier;

    public function __construct(
        public PaginationOptions $pagination,
        public EntrySort $sort,
        ?string $searchQuery = null,
        public ?LocationScope $locationScope = null,
        ?string $categoryIdentifier = null,
    ) {
        $this->searchQuery = $this->normalizeOptionalString($searchQuery);
        $this->categoryIdentifier = $this->normalizeOptionalString($categoryIdentifier);
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }
}
