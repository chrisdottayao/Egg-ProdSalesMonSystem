<?php

namespace App\Support;

use App\Models\EggProduction;
use App\Models\Expense;

/**
 * Phase 3 item 8 (S21): one factual caption under the Production Trend
 * chart. It says what the chart shows and, only when feed expenses were
 * recorded for the same period, the resulting feed cost per egg
 * (feed expenses ÷ eggs produced). Nothing is estimated or invented.
 *
 * OPEN QUESTION: the panel's "cost description" wording is unconfirmed;
 * this is deliberately self-contained so it is easy to change or remove.
 */
class ProductionCostCaption
{
    /** @return array{shows: string, cost: string, has_cost: bool} */
    public static function for(string $start, string $end): array
    {
        $eggs = (int) EggProduction::whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->sum('eggs_collected');
        $feed = (float) Expense::where('category', 'feed')
            ->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->sum('amount');

        $shows = sprintf(
            'Eggs collected per day across tracked buildings (approved entries), %s to %s: %s eggs in total.',
            $start, $end, number_format($eggs)
        );

        if ($feed <= 0) {
            return ['shows' => $shows, 'cost' => 'No feed expense recorded for this period.', 'has_cost' => false];
        }
        if ($eggs <= 0) {
            return ['shows' => $shows, 'cost' => sprintf('Feed expenses recorded: ₱%s, but no eggs were recorded, so no cost per egg can be computed.', number_format($feed, 2)), 'has_cost' => false];
        }

        return [
            'shows'    => $shows,
            'cost'     => sprintf(
                'Feed cost for the period: ₱%s recorded in Feed & Electricity Expenses ÷ %s eggs = ₱%s per egg (feed only; other costs not included).',
                number_format($feed, 2), number_format($eggs), number_format($feed / $eggs, 2)
            ),
            'has_cost' => true,
        ];
    }
}
