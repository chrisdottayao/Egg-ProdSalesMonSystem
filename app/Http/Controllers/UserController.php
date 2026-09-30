<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users   = User::orderByRaw('approved_at IS NOT NULL')->latest()->paginate(20);
        $pending = User::whereNull('approved_at')->latest()->get();
        return view('users.index', compact('users', 'pending'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:10',
            'role'     => 'required|in:admin,manager,staff',
        ]);

        // No manual Hash::make() needed — the model casts 'password' as
        // 'hashed', which hashes plain text on assignment automatically.
        // Admin-created accounts are approved immediately.
        User::create($validated + ['approved_at' => now(), 'approved_by' => auth()->id()]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'required|in:admin,manager,staff',
            // Optional: the inline edit row's password field. Left blank,
            // nothing about the account's login method changes — a Google-only
            // account stays Google-only. Filled in, this is exactly the
            // "admin sets/resets a password" action from the same row.
            'password' => 'nullable|string|min:10',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function approve(Request $request, User $user)
    {
        $validated = $request->validate(['role' => 'required|in:admin,manager,staff']);

        $user->update([
            'role'        => $validated['role'],
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "{$user->name} approved as {$validated['role']}. They can now sign in.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted.');
    }
}
