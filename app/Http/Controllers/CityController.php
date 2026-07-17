<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\View\View;

class CityController extends Controller
{
    public function show(City $city): View
    {
        $facilities = $city->facilities()
            ->with('city')
            ->orderBy('name')
            ->paginate(24);

        return view('cities.show', compact('city', 'facilities'));
    }
}
