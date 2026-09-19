<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    // ENGINEERING — trailing window shown by default on the summary page.
    private const SUMMARY_MONTHS = 6;

    // ENGINEERING — weeks/month used to project a weekly recurring cost
    // (e.g. Aminovit every Monday) into an "estimated monthly" figure.
    private const WEEKS_PER_MONTH = 4.33;

    public function index(Request $request)
    {
        $buildingFilter = $request->input('building_id'); // '', 'farm', or a hen_batches id
        $category       = $request->input('category');
        $startDate      = $request->input('start_date');
        $endDate        = $request->input('end_date');

        $query = Expense::query()->with('building')->latest('date');

        if ($buildingFilter === 'farm') {
            $query->farmWide();
        } elseif (is_numeric($buildingFilter)) {
            $query->forBuilding((int) $buildingFilter);
        }

        if ($category) {
            $query->category($category);
        }
        if ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        $expenses  = $query->paginate(20)->withQueryString();
        $buildings = HenBatch::where('status', 'Active')->orderBy('batch_id')->get();

        $feedDefaults = [
            'kg_per_bag'   => (float) Setting::get('default_kg_per_bag', config('expenses.default_kg_per_bag')),
            'brand'        => Setting::get('default_feed_brand', config('expenses.default_feed_brand')),
            'price_per_bag'=> (float) Setting::get('feed_price_per_bag_estimate', config('expenses.feed_price_per_bag_estimate')),
        ];

        return view('expenses.index', compact(
            'expenses', 'buildings', 'buildingFilter', 'category', 'startDate', 'endDate', 'feedDefaults'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validateExpense($request);
        $data      = $this->prepareData($validated, $request);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('expenses', 'public');
        }

        Expense::create($data);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense)
    {
        $buildings = HenBatch::where('status', 'Active')->orderBy('batch_id')->get();

        $feedDefaults = [
            'kg_per_bag'   => (float) Setting::get('default_kg_per_bag', config('expenses.default_kg_per_bag')),
            'brand'        => Setting::get('default_feed_brand', config('expenses.default_feed_brand')),
            'price_per_bag'=> (float) Setting::get('feed_price_per_bag_estimate', config('expenses.feed_price_per_bag_estimate')),
        ];

        return view('expenses.edit', compact('expense', 'buildings', 'feedDefaults'));
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $this->validateExpense($request);
        $data      = $this->prepareData($validated, $request);

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('expenses', 'public');
        }

        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    // ── Farm-wide summary (read-only) ───────────────────────────────────────

    public function summary()
    {
        $start = Carbon::today()->subMonths(self::SUMMARY_MONTHS)->startOfMonth();

        $expenses = Expense::where('date', '>=', $start)->get();

        $byCategory = collect(Expense::CATEGORIES)->keys()
            ->mapWithKeys(fn ($cat) => [$cat => (float) $expenses->where('category', $cat)->sum('amount')]);

        $byMonth = $expenses->groupBy(fn ($e) => $e->date->format('Y-m'))
            ->map(fn ($rows) => [
                'total'     => (float) $rows->sum('amount'),
                'estimated' => (float) $rows->where('is_estimated', true)->sum('amount'),
            ])
            ->sortKeys();

        $totalAmount     = (float) $expenses->sum('amount');
        $estimatedAmount = (float) $expenses->where('is_estimated', true)->sum('amount');

        // Recurring regimens (e.g. weekly Aminovit/Electrocare) are ongoing
        // costs, not one-off purchases — surfaced as their own projected
        // monthly line rather than buried in whichever month they were reordered.
        $recurring = Expense::where('is_recurring', true)
            ->get()
            ->groupBy('description')
            ->map(function ($rows) {
                $latest = $rows->sortByDesc('date')->first();

                return [
                    'description'   => $latest->description,
                    'recurrence'    => $latest->recurrence,
                    'weekly_amount' => (float) $latest->amount,
                    'est_monthly'   => round((float) $latest->amount * self::WEEKS_PER_MONTH, 2),
                ];
            })
            ->values();

        return view('expenses.summary', compact(
            'byCategory', 'byMonth', 'totalAmount', 'estimatedAmount', 'recurring', 'start'
        ));
    }

    // ── Shared validation/shaping for store() and update() ──────────────────

    private function validateExpense(Request $request): array
    {
        $rules = [
            'date'         => 'required|date',
            'building_id'  => 'nullable|exists:hen_batches,id',
            'category'     => 'required|string|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description'  => 'required|string|max:255',
            'supplier'     => 'nullable|string|max:255',
            'is_estimated' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'recurrence'   => 'nullable|string|max:20',
            'notes'        => 'nullable|string',
            'receipt'      => 'nullable|image|max:5120',
        ];

        if ($request->input('category') === 'feed') {
            // kg_total must never be null on a feed row (IT expert point 1) —
            // requiring bags + kg/bag here is what guarantees that.
            $rules['bags']          = 'required|numeric|min:0.01';
            $rules['kg_per_bag']    = 'required|numeric|min:0.01';
            $rules['price_per_bag'] = 'required|numeric|min:0';
        } else {
            $rules['quantity']   = 'nullable|numeric|min:0';
            $rules['unit']       = 'nullable|string|max:15';
            $rules['unit_price'] = 'nullable|numeric|min:0';
            $rules['amount']     = 'required|numeric|min:0';
        }

        return $request->validate($rules);
    }

    private function prepareData(array $validated, Request $request): array
    {
        $data = [
            'building_id'  => $validated['building_id'] ?? null,
            'date'         => $validated['date'],
            'category'     => $validated['category'],
            'description'  => $validated['description'],
            'supplier'     => $validated['supplier'] ?? null,
            'is_estimated' => $request->boolean('is_estimated'),
            'is_recurring' => $request->boolean('is_recurring'),
            'recurrence'   => $request->boolean('is_recurring') ? ($validated['recurrence'] ?? null) : null,
            'notes'        => $validated['notes'] ?? null,
        ];

        if ($validated['category'] === 'feed') {
            $bags        = (float) $validated['bags'];
            $kgPerBag    = (float) $validated['kg_per_bag'];
            $pricePerBag = (float) $validated['price_per_bag'];

            $data['quantity']   = $bags;
            $data['unit']       = 'bag';
            $data['unit_price'] = $pricePerBag;
            $data['amount']     = round($bags * $pricePerBag, 2);
            $data['kg_total']   = round($bags * $kgPerBag, 2);
        } else {
            $data['quantity']   = $validated['quantity'] ?? null;
            $data['unit']       = $validated['unit'] ?? null;
            $data['unit_price'] = $validated['unit_price'] ?? null;
            $data['amount']     = $validated['amount'];
            $data['kg_total']   = null;
        }

        return $data;
    }
}
