<?php

namespace App\Http\Controllers;

use App\Models\EggProduction;
use App\Models\EggSale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate   = $request->get('end_date', Carbon::today()->format('Y-m-d'));
        $period    = $request->get('period', 'Daily');

        $productions = EggProduction::whereBetween('date', [$startDate, $endDate])->get();
        $sales       = EggSale::whereBetween('date', [$startDate, $endDate])->get();

        $totalProduced  = $productions->sum('eggs_collected');
        $totalSold      = $sales->sum('quantity');
        $avgSalesRate   = $totalProduced > 0 ? round(($totalSold / $totalProduced) * 100, 1) : 0;

        $summary = [
            'total_eggs_produced' => $totalProduced,
            'total_eggs_sold'     => $totalSold,
            'total_revenue'       => $sales->sum('total_amount'),
            'avg_production_rate' => $productions->count()
                ? round($productions->avg(fn($p) => $p->production_rate), 1)
                : 0,
            'avg_sales_rate'      => $avgSalesRate,
            'remaining_eggs'      => $totalProduced - $totalSold,
        ];

        // Daily breakdown for charts
        $dailyData = EggProduction::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get()
            ->map(function ($prod) {
                $sold    = EggSale::whereDate('date', $prod->date)->sum('quantity');
                $revenue = EggSale::whereDate('date', $prod->date)->sum('total_amount');
                return [
                    'date'        => $prod->date->format('M d'),
                    'eggs'        => $prod->eggs_collected,
                    'sold'        => $sold,
                    'revenue'     => $revenue,
                    'prod_rate'   => $prod->production_rate,
                ];
            });

        return view('reports.index', compact('summary', 'dailyData', 'startDate', 'endDate', 'period'));
    }
}
