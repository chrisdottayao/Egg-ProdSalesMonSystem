<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B item 4: staff may change or delete only their own records that
 * are still pending or were rejected; admin/manager may change approved
 * records, and every such edit is audited old → new.
 */
class StaffEditRightsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-14';

    private User $staff;
    private User $other;
    private User $manager;
    private HenBatch $b1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 02:00:00'); // 10:00 Manila
        $this->staff   = User::factory()->create(['role' => 'staff']);
        $this->other   = User::factory()->create(['role' => 'staff']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-E1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    /** One record of each staff-editable type with the given status and owner, dated $date. */
    private function records(string $status, User $owner, string $date = self::TODAY): array
    {
        $s = ['approval_status' => $status];

        return [
            'sale'     => EggSale::create($s + ['entered_by' => $owner->id, 'date' => $date, 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7, 'total_amount' => 70]),
            'expense'  => Expense::create($s + ['entered_by' => $owner->id, 'date' => $date, 'category' => 'electricity', 'description' => 'Meralco', 'amount' => 500]),
            'prod'     => EggProduction::create($s + ['user_id' => $owner->id, 'date' => $date, 'eggs_collected' => 100, 'active_hens' => 200, 'mortality' => 0]),
            'cull'     => CullRecord::create($s + ['entered_by' => $owner->id, 'date' => $date, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 2, 'reason' => 'Sick']),
            'building' => BuildingDaily::create($s + ['user_id' => $owner->id, 'date' => $date, 'hen_batch_id' => $this->b1->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 1200, 'eggs_eggroom' => 1200]),
        ];
    }

    /** Every edit/update/delete request a staff member could make on $r. */
    private function attempts(array $r, string $date = self::TODAY): array
    {
        return [
            fn () => $this->get(route('sales.edit', $r['sale'])),
            fn () => $this->patch(route('sales.update', $r['sale']), ['date' => $date, 'egg_size' => 'Medium', 'quantity' => 11, 'price_per_unit' => 7]),
            fn () => $this->delete(route('sales.destroy', $r['sale'])),
            fn () => $this->get(route('expenses.edit', $r['expense'])),
            fn () => $this->patch(route('expenses.update', $r['expense']), ['date' => $date, 'category' => 'electricity', 'description' => 'Changed', 'amount' => 1]),
            fn () => $this->delete(route('expenses.destroy', $r['expense'])),
            fn () => $this->get(route('productions.edit', $r['prod'])),
            fn () => $this->patch(route('productions.update', $r['prod']), ['date' => $date, 'eggs_collected' => 1, 'active_hens' => 200, 'mortality' => 0]),
            fn () => $this->delete(route('productions.destroy', $r['prod'])),
            fn () => $this->delete(route('cull.destroy', $r['cull'])),
            fn () => $this->delete(route('productions.buildings.destroy', $r['building'])),
        ];
    }

    public function test_staff_get_403_on_every_edit_or_delete_of_an_approved_record(): void
    {
        $r = $this->records('approved', $this->staff); // even their own
        $eggsBefore = (int) EggProduction::sole()->eggs_collected; // after the building row's own rollup
        $this->actingAs($this->staff);

        foreach ($this->attempts($r) as $attempt) {
            $attempt()->assertForbidden()->assertSee('already approved');
        }

        $this->assertSame(10, (int) EggSale::sole()->quantity);
        $this->assertSame('Meralco', Expense::sole()->description);
        $this->assertSame($eggsBefore, (int) EggProduction::sole()->eggs_collected);
        $this->assertSame(1, CullRecord::count());
        $this->assertSame(1, BuildingDaily::count());
    }

    public function test_staff_cannot_change_someone_elses_pending_record(): void
    {
        $r = $this->records('pending', $this->other);
        $this->actingAs($this->staff);

        foreach ($this->attempts($r) as $attempt) {
            $attempt()->assertForbidden()->assertSee('records you entered yourself');
        }
    }

    public function test_staff_can_edit_and_delete_their_own_pending_records(): void
    {
        $r = $this->records('pending', $this->staff);
        $this->actingAs($this->staff);

        $this->get(route('sales.edit', $r['sale']))->assertOk();
        $this->patch(route('sales.update', $r['sale']), ['date' => self::TODAY, 'egg_size' => 'Medium', 'quantity' => 11, 'price_per_unit' => 7])->assertSessionHasNoErrors();
        $this->assertSame(11, (int) EggSale::withPending()->find($r['sale']->id)->quantity);

        $this->patch(route('productions.update', $r['prod']), ['date' => self::TODAY, 'eggs_collected' => 150, 'active_hens' => 200, 'mortality' => 0])->assertSessionHasNoErrors();
        $this->assertSame(150, (int) EggProduction::withPending()->find($r['prod']->id)->eggs_collected);

        $this->delete(route('expenses.destroy', $r['expense']))->assertRedirect();
        $this->delete(route('cull.destroy', $r['cull']))->assertRedirect();
        $this->delete(route('productions.buildings.destroy', $r['building']))->assertRedirect();
        $this->assertSame(0, Expense::withPending()->count() + CullRecord::withPending()->count() + BuildingDaily::withPending()->count());
    }

    public function test_a_rejected_record_can_be_corrected_on_its_own_date_but_not_moved(): void
    {
        $sale = EggSale::create(['approval_status' => 'rejected', 'review_note' => 'Qty wrong', 'entered_by' => $this->staff->id, 'date' => '2026-10-10', 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7, 'total_amount' => 70]);
        $this->actingAs($this->staff);

        $this->patch(route('sales.update', $sale), ['date' => '2026-10-08', 'egg_size' => 'Medium', 'quantity' => 12, 'price_per_unit' => 7])->assertSessionHasErrors('date');
        $this->patch(route('sales.update', $sale), ['date' => '2026-10-10', 'egg_size' => 'Medium', 'quantity' => 12, 'price_per_unit' => 7])->assertSessionHasNoErrors();

        $fresh = EggSale::withPending()->find($sale->id);
        $this->assertSame('pending', $fresh->approval_status); // resubmitted
        $this->assertSame(12, (int) $fresh->quantity);
    }

    public function test_a_past_pending_record_still_follows_the_today_only_rule(): void
    {
        $r = $this->records('pending', $this->staff, '2026-10-12');
        $this->actingAs($this->staff)->delete(route('expenses.destroy', $r['expense']))->assertForbidden()->assertSee("today's records");
    }

    public function test_manager_can_edit_an_approved_record_and_the_audit_shows_old_and_new(): void
    {
        $r = $this->records('approved', $this->staff);

        $this->actingAs($this->manager)->patch(route('expenses.update', $r['expense']), ['date' => self::TODAY, 'category' => 'electricity', 'description' => 'Meralco', 'amount' => 650])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->patch(route('sales.update', $r['sale']), ['date' => self::TODAY, 'egg_size' => 'Medium', 'quantity' => 12, 'price_per_unit' => 7])->assertSessionHasNoErrors();

        $this->assertSame(650.0, Expense::sole()->amount); // still approved, so it counts
        $log = AuditLog::where('action', 'update')->where('model_type', 'Expense')->latest('id')->firstOrFail();
        $this->assertEquals(500, $log->details['changes']['amount']['old']);
        $this->assertEquals(650, $log->details['changes']['amount']['new']);
        $this->assertSame($this->manager->id, $log->user_id);

        $saleLog = AuditLog::where('action', 'update')->where('model_type', 'EggSale')->latest('id')->firstOrFail();
        $this->assertEquals(['old' => 10, 'new' => 12], $saleLog->details['changes']['quantity']);
    }

    public function test_history_lists_hide_edit_and_delete_on_rows_staff_cannot_change(): void
    {
        $approved = $this->records('approved', $this->staff);
        $own      = Expense::create(['approval_status' => 'pending', 'entered_by' => $this->staff->id, 'date' => self::TODAY, 'category' => 'feed', 'description' => 'Own mash', 'amount' => 10]);

        $page = $this->actingAs($this->staff)->get(route('expenses.index'))->assertOk();
        $page->assertDontSee(route('expenses.edit', $approved['expense']), false);
        $page->assertSee(route('expenses.edit', $own), false);
        $page->assertSee('Approved records can only be changed by an admin or manager.');

        $this->actingAs($this->staff)->get(route('sales.index'))->assertDontSee(route('sales.edit', $approved['sale']), false);
        $this->actingAs($this->manager)->get(route('sales.index'))->assertSee(route('sales.edit', $approved['sale']), false);
    }

    public function test_daily_entry_keeps_approved_rows_read_only_for_staff(): void
    {
        $this->records('approved', $this->manager);
        EggGradingDaily::create(['date' => self::TODAY, 'category' => 'Medium', 'cases' => 1, 'trays' => 0, 'pieces' => 0]);

        $page = $this->actingAs($this->staff)->get(route('daily-entry.index'))->assertOk();
        $page->assertSee('Approved — ask a manager or admin to correct it');
        $page->assertSee('name="buildings[' . $this->b1->id . '][population]" value="1500" readonly', false);

        $unchanged = ['population' => 1500, 'mortality' => 0, 'eggs_house' => 1200, 'eggs_eggroom' => 1200, 'soft_shell' => 0];

        // Resubmitting the same numbers is fine and leaves the rows approved.
        $this->actingAs($this->staff)->post(route('daily-entry.store'), [
            'date' => self::TODAY, 'buildings' => [$this->b1->id => $unchanged], 'grading' => ['Medium' => ['total_pcs' => 360]],
        ])->assertRedirect();
        $this->assertSame('approved', BuildingDaily::withPending()->sole()->approval_status);
        $this->assertSame('approved', EggGradingDaily::withPending()->sole()->approval_status);

        // Changing them, or skipping the building, is refused and changes nothing.
        foreach ([
            ['buildings' => [$this->b1->id => ['eggs_house' => 1300] + $unchanged]],
            ['buildings' => [$this->b1->id => ['choice' => 'skip'] + $unchanged]],
            ['buildings' => [$this->b1->id => $unchanged], 'grading' => ['Medium' => ['total_pcs' => 400]]],
            ['buildings' => [$this->b1->id => $unchanged], 'grading' => ['Medium' => ['total_pcs' => 0]]],
        ] as $payload) {
            $this->actingAs($this->staff)->post(route('daily-entry.store'), ['date' => self::TODAY] + $payload)->assertForbidden();
        }
        $this->assertSame(1200, (int) BuildingDaily::sole()->eggs_house);
        $this->assertSame(360, (int) EggGradingDaily::sole()->total_pcs);

        // A manager may correct it.
        $this->actingAs($this->manager)->post(route('daily-entry.store'), [
            'date' => self::TODAY, 'buildings' => [$this->b1->id => ['eggs_house' => 1300] + $unchanged],
        ])->assertRedirect();
        $this->assertSame(1300, (int) BuildingDaily::sole()->eggs_house);
    }
}
