<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'facilityCount' => Facility::count(),
            'cityCount' => City::count(),
        ]);
    }
}
