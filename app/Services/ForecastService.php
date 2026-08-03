<?php

namespace App\Services;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\ForecastEvaluation;
use App\Models\HenBatch;
use Illuminate\Support\Carbon;
use Phpml\Regression\LeastSquares;

class ForecastService
{
    // ── Provenance key ────────────────────────────────────────────────────────
    // OBSERVED   = measured directly in the farm's July 2026 file
    // STATED     = told to us by farm staff in interview, not independently measured
    // PLACEHOLDER = not from the farm at all; invented pending real calibration data
    // ENGINEERING = a mechanical choice (window length, retry count, etc.), not a farm figure

    // ENGINEERING — trailing window for the average egg price used in revenue
    // forecasts; 30 days avoids stale prices from years-old sales once real
    // multi-year data accumulates.
    private const AVG_PRICE_LOOKBACK_DAYS = 30;

    // ENGINEERING — minimum overlapping days (egg_productions x building_daily)
    // required before the age-aware model is trusted over the day-index-only one.
    private const MIN_AGE_FEATURE_DAYS = 30;

    // STATED (farm interview) — cull policy target; used to project when an
    // active batch will likely be depopulated for the spent-hen revenue event
    // and to stop projecting its age/population contribution past that point.
    private const CULL_TARGET_AGE_WEEKS = 140;

    // Caveat that applies to any forecast this service produces: July 2026 is
    // a trough, not a typical month — the production manager says they
    // normally run ~200 cases/day but were at ~190 because several cohorts
    // were young pullets not yet laying. A model trained only on this window
    // will run low until 12 months of data broaden the training set.

    public function forecast(bool $persist = false): array
    {
        $records = EggProduction::orderBy('date')->get(['date', 'eggs_collected']);
        $n       = $records->count();

        if ($n < 30) {
            return [
                'active'  => false,
                'message' => 'Forecasting activates after 30 days of recorded data',
            ];
        }

        $dayIndexX = array_map(fn ($i) => [$i + 1], range(0, $n - 1));
        $Y         = $records->pluck('eggs_collected')->map(fn ($v) => (float) $v)->toArray();

        // ── Single-feature model (day index only) — the pre-Phase-7 baseline ──
        $baselineModel = new LeastSquares();
        $baselineModel->train($dayIndexX, $Y);

        $mapeBefore = $this->holdoutMape($dayIndexX, $Y, fn () => new LeastSquares());

        // ── Two-feature model (day index + flock-weighted mean age) ────────────
        $lastDate      = $records->last()->date;
        $agesByDate    = $this->weightedAgeByDate($records->first()->date, $lastDate);
        $overlapDays   = count(array_intersect_key($agesByDate, array_flip($records->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->all())));
        $ageFeatureOn  = $overlapDays >= self::MIN_AGE_FEATURE_DAYS;

        $model      = $baselineModel;
        $mapeAfter  = null;
        $futureAges = [];

        if ($ageFeatureOn) {
            $twoFeatureX = [];
            foreach ($records as $i => $record) {
                $dateKey = $record->date->format('Y-m-d');
                // Fall back to the nearest known age reading so a handful of
                // missing days doesn't force us back onto the single-feature model.
                $twoFeatureX[] = [$i + 1, $agesByDate[$dateKey] ?? $this->nearestKnownAge($agesByDate, $dateKey)];
            }

            $ageModel = new LeastSquares();
            $ageModel->train($twoFeatureX, $Y);
            $model = $ageModel;

            $mapeAfter  = $this->holdoutMape($twoFeatureX, $Y, fn () => new LeastSquares());
            $futureAges = $this->projectFutureWeightedAges($lastDate, 30);
        }

        $avgPrice = (float) (
            EggSale::where('date', '>=', Carbon::today()->subDays(self::AVG_PRICE_LOOKBACK_DAYS))
                ->avg('price_per_unit') ?? 9.0
        );

        $cullIncomeByDayOffset = $this->projectSpentHenIncome($lastDate, 30);

        $predictFor = function (int $dayOffset) use ($model, $n, $ageFeatureOn, $futureAges) {
            $sample = $ageFeatureOn
                ? [$n + $dayOffset, $futureAges[$dayOffset] ?? end($futureAges)]
                : [$n + $dayOffset];

            return max(0, (int) round($model->predict($sample)));
        };

        // 7-day production forecast
        $forecast7day = [];
        for ($d = 1; $d <= 7; $d++) {
            $forecast7day[] = [
                'day'       => Carbon::parse($lastDate)->addDays($d)->format('M d'),
                'predicted' => $predictFor($d),
            ];
        }

        // 30-day revenue forecast — spent-hen income is added as a discrete lump
        // on its own projected day, never smoothed across the 30 days.
        $forecast30day = [];
        for ($d = 1; $d <= 30; $d++) {
            $predictedEggs = $predictFor($d);
            $cullIncome    = round($cullIncomeByDayOffset[$d] ?? 0, 2);

            $forecast30day[] = [
                'day'               => Carbon::parse($lastDate)->addDays($d)->format('M d'),
                'predicted_revenue' => round($predictedEggs * $avgPrice, 2) + $cullIncome,
                'cull_income'       => $cullIncome,
            ];
        }

        $result = [
            'active'            => true,
            'forecast_7day'     => $forecast7day,
            'forecast_30day'    => $forecast30day,
            'mape'              => $mapeAfter ?? $mapeBefore,
            'mape_before_age_feature' => $mapeBefore,
            'mape_after_age_feature'  => $mapeAfter,
            'age_feature_active'      => $ageFeatureOn,
            'trained_on'        => $n,
            'last_trained'      => now()->format('Y-m-d H:i:s'),
        ];

        if ($persist) {
            ForecastEvaluation::create([
                'trained_on'              => $n,
                'mape'                    => $result['mape'],
                'mape_before_age_feature' => $mapeBefore,
                'forecast_7day_total'     => collect($forecast7day)->sum('predicted'),
                'forecast_30day_total'    => collect($forecast30day)->sum('predicted_revenue'),
                'evaluated_at'            => now(),
            ]);
        }

        return $result;
    }

