<?php

namespace App\Services;

use App\Models\BuildingDaily;
use App\Models\Expense;

class ExpenseAllocator
{
    /**
     * A building's total expense for [$start, $end] = its direct expenses
     * (building_id = $buildingId) + its population-weighted share of every
     * farm-wide expense (building_id null) in the same window.
     *
     * Population-share, not an even split, because eggs from every building
     * are mixed farm-wide before grading/sale and feed/vaccines are bought at
     * farm level — per-building expense is inherently an estimate, and
     * headcount share is the fairest defensible basis for it.
     *
     * @return array{
     *     building_id: int,
     *     direct_total: float,
     *     allocated_total: float,
     *     total: float,
     *     population_share: float,
     *     by_category: array<string,float>,
     *     has_estimated_portion: bool,
     * }
     */
    public function forBuilding(int $buildingId, string $start, string $end): array
    {
        $direct           = Expense::forBuilding($buildingId)->betweenDates($start, $end)->get();
        $directTotal      = (float) $direct->sum('amount');
        $directByCategory = $direct->groupBy('category')->map(fn ($rows) => $rows->sum('amount'));

        $farmWide           = Expense::farmWide()->betweenDates($start, $end)->get();
        $farmWideTotal      = (float) $farmWide->sum('amount');
        $farmWideByCategory = $farmWide->groupBy('category')->map(fn ($rows) => $rows->sum('amount'));

        $share          = $this->populationShare($buildingId, $start, $end);
        $allocatedTotal = round($farmWideTotal * $share, 2);

        $byCategory = collect(array_keys(Expense::CATEGORIES))
            ->mapWithKeys(function ($category) use ($directByCategory, $farmWideByCategory, $share) {
                $direct    = $directByCategory[$category] ?? 0;
                $allocated = ($farmWideByCategory[$category] ?? 0) * $share;

                return [$category => round($direct + $allocated, 2)];
            })
            ->all();

        return [
            'building_id'           => $buildingId,
            'direct_total'          => round($directTotal, 2),
            'allocated_total'       => $allocatedTotal,
            'total'                 => round($directTotal + $allocatedTotal, 2),
            'population_share'      => round($share, 6),
            'by_category'           => $byCategory,
            // A building's figure is a mix of its own real expenses plus an
            // allocated slice of farm-wide ones whenever any farm-wide
            // expense exists in the window — never a pure receipted total.
            'has_estimated_portion' => $farmWideTotal > 0,
        ];
    }

    /**
     * Building's mean daily population over the window ÷ farm's mean daily
     * population over the same window. Both averages are taken over
     * whichever dates building_daily actually has rows for — a building with
     * no rows in the window gets a 0 share rather than a division error.
     */
    private function populationShare(int $buildingId, string $start, string $end): float
    {
        $buildingAvg = (float) (BuildingDaily::where('hen_batch_id', $buildingId)
            ->whereBetween('date', [$start, $end])
            ->avg('population') ?? 0);

        $farmDailyTotals = BuildingDaily::whereBetween('date', [$start, $end])
            ->selectRaw('date, SUM(population) as total_population')
            ->groupBy('date')
            ->pluck('total_population');

        $farmAvg = $farmDailyTotals->isNotEmpty() ? (float) $farmDailyTotals->avg() : 0.0;

        return $farmAvg > 0 ? $buildingAvg / $farmAvg : 0.0;
    }
}
