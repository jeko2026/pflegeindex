<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use App\Models\ServiceType;
use App\Services\CarePageService;
use App\Services\FacilityEnrichment\FacilityAttributePresenter;
use App\Services\FacilityNearby\FacilityNearbyPresenter;
use App\Services\FacilityProfilePresenter;
use App\Services\FacilityTitleDiscriminator;
use App\Services\QualityScoreService;
use Illuminate\View\View;

class SachsenDirectoryController extends Controller
{
    public function land(): View
    {
        $cities = City::query()->where('state_slug', 'sachsen')->whereHas('facilities', fn ($query) => $query->where('is_active', true))->withCount(['facilities' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->get();
        $facilityCount = Facility::query()->whereHas('city', fn ($query) => $query->where('state_slug', 'sachsen'))->where('is_active', true)->count();
        $typeCount = ServiceType::query()->whereHas('facilities', fn ($query) => $query->whereHas('city', fn ($cityQuery) => $cityQuery->where('state_slug', 'sachsen'))->where('is_active', true))->count();

        return view('sachsen.land', compact('cities', 'facilityCount', 'typeCount'));
    }

    public function city(City $city): View
    {
        abort_unless($city->state_slug === 'sachsen', 404);
        $facilities = $city->facilities()->where('is_active', true)->with('serviceTypes')->orderBy('name')->paginate(24);
        abort_if($facilities->total() === 0, 404);
        $services = ServiceType::query()->whereHas('facilities', fn ($q) => $q->where('city_id', $city->id)->where('is_active', true))->withCount(['facilities as facility_count' => fn ($q) => $q->where('city_id', $city->id)->where('is_active', true)])->get();
        $nearbyCities = City::query()->where('state_slug', 'sachsen')->whereKeyNot($city->id)->whereHas('facilities', fn ($query) => $query->where('is_active', true))->withCount(['facilities' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->limit(9)->get();

        return view('sachsen.city', compact('city', 'facilities', 'services', 'nearbyCities'));
    }

    public function service(City $city, string $service): View
    {
        abort_unless($city->state_slug === 'sachsen', 404);
        $type = ServiceType::where('slug', $service)->firstOrFail();
        $facilities = $type->facilities()->where('city_id', $city->id)->where('is_active', true)->with('serviceTypes')->orderBy('name')->get();
        abort_if($facilities->isEmpty(), 404);
        $noindex = $facilities->count() < 3;

        return view('sachsen.service', compact('city', 'type', 'facilities', 'noindex'));
    }

    public function facility(City $city, Facility $facility, FacilityProfilePresenter $profilePresenter, FacilityAttributePresenter $attributePresenter, FacilityTitleDiscriminator $titleDiscriminator, FacilityNearbyPresenter $nearbyPresenter, QualityScoreService $qualityScoreService, CarePageService $carePages): View
    {
        abort_unless($city->state_slug === 'sachsen' && $facility->city_id === $city->id && $facility->is_active, 404);
        $facility->load(['serviceTypes', 'socialLinks:id,facility_id,platform,url', 'openingHours:id,facility_id,hours_text,verified_at', 'sources:id,facility_id,source_type,source_name,source_url,last_seen_at']);
        $relatedFacilities = Facility::query()->where('city_id', $city->id)->where('is_active', true)->whereKeyNot($facility->id)->orderBy('name')->limit(3)->get();
        $profile = $profilePresenter->for($facility);

        return view('sachsen.facility', ['city' => $city, 'facility' => $facility, 'relatedFacilities' => $relatedFacilities, 'profile' => $profile, 'enrichmentGroups' => $attributePresenter->publicGroups($facility), 'titleDiscriminator' => $titleDiscriminator->for($facility), 'qualityScore' => $qualityScoreService->evaluate($facility), 'carePageLinks' => $carePages->links($city, $facility), 'nearbyPlaces' => $nearbyPresenter->for($facility)]);
    }
}
