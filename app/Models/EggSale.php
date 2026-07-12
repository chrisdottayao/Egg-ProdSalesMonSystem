<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggSale extends Model
{
    protected $fillable = [
        'date', 'egg_size', 'quantity', 'price_per_unit', 'total_amount', 'notes', 'production_id',
    ];

    protected $casts = [
        'date' => 'date',
        'price_per_unit' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function eggProduction(): BelongsTo
    {
        return $this->belongsTo(EggProduction::class, 'production_id');
    }

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

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->production_id) {
                $prod = EggProduction::whereDate('date', $model->date)
                    ->where('egg_size', $model->egg_size)
                    ->first();
                if ($prod) {
                    $model->production_id = $prod->id;
                }
            }
        });

        static::created(function ($model) {
            self::logAudit('create', $model);
        });

        static::updating(function ($model) {
            if ($model->isDirty(['date', 'egg_size'])) {
                $prod = EggProduction::whereDate('date', $model->date)
                    ->where('egg_size', $model->egg_size)
                    ->first();
                $model->production_id = $prod ? $prod->id : null;
            }
        });

        static::updated(function ($model) {
            self::logAudit('update', $model);
        });

        static::deleted(function ($model) {
            self::logAudit('delete', $model);
        });
    }

    protected static function logAudit(string $action, EggSale $model)
    {
        $flagged = false;
        $rule = null;

        // Rule: Sales vs. Production
        $produced = EggProduction::whereDate('date', $model->date)->sum('eggs_collected');
        $totalSoldOnDate = EggSale::whereDate('date', $model->date)->sum('quantity');
        if ($produced > 0 && $totalSoldOnDate > $produced) {
            $flagged = true;
            $rule = 'Sales vs. Production';
        }

        // Rule: Duplicate Sales Entry
        $duplicates = EggSale::whereDate('date', $model->date)
            ->where('egg_size', $model->egg_size)
            ->where('id', '!=', $model->id)
            ->exists();
        if ($duplicates) {
            $flagged = true;
            $rule = 'Duplicate Sales Entry';
        }

        // Rule: Batch Mismatch
        if ($model->production_id) {
            $prod = $model->eggProduction;
            if ($prod && ($model->quantity > $prod->eggs_collected)) {
                $flagged = true;
                $rule = 'Batch Mismatch';
            }
        }

        // Rule: Revenue Anomaly
        if ($model->quantity > 0 && $model->price_per_unit <= 0) {
            $flagged = true;
            $rule = 'Revenue Anomaly';
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'EggSale',
            'model_id' => $model->id,
            'details' => [
                'date' => $model->date->format('Y-m-d'),
                'egg_size' => $model->egg_size,
                'quantity' => $model->quantity,
                'price_per_unit' => $model->price_per_unit,
                'total_amount' => $model->total_amount,
            ],
            'inconsistency_flagged' => $flagged,
            'inconsistency_rule' => $rule,
        ]);
    }
}


