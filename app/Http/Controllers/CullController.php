<?php

namespace App\Http\Controllers;

use App\Models\CullRecord;
use App\Models\HenBatch;
use App\Support\LatestEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CullController extends Controller
{
    public function index(Request $request)
    {
        // S4: building filter for the list — every tracked building, including
        // ended (culled) ones, so their records stay reachable.
        $filterBuildings = HenBatch::tracked()->orderByRaw('COALESCE(display_no, building_no) ASC')->get();
        $buildingFilter  = $filterBuildings->firstWhere('id', (int) $request->input('building_id'))?->id;

        // Tracked-only (3N) — the dropdown above already used .tracked(), but
        // the list and monthly stats below it didn't, so they still showed
        // all 45 buildings' history even though only 3 were selectable.
        $cullRecords = CullRecord::with('henBatch')
            ->whereHas('henBatch', fn ($q) => $q->tracked())
            ->when($buildingFilter, fn ($q) => $q->where('hen_batch_id', $buildingFilter))
            ->latest('date')->paginate(20)->withQueryString();
        // Entry dropdown: a building already ended (culled, S3) can't be culled again.
        $henBatches  = HenBatch::where('status', 'Active')->tracked()->notEnded()->orderBy('batch_id')->get();

        // Monthly stats
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthRecords = CullRecord::whereHas('henBatch', fn ($q) => $q->tracked())
            ->where('date', '>=', $thisMonthStart)->get();

        $totalCulledThisMonth = $thisMonthRecords->sum('quantity_culled');
        $totalCullEvents      = $thisMonthRecords->count();

        $reasonCounts = $thisMonthRecords->groupBy('reason')
            ->map(fn($g) => $g->sum('quantity_culled'))
            ->sortDesc();
        $mostCommonReason = $reasonCounts->keys()->first() ?? 'N/A';

        $historicalMonthlyAvg = 4; // baseline monthly average
        $cullingAnomaly = $totalCulledThisMonth > $historicalMonthlyAvg;
        $vsAvg = $totalCulledThisMonth - $historicalMonthlyAvg;

        $monthStats = [
            'total_culled'         => $totalCulledThisMonth,
            'most_common_reason'   => $mostCommonReason,
            'total_events'         => $totalCullEvents,
            'vs_avg'               => $vsAvg,
            'historical_avg'       => $historicalMonthlyAvg,
        ];

        $latest = LatestEntry::for(
            CullRecord::class,
            fn ($q) => $q->whereHas('henBatch', fn ($b) => $b->tracked()),
        );

        return view('cull.index', compact(
            'cullRecords', 'henBatches',
            'monthStats', 'cullingAnomaly', 'latest', 'filterBuildings', 'buildingFilter'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'            => $this->entryDateRules(),
            'hen_batch_id'    => 'nullable|exists:hen_batches,id',
            'quantity_culled' => 'required|integer|min:1',
            'reason'          => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'heads_sold'      => 'nullable|integer|min:0',
            'price_per_head'  => 'nullable|numeric|min:0',
            'buyer'           => 'nullable|string|max:255',
        ]);

        $record = CullRecord::create($validated);

        return redirect()->route('cull.index')
            ->with('success', 'Cull record saved successfully.')
            ->with('latest_ids', [$record->id]);
    }

    public function destroy(CullRecord $cullRecord)
    {
        $this->denyPastRecordChangeForStaff($cullRecord->date);

        $cullRecord->delete();
        return redirect()->route('cull.index')->with('success', 'Cull record deleted.');
    }
}
