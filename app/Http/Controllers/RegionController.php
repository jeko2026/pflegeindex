<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoDistrict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function show(): View
    {
        $facilities = Facility::query()
            ->select('facilities.*')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->with('city')
            ->where('cities.state_slug', 'brandenburg')
            ->orderBy('cities.name')
            ->orderBy('facilities.name')
            ->orderBy('facilities.id')
            ->paginate(24);

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
