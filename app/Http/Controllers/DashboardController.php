<?php

namespace App\Http\Controllers;

use App\Models\AnomalyAlert;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use App\Services\AiInsightService;
use App\Services\ForecastService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{

    public function index()
    {
        $today      = Carbon::today();
        $thisMonth  = Carbon::now()->startOfMonth();

        // ── Core stats ──────────────────────────────────────────────────────
        $eggsToday      = EggProduction::whereDate('date', $today)->sum('eggs_collected');
        $activeHens     = HenBatch::activeHenCount();
        $revenueToday   = EggSale::whereDate('date', $today)->sum('total_amount');
        $eggsThisMonth  = EggProduction::where('date', '>=', $thisMonth)->sum('eggs_collected');
        $salesThisMonth = EggSale::where('date', '>=', $thisMonth)->sum('total_amount');

        $productionRate = $activeHens > 0
            ? round(($eggsToday / $activeHens) * 100, 1)
            : 0;

        $stats = [
            'eggs_today'       => $eggsToday,
            'revenue_today'    => $revenueToday,
            'production_rate'  => $productionRate,
            'active_hens'      => $activeHens,
            'eggs_this_month'  => $eggsThisMonth,
            'sales_this_month' => $salesThisMonth,
        ];

        // ── Recent activity (last 5 production days) ────────────────────────
        $recentActivity = EggProduction::latest('date')
            ->take(5)->get()
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

        // ── Chart data ──────────────────────────────────────────────────────
        $productionChartData = EggProduction::where('date', '>=', Carbon::today()->subDays(29))
            ->orderBy('date')->get(['date', 'eggs_collected'])
            ->map(fn($p) => ['date' => $p->date->format('M d'), 'eggs' => (int) $p->eggs_collected]);

        $revenueChartData = EggSale::where('date', '>=', Carbon::today()->subDays(9))
            ->orderBy('date')->get(['date', 'total_amount'])
            ->groupBy(fn($s) => $s->date->format('M d'))
            ->map(fn($group, $date) => ['date' => $date, 'revenue' => (float) $group->sum('total_amount')])
            ->values();

        // ── Detect & persist anomalies ──────────────────────────────────────
        $this->detectAnomalies();

        // Load stored alerts (last 14 days, unreviewed first)
        $anomalyAlerts = AnomalyAlert::where('alert_date', '>=', Carbon::today()->subDays(14))
            ->orderByRaw("FIELD(status,'unreviewed','reviewed','resolved')")
            ->orderBy('alert_date', 'desc')
            ->get();

        // ── Rule-based recommendations ───────────────────────────────────────
        $recommendations = (new RecommendationService)->getRecommendations();

        // ── Predictive forecast ──────────────────────────────────────────────
        $forecast = (new ForecastService)->forecast();

        return view('dashboard', compact(
            'stats', 'recentActivity',
            'productionChartData', 'revenueChartData',
            'anomalyAlerts', 'recommendations',
            'forecast'
        ));
    }

    public function aiInsight(Request $request): JsonResponse
    {
        $service = new AiInsightService;
        $insight = $service->getInsight(forceRegenerate: $request->boolean('refresh'));

        return response()->json([
            'insight'  => $insight,
            'provider' => $service->currentProvider(),
            'model'    => $service->currentModel(),
        ]);
    }

    public function markReviewed(AnomalyAlert $alert)
    {
        $alert->update(['status' => 'reviewed']);
        return back()->with('success', 'Alert marked as reviewed.');
    }

    public function markResolved(AnomalyAlert $alert)
    {
        $alert->update([
            'status'      => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);
        return back()->with('success', 'Alert resolved.');
    }

    // ── Detection engine ──────────────────────────────────────────────────

    private function detectAnomalies(): void
    {
        $window = Carbon::today()->subDays(7);

        // Rolling averages (exclude today to avoid seeding bias)
        $rollingRevenue = EggSale::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->selectRaw('SUM(total_amount) / COUNT(DISTINCT DATE(date)) as avg_rev')
            ->value('avg_rev') ?? 0;

        $rollingCulling = CullRecord::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->selectRaw('SUM(quantity_culled) / GREATEST(COUNT(DISTINCT DATE(date)), 1) as avg_cull')
            ->value('avg_cull') ?? 0;

        // Revenue anomaly (daily total > 30% below rolling average)
        EggSale::where('date', '>=', $window)
            ->selectRaw('DATE(date) as sale_date, SUM(total_amount) as daily_revenue')
            ->groupBy('sale_date')
            ->get()
            ->each(function ($row) use ($rollingRevenue) {
                if ($rollingRevenue > 0) {
                    $dev = (($row->daily_revenue - $rollingRevenue) / $rollingRevenue) * 100;
                    if ($dev < -30) {
                        $this->upsertAlert([
                            'type'           => 'Revenue Anomaly',
                            'severity'       => 'medium',
                            'alert_date'     => $row->sale_date,
                            'expected_value' => '₱' . number_format(round($rollingRevenue), 2),
                            'actual_value'   => '₱' . number_format($row->daily_revenue, 2),
                            'deviation_pct'  => round($dev, 2),
                            'description'    => 'Daily sales revenue fell more than 30% below the 7-day rolling average.',
                        ]);
                    }
                }
            });

        // High culling day (> 2× rolling average and > 3 absolute)
        CullRecord::where('date', '>=', $window)
            ->selectRaw('DATE(date) as cull_date, SUM(quantity_culled) as daily_cull')
            ->groupBy('cull_date')
            ->get()
            ->each(function ($row) use ($rollingCulling) {
                if ($rollingCulling > 0 && $row->daily_cull > max(3, $rollingCulling * 2)) {
                    $dev = (($row->daily_cull - $rollingCulling) / $rollingCulling) * 100;
                    $this->upsertAlert([
                        'type'           => 'High Culling',
                        'severity'       => 'medium',
                        'alert_date'     => $row->cull_date,
                        'expected_value' => round($rollingCulling, 1) . ' avg/day',
                        'actual_value'   => $row->daily_cull . ' culled',
                        'deviation_pct'  => round($dev, 2),
                        'description'    => 'Unusually high culling count relative to farm historical rate. Review flock age distribution.',
                    ]);
                }
            });
    }

    private function upsertAlert(array $data): void
    {
        AnomalyAlert::updateOrCreate(
            ['type' => $data['type'], 'alert_date' => $data['alert_date']],
            // Only overwrite values, not status/resolved fields (preserve reviews)
            collect($data)->except(['type', 'alert_date'])->toArray()
        );
    }
}
