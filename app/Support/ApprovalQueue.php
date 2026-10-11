<?php

namespace App\Support;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Phase 3B item 3: every kind of record staff enter that needs review, in
 * one place — the Pending Approvals page, the nav badge, approve/reject and
 * the audit labels all read this list.
 */
class ApprovalQueue
{
    /** URL slug => [model, label, relations to load]. Order = page order. */
    public const TYPES = [
        'building-daily' => [BuildingDaily::class, 'Daily building entry', ['henBatch', 'user']],
        'grading'        => [EggGradingDaily::class, 'Egg grading', ['entrant']],
        'production'     => [EggProduction::class, 'Farm production', ['user']],
        'sales'          => [EggSale::class, 'Sale', ['enteredBy']],
        'expenses'       => [Expense::class, 'Utility expense', ['building', 'entrant']],
        'culls'          => [CullRecord::class, 'Cull', ['henBatch', 'entrant']],
    ];

    public static function modelFor(string $type): ?string
    {
        return self::TYPES[$type][0] ?? null;
    }

    public static function typeOf(Model $record): string
    {
        foreach (self::TYPES as $type => [$class]) {
            if ($record instanceof $class) {
                return $type;
            }
        }

        throw new \InvalidArgumentException('Not an approvable record: ' . $record::class);
    }

    /**
     * Pending rows of every type, optionally for one date and/or one person,
     * oldest first. Each item: type, label, record.
     *
     * @return Collection<int, array{type: string, label: string, record: Model}>
     */
    public static function pending(?string $date = null, ?int $userId = null): Collection
    {
        $items = collect();
        foreach (self::TYPES as $type => [$class, $label, $with]) {
            $class::pending()->with($with)
                ->when($date, fn (Builder $q) => $q->whereDate('date', $date))
                ->when($userId, fn (Builder $q) => $q->where($class::enteredByColumn(), $userId))
                ->orderBy('date')->orderBy('id')->get()
                ->each(fn (Model $r) => $items->push(['type' => $type, 'label' => $label, 'record' => $r]));
        }

        return $items->sortBy(fn ($i) => $i['record']->date->toDateString())->values();
    }

    public static function count(): int
    {
        return collect(self::TYPES)->sum(fn ($t) => $t[0]::pending()->count());
    }

    /** People with something waiting, for the page's filter. */
    public static function pendingUserIds(): Collection
    {
        return collect(self::TYPES)->flatMap(fn ($t) => $t[0]::pending()->whereNotNull($t[0]::enteredByColumn())
            ->distinct()->pluck($t[0]::enteredByColumn()))->unique()->values();
    }

    public static function entrant(Model $record): ?\App\Models\User
    {
        return match (true) {
            $record instanceof BuildingDaily, $record instanceof EggProduction => $record->user,
            $record instanceof EggSale => $record->enteredBy,
            default => $record->entrant,
        };
    }

    public static function building(Model $record): string
    {
        return match (true) {
            $record instanceof BuildingDaily, $record instanceof CullRecord => $record->henBatch?->display_label ?? '—',
            $record instanceof Expense => $record->building?->display_label ?? 'Farm-wide',
            default => 'Farm-wide',
        };
    }

    /** The values a reviewer needs to judge the record. */
    public static function summary(Model $record): string
    {
        $n = fn ($v) => number_format((float) $v);

        return match (true) {
            $record instanceof BuildingDaily => "Population {$n($record->population)} · Mortality {$n($record->mortality)} · Eggs {$n($record->eggs_house)} (" . number_format((float) $record->prod_rate, 1) . '%)',
            $record instanceof EggGradingDaily => "{$record->category}: {$n($record->total_pcs)} pcs",
            $record instanceof EggProduction => "Eggs {$n($record->eggs_collected)} · Hens {$n($record->active_hens)} · Mortality {$n($record->mortality)}",
            $record instanceof EggSale => "{$record->egg_size} × {$n($record->quantity)} @ ₱" . number_format((float) $record->price_per_unit, 2) . ' = ₱' . number_format((float) $record->total_amount, 2),
            $record instanceof Expense => "{$record->category_label}: {$record->description} · ₱" . number_format((float) $record->amount, 2),
            $record instanceof CullRecord => "{$n($record->quantity_culled)} hens" . ($record->reason ? " · {$record->reason}" : '')
                . ($record->reason === CullRecord::BUILDING_CULLED_REASON ? ' (ends the building when approved)' : ''),
        };
    }

    /** One-line name for messages and the Audit Log. */
    public static function label(Model $record): string
    {
        $type = self::TYPES[self::typeOf($record)][1];
        $where = self::building($record);

        return "{$type}" . ($where !== 'Farm-wide' ? " ({$where})" : '') . ' for ' . $record->date->toDateString();
    }
}
