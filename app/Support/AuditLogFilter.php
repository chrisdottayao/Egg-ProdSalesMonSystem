<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 3 item 2 (S7): the filters for the Audit Log section of Reports and
 * its PDF/CSV/Excel exports — one definition, so page and exports match.
 * Dates are farm (Manila) calendar days; stored timestamps are UTC.
 */
class AuditLogFilter
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $userId = null,
        public readonly ?string $action = null,
        public readonly ?string $module = null,
        public readonly bool $flaggedOnly = false,
    ) {
    }

    /** Defaults to the report's own date range when no audit dates are given. */
    public static function fromRequest(Request $request, string $defaultFrom, string $defaultTo): self
    {
        return new self(
            from: self::validDate($request->input('audit_from')) ?? $defaultFrom,
            to: self::validDate($request->input('audit_to')) ?? $defaultTo,
            userId: $request->filled('audit_user') ? (int) $request->input('audit_user') : null,
            action: $request->filled('audit_action') ? (string) $request->input('audit_action') : null,
            module: $request->filled('audit_module') ? (string) $request->input('audit_module') : null,
            flaggedOnly: $request->boolean('audit_flagged'),
        );
    }

    public function query(): Builder
    {
        $start = Carbon::parse($this->from, ManilaDate::TZ)->startOfDay()->utc();
        $end   = Carbon::parse($this->to, ManilaDate::TZ)->endOfDay()->utc();

        return AuditLog::with(['user', 'resolver'])
            ->whereBetween('created_at', [$start, $end])
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->action, fn ($q) => $q->where('action', $this->action))
            ->when($this->module, fn ($q) => $q->where('model_type', $this->module))
            ->when($this->flaggedOnly, fn ($q) => $q->where('inconsistency_flagged', true))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Query-string parameters reproducing this filter (for exports and pagination). */
    public function params(): array
    {
        return array_filter([
            'audit_from'    => $this->from,
            'audit_to'      => $this->to,
            'audit_user'    => $this->userId,
            'audit_action'  => $this->action,
            'audit_module'  => $this->module,
            'audit_flagged' => $this->flaggedOnly ? 1 : null,
        ], fn ($v) => $v !== null);
    }

    /** "2026-10-01 to 2026-10-13; user: Mia; action: Login; flagged only" for export headers. */
    public function describe(): string
    {
        $parts = ["{$this->from} to {$this->to}"];
        if ($this->userId) {
            $parts[] = 'user: ' . (User::find($this->userId)?->name ?? "#{$this->userId}");
        }
        if ($this->action) {
            $parts[] = 'action: ' . \Illuminate\Support\Str::headline($this->action);
        }
        if ($this->module) {
            $parts[] = 'record: ' . \Illuminate\Support\Str::headline($this->module);
        }
        if ($this->flaggedOnly) {
            $parts[] = 'flagged only';
        }

        return implode('; ', $parts);
    }

    /** Dropdown options: everyone who appears in the log, and the actions/modules used. */
    public static function options(): array
    {
        return [
            'users'   => User::whereIn('id', AuditLog::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name', 'role']),
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'modules' => AuditLog::distinct()->orderBy('model_type')->pluck('model_type'),
        ];
    }

    /** Readable one-line details: old → new for updates, key: value otherwise. */
    public static function detailsText(AuditLog $log): string
    {
        $details = $log->details ?? [];
        $parts = [];
        foreach ($details['changes'] ?? [] as $field => $change) {
            $parts[] = "{$field}: " . self::scalar($change['old'] ?? null) . ' → ' . self::scalar($change['new'] ?? null);
        }
        foreach ($details as $key => $value) {
            if ($key !== 'changes') {
                $parts[] = "{$key}: " . self::scalar($value);
            }
        }

        return implode('; ', $parts) ?: '—';
    }

    private static function scalar(mixed $v): string
    {
        return match (true) {
            $v === null   => '—',
            is_bool($v)   => $v ? 'yes' : 'no',
            is_array($v)  => isset($v['old']) || array_key_exists('old', $v)
                ? self::scalar($v['old']) . ' → ' . self::scalar($v['new'] ?? null)
                : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default       => (string) $v,
        };
    }

    private static function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
