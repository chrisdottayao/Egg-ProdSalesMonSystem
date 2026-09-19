<?php

return [

    // Production rate = eggs collected ÷ hen population × 100 (hen-day %).
    // Confirmed by the farm: >=80% healthy, the farm's own rule is to
    // consider culling a building around 50%. Editable pending confirmation
    // against the Animal Science expert's production-quality guidance.
    'prod_rate_healthy_min' => 80.0, // >= this => Healthy
    'prod_rate_cull_max'    => 50.0, // <= this => Cull-consideration; between the two => Watch

    'prod_rate_bands' => [
        'healthy' => ['label' => 'Healthy',            'color' => 'green'],
        'watch'   => ['label' => 'Watch',               'color' => 'amber'],
        'cull'    => ['label' => 'Cull-consideration',  'color' => 'red'],
    ],

];
