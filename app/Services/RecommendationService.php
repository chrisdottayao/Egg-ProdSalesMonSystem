<?php

namespace App\Services;

use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function getRecommendations(): array
    {
        if (EggProduction::count() < 30) {
            return [];
        }

        // Fetch once, reuse across all conditions
        $productions = EggProduction::orderBy('date', 'desc')->take(30)->get();

        $salesByDate = EggSale::where('date', '>=', Carbon::today()->subDays(30))
            ->selectRaw('DATE(date) as sale_date, SUM(total_amount) as daily_revenue, SUM(quantity) as daily_sold')
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');

        $recommendations = [];

        foreach ([
            $this->condition1LowProductionRate($productions),
            $this->condition2RisingMortality($productions),
            $this->condition3HighCullingRate(),
            $this->condition4DecliningRevenue($salesByDate),
            $this->condition5UnexplainedHenDecrease($productions),
            $this->condition6UnsoldsAccumulating($productions, $salesByDate),
            $this->condition7FlockDeclineWithLowProduction($productions),
        ] as $result) {
            if ($result !== null) {
                $recommendations[] = $result;
            }
        }

        return $recommendations;
    }

    // ── Condition 1 ───────────────────────────────────────────────────────────
    // Production rate < 70% for 3+ consecutive days
    private function condition1LowProductionRate(Collection $productions): ?array
    {
        $streak = 0;
        $since  = null;

        foreach ($productions as $p) { // desc: most recent first
            $rate = $p->active_hens > 0
                ? ($p->eggs_collected / $p->active_hens) * 100
                : 0;

            if ($rate < 70) {
                $streak++;
                $since = $p->date; // walks backward → ends at oldest in streak
            } else {
                break;
            }
        }

        if ($streak < 3) return null;

        return [
            'condition'       => 'Low Production Rate',
            'recommendation'  => 'Review active hen count accuracy; check for unreported mortality or health issues.',
            'severity'        => $streak >= 5 ? 'critical' : 'warning',
            'triggered_since' => $since->format('Y-m-d'),
        ];
    }

    // ── Condition 2 ───────────────────────────────────────────────────────────
    // Mortality count strictly rising for 5+ consecutive days
    private function condition2RisingMortality(Collection $productions): ?array
    {
        $sorted = $productions->sortBy('date')->values();
        $n      = $sorted->count();
        if ($n < 5) return null;

        $streak = 1;
        $since  = $sorted[$n - 1]->date;

        for ($i = $n - 1; $i >= 1; $i--) {
            if ($sorted[$i]->mortality > $sorted[$i - 1]->mortality) {
                $streak++;
                $since = $sorted[$i - 1]->date;
            } else {
                break;
            }
        }

        if ($streak < 5) return null;

        return [
            'condition'       => 'Rising Mortality',
            'recommendation'  => 'Inspect flock health; verify mortality entries are reflected in active hen count.',
            'severity'        => 'critical',
            'triggered_since' => $since->format('Y-m-d'),
        ];
    }

    // ── Condition 3 ───────────────────────────────────────────────────────────
    // Cull count this month > historical monthly average
    private function condition3HighCullingRate(): ?array
    {
        $monthStart = Carbon::now()->startOfMonth();

        $currentMonthTotal = CullRecord::where('date', '>=', $monthStart)
            ->sum('quantity_culled');

        $historical = CullRecord::where('date', '<', $monthStart)
            ->selectRaw('DATE_FORMAT(date, \'%Y-%m\') as month, SUM(quantity_culled) as monthly_total')
            ->groupBy('month')
            ->get();

        if ($historical->isEmpty()) return null;

        $avgMonthly = $historical->avg('monthly_total');

        if ($currentMonthTotal <= $avgMonthly) return null;

        return [
            'condition'       => 'High Culling Rate',
            'recommendation'  => 'Review flock age distribution; assess whether culling is reducing production rate.',
            'severity'        => 'warning',
            'triggered_since' => $monthStart->format('Y-m-d'),
        ];
    }

    // ── Condition 4 ───────────────────────────────────────────────────────────
    // Daily sales revenue declining for 7+ consecutive days
    private function condition4DecliningRevenue(Collection $salesByDate): ?array
    {
        if ($salesByDate->count() < 7) return null;

        $dates = $salesByDate->keys()->sort()->values();
        $n     = $dates->count();
        $streak = 1;
        $since  = $dates[$n - 1];

        for ($i = $n - 1; $i >= 1; $i--) {
            $todayRev = (float) $salesByDate[$dates[$i]]->daily_revenue;
            $prevRev  = (float) $salesByDate[$dates[$i - 1]]->daily_revenue;

            if ($todayRev < $prevRev) {
                $streak++;
                $since = $dates[$i - 1];
            } else {
                break;
            }
        }

        if ($streak < 7) return null;

        return [
            'condition'       => 'Declining Revenue',
            'recommendation'  => 'Review pricing per egg size; compare sales volume against production to identify unsold stock buildup.',
            'severity'        => 'warning',
            'triggered_since' => $since,
        ];
    }

    // ── Condition 5 ───────────────────────────────────────────────────────────
    // Active hen count decreased without a cull_record or mortality entry
    private function condition5UnexplainedHenDecrease(Collection $productions): ?array
    {
        $sorted = $productions->sortBy('date')->values();
        if ($sorted->count() < 2) return null;

        // Build cull-date lookup in one query
        $cullDates = CullRecord::where('date', '>=', $sorted->first()->date)
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        $flagged = [];
        for ($i = 1; $i < $sorted->count(); $i++) {
            $curr    = $sorted[$i];
            $prev    = $sorted[$i - 1];
            $dateKey = $curr->date->format('Y-m-d');

            if (
                $curr->active_hens < $prev->active_hens
                && $curr->mortality == 0
                && ! $cullDates->has($dateKey)
            ) {
                $flagged[] = $curr->date;
            }
        }

        if (empty($flagged)) return null;

        return [
            'condition'       => 'Unexplained Hen Decrease',
            'recommendation'  => 'Flag possible missing records; prompt Admin/Manager to verify livestock records.',
            'severity'        => 'warning',
            'triggered_since' => $flagged[0]->format('Y-m-d'),
        ];
    }

    // ── Condition 6 ───────────────────────────────────────────────────────────
    // Remaining eggs (produced − sold) increasing for 3+ consecutive days
    private function condition6UnsoldsAccumulating(Collection $productions, Collection $salesByDate): ?array
    {
        $sorted = $productions->sortBy('date')->values();
        $n      = $sorted->count();
        if ($n < 3) return null;

        $daily = $sorted->map(function ($p) use ($salesByDate) {
            $key  = $p->date->format('Y-m-d');
            $sold = isset($salesByDate[$key]) ? (float) $salesByDate[$key]->daily_sold : 0;
            return ['date' => $p->date, 'remaining' => $p->eggs_collected - $sold];
        })->values();

        $streak = 1;
        $since  = $daily[$n - 1]['date'];

        for ($i = $n - 1; $i >= 1; $i--) {
            if ($daily[$i]['remaining'] > $daily[$i - 1]['remaining']) {
                $streak++;
                $since = $daily[$i - 1]['date'];
            } else {
                break;
            }
        }

        if ($streak < 3) return null;

        return [
            'condition'       => 'Unsold Egg Accumulation',
            'recommendation'  => 'Alert Manager to review sales pace and adjust sales planning.',
            'severity'        => 'warning',
            'triggered_since' => $since->format('Y-m-d'),
        ];
    }

    // ── Condition 7 ───────────────────────────────────────────────────────────
    // Active hens declining AND production rate < 75% for 3+ consecutive days
    private function condition7FlockDeclineWithLowProduction(Collection $productions): ?array
    {
        $sorted = $productions->sortBy('date')->values();
        $n      = $sorted->count();
        if ($n < 4) return null;

        $streak = 0;
        $since  = null;

        for ($i = $n - 1; $i >= 1; $i--) {
            $curr = $sorted[$i];
            $prev = $sorted[$i - 1];
            $rate = $curr->active_hens > 0
                ? ($curr->eggs_collected / $curr->active_hens) * 100
                : 0;

            if ($curr->active_hens < $prev->active_hens && $rate < 75) {
                $streak++;
                $since = $curr->date;
            } else {
                break;
            }
        }

        if ($streak < 3) return null;

        return [
            'condition'       => 'Flock Decline & Low Production',
            'recommendation'  => 'Recommend flock replenishment planning.',
            'severity'        => 'critical',
            'triggered_since' => $since->format('Y-m-d'),
        ];
    }
}
