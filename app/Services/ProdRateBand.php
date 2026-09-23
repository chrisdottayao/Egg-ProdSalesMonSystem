<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ProdRateBand
{
    /**
     * Band for a production-rate percentage (eggs ÷ population × 100), per
     * config/dashboard.php's farm-confirmed thresholds: >=80% Healthy,
     * <=50% Cull-consideration, otherwise Watch.
     *
     * @return array{label: string, color: string}|null
     */
    public static function resolve(?float $rate): ?array
    {
        if ($rate === null) {
            return null;
        }

        $healthyMin = (float) config('dashboard.prod_rate_healthy_min');
        $cullMax    = (float) config('dashboard.prod_rate_cull_max');
        $bands      = config('dashboard.prod_rate_bands');

        if ($rate >= $healthyMin) {
            return $bands['healthy'];
        }

        if ($rate <= $cullMax) {
            return $bands['cull'];
        }

        return $bands['watch'];
    }

    /**
     * Replaces the usual Healthy/Watch/Cull-consideration badge for a
     * building whose flock has ended (Prototype 3Q) — a prod-rate band no
     * longer applies to a fully depopulated building.
     *
     * @return array{label: string, color: string}
     */
    public static function ended(Carbon $endedAt): array
    {
        return ['label' => 'Culled — ' . $endedAt->format('M d, Y'), 'color' => 'gray'];
    }
}
