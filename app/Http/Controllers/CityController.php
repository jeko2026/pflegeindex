<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\GeoDistrict;
use App\Platform\DirectoryCore\Application\ListEntries;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\PaginationOptions;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Projects\PflegeIndex\Directory\PflegeEntryRepository;
use App\Projects\PflegeIndex\Directory\Presentation\PflegeEntryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CityController extends Controller
{
    public function show(
        City $city,
        Request $request,
        PflegeEntryRepository $repository,
        PflegeEntryPresenter $presenter,
    ): View {
        $stateSlug = (string) $request->route('stateSlug');

        abort_unless($stateSlug !== '' && $city->state_slug === $stateSlug, 404);

        $listingResult = (new ListEntries($repository))->execute(new ListingCriteria(
            pagination: new PaginationOptions(
                page: max(1, $request->integer('page', 1)),
                perPage: 24,
            ),
            sort: EntrySort::Default,
            locationScope: LocationScope::city($city->slug),
        ));

        $facilities = new LengthAwarePaginator(
            items: $presenter->presentMany($listingResult->entries),
            total: $listingResult->total,
            perPage: $listingResult->perPage,
            currentPage: $listingResult->currentPage,
            options: ['path' => $request->url()],
        );

        $facilityCount = $facilities->total();
        $typeCount = $city->facilities()->distinct()->count('type');

        // Load sibling cities from the same GeoCore district (no N+1: single query)
        $nearbyCities = collect();
        if ($city->geo_municipality_id !== null) {
            $districtId = $city->geoMunicipality()->value('district_id');
            if ($districtId !== null) {
                $nearbyCities = City::query()
                    ->where('id', '!=', $city->id)
                    ->whereNotNull('geo_municipality_id')
                    ->whereHas(
                        'geoMunicipality',
                        fn (Builder $q): Builder => $q->where('district_id', $districtId),
                    )
                    ->has('facilities')
                    ->withCount('facilities')
                    ->orderBy('name')
                    ->limit(9)
                    ->get();
            }
        }

        return view('cities.show', compact('city', 'facilities', 'facilityCount', 'typeCount', 'nearbyCities'));
    }
}
