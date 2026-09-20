<?php

namespace App\Http\Controllers;

use App\Models\HenBatch;
use App\Services\BuildingPerformanceService;
use Illuminate\Http\Request;

class BuildingDashboardController extends Controller
{
    /**
     * 3H shipped the per-building view as its own page. 3J merged that
     * presentation into the main dashboard (an expand-in-place accordion),
     * per the IT expert's actual ask — "the dashboard he already opens every
     * day," not a second page. This route is kept, not deleted, purely so an
     * old bookmark or link still lands somewhere useful.
     */
    public function index(Request $request)
    {
        return redirect()->route('dashboard', $request->only(['building', 'window']));
    }

    /**
     * Per-building detail panel, fetched on demand when a building row is
     * expanded on the main dashboard — one request per building per window,
     * not one per panel. Returns a rendered HTML fragment (not raw JSON) so
     * the existing Blade markup/chart wiring from 3H is reused as-is rather
     * than reimplemented in JavaScript.
     */
    public function detail(Request $request, HenBatch $building, BuildingPerformanceService $service)
    {
        [$start, $end] = $service->resolveWindow($request);
        $window        = $request->input('window', '1');

        return view('dashboard.building-detail', $service->buildingDetail($building, $start, $end, $window));
    }
}
