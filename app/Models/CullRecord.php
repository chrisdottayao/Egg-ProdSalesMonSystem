<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CullRecord extends Model
{
    use Auditable; // Phase 3 item 2
    use HasApproval; // Phase 3B: staff culls wait for review, and so do their effects (item 5)

    protected $fillable = [
        'date', 'hen_batch_id', 'quantity_culled', 'reason', 'notes',
        'heads_sold', 'price_per_head', 'buyer',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Reason on the record Daily Entry's "Culled" choice writes (S3): the
     * whole remaining flock of a building, a planned depopulation — not the
     * day-to-day culling the High Culling anomaly looks for.
     */
    public const BUILDING_CULLED_REASON = 'Building culled (daily entry)';

    public function henBatch(): BelongsTo
    {
        return $this->belongsTo(HenBatch::class);
    }

    public function endsBuilding(): bool
    {
        return $this->reason === self::BUILDING_CULLED_REASON && $this->hen_batch_id !== null;
    }

    /**
     * S3: a whole-building cull ends the building as of its date — ended_at
     * set and that day's building row removed (the hens are gone). Applied
     * when the cull counts: on save for admin/manager, on approval for a
     * staff cull (Phase 3B item 5). The caller re-runs that day's rollup.
     */
    public function applyBuildingEnd(): void
    {
        if (! $this->endsBuilding() || ! $this->isApproved()) {
            return;
        }

        $batch = $this->henBatch;
        if ($batch && ! $batch->isEnded()) {
            $batch->update(['ended_at' => $this->date->toDateString()]);
        }
        BuildingDaily::withoutEvents(fn () => BuildingDaily::withPending()
            ->whereDate('date', $this->date)->where('hen_batch_id', $this->hen_batch_id)->delete());
    }
}
