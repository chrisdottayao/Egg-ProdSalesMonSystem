<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel revisions S8/S14 — "highlight the latest record inputted" in each
 * History table. The highlight is computed on every page load (no new
 * column), so it survives a reload and moves only when a newer save happens:
 *
 *   1. candidates = rows whose `date` is within the last RECENT_DAYS days
 *      (historical backfills target old dates, so they never qualify);
 *   2. drop candidates written during a CSV import, using the import's
 *      audit-log timestamp (imports bulk-insert and log once at the end);
 *   3. latest = max(updated_at) of what is left; highlight every candidate
 *      saved within SAME_SAVE_MINUTES of it, which covers one Daily Entry
 *      request writing several rows.
 *
 * updated_at (not created_at) is used so re-saving a day's entry counts as
 * the newest record.
 */
class LatestEntry
{
    // Shared by the History rows and the legend chip so the two never drift
    // apart. This file is in tailwind.config.js `content` for that reason.
    public const ROW_CLASSES = 'bg-green-50 ring-1 ring-inset ring-green-300';
    public const DOT_CLASSES = 'bg-green-50 ring-1 ring-inset ring-green-400';
    public const PILL_CLASSES = 'bg-green-600 text-white';

    // ENGINEERING — window for "saved in the same request/batch".
    private const SAME_SAVE_MINUTES = 2;

    // ENGINEERING — only rows dated this recently can be "the latest entry".
    private const RECENT_DAYS = 7;

    // ENGINEERING — how far before an import's audit-log entry its rows may
    // have been written (the log is created at the end of the transaction).
    private const IMPORT_WINDOW_MINUTES = 10;

    private function __construct(
        private readonly array $ids,
        public readonly ?Carbon $at,
    ) {
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  (callable(Builder): void)|null  $scope  extra constraints matching the page's own list
     */
    public static function for(string $modelClass, ?callable $scope = null, string $dateColumn = 'date'): self
    {
        // Includes a staff member's own pending entry (Phase 3 item 1) — the
        // scope is a no-op on models without approval.
        $query = $modelClass::query()->withoutGlobalScope('approved')
            ->whereDate($dateColumn, '>=', Carbon::today()->subDays(self::RECENT_DAYS))
            ->whereNotNull('updated_at');

        if ($scope) {
            $scope($query);
        }

        $candidates = $query->get(['id', 'updated_at']);

        if ($candidates->isEmpty()) {
            return new self([], null);
        }

        $candidates = self::withoutImportedRows($candidates);

        $latest = $candidates->max('updated_at');
        if (! $latest) {
            return new self([], null);
        }

        $cutoff = $latest->copy()->subMinutes(self::SAME_SAVE_MINUTES);
        $ids    = $candidates->filter(fn ($row) => $row->updated_at->gte($cutoff))->pluck('id')->all();

        return new self($ids, $latest);
    }

    public function has(Model $row): bool
    {
        return in_array($row->getKey(), $this->ids, true);
    }

    public function any(): bool
    {
        return $this->ids !== [];
    }

    public function ids(): array
    {
        return $this->ids;
    }

    /** "Added 10:42 AM" today, "Added Oct 9, 10:42 AM" on another day — farm (Manila) time, not the app's UTC. */
    public function addedLabel(): string
    {
        if (! $this->at) {
            return '';
        }

        $at = $this->at->copy()->setTimezone(ManilaDate::TZ);

        return 'Added ' . ($at->isSameDay(ManilaDate::today()) ? $at->format('g:i A') : $at->format('M j, g:i A'));
    }

    private static function withoutImportedRows(Collection $candidates): Collection
    {
        $earliest = $candidates->min('updated_at');

        $importTimes = AuditLog::where('action', 'import')
            ->where('created_at', '>=', $earliest)
            ->pluck('created_at');

        if ($importTimes->isEmpty()) {
            return $candidates;
        }

        return $candidates->reject(function ($row) use ($importTimes) {
            return $importTimes->contains(fn ($loggedAt) => $row->updated_at->between(
                $loggedAt->copy()->subMinutes(self::IMPORT_WINDOW_MINUTES),
                $loggedAt->copy()->addSeconds(5),
            ));
        })->values();
    }
}
