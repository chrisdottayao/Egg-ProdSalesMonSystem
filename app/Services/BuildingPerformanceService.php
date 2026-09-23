<?php

namespace App\Services;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\FlockAlert;
use App\Models\HenBatch;
use App\Models\WeatherDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Descriptive per-building and farm-wide performance data — extracted from
 * Prototype 3H's standalone BuildingDashboardController so the same queries
 * back both the merged main-dashboard summary (farmOverview) and the
 * per-building AJAX detail panel (buildingDetail) added in 3J. No business
 * logic changed here from 3H — only relocated so it has two callers instead
 * of one.
 */
class BuildingPerformanceService
{
    public function resolveWindow(Request $request): array
    {
        $startParam = $request->input('start_date');
        $endParam   = $request->input('end_date');

        if ($startParam && $endParam) {
            return [Carbon::parse($startParam)->startOfDay(), Carbon::parse($endParam)->endOfDay()];
        }

        $window = $request->input('window', '1');

        if ($window === 'this_month') {
            return [Carbon::now()->startOfMonth(), Carbon::today()->endOfDay()];
        }

        $months = in_array($window, ['1', '2', '3'], true) ? (int) $window : 1;

        return [Carbon::today()->subMonths($months)->startOfDay(), Carbon::today()->endOfDay()];
    }

    /**
     * Farm-wide summary + building leaderboard for the main dashboard's
     * per-building section.
     */
    public function farmOverview(Collection $buildings, Carbon $start, Carbon $end): array
    {
        $dailyTotals = BuildingDaily::whereBetween('date', [$start, $end])
            ->selectRaw('date, SUM(eggs_house) as eggs, SUM(population) as pop')
            ->groupBy('date')
            ->get();

        $dailyRates = $dailyTotals->filter(fn ($r) => $r->pop > 0)
            ->map(fn ($r) => ($r->eggs / $r->pop) * 100);

        $farmProdRate = $dailyRates->isNotEmpty() ? round((float) $dailyRates->avg(), 1) : null;
        $farmBand     = ProdRateBand::resolve($farmProdRate);

        $openAlertCounts = FlockAlert::whereIn('hen_batch_id', $buildings->pluck('id'))
            ->where('status', 'open')
            ->selectRaw('hen_batch_id, count(*) as c')
            ->groupBy('hen_batch_id')
            ->pluck('c', 'hen_batch_id');

        // Leaderboard — each building's latest prod rate (within the window if
        // it reported any, otherwise its most recent reading overall so a
        // building doesn't just vanish from the list). Kept in the numeric
        // building order $buildings was already fetched in — not re-ranked
        // by performance, so the list reads top-to-bottom as Building 1..45.
        $leaderboard = $buildings->map(function (HenBatch $b) use ($start, $end, $openAlertCounts) {
            $row = BuildingDaily::where('hen_batch_id', $b->id)
                ->whereBetween('date', [$start, $end])
                ->orderByDesc('date')
                ->first()
                ?? BuildingDaily::where('hen_batch_id', $b->id)->orderByDesc('date')->first();

            $rate = $row->prod_rate ?? null;

            return [
                'building'          => $b,
                'prod_rate'         => $rate !== null ? (float) $rate : null,
                // Culled badge (3Q) replaces the usual prod-rate band once a
                // flock has ended — a band no longer means anything for it.
                'band'              => $b->isEnded() ? ProdRateBand::ended($b->ended_at) : ProdRateBand::resolve($rate !== null ? (float) $rate : null),
                'as_of'             => $row->date ?? null,
                'population'        => $row->population ?? null,
                'open_alerts_count' => (int) ($openAlertCounts[$b->id] ?? 0),
            ];
        })->values();

        $farmRevenue  = (float) EggSale::whereBetween('date', [$start, $end])->sum('total_amount');
        $farmExpenses = (float) Expense::whereBetween('date', [$start, $end])->sum('amount');
        $farmNet      = round($farmRevenue - $farmExpenses, 2);

        return [
            'farmProdRate' => $farmProdRate,
            'farmBand'     => $farmBand,
            'leaderboard'  => $leaderboard,
            'farmRevenue'  => $farmRevenue,
            'farmExpenses' => $farmExpenses,
            'farmNet'      => $farmNet,
        ];
    }

