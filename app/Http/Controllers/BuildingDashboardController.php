<?php

namespace App\Http\Controllers;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\FlockAlert;
use App\Models\HenBatch;
use App\Models\WeatherDaily;
use App\Services\BuildingForecastService;
use App\Services\ExpenseAllocator;
use App\Services\ProdRateBand;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BuildingDashboardController extends Controller
{
    public function index(Request $request)
    {
        [$start, $end] = $this->resolveWindow($request);
        $window        = $request->input('window', '1');

        $buildings     = HenBatch::where('status', 'Active')->orderBy('batch_id')->get();
        $buildingParam = $request->input('building');

        if (is_numeric($buildingParam)) {
            $henBatch = $buildings->firstWhere('id', (int) $buildingParam) ?? HenBatch::findOrFail((int) $buildingParam);

            return $this->buildingView($henBatch, $buildings, $start, $end, $window);
        }

        return $this->farmView($buildings, $start, $end, $window);
    }

    // ── Per-building descriptive view ───────────────────────────────────────

    private function buildingView(HenBatch $henBatch, $buildings, Carbon $start, Carbon $end, string $window)
    {
        $latestRow = BuildingDaily::where('hen_batch_id', $henBatch->id)->orderByDesc('date')->first();

        $currentStatus = [
            'population' => $latestRow->population ?? null,
            'age_weeks'  => $this->currentAge($latestRow),
            'prod_rate'  => $latestRow->prod_rate ?? null,
            'as_of'      => $latestRow?->date,
        ];
        $currentBand = ProdRateBand::resolve($currentStatus['prod_rate'] !== null ? (float) $currentStatus['prod_rate'] : null);

        $lastCullDate    = CullRecord::where('hen_batch_id', $henBatch->id)->max('date');
        $daysSinceCull   = $lastCullDate ? Carbon::parse($lastCullDate)->diffInDays(Carbon::today()) : null;

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
        $farmEggs = (int) BuildingDaily::whereBetween('date', [$start, $end])->sum('eggs_house');
        $farmRevenueWindow = (float) EggSale::whereBetween('date', [$start, $end])->sum('total_amount');

        $eggShare         = $farmEggs > 0 ? $buildingEggs / $farmEggs : 0.0;
        $estimatedRevenue = round($farmRevenueWindow * $eggShare, 2);

        // ── Expenses (direct + population-allocated) ────────────────────────
        $expenses = (new ExpenseAllocator)->forBuilding($henBatch->id, $start->format('Y-m-d'), $end->format('Y-m-d'));

        // ── Net contribution ─────────────────────────────────────────────────
        $netContribution = round($estimatedRevenue - $expenses['total'], 2);

        // ── Forecast placeholder (stub — Animal Science model pending) ──────
        $forecast = (new BuildingForecastService)->forecast($henBatch->id);

        return view('building-dashboard.index', [
            'mode'             => 'building',
            'buildings'        => $buildings,
            'henBatch'         => $henBatch,
            'window'           => $window,
            'start'            => $start,
            'end'              => $end,
            'currentStatus'    => $currentStatus,
            'currentBand'      => $currentBand,
            'daysSinceCull'    => $daysSinceCull,
            'daysSinceRestock' => $daysSinceRestock,
            'openAlertsCount'  => $openAlertsCount,
            'trendData'        => $trendData,
            'alerts'           => $alerts,
            'eggShare'         => $eggShare,
            'estimatedRevenue' => $estimatedRevenue,
            'expenses'         => $expenses,
            'netContribution'  => $netContribution,
            'forecast'         => $forecast,
        ]);
    }

    // ── Farm-wide overview (default screen) ─────────────────────────────────

    private function farmView($buildings, Carbon $start, Carbon $end, string $window)
    {
        $dailyTotals = BuildingDaily::whereBetween('date', [$start, $end])
            ->selectRaw('date, SUM(eggs_house) as eggs, SUM(population) as pop')
            ->groupBy('date')
            ->get();

        $dailyRates = $dailyTotals->filter(fn ($r) => $r->pop > 0)
            ->map(fn ($r) => ($r->eggs / $r->pop) * 100);

        $farmProdRate = $dailyRates->isNotEmpty() ? round((float) $dailyRates->avg(), 1) : null;
        $farmBand     = ProdRateBand::resolve($farmProdRate);

        // Leaderboard — each building's latest prod rate (within the window if
        // it reported any, otherwise its most recent reading overall so a
        // building doesn't just vanish from the list).
        $leaderboard = $buildings->map(function (HenBatch $b) use ($start, $end) {
            $row = BuildingDaily::where('hen_batch_id', $b->id)
                ->whereBetween('date', [$start, $end])
                ->orderByDesc('date')
                ->first()
                ?? BuildingDaily::where('hen_batch_id', $b->id)->orderByDesc('date')->first();

            $rate = $row->prod_rate ?? null;

            return [
                'building'   => $b,
                'prod_rate'  => $rate !== null ? (float) $rate : null,
                'band'       => ProdRateBand::resolve($rate !== null ? (float) $rate : null),
                'as_of'      => $row->date ?? null,
                'population' => $row->population ?? null,
            ];
        })->sortByDesc('prod_rate')->values();

        $farmRevenue  = (float) EggSale::whereBetween('date', [$start, $end])->sum('total_amount');
        $farmExpenses = (float) Expense::whereBetween('date', [$start, $end])->sum('amount');
        $farmNet      = round($farmRevenue - $farmExpenses, 2);

        return view('building-dashboard.index', [
            'mode'         => 'farm',
            'buildings'    => $buildings,
            'henBatch'     => null,
            'window'       => $window,
            'start'        => $start,
            'end'          => $end,
            'farmProdRate' => $farmProdRate,
            'farmBand'     => $farmBand,
            'leaderboard'  => $leaderboard,
            'farmRevenue'  => $farmRevenue,
            'farmExpenses' => $farmExpenses,
            'farmNet'      => $farmNet,
        ]);
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    private function resolveWindow(Request $request): array
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

    private function currentAge(?BuildingDaily $row): ?int
    {
        if (! $row || $row->age_weeks === null) {
            return null;
        }

        $daysSince = $row->date->diffInDays(Carbon::today());

        return (int) round($row->age_weeks + $daysSince / 7);
    }
}
