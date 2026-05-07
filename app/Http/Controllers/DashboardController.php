<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        $eggsToday = EggProduction::whereDate('date', $today)->sum('eggs_collected');
        $activeHens = HenBatch::activeHenCount();
        $revenueToday = EggSale::whereDate('date', $today)->sum('total_amount');
        $eggsThisMonth = EggProduction::where('date', '>=', $thisMonth)->sum('eggs_collected');
        $salesThisMonth = EggSale::where('date', '>=', $thisMonth)->sum('total_amount');

        $productionRate = $activeHens > 0
            ? round(($eggsToday / $activeHens) * 100, 1)
            : 0;

        $stats = [
            'eggs_today'        => $eggsToday,
            'revenue_today'     => $revenueToday,
            'production_rate'   => $productionRate,
            'active_hens'       => $activeHens,
            'eggs_this_month'   => $eggsThisMonth,
            'sales_this_month'  => $salesThisMonth,
        ];

        $recentActivity = EggProduction::with([])
            ->latest('date')
            ->take(5)
            ->get()
            ->map(function ($prod) {
                $sold = EggSale::whereDate('date', $prod->date)->sum('quantity');
                $revenue = EggSale::whereDate('date', $prod->date)->sum('total_amount');
                return [
                    'date'      => $prod->date->format('M d, Y'),
                    'eggsProd'  => $prod->eggs_collected,
                    'sold'      => $sold,
                    'revenue'   => '₱' . number_format($revenue, 2),
                    'remaining' => max(0, $prod->eggs_collected - $sold),
                    'notes'     => $prod->notes ?? '—',
                ];
            });

        return view('dashboard', compact('stats', 'recentActivity'));
    }
}
