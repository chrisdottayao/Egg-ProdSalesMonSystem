<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HenBatch extends Model
{
    protected $fillable = [
        'batch_id', 'batch_size', 'status', 'entry_date', 'notes', 'pen_number', 'building',
        'placement_date', 'breed', 'building_no', 'is_tracked', 'display_no', 'ended_at',
    ];

    protected $casts = [
        'entry_date'      => 'date',
        'placement_date'  => 'date',
        'is_tracked'      => 'boolean',
        'ended_at'        => 'date',
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

    /**
     * ended_at is a separate fact from is_tracked (Prototype 3Q) — a building
     * can be tracked AND ended (fully depopulated, e.g. Building 14) at once.
     */
    public function isEnded(): bool
    {
        return $this->ended_at !== null;
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
        // Tracked-only (3N), excluding ended flocks (3Q) — a fully
        // depopulated building has 0 live hens, not its last recorded
        // batch_size.
        return self::where('status', 'Active')->tracked()->whereNull('ended_at')->sum('batch_size');
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
