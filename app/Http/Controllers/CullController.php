<?php

namespace App\Http\Controllers;

use App\Models\CullRecord;
use App\Models\HenBatch;
use Illuminate\Http\Request;

class CullController extends Controller
{
    public function index()
    {
        $cullRecords = CullRecord::with('henBatch')->latest('date')->paginate(20);
        $henBatches = HenBatch::where('status', 'Active')->orderBy('batch_id')->get();
        return view('cull.index', compact('cullRecords', 'henBatches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'hen_batch_id'   => 'nullable|exists:hen_batches,id',
            'quantity_culled'=> 'required|integer|min:1',
            'reason'         => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
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
