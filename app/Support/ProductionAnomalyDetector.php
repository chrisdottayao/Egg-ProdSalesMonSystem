<?php

namespace App\Support;

use App\Models\EggProduction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 3 item 4 (S23): Production Drop, Production Spike and Mortality
 * Spike. Each day is compared with the 7 calendar days before it, using the
 * farm-wide daily rollup (egg_productions), which only ever contains
 * approved building rows (Phase 3 item 1).
 *
 * Rules: a day is only judged when all 7 prior days are recorded; nothing
 * is ever divided by zero; percentages are capped when stored (upsertAlert).
 * Returns alert rows for DashboardController::upsertAlert().
 */
class ProductionAnomalyDetector
{
    public const PRIOR_DAYS = 7;

    /** @return list<array<string, mixed>> */
    public static function detect(string $fromDate, string $toDate): array
    {
        $rows = EggProduction::whereDate('date', '>=', Carbon::parse($fromDate)->subDays(self::PRIOR_DAYS)->toDateString())
            ->whereDate('date', '<=', $toDate)
            ->get(['date', 'eggs_collected', 'active_hens', 'mortality'])
            ->keyBy(fn ($r) => $r->date->toDateString());

        $alerts = [];
        foreach ($rows as $date => $day) {
            if ($date < $fromDate) {
                continue;
            }
            $prior = self::priorDays($rows, $date);
            if ($prior === null) {
                continue; // not enough history: never judge a day without 7 prior recorded days
            }

            array_push($alerts, ...self::productionAlerts($date, $day, $prior), ...self::mortalityAlerts($date, $day, $prior));
        }

        return $alerts;
    }

    /** The 7 recorded days before $date, or null if any is missing. */
    private static function priorDays(Collection $rows, string $date): ?Collection
    {
        $prior = collect(range(1, self::PRIOR_DAYS))
            ->map(fn ($n) => $rows->get(Carbon::parse($date)->subDays($n)->toDateString()));

        return $prior->contains(null) ? null : $prior;
    }

    private static function rate(EggProduction $row): ?float
    {
        return $row->active_hens > 0 ? $row->eggs_collected / $row->active_hens * 100 : null;
    }

    private static function productionAlerts(string $date, EggProduction $day, Collection $prior): array
    {
        $rate  = self::rate($day);
        $rates = $prior->map(fn ($r) => self::rate($r))->filter(fn ($r) => $r !== null);
        if ($rate === null || $rates->count() < self::PRIOR_DAYS) {
            return [];
        }
        $avg = $rates->avg();
        if ($avg <= 0) {
            return [];
        }

        // Rounded so a day sitting exactly on the threshold is judged the
        // same way every time (no floating-point noise); alerts fire only
        // when the deviation EXCEEDS the threshold.
        $dev  = round(($rate - $avg) / $avg * 100, 2);
        $drop = Thresholds::get('anomaly_prod_drop_pct');
        $rise = Thresholds::get('anomaly_prod_spike_pct');
        $numbers = sprintf('%.1f%% on %s vs a 7-day average of %.1f%% (%+.1f%%)', $rate, $date, $avg, $dev);

        if ($dev < -$drop) {
            return [[
                'type'           => 'Production Drop',
                'severity'       => $dev <= -2 * $drop ? 'high' : 'medium',
                'alert_date'     => $date,
                'expected_value' => sprintf('%.1f%% prod rate', $avg),
                'actual_value'   => sprintf('%.1f%% prod rate', $rate),
                'deviation_pct'  => round($dev, 2),
                'description'    => "Production rate fell {$numbers}, beyond the {$drop}% drop threshold. Check feed, water, lighting, heat stress and bird health.",
            ]];
        }
        if ($dev > $rise) {
            return [[
                'type'           => 'Production Spike',
                'severity'       => 'low',
                'alert_date'     => $date,
                'expected_value' => sprintf('%.1f%% prod rate', $avg),
                'actual_value'   => sprintf('%.1f%% prod rate', $rate),
                'deviation_pct'  => round($dev, 2),
                'description'    => "Production rate rose {$numbers}, beyond the {$rise}% spike threshold. Check that eggs and population were entered correctly.",
            ]];
        }

        return [];
    }

    private static function mortalityAlerts(string $date, EggProduction $day, Collection $prior): array
    {
        $deaths  = (int) $day->mortality;
        $min     = Thresholds::get('anomaly_mortality_spike_min');
        $pct     = Thresholds::get('anomaly_mortality_spike_pct');
        $avg     = (float) $prior->avg('mortality');
        if ($deaths < $min) {
            return [];
        }

        if ($avg > 0) {
            $dev = round(($deaths - $avg) / $avg * 100, 2);
            if ($dev <= $pct) {
                return [];
            }
            $vs = sprintf('vs a 7-day average of %.1f per day (%+.0f%%)', $avg, $dev);
        } else {
            $dev = null; // no deaths in the prior 7 days: no ratio to compute
            $vs  = 'after no deaths in the previous 7 days';
        }

        // Weekly mortality band for context (deaths over the last 7 days incl. this one).
        $weekDeaths = $deaths + (int) $prior->take(self::PRIOR_DAYS - 1)->sum('mortality');
        $band = MortalityBands::forDeaths($weekDeaths, $day->active_hens ?: null);
        $bandText = $band ? " Weekly mortality is in the '{$band['label']}' band ({$band['action']})." : '';

        return [[
            'type'           => 'Mortality Spike',
            'severity'       => 'high',
            'alert_date'     => $date,
            'expected_value' => sprintf('%.1f deaths/day', $avg),
            'actual_value'   => "{$deaths} deaths",
            'deviation_pct'  => $dev === null ? null : round($dev, 2),
            'description'    => "{$deaths} deaths on {$date} {$vs}.{$bandText}",
        ]];
    }
}
