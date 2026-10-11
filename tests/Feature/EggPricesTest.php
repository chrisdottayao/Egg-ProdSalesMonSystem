<?php

namespace Tests\Feature;

use App\Models\EggSale;
use App\Models\Setting;
use App\Models\User;
use App\Support\EggPrices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggPricesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function prices(array $overrides = []): array
    {
        $prices = [];
        foreach (EggPrices::sizes() as $size) {
            $prices[EggPrices::fieldKey($size)] = 9.00;
        }

        return ['egg_prices' => array_merge($prices, $overrides)];
    }

    public function test_admin_saves_prices_per_size(): void
    {
        $this->actingAs($this->admin)->patch(route('settings.update'), $this->prices(['medium' => 7.25, 'no_value' => 3.5]))
            ->assertRedirect(route('settings.index'))->assertSessionHasNoErrors();

        $this->assertSame('7.25', Setting::where('key', 'egg_price_medium')->value('value'));
        $this->assertSame(3.5, EggPrices::for('No Value'));
        $this->assertSame(9.0, EggPrices::for('Large'));
    }

    public function test_saving_prices_leaves_expense_estimates_alone_and_vice_versa(): void
    {
        Setting::set('feed_price_per_bag_estimate', 1600);

        $this->actingAs($this->admin)->patch(route('settings.update'), $this->prices(['large' => 8]))->assertSessionHasNoErrors();
        $this->assertSame('1600', Setting::where('key', 'feed_price_per_bag_estimate')->value('value'));

        $this->actingAs($this->admin)->patch(route('settings.update'), ['feed_price_per_bag_estimate' => 1700, 'pullet_cost_per_head_estimate' => 300])->assertSessionHasNoErrors();
        $this->assertSame(8.0, EggPrices::for('Large'));
    }

    public function test_negative_price_is_rejected(): void
    {
        $this->actingAs($this->admin)->patch(route('settings.update'), $this->prices(['small' => -1]))
            ->assertSessionHasErrors('egg_prices.small');
    }

    public function test_sales_form_carries_the_prices_and_prefills_the_selected_size(): void
    {
        Setting::set('egg_price_large', 8.40);
        Setting::set('egg_price_medium', 7.25);

        $page = $this->actingAs($this->admin)->get(route('sales.index'))->assertOk();
        $page->assertSee('name="price_per_unit" id="pricePerUnit" value="8.40"', false); // Large is the default size
        $page->assertSee('"Medium":7.25', false);
    }

    public function test_blank_price_on_post_is_filled_from_the_setting(): void
    {
        Setting::set('egg_price_medium', 7.25);

        $this->actingAs($this->admin)->post(route('sales.store'), [
            'date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => 100, 'price_per_unit' => '',
        ])->assertSessionHasNoErrors();

        $sale = EggSale::firstOrFail();
        $this->assertSame(7.25, (float) $sale->price_per_unit);
        $this->assertSame(725.0, (float) $sale->total_amount);
    }

    public function test_a_typed_special_price_is_kept(): void
    {
        Setting::set('egg_price_medium', 7.25);

        $this->actingAs($this->admin)->post(route('sales.store'), [
            'date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 6.00,
        ]);

        $this->assertSame(6.0, (float) EggSale::firstOrFail()->price_per_unit);
    }

    public function test_csv_rule_blank_uses_setting_and_given_price_is_kept(): void
    {
        Setting::set('egg_price_jumbo', 10.5);

        $this->assertSame(10.5, EggPrices::priceOrDefault('', 'Jumbo'));
        $this->assertSame(10.5, EggPrices::priceOrDefault('  ', 'Jumbo'));
        $this->assertSame(10.5, EggPrices::priceOrDefault(null, 'Jumbo'));
        $this->assertSame(12.0, EggPrices::priceOrDefault('12', 'Jumbo'));
    }

    public function test_existing_sales_keep_their_price_after_a_price_change(): void
    {
        $sale = EggSale::create(['date' => '2026-10-12', 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7.00, 'total_amount' => 70]);

        $this->actingAs($this->admin)->patch(route('settings.update'), $this->prices(['medium' => 8.00]));

        $this->assertSame(7.0, (float) $sale->fresh()->price_per_unit);
        $this->assertSame(70.0, (float) $sale->fresh()->total_amount);
    }

    public function test_staff_and_manager_cannot_edit_prices(): void
    {
        foreach (['staff', 'manager'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->patch(route('settings.update'), $this->prices(['medium' => 1]))->assertForbidden();
            $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
        }
        $this->assertNull(Setting::where('key', 'egg_price_medium')->value('value'));
    }
}
