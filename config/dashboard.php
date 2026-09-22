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

    'prod_rate_bands' => [
        'healthy' => ['label' => 'Healthy',            'color' => 'green'],
        'watch'   => ['label' => 'Watch',               'color' => 'amber'],
        'cull'    => ['label' => 'Cull-consideration',  'color' => 'red'],
    ],

];
