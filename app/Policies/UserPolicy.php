<?php

namespace App\Policies;

use App\Models\User;

/**
 * Phase 3 item 6 (S6): who may manage which accounts.
 * - Admin: everyone, every role.
 * - Manager: staff accounts only — list, create, edit, deactivate, reset
 *   password. Never admins or managers (including themselves here), never
 *   a role other than staff, never approve sign-ups or delete accounts.
 * - Staff: nothing.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return in_array($actor->role, ['admin', 'manager'], true);
    }

    /** Creating an account with $role. */
    public function create(User $actor, string $role = 'staff'): bool
    {
        return $actor->role === 'admin' || ($actor->role === 'manager' && $role === 'staff');
    }

    /** Editing / resetting the password of $target, ending with $newRole. */
    public function update(User $actor, User $target, ?string $newRole = null): bool
    {
        if ($actor->role === 'admin') {
            return true;
        }

        return $actor->role === 'manager'
            && $target->role === 'staff'
            && ($newRole === null || $newRole === 'staff');
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $this->update($actor, $target);
    }

    public function approve(User $actor): bool
    {
        return $actor->role === 'admin';
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->role === 'admin' && $actor->id !== $target->id;
    }

    /** The roles $actor may assign. */
    public static function assignableRoles(User $actor): array
    {
        return $actor->role === 'admin' ? ['staff', 'manager', 'admin'] : ['staff'];
    }
}
