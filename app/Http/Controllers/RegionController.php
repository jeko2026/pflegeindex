<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
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

        return view('regions.brandenburg', [
            'cities' => City::query()
                ->where('state_slug', 'brandenburg')
                ->withCount('facilities')
                ->orderBy('name')
                ->get(),
            'facilities' => $facilities,
            'facilityCount' => $facilities->total(),
            'typeCount' => Facility::query()
                ->join('cities', 'cities.id', '=', 'facilities.city_id')
                ->where('cities.state_slug', 'brandenburg')
                ->distinct()
                ->count('facilities.type'),
        ]);
    }
}
