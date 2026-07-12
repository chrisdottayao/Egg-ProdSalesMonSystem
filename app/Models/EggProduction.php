<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EggProduction extends Model
{
    protected $fillable = [
        'date',
        'eggs_collected',
        'active_hens',
        'egg_size',
        'egg_weight',
        'mortality',
        'notes',
        'spoilage_count',
        'spoilage_reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function getProductionRateAttribute(): float
    {
        if (!$this->active_hens) return 0;
        return round(($this->eggs_collected / $this->active_hens) * 100, 1);
    }

    public function eggSales(): HasMany
    {
        return $this->hasMany(EggSale::class, 'production_id');
    }

    public function getRemainingStockAttribute(): int
    {
        $sold = $this->eggSales()->sum('quantity');
        return max(0, $this->eggs_collected - $sold - $this->spoilage_count);
    }

    public function getQuantitySoldAttribute(): int
    {
        return $this->eggSales()->sum('quantity');
    }

    public function getSellThroughRateAttribute(): float
    {
        if ($this->eggs_collected <= 0) return 0;
        return round(($this->quantity_sold / $this->eggs_collected) * 100, 1);
    }

    public function getBatchStatusAttribute(): string
    {
        $remaining = $this->remaining_stock;
        
        if ($remaining <= 0) {
            return 'Fully Sold';
        }
        
        if ($this->spoilage_count > 0 && $remaining == 0) {
            return 'Spoiled';
        }
        
        if ($this->quantity_sold > 0) {
            return 'Partially Sold';
        }
        
        return 'Active';
    }

    protected static function booted()
    {
        static::created(function ($model) {
            self::logAudit('create', $model);
        });

        static::updated(function ($model) {
            self::logAudit('update', $model);
        });

        static::deleted(function ($model) {
            self::logAudit('delete', $model);
        });
    }

    protected static function logAudit(string $action, EggProduction $model)
    {
        $flagged = false;
        $rule = null;

        if (!$model->active_hens) {
            $flagged = true;
            $rule = 'Missing Hen Count';
        }

        if ($model->mortality > 0) {
            $prev = EggProduction::where('date', '<', $model->date)
                ->orderBy('date', 'desc')
                ->first();
            if ($prev && $prev->active_hens == $model->active_hens) {
                $flagged = true;
                $rule = 'Mortality without Hen Count Update';
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'EggProduction',
            'model_id' => $model->id,
            'details' => [
                'date' => $model->date->format('Y-m-d'),
                'egg_size' => $model->egg_size,
                'eggs_collected' => $model->eggs_collected,
                'active_hens' => $model->active_hens,
                'mortality' => $model->mortality,
                'spoilage_count' => $model->spoilage_count,
            ],
            'inconsistency_flagged' => $flagged,
            'inconsistency_rule' => $rule,
        ]);
    }
}


