<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Panel revision S16 — "users can only record for today".
 *
 * config('app.timezone') is UTC, but the farm's "today" is Asia/Manila
 * (UTC+8): between 00:00 and 08:00 Manila, UTC's today is still yesterday.
 * Every user-facing "is this today?" check goes through here, never through
 * a bare Carbon::today().
 */
class ManilaDate
{
    public const TZ = 'Asia/Manila';

    // ENGINEERING — how old an offline-queued entry (PWA) may be and still
    // sync with the date it was recorded on. Longer = more tolerance for a
    // farm that stays offline; shorter = less room to backdate by forging
    // queued_at.
    public const OFFLINE_GRACE_DAYS = 3;

    public const MESSAGE = 'You can only record entries for today.';

    public static function today(): Carbon
    {
        return Carbon::now(self::TZ)->startOfDay();
    }

    /** Current farm (Manila) time — for "generated at" stamps and display. */
    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }

    public static function isToday(mixed $date): bool
    {
        return $date !== null && Carbon::parse($date)->toDateString() === self::todayString();
    }

    public static function isPast(mixed $date): bool
    {
        return $date !== null && Carbon::parse($date)->toDateString() < self::todayString();
    }

    /**
     * Validation rule: the date must be today (Manila). An entry queued
     * offline may instead carry the Manila date of its queued_at timestamp,
     * provided that was within OFFLINE_GRACE_DAYS and not in the future.
     */
    public static function rule(?string $queuedAt = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($queuedAt) {
            try {
                $date = Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return; // the 'date' rule reports malformed values
            }

            if ($date === self::todayString() || $date === self::offlineQueuedDate($queuedAt)) {
                return;
            }

            $fail(self::MESSAGE);
        };
    }

    /** Manila date an offline entry was queued on, or null if absent/out of range. */
    public static function offlineQueuedDate(?string $queuedAt): ?string
    {
        if (! $queuedAt) {
            return null;
        }

        try {
            $queued = Carbon::parse($queuedAt)->setTimezone(self::TZ);
        } catch (\Throwable) {
            return null;
        }

        $now = Carbon::now(self::TZ);
        if ($queued->gt($now->copy()->addMinutes(5)) || $queued->lt($now->copy()->subDays(self::OFFLINE_GRACE_DAYS))) {
            return null;
        }

        return $queued->toDateString();
    }
}
