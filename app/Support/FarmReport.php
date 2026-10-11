<?php

namespace App\Support;

use App\Models\AnomalyAlert;
use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\ForecastEvaluation;
use App\Models\HenBatch;
use App\Services\BuildingPerformanceService;
use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 3 item 11: the single source for the Reports page and the complete
 * PDF/CSV/Excel report, so they cannot drift apart. Only approved records
 * feed it (BuildingDaily/EggSale default scope, Phase 3 item 1).
 *
 * The full Audit Log is deliberately NOT a section here: it is system
 * activity, not farm results, and has its own export (item 2).
 */
class FarmReport
{
    public const SECTIONS = [
        'summary'   => 'Summary',
        'charts'    => 'Production & Revenue charts',
        'daily'     => 'Daily Breakdown',
        'buildings' => 'Per-Building Performance',
        'grading'   => 'Egg Size (grading) Breakdown',
        'sales'     => 'Sales & Revenue',
        'expenses'  => 'Feed & Electricity Expenses',
        'culls'     => 'Culls & Mortality',
        'alerts'    => 'Anomaly Alerts',
        'forecast'  => 'Forecast & Forecast Evaluation',
        'batch'     => 'Batch Traceability',
        'flags'     => 'Flagged records (unresolved)',
    ];

    private const PERIOD_TO_GROUP = ['Daily' => 'day', 'Weekly' => 'week', 'Monthly' => 'month'];

    private array $cache = [];

    /** @param list<string> $sections */
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly string $period,
        public readonly array $sections,
        public readonly ?string $salesGroup = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $start  = self::validDate($request->input('start_date')) ?? ManilaDate::today()->subDays(7)->toDateString();
        $end    = self::validDate($request->input('end_date')) ?? ManilaDate::todayString();
        $period = in_array($request->input('period'), array_keys(self::PERIOD_TO_GROUP), true) ? $request->input('period') : 'Daily';

        // No "sections" parameter at all = everything (the default, all ticked).
        $sections = $request->has('sections')
            ? array_values(array_intersect(array_keys(self::SECTIONS), (array) $request->input('sections')))
            : array_keys(self::SECTIONS);

