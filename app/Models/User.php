<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password','google_id', 'role', 'approved_at', 'approved_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use Auditable; // Phase 3 item 2: create, role change, approve, delete

    /** Not worth an audit row on its own (changes on every "remember me" login). */
    protected array $auditIgnore = ['remember_token'];

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Admin and manager may correct past-dated records and run historical
     * imports (S16); staff may only record and edit today's entries.
     */
    public function canManageRecords(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_id' => 'string',
            'approved_at' => 'datetime',
        ];
    }
}
