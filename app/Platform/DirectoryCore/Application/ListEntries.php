<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Application;

use App\Platform\DirectoryCore\Contracts\EntryRepository;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;

final readonly class ListEntries
{
    public function __construct(
        private EntryRepository $repository,
    ) {}

    public function execute(ListingCriteria $criteria): ListingResult
    {
        return $this->repository->list($criteria);
    }
}
