<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoDistrict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DistrictController extends Controller
{
    public function show(string $districtSlug): View
    {
        $district = GeoDistrict::query()
            ->where('slug', $districtSlug)
            ->whereIn('type', ['landkreis', 'kreisfreie_stadt'])
            ->whereHas('state', function (Builder $stateQuery): void {
                $stateQuery
                    ->where('ags', '12')
                    ->where('slug', 'brandenburg')
                    ->whereHas('country', fn (Builder $countryQuery): Builder => $countryQuery->where('iso2', 'DE'));
            })
            ->firstOrFail();

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

        $facilities = Facility::query()
            ->select('facilities.*')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->join('geo_municipalities', 'geo_municipalities.id', '=', 'cities.geo_municipality_id')
            ->where('geo_municipalities.district_id', $district->id)
            ->with('city')
            ->orderBy('cities.name')
            ->orderBy('facilities.name')
            ->orderBy('facilities.id')
            ->paginate(24);

        return view('districts.show', [
            'district' => $district,
            'cities' => $cities,
            'linkedCityCount' => $linkedCities->count(),
            'facilities' => $facilities,
            'facilityCount' => $facilities->total(),
        ]);
    }
}
