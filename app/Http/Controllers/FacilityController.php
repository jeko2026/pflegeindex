<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use App\Services\CarePageService;
use App\Services\FacilityProfilePresenter;
use App\Services\FacilityTitleDiscriminator;
use App\Services\QualityScoreService;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function show(City $city, Facility $facility, QualityScoreService $qualityScoreService, CarePageService $carePages, FacilityProfilePresenter $profilePresenter, FacilityTitleDiscriminator $titleDiscriminator): View
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

        return view('facilities.show', [
            'city' => $city,
            'carePageLinks' => $carePages->links($city, $facility),
            'facility' => $facility,
            'relatedFacilities' => $relatedFacilities,
            'qualityScore' => $qualityScoreService->evaluate($facility),
            'profile' => $profilePresenter->for($facility),
            'titleDiscriminator' => $titleDiscriminator->for($facility),
        ]);
    }
}
