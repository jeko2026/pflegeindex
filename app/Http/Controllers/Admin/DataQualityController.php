<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataQualityDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DataQualityController extends Controller
{
    public function __invoke(Request $request, DataQualityDashboardService $dashboard): View
    {
        return view('admin.data-quality', $dashboard->dashboard($request->query()));
    }
}
