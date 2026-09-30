<?php

namespace App\Support;

/**
 * Weekly flock-mortality severity bands for DEKALB White layers, as given in
 * the consulted poultry/agri expert's written guidance. These are the expert's
 * farm-decision framework, not official breeder thresholds.
 *
 * Used only as a LABEL on mortality alerts. The alert trigger itself (7-day vs
 * 30-day rolling comparison in RecommendationService) is unchanged.
 */
class MortalityBands
{
    /**
     * @param  float|null  $weeklyPct  Deaths in the last 7 days as a % of the flock.
     * @return array{label: string, action: string}|null  Null when the % cannot be computed.
     */
    public static function forWeeklyPct(?float $weeklyPct): ?array
    {
        if ($weeklyPct === null || $weeklyPct < 0) {
            return null;
        }

        return match (true) {
            $weeklyPct >= 3.0 => [
                'label'  => 'extreme, outbreak-level signal',
                'action' => 'treat as an emergency and report according to veterinary regulations',
            ],
            $weeklyPct > 1.0 => [
                'label'  => 'severe mortality event',
                'action' => 'urgent veterinary/disease investigation',
            ],
            $weeklyPct > 0.5 => [
                'label'  => 'highly abnormal',
                'action' => 'veterinary investigation and diagnostic work-up',
            ],
            $weeklyPct > 0.2 => [
                'label'  => 'abnormal',
                'action' => 'investigate immediately; necropsy representative birds',
            ],
            $weeklyPct > 0.1 => [
                'label'  => 'elevated',
                'action' => 'check causes, mortality pattern, feed/water, environment',
            ],
            default => [
                'label'  => 'routine background level',
                'action' => 'routine monitoring',
            ],
        };
    }

    /** Convenience: band for raw death count over a flock size. Null if flock size is unknown. */
    public static function forDeaths(float|int $deaths, float|int|null $flockSize): ?array
    {
        if ($flockSize === null || $flockSize <= 0) {
            return null;
        }

        return self::forWeeklyPct(($deaths / $flockSize) * 100);
    }

    /** Band labels at or above "abnormal" (weekly mortality above 0.20%). */
    private const WATCHLIST_LABELS = [
        'abnormal',
        'highly abnormal',
        'severe mortality event',
        'extreme, outbreak-level signal',
    ];

    /**
     * Expert early-warning signs and Philippine disease watch list, shown only
     * when the band is abnormal or worse. Empty string otherwise. Wording only.
     */
    public static function watchlist(?array $band): string
    {
        if ($band === null || ! in_array($band['label'], self::WATCHLIST_LABELS, true)) {
            return '';
        }

        return ' Check for: feed intake 2-5% below normal, abnormal water intake, egg production down 3-5% or more,'
            . ' smaller eggs, weak shells, quiet or panting birds, changed droppings.'
            . ' Philippine surveillance list: Newcastle disease, avian influenza, infectious bronchitis,'
            . ' fowl typhoid/Salmonella, E. coli, Mycoplasma.';
    }
}
