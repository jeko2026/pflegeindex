<?php

declare(strict_types=1);

namespace App\Projects\FuneralIndex\Directory;

use App\Platform\DirectoryCore\Contracts\EntryRepository;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;

/**
 * Architecture proof only: no FuneralIndex persistence or business rules exist yet.
 */
final class FuneralEntryRepository implements EntryRepository
{
    public const PROJECT_NAME = 'FuneralIndex';

    public const ROUTE_PREFIX = 'bestatter';

    public const ENTRY_LABEL = 'Bestatter';

    public const NAVIGATION_LABEL = 'Bestatter finden';

    public function list(ListingCriteria $criteria): ListingResult
    {
        return new ListingResult(
            entries: [],
            currentPage: $criteria->pagination->page,
            perPage: $criteria->pagination->perPage,
            total: 0,
        );
    }
}
