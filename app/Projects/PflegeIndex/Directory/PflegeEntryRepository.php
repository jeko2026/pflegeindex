<?php

declare(strict_types=1);

namespace App\Projects\PflegeIndex\Directory;

use App\Models\Facility;
use App\Platform\DirectoryCore\Contracts\EntryRepository;
use App\Platform\DirectoryCore\Domain\EntryIdentifier;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\ReadModel\EntrySummary;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;
use Illuminate\Database\Eloquent\Builder;

final class PflegeEntryRepository implements EntryRepository
{
    public function list(ListingCriteria $criteria): ListingResult
    {
        $query = Facility::query()
            ->select('facilities.*')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->with('city')
            ->when($criteria->searchQuery !== null, function (Builder $builder) use ($criteria): void {
                $builder->where(function (Builder $search) use ($criteria): void {
                    $like = "%{$criteria->searchQuery}%";

                    $search->where('facilities.name', 'like', $like)
                        ->orWhere('facilities.address', 'like', $like)
                        ->orWhere('facilities.postal_code', 'like', $like)
                        ->orWhere('cities.name', 'like', $like);
                });
            })
            ->when(
                $criteria->categoryIdentifier !== null,
                fn (Builder $builder) => $builder->where('facilities.type', $criteria->categoryIdentifier),
            )
            ->when(
                $criteria->locationIdentifier !== null,
                fn (Builder $builder) => $builder->where('cities.slug', $criteria->locationIdentifier),
            );

        $this->applySort($query, $criteria->sort);

        $paginator = $query->paginate(
            perPage: $criteria->pagination->perPage,
            page: $criteria->pagination->page,
        );

        $entries = $paginator->getCollection()
            ->map(fn (Facility $facility): EntrySummary => $this->toEntrySummary($facility))
            ->values()
            ->all();

        return new ListingResult(
            entries: $entries,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
        );
    }

    private function applySort(Builder $query, EntrySort $sort): void
    {
        match ($sort) {
            EntrySort::Default => $query
                ->orderBy('cities.name')
                ->orderBy('facilities.name')
                ->orderBy('facilities.id'),
            EntrySort::NameAscending => $query->orderBy('facilities.name'),
            EntrySort::NameDescending => $query->orderByDesc('facilities.name'),
        };
    }

    private function toEntrySummary(Facility $facility): EntrySummary
    {
        return new EntrySummary(
            id: new EntryIdentifier($facility->getKey()),
            name: $facility->name,
            slug: $facility->slug,
            categoryIdentifier: $facility->type,
            categoryLabel: $facility->type,
            locationIdentifier: $facility->city?->slug,
            locationName: $facility->city?->name,
            address: $facility->address,
            postalCode: $facility->postal_code,
            telephone: $facility->phone,
        );
    }
}
