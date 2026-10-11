<?php

namespace App\Http\Controllers;

use App\Support\ManilaDate;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

abstract class Controller
{
    /** Phase 3B item 4: see HasApproval::changeableBy(). */
    protected function staffMayChange(Model $record): bool
    {
        return $record->changeableBy(auth()->user());
    }

    /** 403 with a plain explanation when staffMayChange() says no. */
    protected function denyStaffChange(Model $record): void
    {
        if ($this->staffMayChange($record)) {
            return;
        }

        $user = auth()->user();
        abort(403, match (true) {
            $record->isApproved()           => 'This record is already approved, so it can no longer be changed by staff. Ask a manager or admin to correct it.',
            ! $record->isEnteredBy($user)   => 'You can only change records you entered yourself.',
            default                         => "Staff can only change today's records. Ask an admin or manager to correct an older one.",
        });
    }

    /**
     * Date rule when editing $record: admin/manager any date; staff today,
     * or — for a rejected record being corrected — its own date.
     */
    protected function editDateRules(Model $record): array
    {
        if (auth()->user()?->canManageRecords()) {
            return ['required', 'date'];
        }
        if ($record->isRejected()) {
            $own = $record->date->toDateString();

            return ['required', 'date', function (string $attribute, mixed $value, Closure $fail) use ($own) {
                try {
                    $date = Carbon::parse($value)->toDateString();
                } catch (\Throwable) {
                    return;
                }
                if ($date !== $own && $date !== ManilaDate::todayString()) {
                    $fail("A corrected record keeps its own date ({$own}) or today.");
                }
            }];
        }

        return $this->entryDateRules(false);
    }

    /** Date rule for a submitted date: today only for new records; staff edits too. */
    protected function entryDateRules(bool $isNewRecord = true, ?string $queuedAt = null): array
    {
        if (! $isNewRecord && auth()->user()?->canManageRecords()) {
            return ['required', 'date'];
        }

        return ['required', 'date', ManilaDate::rule($queuedAt)];
    }
}
