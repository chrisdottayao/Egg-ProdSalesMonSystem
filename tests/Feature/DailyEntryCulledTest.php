<?php

namespace Tests\Feature;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyEntryCulledTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private HenBatch $b1;
    private HenBatch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // 10:00 Manila
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-C1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
        $this->b2 = HenBatch::create(['batch_id' => 'HB-C2', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true]);
    }

    private function postEntry(array $buildings)
    {
        return $this->actingAs($this->staff)->post(route('daily-entry.store'), [
            'date' => '2026-10-13', 'buildings' => $buildings,
        ]);
    }

    public function test_culled_creates_one_cull_record_sets_ended_at_and_hides_the_building(): void
    {
        $this->postEntry([
            $this->b1->id => ['population' => 1500, 'eggs_house' => 1200, 'choice' => ''],
            $this->b2->id => ['population' => 1750, 'choice' => 'culled'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, CullRecord::count());
        $cull = CullRecord::first();
        $this->assertSame($this->b2->id, $cull->hen_batch_id);
        $this->assertSame(1750, (int) $cull->quantity_culled);
        $this->assertSame('Building culled (daily entry)', $cull->reason);

        $this->assertSame('2026-10-13', $this->b2->fresh()->ended_at->toDateString());
        $this->assertSame(0, BuildingDaily::where('hen_batch_id', $this->b2->id)->count());
        $this->assertSame(1, BuildingDaily::where('hen_batch_id', $this->b1->id)->count());

        $page = $this->actingAs($this->staff)->get(route('daily-entry.index'));
        $page->assertSee('data-hen-batch-id="' . $this->b1->id . '"', false);
        $page->assertDontSee('data-hen-batch-id="' . $this->b2->id . '"', false);
    }

    public function test_posting_a_row_for_an_ended_building_is_ignored(): void
    {
        $this->b2->update(['ended_at' => '2026-10-10']);

        $this->postEntry([
            $this->b2->id => ['population' => 1500, 'eggs_house' => 1200],
        ])->assertRedirect();

        $this->assertSame(0, BuildingDaily::where('hen_batch_id', $this->b2->id)->count());
    }

    public function test_skip_still_writes_no_row(): void
    {
        $this->postEntry([
            $this->b1->id => ['choice' => 'skip'],
            $this->b2->id => ['skip' => '1'], // legacy field still means skip
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, BuildingDaily::count());
        $this->assertSame(0, CullRecord::count());
        $this->assertNull($this->b1->fresh()->ended_at);
    }

    public function test_culled_requires_population(): void
    {
        $this->postEntry([$this->b2->id => ['choice' => 'culled']])
            ->assertSessionHasErrors("buildings.{$this->b2->id}.population");

        $this->assertNull($this->b2->fresh()->ended_at);
    }

    public function test_admin_can_reopen_a_culled_building_and_staff_cannot(): void
    {
        $this->b2->update(['ended_at' => '2026-10-10']);

        $this->actingAs($this->staff)->patch(route('flock-records.hens.reopen', $this->b2))->assertForbidden();
        $this->assertNotNull($this->b2->fresh()->ended_at);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('flock-records.hens.reopen', $this->b2))->assertRedirect(route('flock-records.index'));
        $this->assertNull($this->b2->fresh()->ended_at);

        $this->actingAs($this->staff)->get(route('daily-entry.index'))
            ->assertSee('data-hen-batch-id="' . $this->b2->id . '"', false);
    }

    public function test_ended_building_is_shown_as_culled_on_the_dashboard(): void
    {
        $this->b2->update(['ended_at' => '2026-10-10']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Culled — Oct 10, 2026');
    }

    public function test_ended_building_is_excluded_from_entry_dropdowns_but_kept_in_filters(): void
    {
        $this->b2->update(['ended_at' => '2026-10-10']);

        $cull = $this->actingAs($this->staff)->get(route('cull.index'))->getContent();
        $this->assertStringNotContainsString('value="' . $this->b2->id . '"', $cull);

        $expenses = $this->actingAs($this->staff)->get(route('expenses.index'))->getContent();
        // Once in the filter dropdown (marked culled), not in the entry form.
        $this->assertSame(1, substr_count($expenses, 'value="' . $this->b2->id . '"'));
        $this->assertStringContainsString('(culled)', $expenses);
    }
}
