<?php

namespace Tests\Feature;

use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 3B item 2: on every write path, staff entries are pending and
 * admin/manager entries are approved by themselves on save.
 */
class ApprovalWritePathsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-14';

    private HenBatch $b1;
    private HenBatch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 02:00:00'); // 10:00 Manila
        $this->b1 = HenBatch::create(['batch_id' => 'HB-W1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
        $this->b2 = HenBatch::create(['batch_id' => 'HB-W2', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 2, 'is_tracked' => true]);
    }

    public static function roles(): array
    {
        return ['staff' => ['staff', 'pending'], 'manager' => ['manager', 'approved'], 'admin' => ['admin', 'approved']];
    }

    private function assertStatus(string $expected, Model $row, User $user): void
    {
        $this->assertSame($expected, $row->approval_status, class_basename($row));
        $this->assertSame($user->id, (int) $row->getAttribute($row::enteredByColumn()), class_basename($row) . ' entered by');
        if ($expected === 'approved') {
            $this->assertSame($user->id, $row->approved_by, class_basename($row) . ' approved by self');
            $this->assertNotNull($row->approved_at);
        } else {
            $this->assertNull($row->approved_by);
            $this->assertNull($row->approved_at);
        }
    }

    #[DataProvider('roles')]
    public function test_one_daily_entry_submit_sets_buildings_grading_and_cull_alike(string $role, string $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->post(route('daily-entry.store'), [
            'date'      => self::TODAY,
            'buildings' => [
                $this->b1->id => ['population' => 1500, 'eggs_house' => 1200],
                $this->b2->id => ['population' => 1700, 'choice' => 'culled'],
            ],
            'grading'   => ['Medium' => ['total_pcs' => 700], 'Large' => ['total_pcs' => 500]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertStatus($expected, BuildingDaily::withPending()->sole(), $user);
        $this->assertCount(2, EggGradingDaily::withPending()->get());
        EggGradingDaily::withPending()->get()->each(fn ($g) => $this->assertStatus($expected, $g, $user));
        $this->assertStatus($expected, CullRecord::withPending()->sole(), $user);
    }

    #[DataProvider('roles')]
    public function test_forms_for_sales_expenses_culls_and_production(string $role, string $expected): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        $this->post(route('sales.store'), ['date' => self::TODAY, 'egg_size' => 'Medium', 'quantity' => 100, 'price_per_unit' => 7])->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), ['date' => self::TODAY, 'category' => 'electricity', 'description' => 'Meralco', 'amount' => 900])->assertSessionHasNoErrors();
        $this->post(route('cull.store'), ['date' => self::TODAY, 'hen_batch_id' => $this->b1->id, 'quantity_culled' => 3, 'reason' => 'Sick'])->assertSessionHasNoErrors();
        $this->post(route('productions.store'), ['date' => self::TODAY, 'eggs_collected' => 3000, 'active_hens' => 4000, 'mortality' => 1])->assertSessionHasNoErrors();

        $this->assertStatus($expected, EggSale::withPending()->sole(), $user);
        $this->assertStatus($expected, Expense::withPending()->sole(), $user);
        $this->assertStatus($expected, CullRecord::withPending()->sole(), $user);
        $this->assertStatus($expected, EggProduction::withPending()->sole(), $user);
    }

    #[DataProvider('roles')]
    public function test_edits_follow_the_editor(string $role, string $expected): void
    {
        $user  = User::factory()->create(['role' => $role]);
        // The editor's own rejected records (staff resubmit; admin/manager may
        // edit anything), so the edit is allowed for every role.
        $owned = ['approval_status' => 'rejected', 'review_note' => 'Fix it'];
        $expense    = Expense::create($owned + ['entered_by' => $user->id, 'date' => self::TODAY, 'category' => 'feed', 'description' => 'Mash', 'amount' => 100]);
        $production = EggProduction::create($owned + ['user_id' => $user->id, 'date' => self::TODAY, 'eggs_collected' => 100, 'active_hens' => 200, 'mortality' => 0]);

        $this->actingAs($user)->patch(route('expenses.update', $expense), ['date' => self::TODAY, 'category' => 'electricity', 'description' => 'Bill', 'amount' => 500])->assertSessionHasNoErrors();
        $this->actingAs($user)->patch(route('productions.update', $production), ['date' => self::TODAY, 'eggs_collected' => 150, 'active_hens' => 200, 'mortality' => 0])->assertSessionHasNoErrors();

        $this->assertStatus($expected, $expense->fresh(), $user);
        $this->assertStatus($expected, $production->fresh(), $user);
        $this->assertNull($expense->fresh()->review_note); // the old rejection note is cleared on resubmit
    }

    public function test_offline_replays_from_staff_land_pending(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        // public/sw.js replays the queued form body with queued_at appended.
        $queued = ['queued_at' => '2026-10-14T01:00:00Z'];

        $this->actingAs($staff)->post(route('productions.store'), $queued + ['date' => self::TODAY, 'eggs_collected' => 3000, 'active_hens' => 4000, 'mortality' => 0])->assertRedirect();
        $this->actingAs($staff)->post(route('sales.store'), $queued + ['date' => self::TODAY, 'egg_size' => 'Large', 'quantity' => 50, 'price_per_unit' => 8])->assertRedirect();

        $this->assertStatus('pending', EggProduction::withPending()->sole(), $staff);
        $this->assertStatus('pending', EggSale::withPending()->sole(), $staff);
    }

    public function test_csv_imports_by_a_manager_land_approved_and_staff_cannot_import(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff   = User::factory()->create(['role' => 'staff']);

        $building = UploadedFile::fake()->createWithContent('building.csv',
            "date,building_no,population,mortality,net_birds,feed_bags,eggs_house,eggs_eggroom,soft_shell,age_weeks,prod_rate\n2026-10-13,1,1500,0,1500,4,1200,1200,0,90,80\n");
        $grading = UploadedFile::fake()->createWithContent('grading.csv',
            "date,category,cases,trays,pieces,total_pcs\n2026-10-13,Medium,1,0,0,360\n");
        $historical = UploadedFile::fake()->createWithContent('history.csv',
            "date,eggs_collected,active_hens,mortality_count,feed_bags,feed_cost_per_bag,eggs_sold,price_per_unit,culled_count,cull_reason,notes\n2026-10-01,3000,4000,0,,,,,2,Age,\n");

        foreach ([['imports.building-daily.import', $building], ['imports.egg-grading.import', $grading], ['productions.import.historical', $historical]] as [$route, $file]) {
            $this->actingAs($staff)->post(route($route), ['csv_file' => $file])->assertForbidden();
            $this->actingAs($manager)->post(route($route), ['csv_file' => $file])->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertStatus('approved', BuildingDaily::withPending()->sole(), $manager);
        $this->assertStatus('approved', EggGradingDaily::withPending()->sole(), $manager);
        $this->assertStatus('approved', CullRecord::withPending()->sole(), $manager);
        $imported = EggProduction::withPending()->whereDate('date', '2026-10-01')->sole();
        $this->assertSame('approved', $imported->approval_status);
        $this->assertNull($imported->user_id); // historical rows stay unattributed
    }
}
