<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\EggSale;
use Illuminate\Http\Request;

class EggSaleController extends Controller
{
    public function index()
    {
        $sales = EggSale::latest('date')->paginate(20);

        // Build produced-by-date lookup for Sales Rate + Remaining columns
        $dates = $sales->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->unique()->values();
        $producedByDate = EggProduction::whereIn(\DB::raw('DATE(date)'), $dates)
            ->selectRaw('DATE(date) as date_key, SUM(eggs_collected) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        return view('sales.index', compact('sales', 'producedByDate'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'egg_size'       => 'required|string',
            'quantity'       => 'required|integer|min:1',
            'price_per_unit' => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        // Hard block: sold > produced on that date
        $produced = EggProduction::whereDate('date', $validated['date'])->sum('eggs_collected');
        if ($produced > 0 && $validated['quantity'] > $produced) {
            return back()->withInput()->with(
                'hard_block',
                "Entry blocked: Quantity sold ({$validated['quantity']}) exceeds eggs produced on {$validated['date']} ({$produced}). Correct the entry before saving."
            );
        }

        $validated['total_amount'] = $validated['quantity'] * $validated['price_per_unit'];

        EggSale::create($validated);

        return redirect()->route('sales.index')->with('success', 'Sales entry saved successfully!');
    }

    public function edit(EggSale $sale)
    {
        return view('sales.edit', compact('sale'));
    }

    public function update(Request $request, EggSale $sale)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'egg_size'       => 'required|string',
            'quantity'       => 'required|integer|min:1',
            'price_per_unit' => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $validated['total_amount'] = $validated['quantity'] * $validated['price_per_unit'];
        $sale->update($validated);

        return redirect()->route('sales.index')->with('success', 'Sales record updated.');
    }

    public function destroy(EggSale $sale)
    {
        $sale->delete();
        return redirect()->route('sales.index')->with('success', 'Sales record deleted.');
    }
}
