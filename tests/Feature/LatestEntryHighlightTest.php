<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LatestEntryHighlightTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-10 08:00:00'));
        $this->user = User::factory()->create(['role' => 'staff']);
    }

    /** Bodies of the <tr data-latest> rows in a rendered page. */
    private function highlightedRows(string $html): array
    {
        preg_match_all('/<tr[^>]*\bdata-latest\b[^>]*>(.*?)<\/tr>/s', $html, $m);

        return $m[1];
    }

    private function postSale(string $notes, string $size)
    {
        return $this->actingAs($this->user)->post(route('sales.store'), [
            'date' => '2026-10-10', 'egg_size' => $size, 'quantity' => 10, 'price_per_unit' => 7, 'notes' => $notes,
        ]);
    }

    public function test_saving_a_sale_highlights_exactly_that_row_and_survives_reload(): void
    {
        $this->postSale('first-sale', 'Small');
        $this->travel(30)->minutes();

        $this->postSale('second-sale', 'Medium')
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('latest_ids');

        $page = $this->actingAs($this->user)->get(route('sales.index'))->getContent();
        $rows = $this->highlightedRows($page);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('second-sale', $rows[0]);
        $this->assertStringContainsString('Latest entry (highlighted until the next one is saved)', $page);
        $this->assertStringContainsString('Added 8:30 AM', $rows[0]);

        // A plain reload, later, with no flash data: still highlighted.
        $this->travel(3)->hours();
        $reload = $this->highlightedRows($this->actingAs($this->user)->get(route('sales.index'))->getContent());
        $this->assertCount(1, $reload);
        $this->assertStringContainsString('second-sale', $reload[0]);
    }

    public function test_saving_another_record_moves_the_highlight(): void
    {
        $this->postSale('older-sale', 'Small');
        $this->travel(10)->minutes();
        $this->postSale('newer-sale', 'Medium');

        $rows = $this->highlightedRows($this->actingAs($this->user)->get(route('sales.index'))->getContent());
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('newer-sale', $rows[0]);
        $this->assertStringNotContainsString('older-sale', implode('', $rows));
    }

    public function test_daily_entry_highlights_the_production_row_it_wrote(): void
    {
        EggProduction::create(['date' => '2026-10-08', 'eggs_collected' => 500, 'active_hens' => 900, 'mortality' => 0]);
        $this->travel(1)->hours();

        $batch = HenBatch::create([
            'batch_id' => 'HB-LATEST', 'batch_size' => 2000, 'status' => 'Active',
            'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true,
        ]);

        $this->actingAs($this->user)->post(route('daily-entry.store'), [
            'date'      => '2026-10-09',
            'buildings' => [$batch->id => ['population' => 1500, 'eggs_house' => 1234, 'skip' => '0']],
        ])->assertSessionHas('latest_ids');

        $written = EggProduction::whereDate('date', '2026-10-09')->firstOrFail();
        $this->assertSame([$written->id], session('latest_ids'));

        $rows = $this->highlightedRows($this->actingAs($this->user)->get(route('productions.index'))->getContent());
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('2026-10-09', $rows[0]);
        $this->assertStringContainsString('1,234', $rows[0]);
    }

    public function test_bulk_import_does_not_take_over_the_highlight(): void
    {
        $this->postSale('manual-sale', 'Medium');
        $this->travel(1)->hours();

        // What EggSaleController::importSales does: bulk insert with now()
        // timestamps (bypassing model events), then one audit log entry.
        EggSale::insert([
            ['date' => '2026-10-09', 'egg_size' => 'Large', 'quantity' => 5, 'price_per_unit' => 8,
             'total_amount' => 40, 'notes' => 'imported-recent', 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2025-01-15', 'egg_size' => 'Large', 'quantity' => 5, 'price_per_unit' => 8,
             'total_amount' => 40, 'notes' => 'imported-old', 'created_at' => now(), 'updated_at' => now()],
        ]);
        AuditLog::create(['user_id' => $this->user->id, 'action' => 'import', 'model_type' => 'SalesCsvImport', 'details' => []]);

        $rows = $this->highlightedRows($this->actingAs($this->user)->get(route('sales.index'))->getContent());
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('manual-sale', $rows[0]);
    }

    public function test_old_dated_rows_never_count_as_latest_entry(): void
    {
        EggSale::create(['date' => '2025-01-15', 'egg_size' => 'Medium', 'quantity' => 1, 'price_per_unit' => 7, 'total_amount' => 7, 'notes' => 'backfill']);

        $page = $this->actingAs($this->user)->get(route('sales.index'))->getContent();
        $this->assertSame([], $this->highlightedRows($page));
        $this->assertStringNotContainsString('Latest entry (highlighted', $page);
    }

    public function test_expense_and_cull_history_highlight_their_own_latest(): void
    {
        $batch = HenBatch::create([
            'batch_id' => 'HB-CULL', 'batch_size' => 2000, 'status' => 'Active',
            'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true,
        ]);

        $this->actingAs($this->user)->post(route('expenses.store'), [
            'date' => '2026-10-10', 'category' => 'electricity', 'description' => 'meralco-oct', 'amount' => 900,
        ]);
        $this->travel(5)->minutes();
        $this->actingAs($this->user)->post(route('cull.store'), [
            'date' => '2026-10-10', 'hen_batch_id' => $batch->id, 'quantity_culled' => 3, 'reason' => 'Age', 'notes' => 'cull-note',
        ]);

        // Saving a cull later does not move the expense highlight.
        $expenseRows = $this->highlightedRows($this->actingAs($this->user)->get(route('expenses.index'))->getContent());
        $this->assertCount(1, $expenseRows);
        $this->assertStringContainsString('meralco-oct', $expenseRows[0]);

        $cullRows = $this->highlightedRows($this->actingAs($this->user)->get(route('cull.index'))->getContent());
        $this->assertCount(1, $cullRows);
        $this->assertStringContainsString('cull-note', $cullRows[0]);
    }
}
