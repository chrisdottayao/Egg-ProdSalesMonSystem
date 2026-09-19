<?php

namespace App\Services;

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
}
