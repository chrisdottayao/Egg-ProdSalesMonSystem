<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HenBatch extends Model
{
    use Auditable; // Phase 3 item 2: buildings added, edited, ended, reopened, deleted

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
    /**
     * Buildings that can still receive new entries (S3). Entry forms and
     * dropdowns use this; history/report filters must not, so an ended
     * building's old records stay reachable.
     */
    public function scopeNotEnded(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Buildings that were still running on $date: not ended, or ended after
     * it. Lets an admin correct a past day of a building culled since then;
     * a building culled on $date itself is excluded from that day on.
     */
    public function scopeRunningOn(Builder $query, string $date): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>', $date));
    }

    /**
     * Buildings whose records count on $date: not ended, or ended on/after
     * it (Phase 3 item 9). Unlike runningOn(), the end date itself counts, so
     * a building removed today keeps today's already-entered numbers.
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date));
    }

    /** Has any record that a permanent delete would destroy or orphan. */
    public function hasHistory(): bool
    {
        return BuildingDaily::withPending()->where('hen_batch_id', $this->id)->exists()
            || CullRecord::where('hen_batch_id', $this->id)->exists()
            || Expense::where('building_id', $this->id)->exists()
            || FlockAlert::where('hen_batch_id', $this->id)->exists();
    }

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
