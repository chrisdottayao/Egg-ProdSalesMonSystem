<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CullRecord extends Model
{
    protected $fillable = [
        'date', 'hen_batch_id', 'quantity_culled', 'reason', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function henBatch(): BelongsTo
    {
        return $this->belongsTo(HenBatch::class);
    }
}
