<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyAlert extends Model
{
    protected $fillable = [
        'type', 'severity', 'alert_date', 'expected_value',
        'actual_value', 'deviation_pct', 'description', 'status',
        'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'alert_date'  => 'date',
        'resolved_at' => 'datetime',
    ];

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isHigh(): bool   { return $this->severity === 'high'; }
    public function isMedium(): bool { return $this->severity === 'medium'; }
}
