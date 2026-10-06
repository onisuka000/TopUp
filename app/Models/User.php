<?php

namespace App\Models;

use App\Traits\HasRolesAndPermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'username',
    'login_name',
    'password',
    'encrypted_password',
    'type',
    'status',
    'remark',
])]
#[Hidden([
    'password',
    'encrypted_password',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRolesAndPermissions;

    /**
     * Virtual name attribute accessor for fallback compatibility.
     */
    public function getNameAttribute(): string
    {
        return $this->username ?: $this->login_name;
    }

    /**
     * Check if user is Root (bypasses all checks).
     */
    public function isRoot(): bool
    {
        $type = strtolower(trim((string) $this->type));
        return $type === 'root' || $this->hasRole('root') || $this->hasRole('Root');
    }

    /**
     * Check if user is Super Senior.
     */
    public function isSuperSenior(): bool
    {
        $type = strtolower(trim((string) $this->type));
        return in_array($type, ['super_senior', 'super senior'], true);
    }

    /**
     * Check if user is Senior.
     */
    public function isSenior(): bool
    {
        $type = strtolower(trim((string) $this->type));
        return $type === 'senior';
    }

    /**
     * Check if user is Junior (view only).
     */
    public function isJunior(): bool
    {
        $type = strtolower(trim((string) $this->type));
        return $type === 'junior';
    }

    /**
     * Check if user is a Member customer.
     */
    public function isMember(): bool
    {
        $type = strtolower(trim((string) $this->type));
        return $type === 'member';
    }

    /**
     * Check if user is Admin / Management staff.
     */
    public function isAdminStaff(): bool
    {
        if ($this->isRoot()) {
            return true;
        }

        $type = strtolower(trim((string) $this->type));
        return in_array($type, ['root', 'super_senior', 'super senior', 'senior', 'junior'], true);
    }

    /**
     * Prevent Laravel from querying non-existent remember_token column.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // No-op: remember_token column removed
    }

    /**
     * Get the decrypted password if available.
     */
    public function getDecryptedPasswordAttribute(): ?string
    {
        if (!$this->encrypted_password) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_password);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status'   => 'boolean',
        ];
    }
}
