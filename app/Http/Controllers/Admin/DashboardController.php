<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ContactSuggestion;
use App\Models\Facility;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'facilityCount' => Facility::count(),
            'cityCount' => City::count(),
            'verifiedCount' => Facility::query()->where('contact_status', 'verified')->count(),
            'withoutPhoneCount' => Facility::query()->whereNull('phone')->count(),
            'pendingSuggestionCount' => ContactSuggestion::query()->where('decision', 'pending')->count(),
            'recentFacilities' => Facility::query()
                ->with('city')
                ->whereNotNull('contact_checked_at')
                ->latest('contact_checked_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
