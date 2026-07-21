<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesPublicPagination;
use App\Models\City;
use App\Models\Facility;
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

class RegionController extends Controller
{
    use ValidatesPublicPagination;

    private const BRANDENBURG_STATE_IDENTIFIER = 'brandenburg';

    public function show(
        Request $request,
        PflegeEntryRepository $repository,
        PflegeEntryPresenter $presenter,
    ): View {
        $page = $this->publicPage($request);
        $listingResult = (new ListEntries($repository))->execute(new ListingCriteria(
            pagination: new PaginationOptions(
                page: $page,
                perPage: 24,
            ),
            sort: EntrySort::Default,
            locationScope: LocationScope::state(self::BRANDENBURG_STATE_IDENTIFIER),
        ));
        $this->ensurePublicPageExists($listingResult);

        $facilities = new LengthAwarePaginator(
            items: $presenter->presentMany($listingResult->entries),
            total: $listingResult->total,
            perPage: $listingResult->perPage,
            currentPage: $listingResult->currentPage,
            options: ['path' => $request->url()],
        );

        $districts = collect();

        if ($facilities->onFirstPage()) {
            $districts = GeoDistrict::query()
                ->whereIn('type', ['landkreis', 'kreisfreie_stadt'])
                ->whereHas('state', function (Builder $stateQuery): void {
                    $stateQuery
                        ->where('ags', '12')
                        ->where('slug', 'brandenburg')
                        ->whereHas('country', fn (Builder $countryQuery): Builder => $countryQuery->where('iso2', 'DE'));
                })
                ->withCount([
                    'municipalities as linked_cities_count' => fn (Builder $query): Builder => $query
                        ->join('cities', 'cities.geo_municipality_id', '=', 'geo_municipalities.id'),
                    'municipalities as facilities_count' => fn (Builder $query): Builder => $query
                        ->join('cities', 'cities.geo_municipality_id', '=', 'geo_municipalities.id')
                        ->join('facilities', 'facilities.city_id', '=', 'cities.id'),
                ])
                ->orderByRaw("CASE WHEN type = 'landkreis' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get();
        }

        return view('regions.brandenburg', [
            'cities' => City::query()
                ->where('state_slug', 'brandenburg')
                ->has('facilities')
                ->withCount('facilities')
                ->orderBy('name')
                ->get(),
            'facilities' => $facilities,
            'districts' => $districts,
            'facilityCount' => $facilities->total(),
            'typeCount' => Facility::query()
                ->join('cities', 'cities.id', '=', 'facilities.city_id')
                ->where('cities.state_slug', 'brandenburg')
                ->distinct()
                ->count('facilities.type'),
        ]);
    }
}
