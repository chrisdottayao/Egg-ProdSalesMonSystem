<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherDaily extends Model
{
    protected $table = 'weather_daily';

    protected $fillable = [
        'date',
        'temp_max',
        'temp_min',
        'temp_mean',
        'humidity_mean',
        'precipitation',
        'thi',
        'source',
    ];

    protected $casts = [
        'date'          => 'date',
        'temp_max'      => 'float',
        'temp_min'      => 'float',
        'temp_mean'     => 'float',
        'humidity_mean' => 'float',
        'precipitation' => 'float',
        'thi'           => 'float',
    ];

    /**
     * THI = (1.8T + 32) - (0.55 - 0.0055*RH) * (1.8T - 26), T in °C, RH in %.
     * Null propagates if either input is missing — never invent a THI.
     */
    public static function computeThi(?float $tempMean, ?float $humidityMean): ?float
    {
        if ($tempMean === null || $humidityMean === null) {
            return null;
        }

        $thi = (1.8 * $tempMean + 32) - (0.55 - 0.0055 * $humidityMean) * (1.8 * $tempMean - 26);

        return round($thi, 2);
    }

    /**
     * Comfort band for a THI value, per config/weather.php's thi_bands
     * (PLACEHOLDER thresholds — see that file's comment).
     *
     * @return array{label: string, color: string}|null
     */
    public static function band(?float $thi): ?array
    {
        if ($thi === null) {
            return null;
        }

        foreach (config('weather.thi_bands') as $band) {
            if ($band['max'] === null || $thi < $band['max']) {
                return ['label' => $band['label'], 'color' => $band['color']];
            }
        }

        return null;
    }
}
