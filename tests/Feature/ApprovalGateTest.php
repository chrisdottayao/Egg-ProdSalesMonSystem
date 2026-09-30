<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class ApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'approved_at' => now()]);
    }

    private function fakeGoogle(string $email): void
    {
        $g = (new SocialUser)->map(['id' => 'g-'.$email, 'name' => 'New Person', 'email' => $email]);
        $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $driver->shouldReceive('user')->andReturn($g);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_new_google_login_is_pending_and_not_logged_in(): void
    {
        $this->admin();
        $this->fakeGoogle('stranger@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('pending-approval'));

        $this->assertGuest();
        $u = User::where('email', 'stranger@gmail.com')->first();
        $this->assertNotNull($u);
        $this->assertNull($u->approved_at);
    }

    public function test_pending_user_cannot_reach_protected_pages(): void
    {
        $u = User::factory()->create(['approved_at' => null]);
        $this->actingAs($u)->get('/profile')->assertRedirect(route('pending-approval'));
        $this->assertGuest();
    }

    public function test_pending_page_is_public(): void
    {
        $this->get('/pending-approval')->assertOk()->assertSee('Waiting for admin approval');
    }

    public function test_approved_user_passes_and_returning_google_user_logs_in(): void
    {
        $u = User::factory()->create(['approved_at' => now(), 'google_id' => 'g-ok@gmail.com', 'email' => 'ok@gmail.com']);
        $this->actingAs($u)->get('/profile')->assertOk();
        auth()->logout();

        $this->fakeGoogle('ok@gmail.com');
        $this->get('/auth/google/callback')->assertRedirect();
        $this->assertAuthenticatedAs($u);
    }

    public function test_admin_sees_pending_and_can_approve_then_user_gets_in(): void
    {
        $admin = $this->admin();
        $p = User::factory()->create(['approved_at' => null, 'role' => 'staff']);

        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($p->email)->assertSee('awaiting your approval');

        $this->actingAs($admin)->post("/users/{$p->id}/approve", ['role' => 'manager'])->assertRedirect(route('users.index'));
        $p->refresh();
        $this->assertNotNull($p->approved_at);
        $this->assertSame('manager', $p->role);
        $this->assertSame($admin->id, $p->approved_by);

        auth()->logout();
        $this->actingAs($p)->get('/profile')->assertOk();
    }

    public function test_non_admin_cannot_approve(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'approved_at' => now()]);
        $p = User::factory()->create(['approved_at' => null]);
        $this->actingAs($staff)->post("/users/{$p->id}/approve", ['role' => 'admin'])->assertForbidden();
        $this->assertNull($p->fresh()->approved_at);
    }

    public function test_register_creates_pending_user_without_login(): void
    {
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!'])
            ->assertRedirect(route('pending-approval'));
        $this->assertGuest();
        $this->assertNull(User::where('email', 'x@example.com')->value('approved_at'));
    }
}
