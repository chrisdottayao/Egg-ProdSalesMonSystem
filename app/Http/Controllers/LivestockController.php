<?php

namespace App\Http\Controllers;

use App\Models\HenBatch;
use Illuminate\Http\Request;

class LivestockController extends Controller
{
    public function index()
    {
        // Tracked-only (3M): the live system actively displays 3 of the
        // farm's 45 real buildings. The other 42's flock records aren't
        // deleted — just not listed here — and stay reachable via Tinker/DB
        // if ever needed.
        $henBatches = HenBatch::tracked()->latest()->get();
        $activeHenCount = HenBatch::activeHenCount();
        return view('livestock.index', compact('henBatches', 'activeHenCount'));
    }

    public function storeHen(Request $request)
    {
        $validated = $request->validate([
            'batch_id'   => 'required|string|max:50|unique:hen_batches,batch_id',
            'batch_size' => 'required|integer|min:1',
            'status'     => 'required|in:Active,Culled,Mortality',
            'entry_date' => 'required|date',
            'notes'      => 'nullable|string',
            'pen_number' => 'nullable|string|max:100',
            'building'   => 'nullable|string|max:100',
        ]);

        HenBatch::create($validated);

        return redirect()->route('flock-records.index')->with('success', 'Hen record saved! Active hen count synced with Production module.');
    }

    public function updateHen(Request $request, HenBatch $henBatch)
    {
        $validated = $request->validate([
            'batch_id'   => 'required|string|max:50|unique:hen_batches,batch_id,' . $henBatch->id,
            'batch_size' => 'required|integer|min:1',
            'status'     => 'required|in:Active,Culled,Mortality',
            'entry_date' => 'required|date',
            'notes'      => 'nullable|string',
            'pen_number' => 'nullable|string|max:100',
            'building'   => 'nullable|string|max:100',
        ]);

        $henBatch->update($validated);

        return redirect()->route('flock-records.index')->with('success', 'Hen record updated.');
    }

    public function destroyHen(HenBatch $henBatch)
    {
        $henBatch->delete();
        return redirect()->route('flock-records.index')->with('success', 'Hen record deleted.');
    }
}
