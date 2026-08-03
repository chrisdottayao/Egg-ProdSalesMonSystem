<?php

namespace App\Services;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\FlockAlert;
use App\Models\HenBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecommendationService
{
    // ── Suppression ──────────────────────────────────────────────────────────
    private const RAMP_UP_AGE_WEEKS = 24; // TODO: calibrate — young flocks excluded, intake/lay still stabilizing
    private const CULL_TARGET_AGE_WEEKS = 140; // farm's stated policy target
    private const CULL_WINDOW_LEAD_WEEKS = 4; // window opens this many weeks before target
    private const AUTO_RESOLVE_STREAK_DAYS = 3;

    // ── Production ───────────────────────────────────────────────────────────
    private const PRODUCTION_DEVIATION_THRESHOLD_PTS = 8; // TODO: calibrate
    private const PRODUCTION_DEVIATION_CONSECUTIVE_DAYS = 3;
    private const PEER_DEVIATION_STD_DEVS = 2.0; // TODO: calibrate
    private const NEAR_EXPECTED_TOLERANCE_PTS = 2; // "within 2 points of age-expected"

    // ── Mortality ────────────────────────────────────────────────────────────
    private const MORTALITY_TRAILING_DAYS = 7;
    private const MORTALITY_BASELINE_DAYS = 30;
    private const MORTALITY_SPIKE_PCT = 50; // 7-day mean exceeds 30-day mean by more than this %

    // ── Revenue ──────────────────────────────────────────────────────────────
    private const REVENUE_TRAILING_DAYS = 7;
    private const REVENUE_DECLINE_PCT = -15;

    // ── Culling rate ─────────────────────────────────────────────────────────
    private const CULL_RATE_TRAILING_MONTHS = 6;

    // ── Feed ─────────────────────────────────────────────────────────────────
    // Sack weight — confirmed against the farm's own figures (Phase 2), not a guess.
    private const FEED_KG_PER_BAG = 50;
    private const FEED_TRAILING_DAYS = 7;
    private const FEED_MIN_NONNULL_DAYS = 4;
    private const FEED_INTAKE_DROP_PCT = 5; // TODO: calibrate
    private const FEED_CONVERSION_WINDOW_DAYS = 5;

    // ── Margin ───────────────────────────────────────────────────────────────
    private const MARGIN_WARNING_PCT = 20; // TODO: calibrate — feed cost within this % of revenue per egg

    // ── Count mismatch (Audit Assistant) ────────────────────────────────────
    private const COUNT_MISMATCH_PCT = 0.5;

    // ── Cull readiness ───────────────────────────────────────────────────────
    private const CULL_READINESS_AGE_WEEKS = 132;

    // ── Clustering (Phase 5, consolidated here rather than in anomaly_alerts —
    // see Phase 4/5 duplication note) ────────────────────────────────────────
    private const CLUSTER_LOCAL_MAX = 1;  // 1 building: local (disease, equipment, water line)
    private const CLUSTER_SPREAD_MAX = 4; // 2-4 buildings: possible spread / shared feed-water line
    // 5+ buildings: environmental / farm-wide (usually weather) — severity downgraded

    // Only conditions where "local vs spread vs weather" is a meaningful frame.
    // Peer Deviation already isolates non-peer-following outliers by construction,
    // so clustering it would contradict its own purpose. Cull Readiness, Margin
    // Warning, and Count Mismatch are age/cost/accounting issues, not
    // disease/weather-spread patterns, so "5+ buildings = weather" would mislead.
    private const CLUSTERABLE_CONDITIONS = [
        'Low Production Rate',
        'Rising Mortality (Building)',
        'Feed Intake Drop',
        'Feed Conversion Worsening',
    ];

    private LayCurveService $layCurve;

    /** @var array<int,string> alert id => cluster note, display-only, never persisted */
    private array $clusterNotes = [];

    public function __construct()
    {
        $this->layCurve = new LayCurveService;
    }

    public function getRecommendations(): array
    {
        $hasFarmWideData  = EggProduction::count() >= 30;
        $hasBuildingData  = BuildingDaily::count() > 0;

        if (! $hasFarmWideData && ! $hasBuildingData) {
            return [];
        }

        if ($hasFarmWideData) {
            $this->evaluateRisingMortalityFarmWide();
            $this->evaluateDecliningRevenue();
            $this->evaluateHighCullingRate();
            $this->evaluateUnexplainedHenDecrease();
            $this->evaluateUnsoldsAccumulating();
            $this->evaluateFlockDeclineWithLowProduction();
            $this->evaluateMarginWarningFarmWide();
            $this->evaluateCountMismatchFarmWide();
        }

        if ($hasBuildingData) {
            $this->evaluatePerBuildingConditions();
        }

        return FlockAlert::where('status', 'open')
            ->with('henBatch')
            ->orderByDesc('severity')
            ->orderBy('triggered_since')
            ->get()
            ->map(function (FlockAlert $alert) {
                $recommendation = isset($this->clusterNotes[$alert->id])
                    ? $this->clusterNotes[$alert->id] . ' ' . $alert->recommendation
                    : $alert->recommendation;

                return [
                    'condition'       => $alert->condition,
                    'recommendation'  => $alert->henBatch
                        ? "[{$alert->henBatch->batch_id}"
                            . ($alert->henBatch->building ? " / Bldg {$alert->henBatch->building}" : '')
                            . "] {$recommendation}"
                        : $recommendation,
                    'severity'        => $alert->severity,
                    'triggered_since' => $alert->triggered_since->format('Y-m-d'),
                ];
            })
            ->values()
            ->all();
    }

    // ── Per-building orchestration ───────────────────────────────────────────

    private function evaluatePerBuildingConditions(): void
    {
        $recent = BuildingDaily::where('date', '>=', Carbon::today()->subDays(40))
            ->orderBy('date')
            ->get()
            ->groupBy('hen_batch_id');

        $batches = HenBatch::whereIn('id', $recent->keys())
            ->where('status', 'Active')
            ->get()
            ->keyBy('id');

        foreach ($recent as $henBatchId => $rows) {
            $batch = $batches->get($henBatchId);
            if (! $batch) {
                continue; // batch no longer active — no new alerts, existing ones resolve on their own schedule
            }

            $latest = $rows->last();
            $ageWeeks = $latest->age_weeks;

            // Cull readiness has its own boundary (132wk) below the cull window (136wk) —
            // evaluate it before the blanket suppression gate.
            $this->evaluateCullReadiness($batch, $ageWeeks);

            if ($ageWeeks === null || $ageWeeks < self::RAMP_UP_AGE_WEEKS || $this->inCullWindow($ageWeeks)) {
                continue;
            }

            $this->evaluateLowProductionRate($batch, $rows);
            $this->evaluateRisingMortalityPerBuilding($batch, $rows);
            $this->evaluateFeedIntakeDrop($batch, $rows, $latest);
            $this->evaluateFeedConversionWorsening($batch, $rows);
            $this->evaluateMarginWarningPerBuilding($batch, $latest);
            $this->evaluateCountMismatchPerBuilding($batch, $latest);
        }

        // Peer deviation needs all buildings for the same day at once.
        $this->evaluatePeerDeviation($recent, $batches);

        $this->resolveCullReadinessForDepopulatedBatches();

        $this->clusterOpenAlerts();
    }

    /**
     * Group currently-open per-building alerts by condition and classify:
     * 1 building = local (disease, equipment, water line); 2-4 = possible
     * spread / shared line (note adjacency); 5+ = environmental/farm-wide,
     * severity downgraded (usually weather, per the farm manager).
     *
     * Only 'cluster_id' and 'severity' are persisted (both plain overwrites,
     * safe to redo every run). The note text itself is kept in memory and
     * combined with the recommendation only at display time in
     * getRecommendations() — never concatenated into a stored column —
     * because an alert can sit open across several days without its
     * per-building evaluator touching 'recommendation' again (e.g. while
     * recovering, pre-auto-resolve), and persisting a prepended string would
     * accumulate a new copy of the note on every dashboard load.
     */
    private function clusterOpenAlerts(): void
    {
        $open = FlockAlert::whereIn('condition', self::CLUSTERABLE_CONDITIONS)
            ->where('status', 'open')
            ->whereNotNull('hen_batch_id')
            ->with('henBatch')
            ->get()
            ->groupBy('condition');

        foreach ($open as $alerts) {
            $count = $alerts->count();
            $clusterId = 'clu_' . substr(md5($alerts->first()->condition . '|' . now()->format('Y-m-d')), 0, 16);

            $buildingNumbers = $alerts
                ->map(fn (FlockAlert $a) => $a->henBatch?->building)
                ->filter(fn ($b) => $b !== null && is_numeric($b))
                ->map(fn ($b) => (int) $b)
                ->sort()
                ->values();

            $severityOverride = null;

            if ($count <= self::CLUSTER_LOCAL_MAX) {
                $note = 'Single-building event — likely local: disease, equipment failure, or a water line issue specific to this building.';
            } elseif ($count <= self::CLUSTER_SPREAD_MAX) {
                $adjacentPairs = [];
                for ($i = 1; $i < $buildingNumbers->count(); $i++) {
                    if ($buildingNumbers[$i] - $buildingNumbers[$i - 1] === 1) {
                        $adjacentPairs[] = $buildingNumbers[$i - 1] . '-' . $buildingNumbers[$i];
                    }
                }
                $note = sprintf('%d buildings currently affected — possible spread or a shared feed/water line. ', $count)
                    . ($adjacentPairs
                        ? 'Adjacent buildings involved: ' . implode(', ', $adjacentPairs) . '.'
                        : 'Buildings are not adjacent to each other.');
            } else {
                $note = sprintf(
                    '%d buildings currently affected — pattern is farm-wide, most likely weather rather than a building-specific cause; severity downgraded accordingly.',
                    $count
                );
                $severityOverride = 'warning';
            }

            foreach ($alerts as $alert) {
                $alert->update([
                    'cluster_id' => $clusterId,
                    'severity'   => $severityOverride ?? $alert->severity,
                ]);
                $this->clusterNotes[$alert->id] = $note;
            }
        }
    }

    // Cull Readiness resolves on depopulation, not a "back to normal" streak — the
    // per-active-batch loop above never sees a batch after it's been culled, so this
    // has to be a separate pass over every currently-open Cull Readiness alert.
    private function resolveCullReadinessForDepopulatedBatches(): void
    {
        FlockAlert::where('condition', 'Cull Readiness')
            ->where('status', 'open')
            ->with('henBatch')
            ->get()
            ->each(function (FlockAlert $alert) {
                if (! $alert->henBatch || $alert->henBatch->status !== 'Active') {
                    $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
                }
            });
    }

    private function inCullWindow(?int $ageWeeks): bool
    {
        return $ageWeeks !== null && $ageWeeks >= (self::CULL_TARGET_AGE_WEEKS - self::CULL_WINDOW_LEAD_WEEKS);
    }

    private function deviation(BuildingDaily $row): ?float
    {
        if ($row->age_weeks === null) {
            return null;
        }

        return (float) $row->prod_rate - $this->layCurve->expectedRate($row->age_weeks);
    }

    // ── Condition 1 (rewritten) — Low Production Rate, age-relative ─────────
    private function evaluateLowProductionRate(HenBatch $batch, Collection $rows): void
    {
        $tail = $rows->slice(-self::PRODUCTION_DEVIATION_CONSECUTIVE_DAYS)->values();
        if ($tail->count() < self::PRODUCTION_DEVIATION_CONSECUTIVE_DAYS) {
            return;
        }

        $allBelow = $tail->every(function (BuildingDaily $row) {
            $dev = $this->deviation($row);
            return $dev !== null && $dev < -self::PRODUCTION_DEVIATION_THRESHOLD_PTS;
        });

        $condition = 'Low Production Rate';

        if ($allBelow) {
            $latest = $tail->last();
            $this->openOrTouch(
                $batch->id,
                $condition,
                'warning',
                sprintf(
                    'Actual production rate has been more than %d points below the age-expected rate for %d consecutive days.',
                    self::PRODUCTION_DEVIATION_THRESHOLD_PTS,
                    self::PRODUCTION_DEVIATION_CONSECUTIVE_DAYS
                ),
                $tail->first()->date,
                $this->layCurve->expectedRate($latest->age_weeks),
                $this->deviation($latest)
            );
        } else {
            $this->markNormalDay($batch->id, $condition);
        }
    }

    // ── New condition — Peer deviation ──────────────────────────────────────
    private function evaluatePeerDeviation(Collection $recentByBatch, Collection $batches): void
    {
        $byDate = collect();
        foreach ($recentByBatch as $henBatchId => $rows) {
            foreach ($rows as $row) {
                $byDate->put($row->date->format('Y-m-d'), ($byDate->get($row->date->format('Y-m-d')) ?? collect())->push($row));
            }
        }

        $latestDateKey = $byDate->keys()->sort()->last();
        if (! $latestDateKey) {
            return;
        }

        $dayRows = $byDate->get($latestDateKey);
        $deviations = $dayRows->map(fn (BuildingDaily $row) => $this->deviation($row))->filter(fn ($d) => $d !== null);

        if ($deviations->count() < 3) {
            return; // not enough buildings to establish a meaningful peer mean
        }

        $mean   = $deviations->avg();
        $variance = $deviations->reduce(fn ($carry, $d) => $carry + ($d - $mean) ** 2, 0.0) / $deviations->count();
        $stdDev = sqrt($variance);

        if ($stdDev <= 0) {
            return;
        }

        $threshold = $mean - self::PEER_DEVIATION_STD_DEVS * $stdDev;
        $condition = 'Peer Deviation';

        foreach ($dayRows as $row) {
            $batch = $batches->get($row->hen_batch_id);
            if (! $batch) {
                continue;
            }
            if ($row->age_weeks === null || $row->age_weeks < self::RAMP_UP_AGE_WEEKS || $this->inCullWindow($row->age_weeks)) {
                continue;
            }

            $dev = $this->deviation($row);
            if ($dev === null) {
                continue;
            }

            if ($dev < $threshold) {
                $this->openOrTouch(
                    $batch->id,
                    $condition,
                    'critical',
                    sprintf(
                        'Production is an outlier versus the other %d buildings today (%.1f pts below the daily peer mean, peer SD %.1f) — likely building-specific, not weather.',
                        $dayRows->count() - 1,
                        $mean - $dev,
                        $stdDev
                    ),
                    $row->date
                );
            } else {
                $this->markNormalDay($batch->id, $condition);
            }
        }
    }

    // ── Condition 2 (rewritten) — Rising Mortality, farm-wide ────────────────
    private function evaluateRisingMortalityFarmWide(): void
    {
        $records = EggProduction::orderByDesc('date')
            ->take(self::MORTALITY_BASELINE_DAYS)
            ->get(['date', 'mortality']);

        if ($records->count() < self::MORTALITY_BASELINE_DAYS) {
            return;
        }

        $mean7  = $records->take(self::MORTALITY_TRAILING_DAYS)->avg('mortality');
        $mean30 = $records->avg('mortality');
        $condition = 'Rising Mortality (Farm-wide)';

        if ($mean30 > 0 && $mean7 > $mean30 * (1 + self::MORTALITY_SPIKE_PCT / 100)) {
            $this->openOrTouch(
                null,
                $condition,
                'critical',
                sprintf(
                    'Farm-wide 7-day average mortality (%.1f/day) is %.0f%% above the 30-day average (%.1f/day).',
                    $mean7, (($mean7 - $mean30) / $mean30) * 100, $mean30
                ),
                $records->take(self::MORTALITY_TRAILING_DAYS)->last()->date
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    private function evaluateRisingMortalityPerBuilding(HenBatch $batch, Collection $rows): void
    {
        if ($rows->count() < self::MORTALITY_BASELINE_DAYS) {
            return;
        }

        $tail30 = $rows->slice(-self::MORTALITY_BASELINE_DAYS)->values();
        $tail7  = $tail30->slice(-self::MORTALITY_TRAILING_DAYS)->values();

        $mean7  = $tail7->avg('mortality');
        $mean30 = $tail30->avg('mortality');
        $condition = 'Rising Mortality (Building)';

        if ($mean30 > 0 && $mean7 > $mean30 * (1 + self::MORTALITY_SPIKE_PCT / 100)) {
            $this->openOrTouch(
                $batch->id,
                $condition,
                'critical',
                sprintf(
                    'This building\'s 7-day average mortality (%.1f/day) is %.0f%% above its own 30-day average (%.1f/day) — a farm-wide average can hide a single sick house.',
                    $mean7, (($mean7 - $mean30) / $mean30) * 100, $mean30
                ),
                $tail7->first()->date
            );
        } else {
            $this->markNormalDay($batch->id, $condition);
        }
    }

    // ── Condition 4 (rewritten) — Declining Revenue ──────────────────────────
    private function evaluateDecliningRevenue(): void
    {
        $days = self::REVENUE_TRAILING_DAYS;

        $sales = EggSale::where('date', '>=', Carbon::today()->subDays($days * 2))
            ->selectRaw('DATE(date) as sale_date, SUM(total_amount) as revenue, SUM(quantity) as qty')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        if ($sales->count() < $days * 2) {
            return;
        }

        $recent = $sales->slice(-$days)->values();
        $prior  = $sales->slice(-$days * 2, $days)->values();

        $meanRevenueRecent = $recent->avg('revenue');
        $meanRevenuePrior  = $prior->avg('revenue');
        $condition = 'Declining Revenue';

        if ($meanRevenuePrior <= 0) {
            return;
        }

        $pctChange = (($meanRevenueRecent - $meanRevenuePrior) / $meanRevenuePrior) * 100;

        if ($pctChange < self::REVENUE_DECLINE_PCT) {
            $qtyRecent = $recent->avg('qty');
            $qtyPrior  = $prior->avg('qty');
            $priceRecent = $qtyRecent > 0 ? $meanRevenueRecent / $qtyRecent : 0;
            $pricePrior  = $qtyPrior > 0 ? $meanRevenuePrior / $qtyPrior : 0;

            $qtyChangePct   = $qtyPrior > 0 ? (($qtyRecent - $qtyPrior) / $qtyPrior) * 100 : 0;
            $priceChangePct = $pricePrior > 0 ? (($priceRecent - $pricePrior) / $pricePrior) * 100 : 0;

            $driver = abs($priceChangePct) >= abs($qtyChangePct)
                ? sprintf('price-driven (avg price %.1f%% vs prior week — the farm reprices weekly, so confirm this isn\'t routine)', $priceChangePct)
                : sprintf('volume-driven (units sold %.1f%% vs prior week)', $qtyChangePct);

            $this->openOrTouch(
                null,
                $condition,
                'warning',
                sprintf(
                    'Revenue over the last %d days (₱%s/day avg) is %.0f%% below the prior %d days (₱%s/day avg) — %s.',
                    $days, number_format($meanRevenueRecent, 2), $pctChange, $days, number_format($meanRevenuePrior, 2), $driver
                ),
                $recent->first()->sale_date
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    // ── Condition 3 (rewritten) — High Culling Rate, trailing 6mo + cull-window skip ──
    private function evaluateHighCullingRate(): void
    {
        $monthStart = Carbon::now()->startOfMonth();
        $baselineStart = (clone $monthStart)->subMonths(self::CULL_RATE_TRAILING_MONTHS);

        $currentMonthCulls = CullRecord::with('henBatch')
            ->where('date', '>=', $monthStart)
            ->get()
            ->filter(fn (CullRecord $c) => ! $this->wasPlannedCull($c));

        $historical = CullRecord::with('henBatch')
            ->where('date', '>=', $baselineStart)
            ->where('date', '<', $monthStart)
            ->get()
            ->filter(fn (CullRecord $c) => ! $this->wasPlannedCull($c));

        if ($historical->isEmpty()) {
            return;
        }

        $monthlyAvg = $historical
            ->groupBy(fn (CullRecord $c) => $c->date->format('Y-m'))
            ->map(fn ($g) => $g->sum('quantity_culled'))
            ->avg();

        $currentTotal = $currentMonthCulls->sum('quantity_culled');
        $condition = 'High Culling Rate';

        if ($monthlyAvg > 0 && $currentTotal > $monthlyAvg) {
            $this->openOrTouch(
                null,
                $condition,
                'warning',
                sprintf(
                    'Unplanned culls this month (%d) exceed the trailing %d-month average (%.0f) — excludes culls inside a building\'s planned cull window.',
                    $currentTotal, self::CULL_RATE_TRAILING_MONTHS, $monthlyAvg
                ),
                $monthStart
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    private function wasPlannedCull(CullRecord $cull): bool
    {
        if (! $cull->henBatch) {
            return false;
        }

        $age = $this->ageAtDate($cull->henBatch, $cull->date);

        return $age !== null && $this->inCullWindow($age);
    }

    // ── Condition 7 — Flock Decline & Low Production (suppressed in cull window) ──
    private function evaluateFlockDeclineWithLowProduction(): void
    {
        $anyBatchInCullWindow = HenBatch::where('status', 'Active')->get()
            ->contains(function (HenBatch $batch) {
                $latest = BuildingDaily::where('hen_batch_id', $batch->id)->orderByDesc('date')->first();
                return $latest && $this->inCullWindow($latest->age_weeks);
            });

        $condition = 'Flock Decline & Low Production';

        if ($anyBatchInCullWindow) {
            // A normal planned cull is in progress somewhere on the farm — this is exactly
            // what that looks like farm-wide, so don't fire.
            $this->markNormalDay(null, $condition);
            return;
        }

        $productions = EggProduction::orderBy('date', 'desc')->take(30)->get();
        if ($productions->count() < 4) {
            return;
        }

        $sorted = $productions->sortBy('date')->values();
        $n = $sorted->count();
        $streak = 0;
        $since = null;

        for ($i = $n - 1; $i >= 1; $i--) {
            $curr = $sorted[$i];
            $prev = $sorted[$i - 1];
            $rate = $curr->active_hens > 0 ? ($curr->eggs_collected / $curr->active_hens) * 100 : 0;

            if ($curr->active_hens < $prev->active_hens && $rate < 75) {
                $streak++;
                $since = $curr->date;
            } else {
                break;
            }
        }

        if ($streak >= 3) {
            $this->openOrTouch(
                null,
                $condition,
                'critical',
                'Active hens declining alongside sub-75% production rate for 3+ consecutive days — recommend flock replenishment planning.',
                $since
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    // ── Condition 5 — Unexplained Hen Decrease (unchanged) ───────────────────
    private function evaluateUnexplainedHenDecrease(): void
    {
        $productions = EggProduction::orderBy('date', 'desc')->take(30)->get();
        $sorted = $productions->sortBy('date')->values();
        $condition = 'Unexplained Hen Decrease';

        if ($sorted->count() < 2) {
            return;
        }

        $cullDates = CullRecord::where('date', '>=', $sorted->first()->date)
            ->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $flagged = [];
        for ($i = 1; $i < $sorted->count(); $i++) {
            $curr = $sorted[$i];
            $prev = $sorted[$i - 1];
            $dateKey = $curr->date->format('Y-m-d');

            if ($curr->active_hens < $prev->active_hens && $curr->mortality == 0 && ! $cullDates->has($dateKey)) {
                $flagged[] = $curr->date;
            }
        }

        if (! empty($flagged)) {
            $this->openOrTouch(
                null,
                $condition,
                'warning',
                'Active hen count decreased without a matching mortality or cull record — verify livestock records.',
                $flagged[0]
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    // ── Condition 6 — Unsold Egg Accumulation (unchanged) ───────────────────
    private function evaluateUnsoldsAccumulating(): void
    {
        $productions = EggProduction::orderBy('date', 'desc')->take(30)->get();
        $sorted = $productions->sortBy('date')->values();
        $condition = 'Unsold Egg Accumulation';
        $n = $sorted->count();

        if ($n < 3) {
            return;
        }

        $salesByDate = EggSale::where('date', '>=', Carbon::today()->subDays(30))
            ->selectRaw('DATE(date) as sale_date, SUM(quantity) as daily_sold')
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');

        $daily = $sorted->map(function ($p) use ($salesByDate) {
            $key = $p->date->format('Y-m-d');
            $sold = isset($salesByDate[$key]) ? (float) $salesByDate[$key]->daily_sold : 0;
            return ['date' => $p->date, 'remaining' => $p->eggs_collected - $sold];
        })->values();

        $streak = 1;
        $since = $daily[$n - 1]['date'];

        for ($i = $n - 1; $i >= 1; $i--) {
            if ($daily[$i]['remaining'] > $daily[$i - 1]['remaining']) {
                $streak++;
                $since = $daily[$i - 1]['date'];
            } else {
                break;
            }
        }

        if ($streak >= 3) {
            $this->openOrTouch(
                null,
                $condition,
                'warning',
                'Remaining unsold eggs have grown for 3+ consecutive days — review sales pace.',
                $since
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    // ── New condition — Feed intake drop ─────────────────────────────────────
    private function evaluateFeedIntakeDrop(HenBatch $batch, Collection $rows, BuildingDaily $latest): void
    {
        $condition = 'Feed Intake Drop';
        $baseline = $this->trailingFeedPerBirdMean($rows, excludeLast: true);

        if ($baseline === null || $latest->feed_bags === null || $latest->population <= 0) {
            return;
        }

        $todayFeedPerBird = $this->feedPerBird($latest);
        $prodDeviation = $this->deviation($latest);

        $feedDropped = $todayFeedPerBird < $baseline * (1 - self::FEED_INTAKE_DROP_PCT / 100);
        $prodStillNormal = $prodDeviation !== null && abs($prodDeviation) <= self::NEAR_EXPECTED_TOLERANCE_PTS;

        if ($feedDropped && $prodStillNormal) {
            $this->openOrTouch(
                $batch->id,
                $condition,
                'critical',
                sprintf(
                    'Feed intake (%.1fg/bird) is %.0f%% below its 7-day trailing mean (%.1fg/bird) while production is still near age-expected — hens often go off feed before lay rate drops.',
                    $todayFeedPerBird, (($baseline - $todayFeedPerBird) / $baseline) * 100, $baseline
                ),
                $latest->date
            );
        } else {
            $this->markNormalDay($batch->id, $condition);
        }
    }

    // ── New condition — Feed conversion worsening ────────────────────────────
    private function evaluateFeedConversionWorsening(HenBatch $batch, Collection $rows): void
    {
        $condition = 'Feed Conversion Worsening';
        $window = self::FEED_CONVERSION_WINDOW_DAYS;

        if ($rows->count() < $window) {
            return;
        }

        $tail = $rows->slice(-$window)->values();
        $first = $tail->first();
        $last = $tail->last();

        if ($first->feed_bags === null || $last->feed_bags === null) {
            return;
        }

        $feedRising = $this->feedPerBird($last) > $this->feedPerBird($first);
        $prodFalling = $last->prod_rate < $first->prod_rate;

        if ($feedRising && $prodFalling) {
            $this->openOrTouch(
                $batch->id,
                $condition,
                'warning',
                sprintf(
                    'Feed per bird rose (%.1f → %.1fg) while production fell (%.1f%% → %.1f%%) over %d days — check for spillage, rodents, or illness.',
                    $this->feedPerBird($first), $this->feedPerBird($last), $first->prod_rate, $last->prod_rate, $window
                ),
                $first->date
            );
        } else {
            $this->markNormalDay($batch->id, $condition);
        }
    }

    private function feedPerBird(BuildingDaily $row): ?float
    {
        if ($row->feed_bags === null || $row->population <= 0) {
            return null;
        }

        return ((float) $row->feed_bags * self::FEED_KG_PER_BAG * 1000) / $row->population;
    }

    private function trailingFeedPerBirdMean(Collection $rows, bool $excludeLast): ?float
    {
        $pool = $excludeLast ? $rows->slice(-(self::FEED_TRAILING_DAYS + 1), self::FEED_TRAILING_DAYS) : $rows->slice(-self::FEED_TRAILING_DAYS);

        $values = $pool->map(fn (BuildingDaily $r) => $this->feedPerBird($r))->filter(fn ($v) => $v !== null);

        if ($values->count() < self::FEED_MIN_NONNULL_DAYS) {
            return null;
        }

        return $values->avg();
    }

    // ── New condition — Margin warning ───────────────────────────────────────
    private function evaluateMarginWarningFarmWide(): void
    {
        $condition = 'Margin Warning (Farm-wide)';
        $latest = EggProduction::orderByDesc('date')->first();

        if (! $latest || $latest->feed_bags === null || $latest->feed_cost_per_bag === null || $latest->eggs_collected <= 0) {
            return;
        }

        $revenuePerEgg = $this->revenuePerEggOn($latest->date);
        if ($revenuePerEgg === null) {
            return;
        }

        $feedCostPerEgg = ((float) $latest->feed_bags * (float) $latest->feed_cost_per_bag) / $latest->eggs_collected;

        if ($feedCostPerEgg >= $revenuePerEgg * (1 - self::MARGIN_WARNING_PCT / 100)) {
            $this->openOrTouch(
                null,
                $condition,
                'warning',
                sprintf(
                    'Farm-wide feed cost per egg (₱%.2f) is within %d%% of revenue per egg (₱%.2f) — margin is thin. Feed cost per sack is an unverified estimate; confirm before acting.',
                    $feedCostPerEgg, self::MARGIN_WARNING_PCT, $revenuePerEgg
                ),
                $latest->date
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    private function evaluateMarginWarningPerBuilding(HenBatch $batch, BuildingDaily $latest): void
    {
        $condition = 'Margin Warning (Building)';
        $farmLatest = EggProduction::whereDate('date', $latest->date)->first();

        if (! $farmLatest || $farmLatest->feed_cost_per_bag === null || $latest->feed_bags === null || $latest->eggs_house <= 0) {
            return;
        }

        $revenuePerEgg = $this->revenuePerEggOn($latest->date);
        if ($revenuePerEgg === null) {
            return;
        }

        $feedCostPerEgg = ((float) $latest->feed_bags * (float) $farmLatest->feed_cost_per_bag) / $latest->eggs_house;

        if ($feedCostPerEgg >= $revenuePerEgg * (1 - self::MARGIN_WARNING_PCT / 100)) {
            $this->openOrTouch(
                $batch->id,
                $condition,
                'warning',
                sprintf(
                    'Feed cost per egg for this building (₱%.2f) is within %d%% of farm-wide revenue per egg (₱%.2f). Feed cost per sack is an unverified estimate.',
                    $feedCostPerEgg, self::MARGIN_WARNING_PCT, $revenuePerEgg
                ),
                $latest->date
            );
        } else {
            $this->markNormalDay($batch->id, $condition);
        }
    }

    private function revenuePerEggOn(Carbon $date): ?float
    {
        $sale = EggSale::whereDate('date', $date)
            ->selectRaw('SUM(total_amount) as revenue, SUM(quantity) as qty')
            ->first();

        if (! $sale || ! $sale->qty) {
            return null;
        }

        return (float) $sale->revenue / (float) $sale->qty;
    }

    // ── New condition — Count mismatch (Audit Assistant) ─────────────────────
    private function evaluateCountMismatchPerBuilding(HenBatch $batch, BuildingDaily $latest): void
    {
        $condition = 'Count Mismatch (Building)';

        if ($latest->eggs_house <= 0) {
            return;
        }

        $pctDiff = abs($latest->eggs_house - $latest->eggs_eggroom) / $latest->eggs_house * 100;

        if ($pctDiff > self::COUNT_MISMATCH_PCT) {
            $this->openOrTouch(
                $batch->id,
                $condition,
                'warning',
                sprintf(
                    'House count (%d) and Egg Room count (%d) differ by %.2f%%, above the %.1f%% audit threshold.',
                    $latest->eggs_house, $latest->eggs_eggroom, $pctDiff, self::COUNT_MISMATCH_PCT
                ),
                $latest->date
            );
        } else {
            $this->markNormalDay($batch->id, $condition);
        }
    }

    private function evaluateCountMismatchFarmWide(): void
    {
        $condition = 'Count Mismatch (Farm-wide)';
        $latest = EggProduction::orderByDesc('date')->first();

        if (! $latest || $latest->eggs_collected <= 0) {
            return;
        }

        $gradingTotal = EggGradingDaily::whereDate('date', $latest->date)->sum('total_pcs');

        if ($gradingTotal <= 0) {
            return;
        }

        $pctDiff = abs($latest->eggs_collected - $gradingTotal) / $latest->eggs_collected * 100;

        if ($pctDiff > self::COUNT_MISMATCH_PCT) {
            $this->openOrTouch(
                null,
                $condition,
                'warning',
                sprintf(
                    'Farm house total (%d) and grading total (%d) differ by %.2f%%, above the %.1f%% audit threshold.',
                    $latest->eggs_collected, $gradingTotal, $pctDiff, self::COUNT_MISMATCH_PCT
                ),
                $latest->date
            );
        } else {
            $this->markNormalDay(null, $condition);
        }
    }

    // ── New condition — Cull readiness ───────────────────────────────────────
    private function evaluateCullReadiness(HenBatch $batch, ?int $ageWeeks): void
    {
        $condition = 'Cull Readiness';

        if ($ageWeeks !== null && $ageWeeks >= self::CULL_READINESS_AGE_WEEKS) {
            $this->openOrTouch(
                $batch->id,
                $condition,
                'warning',
                sprintf(
                    'Flock is %d weeks old (target cull %d weeks) — order replacement pullets so the batch isn\'t culled before replacements are ready.',
                    $ageWeeks, self::CULL_TARGET_AGE_WEEKS
                ),
                Carbon::today()
            );
        }
    }

    // ── Age helper ────────────────────────────────────────────────────────────
    private function ageAtDate(HenBatch $batch, Carbon $date): ?int
    {
        if ($batch->placement_date) {
            return (int) floor($batch->placement_date->diffInDays($date) / 7);
        }

        $row = BuildingDaily::where('hen_batch_id', $batch->id)
            ->orderByRaw('ABS(DATEDIFF(date, ?)) asc', [$date->format('Y-m-d')])
            ->first();

        if (! $row || $row->age_weeks === null) {
            return null;
        }

        $dayDiff = $row->date->diffInDays($date, false);

        return (int) round($row->age_weeks + $dayDiff / 7);
    }

    // ── Alert lifecycle ───────────────────────────────────────────────────────
    private function openOrTouch(
        ?int $henBatchId,
        string $condition,
        string $severity,
        string $recommendation,
        Carbon $triggeredSince,
        ?float $expectedRate = null,
        ?float $deviation = null
    ): void {
        $existing = FlockAlert::where('hen_batch_id', $henBatchId)
            ->where('condition', $condition)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            $existing->update([
                'severity'       => $severity,
                'recommendation' => $recommendation,
                'expected_rate'  => $expectedRate,
                'deviation'      => $deviation,
                'normal_streak'  => 0,
            ]);
            return;
        }

        FlockAlert::create([
            'hen_batch_id'     => $henBatchId,
            'condition'        => $condition,
            'severity'         => $severity,
            'recommendation'   => $recommendation,
            'expected_rate'    => $expectedRate,
            'deviation'        => $deviation,
            'triggered_since'  => $triggeredSince,
            'status'           => 'open',
            'normal_streak'    => 0,
        ]);
    }

    private function markNormalDay(?int $henBatchId, string $condition): void
    {
        $existing = FlockAlert::where('hen_batch_id', $henBatchId)
            ->where('condition', $condition)
            ->where('status', 'open')
            ->first();

        if (! $existing) {
            return;
        }

        $streak = $existing->normal_streak + 1;

        if ($streak >= self::AUTO_RESOLVE_STREAK_DAYS) {
            $existing->update(['status' => 'resolved', 'resolved_at' => now()]);
        } else {
            $existing->update(['normal_streak' => $streak]);
        }
    }
}
