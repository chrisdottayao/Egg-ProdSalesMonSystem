<?php

namespace App\Support;

use App\Models\EggSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 3 item 7 (S25): eggs sold and revenue for a date range, grouped by
 * day / week (Monday–Sunday) / month, with a per-size breakdown. Approved
 * sales only (EggSale's default scope, Phase 3 item 1). Used by the Reports
 * page, its exports, and the complete report (item 11).
 */
class SalesReport
{
    public const GROUPS = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month'];

    private const PCS_PER_TRAY = 30;
    private const PCS_PER_CASE = 360;

    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly string $group = 'day',
    ) {
    }

    public static function make(string $start, string $end, ?string $group): self
    {
        return new self($start, $end, array_key_exists((string) $group, self::GROUPS) ? $group : 'day');
    }

    /** Sales sizes in display order (the 11 the sales form and importer accept). */
    public function sizes(): array
    {
        return EggPrices::sizes();
    }

    /**
     * @return array{rows: Collection, totals: array, sizes: array}
     *   rows: period label => [label, start, pcs, trays, cases, revenue, avg_price, by_size[size => pcs]]
     */
    public function data(): array
    {
        $sales = EggSale::whereDate('date', '>=', $this->start)->whereDate('date', '<=', $this->end)
            ->orderBy('date')->get(['date', 'egg_size', 'quantity', 'total_amount']);

        $rows = $sales->groupBy(fn ($s) => $this->periodKey($s->date))
            ->map(fn ($group, $key) => $this->row($group, $key))
            ->sortKeys()
            ->values();

        return ['rows' => $rows, 'totals' => $this->row($sales, null), 'sizes' => $this->sizes()];
    }

    private function row(Collection $sales, ?string $key): array
    {
        $pcs     = (int) $sales->sum('quantity');
        $revenue = (float) $sales->sum('total_amount');
        $bySize  = [];
        foreach ($this->sizes() as $size) {
            $bySize[$size] = (int) $sales->where('egg_size', $size)->sum('quantity');
        }

        return [
            'label'     => $key === null ? 'Total' : $this->periodLabel($key),
            'start'     => $key,
            'pcs'       => $pcs,
            'trays'     => round($pcs / self::PCS_PER_TRAY, 1),
            'cases'     => round($pcs / self::PCS_PER_CASE, 2),
            'revenue'   => round($revenue, 2),
            'avg_price' => $pcs > 0 ? round($revenue / $pcs, 2) : null,
            'by_size'   => $bySize,
        ];
    }

    private function periodKey(Carbon $date): string
    {
        return match ($this->group) {
            'week'  => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function periodLabel(string $key): string
    {
        return match ($this->group) {
            'week'  => 'Week of ' . Carbon::parse($key)->format('M d') . ' – ' . Carbon::parse($key)->addDays(6)->format('M d, Y'),
            'month' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
            default => Carbon::parse($key)->format('M d, Y'),
        };
    }

    public function describe(): string
    {
        return "{$this->start} to {$this->end}, grouped by " . strtolower(self::GROUPS[$this->group]);
    }
}
