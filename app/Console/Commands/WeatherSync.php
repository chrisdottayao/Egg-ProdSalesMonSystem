<?php

namespace App\Console\Commands;

use App\Models\WeatherDaily;
use App\Services\OpenMeteoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WeatherSync extends Command
{
    protected $signature   = 'weather:sync';
    protected $description = 'Sync the last 14 days + upcoming ~16 days of weather from Open-Meteo';

    public function handle(OpenMeteoService $service): int
    {
        $rows = $service->fetchRecent();

        if (empty($rows)) {
            $this->warn('No weather data returned — leaving weather_daily unchanged.');
            return self::SUCCESS;
        }

        $today   = Carbon::today();
        $written = 0;

        DB::transaction(function () use ($rows, $today, &$written) {
            foreach ($rows as $row) {
                $thi  = WeatherDaily::computeThi($row['temp_mean'], $row['humidity_mean']);
                $date = Carbon::parse($row['date']);

                // Dates still in the future are a forecast, not an observation.
                // A later run of weather:backfill (or a future weather:sync, once
                // that date has passed) overwrites the row with source='archive'.
                $source = $date->gt($today) ? 'forecast' : 'archive';

                WeatherDaily::updateOrCreate(
                    ['date' => $row['date']],
                    [
                        'temp_max'      => $row['temp_max'],
                        'temp_min'      => $row['temp_min'],
                        'temp_mean'     => $row['temp_mean'],
                        'humidity_mean' => $row['humidity_mean'],
                        'precipitation' => $row['precipitation'],
                        'thi'           => $thi,
                        'source'        => $source,
                    ]
                );
                $written++;
            }
        });

        $this->info("Weather sync complete: {$written} day(s) written.");

        return self::SUCCESS;
    }
}
