<?php

namespace Tests\Feature;

use App\Models\EggGradingDaily;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyEntryGradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The entries below are dated 2026-09-19; S16 only accepts today (Manila).
        $this->travelTo('2026-09-19 02:00:00');
    }

    private function postEntry(array $grading, string $date = '2026-09-19')
    {
        $user  = User::factory()->create(['role' => 'staff']);
        $batch = HenBatch::create([
            'batch_id' => 'HB-GRADE-TEST', 'batch_size' => 2000, 'status' => 'Active',
            'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true,
        ]);

        return $this->actingAs($user)->post(route('daily-entry.store'), [
            'date'      => $date,
            'buildings' => [$batch->id => ['population' => 1500, 'eggs_house' => 1000, 'skip' => '0']],
            'grading'   => $grading,
        ]);
    }

    public function test_total_pieces_is_split_into_cases_trays_pieces_server_side(): void
    {
        // Browser-sent split is deliberately wrong; the server must ignore it.
        $this->postEntry([
            'Medium' => ['total_pcs' => 1000, 'cases' => 99, 'trays' => 0, 'pieces' => 0],
        ])->assertRedirect();

        $row = EggGradingDaily::where('category', 'Medium')->firstOrFail();
        $this->assertSame(2, (int) $row->cases);
        $this->assertSame(9, (int) $row->trays);
        $this->assertSame(10, (int) $row->pieces);
        $this->assertSame(1000, (int) $row->total_pcs);
    }

    public function test_zero_or_blank_sizes_store_nothing(): void
    {
        $this->postEntry([
            'Medium' => ['total_pcs' => 1000],
            'Large'  => ['total_pcs' => 0],
            'Small'  => ['total_pcs' => ''],
        ])->assertRedirect();

        $this->assertSame(['Medium'], EggGradingDaily::pluck('category')->all());
    }

    public function test_setting_a_size_to_zero_removes_the_previously_saved_row(): void
    {
        EggGradingDaily::create(['date' => '2026-09-19', 'category' => 'Large', 'cases' => 1, 'trays' => 0, 'pieces' => 0]);

        $this->postEntry(['Large' => ['total_pcs' => 0], 'Medium' => ['total_pcs' => 30]])->assertRedirect();

        $this->assertSame(['Medium'], EggGradingDaily::pluck('category')->all());
    }

    public function test_grading_total_equals_sum_of_sizes(): void
    {
        $this->postEntry([
            'Small'  => ['total_pcs' => 361],
            'Medium' => ['total_pcs' => 1000],
            'Large'  => ['total_pcs' => 29],
        ])->assertRedirect();

        $this->assertSame(1390, (int) EggGradingDaily::sum('total_pcs'));
    }

    public function test_grading_section_renders_before_the_buildings_section(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        HenBatch::create(['batch_id' => 'HB-ORDER', 'batch_size' => 100, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);

        $html = $this->actingAs($user)->get(route('daily-entry.index'))->assertOk()->getContent();

        $grading   = strpos($html, 'Egg Grading (farm-wide)');
        $buildings = strpos($html, 'class="border-b last:border-0 building-row"');
        $this->assertNotFalse($grading);
        $this->assertNotFalse($buildings);
        $this->assertLessThan($buildings, $grading);
        // Still one form with one Save.
        $this->assertSame(1, substr_count($html, 'id="dailyEntryForm"'));
    }

    public function test_entry_page_prefills_total_pieces_and_shows_match_indicator(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        EggGradingDaily::create(['date' => '2026-09-19', 'category' => 'Medium', 'cases' => 2, 'trays' => 9, 'pieces' => 10]);

        $this->actingAs($user)->get(route('daily-entry.index', ['date' => '2026-09-19']))
            ->assertOk()
            ->assertSee('name="grading[Medium][total_pcs]" value="1000"', false)
            ->assertSee('id="gradingTotal"', false)
            ->assertSee('id="buildingEggsTotalEcho"', false)
            ->assertSee('id="gradingMatch"', false);
    }
}
