<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Permissions belonging to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * Users associated with this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Give permission(s) to this role.
     */
    public function givePermissionTo(Permission|string ...$permissions): self
    {
        $permissionModels = collect($permissions)->map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission;
            }

            return Permission::where('name', $permission)->orWhere('slug', $permission)->first();
        })->filter();

        $this->permissions()->syncWithoutDetaching($permissionModels->pluck('id'));

        return $this;
    }

    /**
     * Check if role has a permission.
     */
    public function hasPermissionTo(string $permission): bool
    {
        return $this->permissions->contains(function ($perm) use ($permission) {
            return $perm->name === $permission || $perm->slug === $permission;
        });
    }
}
