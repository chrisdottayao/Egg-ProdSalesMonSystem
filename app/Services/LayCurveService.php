<?php

namespace App\Services;

class LayCurveService
{
    /**
     * PLACEHOLDER lay curve — Dekalb White, sketched from general breed
     * standard (near 0% before 19wk, peak ~90-93% at 26-30wk, declining
     * ~0.3-0.5 pts/wk to ~65% by 100wk, ~55-58% by 140wk).
     *
     * TODO: calibrate — this is not fitted to this farm's real production
     * history. Replace with a curve fitted from this flock's own age/rate
     * data once 12+ months are available across enough hen-batches to
     * cover the full age range.
     *
     * [age_weeks => expected_lay_rate_pct]
     */
    private const CONTROL_POINTS = [
        0   => 0,
        18  => 0,
        20  => 15,
        22  => 45,
        24  => 70,
        26  => 88,
        28  => 92,
        30  => 93,
        40  => 90,
        50  => 86,
        60  => 83,
        70  => 80,
        80  => 76,
        90  => 71,
        100 => 65,
        110 => 62,
        120 => 60,
        130 => 58,
        140 => 56,
        150 => 55,
    ];

    /**
     * Expected lay rate (%) for a flock at the given age in weeks, linearly
     * interpolated between the nearest control points. Clamped flat beyond
     * either end of the table.
     */
    public function expectedRate(float $ageWeeks): float
    {
        $points = self::CONTROL_POINTS;
        $ages   = array_keys($points);

        if ($ageWeeks <= $ages[0]) {
            return $points[$ages[0]];
        }

        $lastAge = $ages[count($ages) - 1];
        if ($ageWeeks >= $lastAge) {
            return $points[$lastAge];
        }

        foreach ($ages as $i => $age) {
            if ($ageWeeks === $age) {
                return $points[$age];
            }
            if ($ageWeeks < $age) {
                $prevAge  = $ages[$i - 1];
                $prevRate = $points[$prevAge];
                $rate     = $points[$age];
                $fraction = ($ageWeeks - $prevAge) / ($age - $prevAge);

                return $prevRate + ($rate - $prevRate) * $fraction;
            }
        }

        // Unreachable given the clamps above.
        return $points[$lastAge];
    }
}
