<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlockAlert extends Model
{
    protected $fillable = [
        'hen_batch_id',
        'condition',
        'severity',
        'recommendation',
        'expected_rate',
        'deviation',
        'cluster_id',
        'triggered_since',
        'status',
        'normal_streak',
        'resolved_at',
    ];

    protected $casts = [
        'triggered_since' => 'date',
        'resolved_at'     => 'datetime',
    ];

    public function henBatch(): BelongsTo
    {
        return $this->belongsTo(HenBatch::class);
    }
}
