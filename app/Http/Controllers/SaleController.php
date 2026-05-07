<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with('customer')->latest('sale_date')->paginate(20);
        return view('sales.index', compact('sales'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        return view('sales.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'sale_date'            => 'required|date',
            'amount_paid'          => 'required|numeric|min:0',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.egg_type'     => 'required|string|max:100',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit'         => 'required|in:tray,piece',
            'items.*.unit_price'   => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $totalAmount = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $totalAmount += $subtotal;
                $itemsData[] = array_merge($item, ['subtotal' => $subtotal]);
            }

            $amountPaid = $validated['amount_paid'];
            $paymentStatus = match(true) {
                $amountPaid >= $totalAmount => 'paid',
                $amountPaid > 0            => 'partial',
                default                    => 'unpaid',
            };

            $sale = Sale::create([
                'customer_id'    => $validated['customer_id'] ?? null,
                'sale_date'      => $validated['sale_date'],
                'total_amount'   => $totalAmount,
                'amount_paid'    => $amountPaid,
                'payment_status' => $paymentStatus,
                'notes'          => $validated['notes'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $sale->items()->create($item);
            }
        });

        return redirect()->route('sales.index')->with('success', 'Sale recorded successfully.');
    }

    public function show(Sale $sale)
    {
        $sale->load('customer', 'items');
        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        $sale->load('items');
        $customers = Customer::orderBy('name')->get();
        return view('sales.edit', compact('sale', 'customers'));
    }

    public function update(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'sale_date'            => 'required|date',
            'amount_paid'          => 'required|numeric|min:0',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.egg_type'     => 'required|string|max:100',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit'         => 'required|in:tray,piece',
            'items.*.unit_price'   => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $sale) {
            $totalAmount = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $totalAmount += $subtotal;
                $itemsData[] = array_merge($item, ['subtotal' => $subtotal]);
            }

            $amountPaid = $validated['amount_paid'];
            $paymentStatus = match(true) {
                $amountPaid >= $totalAmount => 'paid',
                $amountPaid > 0            => 'partial',
                default                    => 'unpaid',
            };

            $sale->update([
                'customer_id'    => $validated['customer_id'] ?? null,
                'sale_date'      => $validated['sale_date'],
                'total_amount'   => $totalAmount,
                'amount_paid'    => $amountPaid,
                'payment_status' => $paymentStatus,
                'notes'          => $validated['notes'] ?? null,
            ]);

            $sale->items()->delete();
            foreach ($itemsData as $item) {
                $sale->items()->create($item);
            }
        });

        return redirect()->route('sales.index')->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();
        return redirect()->route('sales.index')->with('success', 'Sale deleted.');
    }
}