    /**
     * Everything the per-building detail panel needs: current status, trend
     * (+ THI overlay data), scoped alerts, estimated revenue/expenses/net,
     * the forecast stub, and pre-built descriptive insight lines.
     */
    public function buildingDetail(HenBatch $henBatch, Carbon $start, Carbon $end, string $window): array
    {
        $latestRow = BuildingDaily::where('hen_batch_id', $henBatch->id)->orderByDesc('date')->first();

        $currentStatus = [
            'population' => $latestRow->population ?? null,
            'age_weeks'  => $this->currentAge($latestRow),
            'prod_rate'  => $latestRow->prod_rate ?? null,
            'as_of'      => $latestRow?->date,
        ];
        // Culled badge (3Q) replaces the usual prod-rate band once ended.
        $currentBand = $henBatch->isEnded()
            ? ProdRateBand::ended($henBatch->ended_at)
            : ProdRateBand::resolve($currentStatus['prod_rate'] !== null ? (float) $currentStatus['prod_rate'] : null);

        $lastCullDate  = CullRecord::where('hen_batch_id', $henBatch->id)->max('date');
        $daysSinceCull = $lastCullDate ? Carbon::parse($lastCullDate)->diffInDays(Carbon::today()) : null;

        $lastRestockDate  = Expense::forBuilding($henBatch->id)->category('restocking')->max('date');
        $daysSinceRestock = $lastRestockDate ? Carbon::parse($lastRestockDate)->diffInDays(Carbon::today()) : null;

        $openAlertsCount = FlockAlert::where('hen_batch_id', $henBatch->id)->where('status', 'open')->count();

        // ── Trend (prod rate + optional THI overlay) ────────────────────────
        $weatherByDate = WeatherDaily::whereBetween('date', [$start, $end])
            ->get(['date', 'thi'])
            ->keyBy(fn ($w) => $w->date->format('Y-m-d'));

        $trendData = BuildingDaily::where('hen_batch_id', $henBatch->id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get(['date', 'prod_rate'])
            ->map(function ($row) use ($weatherByDate) {
                $dateKey = $row->date->format('Y-m-d');

                return [
                    'date'      => $row->date->format('M d'),
                    'prod_rate' => (float) $row->prod_rate,
                    'thi'       => $weatherByDate[$dateKey]->thi ?? null,
                ];
            });

        // ── Alerts (this building, for the window, plus anything still open) ─
        $alerts = FlockAlert::where('hen_batch_id', $henBatch->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('triggered_since', [$start, $end])->orWhere('status', 'open');
            })
            ->orderByDesc('triggered_since')
            ->get();

        // ── Estimated Revenue Contribution — by share of eggs produced ──────
        $buildingEggs = (int) BuildingDaily::where('hen_batch_id', $henBatch->id)
            ->whereBetween('date', [$start, $end])->sum('eggs_house');
        $farmEggs          = (int) BuildingDaily::whereBetween('date', [$start, $end])->sum('eggs_house');
        $farmRevenueWindow = (float) EggSale::whereBetween('date', [$start, $end])->sum('total_amount');

        $eggShare         = $farmEggs > 0 ? $buildingEggs / $farmEggs : 0.0;
        $estimatedRevenue = round($farmRevenueWindow * $eggShare, 2);

        // ── Expenses (direct + population-allocated) ────────────────────────
        $expenses = (new ExpenseAllocator)->forBuilding($henBatch->id, $start->format('Y-m-d'), $end->format('Y-m-d'));

        // ── Net contribution ─────────────────────────────────────────────────
        $netContribution = round($estimatedRevenue - $expenses['total'], 2);

        // ── Forecast placeholder (stub — Animal Science model pending) ──────
        $forecast = (new BuildingForecastService)->forecast($henBatch->id);

        $windowLabel = $window === 'this_month' ? 'this month' : $window . '-month window';

        if ($henBatch->isEnded()) {
            // Ended (3Q) — no "declining production" narrative for a building
            // that's actually just empty now; state the fact instead.
            $insight = [
                'Flock ended: ' . $henBatch->ended_at->format('M d, Y') . ' — building fully depopulated.',
                'Historical figures below reflect the flock through its end date.',
                "Estimated revenue ({$windowLabel}): ₱" . number_format($estimatedRevenue, 2)
                    . ' · Estimated expenses: ₱' . number_format($expenses['total'], 2)
                    . ' · Net: ₱' . number_format($netContribution, 2),
                'Status: ' . $currentBand['label'],
            ];
        } else {
            $insight = [
                'Prod rate today: ' . ($currentStatus['prod_rate'] !== null ? number_format($currentStatus['prod_rate'], 1) . '%' : '—')
                    . ($currentBand ? ' (' . $currentBand['label'] . ')' : ''),
                'Flock age: ' . ($currentStatus['age_weeks'] !== null ? $currentStatus['age_weeks'] . ' weeks' : '—'),
                "Estimated revenue ({$windowLabel}): ₱" . number_format($estimatedRevenue, 2)
                    . ' · Estimated expenses: ₱' . number_format($expenses['total'], 2)
                    . ' · Net: ₱' . number_format($netContribution, 2),
                'Status: ' . ($currentBand['label'] ?? 'Unknown'),
            ];
        }
        $insightPlaceholder = [
            'Projected next 1–2 months: —',
            'Projected earnings: —',
        ];

        return [
            'henBatch'           => $henBatch,
            'window'             => $window,
            'currentStatus'      => $currentStatus,
            'currentBand'        => $currentBand,
            'daysSinceCull'      => $daysSinceCull,
            'daysSinceRestock'   => $daysSinceRestock,
            'openAlertsCount'    => $openAlertsCount,
            'trendData'          => $trendData,
            'alerts'             => $alerts,
            'eggShare'           => $eggShare,
            'estimatedRevenue'   => $estimatedRevenue,
            'expenses'           => $expenses,
            'netContribution'    => $netContribution,
            'forecast'           => $forecast,
            'insight'            => $insight,
            'insightPlaceholder' => $insightPlaceholder,
        ];
    }

    private function currentAge(?BuildingDaily $row): ?int
    {
        if (! $row || $row->age_weeks === null) {
            return null;
        }

        $daysSince = $row->date->diffInDays(Carbon::today());

        return (int) round($row->age_weeks + $daysSince / 7);
    }
}
