<?php

namespace App\Support;

/**
 * Seasonal risk note for Philippine layer farms, from the agri expert's
 * month-by-month guide. Display-only: a pure function of the calendar month.
 */
class SeasonalRisk
{
    /** @return array{level: string, color: string, note: string} */
    public static function forMonth(int $month): array
    {
        return match (true) {
            $month >= 3 && $month <= 5 => [
                'level' => 'Higher heat-related risk',
                'color' => 'red',
                'note'  => 'Hot season: higher risk of heat stress, dehydration, reduced feed intake and poor ventilation.',
            ],
            $month >= 6 && $month <= 9 => [
                'level' => 'Higher disease and environmental risk',
                'color' => 'orange',
                'note'  => 'Wet season: higher humidity, wet litter and ventilation problems; bacterial disease pressure, possible AI/ND exposure.',
            ],
            default => [
                'level' => 'Generally lower environmental risk',
                'color' => 'green',
                'note'  => 'Cooler months: generally lower environmental risk, but disease outbreaks can occur at any time.',
            ],
        };
    }
}
