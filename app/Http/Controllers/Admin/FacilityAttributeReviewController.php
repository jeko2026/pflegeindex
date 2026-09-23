<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacilityAttribute;
use App\Services\FacilityEnrichment\AttributeTaxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FacilityAttributeReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', FacilityAttribute::REVIEW_CANDIDATE);
        $state = (string) $request->query('state', '');
        $attributeKey = (string) $request->query('attribute_key', '');
        $medical = $request->boolean('medical');
        $medicalKeys = array_filter(AttributeTaxonomy::keys(), AttributeTaxonomy::medical(...));
        $attributes = FacilityAttribute::query()->with('facility.city')
            ->when($status !== 'all', fn ($query) => $query->where('review_status', $status))
            ->when(in_array($state, ['brandenburg', 'sachsen'], true), fn ($query) => $query->whereHas('facility.city', fn ($city) => $city->where('state_slug', $state)))
            ->when(AttributeTaxonomy::known($attributeKey), fn ($query) => $query->where('attribute_key', $attributeKey))
            ->when($medical, fn ($query) => $query->whereIn('attribute_key', $medicalKeys))
            ->latest('discovered_at')->paginate(30)->withQueryString();

        $attributeKeys = AttributeTaxonomy::keys();

        return view('admin.facility-attributes.index', compact('attributes', 'status', 'state', 'attributeKey', 'medical', 'attributeKeys'));
    }

    public function approve(FacilityAttribute $attribute): RedirectResponse
    {
        return $this->set($attribute, FacilityAttribute::REVIEW_APPROVED);
    }

    public function reject(FacilityAttribute $attribute): RedirectResponse
    {
        return $this->set($attribute, FacilityAttribute::REVIEW_REJECTED);
    }

    public function needsReview(FacilityAttribute $attribute): RedirectResponse
    {
        return $this->set($attribute, FacilityAttribute::REVIEW_NEEDS_REVIEW);
    }

    private function set(FacilityAttribute $attribute, string $status): RedirectResponse
    {
        $attribute->update(['review_status' => $status, 'verified_at' => $status === FacilityAttribute::REVIEW_APPROVED ? now() : $attribute->verified_at]);

        return back()->with('status', 'Attributstatus gespeichert.');
    }
}
