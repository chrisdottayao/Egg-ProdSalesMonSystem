<?php

namespace Tests\Feature;

use App\Models\BuildingDaily;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Services\ExpenseAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseAllocatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Two buildings, 300 and 700 birds (30%/70% population share). A single
     * farm-wide expense must be allocated back out in exactly that split —
     * across all buildings, the allocated portions must sum to the original
     * farm-wide total. Building A's own direct expense must affect only
     * Building A.
     */
    public function test_population_allocated_shares_sum_back_to_the_farm_wide_total(): void
    {
        $buildingA = HenBatch::create([
            'batch_id' => 'HB-TEST-A', 'batch_size' => 300, 'status' => 'Active', 'entry_date' => '2026-01-01',
        ]);
        $buildingB = HenBatch::create([
            'batch_id' => 'HB-TEST-B', 'batch_size' => 700, 'status' => 'Active', 'entry_date' => '2026-01-01',
        ]);

        foreach (['2026-06-01', '2026-06-02'] as $date) {
            BuildingDaily::create([
                'date' => $date, 'hen_batch_id' => $buildingA->id, 'population' => 300,
                'mortality' => 0, 'net_birds' => 300, 'eggs_house' => 250, 'eggs_eggroom' => 250,
            ]);
            BuildingDaily::create([
                'date' => $date, 'hen_batch_id' => $buildingB->id, 'population' => 700,
                'mortality' => 0, 'net_birds' => 700, 'eggs_house' => 600, 'eggs_eggroom' => 600,
            ]);
        }

        // Farm-wide expense (building_id null) — should split 30/70 by population.
        Expense::create([
            'building_id' => null, 'date' => '2026-06-01', 'category' => 'vaccine',
            'description' => 'Test vaccine batch', 'amount' => 10000,
            'quantity' => 10, 'unit' => 'bottle', 'unit_price' => 1000,
        ]);

        // Building A also has its own direct expense — must not leak to B.
        Expense::create([
            'building_id' => $buildingA->id, 'date' => '2026-06-01', 'category' => 'medicine',
            'description' => 'Direct med for building A', 'amount' => 500,
        ]);

        $allocator = new ExpenseAllocator();
        $resultA   = $allocator->forBuilding($buildingA->id, '2026-06-01', '2026-06-02');
        $resultB   = $allocator->forBuilding($buildingB->id, '2026-06-01', '2026-06-02');

        $this->assertEqualsWithDelta(0.3, $resultA['population_share'], 0.0001);
        $this->assertEqualsWithDelta(0.7, $resultB['population_share'], 0.0001);

        // The allocated (non-direct) portions across all buildings sum back
        // to the original farm-wide total.
        $this->assertEqualsWithDelta(10000.0, $resultA['allocated_total'] + $resultB['allocated_total'], 0.01);

        $this->assertEqualsWithDelta(500.0 + 3000.0, $resultA['total'], 0.01); // direct + 30% of 10000
        $this->assertEqualsWithDelta(7000.0, $resultB['total'], 0.01);         // 70% of 10000, no direct expense

        $this->assertTrue($resultA['has_estimated_portion']);
        $this->assertTrue($resultB['has_estimated_portion']);
    }
}
