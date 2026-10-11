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
}
