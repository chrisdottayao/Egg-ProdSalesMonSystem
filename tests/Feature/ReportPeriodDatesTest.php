<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPeriodDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_select_gets_manila_based_start_dates(): void
    {
        // 2026-03-30 18:00 UTC is already Mar 31 in Manila; one month back clamps to Feb 28.
        $this->travelTo('2026-03-30 18:00:00');
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get(route('reports.index'))
            ->assertOk()
            ->assertSee("Daily: '2026-03-31'", false)
            ->assertSee("Weekly: '2026-03-24'", false)
            ->assertSee("Monthly: '2026-02-28'", false)
            ->assertSee("end: '2026-03-31'", false);
    }

    public function test_explicit_request_dates_are_kept_on_load(): void
    {
        $this->travelTo('2026-10-24 04:00:00');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('reports.index', ['start_date' => '2026-10-01', 'end_date' => '2026-10-10', 'period' => 'Weekly']))
            ->assertOk()
            ->assertSee('name="start_date" value="2026-10-01"', false)
            ->assertSee('name="end_date" value="2026-10-10"', false)
            ->assertSee('<option value="Weekly" selected>', false);
    }
}
