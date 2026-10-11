<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggSale;
use App\Models\User;
use App\Services\ProductionRollupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3B item 3: approve or reject any pending record. Approving makes it
 * count and re-runs what is derived from it; rejecting returns it to the
 * staff member with the reviewer's note.
 */
class RecordApprover
{
    public static function approve(Model $record, User $by): void
    {
        self::approveMany(collect([$record]), $by);
    }

    /** Approve several records, re-running each affected day's rollup once. */
    public static function approveMany(Collection $records, User $by): int
    {
        DB::transaction(function () use ($records, $by) {
            $rollupDates = [];
            foreach ($records as $record) {
                // Quietly: the 'approve' audit row below records the change, so
                // the models' own update hooks would only duplicate it.
                $record->forceFill([
                    'approval_status' => 'approved',
                    'approved_by'     => $by->id,
                    'approved_at'     => now(),
                    'review_note'     => null,
                ])->saveQuietly();

                if ($record instanceof BuildingDaily) {
                    $rollupDates[$record->date->toDateString()] = true;
                }

                // Phase 3B item 5: a staff "Culled" takes effect only now — the
                // building ends and that day's row goes, as it would have had a
                // manager entered it.
                if ($record instanceof CullRecord && $record->endsBuilding()) {
                    $record->applyBuildingEnd();
                    $rollupDates[$record->date->toDateString()] = true;
                }

                self::audit('approve', $record, $by, null);

                // Phase 3B item 7: sold > produced is evaluated again now that
                // the sale counts (against approved production and sales only).
                if ($record instanceof EggSale && ($check = SoldVsProduced::check($record->date->toDateString(), (int) $record->quantity, $record))) {
                    SoldVsProduced::flag($record, $check, $by->id, 'checked at approval');
                }
            }

            // An approved building row now counts: recompute that day's farm total.
            foreach (array_keys($rollupDates) as $date) {
                (new ProductionRollupService)->rollupDate($date);
            }
        });

        return $records->count();
    }

    public static function reject(Model $record, User $by, string $note): void
    {
        $record->forceFill([
            'approval_status' => 'rejected',
            'approved_by'     => $by->id, // the reviewer
            'approved_at'     => now(),
            'review_note'     => $note,
        ])->saveQuietly();

        self::audit('reject', $record, $by, $note);
    }

    private static function audit(string $action, Model $record, User $by, ?string $note): void
    {
        AuditLog::create([
            'user_id'    => $by->id,
            'action'     => $action,
            'model_type' => class_basename($record),
            'model_id'   => $record->id,
            'details'    => array_filter([
                'record'     => ApprovalQueue::label($record),
                'values'     => ApprovalQueue::summary($record),
                'entered_by' => ApprovalQueue::entrant($record)?->name,
                'note'       => $note,
            ], fn ($v) => $v !== null),
        ]);
    }
}
