<?php

namespace Tests\Unit;

use App\Support\MortalityBands;
use PHPUnit\Framework\TestCase;

class MortalityBandsTest extends TestCase
{
    public function test_band_edges_match_the_expert_scale(): void
    {
        $this->assertSame('routine background level', MortalityBands::forWeeklyPct(0.10)['label']);
        $this->assertSame('elevated', MortalityBands::forWeeklyPct(0.11)['label']);
        $this->assertSame('elevated', MortalityBands::forWeeklyPct(0.20)['label']);
        $this->assertSame('abnormal', MortalityBands::forWeeklyPct(0.21)['label']);
        $this->assertSame('abnormal', MortalityBands::forWeeklyPct(0.50)['label']);
        $this->assertSame('highly abnormal', MortalityBands::forWeeklyPct(0.51)['label']);
        $this->assertSame('highly abnormal', MortalityBands::forWeeklyPct(1.00)['label']);
        $this->assertSame('severe mortality event', MortalityBands::forWeeklyPct(1.01)['label']);
        $this->assertSame('severe mortality event', MortalityBands::forWeeklyPct(2.99)['label']);
        $this->assertSame('extreme, outbreak-level signal', MortalityBands::forWeeklyPct(3.0)['label']);
    }

    public function test_unknown_or_invalid_input_returns_null(): void
    {
        $this->assertNull(MortalityBands::forWeeklyPct(null));
        $this->assertNull(MortalityBands::forWeeklyPct(-1.0));
        $this->assertNull(MortalityBands::forDeaths(10, 0));
        $this->assertNull(MortalityBands::forDeaths(10, null));
    }

    public function test_for_deaths_computes_percentage_of_flock(): void
    {
        // 35 deaths in a 10,000-bird flock = 0.35% -> abnormal.
        $this->assertSame('abnormal', MortalityBands::forDeaths(35, 10000)['label']);
    }
}
