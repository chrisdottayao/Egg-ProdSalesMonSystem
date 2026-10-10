<?php

namespace Tests\Feature;

use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddBuildingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // 10:00 Manila
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'building_no' => 7, 'batch_id' => '', 'breed' => '', 'batch_size' => 4800,
            'placement_date' => '2026-10-13', 'notes' => 'New flock',
        ];
    }

    public function test_admin_adds_building_7_and_it_appears_in_daily_entry_with_its_starting_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('flock-records.buildings.store'), $this->payload())
            ->assertRedirect(route('flock-records.index'))
            ->assertSessionHasNoErrors();

        $b = HenBatch::where('building_no', 7)->firstOrFail();
        $this->assertSame('Building 7', $b->batch_id);
        $this->assertSame('Dekalb White', $b->breed);
        $this->assertTrue($b->is_tracked);
        $this->assertSame('Active', $b->status);
        $this->assertNull($b->display_no);
        $this->assertSame('2026-10-13', $b->placement_date->toDateString());
        $this->assertSame(4800, (int) $b->batch_size);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('daily-entry.index'))
            ->assertSee('data-hen-batch-id="' . $b->id . '"', false)
            ->assertSee('name="buildings[' . $b->id . '][population]" value="4800"', false);
    }

    public function test_manager_can_add_a_building(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->post(route('flock-records.buildings.store'), $this->payload(['building_no' => 8]))
            ->assertSessionHasNoErrors();
        $this->assertTrue(HenBatch::where('building_no', 8)->exists());
    }

    public function test_duplicate_active_building_number_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        HenBatch::create(['batch_id' => 'HB-OLD', 'batch_size' => 100, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 7, 'is_tracked' => true]);
        // Relabelled building: real 45 shown as "Building 3" also blocks 3.
        HenBatch::create(['batch_id' => 'HB-45', 'batch_size' => 100, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 45, 'display_no' => 3, 'is_tracked' => true]);

        $this->actingAs($admin)->post(route('flock-records.buildings.store'), $this->payload())
            ->assertSessionHasErrors('building_no');
        $this->actingAs($admin)->post(route('flock-records.buildings.store'), $this->payload(['building_no' => 3]))
            ->assertSessionHasErrors('building_no');

        $this->assertSame(1, HenBatch::where('building_no', 7)->count());
    }

    public function test_number_of_an_ended_building_can_be_reused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        HenBatch::create(['batch_id' => 'Building 7', 'batch_size' => 100, 'status' => 'Active', 'entry_date' => '2025-01-01', 'building_no' => 7, 'is_tracked' => true, 'ended_at' => '2026-09-01']);

        $this->actingAs($admin)->post(route('flock-records.buildings.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $new = HenBatch::where('building_no', 7)->whereNull('ended_at')->firstOrFail();
        $this->assertSame('Building 7 (2026-10-13)', $new->batch_id);
    }

    public function test_staff_cannot_add_a_building(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('flock-records.buildings.store'), $this->payload())->assertForbidden();
        $this->actingAs($staff)->get(route('flock-records.index'))->assertOk()->assertDontSee('Add Building');
        $this->assertSame(0, HenBatch::count());
    }
}
