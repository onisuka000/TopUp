<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $rootUser;
    private User $juniorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootUser = User::create([
            'username' => 'rootboss',
            'login_name' => 'rootboss',
            'password' => bcrypt('password'),
            'type' => 'root',
            'status' => true,
        ]);

        $viewOnlyPerm = Permission::create([
            'name' => 'viewonly::game',
            'slug' => 'viewonly-game',
            'description' => 'View game',
        ]);
        $juniorRole = Role::create([
            'name' => 'junior',
            'slug' => 'junior',
            'description' => 'Junior',
        ]);
        $juniorRole->permissions()->attach($viewOnlyPerm->id);

        $this->juniorUser = User::create([
            'username' => 'juniorstaff',
            'login_name' => 'juniorstaff',
            'password' => bcrypt('password'),
            'type' => 'junior',
            'status' => true,
        ]);
        $this->juniorUser->roles()->attach($juniorRole->id);
    }

    public function test_root_can_view_and_create_games(): void
    {
        $response = $this->actingAs($this->rootUser)->get('/admin/games');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Games/Index'));

        $createResponse = $this->actingAs($this->rootUser)->post('/admin/games', [
            'name' => 'Genshin Impact',
            'has_zone_id' => false,
            'is_active' => true,
        ]);
        $createResponse->assertRedirect();
        $this->assertDatabaseHas('games', ['name' => 'Genshin Impact']);
    }

    public function test_junior_cannot_create_games(): void
    {
        $response = $this->actingAs($this->juniorUser)->post('/admin/games', [
            'name' => 'Valorant',
            'has_zone_id' => false,
            'is_active' => true,
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('games', ['name' => 'Valorant']);
    }

    public function test_root_can_create_and_toggle_product(): void
    {
        $game = Game::create([
            'name' => 'Free Fire',
            'slug' => 'free-fire',
            'has_zone_id' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->rootUser)->post('/admin/products', [
            'game_id' => $game->id,
            'name' => '100 Diamonds',
            'provider_code' => 'FF_100',
            'cost_price' => 0.90,
            'selling_price' => 1.00,
            'is_active' => true,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['name' => '100 Diamonds']);

        $product = Product::first();
        $this->actingAs($this->rootUser)->patch("/admin/products/{$product->id}/toggle");
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_root_can_update_order_status(): void
    {
        $game = Game::create([
            'name' => 'MLBB',
            'slug' => 'mlbb',
            'is_active' => true,
        ]);
        $product = Product::create([
            'game_id' => $game->id,
            'name' => '86 Diamonds',
            'provider_code' => 'MLBB_86',
            'cost_price' => 1.0,
            'selling_price' => 1.2,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'ORD-123456',
            'product_id' => $product->id,
            'game_user_id' => '12345',
            'zone_id' => '1234',
            'amount' => 1.2,
            'payment_method' => 'KHQR',
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->rootUser)->patch("/admin/orders/{$order->id}/status", [
            'status' => 'COMPLETED',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'COMPLETED']);
    }

    public function test_root_can_create_user_and_toggle_ban_status(): void
    {
        $response = $this->actingAs($this->rootUser)->post('/admin/users', [
            'username' => 'newcashier',
            'login_name' => 'cashier1',
            'password' => 'secret123',
            'type' => 'junior',
            'status' => true,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['username' => 'newcashier']);

        $user = User::where('username', 'newcashier')->first();
        $this->actingAs($this->rootUser)->patch("/admin/users/{$user->id}/toggle");
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => false]);
    }
}
