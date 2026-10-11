<?php

namespace Tests\Feature;

use App\Models\AnomalyAlert;
use App\Models\EggProduction;
use App\Models\Setting;
use App\Models\User;
use App\Support\ProductionAnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // Manila 2026-10-13
    }

    /** 7 normal days (Oct 6–12): 850 eggs / 1000 hens = 85%, 2 deaths/day; then Oct 13 as given. */
    private function seedDays(int $eggsToday, int $deathsToday = 2, int $priorDays = 7, int $priorDeaths = 2): void
    {
        for ($i = $priorDays; $i >= 1; $i--) {
            EggProduction::create(['date' => Carbon::parse('2026-10-13')->subDays($i)->toDateString(), 'eggs_collected' => 850, 'active_hens' => 1000, 'mortality' => $priorDeaths]);
        }
        EggProduction::create(['date' => '2026-10-13', 'eggs_collected' => $eggsToday, 'active_hens' => 1000, 'mortality' => $deathsToday]);
    }

    private function types(): array
    {
        return array_column(ProductionAnomalyDetector::detect('2026-10-13', '2026-10-13'), 'type');
    }

    public function test_production_drop_fires_beyond_20_percent_and_not_at_the_boundary(): void
    {
        $this->seedDays(680); // 68% vs 85% = exactly -20%: does not exceed, no alert
        $this->assertSame([], $this->types());

        EggProduction::whereDate('date', '2026-10-13')->update(['eggs_collected' => 679]); // -20.12%
        $this->assertSame(['Production Drop'], $this->types());
    }

    public function test_small_drop_does_not_fire(): void
    {
        $this->seedDays(690); // 69% = -18.8%
        $this->assertSame([], $this->types());
    }

    public function test_production_spike_fires_and_small_rise_does_not(): void
    {
        $this->seedDays(1070); // 107% = +25.9%
        $this->assertSame(['Production Spike'], $this->types());

        EggProduction::whereDate('date', '2026-10-13')->update(['eggs_collected' => 1050]); // +23.5%
        $this->assertSame([], $this->types());
    }

    public function test_mortality_spike_fires_at_double_with_minimum_count(): void
    {
        $this->seedDays(850, deathsToday: 5); // avg 2 → +150% (more than double) and ≥ 3
        $alerts = ProductionAnomalyDetector::detect('2026-10-13', '2026-10-13');
        $this->assertSame(['Mortality Spike'], array_column($alerts, 'type'));
        $this->assertStringContainsString('5 deaths on 2026-10-13 vs a 7-day average of 2.0 per day', $alerts[0]['description']);
    }

    public function test_mortality_below_threshold_or_minimum_does_not_fire(): void
    {
        $this->seedDays(850, deathsToday: 4); // exactly +100%: does not exceed
        $this->assertSame([], $this->types());

        EggProduction::query()->update(['mortality' => 1]);
        EggProduction::whereDate('date', '2026-10-13')->update(['mortality' => 2]); // +100%... and below the minimum of 3
        $this->assertSame([], $this->types());
    }

    public function test_no_history_means_no_alert(): void
    {
        $this->seedDays(100, deathsToday: 50, priorDays: 6); // only 6 prior days
        $this->assertSame([], $this->types());
    }

    public function test_zero_average_does_not_crash(): void
    {
        $this->seedDays(850, deathsToday: 5, priorDeaths: 0);
        $alerts = ProductionAnomalyDetector::detect('2026-10-13', '2026-10-13');
        $this->assertSame(['Mortality Spike'], array_column($alerts, 'type'));
        $this->assertNull($alerts[0]['deviation_pct']);
        $this->assertStringContainsString('after no deaths in the previous 7 days', $alerts[0]['description']);

        // Zero hens on prior days: no production ratio, no crash.
        EggProduction::query()->update(['active_hens' => 0]);
        $this->assertNotContains('Production Drop', $this->types());
    }

    public function test_configured_threshold_is_used(): void
    {
        Setting::set('threshold_anomaly_prod_drop_pct', 10);
        $this->seedDays(750); // -11.8%
        $this->assertSame(['Production Drop'], $this->types());
    }

    public function test_dashboard_stores_the_alert_and_keeps_existing_detectors(): void
    {
        $this->seedDays(400); // -53%: high severity
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $alert = AnomalyAlert::where('type', 'Production Drop')->firstOrFail();
        $this->assertSame('high', $alert->severity);
        $this->assertSame(-52.94, (float) $alert->deviation_pct);
    }
}
