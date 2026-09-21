<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\CarePageService;
use App\Services\SeoActionContent;
use Illuminate\View\View;

class CityCareController extends Controller
{
    public function show(City $city, string $careSlug, CarePageService $pages): View
    {
        $category = $pages->category($city, $careSlug);
        abort_if($category === null, 404);

        $facilities = $pages->facilities($city, $careSlug)
            ->with('city')->orderBy('name')->orderBy('id')->get();
        abort_if($facilities->count() < $pages->minimumFacilities($city, $careSlug), 404);
        $editorial = app(SeoActionContent::class)->service($city, $category, $facilities);

        return view('cities.care', compact('city', 'category', 'facilities', 'editorial'));
    }
}
