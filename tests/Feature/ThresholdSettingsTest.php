<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ProdRateBand;
use App\Support\MortalityBands;
use App\Support\Thresholds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThresholdSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** The current values and bases, with overrides. */
    private function form(array $values = [], array $basis = []): array
    {
        $t = [];
        $b = [];
        foreach (Thresholds::DEFINITIONS as $key => $def) {
            $t[$key] = $values[$key] ?? Thresholds::get($key);
            $b[$key] = $basis[$key] ?? Thresholds::basis($key);
        }

        return ['thresholds' => $t, 'threshold_basis' => $b];
    }

    public function test_defaults_and_honest_basis_wording(): void
    {
        $this->assertSame(80.0, Thresholds::get('prod_rate_healthy_min'));
        $this->assertSame(50.0, Thresholds::get('prod_rate_cull_max'));
        $this->assertSame('Farm-defined cull line. Not taken from a published standard.', Thresholds::basis('prod_rate_cull_max'));
        $this->assertSame('Based on breeder performance guides for white-egg layers (high-production range).', Thresholds::basis('prod_rate_healthy_min'));
    }

    public function test_override_applies_to_prod_rate_band_and_mortality_bands(): void
    {
        $this->assertSame('Watch', ProdRateBand::resolve(78)['label']);

        $this->actingAs($this->admin)->patch(route('settings.thresholds.update'), $this->form([
            'prod_rate_healthy_min' => 75, 'mortality_band_elevated' => 0.15,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('settings.index'));

        $this->assertSame('Healthy', ProdRateBand::resolve(78)['label']);
        $this->assertSame('routine background level', MortalityBands::forWeeklyPct(0.12)['label']);
    }

    public function test_invalid_ordering_and_out_of_range_values_are_rejected(): void
    {
        $this->actingAs($this->admin)->patch(route('settings.thresholds.update'), $this->form(['prod_rate_healthy_min' => 40]))
            ->assertSessionHasErrors('thresholds.prod_rate_healthy_min');
        $this->actingAs($this->admin)->patch(route('settings.thresholds.update'), $this->form(['prod_rate_cull_max' => 120]))
            ->assertSessionHasErrors('thresholds.prod_rate_cull_max');
        $this->actingAs($this->admin)->patch(route('settings.thresholds.update'), $this->form(['mortality_band_high' => 0.15]))
            ->assertSessionHasErrors('thresholds.mortality_band_high');

        $this->assertSame(80.0, Thresholds::get('prod_rate_healthy_min'));
    }

    public function test_non_admins_cannot_edit(): void
    {
        foreach (['manager', 'staff'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->patch(route('settings.thresholds.update'), $this->form(['prod_rate_healthy_min' => 70]))->assertForbidden();
        }
        $this->assertSame(80.0, Thresholds::get('prod_rate_healthy_min'));
    }

    public function test_changes_are_audited_old_to_new(): void
    {
        $this->actingAs($this->admin)->patch(route('settings.thresholds.update'), $this->form(
            ['anomaly_prod_drop_pct' => 15],
            ['prod_rate_cull_max' => 'Farm-defined cull line, confirmed with the owner.'],
        ));

        $log = AuditLog::where('action', 'thresholds_update')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertEquals(['old' => 20.0, 'new' => 15.0], $log->details['changes']['anomaly_prod_drop_pct']);
        $this->assertSame('Farm-defined cull line, confirmed with the owner.', $log->details['changes']['prod_rate_cull_max (basis)']['new']);
    }

    public function test_thresholds_are_shown_read_only_to_staff_on_the_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()->assertSee('Thresholds in use')->assertSee('Healthy: &ge; 80%', false);
    }
}