    /**
     * MAPE holdout — retrain on the first n-7 records, predict the last 7 as a
     * holdout. Kept identical in mechanism for both the single- and
     * two-feature models so the reported improvement is apples-to-apples.
     */
    private function holdoutMape(array $X, array $Y, callable $newModel): float
    {
        $holdout = 7;
        $n       = count($Y);

        $trainX = array_slice($X, 0, $n - $holdout);
        $trainY = array_slice($Y, 0, $n - $holdout);

        $mapeModel = $newModel();
        $mapeModel->train($trainX, $trainY);

        $mapeValues = [];
        for ($i = 0; $i < $holdout; $i++) {
            $idx       = $n - $holdout + $i;
            $actual    = $Y[$idx];
            $predicted = $mapeModel->predict($X[$idx]);
            if ($actual > 0) {
                $mapeValues[] = abs($actual - $predicted) / $actual * 100;
            }
        }

        return count($mapeValues) > 0
            ? round(array_sum($mapeValues) / count($mapeValues), 2)
            : 0.0;
    }

    /**
     * Flock-weighted mean age per date: sum(population x age_weeks) / sum(population)
     * across that date's building_daily rows. OBSERVED-grade once building_daily
     * has real history; today it's sparse (Phase 3 is new), which is why the
     * model falls back to the single-feature baseline below MIN_AGE_FEATURE_DAYS.
     */
    private function weightedAgeByDate(Carbon $start, Carbon $end): array
    {
        return BuildingDaily::whereBetween('date', [$start, $end])
            ->get(['date', 'population', 'age_weeks'])
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'))
            ->map(function ($rows) {
                $totalPop = $rows->sum('population');
                return $totalPop > 0
                    ? $rows->reduce(fn ($carry, $r) => $carry + $r->population * $r->age_weeks, 0) / $totalPop
                    : null;
            })
            ->filter(fn ($v) => $v !== null)
            ->all();
    }

