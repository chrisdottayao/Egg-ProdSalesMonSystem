<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastEvaluation extends Model
{
    protected $fillable = [
        'trained_on', 'mape', 'forecast_7day_total', 'forecast_30day_total', 'evaluated_at',
    ];

    protected $casts = [
        'evaluated_at'         => 'datetime',
        'mape'                 => 'decimal:4',
        'forecast_30day_total' => 'decimal:2',
    ];
}
