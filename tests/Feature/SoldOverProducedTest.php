<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\User;
use App\Support\SoldVsProduced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoldOverProducedTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // 10:00 Manila
        $this->staff   = User::factory()->create(['role' => 'staff']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        EggProduction::create(['date' => '2026-10-13', 'eggs_collected' => 1000, 'active_hens' => 1200, 'mortality' => 0]);
        EggSale::create(['date' => '2026-10-13', 'egg_size' => 'Small', 'quantity' => 700, 'price_per_unit' => 6, 'total_amount' => 4200]);
    }

    private function sale(int $qty, array $extra = [])
    {
        return $this->actingAs($this->staff)->post(route('sales.store'), [
            'date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => $qty, 'price_per_unit' => 7,
        ] + $extra);
    }

    public function test_warning_appears_and_unconfirmed_submit_is_not_saved(): void
    {
        $this->sale(400)->assertRedirect()->assertSessionHas('sold_gt_produced');
        $this->assertSame(1, EggSale::withPending()->count());

        $this->from(route('sales.index'))->followingRedirects()->sale(400)
            ->assertSee('Sold is higher than produced')
            ->assertSee('1,100 eggs sold (700 already recorded + 400 in this entry) vs 1,000 eggs produced', false);
    }

    public function test_confirmed_submit_saves_and_flags_with_the_numbers(): void
    {
        $this->sale(400, ['confirm_sold_gt_produced' => 1])->assertSessionHasNoErrors()->assertSessionMissing('sold_gt_produced');

        $sale = EggSale::withPending()->where('egg_size', 'Medium')->firstOrFail();
        $flag = AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->firstOrFail();
        $this->assertTrue($flag->inconsistency_flagged);
        $this->assertFalse($flag->resolved);
        $this->assertSame($sale->id, $flag->model_id);
        $this->assertSame(1000, $flag->details['produced']);
        $this->assertSame(1100, $flag->details['sold_total']);
        $this->assertSame('yes', $flag->details['confirmed']);

        $this->actingAs($this->manager)->get(route('sales.index'))->assertSee('Sold &gt; produced', false);
    }

    public function test_offline_replay_is_saved_and_flagged_not_bounced(): void
    {
        // The PWA treats a redirect as success; bouncing would lose the entry.
        $this->sale(400, ['queued_at' => '2026-10-13T01:00:00Z'])->assertSessionMissing('sold_gt_produced');

        $this->assertSame(2, EggSale::withPending()->count());
        $this->assertSame('no (synced from offline)', AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->value('details')['confirmed']);
    }

    public function test_manager_resolves_the_flag_with_a_note(): void
    {
        $this->sale(400, ['confirm_sold_gt_produced' => 1]);
        $flag = AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->firstOrFail();

        $this->actingAs($this->staff)->patch(route('audit-flags.resolve', $flag), ['resolution_note' => 'x'])->assertForbidden();
        $this->actingAs($this->manager)->patch(route('audit-flags.resolve', $flag), ['resolution_note' => ''])->assertSessionHasErrors('resolution_note');

        $this->actingAs($this->manager)->patch(route('audit-flags.resolve', $flag), ['resolution_note' => 'Carry-over stock from Oct 12'])->assertRedirect();
        $flag->refresh();
        $this->assertTrue($flag->resolved);
        $this->assertSame($this->manager->id, $flag->resolved_by);
        $this->assertSame('Carry-over stock from Oct 12', $flag->resolution_note);
        $this->assertTrue(AuditLog::where('action', 'resolve_flag')->where('model_id', $flag->model_id)->exists());
    }

    public function test_no_warning_or_flag_when_sold_is_within_produced(): void
    {
        $this->sale(300)->assertSessionMissing('sold_gt_produced'); // 700 + 300 = 1000, not more

        $this->assertSame(2, EggSale::withPending()->count());
        $this->assertFalse(AuditLog::where('inconsistency_rule', SoldVsProduced::RULE)->exists());
    }

    public function test_rejected_sales_do_not_count_toward_sold(): void
    {
        EggSale::withPending()->first()->forceFill(['approval_status' => 'rejected'])->save();

        $this->sale(400)->assertSessionMissing('sold_gt_produced');
    }

    public function test_editing_a_sale_upward_also_warns(): void
    {
        $this->actingAs($this->manager);
        $sale = EggSale::first();
        $this->patch(route('sales.update', $sale), ['date' => '2026-10-13', 'egg_size' => 'Small', 'quantity' => 1200, 'price_per_unit' => 6])
            ->assertSessionHas('sold_gt_produced');
        $this->assertSame(700, (int) $sale->fresh()->quantity);
    }
}
