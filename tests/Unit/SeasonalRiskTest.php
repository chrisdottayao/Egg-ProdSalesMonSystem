<?php

namespace Tests\Unit;

use App\Support\SeasonalRisk;
use PHPUnit\Framework\TestCase;

class SeasonalRiskTest extends TestCase
{
    public function test_each_month_maps_to_expected_level(): void
    {
        $expected = [
            1 => 'green', 2 => 'green',
            3 => 'red', 4 => 'red', 5 => 'red',
            6 => 'orange', 7 => 'orange', 8 => 'orange', 9 => 'orange',
            10 => 'green', 11 => 'green', 12 => 'green',
        ];
        foreach ($expected as $month => $color) {
            $this->assertSame($color, SeasonalRisk::forMonth($month)['color'], "month {$month}");
        }
    }

    public function test_october_is_generally_lower_risk(): void
    {
        $this->assertStringContainsString('lower', SeasonalRisk::forMonth(10)['level']);
    }
}
