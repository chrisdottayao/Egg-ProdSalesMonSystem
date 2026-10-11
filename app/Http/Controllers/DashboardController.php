<?php

namespace App\Http\Controllers;

use App\Models\AnomalyAlert;
use App\Models\AuditLog;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\ForecastEvaluation;
use App\Models\HenBatch;
use App\Models\WeatherDaily;
use App\Services\AiInsightService;
use App\Services\BuildingPerformanceService;
use App\Services\ForecastService;
use App\Services\RecommendationService;
use App\Support\ManilaDate;
use App\Support\ProductionAnomalyDetector;
use App\Support\ProductionCostCaption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private const RETRAIN_AFTER_DAYS = 7;


    public function index(Request $request)
    {
        $today      = Carbon::today();
        $thisMonth  = Carbon::now()->startOfMonth();

        // ── Core stats ──────────────────────────────────────────────────────
        $eggsToday      = EggProduction::whereDate('date', $today)->sum('eggs_collected');
        $activeHens     = HenBatch::activeHenCount();
        $revenueToday   = EggSale::whereDate('date', $today)->sum('total_amount');
        $eggsThisMonth  = EggProduction::where('date', '>=', $thisMonth)->sum('eggs_collected');
        $salesThisMonth = EggSale::where('date', '>=', $thisMonth)->sum('total_amount');

        // production_rate was removed (3N) — it duplicated the Per-Building
        // Performance section's "Farm-wide Prod Rate" below, computed a
        // different way; that section's figure is the one that stays.
        $stats = [
            'eggs_today'       => $eggsToday,
            'revenue_today'    => $revenueToday,
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

        // S21: what the Production Trend shows, plus feed cost per egg only when
        // feed expenses exist for the same 30 days (Phase 3 item 8).
        $productionCaption = ProductionCostCaption::for(
            Carbon::today()->subDays(29)->toDateString(), Carbon::today()->toDateString()
        );

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
        // Automatic retraining: no scheduler needed. If the newest saved
        // training run is older than RETRAIN_AFTER_DAYS (or none exists yet)
        // and there is enough data for a live forecast, refit and record it.
        // The weekly `forecast:retrain` job still runs when a scheduler is up.
        $forecastService = new ForecastService;
        $forecast        = $forecastService->forecast();
        $lastTrained     = ForecastEvaluation::latest('evaluated_at')->value('evaluated_at');

        if ($forecast['active'] && (! $lastTrained || Carbon::parse($lastTrained)->lt(now()->subDays(self::RETRAIN_AFTER_DAYS)))) {
            try {
                $forecast    = $forecastService->forecast(persist: true);
                $lastTrained = ForecastEvaluation::latest('evaluated_at')->value('evaluated_at');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-retrain failed: '.$e->getMessage());
            }
        }
        $lastTrained = $lastTrained ? Carbon::parse($lastTrained) : null;

        $pendingApprovals = Auth::user()?->role === 'admin'
            ? \App\Models\User::whereNull('approved_at')->count()
            : 0;

        // ── Weather context (read-only — dashboard never calls Open-Meteo live) ──
        // "Latest" means most recent OBSERVED/current reading — capped at
        // today so a future forecast row (weather:sync also writes ~16 days
        // ahead) never gets mislabeled as "latest."
        $latestWeather = WeatherDaily::whereNotNull('thi')->where('date', '<=', $today)->orderByDesc('date')->first();
        // 7 days back + 7 days forward (3R pt2 — was a 14-day-back/unbounded
        // window). 'source' lets the chart draw the forecast portion dashed,
        // same convention as the Production/Revenue trend charts.
        $weatherTrend  = WeatherDaily::where('date', '>=', Carbon::today()->subDays(6))
            ->where('date', '<=', Carbon::today()->addDays(7))
            ->orderBy('date')
            ->get(['date', 'thi', 'temp_mean', 'source'])
            ->map(fn ($w) => ['date' => $w->date->format('M d'), 'thi' => $w->thi, 'temp_mean' => $w->temp_mean, 'is_forecast' => $w->source === 'forecast']);

        // ── Per-building performance (3J — merged from the old standalone
        // investment dashboard). Financial figures here are gated to
        // admin/manager in the view, same access level 3H used, but computed
        // unconditionally so the controller doesn't need role branching. ──
        $performanceService = new BuildingPerformanceService;
        $window             = $request->input('window', '1');
        [$perfStart, $perfEnd] = $performanceService->resolveWindow($request);
        // Numeric building order by the DISPLAYED number (3N: display_no when
        // set, else building_no), not alphabetical batch_id; any building
        // without either number sorts to the end rather than the top.
        // ->tracked(): only the 3 actively-tracked buildings (3M) — the other
        // 42's historical data is untouched, just not surfaced here.
        $buildings          = HenBatch::where('status', 'Active')
            ->tracked()
            ->orderByRaw('COALESCE(display_no, building_no) IS NULL, COALESCE(display_no, building_no) ASC')
            ->get();
        $farmOverview       = $performanceService->farmOverview($buildings, $perfStart, $perfEnd);

        return view('dashboard', compact(
            'stats', 'recentActivity',
            'productionChartData', 'productionCaption', 'revenueChartData',
            'anomalyAlerts', 'recommendations',
            'forecast', 'lastTrained', 'pendingApprovals', 'latestWeather', 'weatherTrend',
            'window', 'perfStart', 'perfEnd', 'buildings', 'farmOverview'
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
        $old = $alert->status;
        $alert->update(['status' => 'reviewed']);
        $this->auditAlert($alert, $old);

        return back()->with('success', 'Alert marked as reviewed.');
    }

    public function markResolved(AnomalyAlert $alert)
    {
        $old = $alert->status;
        $alert->update([
            'status'      => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);
        $this->auditAlert($alert, $old);

        return back()->with('success', 'Alert resolved.');
    }

    /** Phase 3 item 2: anomaly alert status changes go to the Audit Log. */
    private function auditAlert(AnomalyAlert $alert, ?string $oldStatus): void
    {
        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'alert_' . $alert->status,
            'model_type' => 'AnomalyAlert',
            'model_id'   => $alert->id,
            'details'    => [
                'type'       => $alert->type,
                'alert_date' => Carbon::parse($alert->alert_date)->toDateString(),
                'status'     => ['old' => $oldStatus, 'new' => $alert->status],
            ],
        ]);
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

        // Scoped to tracked buildings (3M) via hen_batch_id, so "farm-wide"
        // here means the 3 actively-tracked buildings, not all 45 — CullRecord
        // has building attribution, unlike EggSale below.
        $trackedBatchIds = HenBatch::tracked()->pluck('id');

        // A whole-building cull (Daily Entry "Culled", S3) is a planned
        // depopulation, not unusual culling — kept out of both the baseline
        // and the High Culling check below.
        $routineCulls = fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', CullRecord::BUILDING_CULLED_REASON);

        $rollingCulling = CullRecord::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->whereIn('hen_batch_id', $trackedBatchIds)
            ->where($routineCulls)
            ->selectRaw('SUM(quantity_culled) / GREATEST(COUNT(DISTINCT DATE(date)), 1) as avg_cull')
            ->value('avg_cull') ?? 0;

        // Revenue anomaly (daily total > 30% below rolling average). EggSale
        // has no building attribution at all (eggs are pooled farm-wide before
        // sale — see the data-model notes elsewhere), so this genuinely can't
        // be scoped to tracked buildings; it stays a true farm-wide figure.
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
            ->whereIn('hen_batch_id', $trackedBatchIds)
            ->where($routineCulls)
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

        // Production Drop / Production Spike / Mortality Spike (Phase 3 item 4).
        $today = ManilaDate::todayString();
        foreach (ProductionAnomalyDetector::detect(ManilaDate::today()->subDays(7)->toDateString(), $today) as $alert) {
            $this->upsertAlert($alert);
        }
    }

    private function upsertAlert(array $data): void
    {
        // anomaly_alerts.deviation_pct is decimal(6,2): anything beyond
        // ±9999.99 (e.g. one large cull against a ~4/day average) would make
        // the insert, and with it the whole dashboard, fail.
        if (isset($data['deviation_pct'])) {
            $data['deviation_pct'] = max(-9999.99, min(9999.99, $data['deviation_pct']));
        }

        AnomalyAlert::updateOrCreate(
            ['type' => $data['type'], 'alert_date' => $data['alert_date']],
            // Only overwrite values, not status/resolved fields (preserve reviews)
            collect($data)->except(['type', 'alert_date'])->toArray()
        );
    }
}
