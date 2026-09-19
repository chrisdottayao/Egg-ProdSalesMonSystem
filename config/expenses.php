<?php

return [

    // Feed is bought in bags; this is the default kg/bag used to compute
    // kg_total when a specific entry doesn't override it.
    'default_kg_per_bag' => 50,

    // Confirmed by the farm — layer houses use ACE FEEDS layer mash. The
    // farm's younger-bird feeds (chick booster/starter, grower pellets) apply
    // only to the grower houses and are out of scope for laying-house expense.
    'default_feed_brand' => 'ACE FEEDS',

    // ESTIMATE — feed comes through the owner's partner (kasosyo); neither the
    // managers nor the production manager know the actual price. This is a
    // market-reference placeholder standing in for a real figure, not a
    // temporary stopgap — it's meant to be edited (Settings screen) the
    // moment the owner confirms one, at which point every dependent figure
    // uses the new value going forward (already-saved expense rows, each a
    // record of what was entered at the time, are untouched).
    'feed_price_per_bag_estimate' => 1500,

    // ESTIMATE — the farm rears its own birds from chicks (chick booster →
    // starter → grower → layer mash), so true restocking cost is closer to
    // day-old chick cost + rearing feed than a finished-pullet purchase
    // price. Market-reference placeholder; owner-only figure pending
    // confirmation.
    'pullet_cost_per_head_estimate' => 320,

];
