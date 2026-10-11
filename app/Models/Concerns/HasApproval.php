<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3 item 1 (S11, S5) — staff entries wait for manager/admin review.
 *
 * A global scope keeps pending and rejected rows out of EVERY default query
 * on the model, so dashboard totals, the production rollup, reports, alerts
 * and forecasts (ForecastService, RecommendationService and the AI insight
 * read these models directly and are not to be modified) only ever see
 * approved data. Pages that must show unapproved rows — history, the
 * approvals queue, Daily Entry and every write path — opt out explicitly
 * with withPending().
 */
trait HasApproval
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_REJECTED = 'rejected';

    public static function bootHasApproval(): void
    {
        static::addGlobalScope('approved', fn (Builder $q) => $q->where($q->qualifyColumn('approval_status'), self::STATUS_APPROVED));
    }

    public function initializeHasApproval(): void
    {
        $this->mergeFillable(['approval_status', 'approved_by', 'approved_at', 'review_note', static::enteredByColumn()]);
        $this->mergeCasts(['approved_at' => 'datetime']);
    }

    /** Include pending and rejected rows too (history, approvals, writes). */
    public static function withPending(): Builder
    {
        return static::query()->withoutGlobalScope('approved');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->withoutGlobalScope('approved')->where($q->qualifyColumn('approval_status'), self::STATUS_PENDING);
    }

    /** Route-model binding must find pending/rejected rows (edit, approve, reject). */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withoutGlobalScope('approved'), $value, $field);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Column naming who entered the row. building_daily and egg_productions
     * already had user_id; the tables given approval later got entered_by.
     */
    public static function enteredByColumn(): string
    {
        return defined(static::class . '::ENTERED_BY_COLUMN') ? static::ENTERED_BY_COLUMN : 'entered_by';
    }

    /** Who entered the row, whatever the column is called (Phase 3B). */
    public function entrant(): BelongsTo
    {
        return $this->belongsTo(User::class, static::enteredByColumn());
    }

    public function isEnteredBy(?User $user): bool
    {
        return $user !== null && (int) $this->getAttribute(static::enteredByColumn()) === (int) $user->id;
    }

    /**
     * Phase 3B item 4: may $user change or delete this row? Admin/manager:
     * always (every edit is audited old → new). Staff: only their own rows
     * that are still pending or were rejected — never an approved one. A
     * pending row still follows the today-only rule (S16); a rejected one
     * can be corrected whatever its date, since the reviewer asked for that.
     */
    public function changeableBy(?User $user): bool
    {
        if ($user?->canManageRecords()) {
            return true;
        }

        return ! $this->isApproved()
            && $this->isEnteredBy($user)
            && ($this->isRejected() || ! \App\Support\ManilaDate::isPast($this->date));
    }

    /**
     * History lists (Phase 3B item 6): admin/manager see every row; staff see
     * approved rows plus their own pending and rejected ones.
     */
    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        $q->withoutGlobalScope('approved');
        if ($user?->canManageRecords()) {
            return $q;
        }

        return $q->where(fn (Builder $w) => $w
            ->where($w->qualifyColumn('approval_status'), self::STATUS_APPROVED)
            ->orWhere($w->qualifyColumn(static::enteredByColumn()), $user?->id ?? 0));
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED;
    }

    /**
     * Status fields for a row saved by $user: admin/manager entries are
     * approved by themselves on the spot; staff entries wait for review.
     */
    public static function approvalFieldsFor(?User $user): array
    {
        if ($user?->canManageRecords()) {
            return ['approval_status' => self::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now(), 'review_note' => null];
        }

        return ['approval_status' => self::STATUS_PENDING, 'approved_by' => null, 'approved_at' => null, 'review_note' => null];
    }

    /** approvalFieldsFor() plus who entered the row — for a new row (Phase 3B item 2). */
    public static function entryFieldsFor(?User $user): array
    {
        return static::approvalFieldsFor($user) + [static::enteredByColumn() => $user?->id];
    }
}
