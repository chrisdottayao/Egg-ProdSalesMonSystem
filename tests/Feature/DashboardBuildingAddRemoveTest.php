<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\EggProduction;
use App\Models\HenBatch;
use App\Models\User;
use App\Services\ProductionRollupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBuildingAddRemoveTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private HenBatch $b1;
    private HenBatch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // Manila 2026-10-13
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'Building 1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
        $this->b2 = HenBatch::create(['batch_id' => 'Building 2', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true]);
        foreach ([$this->b1, $this->b2] as $b) {
            BuildingDaily::create(['date' => '2026-10-12', 'hen_batch_id' => $b->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 1000, 'eggs_eggroom' => 1000]);
        }
    }

    public function test_add_from_dashboard_creates_a_tracked_building_in_daily_entry(): void
    {
        $this->actingAs($this->manager)->get(route('dashboard'))->assertSee('+ Add Building');

        $this->actingAs($this->manager)->post(route('flock-records.buildings.store'), [
            'building_no' => 7, 'batch_size' => 3000, 'placement_date' => '2026-10-13', 'return_to' => 'dashboard',
        ])->assertRedirect(route('dashboard'));

        $b7 = HenBatch::where('building_no', 7)->firstOrFail();
        $this->assertTrue($b7->is_tracked);
        $this->actingAs($this->manager)->get(route('daily-entry.index'))->assertSee('data-hen-batch-id="' . $b7->id . '"', false);
        $this->assertTrue(AuditLog::where('model_type', 'HenBatch')->where('model_id', $b7->id)->where('action', 'create')->exists());
    }

    public function test_remove_with_history_ends_it_and_keeps_earlier_totals(): void
    {
        $this->assertSame(2000, (int) EggProduction::whereDate('date', '2026-10-12')->value('eggs_collected'));

        $this->actingAs($this->manager)->post(route('dashboard.buildings.remove', $this->b2), ['mode' => 'end'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('2026-10-13', $this->b2->fresh()->ended_at->toDateString());
        $this->assertNotNull($this->b2->fresh()); // not deleted
        // Earlier totals unchanged, even after a recompute.
        (new ProductionRollupService)->rollupDate('2026-10-12');
        $this->assertSame(2000, (int) EggProduction::whereDate('date', '2026-10-12')->value('eggs_collected'));

        $this->actingAs($this->manager)->get(route('daily-entry.index'))
            ->assertDontSee('data-hen-batch-id="' . $this->b2->id . '"', false);
        $this->actingAs($this->manager)->get(route('dashboard'))->assertSee('Removed buildings');
        $this->assertTrue(AuditLog::where('action', 'remove_building_end')->where('model_id', $this->b2->id)->where('user_id', $this->manager->id)->exists());
    }

    public function test_rollup_ignores_an_ended_building_after_its_end_date(): void
    {
        $this->b2->update(['ended_at' => '2026-10-12']);
        // A stray later row (e.g. from an import) must not count.
        foreach ([$this->b1, $this->b2] as $b) {
            BuildingDaily::create(['date' => '2026-10-13', 'hen_batch_id' => $b->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 700, 'eggs_eggroom' => 700]);
        }

        $this->assertSame(700, (int) EggProduction::whereDate('date', '2026-10-13')->value('eggs_collected'));
        (new ProductionRollupService)->rollupDate('2026-10-12');
        $this->assertSame(2000, (int) EggProduction::whereDate('date', '2026-10-12')->value('eggs_collected')); // the end date itself still counts
    }

    public function test_remove_with_no_records_deletes_and_with_history_refuses_delete(): void
    {
        $empty = HenBatch::create(['batch_id' => 'Building 9', 'batch_size' => 100, 'status' => 'Active', 'entry_date' => '2026-10-13', 'building_no' => 9, 'is_tracked' => true]);

        $this->actingAs($this->manager)->post(route('dashboard.buildings.remove', $empty), ['mode' => 'delete'])->assertRedirect(route('dashboard'));
        $this->assertNull($empty->fresh());
        $this->assertTrue(AuditLog::where('action', 'remove_building_delete')->where('model_id', $empty->id)->exists());

        $this->actingAs($this->manager)->post(route('dashboard.buildings.remove', $this->b1), ['mode' => 'delete'])
            ->assertSessionHas('error');
        $this->assertNotNull($this->b1->fresh());
        $this->assertNull($this->b1->fresh()->ended_at);
    }

    public function test_staff_do_not_see_the_controls_and_get_403(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('dashboard'))->assertDontSee('+ Add Building')->assertDontSee('Remove…');
        $this->actingAs($staff)->post(route('dashboard.buildings.remove', $this->b1), ['mode' => 'end'])->assertForbidden();
        $this->actingAs($staff)->post(route('flock-records.buildings.store'), ['building_no' => 8, 'batch_size' => 10, 'placement_date' => '2026-10-13'])->assertForbidden();
        $this->assertNull($this->b1->fresh()->ended_at);
    }

    public function test_admin_reopens_from_the_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->b2->update(['ended_at' => '2026-10-13']);

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Reopen');
        $this->actingAs($admin)->patch(route('flock-records.hens.reopen', $this->b2), ['return_to' => 'dashboard'])->assertRedirect(route('dashboard'));
        $this->assertNull($this->b2->fresh()->ended_at);

        // Managers do not get Reopen (admin-only, as before).
        $this->b2->update(['ended_at' => '2026-10-13']);
        $this->actingAs($this->manager)->patch(route('flock-records.hens.reopen', $this->b2))->assertForbidden();
    }
}
