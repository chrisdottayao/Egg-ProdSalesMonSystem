<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EggProduction extends Model
{
    protected $fillable = [
        'date', 'eggs_collected', 'active_hens', 'egg_size', 'egg_weight', 'mortality', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function getProductionRateAttribute(): float
    {
        if (!$this->active_hens) return 0;
        return round(($this->eggs_collected / $this->active_hens) * 100, 1);
    }
}
