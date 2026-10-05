<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CreateOrderAction
{
    /**
     * Execute the order creation action.
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data): array
    {
        // 1. Fetch product with its game relation
        $product = Product::with('game')->findOrFail($data['product_id']);

        // 2. Generate unique order number (e.g. ORD-20261005-XXXXXX)
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        // 3. Create Order record in Database
        $order = Order::create([
            'order_number'   => $orderNumber,
            'product_id'     => $product->id,
            'game_user_id'   => $data['user_id'],
            'zone_id'        => $data['zone_id'] ?? null,
            'amount'         => $product->selling_price,
            'payment_method' => 'KHQR',
            'status'         => 'PENDING',
        ]);

        // 4. Prepare ABA PayWay Parameters
        $merchantId  = config('services.aba.merchant_id');
        $apiKey      = config('services.aba.api_key');
        $apiUrl      = config('services.aba.url');
        $reqTime     = date('YmdHis');
        $tranId      = $order->order_number;
        $amount      = number_format($order->amount, 2, '.', '');
        $firstName   = 'Gamer';
        $lastName    = 'Customer';
        $phone       = '012345678';
        $email       = 'customer@gmail.com';
        $paymentOpt  = 'abapay_khqr';
        $type        = 'purchase';
        $shipping    = '';
        $returnUrl   = base64_encode('https://yourdomain.com');
        $pushbackUrl = 'https://fleshy-emit-pellet.ngrok-free.dev/api/webhook/aba';

        $items = base64_encode(json_encode([
            [
                'name'     => $product->name,
                'quantity' => 1,
                'price'    => (float) $amount,
            ]
        ]));

        $hashStr = $reqTime
            . $merchantId
            . $tranId
            . $amount
            . $items
            . $shipping
            . $firstName
            . $lastName
            . $email
            . $phone
            . $type
            . $paymentOpt
            . $returnUrl;

        $hash = base64_encode(hash_hmac('sha512', $hashStr, $apiKey, true));

        $qrImage  = null;
        $qrString = null;

        // 5. Call ABA Sandbox API if configured
        if ($merchantId && $apiKey && $apiUrl) {
            try {
                $response = Http::asMultipart()->post($apiUrl, [
                    ['name' => 'req_time',       'contents' => $reqTime],
                    ['name' => 'merchant_id',    'contents' => $merchantId],
                    ['name' => 'tran_id',        'contents' => $tranId],
                    ['name' => 'amount',         'contents' => $amount],
                    ['name' => 'items',          'contents' => $items],
                    ['name' => 'firstname',      'contents' => $firstName],
                    ['name' => 'lastname',       'contents' => $lastName],
                    ['name' => 'email',          'contents' => $email],
                    ['name' => 'phone',          'contents' => $phone],
                    ['name' => 'type',           'contents' => $type],
                    ['name' => 'payment_option', 'contents' => $paymentOpt],
                    ['name' => 'return_url',     'contents' => $returnUrl],
                    ['name' => 'push_back_url',  'contents' => $pushbackUrl],
                    ['name' => 'hash',           'contents' => $hash],
                ]);

                Log::info('ABA Sandbox Response:', [
                    'sent_hash_string' => $hashStr,
                    'status_code'      => $response->status(),
                    'body'             => $response->json() ?? $response->body()
                ]);

                $dataResult = $response->json();
                $qrImage    = $dataResult['qrImage'] ?? null;
                $qrString   = $dataResult['qrString'] ?? null;
            } catch (\Exception $e) {
                Log::error('ABA API Error: ' . $e->getMessage());
            }
        }

        // 6. Generate fallback KHQR Code for local test / mock
        if (!$qrImage) {
            $qrPayload = $qrString ?: $this->generateKhqrPayload($order);
            $qrCodeSvg = QrCode::format('svg')
                ->size(250)
                ->errorCorrection('H')
                ->generate($qrPayload);

            $qrImage  = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);
            $qrString = $qrPayload;
        }

        return [
            'success'      => true,
            'order_number' => $order->order_number,
            'amount'       => $amount,
            'qr_image'     => $qrImage,
            'qr_string'    => $qrString,
        ];
    }

    /**
     * Generate fallback dummy KHQR payload
     */
    private function generateKhqrPayload(Order $order): string
    {
        return "https://bakong.nbc.org.kh/pay?merchant=topup_store&amount={$order->amount}&currency=USD&order_id={$order->order_number}";
    }
}
