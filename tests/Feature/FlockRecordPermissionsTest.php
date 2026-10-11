<?php

namespace Tests\Feature;

use App\Models\BuildingDaily;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockRecordPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private HenBatch $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00');
        $this->b = HenBatch::create(['batch_id' => 'Building 1', 'batch_size' => 100, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    public function test_staff_can_view_but_not_change_flock_records(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('flock-records.index'))->assertOk()->assertDontSee('>Delete</button>', false);
        $this->actingAs($staff)->post(route('flock-records.hens.store'), ['batch_id' => 'X', 'batch_size' => 1, 'status' => 'Active', 'entry_date' => '2026-10-13'])->assertForbidden();
        $this->actingAs($staff)->patch(route('flock-records.hens.update', $this->b), ['batch_id' => 'X', 'batch_size' => 1, 'status' => 'Culled', 'entry_date' => '2026-10-13'])->assertForbidden();
        $this->actingAs($staff)->delete(route('flock-records.hens.destroy', $this->b))->assertForbidden();
        $this->assertSame('Building 1', $this->b->fresh()->batch_id);
    }

    public function test_a_building_with_history_cannot_be_deleted(): void
    {
        BuildingDaily::create(['date' => '2026-10-12', 'hen_batch_id' => $this->b->id, 'population' => 100, 'mortality' => 0, 'net_birds' => 100, 'eggs_house' => 80, 'eggs_eggroom' => 80]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('flock-records.hens.destroy', $this->b))->assertRedirect(route('flock-records.index'));
        $this->assertNotNull($this->b->fresh());
    }
}
