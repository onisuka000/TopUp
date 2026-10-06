<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Define all permissions across all modules
        $permissions = [
            // Dashboard
            'view::dashboard'   => 'View admin dashboard and statistics',

            // User Management
            'viewonly::user'    => 'View users list and details only',
            'create::user'      => 'Create new users',
            'edit::user'        => 'Edit and update existing users',
            'delete::user'      => 'Delete users from system',

            // Games
            'viewonly::game'    => 'View games list and details only',
            'create::game'      => 'Create new game categories',
            'edit::game'        => 'Edit existing game categories',
            'delete::game'      => 'Delete game categories',

            // Products
            'viewonly::product' => 'View products list and details only',
            'create::product'   => 'Create new product top-up packages',
            'edit::product'     => 'Edit existing product packages',
            'delete::product'   => 'Delete product packages',

            // Orders
            'viewonly::order'   => 'View orders and transactions only',
            'create::order'     => 'Create manual orders',
            'edit::order'       => 'Edit and update order status',
            'delete::order'     => 'Delete order records',
        ];

        $permissionModels = [];
        foreach ($permissions as $name => $description) {
            $permissionModels[$name] = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'slug'        => Str::slug(str_replace('::', '-', $name)),
                    'description' => $description,
                ]
            );
        }

        // 2. Define Roles matching user types: root, super_senior, senior, junior
        // Root: Full access
        $rootRole = Role::firstOrCreate(
            ['name' => 'root'],
            [
                'slug'        => 'root',
                'description' => 'Root Administrator with full unrestricted access (bypasses all permissions)',
            ]
        );
        $rootRole->permissions()->sync(collect($permissionModels)->pluck('id'));

        // Super Senior: All permissions
        $superSeniorRole = Role::firstOrCreate(
            ['name' => 'super_senior'],
            [
                'slug'        => 'super-senior',
                'description' => 'Super Senior Manager with full management permissions',
            ]
        );
        $superSeniorRole->permissions()->sync(collect($permissionModels)->pluck('id'));

        // Senior: View & Edit permissions
        $seniorRole = Role::firstOrCreate(
            ['name' => 'senior'],
            [
                'slug'        => 'senior',
                'description' => 'Senior Staff with View and Edit permissions',
            ]
        );
        $seniorRole->permissions()->sync([
            $permissionModels['view::dashboard']->id,
            $permissionModels['viewonly::user']->id,
            $permissionModels['viewonly::game']->id,
            $permissionModels['viewonly::product']->id,
            $permissionModels['viewonly::order']->id,
            $permissionModels['edit::user']->id,
            $permissionModels['edit::game']->id,
            $permissionModels['edit::product']->id,
            $permissionModels['edit::order']->id,
        ]);

        // Junior: View Only
        $juniorRole = Role::firstOrCreate(
            ['name' => 'junior'],
            [
                'slug'        => 'junior',
                'description' => 'Junior Staff with Read-Only View access',
            ]
        );
        $juniorRole->permissions()->sync([
            $permissionModels['view::dashboard']->id,
            $permissionModels['viewonly::user']->id,
            $permissionModels['viewonly::game']->id,
            $permissionModels['viewonly::product']->id,
            $permissionModels['viewonly::order']->id,
        ]);

        // Also keep legacy Editor / Viewer / Admin if needed
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            [
                'slug'        => 'admin',
                'description' => 'Administrator',
            ]
        );
        $adminRole->permissions()->sync(collect($permissionModels)->pluck('id'));

        $editorRole = Role::firstOrCreate(
            ['name' => 'Editor'],
            [
                'slug'        => 'editor',
                'description' => 'Only View and Edit',
            ]
        );
        $editorRole->permissions()->sync($seniorRole->permissions->pluck('id'));

        $viewerRole = Role::firstOrCreate(
            ['name' => 'Viewer'],
            [
                'slug'        => 'viewer',
                'description' => 'Read-only viewer',
            ]
        );
        $viewerRole->permissions()->sync($juniorRole->permissions->pluck('id'));
    }
}
