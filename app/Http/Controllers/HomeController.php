<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** @var list<array{label: string, query: string, type: string}> */
    private const POPULAR_SEARCHES = [
        ['label' => 'Pflegedienst Potsdam',                  'query' => 'Potsdam',                   'type' => 'Ambulante Pflege'],
        ['label' => 'Pflegeheim Cottbus',                    'query' => 'Cottbus',                   'type' => 'Stationäre/teilstationäre Pflege'],
        ['label' => 'Pflegeheim Brandenburg an der Havel',  'query' => 'Brandenburg an der Havel',  'type' => 'Stationäre/teilstationäre Pflege'],
        ['label' => 'Pflegedienst Frankfurt (Oder)',         'query' => 'Frankfurt (Oder)',           'type' => 'Ambulante Pflege'],
        ['label' => 'Pflegeheim Oranienburg',                'query' => 'Oranienburg',               'type' => 'Stationäre/teilstationäre Pflege'],
        ['label' => 'Pflegedienst Eberswalde',               'query' => 'Eberswalde',                'type' => 'Ambulante Pflege'],
    ];

    public function __invoke(): View
    {
        $facilityCount = Cache::remember('home_facility_count', 86400, fn () => Facility::count());
        $cityCount = Cache::remember('home_city_count', 86400, fn () => City::count());
        $brandenburgFacilityCount = Cache::remember('home_brandenburg_facility_count_v2', 86400, fn () => Facility::whereHas('city', fn ($query) => $query->where('state_slug', 'brandenburg'))->where('is_active', true)->count());
        $sachsenFacilityCount = Cache::remember('home_sachsen_facility_count_v2', 86400, fn () => Facility::whereHas('city', fn ($query) => $query->where('state_slug', 'sachsen'))->where('is_active', true)->count());
        $topCities = Cache::remember('home_top_cities_v2', 86400, fn () => City::query()
            ->has('facilities')
            ->withCount('facilities')
            ->orderByDesc('facilities_count')
            ->limit(10)
            ->get()
            ->map(fn ($city) => [
                'name' => $city->name,
                'slug' => $city->slug,
                'state_slug' => $city->state_slug,
            ])
            ->toArray()
        );

        return view('home', [
            'facilityCount' => $facilityCount,
            'cityCount' => $cityCount,
            'topCities' => $topCities,
            'popularSearches' => collect(self::POPULAR_SEARCHES),
            'brandenburgFacilityCount' => $brandenburgFacilityCount,
            'sachsenFacilityCount' => $sachsenFacilityCount,
        ]);
    }
}
