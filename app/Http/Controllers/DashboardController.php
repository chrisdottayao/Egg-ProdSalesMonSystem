<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EggProduction;
use App\Models\Flock;
use App\Models\Sale;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        $stats = [
            'active_flocks'      => Flock::where('status', 'active')->count(),
            'total_birds'        => Flock::where('status', 'active')->sum('quantity'),
            'eggs_today'         => EggProduction::whereDate('date', $today)->sum('total_eggs'),
            'eggs_this_month'    => EggProduction::where('date', '>=', $thisMonth)->sum('total_eggs'),
            'sales_this_month'   => Sale::where('sale_date', '>=', $thisMonth)->sum('total_amount'),
            'unpaid_balance'     => Sale::whereIn('payment_status', ['unpaid', 'partial'])->selectRaw('SUM(total_amount - amount_paid) as balance')->value('balance') ?? 0,
            'total_customers'    => Customer::count(),
        ];

        $recentSales = Sale::with('customer')->latest('sale_date')->take(5)->get();
        $recentProductions = EggProduction::with('flock')->latest('date')->take(5)->get();

        return view('dashboard', compact('stats', 'recentSales', 'recentProductions'));
    }
}
