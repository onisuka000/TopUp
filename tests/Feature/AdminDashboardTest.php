<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/admin/login');
    }

    public function test_root_user_can_access_dashboard_without_permissions(): void
    {
        $user = User::create([
            'username' => 'rootboss',
            'login_name' => 'rootboss',
            'password' => bcrypt('password'),
            'type' => 'root',
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_user_with_view_dashboard_permission_can_access(): void
    {
        $permission = Permission::create([
            'name' => 'view::dashboard',
            'slug' => 'view-dashboard',
            'description' => 'View dashboard',
        ]);

        $role = Role::create([
            'name' => 'senior',
            'slug' => 'senior',
            'description' => 'Senior',
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::create([
            'username' => 'senioruser',
            'login_name' => 'senioruser',
            'password' => bcrypt('password'),
            'type' => 'senior',
            'status' => true,
        ]);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::create([
            'username' => 'nopermuser',
            'login_name' => 'nopermuser',
            'password' => bcrypt('password'),
            'type' => 'other',
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_legacy_dashboard_redirects_to_admin_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard'));
    }
}
