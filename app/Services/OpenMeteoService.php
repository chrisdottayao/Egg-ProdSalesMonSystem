<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenMeteoService
{
    private const ARCHIVE_URL  = 'https://archive-api.open-meteo.com/v1/archive';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    private const DAILY_PARAMS = 'temperature_2m_max,temperature_2m_min,temperature_2m_mean,precipitation_sum';

    // Open-Meteo has no daily humidity aggregate — relative_humidity_2m is
    // hourly only, so every caller here averages 24 hourly values into one
    // daily mean itself (see mapResponse()).
    private const HOURLY_PARAMS = 'relative_humidity_2m';

    /**
     * Historical reanalysis data. The archive lags ~5 days behind today —
     * callers should only request dates older than that.
     *
     * @return array<int, array{date: string, temp_max: ?float, temp_min: ?float, temp_mean: ?float, precipitation: ?float, humidity_mean: ?float}>
     */
    public function fetchArchive(string $start, string $end): array
    {
        return $this->fetch(self::ARCHIVE_URL, [
            'start_date' => $start,
            'end_date'   => $end,
        ]);
    }

    /**
     * Recent past (14 days) + short-range forecast (16 days ahead) — covers
     * the archive's 5-day gap plus a real forecast window. Beyond ~16 days
     * Open-Meteo has no forecast; long-range projections must fall back to
     * calendar-month climatology (see ForecastService).
     *
     * @return array<int, array{date: string, temp_max: ?float, temp_min: ?float, temp_mean: ?float, precipitation: ?float, humidity_mean: ?float}>
     */
    public function fetchRecent(): array
    {
        return $this->fetch(self::FORECAST_URL, [
            'past_days'     => 14,
            'forecast_days' => 16,
        ]);
    }

    /**
     * A weather fetch failing must never break anything else that depends on
     * this service, so failures are logged and swallowed into an empty result
     * rather than thrown.
     */
    private function fetch(string $url, array $extraParams): array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->get($url, array_merge([
                    'latitude'  => config('weather.latitude'),
                    'longitude' => config('weather.longitude'),
                    'daily'     => self::DAILY_PARAMS,
                    'hourly'    => self::HOURLY_PARAMS,
                    'timezone'  => config('weather.timezone'),
                ], $extraParams));

            if (! $response->successful()) {
                Log::warning('OpenMeteoService: non-successful response', [
                    'url'    => $url,
                    'status' => $response->status(),
                ]);
                return [];
            }

            return $this->mapResponse($response->json() ?? []);
        } catch (\Throwable $e) {
            Log::warning('OpenMeteoService: fetch failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function mapResponse(array $json): array
    {
        $daily = $json['daily'] ?? [];
        $dates = $daily['time'] ?? [];

        $tempMax  = $daily['temperature_2m_max'] ?? [];
        $tempMin  = $daily['temperature_2m_min'] ?? [];
        $tempMean = $daily['temperature_2m_mean'] ?? [];
        $precip   = $daily['precipitation_sum'] ?? [];

        $humidityMeanByDate = $this->averageHourlyHumidityByDate($json['hourly'] ?? []);

        $rows = [];
        foreach ($dates as $i => $date) {
            $rows[] = [
                'date'          => $date,
                'temp_max'      => $tempMax[$i] ?? null,
                'temp_min'      => $tempMin[$i] ?? null,
                'temp_mean'     => $tempMean[$i] ?? null,
                'precipitation' => $precip[$i] ?? null,
                'humidity_mean' => $humidityMeanByDate[$date] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Null-safe average: a day with only partial hourly coverage still gets a
     * mean from whatever hours are present, and a day with zero hours simply
     * has no key in the returned map.
     *
     * @return array<string, float>
     */
    private function averageHourlyHumidityByDate(array $hourly): array
    {
        $times  = $hourly['time'] ?? [];
        $values = $hourly['relative_humidity_2m'] ?? [];

        $byDate = [];
        foreach ($times as $i => $time) {
            $rh = $values[$i] ?? null;
            if ($rh === null) {
                continue;
            }
            $byDate[substr($time, 0, 10)][] = (float) $rh;
        }

        return array_map(fn (array $vals) => array_sum($vals) / count($vals), $byDate);
    }
}
