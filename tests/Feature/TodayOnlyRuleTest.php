<?php

namespace Tests\Feature;

use App\Models\CullRecord;
use App\Models\EggProduction;
use App\Models\EggSale;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\User;
use App\Support\ManilaDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TodayOnlyRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $admin;
    private HenBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        // 2026-10-12 17:00 UTC is already 2026-10-13 01:00 in Manila.
        $this->travelTo('2026-10-12 17:00:00');
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->batch = HenBatch::create(['batch_id' => 'HB-T1', 'batch_size' => 2000, 'status' => 'Active', 'entry_date' => '2026-01-01', 'building_no' => 1, 'is_tracked' => true]);
    }

    /** @return array<string, array{string, callable(string): array}> */
    private function forms(): array
    {
        return [
            'sales'      => ['sales.store', fn ($d) => ['date' => $d, 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7]],
            'expenses'   => ['expenses.store', fn ($d) => ['date' => $d, 'category' => 'electricity', 'description' => 'Bill', 'amount' => 100]],
            'cull'       => ['cull.store', fn ($d) => ['date' => $d, 'hen_batch_id' => $this->batch->id, 'quantity_culled' => 2]],
            'production' => ['productions.store', fn ($d) => ['date' => $d, 'eggs_collected' => 100, 'active_hens' => 200, 'mortality' => 0]],
            'daily'      => ['daily-entry.store', fn ($d) => ['date' => $d, 'buildings' => [$this->batch->id => ['population' => 1500, 'eggs_house' => 1000]]]],
        ];
    }

    public function test_manila_today_differs_from_utc_today_at_this_hour(): void
    {
        $this->assertSame('2026-10-13', ManilaDate::todayString());
        $this->assertSame('2026-10-12', now()->toDateString());
    }

    public function test_staff_cannot_post_yesterday_on_any_form(): void
    {
        $dateErrors = [];
        foreach ($this->forms() as $name => [$route, $payload]) {
            $this->actingAs($this->staff)->post(route($route), $payload('2026-10-12'));
            $errors = session('errors');
            $dateErrors[$name] = $errors instanceof \Illuminate\Support\ViewErrorBag
                ? $errors->get('date')
                : ($errors['default']['messages']['date'] ?? null); // array session driver stores it serialized
            session()->forget('errors');
        }

        $this->assertSame(array_fill_keys(array_keys($this->forms()), [ManilaDate::MESSAGE]), $dateErrors);

        $this->assertSame(0, EggSale::withPending()->count() + Expense::withPending()->count() + CullRecord::withPending()->count() + EggProduction::withPending()->count());
    }

    public function test_staff_can_post_manila_today_even_while_utc_is_still_yesterday(): void
    {
        foreach ($this->forms() as $name => [$route, $payload]) {
            $this->actingAs($this->staff)->post(route($route), $payload('2026-10-13'))
                ->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertSame(1, EggSale::withPending()->count()); // staff sale: pending until approved
        $this->assertSame(1, Expense::withPending()->count()); // pending too (Phase 3B)
        $this->assertSame(1, CullRecord::withPending()->count());
    }

    public function test_new_records_are_today_only_for_admin_too(): void
    {
        $this->actingAs($this->admin)->post(route('sales.store'), $this->forms()['sales'][1]('2026-10-12'))
            ->assertSessionHasErrors('date');
    }

    public function test_daily_entry_page_redirects_staff_to_today_but_lets_admin_open_a_past_date(): void
    {
        $this->actingAs($this->staff)->get(route('daily-entry.index', ['date' => '2026-10-10']))
            ->assertRedirect(route('daily-entry.index'))
            ->assertSessionHas('date_notice');

        $this->actingAs($this->admin)->get(route('daily-entry.index', ['date' => '2026-10-10']))->assertOk();
        $this->actingAs($this->admin)->get(route('daily-entry.index', ['date' => '2026-10-20']))->assertRedirect(route('daily-entry.index'));
    }

    public function test_admin_can_edit_an_old_record_and_staff_cannot(): void
    {
        $sale = EggSale::create(['date' => '2026-10-01', 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7, 'total_amount' => 70]);
        $update = ['date' => '2026-10-01', 'egg_size' => 'Medium', 'quantity' => 12, 'price_per_unit' => 7];

        $this->actingAs($this->staff)->get(route('sales.edit', $sale))->assertForbidden();
        $this->actingAs($this->staff)->patch(route('sales.update', $sale), $update)->assertForbidden();
        $this->actingAs($this->staff)->delete(route('sales.destroy', $sale))->assertForbidden();
        $this->assertSame(10, (int) $sale->fresh()->quantity);

        $this->actingAs($this->admin)->patch(route('sales.update', $sale), $update)->assertSessionHasNoErrors();
        $this->assertSame(12, (int) $sale->fresh()->quantity);
    }

    public function test_staff_can_edit_todays_record_but_not_move_it_to_another_day(): void
    {
        // Their own pending sale (an approved one is locked for staff, Phase 3B item 4).
        $sale = EggSale::create(['date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => 10, 'price_per_unit' => 7, 'total_amount' => 70, 'approval_status' => 'pending', 'entered_by' => $this->staff->id]);

        $this->actingAs($this->staff)->patch(route('sales.update', $sale), ['date' => '2026-10-13', 'egg_size' => 'Medium', 'quantity' => 11, 'price_per_unit' => 7])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patch(route('sales.update', $sale), ['date' => '2026-10-05', 'egg_size' => 'Medium', 'quantity' => 11, 'price_per_unit' => 7])
            ->assertSessionHasErrors('date');
    }

    public function test_import_routes_are_admin_and_manager_only(): void
    {
        $csv = fn () => UploadedFile::fake()->createWithContent('x.csv', "date\n");
        $manager = User::factory()->create(['role' => 'manager']);

        foreach (['sales.import', 'imports.building-daily.import', 'imports.egg-grading.import', 'productions.import.historical'] as $route) {
            $this->actingAs($this->staff)->post(route($route), ['csv_file' => $csv()])->assertForbidden();
        }
        foreach (['sales.import.template', 'imports.building-daily.template', 'productions.import.template'] as $route) {
            $this->actingAs($this->staff)->get(route($route))->assertForbidden();
            $this->actingAs($manager)->get(route($route))->assertOk();
        }
    }

    public function test_offline_entry_syncs_with_the_date_it_was_queued_on(): void
    {
        // Queued 2026-10-12 21:00 Manila (13:00 UTC), syncing now on the 13th.
        $this->actingAs($this->staff)->post(route('sales.store'), $this->forms()['sales'][1]('2026-10-12') + ['queued_at' => '2026-10-12T13:00:00Z'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, EggSale::withPending()->count()); // staff sale: pending until approved
    }

    public function test_offline_entry_cannot_backdate_beyond_its_queued_day_or_grace_window(): void
    {
        // Date doesn't match the day it was queued.
        $this->actingAs($this->staff)->post(route('sales.store'), $this->forms()['sales'][1]('2026-10-11') + ['queued_at' => '2026-10-12T13:00:00Z'])
            ->assertSessionHasErrors('date');

        // Queued too long ago (beyond OFFLINE_GRACE_DAYS).
        $this->actingAs($this->staff)->post(route('sales.store'), $this->forms()['sales'][1]('2026-10-05') + ['queued_at' => '2026-10-05T03:00:00Z'])
            ->assertSessionHasErrors('date');

        // queued_at only applies to the offline-writable forms.
        $this->actingAs($this->staff)->post(route('expenses.store'), $this->forms()['expenses'][1]('2026-10-12') + ['queued_at' => '2026-10-12T13:00:00Z'])
            ->assertSessionHasErrors('date');

        $this->assertSame(0, EggSale::withPending()->count() + Expense::count());
    }
}
