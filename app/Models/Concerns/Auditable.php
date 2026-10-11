<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Phase 3 item 2 (S7): every create / update (old → new) / delete of the
 * model lands in the Audit Log with the acting user. Secrets are scrubbed
 * by AuditLog itself. Models may set $auditIgnore for noisy columns.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('create', $m->auditSnapshot()));
        static::updated(function ($m) {
            $changes = AuditLog::diff($m, array_merge(['updated_at', 'created_at'], $m->auditIgnore ?? []));
            if ($changes) {
                $m->writeAudit('update', ['changes' => $changes]);
            }
        });
        static::deleted(fn ($m) => $m->writeAudit('delete', $m->auditSnapshot()));
    }

    protected function auditSnapshot(): array
    {
        return collect($this->getAttributes())
            ->except(array_merge(['created_at', 'updated_at'], $this->auditIgnore ?? []))
            ->all();
    }

    protected function writeAudit(string $action, array $details): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => class_basename($this),
            'model_id'   => $this->getKey(),
            'details'    => $details,
        ]);
    }
}
