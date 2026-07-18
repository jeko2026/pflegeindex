<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CityController extends Controller
{
    public function show(City $city, Request $request): View
    {
        $stateSlug = (string) $request->route('stateSlug');

        abort_unless($stateSlug !== '' && $city->state_slug === $stateSlug, 404);

        $facilities = $city->facilities()
            ->with('city')
            ->whereHas('city', fn (Builder $query): Builder => $query->where('state_slug', $stateSlug))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(24);

        $facilityCount = $facilities->total();
        $typeCount = $city->facilities()->distinct()->count('type');

        return view('cities.show', compact('city', 'facilities', 'facilityCount', 'typeCount'));
    }
}
