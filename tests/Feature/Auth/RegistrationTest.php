<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_register_pending_admin_approval(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Registration no longer logs the user in — an admin must approve
        // the account first (see ApprovalGateTest for the full gate).
        $this->assertGuest();
        $response->assertRedirect(route('pending-approval'));
        $this->assertNull(\App\Models\User::where('email', 'test@example.com')->value('approved_at'));
    }
}
