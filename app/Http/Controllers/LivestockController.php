<?php

namespace App\Http\Controllers;

use App\Models\CattleRecord;
use App\Models\HenBatch;
use Illuminate\Http\Request;

class LivestockController extends Controller
{
    public function index()
    {
        $henBatches = HenBatch::latest()->get();
        $cattleRecords = CattleRecord::latest()->get();
        $activeHenCount = HenBatch::activeHenCount();
        return view('livestock.index', compact('henBatches', 'cattleRecords', 'activeHenCount'));
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

        return redirect()->route('livestock.index')->with('success', 'Hen record saved! Active hen count synced with Production module.');
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

        return redirect()->route('livestock.index')->with('success', 'Hen record updated.');
    }

    public function destroyHen(HenBatch $henBatch)
    {
        $henBatch->delete();
        return redirect()->route('livestock.index')->with('success', 'Hen record deleted.');
    }

    public function storeCattle(Request $request)
    {
        $validated = $request->validate([
            'ear_tag'    => 'required|string|max:50|unique:cattle_records,ear_tag',
            'status'     => 'required|in:Active,Sold,Deceased',
            'entry_date' => 'required|date',
            'notes'      => 'nullable|string',
        ]);

        CattleRecord::create($validated);

        return redirect()->route('livestock.index')->with('success', 'Cattle record saved successfully!');
    }

    public function updateCattle(Request $request, CattleRecord $cattleRecord)
    {
        $validated = $request->validate([
            'ear_tag'    => 'required|string|max:50|unique:cattle_records,ear_tag,' . $cattleRecord->id,
            'status'     => 'required|in:Active,Sold,Deceased',
            'entry_date' => 'required|date',
            'notes'      => 'nullable|string',
        ]);

        $cattleRecord->update($validated);

        return redirect()->route('livestock.index')->with('success', 'Cattle record updated.');
    }

    public function destroyCattle(CattleRecord $cattleRecord)
    {
        $cattleRecord->delete();
        return redirect()->route('livestock.index')->with('success', 'Cattle record deleted.');
    }
}
