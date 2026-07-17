<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function show(City $city, Facility $facility): View
    {
        return view('facilities.show', compact('city', 'facility'));
    }
}
