<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'details',
        'ip_address',
        'user_agent',
        'inconsistency_flagged',
        'inconsistency_rule',
        'resolved',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'details'               => 'array',
        'inconsistency_flagged' => 'boolean',
        'resolved'              => 'boolean',
        'resolved_at'           => 'datetime',
    ];

    /** Keys never written to `details` (Phase 3 item 2). */
    private const SECRET_KEYS = ['password', 'password_confirmation', 'current_password', 'remember_token', '_token', 'token', 'api_key', 'secret'];

    /** The only columns that may change after a row is written: resolving a flag. */
    private const RESOLUTION_FIELDS = ['resolved', 'resolved_by', 'resolved_at', 'resolution_note', 'updated_at'];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            // Where the action came from (none for console/queue jobs).
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $request = request();
                $log->ip_address ??= $request?->ip();
                $log->user_agent ??= $request?->userAgent() ? Str::limit($request->userAgent(), 500, '') : null;
            }
            $log->details = self::scrub($log->details ?? []);
        });

        // Append-only: the log is evidence. Only a flag's resolution may be
        // recorded later; nothing is edited or deleted.
        static::updating(function (AuditLog $log) {
            $changed = array_keys($log->getDirty());
            if ($blocked = array_diff($changed, self::RESOLUTION_FIELDS)) {
                throw new LogicException('Audit log rows are append-only (attempted to change: ' . implode(', ', $blocked) . ').');
            }
        });
        static::deleting(fn () => throw new LogicException('Audit log rows are append-only.'));
    }

    /** Removes secrets anywhere in a details array; a changed password is noted, never stored. */
    public static function scrub(mixed $details): mixed
    {
        if (! is_array($details)) {
            return $details;
        }
        foreach ($details as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $details[$key] = '(hidden)';
            } elseif (is_array($value)) {
                $details[$key] = self::scrub($value);
            }
        }

        return $details;
    }

    /** old → new for every changed attribute of a just-updated model. */
    public static function diff(Model $model, array $ignore = ['updated_at', 'created_at']): array
    {
        $changes = [];
        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            $old = $model->getRawOriginal($key);
            // "0.00" from the database vs 0 from PHP is not a real change.
            if (is_numeric($old) && is_numeric($new) && (float) $old === (float) $new) {
                continue;
            }
            $changes[$key] = ['old' => $model->getOriginal($key), 'new' => $new];
        }

        return self::scrub($changes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** "Egg Sale", "Building Daily Csv Import", … */
    public function getModuleLabelAttribute(): string
    {
        return Str::headline($this->model_type ?? '—');
    }

    /** Actions whose headline form would read badly. */
    private const ACTION_LABELS = [
        'sold_gt_produced'        => 'Sold > produced',
        'login_failed'            => 'Failed login',
        'remove_building_end'     => 'Removed building (ended)',
        'remove_building_delete'  => 'Removed building (deleted)',
    ];

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? Str::headline($this->action ?? '—');
    }
}
