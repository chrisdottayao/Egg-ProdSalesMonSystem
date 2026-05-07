<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\Flock;
use Illuminate\Http\Request;

class EggProductionController extends Controller
{
    public function index()
    {
        $productions = EggProduction::with('flock')->latest('date')->paginate(20);
        return view('productions.index', compact('productions'));
    }

    public function create()
    {
        $flocks = Flock::where('status', 'active')->orderBy('name')->get();
        return view('productions.create', compact('flocks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'flock_id'     => 'required|exists:flocks,id',
            'date'         => 'required|date',
            'total_eggs'   => 'required|integer|min:0',
            'cracked_eggs' => 'required|integer|min:0',
            'notes'        => 'nullable|string',
        ]);

        if ($validated['cracked_eggs'] > $validated['total_eggs']) {
            return back()->withErrors(['cracked_eggs' => 'Cracked eggs cannot exceed total eggs.'])->withInput();
        }

        EggProduction::create($validated);

        return redirect()->route('productions.index')->with('success', 'Production record added.');
    }

    public function edit(EggProduction $production)
    {
        $flocks = Flock::orderBy('name')->get();
        return view('productions.edit', compact('production', 'flocks'));
    }

    public function update(Request $request, EggProduction $production)
    {
        $validated = $request->validate([
            'flock_id'     => 'required|exists:flocks,id',
            'date'         => 'required|date',
            'total_eggs'   => 'required|integer|min:0',
            'cracked_eggs' => 'required|integer|min:0',
            'notes'        => 'nullable|string',
        ]);

        if ($validated['cracked_eggs'] > $validated['total_eggs']) {
            return back()->withErrors(['cracked_eggs' => 'Cracked eggs cannot exceed total eggs.'])->withInput();
        }

        $production->update($validated);

        return redirect()->route('productions.index')->with('success', 'Production record updated.');
    }

    public function destroy(EggProduction $production)
    {
        $production->delete();
        return redirect()->route('productions.index')->with('success', 'Production record deleted.');
    }
}
