<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRolesAndPermissions
{
    /**
     * User's assigned roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * User's directly assigned permissions.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    /**
     * Check if user has specific role(s).
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roles->contains(function ($role) use ($roles) {
            return in_array($role->name, $roles, true) || in_array($role->slug, $roles, true);
        });
    }

    /**
     * Check if user has a specific permission.
     * Root users can access all without checking permissions.
     */
    public function hasPermissionTo(string $permission): bool
    {
        // 1. Root users can access all (don't check permission)
        if (($this->type ?? null) === 'root' || $this->hasRole(['root', 'Super Admin', 'super-admin'])) {
            return true;
        }

        // 2. Check direct permissions
        $hasDirect = $this->permissions->contains(function ($perm) use ($permission) {
            return $perm->name === $permission || $perm->slug === $permission;
        });

        if ($hasDirect) {
            return true;
        }

        // 3. Check permissions via assigned roles
        foreach ($this->roles as $role) {
            if ($role->permissions->contains(function ($perm) use ($permission) {
                return $perm->name === $permission || $perm->slug === $permission;
            })) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all permissions assigned directly or via roles.
     */
    public function getAllPermissions(): Collection
    {
        if (($this->type ?? null) === 'root' || $this->hasRole(['root', 'Super Admin'])) {
            return Permission::all();
        }

        $rolePermissions = $this->roles->loadMissing('permissions')->flatMap->permissions;

        return $this->permissions->merge($rolePermissions)->unique('id');
    }

    /**
     * Assign role(s) to user.
     */
    public function assignRole(Role|string ...$roles): self
    {
        $roleModels = collect($roles)->map(function ($role) {
            if ($role instanceof Role) {
                return $role;
            }

            return Role::where('name', $role)->orWhere('slug', $role)->first();
        })->filter();

        $this->roles()->syncWithoutDetaching($roleModels->pluck('id'));
        $this->load('roles');

        return $this;
    }

    /**
     * Give direct permission(s) to user.
     */
    public function givePermissionTo(Permission|string ...$permissions): self
    {
        $permModels = collect($permissions)->map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission;
            }

            return Permission::where('name', $permission)->orWhere('slug', $permission)->first();
        })->filter();

        $this->permissions()->syncWithoutDetaching($permModels->pluck('id'));
        $this->load('permissions');

        return $this;
    }
}
