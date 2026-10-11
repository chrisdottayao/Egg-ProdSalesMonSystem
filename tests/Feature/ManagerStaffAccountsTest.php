<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerStaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);
        $this->manager = User::factory()->create(['role' => 'manager', 'name' => 'Mia Manager']);
        $this->staff   = User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);
    }

    public function test_manager_can_create_a_staff_account_and_it_is_audited(): void
    {
        $this->actingAs($this->manager)->post(route('users.store'), [
            'name' => 'New Staff', 'email' => 'new.staff@example.test', 'password' => 'long-enough-1', 'role' => 'staff',
        ])->assertRedirect(route('users.index'));

        $new = User::where('email', 'new.staff@example.test')->firstOrFail();
        $this->assertSame('staff', $new->role);
        $this->assertNotNull($new->approved_at);
        $this->assertSame($this->manager->id, AuditLog::where('model_type', 'User')->where('action', 'create')->where('model_id', $new->id)->value('user_id'));
    }

    public function test_manager_cannot_create_a_manager_or_admin(): void
    {
        foreach (['manager', 'admin'] as $role) {
            $this->actingAs($this->manager)->post(route('users.store'), [
                'name' => 'X', 'email' => "x.{$role}@example.test", 'password' => 'long-enough-1', 'role' => $role,
            ])->assertForbidden();
        }
        $this->assertSame(3, User::count());
    }

    public function test_manager_cannot_edit_an_admin_or_another_manager(): void
    {
        $other = User::factory()->create(['role' => 'manager']);
        foreach ([$this->admin, $other] as $target) {
            $this->actingAs($this->manager)->patch(route('users.update', $target), [
                'name' => 'Hacked', 'email' => $target->email, 'role' => 'staff',
            ])->assertForbidden();
            $this->assertNotSame('Hacked', $target->fresh()->name);
        }
        $this->actingAs($this->manager)->patch(route('users.deactivate', $this->admin))->assertForbidden();
    }

    public function test_manager_cannot_promote_staff_via_request_tampering(): void
    {
        $this->actingAs($this->manager)->patch(route('users.update', $this->staff), [
            'name' => $this->staff->name, 'email' => $this->staff->email, 'role' => 'manager',
        ])->assertForbidden();

        $this->assertSame('staff', $this->staff->fresh()->role);
    }

    public function test_manager_edits_staff_and_resets_password(): void
    {
        $this->actingAs($this->manager)->patch(route('users.update', $this->staff), [
            'name' => 'Sam S.', 'email' => $this->staff->email, 'role' => 'staff', 'password' => 'brand-new-pass-1',
        ])->assertSessionHasNoErrors();

        $fresh = $this->staff->fresh();
        $this->assertSame('Sam S.', $fresh->name);
        $this->assertTrue(Hash::check('brand-new-pass-1', $fresh->password));
    }

    public function test_deactivated_staff_is_signed_out_and_can_be_reactivated(): void
    {
        $this->actingAs($this->manager)->patch(route('users.deactivate', $this->staff))->assertRedirect();
        $this->assertNotNull($this->staff->fresh()->deactivated_at);
        $this->assertTrue(AuditLog::where('model_type', 'User')->where('model_id', $this->staff->id)->where('action', 'update')->exists());

        $this->actingAs($this->staff->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($this->manager)->patch(route('users.reactivate', $this->staff));
        $this->assertNull($this->staff->fresh()->deactivated_at);
    }

    public function test_manager_sees_only_staff_accounts(): void
    {
        $this->actingAs($this->manager)->get(route('users.index'))
            ->assertOk()->assertSee('Staff Accounts')->assertSee('Sam Staff')
            ->assertDontSee('Ada Admin')->assertDontSee('<option value="manager"', false);
    }

    public function test_manager_cannot_delete_or_approve(): void
    {
        $this->actingAs($this->manager)->delete(route('users.destroy', $this->staff))->assertForbidden();
        $this->actingAs($this->manager)->post(route('users.approve', $this->staff), ['role' => 'staff'])->assertForbidden();
        $this->assertNotNull($this->staff->fresh());
    }

    public function test_admin_keeps_full_access_and_staff_has_none(): void
    {
        $this->actingAs($this->admin)->patch(route('users.update', $this->manager), [
            'name' => $this->manager->name, 'email' => $this->manager->email, 'role' => 'admin',
        ])->assertSessionHasNoErrors();
        $this->assertSame('admin', $this->manager->fresh()->role);

        $this->actingAs($this->staff)->get(route('users.index'))->assertForbidden();
    }
}
