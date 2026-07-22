<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
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
        $topCities = City::query()
            ->has('facilities')
            ->withCount('facilities')
            ->orderByDesc('facilities_count')
            ->limit(10)
            ->get();

        return view('home', [
            'facilityCount' => Facility::count(),
            'cityCount' => City::count(),
            'topCities' => $topCities,
            'popularSearches' => collect(self::POPULAR_SEARCHES),
        ]);
    }
}
