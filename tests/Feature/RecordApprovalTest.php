<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $manager;
    private HenBatch $b1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // 10:00 Manila
        $this->staff   = User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);
        $this->manager = User::factory()->create(['role' => 'manager', 'name' => 'Mia Manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-A1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    private function staffDailyEntry(int $eggs = 1200)
    {
        return $this->actingAs($this->staff)->post(route('daily-entry.store'), [
            'date' => '2026-10-13', 'buildings' => [$this->b1->id => ['population' => 1500, 'eggs_house' => $eggs]],
        ]);
    }

    private function staffSale()
    {
        return $this->actingAs($this->staff)->post(route('sales.store'), [
            'date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => 100, 'price_per_unit' => 7,
        ]);
    }

    public function test_staff_daily_entry_is_pending_and_excluded_from_totals(): void
    {
        $this->staffDailyEntry()->assertSessionHasNoErrors();

        $row = BuildingDaily::withPending()->firstOrFail();
        $this->assertSame('pending', $row->approval_status);
        $this->assertSame($this->staff->id, $row->user_id);
        $this->assertSame(0, BuildingDaily::count());                         // default queries skip it
        $this->assertNull(EggProduction::whereDate('date', '2026-10-13')->first()); // no rollup
    }

    public function test_approving_includes_the_row_and_reruns_the_rollup(): void
    {
        $this->staffDailyEntry(1234);
        $row = BuildingDaily::withPending()->firstOrFail();

        $this->actingAs($this->manager)->patch(route('approvals.approve', ['building-daily', $row->id]))->assertRedirect();

        $row->refresh();
        $this->assertSame('approved', $row->approval_status);
        $this->assertSame($this->manager->id, $row->approved_by);
        $this->assertNotNull($row->approved_at);
        $this->assertSame(1234, (int) EggProduction::whereDate('date', '2026-10-13')->value('eggs_collected'));

        $log = AuditLog::where('action', 'approve')->where('model_type', 'BuildingDaily')->firstOrFail();
        $this->assertSame($this->manager->id, $log->user_id);
    }

    public function test_staff_sale_is_pending_until_approved(): void
    {
        $this->staffSale();
        $sale = EggSale::withPending()->firstOrFail();
        $this->assertSame('pending', $sale->approval_status);
        $this->assertSame($this->staff->id, $sale->entered_by);
        $this->assertSame(0.0, (float) EggSale::sum('total_amount'));

        $this->actingAs($this->manager)->patch(route('approvals.approve', ['sales', $sale->id]));
        $this->assertSame(700.0, (float) EggSale::sum('total_amount'));
    }

    public function test_admin_and_manager_entries_are_approved_on_save(): void
    {
        $this->actingAs($this->manager)->post(route('sales.store'), [
            'date' => '2026-10-13', 'egg_size' => 'Large', 'quantity' => 10, 'price_per_unit' => 8,
        ]);

        $sale = EggSale::firstOrFail();
        $this->assertSame('approved', $sale->approval_status);
        $this->assertSame($this->manager->id, $sale->approved_by);
    }

    public function test_staff_cannot_approve_or_open_the_queue(): void
    {
        $this->staffSale();
        $sale = EggSale::withPending()->firstOrFail();

        $this->actingAs($this->staff)->get(route('approvals.index'))->assertForbidden();
        $this->actingAs($this->staff)->patch(route('approvals.approve', ['sales', $sale->id]))->assertForbidden();
        $this->assertSame('pending', $sale->fresh()->approval_status);
    }

    public function test_rejection_requires_a_note_and_staff_can_resubmit(): void
    {
        $this->staffSale();
        $sale = EggSale::withPending()->firstOrFail();

        $this->actingAs($this->manager)->patch(route('approvals.reject', ['sales', $sale->id]), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');
        $this->assertSame('pending', $sale->fresh()->approval_status);

        $this->actingAs($this->manager)->patch(route('approvals.reject', ['sales', $sale->id]), ['review_note' => 'Quantity looks doubled']);
        $this->assertSame('rejected', $sale->fresh()->approval_status);
        $this->assertTrue(AuditLog::where('action', 'reject')->where('model_id', $sale->id)->exists());

        // Staff sees it with the note, edits and resubmits → pending again.
        $this->actingAs($this->staff)->get(route('sales.index'))->assertSee('Quantity looks doubled');
        $this->actingAs($this->staff)->patch(route('sales.update', $sale), [
            'date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => 50, 'price_per_unit' => 7,
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending', $sale->fresh()->approval_status);
        $this->assertSame(50, (int) $sale->fresh()->quantity);
    }

    public function test_history_shows_entered_by_status_and_approver_in_manila_time(): void
    {
        $this->staffSale();
        $sale = EggSale::withPending()->firstOrFail();
        $this->actingAs($this->manager)->patch(route('approvals.approve', ['sales', $sale->id]));

        $this->actingAs($this->manager)->get(route('sales.index'))
            ->assertSee('Sam Staff')->assertSee('Approved')->assertSee('Mia Manager')
            ->assertSee('Oct 13, 2026 10:00 AM'); // 02:00 UTC
    }

    public function test_rows_that_existed_before_the_migration_count_as_before(): void
    {
        // Inserted without any approval fields, like rows from before Phase 3.
        DB::table('egg_sales')->insert(['date' => '2026-10-12', 'egg_size' => 'Small', 'quantity' => 10, 'price_per_unit' => 6, 'total_amount' => 60, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(60.0, (float) EggSale::sum('total_amount'));
    }

    public function test_nav_badge_counts_pending_entries_for_managers(): void
    {
        $this->staffSale();
        $this->staffDailyEntry();

        $this->actingAs($this->manager)->get(route('approvals.index'))
            ->assertOk()->assertSee('Pending Approvals')->assertSee('aria-label="2 waiting"', false);
    }
}
