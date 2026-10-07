<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Order;
use App\Models\Product;
use App\Services\TokovoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokovoucherIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.tokovoucher.member_code', 'TEST_MEMBER');
        Config::set('services.tokovoucher.secret_key', 'TEST_SECRET');
        Config::set('services.tokovoucher.url', 'https://api.tokovoucher.net/v1');
    }

    public function test_signature_generation(): void
    {
        $service = app(TokovoucherService::class);
        $refId = 'ORD-20261007-TEST01';
        $expected = md5('TEST_MEMBER:TEST_SECRET:' . $refId);

        $this->assertEquals($expected, $service->generateSignature($refId));
        $this->assertTrue($service->validateWebhookSignature($refId, $expected));
        $this->assertFalse($service->validateWebhookSignature($refId, 'wrong-sig'));
    }

    public function test_topup_success_marks_order_completed(): void
    {
        $game = Game::create([
            'name'        => 'Mobile Legends: Bang Bang',
            'slug'        => 'mobile-legends',
            'has_zone_id' => true,
            'is_active'   => true,
        ]);

        $product = Product::create([
            'game_id'       => $game->id,
            'name'          => '86 Diamonds',
            'provider_code' => 'ML86',
            'cost_price'    => 1.50,
            'selling_price' => 1.80,
            'is_active'     => true,
        ]);

        $order = Order::create([
            'order_number'   => 'ORD-20261007-SUCC01',
            'product_id'     => $product->id,
            'game_user_id'   => '12345678',
            'zone_id'        => '2001',
            'amount'         => 1.80,
            'payment_method' => 'KHQR',
            'status'         => 'PAID',
        ]);

        Http::fake([
            'https://api.tokovoucher.net/v1/transaksi' => Http::response([
                'status'     => 'sukses',
                'message'    => 'TRXID:TRX123. SUKSES',
                'sn'         => 'SN-MLBB-998877',
                'ref_id'     => $order->order_number,
                'trx_id'     => 'TRX123',
                'produk'     => 'ML86',
                'sisa_saldo' => 500000,
                'price'      => 20000,
            ], 200),
        ]);

        $service = app(TokovoucherService::class);
        $result  = $service->topUp($order);

        $this->assertTrue($result['success']);
        $this->assertEquals('sukses', $result['status']);

        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);
        $this->assertEquals('TRX123', $order->provider_ref_id);
    }

    public function test_topup_pending_marks_order_processing(): void
    {
        $game = Game::create([
            'name'        => 'Mobile Legends: Bang Bang',
            'slug'        => 'mobile-legends',
            'has_zone_id' => true,
            'is_active'   => true,
        ]);

        $product = Product::create([
            'game_id'       => $game->id,
            'name'          => '86 Diamonds',
            'provider_code' => 'ML86',
            'cost_price'    => 1.50,
            'selling_price' => 1.80,
            'is_active'     => true,
        ]);

        $order = Order::create([
            'order_number'   => 'ORD-20261007-PEND01',
            'product_id'     => $product->id,
            'game_user_id'   => '12345678',
            'zone_id'        => '2001',
            'amount'         => 1.80,
            'payment_method' => 'KHQR',
            'status'         => 'PAID',
        ]);

        Http::fake([
            'https://api.tokovoucher.net/v1/transaksi' => Http::response([
                'status'     => 'pending',
                'message'    => 'TRXID:TRXPEND. PENDING',
                'sn'         => '',
                'ref_id'     => $order->order_number,
                'trx_id'     => 'TRXPEND',
                'produk'     => 'ML86',
                'sisa_saldo' => 500000,
                'price'      => 20000,
            ], 200),
        ]);

        $service = app(TokovoucherService::class);
        $result  = $service->topUp($order);

        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['status']);

        $order->refresh();
        $this->assertEquals('PROCESSING', $order->status);
        $this->assertEquals('TRXPEND', $order->provider_ref_id);
    }

    public function test_tokovoucher_webhook_completes_order(): void
    {
        $game = Game::create([
            'name'        => 'Mobile Legends: Bang Bang',
            'slug'        => 'mobile-legends',
            'has_zone_id' => true,
            'is_active'   => true,
        ]);

        $product = Product::create([
            'game_id'       => $game->id,
            'name'          => '86 Diamonds',
            'provider_code' => 'ML86',
            'cost_price'    => 1.50,
            'selling_price' => 1.80,
            'is_active'     => true,
        ]);

        $order = Order::create([
            'order_number'   => 'ORD-20261007-WEBHOOK01',
            'product_id'     => $product->id,
            'game_user_id'   => '12345678',
            'zone_id'        => '2001',
            'amount'         => 1.80,
            'payment_method' => 'KHQR',
            'status'         => 'PROCESSING',
        ]);

        $sig = md5('TEST_MEMBER:TEST_SECRET:' . $order->order_number);

        $response = $this->withHeaders([
            'X-TokoVoucher-Authorization' => $sig,
        ])->postJson('/api/webhook/tokovoucher', [
            'status'     => 'sukses',
            'message'    => 'Transaksi berhasil',
            'sn'         => 'SN-FINAL-12345',
            'ref_id'     => $order->order_number,
            'trx_id'     => 'TRX-FINAL-999',
            'produk'     => 'ML86',
            'sisa_saldo' => 450000,
            'price'      => 20000,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'SUCCESS']);

        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);
        $this->assertEquals('TRX-FINAL-999', $order->provider_ref_id);
    }

    public function test_tokovoucher_webhook_rejects_invalid_signature(): void
    {
        $response = $this->withHeaders([
            'X-TokoVoucher-Authorization' => 'invalid-signature',
        ])->postJson('/api/webhook/tokovoucher', [
            'status'  => 'sukses',
            'ref_id'  => 'ORD-20261007-FAKE',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid Tokovoucher signature']);
    }

    public function test_mobile_legends_seeder_populates_products(): void
    {
        $this->seed(\Database\Seeders\MobileLegendsSeeder::class);

        $game = Game::where('slug', 'mobile-legends')->first();
        $this->assertNotNull($game);
        $this->assertTrue((bool) $game->has_zone_id);

        $this->assertDatabaseHas('products', [
            'game_id'       => $game->id,
            'provider_code' => 'MLA86',
            'name'          => '86 Diamonds',
        ]);

        $this->assertDatabaseHas('products', [
            'game_id'       => $game->id,
            'provider_code' => 'MLBBGLOWDP',
        ]);
    }

    public function test_admin_can_retry_tokovoucher_topup(): void
    {
        $admin = \App\Models\User::create([
            'username'   => 'adminboss',
            'login_name' => 'adminboss',
            'password'   => bcrypt('password'),
            'type'       => 'root',
            'status'     => true,
        ]);

        $game = Game::create([
            'name'        => 'Mobile Legends: Bang Bang',
            'slug'        => 'mobile-legends',
            'has_zone_id' => true,
            'is_active'   => true,
        ]);

        $product = Product::create([
            'game_id'       => $game->id,
            'name'          => '86 Diamonds',
            'provider_code' => 'ML86',
            'cost_price'    => 1.50,
            'selling_price' => 1.80,
            'is_active'     => true,
        ]);

        $order = Order::create([
            'order_number'   => 'ORD-20261007-ADMINRETRY',
            'product_id'     => $product->id,
            'game_user_id'   => '12345678',
            'zone_id'        => '2001',
            'amount'         => 1.80,
            'payment_method' => 'KHQR',
            'status'         => 'FAILED',
        ]);

        Http::fake([
            'https://api.tokovoucher.net/v1/transaksi' => Http::response([
                'status'  => 'sukses',
                'trx_id'  => 'TRX-ADMIN-100',
                'sn'      => 'SN-ADMIN-100',
                'message' => 'SUKSES',
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/retry-tokovoucher");

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);
        $this->assertEquals('TRX-ADMIN-100', $order->provider_ref_id);
    }

    public function test_admin_can_check_tokovoucher_status(): void
    {
        $admin = \App\Models\User::create([
            'username'   => 'adminboss2',
            'login_name' => 'adminboss2',
            'password'   => bcrypt('password'),
            'type'       => 'root',
            'status'     => true,
        ]);

        $game = Game::create([
            'name'        => 'Mobile Legends: Bang Bang',
            'slug'        => 'mobile-legends',
            'has_zone_id' => true,
            'is_active'   => true,
        ]);

        $product = Product::create([
            'game_id'       => $game->id,
            'name'          => '86 Diamonds',
            'provider_code' => 'ML86',
            'cost_price'    => 1.50,
            'selling_price' => 1.80,
            'is_active'     => true,
        ]);

        $order = Order::create([
            'order_number'   => 'ORD-20261007-ADMINCHECK',
            'product_id'     => $product->id,
            'game_user_id'   => '12345678',
            'zone_id'        => '2001',
            'amount'         => 1.80,
            'payment_method' => 'KHQR',
            'status'         => 'PROCESSING',
        ]);

        Http::fake([
            'https://api.tokovoucher.net/v1/transaksi/status' => Http::response([
                'status'  => 'sukses',
                'trx_id'  => 'TRX-CHECK-200',
                'sn'      => 'SN-CHECK-200',
                'message' => 'SUKSES',
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/check-tokovoucher");

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);
        $this->assertEquals('TRX-CHECK-200', $order->provider_ref_id);
    }
}
