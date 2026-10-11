<?php

namespace Tests\Feature;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\HenBatch;
use App\Models\User;
use App\Support\FarmReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B item 5: a staff cull — and the building end it brings — waits
 * for approval. Approving applies it exactly as a manager's cull would;
 * rejecting changes nothing.
 */
class CullApprovalEffectsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-14';

    private User $staff;
    private User $manager;
    private HenBatch $b1;
    private HenBatch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 02:00:00'); // 10:00 Manila
        $this->staff   = User::factory()->create(['role' => 'staff']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-K1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
        $this->b2 = HenBatch::create(['batch_id' => 'HB-K2', 'batch_size' => 1800, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true]);

        // Today's approved rows for both buildings (entered earlier by the manager).
        foreach ([[$this->b1, 1200], [$this->b2, 1000]] as [$b, $eggs]) {
            BuildingDaily::create(['approval_status' => 'approved', 'user_id' => $this->manager->id, 'date' => self::TODAY, 'hen_batch_id' => $b->id, 'population' => 1700, 'mortality' => 0, 'net_birds' => 1700, 'eggs_house' => $eggs, 'eggs_eggroom' => $eggs]);
        }
    }

    private function staffMarksB2Culled(): void
    {
        $this->actingAs($this->staff)->post(route('daily-entry.store'), [
            'date' => self::TODAY, 'buildings' => [$this->b2->id => ['population' => 1700, 'choice' => 'culled']],
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function cullTotal(): int
    {
        return (new FarmReport('2026-10-01', self::TODAY, 'Daily', ['culls']))->culls()['total_culled'];
    }

    public function test_staff_cull_leaves_the_building_active_and_counts_unchanged(): void
    {
        $hensBefore = HenBatch::activeHenCount();
        $this->staffMarksB2Culled();

        $cull = CullRecord::withPending()->sole();
        $this->assertSame('pending', $cull->approval_status);
        $this->assertNull($this->b2->fresh()->ended_at);
        $this->assertSame($hensBefore, HenBatch::activeHenCount());
        $this->assertSame(2, BuildingDaily::whereDate('date', self::TODAY)->count()); // its row for today stays
        $this->assertSame(2200, (int) EggProduction::whereDate('date', self::TODAY)->value('eggs_collected'));
        $this->assertSame(0, $this->cullTotal());

        // Still on the form, marked as waiting.
        $this->actingAs($this->staff)->get(route('daily-entry.index'))
            ->assertSee('data-hen-batch-id="' . $this->b2->id . '"', false)
            ->assertSee('Cull waiting for approval');

        // Marking it culled again the same day doesn't queue a second cull.
        $this->staffMarksB2Culled();
        $this->assertSame(1, CullRecord::withPending()->count());
    }

    public function test_approving_ends_the_building_and_updates_the_totals(): void
    {
        $this->staffMarksB2Culled();
        $cull = CullRecord::withPending()->sole();

        $this->actingAs($this->manager)->patch(route('approvals.approve', ['culls', $cull->id]))->assertRedirect();

        $this->assertSame(self::TODAY, $this->b2->fresh()->ended_at->toDateString());
        $this->assertSame(0, BuildingDaily::withPending()->where('hen_batch_id', $this->b2->id)->count());
        $this->assertSame(1200, (int) EggProduction::whereDate('date', self::TODAY)->value('eggs_collected')); // rollup re-ran
        $this->assertSame(1700, $this->cullTotal());
        $this->assertSame(2000, HenBatch::activeHenCount()); // only building 1 left

        $this->actingAs($this->staff)->get(route('daily-entry.index'))
            ->assertDontSee('data-hen-batch-id="' . $this->b2->id . '"', false);
    }

    public function test_rejecting_changes_nothing(): void
    {
        $this->staffMarksB2Culled();
        $cull = CullRecord::withPending()->sole();

        $this->actingAs($this->manager)->patch(route('approvals.reject', ['culls', $cull->id]), ['review_note' => 'Building 2 is still laying'])->assertRedirect();

        $this->assertNull($this->b2->fresh()->ended_at);
        $this->assertSame(2, BuildingDaily::whereDate('date', self::TODAY)->count());
        $this->assertSame(2200, (int) EggProduction::whereDate('date', self::TODAY)->value('eggs_collected'));
        $this->assertSame(0, $this->cullTotal());
        $this->assertSame(3800, HenBatch::activeHenCount());
    }

    public function test_bulk_approval_applies_the_cull_too(): void
    {
        $this->staffMarksB2Culled();
        $this->actingAs($this->manager)->patch(route('approvals.approve-all'), ['date' => self::TODAY])->assertSessionHas('success');
        $this->assertNotNull($this->b2->fresh()->ended_at);
    }

    public function test_cull_page_entry_by_staff_counts_only_once_approved(): void
    {
        $this->actingAs($this->staff)->post(route('cull.store'), ['date' => self::TODAY, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 4, 'reason' => 'Sick'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->cullTotal());
        $this->assertNull($this->b1->fresh()->ended_at); // a routine cull never ends a building

        $cull = CullRecord::withPending()->sole();
        $this->actingAs($this->manager)->patch(route('approvals.approve', ['culls', $cull->id]));
        $this->assertSame(4, $this->cullTotal());
        $this->assertNull($this->b1->fresh()->ended_at);
    }
}
