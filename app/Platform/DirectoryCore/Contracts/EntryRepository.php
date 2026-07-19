<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Contracts;

use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;

interface EntryRepository
{
    public function list(ListingCriteria $criteria): ListingResult;
}
