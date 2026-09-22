<?php

namespace App\Console\Commands;

use App\Models\BuildingDaily;
use App\Services\ProductionRollupService;
use Illuminate\Console\Command;

class RerollProductionHistory extends Command
{
    protected $signature   = 'production:reroll-history';
    protected $description = 'One-off (3N): re-run the egg_productions rollup for every distinct building_daily date, now scoped to tracked buildings only';

    public function handle(ProductionRollupService $rollup): int
    {
        $dates = BuildingDaily::select('date')->distinct()->pluck('date');

        if ($dates->isEmpty()) {
            $this->warn('No building_daily rows found — nothing to re-roll.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($dates->count());
        $bar->start();

        foreach ($dates as $date) {
            $rollup->rollupDate($date);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Re-rolled {$dates->count()} date(s) — egg_productions now reflects tracked buildings only for all history.");

        return self::SUCCESS;
    }
}
