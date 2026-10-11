<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Phase 3 item 2 (S7): who logged in (password or Google), failed login
 * attempts, logouts and password resets — with IP and browser (AuditLog
 * fills those from the request). Never the password itself.
 */
class RecordAuthActivity
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class         => 'onLogin',
            Failed::class        => 'onFailed',
            Logout::class        => 'onLogout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }

    public function onLogin(Login $event): void
    {
        $this->log('login', $event->user?->getAuthIdentifier(), [
            'method'   => request()->routeIs('auth.google.callback') ? 'google' : 'password',
            'remember' => $event->remember,
        ]);
    }

    public function onFailed(Failed $event): void
    {
        // No user_id: the attempt wasn't authenticated, even if the email exists.
        $this->log('login_failed', null, [
            'email'        => $event->credentials['email'] ?? null,
            'known_email'  => $event->user !== null,
        ]);
    }

    public function onLogout(Logout $event): void
    {
        $this->log('logout', $event->user?->getAuthIdentifier(), []);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->log('password_reset', $event->user->getAuthIdentifier(), ['via' => 'reset link']);
    }

    private function log(string $action, mixed $userId, array $details): void
    {
        AuditLog::create([
            'user_id'    => $userId,
            'action'     => $action,
            'model_type' => 'Auth',
            'model_id'   => $userId,
            'details'    => $details,
        ]);
    }
}
