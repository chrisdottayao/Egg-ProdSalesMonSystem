<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EggSale;
use App\Models\User;
use App\Support\SalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00');
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);

        // Mon Oct 5 – Tue Oct 13. Approved:
        EggSale::create(['date' => '2026-10-05', 'egg_size' => 'Medium', 'quantity' => 360, 'price_per_unit' => 7, 'total_amount' => 2520]);
        EggSale::create(['date' => '2026-10-05', 'egg_size' => 'Large', 'quantity' => 300, 'price_per_unit' => 8, 'total_amount' => 2400]);
        EggSale::create(['date' => '2026-10-12', 'egg_size' => 'Medium', 'quantity' => 600, 'price_per_unit' => 7, 'total_amount' => 4200]);
        // Pending: must be excluded.
        EggSale::create(['date' => '2026-10-12', 'egg_size' => 'Small', 'quantity' => 999, 'price_per_unit' => 6, 'total_amount' => 5994, 'approval_status' => 'pending']);
    }

    public function test_totals_match_the_approved_data_and_pending_is_excluded(): void
    {
        $d = SalesReport::make('2026-10-05', '2026-10-13', 'day')->data();

        $this->assertCount(2, $d['rows']);
        $this->assertSame(1260, $d['totals']['pcs']);
        $this->assertSame(9120.0, $d['totals']['revenue']);
        $this->assertSame(42.0, $d['totals']['trays']);
        $this->assertSame(3.5, $d['totals']['cases']);
        $this->assertSame(7.24, $d['totals']['avg_price']); // 9120 / 1260
        $this->assertSame(960, $d['totals']['by_size']['Medium']);
        $this->assertSame(0, $d['totals']['by_size']['Small']);
    }

    public function test_grouping_by_week_and_month(): void
    {
        $week = SalesReport::make('2026-10-05', '2026-10-13', 'week')->data();
        $this->assertSame(['Week of Oct 05 – Oct 11, 2026', 'Week of Oct 12 – Oct 18, 2026'], $week['rows']->pluck('label')->all());

        $month = SalesReport::make('2026-10-05', '2026-10-13', 'month')->data();
        $this->assertSame(['Oct 2026'], $month['rows']->pluck('label')->all());
        $this->assertSame(1260, $month['rows'][0]['pcs']);
    }

    public function test_reports_page_shows_the_section_with_totals(): void
    {
        $this->actingAs($this->admin)->get(route('reports.index', ['start_date' => '2026-10-05', 'end_date' => '2026-10-13']))
            ->assertOk()->assertSee('Sales &amp; Revenue', false)->assertSee('₱9,120.00')->assertSee('1,260');
    }

    public function test_exports_return_files_with_header_and_are_logged(): void
    {
        $p = ['start_date' => '2026-10-05', 'end_date' => '2026-10-13', 'sales_group' => 'week'];

        $this->actingAs($this->admin)->get(route('reports.export.sales.pdf', $p))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $csv = $this->actingAs($this->admin)->get(route('reports.export.sales.csv', $p))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('SPC Farm Magalang — Sales & Revenue Report', $body);
        $this->assertStringContainsString('Ada Admin (admin)', $body);
        $this->assertStringContainsString('2026-10-13 10:00', $body); // generated at, Manila time
        $this->assertStringContainsString('Total,1260,42,3.5,9120.00,7.24', $body);

        $this->actingAs($this->admin)->get(route('reports.export.sales.excel', $p))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame(3, AuditLog::where('action', 'export')->where('details->report', 'Sales & Revenue')->count());
    }

    public function test_staff_gets_403(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        foreach (['pdf', 'csv', 'excel'] as $f) {
            $this->actingAs($staff)->get(route("reports.export.sales.{$f}"))->assertForbidden();
        }
    }
}
