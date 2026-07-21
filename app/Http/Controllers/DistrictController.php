<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesPublicPagination;
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

class DistrictController extends Controller
{
    use ValidatesPublicPagination;

    public function show(
        string $districtSlug,
        Request $request,
        PflegeEntryRepository $repository,
        PflegeEntryPresenter $presenter,
    ): View {
        $allDistricts = GeoDistrict::query()
            ->whereIn('type', ['landkreis', 'kreisfreie_stadt'])
            ->whereHas('state', function (Builder $stateQuery): void {
                $stateQuery
                    ->where('ags', '12')
                    ->where('slug', 'brandenburg')
                    ->whereHas('country', fn (Builder $countryQuery): Builder => $countryQuery->where('iso2', 'DE'));
            })
            ->orderByRaw("CASE WHEN type = 'landkreis' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        /** @var GeoDistrict|null $district */
        $district = $allDistricts->firstWhere('slug', $districtSlug);

        abort_if($district === null, 404);

        $otherDistricts = $allDistricts
            ->reject(fn (GeoDistrict $d): bool => $d->id === $district->id)
            ->take(8)
            ->values();

        $linkedCities = City::query()
            ->whereNotNull('geo_municipality_id')
            ->whereHas(
                'geoMunicipality',
                fn (Builder $query): Builder => $query->where('district_id', $district->id),
            )
            ->withCount('facilities')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $cities = $linkedCities
            ->filter(fn (City $city): bool => $city->facilities_count > 0)
            ->values();

        $page = $this->publicPage($request);
        $listingResult = (new ListEntries($repository))->execute(new ListingCriteria(
            pagination: new PaginationOptions(
                page: $page,
                perPage: 24,
            ),
            sort: EntrySort::Default,
            locationScope: LocationScope::district($district->ags),
        ));
        $this->ensurePublicPageExists($listingResult);

        $facilities = new LengthAwarePaginator(
            items: $presenter->presentMany($listingResult->entries),
            total: $listingResult->total,
            perPage: $listingResult->perPage,
            currentPage: $listingResult->currentPage,
            options: ['path' => $request->url()],
        );

        return view('districts.show', [
            'district' => $district,
            'cities' => $cities,
            'linkedCityCount' => $linkedCities->count(),
            'facilities' => $facilities,
            'facilityCount' => $facilities->total(),
            'otherDistricts' => $otherDistricts,
        ]);
    }
}
