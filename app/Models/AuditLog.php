<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'details',
        'inconsistency_flagged',
        'inconsistency_rule',
        'resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'details'               => 'array',
        'inconsistency_flagged' => 'boolean',
        'resolved'              => 'boolean',
        'resolved_at'           => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
