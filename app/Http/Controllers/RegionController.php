<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function show(): View
    {
        return view('regions.brandenburg', [
            'cities' => City::query()->withCount('facilities')->orderBy('name')->get(),
            'facilityCount' => Facility::count(),
            'typeCount' => Facility::query()->distinct()->count('type'),
        ]);
    }
}
