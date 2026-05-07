<?php

namespace App\Http\Controllers;

use App\Models\Flock;
use Illuminate\Http\Request;

class FlockController extends Controller
{
    public function index()
    {
        $flocks = Flock::latest()->paginate(15);
        return view('flocks.index', compact('flocks'));
    }

    public function create()
    {
        return view('flocks.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'breed'            => 'nullable|string|max:255',
            'quantity'         => 'required|integer|min:1',
            'acquisition_date' => 'required|date',
            'status'           => 'required|in:active,sold,retired',
            'notes'            => 'nullable|string',
        ]);

        Flock::create($validated);

        return redirect()->route('flocks.index')->with('success', 'Flock added successfully.');
    }

    public function show(Flock $flock)
    {
        $flock->load(['productions' => fn($q) => $q->latest('date')->take(30)]);
        return view('flocks.show', compact('flock'));
    }

    public function edit(Flock $flock)
    {
        return view('flocks.edit', compact('flock'));
    }

    public function update(Request $request, Flock $flock)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'breed'            => 'nullable|string|max:255',
            'quantity'         => 'required|integer|min:1',
            'acquisition_date' => 'required|date',
            'status'           => 'required|in:active,sold,retired',
            'notes'            => 'nullable|string',
        ]);

        $flock->update($validated);

        return redirect()->route('flocks.index')->with('success', 'Flock updated successfully.');
    }

    public function destroy(Flock $flock)
    {
        $flock->delete();
        return redirect()->route('flocks.index')->with('success', 'Flock deleted.');
    }
}
