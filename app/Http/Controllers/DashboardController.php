<?php

namespace App\Http\Controllers;

use App\Models\AnomalyAlert;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use App\Services\ForecastService;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

        // ── Farm recommendations ─────────────────────────────────────────────
        $unsoldEggs = $eggsThisMonth - EggSale::where('date', '>=', $thisMonth)->sum('quantity');

        $farmRecommendations = [
            [
                'condition'      => 'Production rate below 70%',
                'recommendation' => 'Review active hen count; check for unreported mortality or health issues.',
                'active'         => $productionRate > 0 && $productionRate < 70,
            ],
            [
                'condition'      => 'Unsold eggs accumulating',
                'recommendation' => 'Review sales pace and adjust pricing or distribution to reduce unsold stock.',
                'active'         => $unsoldEggs > 50,
            ],
        ];

        // ── Predictive forecast ──────────────────────────────────────────────
        $forecast = (new ForecastService)->forecast();

        return view('dashboard', compact(
            'stats', 'recentActivity',
            'productionChartData', 'revenueChartData',
            'anomalyAlerts', 'farmRecommendations',
            'forecast'
        ));
    }

    public function aiInsight(): JsonResponse
    {
        $cached = Cache::get('groq_ai_insight');
        if ($cached !== null) {
            return response()->json(['insight' => $cached]);
        }

        $apiKey = config('services.groq.api_key');
        if (! $apiKey) {
            return response()->json(['insight' => 'AI insights temporarily unavailable.']);
        }

        $today      = Carbon::today();
        $eggsToday  = EggProduction::whereDate('date', $today)->sum('eggs_collected');
        $activeHens = HenBatch::activeHenCount();
        $revToday   = EggSale::whereDate('date', $today)->sum('total_amount');
        $prodRate   = $activeHens > 0 ? round(($eggsToday / $activeHens) * 100, 1) : 0;

        $avg7day = round(
            EggProduction::where('date', '>=', Carbon::today()->subDays(7))
                ->where('date', '<', $today)
                ->avg('eggs_collected') ?? 0
        );

        $soldToday = EggSale::whereDate('date', $today)->sum('quantity');
        $remaining = max(0, $eggsToday - $soldToday);

        $forecast = (new ForecastService)->forecast();
        $mape     = $forecast['active'] ? $forecast['mape'] . '%' : 'N/A';

        $userContent = "Farm data: Eggs today: {$eggsToday}. "
            . "Production rate: {$prodRate}%. "
            . "Revenue today: ₱" . number_format($revToday, 2) . ". "
            . "7-day average production: {$avg7day}. "
            . "MAPE forecast accuracy: {$mape}. "
            . "Unsold eggs today: {$remaining}. "
            . "Active hens: {$activeHens}.";

        try {
            $client   = new Client(['timeout' => 10]);
            $response = $client->post('https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'      => 'llama3-8b-8192',
                    'messages'   => [
                        [
                            'role'    => 'system',
                            'content' => 'You are an agricultural data analyst for a small egg farm in the Philippines. Analyze the farm data provided and give a 2-3 sentence plain-language insight about current performance, any concerns, and one actionable recommendation. Be specific with numbers.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $userContent,
                        ],
                    ],
                    'max_tokens' => 150,
                ],
            ]);

            $body    = json_decode((string) $response->getBody(), true);
            $insight = $body['choices'][0]['message']['content'] ?? 'AI insights temporarily unavailable.';

            Cache::put('groq_ai_insight', $insight, now()->addHour());

            return response()->json(['insight' => $insight]);
        } catch (\Throwable) {
            return response()->json(['insight' => 'AI insights temporarily unavailable.']);
        }
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
        $rollingProduction = EggProduction::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->avg('eggs_collected') ?? 0;

        $rollingMortality = EggProduction::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->avg('mortality') ?? 0;

        $rollingRevenue = EggSale::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->selectRaw('SUM(total_amount) / COUNT(DISTINCT DATE(date)) as avg_rev')
            ->value('avg_rev') ?? 0;

        $rollingCulling = CullRecord::where('date', '>=', $window)
            ->where('date', '<', Carbon::today())
            ->selectRaw('SUM(quantity_culled) / GREATEST(COUNT(DISTINCT DATE(date)), 1) as avg_cull')
            ->value('avg_cull') ?? 0;

        // Check last 7 days of production entries
        EggProduction::where('date', '>=', $window)->get()
            ->each(function ($prod) use ($rollingProduction, $rollingMortality) {
                // Production drop > 20%
                if ($rollingProduction > 0) {
                    $dev = (($prod->eggs_collected - $rollingProduction) / $rollingProduction) * 100;
                    if ($dev < -20) {
                        $this->upsertAlert([
                            'type'           => 'Production Drop',
                            'severity'       => $dev < -35 ? 'high' : 'medium',
                            'alert_date'     => $prod->date->format('Y-m-d'),
                            'expected_value' => number_format(round($rollingProduction)) . ' eggs',
                            'actual_value'   => number_format($prod->eggs_collected) . ' eggs',
                            'deviation_pct'  => round($dev, 2),
                            'description'    => 'Daily egg production dropped more than 20% below the 7-day rolling average.',
                        ]);
                    }
                }

                // Mortality spike (> 2× rolling average and > 2 absolute)
                if ($rollingMortality > 0 && $prod->mortality > max(2, $rollingMortality * 2)) {
                    $dev = (($prod->mortality - $rollingMortality) / $rollingMortality) * 100;
                    $this->upsertAlert([
                        'type'           => 'Mortality Spike',
                        'severity'       => 'high',
                        'alert_date'     => $prod->date->format('Y-m-d'),
                        'expected_value' => round($rollingMortality, 1) . ' avg/day',
                        'actual_value'   => $prod->mortality . ' recorded',
                        'deviation_pct'  => round($dev, 2),
                        'description'    => 'Mortality count significantly exceeds the 7-day daily average. Inspect flock health.',
                    ]);
                }
            });

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
