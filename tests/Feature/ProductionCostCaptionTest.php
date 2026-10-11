<?php

namespace Tests\Feature;

use App\Models\EggProduction;
use App\Models\Expense;
use App\Models\User;
use App\Support\ProductionCostCaption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCostCaptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00');
        EggProduction::create(['date' => '2026-10-10', 'eggs_collected' => 6000, 'active_hens' => 7000, 'mortality' => 0]);
        EggProduction::create(['date' => '2026-10-11', 'eggs_collected' => 4000, 'active_hens' => 7000, 'mortality' => 0]);
    }

    public function test_without_feed_expense_it_says_so_and_invents_nothing(): void
    {
        Expense::create(['date' => '2026-10-10', 'category' => 'electricity', 'description' => 'Bill', 'amount' => 900]);

        $c = ProductionCostCaption::for('2026-09-14', '2026-10-13');
        $this->assertFalse($c['has_cost']);
        $this->assertSame('No feed expense recorded for this period.', $c['cost']);
        $this->assertStringContainsString('10,000 eggs in total', $c['shows']);
    }

    public function test_with_feed_expense_it_shows_cost_per_egg(): void
    {
        Expense::create(['date' => '2026-10-01', 'category' => 'feed', 'description' => 'Layer mash', 'amount' => 15000, 'quantity' => 10, 'unit' => 'bag', 'unit_price' => 1500, 'kg_total' => 500]);
        Expense::create(['date' => '2026-08-01', 'category' => 'feed', 'description' => 'Outside the period', 'amount' => 99999, 'quantity' => 1, 'unit' => 'bag', 'unit_price' => 99999, 'kg_total' => 50]);

        $c = ProductionCostCaption::for('2026-09-14', '2026-10-13');
        $this->assertTrue($c['has_cost']);
        $this->assertStringContainsString('₱15,000.00', $c['cost']);
        $this->assertStringContainsString('₱1.50 per egg', $c['cost']);
    }

    public function test_dashboard_shows_the_caption_under_the_production_trend(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('data-production-caption', false)->assertSee('No feed expense recorded for this period.');
    }
}
