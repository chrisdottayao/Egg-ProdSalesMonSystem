<?php

return [

    // Fixed single-point farm location — SPC Farm Magalang, Pampanga.
    'latitude'  => 15.25031,
    'longitude' => 120.66569,
    'timezone'  => 'Asia/Manila',

    // Toggle for ForecastService: whether temp_mean/thi/precipitation are
    // added as extra forecast features. Flip to false if they don't improve
    // MAPE once measured (see ForecastService::forecast()'s before/after log).
    'use_in_forecast' => true,

    // THI (Temperature-Humidity Index) comfort bands for laying hens.
    // WeatherDaily::computeThi() uses the NRC (1971) livestock formula —
    // (1.8T+32) - (0.55-0.0055*RH)*(1.8T-26) — which, despite taking Celsius
    // input, outputs values on a ~70-90 scale (Fahrenheit conversion is baked
    // into its constants), not the raw ~20-35 Celsius-like scale a THI number
    // might suggest. These cutoffs match that output scale.
    // PLACEHOLDER — these are the commonly cited dairy/poultry heat-stress
    // cutoffs for this exact formula, not calibrated to this farm's flock.
    // Confirm with the Animal Science expert before treating them as more
    // than a rough guide.
    'thi_bands' => [
        ['max' => 74.9, 'label' => 'Comfort',          'color' => 'green'],
        ['max' => 79.0, 'label' => 'Mild stress',       'color' => 'yellow'],
        ['max' => 84.0, 'label' => 'Moderate stress',   'color' => 'orange'],
        ['max' => null, 'label' => 'Severe stress',     'color' => 'red'],
    ],

];
