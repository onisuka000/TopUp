<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAndAdminAuthSeparationTest extends TestCase
{
    use RefreshDatabase;

    private User $memberUser;
    private User $bannedMember;
    private User $rootUser;
    private User $seniorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberUser = User::create([
            'username' => 'vip_gamer',
            'login_name' => 'gamer_member',
            'password' => Hash::make('password123'),
            'type' => 'member',
            'status' => true,
        ]);

        $this->bannedMember = User::create([
            'username' => 'cheater_gamer',
            'login_name' => 'banned_member',
            'password' => Hash::make('password123'),
            'type' => 'member',
            'status' => false,
        ]);

        $this->rootUser = User::create([
            'username' => 'admin_root',
            'login_name' => 'admin_root',
            'password' => Hash::make('password123'),
            'type' => 'root',
            'status' => true,
        ]);

        $this->seniorUser = User::create([
            'username' => 'senior_staff',
            'login_name' => 'senior_staff',
            'password' => Hash::make('password123'),
            'type' => 'senior',
            'status' => true,
        ]);
    }

    public function test_member_can_log_in_to_member_site(): void
    {
        $response = $this->post(route('member.login'), [
            'login_name' => 'gamer_member',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->memberUser);
    }

    public function test_non_member_cannot_log_in_to_member_site(): void
    {
        $response = $this->post(route('member.login'), [
            'login_name' => 'admin_root',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('login_name');
        $this->assertGuest();
    }

    public function test_banned_member_cannot_log_in_to_member_site(): void
    {
        $response = $this->post(route('member.login'), [
            'login_name' => 'banned_member',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['login_name' => 'User is ban']);
        $this->assertGuest();
    }

    public function test_member_cannot_log_in_to_admin_site(): void
    {
        $response = $this->post(route('login'), [
            'login_name' => 'gamer_member',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('login_name');
        $this->assertGuest();
    }

    public function test_member_cannot_access_admin_dashboard_or_users_routes(): void
    {
        $response = $this->actingAs($this->memberUser)->get(route('admin.dashboard'));
        $response->assertForbidden();

        $response2 = $this->actingAs($this->memberUser)->get(route('admin.users.index'));
        $response2->assertForbidden();
    }

    public function test_admin_staff_can_log_in_to_admin_site_and_access_admin_pages(): void
    {
        $response = $this->post(route('login'), [
            'login_name' => 'admin_root',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->rootUser);

        $dashboardResponse = $this->actingAs($this->rootUser)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);

        $usersResponse = $this->actingAs($this->rootUser)->get(route('admin.users.index'));
        $usersResponse->assertStatus(200);
    }

    public function test_non_member_is_rejected_on_storefront_google_login(): void
    {
        $response = $this->post(route('auth.google.quick'), [
            'name' => 'Staff Impersonator',
            'email' => 'admin_root',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_member_can_be_created_and_logged_in_with_telegram_phone_number(): void
    {
        $initialCount = User::where('type', 'member')->count();

        $response = $this->post(route('auth.telegram.phone'), [
            'phone' => '+855 12 888 999',
            'username' => 'KhmerSniper',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $authUser = auth()->user();
        $this->assertEquals('+85512888999', $authUser->login_name);
        $this->assertEquals('KhmerSniper', $authUser->username);
        $this->assertEquals('member', $authUser->type);
        $this->assertTrue((bool) $authUser->status);

        // Verify exactly 1 account created
        $this->assertEquals($initialCount + 1, User::where('type', 'member')->count());
    }

    public function test_existing_telegram_member_logs_in_without_creating_duplicate_account(): void
    {
        // First login creates account
        $this->post(route('auth.telegram.phone'), [
            'phone' => '+855 99 111 222',
        ]);
        auth()->logout();

        $countBefore = User::where('login_name', '+85599111222')->count();
        $this->assertEquals(1, $countBefore);

        // Second login re-authenticates the SAME account (only 1 account)
        $response = $this->post(route('auth.telegram.phone'), [
            'phone' => '+855 99 111 222',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertEquals('+85599111222', auth()->user()->login_name);

        $countAfter = User::where('login_name', '+85599111222')->count();
        $this->assertEquals(1, $countAfter);
    }

    public function test_banned_telegram_member_cannot_log_in(): void
    {
        // Create banned telegram user
        User::create([
            'username' => 'Banned TG',
            'login_name' => '+85512000000',
            'password' => Hash::make('password'),
            'type' => 'member',
            'status' => false,
        ]);

        $response = $this->post(route('auth.telegram.phone'), [
            'phone' => '+855 12 000 000',
        ]);

        $response->assertSessionHasErrors(['phone' => 'User is ban']);
        $this->assertGuest();
    }

    public function test_staff_account_cannot_log_in_via_telegram_phone(): void
    {
        // Create staff user whose login_name is a phone number
        User::create([
            'username' => 'Staff TG',
            'login_name' => '+85512777777',
            'password' => Hash::make('password'),
            'type' => 'senior',
            'status' => true,
        ]);

        $response = $this->post(route('auth.telegram.phone'), [
            'phone' => '+855 12 777 777',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }
}
