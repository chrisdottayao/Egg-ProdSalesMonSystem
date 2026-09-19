<?php

namespace App\Console\Commands;

use App\Models\EggProduction;
use App\Models\WeatherDaily;
use App\Services\OpenMeteoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WeatherBackfill extends Command
{
    protected $signature   = 'weather:backfill {--start=} {--end=}';
    protected $description = 'One-time backfill of historical weather (temp/humidity/rain/THI) from Open-Meteo archive';

    // The archive lags behind today by roughly this many days — dates newer
    // than that aren't reliably available yet and belong to weather:sync instead.
    private const ARCHIVE_LAG_DAYS = 5;

    public function handle(OpenMeteoService $service): int
    {
        $start = $this->option('start')
            ? Carbon::parse($this->option('start'))
            : Carbon::parse(EggProduction::min('date') ?? Carbon::today()->subYear()->format('Y-m-d'));

        $end = $this->option('end')
            ? Carbon::parse($this->option('end'))
            : Carbon::today()->subDays(self::ARCHIVE_LAG_DAYS);

        if ($start->gt($end)) {
            $this->warn("Start date ({$start->format('Y-m-d')}) is after end date ({$end->format('Y-m-d')}) — nothing to backfill.");
            return self::SUCCESS;
        }

        $this->info("Backfilling weather from {$start->format('Y-m-d')} to {$end->format('Y-m-d')}...");

        // One API call per calendar year keeps each response small and avoids
        // upstream timeouts on Railway for multi-year ranges.
        $chunkStart = $start->copy();
        while ($chunkStart->lte($end)) {
            $chunkEnd = $chunkStart->copy()->endOfYear()->min($end);

            $rows = $service->fetchArchive($chunkStart->format('Y-m-d'), $chunkEnd->format('Y-m-d'));

            $written = 0;
            DB::transaction(function () use ($rows, &$written) {
                foreach ($rows as $row) {
                    $thi = WeatherDaily::computeThi($row['temp_mean'], $row['humidity_mean']);

                    WeatherDaily::updateOrCreate(
                        ['date' => $row['date']],
                        [
                            'temp_max'      => $row['temp_max'],
                            'temp_min'      => $row['temp_min'],
                            'temp_mean'     => $row['temp_mean'],
                            'humidity_mean' => $row['humidity_mean'],
                            'precipitation' => $row['precipitation'],
                            'thi'           => $thi,
                            'source'        => 'archive',
                        ]
                    );
                    $written++;
                }
            });

            $this->line("  {$chunkStart->format('Y-m-d')} to {$chunkEnd->format('Y-m-d')}: {$written} day(s) fetched and written.");

            $chunkStart = $chunkEnd->copy()->addDay();
        }

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
