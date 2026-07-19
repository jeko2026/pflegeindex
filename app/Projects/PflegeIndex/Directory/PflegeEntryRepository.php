<?php

declare(strict_types=1);

namespace App\Projects\PflegeIndex\Directory;

use App\Models\Facility;
use App\Platform\DirectoryCore\Contracts\EntryRepository;
use App\Platform\DirectoryCore\Domain\EntryIdentifier;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\LocationScopeType;
use App\Platform\DirectoryCore\ReadModel\EntrySummary;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

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
            );

        if ($criteria->locationScope !== null) {
            $this->applyLocationScope($query, $criteria->locationScope);
        }

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

    private function applyLocationScope(Builder $query, LocationScope $scope): void
    {
        match ($scope->type) {
            LocationScopeType::City => $query->where('cities.slug', $scope->identifier),
            LocationScopeType::District,
            LocationScopeType::State => throw new LogicException(
                "Location scope {$scope->type->name} is not supported by PflegeEntryRepository.",
            ),
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
            locationScope: $facility->city === null ? null : LocationScope::city($facility->city->slug),
            locationName: $facility->city?->name,
            address: $facility->address,
            postalCode: $facility->postal_code,
            telephone: $facility->phone,
        );
    }
}