        return new self($start, $end, $period, $sections, $request->input('sales_group'));
    }

    public function includes(string $section): bool
    {
        return in_array($section, $this->sections, true);
    }

    /** "Summary, Daily Breakdown, …" for export headers. */
    public function sectionsText(): string
    {
        return implode(', ', array_map(fn ($k) => self::SECTIONS[$k], $this->sections)) ?: 'none';
    }

    public function rangeText(): string
    {
        return Carbon::parse($this->start)->format('M d, Y') . ' – ' . Carbon::parse($this->end)->format('M d, Y');
    }

    public function filtersText(): string
    {
        return "period: {$this->period}; sales grouped by " . strtolower(SalesReport::GROUPS[$this->salesReport()->group]);
    }

    // ── Sections ─────────────────────────────────────────────────────────

    private function productions()
    {
        return $this->cache['productions'] ??= EggProduction::whereDate('date', '>=', $this->start)
            ->whereDate('date', '<=', $this->end)->orderBy('date')->get();
    }

    private function sales()
    {
        return $this->cache['sales'] ??= EggSale::whereDate('date', '>=', $this->start)
            ->whereDate('date', '<=', $this->end)->get();
    }

    private function expenses()
    {
        return $this->cache['expenses'] ??= Expense::with('building')->whereDate('date', '>=', $this->start)
            ->whereDate('date', '<=', $this->end)->orderBy('date')->get();
    }

    public function summary(): array
    {
        $productions   = $this->productions();
        $sales         = $this->sales();
        $totalProduced = (int) $productions->sum('eggs_collected');
        $totalSold     = (int) $sales->sum('quantity');
        $totalSpoiled  = (int) $productions->sum(fn ($p) => $p->spoilage_count ?? 0);
        $totalRevenue  = (float) $sales->sum('total_amount');
        // Farm-wide, all expense categories (unchanged since Phase 1, S19).
        $totalExpenses = (float) $this->expenses()->sum('amount');

        return [
            'total_eggs_produced' => $totalProduced,
            'total_eggs_sold'     => $totalSold,
            'total_revenue'       => $totalRevenue,
            'total_mortality'     => (int) $productions->sum('mortality'),
            'total_expenses'      => $totalExpenses,
            'net_income'          => round($totalRevenue - $totalExpenses, 2),
            'avg_production_rate' => $productions->count() ? round($productions->avg(fn ($p) => $p->production_rate), 1) : 0,
            'avg_sales_rate'      => $totalProduced > 0 ? round($totalSold / $totalProduced * 100, 1) : 0,
            // Remaining = collected − sold − spoiled.
            'remaining_eggs'      => $totalProduced - $totalSold - $totalSpoiled,
        ];
    }

    public function dailyData()
    {
        $sales    = $this->sales()->groupBy(fn ($s) => $s->date->toDateString());
        $expenses = $this->expenses()->groupBy(fn ($e) => $e->date->toDateString());

        return $this->cache['daily'] ??= $this->productions()->map(function ($prod) use ($sales, $expenses) {
            $key = $prod->date->toDateString();

            return [
                'date'      => $prod->date->format('M d'),
                'iso_date'  => $key,
                'eggs'      => (int) $prod->eggs_collected,
                'sold'      => (int) ($sales[$key] ?? collect())->sum('quantity'),
                'revenue'   => (float) ($sales[$key] ?? collect())->sum('total_amount'),
                'prod_rate' => $prod->production_rate,
                'spoiled'   => (int) ($prod->spoilage_count ?? 0),
                'mortality' => (int) ($prod->mortality ?? 0),
                'expenses'  => (float) ($expenses[$key] ?? collect())->sum('amount'),
            ];
        })->values();
    }

    public function batches()
    {
        return EggProduction::with('eggSales')->whereDate('date', '>=', $this->start)->whereDate('date', '<=', $this->end)
            ->orderBy('date', 'desc')->get();
    }

    public function buildings(): array
    {
        $buildings = HenBatch::where('status', 'Active')->tracked()
            ->orderByRaw('COALESCE(display_no, building_no) IS NULL, COALESCE(display_no, building_no) ASC')->get();

        return (new BuildingPerformanceService)->farmOverview($buildings, Carbon::parse($this->start)->startOfDay(), Carbon::parse($this->end)->endOfDay());
    }

    /** @return array{rows: list<array{category: string, pcs: int, share: float}>, total: int} */
    public function grading(): array
    {
        $byCat = EggGradingDaily::whereDate('date', '>=', $this->start)->whereDate('date', '<=', $this->end)
            ->get()->groupBy('category')->map(fn ($g) => (int) $g->sum('total_pcs'));
        $total = (int) $byCat->sum();
        $rows  = collect(EggGradingDaily::CATEGORIES)->map(fn ($c) => [
            'category' => $c,
            'pcs'      => (int) ($byCat[$c] ?? 0),
            'share'    => $total > 0 ? round(($byCat[$c] ?? 0) / $total * 100, 1) : 0.0,
        ])->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function salesReport(): SalesReport
    {
        return $this->cache['salesReport'] ??= SalesReport::make($this->start, $this->end, $this->salesGroup ?: self::PERIOD_TO_GROUP[$this->period]);
    }

    public function expensesSection(): array
    {
        $all = $this->expenses();

        return [
            'feed'        => (float) $all->where('category', 'feed')->sum('amount'),
            'electricity' => (float) $all->where('category', 'electricity')->sum('amount'),
            // Rows recorded before the Utility Expenses change (S19) still count.
            'older'       => (float) $all->whereNotIn('category', Expense::ACTIVE_CATEGORIES)->sum('amount'),
            'total'       => (float) $all->sum('amount'),
            'rows'        => $all,
        ];
    }

    public function culls(): array
    {
        $culls = CullRecord::with('henBatch')->whereHas('henBatch', fn ($q) => $q->tracked())
            ->whereDate('date', '>=', $this->start)->whereDate('date', '<=', $this->end)->orderBy('date')->get();
        $mortalityByBuilding = BuildingDaily::with('henBatch')->whereDate('date', '>=', $this->start)->whereDate('date', '<=', $this->end)
            ->get()->groupBy('hen_batch_id')->map(fn ($rows) => [
                'building'  => $rows->first()->henBatch?->display_label ?? '—',
                'mortality' => (int) $rows->sum('mortality'),
            ])->values();

        return [
            'rows'                  => $culls,
            'total_culled'          => (int) $culls->sum('quantity_culled'),
            'total_mortality'       => (int) $this->productions()->sum('mortality'),
            'mortality_by_building' => $mortalityByBuilding,
        ];
    }

    public function alerts()
    {
        return AnomalyAlert::whereDate('alert_date', '>=', $this->start)->whereDate('alert_date', '<=', $this->end)
            ->orderBy('alert_date')->get();
    }

    public function forecast(): array
    {
        $forecast = (new ForecastService)->forecast();

        return [
            'current'     => $forecast,
            'evaluations' => ForecastEvaluation::latest('evaluated_at')->take(10)->get(),
            // Only today's already-generated insight, from cache: an export
            // never calls the AI provider itself.
            'ai_insight'  => Cache::get('ai_insight_' . Carbon::today()->format('Y-m-d')),
        ];
    }

    /** Unresolved inconsistency flags raised in the period (Manila days). */
    public function flags()
    {
        return AuditLog::with('user')->where('inconsistency_flagged', true)->where('resolved', false)
            ->whereBetween('created_at', [
                Carbon::parse($this->start, ManilaDate::TZ)->startOfDay()->utc(),
                Carbon::parse($this->end, ManilaDate::TZ)->endOfDay()->utc(),
            ])->orderBy('created_at')->get();
    }

    /** PNG charts (data URIs) for the PDF. */
    public function charts(): array
    {
        $daily = $this->dailyData();
        $labels = $daily->pluck('date')->all();

        return [
            'production' => ChartImage::render('Egg Production Trend', 'Date', 'Eggs collected', $labels, [
                ['label' => 'Eggs collected', 'values' => $daily->pluck('eggs')->all(), 'color' => [76, 175, 80], 'type' => 'line'],
            ]),
            'revenue' => ChartImage::render('Sales Revenue Trend', 'Date', 'Revenue (PHP)', $labels, [
                ['label' => 'Revenue (PHP)', 'values' => $daily->pluck('revenue')->all(), 'color' => [33, 150, 243], 'type' => 'bar'],
            ], 'P'),
        ];
    }

    /** "Basis & definitions" footer, with the thresholds in effect (item 5). */
    public function definitions(): array
    {
        $t = Thresholds::all();
        $f = fn ($v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return [
            'Prod Rate'     => 'Eggs collected ÷ hens (population) × 100 for the day; averages are the mean of daily rates.',
            'Remaining'     => 'Eggs collected − eggs sold − eggs spoiled.',
            'Sell-through'  => 'Eggs sold ÷ eggs collected × 100.',
            'Healthy'       => "Prod rate ≥ {$f($t['prod_rate_healthy_min'])}%. Basis: " . Thresholds::basis('prod_rate_healthy_min'),
            'Watch'         => "Prod rate between {$f($t['prod_rate_cull_max'])}% and {$f($t['prod_rate_healthy_min'])}%.",
            'Cull-consideration' => "Prod rate ≤ {$f($t['prod_rate_cull_max'])}%. Basis: " . Thresholds::basis('prod_rate_cull_max'),
            'Units'         => '1 tray = 30 pcs; 1 case = 12 trays = 360 pcs.',
            'Records'       => 'Only approved records are included; entries waiting for approval or rejected are left out.',
        ];
    }

    /** Everything the views need for the ticked sections. */
    public function build(): array
    {
        $data = ['report' => $this, 'startDate' => $this->start, 'endDate' => $this->end, 'period' => $this->period];
        $data['summary']   = $this->summary();   // used by the header totals and the page in any case
        $data['dailyData'] = $this->dailyData();

        foreach ($this->sections as $section) {
            match ($section) {
                'charts'    => $data['charts'] = $this->charts(),
                'buildings' => $data['buildings'] = $this->buildings(),
                'grading'   => $data['grading'] = $this->grading(),
                'sales'     => $data['salesData'] = $this->salesReport()->data(),
                'expenses'  => $data['expenses'] = $this->expensesSection(),
                'culls'     => $data['culls'] = $this->culls(),
                'alerts'    => $data['alerts'] = $this->alerts(),
                'forecast'  => $data['forecast'] = $this->forecast(),
                'batch'     => $data['batches'] = $this->batches(),
                'flags'     => $data['flags'] = $this->flags(),
                default     => null,
            };
        }
        $data['definitions'] = $this->definitions();

        return $data;
    }

    private static function validDate(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
