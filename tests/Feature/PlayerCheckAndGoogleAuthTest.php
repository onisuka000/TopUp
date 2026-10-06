<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerCheckAndGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_check_returns_verified_ign(): void
    {
        $game = Game::create([
            'name' => 'Mobile Legends: Bang Bang',
            'slug' => 'mobile-legends',
            'has_zone_id' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/check-id', [
            'game_id' => $game->id,
            'user_id' => '12345678',
            'zone_id' => '2001',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'verified' => true,
            'user_id' => '12345678',
            'game_name' => 'Mobile Legends: Bang Bang',
        ]);
        $this->assertNotEmpty($response->json('player_name'));
    }

    public function test_player_check_requires_zone_id_if_game_requires_it(): void
    {
        $game = Game::create([
            'name' => 'Mobile Legends',
            'slug' => 'mlbb',
            'has_zone_id' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/check-id', [
            'game_id' => $game->id,
            'user_id' => '12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_google_auth_redirect_and_quick_signin(): void
    {
        // 1. Google redirect route
        $redirectResponse = $this->get('/auth/google');
        $redirectResponse->assertRedirect();

        // 2. Quick Google Sign-In
        $signInResponse = $this->post('/auth/google/quick-signin', [
            'name' => 'John Wick',
            'email' => 'john.wick@gmail.com',
        ]);

        $signInResponse->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'login_name' => 'john.wick@gmail.com',
            'username' => 'John Wick',
        ]);
    }
}
