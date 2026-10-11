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
use App\Support\ApprovalQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B item 3: the Pending Approvals page and its actions cover every
 * record type staff enter.
 */
class PendingApprovalsAllTypesTest extends TestCase
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
        $this->staff   = User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);
        $this->other   = User::factory()->create(['role' => 'staff', 'name' => 'Oli Other']);
        $this->manager = User::factory()->create(['role' => 'manager', 'name' => 'Mia Manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-P1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    /** One pending record of every type, entered by $user on $date. */
    private function pendingOfEveryType(User $user, string $date = self::TODAY): array
    {
        $p = ['approval_status' => 'pending'];

        return [
            'building-daily' => BuildingDaily::create($p + ['user_id' => $user->id, 'date' => $date, 'hen_batch_id' => $this->b1->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 1234, 'eggs_eggroom' => 1234]),
            'grading'        => EggGradingDaily::create($p + ['entered_by' => $user->id, 'date' => $date, 'category' => 'Medium', 'cases' => 1, 'trays' => 0, 'pieces' => 0]),
            'production'     => EggProduction::create($p + ['user_id' => $user->id, 'date' => $date, 'eggs_collected' => 3100, 'active_hens' => 4000, 'mortality' => 1]),
            'sales'          => EggSale::create($p + ['entered_by' => $user->id, 'date' => $date, 'egg_size' => 'Large', 'quantity' => 50, 'price_per_unit' => 8, 'total_amount' => 400]),
            'expenses'       => Expense::create($p + ['entered_by' => $user->id, 'date' => $date, 'category' => 'electricity', 'description' => 'Meralco bill', 'amount' => 900]),
            'culls'          => CullRecord::create($p + ['entered_by' => $user->id, 'date' => $date, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 3, 'reason' => 'Sick']),
        ];
    }

    public function test_page_lists_every_type_with_entered_by_and_values(): void
    {
        $this->pendingOfEveryType($this->staff);

        $page = $this->actingAs($this->manager)->get(route('approvals.index'))->assertOk();
        foreach (array_keys(ApprovalQueue::TYPES) as $type) {
            $page->assertSee('data-approval-type="' . $type . '"', false);
        }
        $page->assertSee('Sam Staff')->assertSee('Meralco bill')->assertSee('Large × 50')->assertSee('Medium: 360 pcs')->assertSee('Waiting for review (6)');
    }

    public function test_approve_each_type_makes_it_count_and_is_audited(): void
    {
        $records = $this->pendingOfEveryType($this->staff);

        foreach ($records as $type => $record) {
            $this->actingAs($this->manager)->patch(route('approvals.approve', [$type, $record->id]))->assertRedirect();
            $fresh = $record::withPending()->find($record->id);
            $this->assertSame('approved', $fresh->approval_status, $type);
            $this->assertSame($this->manager->id, $fresh->approved_by, $type);
            $this->assertTrue(AuditLog::where('action', 'approve')->where('model_type', class_basename($record))->where('model_id', $record->id)->exists(), $type);
        }

        $this->assertSame(1, Expense::count());
        $this->assertSame(1, CullRecord::count());
        $this->assertSame(1, EggGradingDaily::count());
        $this->assertSame(1, EggSale::count());
        $this->assertSame(0, ApprovalQueue::count());
    }

    public function test_approving_a_building_row_reruns_the_rollup(): void
    {
        $records = $this->pendingOfEveryType($this->staff);
        $this->actingAs($this->manager)->patch(route('approvals.approve', ['building-daily', $records['building-daily']->id]));

        // The farm row for the date (here the staff's own, still pending) now carries the approved building total.
        $this->assertSame(1234, (int) EggProduction::withPending()->whereDate('date', self::TODAY)->value('eggs_collected'));
    }

    public function test_reject_each_type_needs_a_note_and_keeps_it_out(): void
    {
        $records = $this->pendingOfEveryType($this->staff);

        foreach ($records as $type => $record) {
            $this->actingAs($this->manager)->patch(route('approvals.reject', [$type, $record->id]), ['review_note' => ''])->assertSessionHasErrors('review_note');
            $this->actingAs($this->manager)->patch(route('approvals.reject', [$type, $record->id]), ['review_note' => "Recount {$type}"])->assertRedirect();

            $fresh = $record::withPending()->find($record->id);
            $this->assertSame('rejected', $fresh->approval_status, $type);
            $this->assertSame("Recount {$type}", $fresh->review_note, $type);
            $this->assertSame(0, $record::count(), $type);
        }
        $this->assertSame(6, AuditLog::where('action', 'reject')->count());
    }

    public function test_bulk_approve_for_a_date_or_a_person(): void
    {
        $this->pendingOfEveryType($this->staff);
        $this->travelTo('2026-10-15 02:00:00');
        $this->pendingOfEveryType($this->other, '2026-10-15');
        $this->assertSame(12, ApprovalQueue::count());

        // No filter: refused, nothing approved.
        $this->actingAs($this->manager)->patch(route('approvals.approve-all'))->assertSessionHasErrors('bulk');
        $this->assertSame(12, ApprovalQueue::count());

        $this->actingAs($this->manager)->patch(route('approvals.approve-all'), ['date' => self::TODAY])->assertSessionHas('success', 'Approved 6 entries.');
        $this->assertSame(6, ApprovalQueue::count());
        $this->assertSame(1, Expense::count());

        $this->actingAs($this->manager)->patch(route('approvals.approve-all'), ['user_id' => $this->other->id])->assertSessionHas('success', 'Approved 6 entries.');
        $this->assertSame(0, ApprovalQueue::count());
        $this->assertSame(12, AuditLog::where('action', 'approve')->count());
    }

    public function test_staff_cannot_approve_reject_or_bulk_approve(): void
    {
        $records = $this->pendingOfEveryType($this->staff);

        $this->actingAs($this->staff)->get(route('approvals.index'))->assertForbidden();
        $this->actingAs($this->staff)->patch(route('approvals.approve', ['expenses', $records['expenses']->id]))->assertForbidden();
        $this->actingAs($this->staff)->patch(route('approvals.reject', ['culls', $records['culls']->id]), ['review_note' => 'x'])->assertForbidden();
        $this->actingAs($this->staff)->patch(route('approvals.approve-all'), ['date' => self::TODAY])->assertForbidden();
        $this->assertSame(6, ApprovalQueue::count());
    }

    public function test_nav_badge_counts_every_type(): void
    {
        $this->pendingOfEveryType($this->staff);
        $this->actingAs($this->manager)->get(route('approvals.index'))->assertSee('aria-label="6 waiting"', false);
    }

    public function test_rejected_record_shows_its_note_to_staff_and_resubmitting_returns_it_to_pending(): void
    {
        $expense = $this->pendingOfEveryType($this->staff)['expenses'];
        $this->actingAs($this->manager)->patch(route('approvals.reject', ['expenses', $expense->id]), ['review_note' => 'Amount should be 950']);

        // Still listed for the staff member (the note itself is shown by item 6's status column).
        $this->actingAs($this->staff)->get(route('expenses.index'))->assertSee('Meralco bill');

        $this->actingAs($this->staff)->patch(route('expenses.update', $expense), ['date' => self::TODAY, 'category' => 'electricity', 'description' => 'Meralco bill', 'amount' => 950])->assertSessionHasNoErrors();
        $fresh = Expense::withPending()->find($expense->id);
        $this->assertSame('pending', $fresh->approval_status);
        $this->assertSame(950.0, $fresh->amount);
        $this->assertSame(1, ApprovalQueue::count() - 5); // back in the queue
    }
}
