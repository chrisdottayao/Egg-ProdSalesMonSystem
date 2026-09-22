<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HenBatch extends Model
{
    protected $fillable = [
        'batch_id', 'batch_size', 'status', 'entry_date', 'notes', 'pen_number', 'building',
        'placement_date', 'breed', 'building_no', 'is_tracked',
    ];

    protected $casts = [
        'entry_date'      => 'date',
        'placement_date'  => 'date',
        'is_tracked'      => 'boolean',
    ];

    /**
     * Buildings the live system actively records/displays (Prototype 3M —
     * scoped to 3 of the farm's 45 real buildings per the IT expert's
     * recommendation). Untracked buildings' historical data is untouched —
     * this only controls what's selectable/visible going forward.
     */
    public function scopeTracked(Builder $query): Builder
    {
        return $query->where('is_tracked', true);
    }

    public function cullRecords(): HasMany
    {
        return $this->hasMany(CullRecord::class);
    }

    public function buildingDailies(): HasMany
    {
        return $this->hasMany(BuildingDaily::class);
    }

    public static function activeHenCount(): int
    {
        return self::where('status', 'Active')->sum('batch_size');
    }
}
