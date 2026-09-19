<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_entry_computes_kg_total_and_amount_from_bags(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)->post(route('expenses.store'), [
            'date'          => '2026-09-19',
            'building_id'   => '',
            'category'      => 'feed',
            'description'   => 'ACE FEEDS layer mash',
            'bags'          => 20,
            'kg_per_bag'    => 50,
            'price_per_bag' => 1500,
            'is_estimated'  => '1',
        ])->assertRedirect(route('expenses.index'));

        $expense = Expense::where('category', 'feed')->firstOrFail();

        $this->assertSame(1000.0, $expense->kg_total);
        $this->assertSame(30000.0, $expense->amount);
        $this->assertSame('bag', $expense->unit);
        $this->assertSame(20.0, $expense->quantity);
        $this->assertTrue($expense->is_estimated);
        $this->assertNull($expense->building_id);
    }

    public function test_feed_entry_without_kg_per_bag_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)->post(route('expenses.store'), [
            'date'        => '2026-09-19',
            'category'    => 'feed',
            'description' => 'ACE FEEDS layer mash',
            'bags'        => 20,
            // kg_per_bag deliberately omitted — must never save a feed row
            // with a null kg_total.
            'price_per_bag' => 1500,
        ])->assertSessionHasErrors('kg_per_bag');

        $this->assertSame(0, Expense::where('category', 'feed')->count());
    }

    public function test_non_feed_category_saves_generic_fields_directly(): void
    {
        $user     = User::factory()->create(['role' => 'staff']);
        $building = HenBatch::create([
            'batch_id' => 'HB-CTRL-TEST', 'batch_size' => 200, 'status' => 'Active', 'entry_date' => '2026-01-01',
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'date'        => '2026-09-19',
            'building_id' => (string) $building->id,
            'category'    => 'electricity',
            'description' => 'Meralco bill',
            'quantity'    => 1,
            'unit'        => 'month',
            'unit_price'  => 8500,
            'amount'      => 8500,
        ])->assertRedirect(route('expenses.index'));

        $expense = Expense::where('category', 'electricity')->firstOrFail();

        $this->assertSame(8500.0, $expense->amount);
        $this->assertNull($expense->kg_total);
        $this->assertSame($building->id, $expense->building_id);
    }

    public function test_index_filters_by_building_and_farm_wide(): void
    {
        $user     = User::factory()->create(['role' => 'staff']);
        $building = HenBatch::create([
            'batch_id' => 'HB-CTRL-TEST-2', 'batch_size' => 200, 'status' => 'Active', 'entry_date' => '2026-01-01',
        ]);

        Expense::create([
            'building_id' => null, 'date' => '2026-09-19', 'category' => 'vaccine',
            'description' => 'Farm-wide vaccine', 'amount' => 1000,
        ]);
        Expense::create([
            'building_id' => $building->id, 'date' => '2026-09-19', 'category' => 'medicine',
            'description' => 'Direct medicine', 'amount' => 500,
        ]);

        $farmWideOnly = $this->actingAs($user)->get(route('expenses.index', ['building_id' => 'farm']));
        $farmWideOnly->assertSee('Farm-wide vaccine');
        $farmWideOnly->assertDontSee('Direct medicine');

        $buildingOnly = $this->actingAs($user)->get(route('expenses.index', ['building_id' => $building->id]));
        $buildingOnly->assertSee('Direct medicine');
        $buildingOnly->assertDontSee('Farm-wide vaccine');
    }
}
