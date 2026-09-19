<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    // building_id references hen_batches.id — a "building" in this system is
    // a HenBatch (see building_daily.hen_batch_id); there is no standalone
    // buildings table.
    protected $fillable = [
        'building_id', 'date', 'category', 'description',
        'quantity', 'unit', 'unit_price', 'amount', 'kg_total',
        'supplier', 'is_estimated', 'is_recurring', 'recurrence',
        'receipt_path', 'notes',
    ];

    protected $casts = [
        'date'         => 'date',
        'quantity'     => 'float',
        'unit_price'   => 'float',
        'amount'       => 'float',
        'kg_total'     => 'float',
        'is_estimated' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    public const CATEGORIES = [
        'feed'         => ['label' => 'Feed',              'color' => 'green'],
        'vaccine'      => ['label' => 'Vaccine',            'color' => 'blue'],
        'vitamins'     => ['label' => 'Vitamins',           'color' => 'purple'],
        'medicine'     => ['label' => 'Medicine',           'color' => 'pink'],
        'restocking'   => ['label' => 'Restocking',         'color' => 'orange'],
        'electricity'  => ['label' => 'Electricity',        'color' => 'yellow'],
        'manpower'     => ['label' => 'Manpower',           'color' => 'indigo'],
        'other'        => ['label' => 'Other',              'color' => 'gray'],
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(HenBatch::class, 'building_id');
    }

    public function scopeFarmWide(Builder $query): Builder
    {
        return $query->whereNull('building_id');
    }

    public function scopeForBuilding(Builder $query, int $buildingId): Builder
    {
        return $query->where('building_id', $buildingId);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeBetweenDates(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function getEstimatedBadgeAttribute(): bool
    {
        return (bool) $this->is_estimated;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? ucfirst($this->category);
    }

    public function getCategoryColorAttribute(): string
    {
        return self::CATEGORIES[$this->category]['color'] ?? 'gray';
    }
}
