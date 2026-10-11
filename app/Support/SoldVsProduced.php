<?php

namespace App\Support;

use App\Models\EggProduction;
use App\Models\EggSale;

/**
 * Phase 3 item 3 (S24): is a sale about to push the day's eggs sold above
 * the eggs produced? Not a hard block — carried-over stock can make it
 * legitimate — but the user must confirm and the entry is flagged.
 *
 * Both sides are in pieces. Production is the approved daily rollup
 * (egg_productions.eggs_collected, which comes from building eggs_house;
 * egg grading's cases/trays use the same 1 tray = 30 pcs, 1 case = 360 pcs).
 * Sold counts every sale recorded for the date that isn't rejected
 * (approved or still pending), plus this entry.
 */
class SoldVsProduced
{
    public const RULE = 'sold_gt_produced';

    /** @return array{date: string, produced: int, sold_before: int, this_entry: int, sold_total: int}|null */
    public static function check(string $date, int $quantity, ?EggSale $replacing = null): ?array
    {
        $produced = (int) EggProduction::whereDate('date', $date)->sum('eggs_collected');
        if ($produced <= 0) {
            return null; // no approved production recorded yet: nothing to compare against
        }

        $soldBefore = (int) EggSale::withPending()
            ->whereDate('date', $date)
            ->where('approval_status', '!=', EggSale::STATUS_REJECTED)
            ->when($replacing, fn ($q) => $q->where('id', '!=', $replacing->id))
            ->sum('quantity');

        $total = $soldBefore + $quantity;
        if ($total <= $produced) {
            return null;
        }

        return ['date' => $date, 'produced' => $produced, 'sold_before' => $soldBefore, 'this_entry' => $quantity, 'sold_total' => $total];
    }

    public static function message(array $c): string
    {
        return sprintf(
            'Sold is higher than produced for %s: %s eggs sold (%s already recorded + %s in this entry) vs %s eggs produced. Continue?',
            $c['date'], number_format($c['sold_total']), number_format($c['sold_before']), number_format($c['this_entry']), number_format($c['produced'])
        );
    }
}
