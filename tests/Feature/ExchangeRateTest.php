<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Game;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'type' => 'root',
        ]);

        $this->game = Game::create([
            'name'        => 'Mobile Legends: Bang Bang',
            'slug'        => 'mobile-legends',
            'has_zone_id' => true,
            'is_active'   => true,
        ]);
    }

    public function test_admin_can_view_exchange_rates_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/exchange-rates');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ExchangeRates/Index')
            ->has('currencies', 4)
            ->has('defaultCurrency')
        );
    }

    public function test_admin_can_store_new_currency(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/exchange-rates', [
            'currency'      => 'Singapore Dollar',
            'currency_code' => 'SGD',
            'symbol'        => 'S$',
            'exchange_rate' => 1.35,
            'is_default'    => false,
            'is_active'     => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('currencies', [
            'currency_code' => 'SGD',
            'symbol'        => 'S$',
            'exchange_rate' => 1.35,
        ]);
    }

    public function test_admin_can_update_currency_and_recalculate_idr_product_costs(): void
    {
        $idr = Currency::where('currency_code', 'IDR')->first();

        // Create product with Tokovoucher price 16,000 IDR -> cost was 1.00 USD at 16,000 rate
        $product = Product::create([
            'game_id'           => $this->game->id,
            'name'              => '86 Diamonds',
            'provider_code'     => 'MLA86',
            'tokovoucher_price' => 16000,
            'cost_price'        => 1.00,
            'selling_price'     => 1.25,
            'is_active'         => true,
        ]);

        // Update IDR exchange rate to 20,000 (meaning IDR depreciated, cost in USD should decrease to 0.80)
        $response = $this->actingAs($this->admin)->put("/admin/exchange-rates/{$idr->id}", [
            'currency'      => 'Indonesian Rupiah',
            'currency_code' => 'IDR',
            'symbol'        => 'Rp',
            'exchange_rate' => 20000.0,
            'is_default'    => false,
            'is_active'     => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('currencies', [
            'currency_code' => 'IDR',
            'exchange_rate' => 20000.0,
        ]);

        // Product cost price should be recalculated: 16000 / 20000 = 0.80 USD
        $product->refresh();
        $this->assertEquals(0.80, (float) $product->cost_price);
    }

    public function test_admin_cannot_delete_default_base_usd_currency(): void
    {
        $usd = Currency::where('currency_code', 'USD')->first();

        $response = $this->actingAs($this->admin)->delete("/admin/exchange-rates/{$usd->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('currencies', [
            'currency_code' => 'USD',
        ]);
    }

    public function test_admin_can_delete_non_default_currency(): void
    {
        $khr = Currency::where('currency_code', 'KHR')->first();

        $response = $this->actingAs($this->admin)->delete("/admin/exchange-rates/{$khr->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('currencies', [
            'id' => $khr->id,
        ]);
    }

    public function test_admin_can_sync_products_from_tokovoucher(): void
    {
        Http::fake([
            'https://api.tokovoucher.net/*' => Http::response([
                'status' => 1,
                'data'   => [
                    [
                        'code'            => 'MLA86',
                        'nama_produk'     => '86 Diamonds',
                        'price'           => 16000,
                        'status'          => 1,
                        'operator_produk' => 'Mobile Legends',
                        'jenis_name'      => 'Mobile Legends ID',
                    ],
                ],
            ], 200),
        ]);

        $product = Product::create([
            'game_id'           => $this->game->id,
            'name'              => '86 Diamonds',
            'provider_code'     => 'MLA86',
            'tokovoucher_price' => 25000,
            'cost_price'        => 1.56,
            'selling_price'     => 1.83,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/products/sync-tokovoucher');

        $response->assertRedirect();

        $product->refresh();
        $this->assertEquals(16000, (float) $product->tokovoucher_price);
        $this->assertEquals(1.00, (float) $product->cost_price);
    }

    public function test_guest_cannot_access_exchange_rates(): void
    {
        $response = $this->get('/admin/exchange-rates');
        $response->assertRedirect('/admin/login');
    }
}
