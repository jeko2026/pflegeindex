<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function show(City $city, Facility $facility): View
    {
        $relatedFacilities = Facility::query()
            ->with('city')
            ->where('city_id', $facility->city_id)
            ->where('id', '!=', $facility->id)
            ->orderBy('type')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(3)
            ->get();

        return view('facilities.show', compact('city', 'facility', 'relatedFacilities'));
    }
}
