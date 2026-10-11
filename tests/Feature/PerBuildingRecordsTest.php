<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerBuildingRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private HenBatch $b1;
    private HenBatch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-P1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
        $this->b2 = HenBatch::create(['batch_id' => 'HB-P2', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true]);

        foreach (['2026-10-10' => [1111, 2222], '2026-10-11' => [1112, 2223]] as $date => [$e1, $e2]) {
            BuildingDaily::create(['date' => $date, 'hen_batch_id' => $this->b1->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => $e1, 'eggs_eggroom' => $e1]);
            BuildingDaily::create(['date' => $date, 'hen_batch_id' => $this->b2->id, 'population' => 2500, 'mortality' => 0, 'net_birds' => 2500, 'eggs_house' => $e2, 'eggs_eggroom' => $e2]);
        }
    }

    public function test_filtering_production_history_by_building_returns_only_its_rows(): void
    {
        $page = $this->actingAs($this->admin)->get(route('productions.index', ['building_id' => $this->b1->id]))->assertOk();

        $page->assertSee('1,111')->assertSee('1,112')
            ->assertDontSee('2,222')->assertDontSee('2,223')
            ->assertSee('Building 1');
    }

    public function test_ended_buildings_old_rows_are_still_filterable(): void
    {
        $this->b2->update(['ended_at' => '2026-10-12']);

        $page = $this->actingAs($this->admin)->get(route('productions.index'))->assertOk();
        $page->assertSee('Building 2 (culled Oct 12, 2026)');

        $this->actingAs($this->admin)->get(route('productions.index', ['building_id' => $this->b2->id]))
            ->assertSee('2,222')->assertDontSee('1,111');
    }

    public function test_deleting_a_building_row_is_audit_logged_and_updates_the_farm_total(): void
    {
        $row = BuildingDaily::where('hen_batch_id', $this->b2->id)->whereDate('date', '2026-10-10')->firstOrFail();
        $this->assertSame(1111 + 2222, (int) EggProduction::whereDate('date', '2026-10-10')->value('eggs_collected'));

        $this->actingAs($this->admin)->delete(route('productions.buildings.destroy', $row))
            ->assertRedirect(route('productions.index', ['building_id' => $this->b2->id]));

        $this->assertNull($row->fresh());
        $this->assertSame(1111, (int) EggProduction::whereDate('date', '2026-10-10')->value('eggs_collected'));
        $log = AuditLog::where('model_type', 'BuildingDaily')->where('action', 'delete')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_staff_cannot_delete_a_past_building_row(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $row = BuildingDaily::where('hen_batch_id', $this->b1->id)->firstOrFail();

        $this->actingAs($staff)->delete(route('productions.buildings.destroy', $row))->assertForbidden();
        $this->assertNotNull($row->fresh());
    }

    public function test_admin_can_open_a_past_day_of_a_since_culled_building_to_correct_it(): void
    {
        $this->b2->update(['ended_at' => '2026-10-12']);

        $this->actingAs($this->admin)->get(route('daily-entry.index', ['date' => '2026-10-11']))
            ->assertSee('data-hen-batch-id="' . $this->b2->id . '"', false);
        $this->actingAs($this->admin)->get(route('daily-entry.index', ['date' => '2026-10-12']))
            ->assertDontSee('data-hen-batch-id="' . $this->b2->id . '"', false);
    }

    public function test_cull_list_filters_by_building(): void
    {
        CullRecord::create(['date' => '2026-10-10', 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 3, 'notes' => 'cull-b1']);
        CullRecord::create(['date' => '2026-10-10', 'hen_batch_id' => $this->b2->id, 'quantity_culled' => 4, 'notes' => 'cull-b2']);

        $this->actingAs($this->admin)->get(route('cull.index', ['building_id' => $this->b2->id]))
            ->assertSee('cull-b2')->assertDontSee('cull-b1');
    }

    public function test_dashboard_links_each_building_to_its_records(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee(route('productions.index', ['building_id' => $this->b1->id]), false);
    }
}
