<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * User management. Admin manages everyone; a manager manages staff accounts
 * only (Phase 3 item 6) — enforced here through UserPolicy, not just by what
 * the page shows. Every change is audited by the User model (Auditable).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);
        $actor = $request->user();

        $users = User::query()
            ->when($actor->role !== 'admin', fn ($q) => $q->where('role', 'staff'))
            ->orderByRaw('approved_at IS NOT NULL')->latest()->paginate(20);
        // Approving sign-ups stays with the admin.
        $pending = $actor->role === 'admin' ? User::whereNull('approved_at')->latest()->get() : collect();
        $assignableRoles = UserPolicy::assignableRoles($actor);

        return view('users.index', compact('users', 'pending', 'assignableRoles'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:10',
            'role'     => 'required|in:admin,manager,staff',
        ]);
        Gate::authorize('create', [User::class, $validated['role']]);

        // No manual Hash::make() needed — the model casts 'password' as
        // 'hashed', which hashes plain text on assignment automatically.
        // Accounts created here are approved immediately.
        User::create($validated + ['approved_at' => now(), 'approved_by' => $actor->id]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', [$user, null]);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'required|in:admin,manager,staff',
            // Optional: the inline edit row's password field. Left blank,
            // nothing about the account's login method changes — a Google-only
            // account stays Google-only. Filled in, this is the "set/reset a
            // password" action from the same row.
            'password' => 'nullable|string|min:10',
        ]);
        // A tampered request (e.g. role=manager from a manager) is refused,
        // not silently downgraded.
        Gate::authorize('update', [$user, $validated['role']]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function deactivate(Request $request, User $user)
    {
        Gate::authorize('deactivate', $user);
        $user->update(['deactivated_at' => now(), 'deactivated_by' => $request->user()->id]);

        return redirect()->route('users.index')->with('success', "{$user->name} deactivated. They can no longer sign in.");
    }

    public function reactivate(Request $request, User $user)
    {
        Gate::authorize('deactivate', $user);
        $user->update(['deactivated_at' => null, 'deactivated_by' => null]);

        return redirect()->route('users.index')->with('success', "{$user->name} reactivated.");
    }

    public function approve(Request $request, User $user)
    {
        Gate::authorize('approve', User::class);
        $validated = $request->validate(['role' => 'required|in:admin,manager,staff']);

        $user->update([
            'role'        => $validated['role'],
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "{$user->name} approved as {$validated['role']}. They can now sign in.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        Gate::authorize('delete', $user);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }
}