    private function nearestKnownAge(array $agesByDate, string $dateKey): ?float
    {
        if (empty($agesByDate)) {
            return null;
        }

        $target = Carbon::parse($dateKey);
        $nearest = null;
        $nearestDiff = null;

        foreach ($agesByDate as $date => $age) {
            $diff = abs(Carbon::parse($date)->diffInDays($target));
            if ($nearestDiff === null || $diff < $nearestDiff) {
                $nearestDiff = $diff;
                $nearest = $age;
            }
        }

        return $nearest;
    }

    /**
     * Because ages are known in advance (today's active buildings simply get
     * older), the forecast horizon needs no extrapolation of this feature —
     * only a day-by-day age projection per building, weighted by its most
     * recently recorded population.
     *
     * @return array<int,float> day offset (1..$days) => weighted mean age
     */
    private function projectFutureWeightedAges(Carbon $lastDate, int $days): array
    {
        $latestPerBatch = BuildingDaily::where('date', '>=', $lastDate->copy()->subDays(14))
            ->orderBy('date')
            ->get()
            ->groupBy('hen_batch_id')
            ->map(fn ($rows) => $rows->last())
            ->filter(fn ($row) => $row->age_weeks !== null);

        $projections = [];
        for ($d = 1; $d <= $days; $d++) {
            $totalWeighted = 0;
            $totalPop      = 0;

            foreach ($latestPerBatch as $row) {
                $ageAtD = $row->age_weeks + ($d / 7);
                if ($ageAtD > self::CULL_TARGET_AGE_WEEKS) {
                    continue; // assume depopulated by then, per stated cull policy
                }
                $totalWeighted += $row->population * $ageAtD;
                $totalPop      += $row->population;
            }

            $projections[$d] = $totalPop > 0 ? $totalWeighted / $totalPop : null;
        }

        // Fill any all-depopulated day (rare) with the last known value so predict() never gets null.
        $lastKnown = null;
        foreach ($projections as $d => $age) {
            if ($age === null) {
                $projections[$d] = $lastKnown ?? self::CULL_TARGET_AGE_WEEKS;
            } else {
                $lastKnown = $age;
            }
        }

        return $projections;
    }

    /**
     * Spent-hen sale income (Phase 6) as a discrete lump on the day a batch is
     * projected to cross the cull-policy age, rather than smoothed across the
     * 30-day window. Heads sold = that batch's latest recorded population;
     * price = the average of price_per_head actually recorded on past cull
     * records (skipped entirely if none exist yet — we don't invent a price).
     *
     * @return array<int,float> day offset (1..$days) => projected income that day
     */
    private function projectSpentHenIncome(Carbon $lastDate, int $days): array
    {
        $avgPricePerHead = CullRecord::whereNotNull('price_per_head')->avg('price_per_head');
        if (! $avgPricePerHead) {
            return [];
        }

        $latestPerBatch = BuildingDaily::where('date', '>=', $lastDate->copy()->subDays(14))
            ->orderBy('date')
            ->get()
            ->groupBy('hen_batch_id')
            ->map(fn ($rows) => $rows->last())
            ->filter(fn ($row) => $row->age_weeks !== null);

        $activeBatchIds = HenBatch::where('status', 'Active')->pluck('id')->flip();

        $income = [];
        foreach ($latestPerBatch as $henBatchId => $row) {
            if (! $activeBatchIds->has($henBatchId)) {
                continue;
            }

            $weeksToTarget = self::CULL_TARGET_AGE_WEEKS - $row->age_weeks;
            if ($weeksToTarget < 0) {
                continue; // already past target — a real cull is overdue, not a clean future event to project
            }

            $dayOffset = (int) round($weeksToTarget * 7);
            if ($dayOffset < 1 || $dayOffset > $days) {
                continue;
            }

            $income[$dayOffset] = ($income[$dayOffset] ?? 0) + ($row->population * $avgPricePerHead);
        }

        return $income;
    }
}
