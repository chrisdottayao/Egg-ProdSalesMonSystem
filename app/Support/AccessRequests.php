<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Tells admins that someone is waiting for approval. */
class AccessRequests
{
    public static function notifyAdmins(User $requester): void
    {
        try {
            $admins = User::where('role', 'admin')->whereNotNull('approved_at')->pluck('email')->all();
            if (! $admins) {
                return;
            }
            $url  = url('/users');
            $body = "{$requester->name} ({$requester->email}) signed in and is waiting for approval.\n\n"
                  . "Review it on the Users Management page: {$url}\n"
                  . "Until you approve, this person cannot access the system.";
            Mail::raw($body, fn ($m) => $m->to($admins)->subject('Egg Monitor: new sign-in awaiting approval'));
        } catch (\Throwable $e) {
            // The in-app pending banner is the primary alert; never fail a login over email.
            Log::warning('Admin approval email failed: '.$e->getMessage());
        }
    }
}
