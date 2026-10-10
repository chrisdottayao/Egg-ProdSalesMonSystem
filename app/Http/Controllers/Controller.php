<?php

namespace App\Http\Controllers;

use App\Support\ManilaDate;

abstract class Controller
{
    /**
     * S16: staff may only change records dated today (Manila). Admin and
     * manager can still correct older records; every change stays
     * audit-logged.
     */
    protected function denyPastRecordChangeForStaff(mixed $recordDate): void
    {
        abort_if(
            ! auth()->user()?->canManageRecords() && ManilaDate::isPast($recordDate),
            403,
            "Staff can only change today's records. Ask an admin or manager to correct an older one."
        );
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
