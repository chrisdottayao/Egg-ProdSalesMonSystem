<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\HenBatch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-13 02:00:00'); // 10:00 Manila
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin', 'password' => bcrypt('right-password')]);
    }

    public function test_login_is_logged_with_user_and_ip(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'right-password'], ['REMOTE_ADDR' => '10.1.2.3']);

        $log = AuditLog::where('action', 'login')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('10.1.2.3', $log->ip_address);
        $this->assertSame('password', $log->details['method']);
    }

    public function test_failed_login_is_logged_without_a_user_and_without_the_password(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'wrong-password']);

        $log = AuditLog::where('action', 'login_failed')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame($this->admin->email, $log->details['email']);
        $this->assertStringNotContainsString('wrong-password', json_encode($log->details));
    }

    public function test_logout_is_logged(): void
    {
        $this->actingAs($this->admin)->post('/logout');

        $this->assertSame($this->admin->id, AuditLog::where('action', 'logout')->value('user_id'));
    }

    public function test_expense_create_update_delete_are_logged_with_old_and_new_values(): void
    {
        $this->actingAs($this->admin);
        $e = Expense::create(['date' => '2026-10-13', 'category' => 'electricity', 'description' => 'Bill', 'amount' => 100]);
        $e->update(['amount' => 150]);
        $e->delete();

        $logs = AuditLog::where('model_type', 'Expense')->orderBy('id')->get();
        $this->assertSame(['create', 'update', 'delete'], $logs->pluck('action')->all());
        $this->assertSame($this->admin->id, $logs[1]->user_id);
        $this->assertEquals(['old' => 100, 'new' => 150], $logs[1]->details['changes']['amount']);
    }

    public function test_settings_changes_are_logged_old_to_new(): void
    {
        Setting::set('feed_price_per_bag_estimate', 1500);
        $this->actingAs($this->admin)->patch(route('settings.update'), ['feed_price_per_bag_estimate' => 1650, 'pullet_cost_per_head_estimate' => 320]);

        $log = AuditLog::where('model_type', 'Setting')->where('action', 'update')->latest('id')->firstOrFail();
        $this->assertEquals(['old' => '1500', 'new' => 1650], $log->details['changes']['value']);
    }

    public function test_user_role_change_is_logged_and_passwords_are_never_stored(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($this->admin);
        $staff->update(['role' => 'manager', 'password' => bcrypt('new-secret')]);

        $log = AuditLog::where('model_type', 'User')->where('action', 'update')->latest('id')->firstOrFail();
        $this->assertEquals(['old' => 'staff', 'new' => 'manager'], $log->details['changes']['role']);
        $this->assertSame('(hidden)', $log->details['changes']['password']);
        $this->assertStringNotContainsString('$2y$', json_encode(AuditLog::all()->pluck('details')));
    }

    public function test_building_add_end_and_reopen_are_logged(): void
    {
        $this->actingAs($this->admin)->post(route('flock-records.buildings.store'), [
            'building_no' => 9, 'batch_size' => 1000, 'placement_date' => '2026-10-13',
        ]);
        $b = HenBatch::where('building_no', 9)->firstOrFail();
        $b->update(['ended_at' => '2026-10-13']);
        $this->actingAs($this->admin)->patch(route('flock-records.hens.reopen', $b));

        $actions = AuditLog::where('model_type', 'HenBatch')->pluck('action')->all();
        $this->assertContains('create', $actions);
        $this->assertContains('update', $actions);
        $this->assertContains('reopen_building', $actions);
    }

    public function test_numerically_equal_values_are_not_reported_as_changes(): void
    {
        $this->actingAs($this->admin);
        $e = Expense::create(['date' => '2026-10-13', 'category' => 'electricity', 'description' => 'Bill', 'amount' => 100]);
        $e = $e->fresh(); // amount now "100.00" from the database
        $e->update(['amount' => 100, 'description' => 'Bill (Oct)']);

        $changes = AuditLog::where('model_type', 'Expense')->where('action', 'update')->firstOrFail()->details['changes'];
        $this->assertSame(['description'], array_keys($changes));
    }

    public function test_readable_action_labels_and_nested_details(): void
    {
        $log = AuditLog::create(['action' => 'sold_gt_produced', 'model_type' => 'EggSale', 'details' => ['entries' => ['Building 1' => ['population' => 4768, 'eggs_house' => 4100]]]]);

        $this->assertSame('Sold > produced', $log->action_label);
        $this->assertSame('entries: Building 1 (population: 4768, eggs_house: 4100)', \App\Support\AuditLogFilter::detailsText($log));
    }

    public function test_audit_rows_are_append_only(): void
    {
        $log = AuditLog::create(['action' => 'test', 'model_type' => 'X', 'details' => []]);

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('update should be refused');
        } catch (LogicException) {
        }
        try {
            $log->delete();
            $this->fail('delete should be refused');
        } catch (LogicException) {
        }
        $this->assertSame('test', $log->fresh()->action);

        // Resolving a flag is the one allowed change.
        $log->fresh()->update(['resolved' => true, 'resolved_by' => $this->admin->id, 'resolved_at' => now()]);
        $this->assertTrue($log->fresh()->resolved);
    }

    public function test_staff_cannot_open_the_log_or_its_exports(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        foreach (['pdf', 'csv', 'excel'] as $f) {
            $this->actingAs($staff)->get(route("reports.export.audit.{$f}"))->assertForbidden();
        }
    }

    public function test_filters_narrow_the_log(): void
    {
        $other = User::factory()->create(['role' => 'manager', 'name' => 'Max Manager']);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'login', 'model_type' => 'Auth', 'details' => ['marker' => 'admin-login']]);
        AuditLog::create(['user_id' => $other->id, 'action' => 'export', 'model_type' => 'Report', 'details' => ['marker' => 'manager-export']]);
        AuditLog::create(['user_id' => $other->id, 'action' => 'create', 'model_type' => 'EggSale', 'details' => ['marker' => 'flagged-sale'], 'inconsistency_flagged' => true, 'inconsistency_rule' => 'sold_gt_produced']);

        $base = ['start_date' => '2026-10-13', 'end_date' => '2026-10-13'];
        $this->actingAs($this->admin)->get(route('reports.index', $base + ['audit_user' => $other->id]))
            ->assertSee('manager-export')->assertSee('flagged-sale')->assertDontSee('admin-login');
        $this->actingAs($this->admin)->get(route('reports.index', $base + ['audit_action' => 'login']))
            ->assertSee('admin-login')->assertDontSee('manager-export');
        $this->actingAs($this->admin)->get(route('reports.index', $base + ['audit_flagged' => 1]))
            ->assertSee('flagged-sale')->assertDontSee('manager-export')->assertSee('Sold > produced');
        $this->actingAs($this->admin)->get(route('reports.index', $base))
            ->assertSee('Audit Log')->assertDontSee('Audit Inconsistency Report')->assertSee('Oct 13, 2026 10:00:00 AM');
    }

    public function test_exports_return_files_and_are_logged(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'login', 'model_type' => 'Auth', 'details' => []]);
        $params = ['start_date' => '2026-10-13', 'end_date' => '2026-10-13'];

        $this->actingAs($this->admin)->get(route('reports.export.audit.pdf', $params))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $csv = $this->actingAs($this->admin)->get(route('reports.export.audit.csv', $params))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $this->assertStringContainsString('When (Asia/Manila)', $csv->streamedContent());
        $this->actingAs($this->admin)->get(route('reports.export.audit.excel', $params))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame(3, AuditLog::where('action', 'export')->where('model_type', 'Report')->count());
    }
}
