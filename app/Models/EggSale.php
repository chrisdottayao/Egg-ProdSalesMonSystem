<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EggSale extends Model
{
    protected $fillable = [
        'date', 'egg_size', 'quantity', 'price_per_unit', 'total_amount', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'price_per_unit' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function getRemainingEggsAttribute(): ?int
    {
        $produced = EggProduction::whereDate('date', $this->date)->value('eggs_collected');
        if ($produced === null) return null;
        $sold = self::whereDate('date', $this->date)->sum('quantity');
        return $produced - $sold;
    }

    public function getSalesRateAttribute(): ?float
    {
        $produced = EggProduction::whereDate('date', $this->date)->value('eggs_collected');
        if (!$produced) return null;
        return round(($this->quantity / $produced) * 100, 1);
    }
}
