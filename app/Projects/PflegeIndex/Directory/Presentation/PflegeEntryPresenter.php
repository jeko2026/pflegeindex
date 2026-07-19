<?php

declare(strict_types=1);

namespace App\Projects\PflegeIndex\Directory\Presentation;

use App\Platform\DirectoryCore\ReadModel\EntrySummary;
use UnexpectedValueException;

final class PflegeEntryPresenter
{
    /**
     * @param  list<EntrySummary>  $entries
     * @return list<PflegeEntryCardViewModel>
     */
    public function presentMany(array $entries): array
    {
        return array_map($this->present(...), $entries);
    }

    public function present(EntrySummary $entry): PflegeEntryCardViewModel
    {
        if ($entry->locationIdentifier === null || $entry->locationName === null) {
            throw new UnexpectedValueException('A PflegeIndex directory entry requires a city.');
        }

        return new PflegeEntryCardViewModel(
            name: $entry->name,
            slug: $entry->slug,
            type: $entry->categoryLabel ?? $entry->categoryIdentifier ?? '',
            city: new PflegeEntryLocationViewModel(
                name: $entry->locationName,
                slug: $entry->locationIdentifier,
            ),
            address: $entry->address,
            postal_code: $entry->postalCode,
            phone: $entry->telephone,
            url: route('facilities.show', [$entry->locationIdentifier, $entry->slug]),
        );
    }
}
