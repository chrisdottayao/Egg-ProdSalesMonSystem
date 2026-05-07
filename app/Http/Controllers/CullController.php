<?php

namespace App\Http\Controllers;

use App\Models\CullRecord;
use App\Models\HenBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CullController extends Controller
{
    public function index()
    {
        $cullRecords = CullRecord::with('henBatch')->latest('date')->paginate(20);
        $henBatches  = HenBatch::where('status', 'Active')->orderBy('batch_id')->get();

        // Monthly stats
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthRecords = CullRecord::where('date', '>=', $thisMonthStart)->get();

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

        return view('cull.index', compact(
            'cullRecords', 'henBatches',
            'monthStats', 'cullingAnomaly'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'            => 'required|date',
            'hen_batch_id'    => 'nullable|exists:hen_batches,id',
            'quantity_culled' => 'required|integer|min:1',
            'reason'          => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        CullRecord::create($validated);

        return redirect()->route('cull.index')->with('success', 'Cull record saved successfully.');
    }

    public function destroy(CullRecord $cullRecord)
    {
        $cullRecord->delete();
        return redirect()->route('cull.index')->with('success', 'Cull record deleted.');
    }
}
