<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\HenBatch;
use Illuminate\Http\Request;

class EggProductionController extends Controller
{
    public function index()
    {
        $productions = EggProduction::latest('date')->paginate(20);
        $activeHens = HenBatch::activeHenCount();
        return view('productions.index', compact('productions', 'activeHens'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'          => 'required|date',
            'eggs_collected'=> 'required|integer|min:0|max:10000',
            'active_hens'   => 'required|integer|min:1',
            'egg_size'      => 'required|string',
            'egg_weight'    => 'nullable|numeric|min:0',
            'mortality'     => 'required|integer|min:0',
            'notes'         => 'nullable|string',
        ]);

        EggProduction::create($validated);

        return redirect()->route('productions.index')->with('success', 'Production entry saved successfully!');
    }

    public function edit(EggProduction $production)
    {
        return view('productions.edit', compact('production'));
    }

    public function update(Request $request, EggProduction $production)
    {
        $validated = $request->validate([
            'date'          => 'required|date',
            'eggs_collected'=> 'required|integer|min:0|max:10000',
            'active_hens'   => 'required|integer|min:1',
            'egg_size'      => 'required|string',
            'egg_weight'    => 'nullable|numeric|min:0',
            'mortality'     => 'required|integer|min:0',
            'notes'         => 'nullable|string',
        ]);

        $production->update($validated);

        return redirect()->route('productions.index')->with('success', 'Production record updated.');
    }

    public function destroy(EggProduction $production)
    {
        $production->delete();
        return redirect()->route('productions.index')->with('success', 'Production record deleted.');
    }
}
