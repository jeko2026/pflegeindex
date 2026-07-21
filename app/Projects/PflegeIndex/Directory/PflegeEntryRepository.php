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

        $total = $query->toBase()->getCountForPagination();

        if ($criteria->pagination->page > max(1, (int) ceil($total / $criteria->pagination->perPage))) {
            return new ListingResult(
                entries: [],
                currentPage: $criteria->pagination->page,
                perPage: $criteria->pagination->perPage,
                total: $total,
            );
        }

        $paginator = $query->paginate(
            perPage: $criteria->pagination->perPage,
            page: $criteria->pagination->page,
            total: $total,
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
            LocationScopeType::District => $query
                ->join(
                    'geo_municipalities',
                    'geo_municipalities.id',
                    '=',
                    'cities.geo_municipality_id',
                )
                ->join('geo_districts', 'geo_districts.id', '=', 'geo_municipalities.district_id')
                ->where('geo_districts.ags', $scope->identifier),
            LocationScopeType::State => $this->applyStateScope($query, $scope),
        };
    }

    private function applyStateScope(Builder $query, LocationScope $scope): void
    {
        $query
            ->leftJoin(
                'geo_municipalities',
                'geo_municipalities.id',
                '=',
                'cities.geo_municipality_id',
            )
            ->leftJoin('geo_districts', 'geo_districts.id', '=', 'geo_municipalities.district_id')
            ->leftJoin('geo_states', 'geo_states.id', '=', 'geo_districts.state_id')
            ->leftJoin('geo_countries', 'geo_countries.id', '=', 'geo_states.country_id')
            ->where(function (Builder $stateQuery) use ($scope): void {
                $stateQuery
                    ->where(function (Builder $officialState) use ($scope): void {
                        $officialState
                            ->where('geo_states.slug', $scope->identifier)
                            ->where('geo_countries.iso2', 'DE');
                    })
                    ->orWhere(function (Builder $legacyCity) use ($scope): void {
                        // Unmapped legacy cities have no official GeoCore relation yet.
                        $legacyCity
                            ->whereNull('cities.geo_municipality_id')
                            ->where('cities.state_slug', $scope->identifier);
                    });
            });
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
