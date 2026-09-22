<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HenBatch extends Model
{
    protected $fillable = [
        'batch_id', 'batch_size', 'status', 'entry_date', 'notes', 'pen_number', 'building',
        'placement_date', 'breed', 'building_no', 'is_tracked', 'display_no',
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
        // Tracked-only (3N) — was summing all 45 buildings' populations.
        return self::where('status', 'Active')->tracked()->sum('batch_size');
    }

    /**
     * The number to show a user for this building: display_no (Prototype 3N's
     * UI relabeling, e.g. tracked building "4" reads as "1") if set, else the
     * real building_no. Internal matching (CSV import, physical-adjacency
     * clustering) must keep using building_no directly, never this.
     */
    public function getEffectiveBuildingNoAttribute(): ?int
    {
        return $this->display_no ?? $this->building_no;
    }

    /**
     * "Building {N}" using the effective (display) number, or the batch_id
     * for a batch with neither number set. The single source of truth for
     * how a building's name is shown anywhere in the UI.
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->effective_building_no !== null
            ? 'Building ' . $this->effective_building_no
            : $this->batch_id;
    }
}
