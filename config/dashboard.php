<?php

// NOTE (Prototype 3M): the farm has 45 real buildings, but the live system
// currently actively records/displays only 3 of them (hen_batches.is_tracked)
// per the IT expert's scope-reduction recommendation. The bands below apply
// to whichever buildings are tracked, not a fixed count — nothing here
// hardcodes "45," this note just exists so that mismatch isn't confusing.

return [

    // Production rate = eggs collected ÷ hen population × 100 (hen-day %).
    // Confirmed by the farm: >=80% healthy, the farm's own rule is to
    // consider culling a building around 50%. Editable pending confirmation
    // against the Animal Science expert's production-quality guidance.
    'prod_rate_healthy_min' => 80.0, // >= this => Healthy
    'prod_rate_cull_max'    => 50.0, // <= this => Cull-consideration; between the two => Watch

    // Phase 3 item 4 (S23) — anomaly detectors on the dashboard. Each compares
    // a day against the trailing 7 days of approved records and needs all 7
    // of those days recorded. Editable in Settings > Thresholds & Alert Rules.
    'anomaly_prod_drop_pct'        => 20.0,  // day's prod rate this % below the 7-day average
    'anomaly_prod_spike_pct'       => 25.0,  // day's prod rate this % above the 7-day average
    'anomaly_mortality_spike_pct'  => 100.0, // day's deaths more than this % above the 7-day average (100 = more than double)
    'anomaly_mortality_spike_min'  => 3,     // and at least this many deaths that day (ignores 0 -> 1 jumps)

    // Weekly mortality bands (% of flock dying in 7 days) — lower bounds of
    // the expert's elevated / abnormal / highly abnormal / severe / extreme
    // levels used by App\Support\MortalityBands. Editable in Settings.
    'mortality_band_elevated'  => 0.1,
    'mortality_band_abnormal'  => 0.2,
    'mortality_band_high'      => 0.5,
    'mortality_band_severe'    => 1.0,
    'mortality_band_extreme'   => 3.0,

    'prod_rate_bands' => [
        'healthy' => ['label' => 'Healthy',            'color' => 'green'],
        'watch'   => ['label' => 'Watch',               'color' => 'amber'],
        'cull'    => ['label' => 'Cull-consideration',  'color' => 'red'],
    ],

];
