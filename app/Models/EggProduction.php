<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EggProduction extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'eggs_collected',
        'active_hens',
        'egg_size',
        'egg_weight',
        'mortality',
        'notes',
        'spoilage_count',
        'spoilage_reason',
        'feed_bags',
        'feed_kg_per_bag',
        'feed_cost_per_bag',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProductionRateAttribute(): float
    {
        if (!$this->active_hens) return 0;
        return round(($this->eggs_collected / $this->active_hens) * 100, 1);
    }

    public function getFeedGramsPerBirdAttribute(): ?float
    {
        if ($this->feed_bags === null || $this->feed_kg_per_bag === null || !$this->active_hens) {
            return null;
        }

        return ($this->feed_bags * $this->feed_kg_per_bag * 1000) / $this->active_hens;
    }

    public function eggSales(): HasMany
    {
        return $this->hasMany(EggSale::class, 'production_id');
    }

    /**
     * Sold quantity for this production row's date — summed across ALL
     * egg_sales rows for that date (all 11 categories), not via the
     * eggSales() relation above. production_id only ever links a sale to
     * ONE production row when their egg_size happens to match, but a day's
     * production is a single total spanning every size — the relation would
     * silently undercount to whichever one size happened to link, which is
     * exactly the Batch Traceability bug this fixes.
     */
    public function getQuantitySoldAttribute(): int
    {
        return (int) EggSale::whereDate('date', $this->date)->sum('quantity');
    }

    public function getRemainingStockAttribute(): int
    {
        return max(0, $this->eggs_collected - $this->quantity_sold - ($this->spoilage_count ?? 0));
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


