<?php

namespace Tests\Feature;

use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use App\Services\ExpenseAllocator;
use App\Services\ProductionRollupService;
use App\Support\FarmReport;
use App\Support\ProductionCostCaption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 3B item 1: approval columns on egg_grading_daily, expenses,
 * cull_records and egg_productions; pending rows count nowhere.
 */
class ApprovalScopeAllTablesTest extends TestCase
{
    use RefreshDatabase;

    private HenBatch $b1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 02:00:00'); // 10:00 Manila
        $this->b1 = HenBatch::create(['batch_id' => 'HB-A1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);

        // Rows saved the old way (no status given) — they count as before.
        EggProduction::create(['date' => '2026-10-12', 'eggs_collected' => 4000, 'active_hens' => 5000, 'mortality' => 2]);
        EggGradingDaily::create(['date' => '2026-10-12', 'category' => 'Medium', 'cases' => 1, 'trays' => 0, 'pieces' => 0]);
        Expense::create(['date' => '2026-10-12', 'category' => 'feed', 'description' => 'Layer mash', 'amount' => 3000]);
        CullRecord::create(['date' => '2026-10-12', 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 5, 'reason' => 'Sick']);

        // The same again, pending: none of it may show up anywhere.
        $pending = ['approval_status' => 'pending'];
        EggProduction::create($pending + ['date' => '2026-10-13', 'eggs_collected' => 99999, 'active_hens' => 5000, 'mortality' => 777]);
        EggGradingDaily::create($pending + ['date' => '2026-10-13', 'category' => 'Large', 'cases' => 50, 'trays' => 0, 'pieces' => 0]);
        Expense::create($pending + ['date' => '2026-10-13', 'category' => 'feed', 'description' => 'Pending mash', 'amount' => 88888]);
        Expense::create($pending + ['date' => '2026-10-13', 'category' => 'electricity', 'description' => 'Pending bill', 'amount' => 7777, 'building_id' => $this->b1->id]);
        CullRecord::create($pending + ['date' => '2026-10-13', 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 444, 'reason' => 'Sick']);
    }

    public function test_the_four_tables_have_the_approval_columns(): void
    {
        foreach (['egg_grading_daily', 'expenses', 'cull_records', 'egg_productions'] as $table) {
            foreach (['approval_status', 'approved_by', 'approved_at', 'review_note'] as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
        }
        foreach (['egg_grading_daily', 'expenses', 'cull_records'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'entered_by'), "{$table}.entered_by");
        }
        $this->assertTrue(Schema::hasColumn('egg_productions', 'user_id'));
    }

    public function test_old_rows_still_count_and_pending_rows_are_hidden_by_default(): void
    {
        $this->assertSame(1, EggProduction::count());
        $this->assertSame(1, EggGradingDaily::count());
        $this->assertSame(1, Expense::count());
        $this->assertSame(1, CullRecord::count());

        $this->assertSame(2, EggProduction::withPending()->count());
        $this->assertSame(3, Expense::withPending()->count());
        $this->assertSame('approved', EggProduction::first()->approval_status);
    }

    public function test_pending_rows_are_excluded_from_every_report_total(): void
    {
        $report = new FarmReport('2026-10-01', '2026-10-14', 'Daily', array_keys(FarmReport::SECTIONS));
        $data   = $report->build();

        $this->assertSame(4000, $data['summary']['total_eggs_produced']);
        $this->assertSame(2, $data['summary']['total_mortality']);
        $this->assertSame(3000.0, $data['summary']['total_expenses']);
        $this->assertSame(360, $report->grading()['total']);
        $this->assertSame(3000.0, $report->expensesSection()['total']);
        $this->assertSame(5, $report->culls()['total_culled']);
        $this->assertCount(1, $report->dailyData()); // the chart series
    }

    public function test_pending_rows_are_excluded_from_allocation_cost_caption_and_forecast_input(): void
    {
        $alloc = (new ExpenseAllocator)->forBuilding($this->b1->id, '2026-10-01', '2026-10-14');
        $this->assertSame(0.0, $alloc['direct_total']); // the pending building expense is ignored

        $caption = ProductionCostCaption::for('2026-10-01', '2026-10-14');
        $this->assertStringNotContainsString('88,888', json_encode($caption));

        // ForecastService (not modified) reads EggProduction::orderBy('date')->get():
        // the global scope keeps the pending day out of its training data.
        $this->assertSame(['2026-10-12'], EggProduction::orderBy('date')->get()->map(fn ($p) => $p->date->toDateString())->all());
    }

    public function test_dashboard_does_not_show_pending_figures(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('99,999');
    }

    public function test_building_history_check_sees_pending_rows(): void
    {
        $b2 = HenBatch::create(['batch_id' => 'HB-A2', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true]);
        $this->assertFalse($b2->hasHistory());

        CullRecord::create(['approval_status' => 'pending', 'date' => '2026-10-14', 'hen_batch_id' => $b2->id, 'quantity_culled' => 1]);
        $this->assertTrue($b2->hasHistory()); // a pending cull must block a permanent delete too
    }

    public function test_rollup_creates_an_approved_row_and_keeps_a_pending_rows_status(): void
    {
        \App\Models\BuildingDaily::create(['date' => '2026-10-14', 'hen_batch_id' => $this->b1->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 1200, 'eggs_eggroom' => 1200]);
        (new ProductionRollupService)->rollupDate('2026-10-14');
        $this->assertSame('approved', EggProduction::whereDate('date', '2026-10-14')->value('approval_status'));

        \App\Models\BuildingDaily::create(['date' => '2026-10-13', 'hen_batch_id' => $this->b1->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 1100, 'eggs_eggroom' => 1100]);
        (new ProductionRollupService)->rollupDate('2026-10-13');
        $row = EggProduction::withPending()->whereDate('date', '2026-10-13')->sole(); // no duplicate created
        $this->assertSame('pending', $row->approval_status);
        $this->assertSame(1100, (int) $row->eggs_collected);
    }
}
