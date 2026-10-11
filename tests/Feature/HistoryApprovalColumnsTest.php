<?php

namespace Tests\Feature;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 3B item 6: every history table shows Entered by, Status, Approved
 * by and Approved at (Manila); staff see their own pending rows with a
 * "Waiting for approval" badge, and the newest-entry highlight still works.
 */
class HistoryApprovalColumnsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-14';

    private User $staff;
    private User $other;
    private User $manager;
    private HenBatch $b1;
    private int $sizeIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 02:00:00'); // 10:00 Manila
        $this->staff   = User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);
        $this->other   = User::factory()->create(['role' => 'staff', 'name' => 'Oli Other']);
        $this->manager = User::factory()->create(['role' => 'manager', 'name' => 'Mia Manager']);
        $this->b1 = HenBatch::create(['batch_id' => 'HB-H1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    /** Page => [route, model, attributes for a row, column holding the person]. */
    private function tables(): array
    {
        return [
            'expenses'   => [route('expenses.index'), Expense::class, fn ($tag) => ['date' => self::TODAY, 'category' => 'electricity', 'description' => $tag, 'amount' => 100]],
            'cull'       => [route('cull.index'), CullRecord::class, fn ($tag) => ['date' => self::TODAY, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 2, 'reason' => $tag]],
            'production' => [route('productions.index'), EggProduction::class, fn ($tag) => ['date' => self::TODAY, 'eggs_collected' => 100, 'active_hens' => 200, 'mortality' => 0, 'notes' => $tag]],
            // egg_sales is unique per (date, size), so each row takes the next size.
            'sales'      => [route('sales.index'), EggSale::class, fn ($tag) => ['date' => self::TODAY, 'egg_size' => ['Medium', 'Large', 'Small', 'Jumbo'][$this->sizeIndex++ % 4], 'quantity' => 5, 'price_per_unit' => 7, 'total_amount' => 35, 'notes' => $tag]],
        ];
    }

    public function test_every_history_table_has_the_four_columns_and_shows_approver_in_manila_time(): void
    {
        foreach ($this->tables() as $name => [$url, $model, $attrs]) {
            $model::query()->withoutGlobalScopes()->delete();
            $model::create($attrs("{$name}-approved") + [
                $model::enteredByColumn() => $this->staff->id,
                'approval_status' => 'approved', 'approved_by' => $this->manager->id,
                'approved_at' => Carbon::parse('2026-10-14 01:30:00', 'UTC'),
            ]);

            $page = $this->actingAs($this->manager)->get($url)->assertOk();
            $page->assertSeeInOrder(['Entered by', 'Status', 'Approved by', 'Approved at'], false);
            $page->assertSee('Sam Staff')->assertSee('Mia Manager')->assertSee('Oct 14, 2026 9:30 AM'); // 01:30 UTC = 09:30 Manila
            $page->assertSee('data-approval-status="approved"', false);
        }

        // The per-building view (Daily Entry history) has them too.
        BuildingDaily::create(['approval_status' => 'pending', 'user_id' => $this->staff->id, 'date' => self::TODAY, 'hen_batch_id' => $this->b1->id, 'population' => 1500, 'mortality' => 0, 'net_birds' => 1500, 'eggs_house' => 1200, 'eggs_eggroom' => 1200]);
        $this->actingAs($this->manager)->get(route('productions.index', ['building_id' => $this->b1->id]))
            ->assertSeeInOrder(['Entered by', 'Status', 'Approved by', 'Approved at'], false)
            ->assertSee('Waiting for approval');
    }

    public function test_staff_see_their_own_pending_and_rejected_rows_with_badges_but_not_others(): void
    {
        foreach ($this->tables() as $name => [$url, $model, $attrs]) {
            $col = $model::enteredByColumn();
            $model::create($attrs("{$name}-mine-pending") + [$col => $this->staff->id, 'approval_status' => 'pending']);
            $model::create($attrs("{$name}-mine-rejected") + [$col => $this->staff->id, 'approval_status' => 'rejected', 'review_note' => "Fix {$name} please"]);
            $model::create($attrs("{$name}-theirs-pending") + [$col => $this->other->id, 'approval_status' => 'pending']);

            $page = $this->actingAs($this->staff)->get($url)->assertOk();
            $page->assertSee("{$name}-mine-pending")->assertSee('Waiting for approval');
            $page->assertSee("{$name}-mine-rejected")->assertSee('data-approval-status="rejected"', false)->assertSee("Fix {$name} please");
            $page->assertDontSee("{$name}-theirs-pending");

            // A manager sees everyone's.
            $this->actingAs($this->manager)->get($url)->assertSee("{$name}-theirs-pending");
        }
    }

    public function test_the_newest_entry_highlight_still_marks_a_pending_row(): void
    {
        $this->actingAs($this->staff)->post(route('expenses.store'), ['date' => self::TODAY, 'category' => 'electricity', 'description' => 'brand-new-bill', 'amount' => 900]);

        $html = $this->actingAs($this->staff)->get(route('expenses.index'))->getContent();
        $this->assertMatchesRegularExpression('/<tr[^>]*data-latest[^>]*>(?:(?!<\/tr>).)*brand-new-bill/s', $html);
        $this->assertStringContainsString('Waiting for approval', $html);
    }
}
