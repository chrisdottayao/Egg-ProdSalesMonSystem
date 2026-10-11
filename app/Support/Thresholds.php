<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Phase 3 items 4–5 (S1, S2, S23): every threshold the system decides with,
 * in one place. The effective value is an admin override from Settings
 * (key "threshold_{key}") or the default in config/dashboard.php. Each also
 * has an editable "basis" note saying where the number comes from.
 */
class Thresholds
{
    /**
     * key => [default, label, unit, group, default basis]. The app reads
     * value defaults from config/dashboard.php (same key); 'default' here is
     * the same number, used only when no app is booted (plain unit tests).
     */
    public const DEFINITIONS = [
        'prod_rate_healthy_min' => ['default' => 80.0, 'label' => 'Healthy production rate (at or above)', 'unit' => '%', 'group' => 'Production rate bands',
            'basis' => 'Based on breeder performance guides for white-egg layers (high-production range).'],
        'prod_rate_cull_max' => ['default' => 50.0, 'label' => 'Cull-consideration production rate (at or below)', 'unit' => '%', 'group' => 'Production rate bands',
            'basis' => 'Farm-defined cull line. Not taken from a published standard.'],

        'mortality_band_elevated' => ['default' => 0.1, 'label' => 'Elevated weekly mortality (above)', 'unit' => '% of flock / 7 days', 'group' => 'Mortality bands',
            'basis' => 'Consulted poultry/agri expert\'s written guidance for DEKALB White layers; a farm decision framework, not an official breeder threshold.'],
        'mortality_band_abnormal' => ['default' => 0.2, 'label' => 'Abnormal weekly mortality (above)', 'unit' => '% of flock / 7 days', 'group' => 'Mortality bands',
            'basis' => 'Consulted poultry/agri expert\'s written guidance; not an official breeder threshold.'],
        'mortality_band_high' => ['default' => 0.5, 'label' => 'Highly abnormal weekly mortality (above)', 'unit' => '% of flock / 7 days', 'group' => 'Mortality bands',
            'basis' => 'Consulted poultry/agri expert\'s written guidance; not an official breeder threshold.'],
        'mortality_band_severe' => ['default' => 1.0, 'label' => 'Severe mortality event (above)', 'unit' => '% of flock / 7 days', 'group' => 'Mortality bands',
            'basis' => 'Consulted poultry/agri expert\'s written guidance; not an official breeder threshold.'],
        'mortality_band_extreme' => ['default' => 3.0, 'label' => 'Extreme, outbreak-level (at or above)', 'unit' => '% of flock / 7 days', 'group' => 'Mortality bands',
            'basis' => 'Consulted poultry/agri expert\'s written guidance; not an official breeder threshold.'],

        'anomaly_prod_drop_pct' => ['default' => 20.0, 'label' => 'Production Drop alert: below the 7-day average by', 'unit' => '%', 'group' => 'Anomaly alerts',
            'basis' => 'System default chosen to flag clear drops while ignoring normal day-to-day variation. Not from a published standard.'],
        'anomaly_prod_spike_pct' => ['default' => 25.0, 'label' => 'Production Spike alert: above the 7-day average by', 'unit' => '%', 'group' => 'Anomaly alerts',
            'basis' => 'System default; an unusually high day is more often an entry mistake than real production. Not from a published standard.'],
        'anomaly_mortality_spike_pct' => ['default' => 100.0, 'label' => 'Mortality Spike alert: above the 7-day average by', 'unit' => '%', 'group' => 'Anomaly alerts',
            'basis' => 'System default (100% = double the usual daily deaths). Not from a published standard.'],
        'anomaly_mortality_spike_min' => ['default' => 3.0, 'label' => 'Mortality Spike alert: minimum deaths that day', 'unit' => 'birds', 'group' => 'Anomaly alerts',
            'basis' => 'System default so that very small numbers (e.g. 0 to 1 death) do not raise alerts.'],
    ];

    public static function get(string $key): float
    {
        self::assertKnown($key);
        if (! self::appBooted()) {
            return (float) self::DEFINITIONS[$key]['default'];
        }

        return (float) Setting::get('threshold_' . $key, self::default($key));
    }

    public static function default(string $key): float
    {
        self::assertKnown($key);

        return (float) (self::appBooted() ? config('dashboard.' . $key, self::DEFINITIONS[$key]['default']) : self::DEFINITIONS[$key]['default']);
    }

    private static function appBooted(): bool
    {
        return function_exists('app') && app()->bound('config');
    }

    public static function basis(string $key): string
    {
        self::assertKnown($key);
        if (! self::appBooted()) {
            return self::DEFINITIONS[$key]['basis'];
        }

        return (string) Setting::get('threshold_basis_' . $key, self::DEFINITIONS[$key]['basis']);
    }

    /** @return array<string, float> every threshold's effective value */
    public static function all(): array
    {
        return collect(array_keys(self::DEFINITIONS))->mapWithKeys(fn ($k) => [$k => self::get($k)])->all();
    }

    private static function assertKnown(string $key): void
    {
        if (! isset(self::DEFINITIONS[$key])) {
            throw new \InvalidArgumentException("Unknown threshold: {$key}");
        }
    }
}
