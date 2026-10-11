<?php

namespace Tests\Feature;

use App\Http\Controllers\EggSaleController;
use App\Models\BuildingDaily;
use App\Models\EggGradingDaily;
use App\Models\EggSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CombinedImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);

        // The real sales importer finishes with a MySQL-only UPDATE ... JOIN
        // (backfilling production_id) that SQLite can't run. Stand in for it
        // with the same contract — read csv_file, save rows in a transaction,
        // flash import_summary — so the combined panel's flow is what's tested.
        $this->app->instance(EggSaleController::class, new class extends EggSaleController {
            public function importSales(Request $request)
            {
                $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);
                $rows = array_map('str_getcsv', file($request->file('csv_file')->getPathname(), FILE_IGNORE_NEW_LINES));
                $header = array_shift($rows);
                if ($header !== ['date', 'egg_size', 'quantity_sold', 'price_per_unit', 'notes']) {
                    throw new \RuntimeException('Unexpected sales CSV header.');
                }
                DB::transaction(function () use ($rows) {
                    foreach ($rows as [$date, $size, $qty, $price, $notes]) {
                        EggSale::create(['date' => $date, 'egg_size' => $size, 'quantity' => (int) $qty, 'price_per_unit' => (float) $price, 'total_amount' => $qty * $price, 'notes' => $notes]);
                    }
                });

                return redirect()->route('sales.index')->with(['import_summary' => ['imported' => count($rows), 'skipped' => 0, 'failed' => 0], 'import_error_token' => null]);
            }
        });
    }

    private function buildingCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('building.csv',
            "date,building_no,population,mortality,net_birds,feed_bags,eggs_house,eggs_eggroom,soft_shell,age_weeks,prod_rate\n" .
            "2025-03-01,4,4800,1,4799,12,4100,4100,3,60,85.42\n" .
            "2025-03-02,4,4799,0,4799,12,4120,4120,2,60,85.85\n");
    }

    private function gradingCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('grading.csv',
            "date,category,cases,trays,pieces,total_pcs\n" .
            "2025-03-01,Medium,2,9,10,1000\n");
    }

    private function salesCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sales.csv',
            "date,egg_size,quantity_sold,price_per_unit,notes\n" .
            "2025-03-01,Medium,500,7.25,imported sale\n");
    }

    public function test_production_only_leaves_sales_untouched(): void
    {
        $this->actingAs($this->admin)->post(route('imports.combined'), [
            'types' => ['production'], 'building_file' => $this->buildingCsv(), 'grading_file' => $this->gradingCsv(),
            'sales_file' => $this->salesCsv(), // ignored: Sales not ticked
        ])->assertRedirect(route('daily-entry.index'))->assertSessionHasNoErrors();

        $this->assertSame(2, BuildingDaily::count());
        $this->assertSame(1, EggGradingDaily::count());
        $this->assertSame(0, EggSale::count());

        $results = session('combined_import_results');
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['ok']);
        $this->assertSame(2, $results[0]['summary']['imported']);
    }

    public function test_sales_only_leaves_production_untouched(): void
    {
        $this->actingAs($this->admin)->post(route('imports.combined'), [
            'types' => ['sales'], 'sales_file' => $this->salesCsv(), 'building_file' => $this->buildingCsv(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, EggSale::count());
        $this->assertSame(0, BuildingDaily::count());
        $this->assertSame(['Sales'], array_column(session('combined_import_results'), 'type'));
    }

    public function test_both_works(): void
    {
        $this->actingAs($this->admin)->post(route('imports.combined'), [
            'types' => ['production', 'sales'], 'building_file' => $this->buildingCsv(), 'sales_file' => $this->salesCsv(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, BuildingDaily::count());
        $this->assertSame(1, EggSale::count());
        $this->assertSame(['Production — building daily', 'Sales'], array_column(session('combined_import_results'), 'type'));
    }

    public function test_neither_is_rejected_with_a_message(): void
    {
        $this->actingAs($this->admin)->post(route('imports.combined'), ['building_file' => $this->buildingCsv()])
            ->assertSessionHasErrors(['types' => 'Choose what to import: Production, Sales, or both.']);

        $this->assertSame(0, BuildingDaily::count());
    }

    public function test_ticked_type_without_a_file_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('imports.combined'), ['types' => ['sales']])
            ->assertSessionHasErrors('sales_file');
        $this->actingAs($this->admin)->post(route('imports.combined'), ['types' => ['production']])
            ->assertSessionHasErrors('building_file');
    }

    public function test_a_failed_sales_file_does_not_undo_the_production_import(): void
    {
        $broken = UploadedFile::fake()->createWithContent('sales.csv', "not,a,valid\nsales,file,here\n");

        $this->actingAs($this->admin)->post(route('imports.combined'), [
            'types' => ['production', 'sales'], 'building_file' => $this->buildingCsv(), 'sales_file' => $broken,
        ])->assertRedirect(route('daily-entry.index'));

        $this->assertSame(2, BuildingDaily::count());
        $this->assertSame(0, EggSale::count());

        $results = collect(session('combined_import_results'))->keyBy('type');
        $this->assertTrue($results['Production — building daily']['ok']);
    }

    public function test_staff_cannot_import(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post(route('imports.combined'), ['types' => ['sales'], 'sales_file' => $this->salesCsv()])
            ->assertForbidden();
        $this->assertSame(0, EggSale::count());
    }
}
