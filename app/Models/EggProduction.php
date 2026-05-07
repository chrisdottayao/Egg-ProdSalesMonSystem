<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggProduction extends Model
{
    protected $fillable = [
        'flock_id', 'date', 'total_eggs', 'cracked_eggs', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function getGoodEggsAttribute(): int
    {
        return $this->total_eggs - $this->cracked_eggs;
    }
}
