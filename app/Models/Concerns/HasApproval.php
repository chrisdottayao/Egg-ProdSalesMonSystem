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
        $this->mergeFillable(['approval_status', 'approved_by', 'approved_at', 'review_note']);
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
}
