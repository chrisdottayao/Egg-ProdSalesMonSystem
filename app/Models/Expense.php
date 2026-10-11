<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable; // Phase 3 item 2
    use HasApproval; // Phase 3B: staff expenses wait for review

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

    // Panel revision S19: the module is "Utility Expenses" and only these
    // categories can be recorded going forward. The rest of CATEGORIES stays
    // so rows already stored under them still display and can be edited.
    public const ACTIVE_CATEGORIES = ['feed', 'electricity'];

    public static function activeCategories(): array
    {
        return array_intersect_key(self::CATEGORIES, array_flip(self::ACTIVE_CATEGORIES));
    }

    public function getIsArchivedCategoryAttribute(): bool
    {
        return ! in_array($this->category, self::ACTIVE_CATEGORIES, true);
    }

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
