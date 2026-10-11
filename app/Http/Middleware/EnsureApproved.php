<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out any account an admin has not approved yet (new Google sign-ups,
 * /register sign-ups) and sends it to the "pending approval" page.
 */
class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // A deactivated account (Phase 3 item 6) is signed out on its next request.
        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated. Ask a manager or admin.']);
        }

        if ($user && ! $user->isApproved()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('pending-approval');
        }

        return $next($request);
    }
}
