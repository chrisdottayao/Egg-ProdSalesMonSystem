<?php

namespace Tests\Feature;

use App\Models\AnomalyAlert;
use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use App\Support\ProductionAnomalyDetector;
use App\Support\SoldVsProduced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B item 7: every approve / reject / approve-on-save is audited with
 * the record's type and id, and the sold > produced check and the anomaly
 * detectors only ever see approved records.
 */
class ApprovalAuditAndFlagsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-14';

    private User $staff;
    private User $manager;
    private HenBatch $b1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 02:00:00'); // 10:00 Manila
        $this->staff   = User::factory()->create(['role' => 'staff']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-Q1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    private function enterOneOfEach(User $user): void
    {
        $this->actingAs($user);
        $this->post(route('daily-entry.store'), ['date' => self::TODAY, 'buildings' => [$this->b1->id => ['population' => 1500, 'eggs_house' => 1200]], 'grading' => ['Medium' => ['total_pcs' => 360]]])->assertSessionHasNoErrors();
        $this->post(route('sales.store'), ['date' => self::TODAY, 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7])->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), ['date' => self::TODAY, 'category' => 'electricity', 'description' => 'Bill', 'amount' => 900])->assertSessionHasNoErrors();
        $this->post(route('cull.store'), ['date' => self::TODAY, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 2, 'reason' => 'Sick'])->assertSessionHasNoErrors();
    }

    public function test_manager_entries_write_an_auto_approve_row_per_record(): void
    {
        $this->enterOneOfEach($this->manager);

        $records = [
            BuildingDaily::sole(), EggGradingDaily::sole(), EggSale::sole(), Expense::sole(), CullRecord::sole(),
        ];
        foreach ($records as $record) {
            $log = AuditLog::where('action', 'auto_approve')->where('model_type', class_basename($record))->where('model_id', $record->id)->first();
            $this->assertNotNull($log, class_basename($record));
            $this->assertSame($this->manager->id, $log->user_id);
            $this->assertSame('Approved on save', $log->action_label);
        }
        // The rollup's derived farm row isn't someone's approval.
        $this->assertFalse(AuditLog::where('action', 'auto_approve')->where('model_type', 'EggProduction')->exists());
    }

    public function test_staff_entries_write_no_auto_approve_and_reviews_are_audited_once(): void
    {
        $this->enterOneOfEach($this->staff);
        $this->assertFalse(AuditLog::where('action', 'auto_approve')->exists());

        $expense = Expense::pending()->sole();
        $cull    = CullRecord::pending()->sole();
        $this->actingAs($this->manager)->patch(route('approvals.approve', ['expenses', $expense->id]));
        $this->actingAs($this->manager)->patch(route('approvals.reject', ['culls', $cull->id]), ['review_note' => 'Wrong building']);

        $approve = AuditLog::where('action', 'approve')->sole();
        $this->assertSame(['Expense', $expense->id], [$approve->model_type, $approve->model_id]);
        $reject = AuditLog::where('action', 'reject')->sole();
        $this->assertSame(['CullRecord', $cull->id], [$reject->model_type, $reject->model_id]);
        $this->assertSame('Wrong building', $reject->details['note']);

        // No duplicate rows from the models' own update hooks.
        $this->assertFalse(AuditLog::where('action', 'auto_approve')->exists());
        $this->assertSame(0, AuditLog::where('action', 'update')->whereIn('model_type', ['Expense', 'CullRecord'])->count());
    }

    public function test_a_pending_sale_over_production_is_flagged_only_when_approved(): void
    {
        EggProduction::create(['date' => self::TODAY, 'eggs_collected' => 1000, 'active_hens' => 1200, 'mortality' => 0]);

        $this->actingAs($this->staff)->post(route('sales.store'), ['date' => self::TODAY, 'egg_size' => 'Large', 'quantity' => 1500, 'price_per_unit' => 8, 'confirm_sold_gt_produced' => 1])->assertSessionHasNoErrors();
        $this->assertFalse(AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->exists());

        $sale = EggSale::pending()->sole();
        $this->actingAs($this->manager)->patch(route('approvals.approve', ['sales', $sale->id]));

        $flag = AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->sole();
        $this->assertSame($sale->id, $flag->model_id);
        $this->assertSame(1000, $flag->details['produced']);
        $this->assertSame(1500, $flag->details['sold_total']);
        $this->assertSame('checked at approval', $flag->details['confirmed']);
    }

    public function test_pending_records_never_drive_sold_over_produced(): void
    {
        // Only pending production: nothing approved to compare against.
        EggProduction::create(['approval_status' => 'pending', 'date' => self::TODAY, 'eggs_collected' => 100, 'active_hens' => 1200, 'mortality' => 0]);
        $this->assertNull(SoldVsProduced::check(self::TODAY, 5000));

        // Approved production; another pending sale doesn't count toward sold.
        EggProduction::create(['date' => '2026-10-13', 'eggs_collected' => 1000, 'active_hens' => 1200, 'mortality' => 0]);
        EggSale::create(['approval_status' => 'pending', 'date' => '2026-10-13', 'egg_size' => 'Small', 'quantity' => 900, 'price_per_unit' => 6, 'total_amount' => 5400]);
        $this->assertNull(SoldVsProduced::check('2026-10-13', 200));

        // Approving a sale that stays within production raises nothing.
        $this->actingAs($this->manager)->patch(route('approvals.approve', ['sales', EggSale::pending()->sole()->id]));
        $this->assertFalse(AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->exists());
    }

    public function test_anomaly_detectors_ignore_pending_records(): void
    {
        // Seven steady approved days, then a pending day with a big drop and many deaths.
        foreach (range(7, 1) as $n) {
            EggProduction::create(['date' => now('Asia/Manila')->subDays($n)->toDateString(), 'eggs_collected' => 850, 'active_hens' => 1000, 'mortality' => 2]);
        }
        $drop = EggProduction::create(['approval_status' => 'pending', 'date' => self::TODAY, 'eggs_collected' => 300, 'active_hens' => 1000, 'mortality' => 40]);
        $this->assertSame([], ProductionAnomalyDetector::detect(self::TODAY, self::TODAY));

        // A pending routine cull doesn't raise High Culling on the dashboard either.
        foreach (['2026-10-11', '2026-10-12', '2026-10-13'] as $d) {
            CullRecord::create(['date' => $d, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 2]);
        }
        CullRecord::create(['approval_status' => 'pending', 'date' => self::TODAY, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 90]);
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk();
        $this->assertFalse(AnomalyAlert::where('type', 'High Culling')->exists());

        // Once approved, the same day is judged.
        $drop->forceFill(['approval_status' => 'approved'])->saveQuietly();
        $types = array_column(ProductionAnomalyDetector::detect(self::TODAY, self::TODAY), 'type');
        $this->assertContains('Production Drop', $types);
        $this->assertContains('Mortality Spike', $types);
    }
}
