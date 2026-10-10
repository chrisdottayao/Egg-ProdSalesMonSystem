<?php

// S12/S13 — default selling price per piece for each egg size, used until an
// admin sets the real price on the Settings screen (stored as
// settings.egg_price_{size}). PLACEHOLDER: the sales form's old fixed default
// was 9.00 for every size; replace these with the farm's actual prices.
// Changing a price only affects what NEW sales default to — saved sales keep
// the price they were recorded with.
return [

    'defaults' => [
        'Peewee'    => 9.00,
        'Small'     => 9.00,
        'Medium'    => 9.00,
        'Large'     => 9.00,
        'XL'        => 9.00,
        'Jumbo'     => 9.00,
        'No Value'  => 9.00,
        'No Weight' => 9.00,
        'Dirty'     => 9.00,
        'Broken'    => 9.00,
        'Waste'     => 9.00,
    ],

];
