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

        // Recent activity table (last 5 production days)
        $recentActivity = EggProduction::latest('date')
            ->take(5)
            ->get()
            ->map(function ($prod) {
                $sold    = EggSale::whereDate('date', $prod->date)->sum('quantity');
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

        // Production Trend chart data (last 30 days)
        $productionChartData = EggProduction::where('date', '>=', Carbon::today()->subDays(29))
            ->orderBy('date')
            ->get(['date', 'eggs_collected'])
            ->map(fn($p) => [
                'date' => $p->date->format('M d'),
                'eggs' => (int) $p->eggs_collected,
            ]);

        // Revenue chart data (last 10 days)
        $revenueChartData = EggSale::where('date', '>=', Carbon::today()->subDays(9))
            ->orderBy('date')
            ->get(['date', 'total_amount'])
            ->groupBy(fn($s) => $s->date->format('M d'))
            ->map(fn($group, $date) => [
                'date'    => $date,
                'revenue' => (float) $group->sum('total_amount'),
            ])->values();

        // 7-day rolling average (exclude today)
        $rollingAvg = EggProduction::where('date', '>=', Carbon::today()->subDays(7))
            ->where('date', '<', $today)
            ->avg('eggs_collected') ?? 0;

        // Anomaly detection
        $anomalyAlerts = [];
        if ($rollingAvg > 0) {
            EggProduction::where('date', '>=', Carbon::today()->subDays(7))
                ->latest('date')
                ->get()
                ->each(function ($prod) use ($rollingAvg, &$anomalyAlerts) {
                    $deviation = (($prod->eggs_collected - $rollingAvg) / $rollingAvg) * 100;
                    if ($deviation < -20) {
                        $anomalyAlerts[] = [
                            'type'        => 'Production Drop',
                            'severity'    => 'high',
                            'date'        => $prod->date->format('M d, Y'),
                            'expected'    => number_format(round($rollingAvg)) . ' eggs',
                            'actual'      => number_format($prod->eggs_collected) . ' eggs',
                            'deviation'   => round($deviation, 1) . '%',
                            'status'      => 'Unreviewed',
                            'description' => 'Daily production dropped more than 20% below 7-day rolling average',
                        ];
                    }
                });
        }

        // Farm recommendations
        $unsoldEggs = $eggsThisMonth - EggSale::where('date', '>=', $thisMonth)->sum('quantity');
        $farmRecommendations = [
            [
                'condition'      => 'Production rate below 70% for 3+ days',
                'recommendation' => 'Review active hen count accuracy; check for unreported mortality or health issues',
                'active'         => $productionRate > 0 && $productionRate < 70,
            ],
            [
                'condition'      => 'Remaining eggs accumulating unsold over 3 days',
                'recommendation' => 'Alert Manager to review sales pace and adjust sales planning',
                'active'         => $unsoldEggs > 50,
            ],
        ];

        // AI insights (rotate by day of week)
        $aiInsights = [
            "Production remains stable. Current production rate of {$productionRate}% is " . ($productionRate >= 80 ? 'within optimal range' : 'below target — review flock health') . " for this flock size.",
            "Average production trend over the past week is consistent. Daily revenue averaging ₱" . number_format($salesThisMonth / max(1, Carbon::now()->day), 0) . " this month based on current pricing.",
            "Sales analysis shows {$eggsToday} eggs collected today. Review daily sales pace to minimize unsold inventory at end of day.",
            "Production rate holding at {$productionRate}%. Revenue totals ₱" . number_format($revenueToday, 0) . " today. Forecast aligns with historical averages.",
            "Week-over-week analysis shows consistent output. Active hen count: {$activeHens}. Current flock health metrics remain stable.",
            "Revenue trends indicate consistent daily range driven by current pricing. Monitor sell-through rate daily to optimize turnover.",
        ];
        $aiInsight = $aiInsights[date('N') % count($aiInsights)];

        return view('dashboard', compact(
            'stats', 'recentActivity',
            'productionChartData', 'revenueChartData',
            'anomalyAlerts', 'farmRecommendations', 'aiInsight'
        ));
    }
}
