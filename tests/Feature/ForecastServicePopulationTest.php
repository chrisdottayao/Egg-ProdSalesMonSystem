<?php

namespace Tests\Feature;

use App\Models\EggProduction;
use App\Services\ForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ForecastServicePopulationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 3R — a population-aware (rate-based) model should stay accurate across
     * a sudden population drop (e.g. a building culled), because the drop is
     * a known input, not unexplained variance. Same lay rate (80%) held
     * constant throughout; only active_hens drops for the last 7 days
     * (the holdout window holdoutMape() always tests on). A raw-egg-count
     * model has no way to represent this and would read the whole drop as
     * error — this proves the rate-based fix keeps MAPE low instead.
     */
    public function test_forecast_mape_stays_low_across_a_synthetic_population_drop(): void
    {
        $rate = 0.80;

        // Days 1-33: 1,000 hens. Days 34-40 (the 7-day holdout): 700 hens —
        // same rate both sides, only population changed.
        for ($day = 1; $day <= 33; $day++) {
            EggProduction::create([
                'date'           => Carbon::parse('2026-01-01')->addDays($day),
                'eggs_collected' => (int) round(1000 * $rate),
                'active_hens'    => 1000,
                'mortality'      => 0,
            ]);
        }
        for ($day = 34; $day <= 40; $day++) {
            EggProduction::create([
                'date'           => Carbon::parse('2026-01-01')->addDays($day),
                'eggs_collected' => (int) round(700 * $rate),
                'active_hens'    => 700,
                'mortality'      => 0,
            ]);
        }

        $result = (new ForecastService)->forecast();

        $this->assertTrue($result['active']);
        // A raw-count model facing this same series would score roughly
        // (800-560)/560 ≈ 43% MAPE on the holdout (predicting ~800 against an
        // actual of 560); the rate-based model, told the holdout's actual
        // population, should stay in single digits.
        $this->assertLessThan(5.0, $result['mape']);
    }
}
