<?php

namespace App\Services;

use App\Models\BuildingDaily;
use App\Models\EggProduction;
use Illuminate\Support\Carbon;

class ProductionRollupService
{
    /**
     * Recompute the single egg_productions row for a date as a sum of that
     * date's building_daily rows. Only the fields building_daily can derive
     * are overwritten — egg_size, egg_weight, feed_cost_per_bag, spoilage,
     * and notes stay whatever was entered manually.
     */
    public function rollupDate(Carbon|string $date): void
    {
        $date = Carbon::parse($date)->format('Y-m-d');

        $buildings = BuildingDaily::whereDate('date', $date)->get();

        if ($buildings->isEmpty()) {
            return;
        }

        $eggsCollected = (int) $buildings->sum('eggs_house');
        $activeHens    = (int) $buildings->sum('population');
        $mortality     = (int) $buildings->sum('mortality');
        $feedBags      = (float) $buildings->sum('feed_bags');

        $production = EggProduction::whereDate('date', $date)->first();

        if ($production) {
            $production->update([
                'eggs_collected' => $eggsCollected,
                'active_hens'    => $activeHens,
                'mortality'      => $mortality,
                'feed_bags'      => $feedBags,
            ]);
        } else {
            EggProduction::create([
                'date'           => $date,
                'eggs_collected' => $eggsCollected,
                'active_hens'    => $activeHens,
                'mortality'      => $mortality,
                'feed_bags'      => $feedBags,
            ]);
        }
    }
}
