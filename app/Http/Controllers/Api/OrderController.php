<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrderController extends Controller
{
    /**
     * Endpoint បង្កើត Order និងទាញយក QR Code សម្រាប់ទូទាត់ប្រាក់
     * POST /api/orders/create
     */
    public function create(Request $request)
    {
        // ១. ផ្ទៀងផ្ទាត់ទិន្នន័យពី Frontend
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'user_id'    => 'required|string',
            'zone_id'    => 'nullable|string',
        ]);

        $product = Product::with('game')->findOrFail($validated['product_id']);

        // ២. បង្កើត Order Number ពិសេស (ឧ. ORD-20261005-XXXX)
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        // ៣. បង្កើត Record ក្នុង Database (Status: PENDING)
        $order = Order::create([
            'order_number'    => $orderNumber,
            'product_id'      => $product->id,
            'game_user_id'    => $validated['user_id'],
            'zone_id'         => $validated['zone_id'],
            'amount'          => $product->selling_price,
            'payment_method'  => 'KHQR',
            'status'          => 'PENDING',
        ]);

        // ៤. ព័ត៌មាន ABA PayWay Config
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
        $shipping    = ''; // ទទេសម្រាប់ digital goods
        $returnUrl   = base64_encode('https://yourdomain.com');
        $pushbackUrl = 'https://fleshy-emit-pellet.ngrok-free.dev/api/webhook/aba';

        // Items ត្រូវ Encode ជា Base64 JSON
        $items = base64_encode(json_encode([
            [
                'name'     => $product->name,
                'quantity' => 1,
                'price'    => (float) $amount,
            ]
        ]));

        // ១. រូបមន្ត Hash ផ្លូវការ (គ្មាន push_back_url ក្នុង string ទេ)
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

        // ២. បាញ់ Request ជា Multipart Form-Data (តម្រូវការចាំបាច់របស់ ABA PayWay)
        $response = Http::asMultipart()->post($apiUrl, [
            [
                'name'     => 'req_time',
                'contents' => $reqTime,
            ],
            [
                'name'     => 'merchant_id',
                'contents' => $merchantId,
            ],
            [
                'name'     => 'tran_id',
                'contents' => $tranId,
            ],
            [
                'name'     => 'amount',
                'contents' => $amount,
            ],
            [
                'name'     => 'items',
                'contents' => $items,
            ],
            [
                'name'     => 'firstname',
                'contents' => $firstName,
            ],
            [
                'name'     => 'lastname',
                'contents' => $lastName,
            ],
            [
                'name'     => 'email',
                'contents' => $email,
            ],
            [
                'name'     => 'phone',
                'contents' => $phone,
            ],
            [
                'name'     => 'type',
                'contents' => $type,
            ],
            [
                'name'     => 'payment_option',
                'contents' => $paymentOpt,
            ],
            [
                'name'     => 'return_url',
                'contents' => $returnUrl,
            ],
            [
                'name'     => 'push_back_url',
                'contents' => $pushbackUrl,
            ],
            [
                'name'     => 'hash',
                'contents' => $hash,
            ],
        ]);

        Log::info('ABA Sandbox Response:', [
            'sent_hash_string' => $hashStr,
            'status_code'      => $response->status(),
            'body'             => $response->json() ?? $response->body()
        ]);

        // ៥. ហៅទៅ ABA Sandbox API (បើសិនជាមាន Config ត្រឹមត្រូវ)
        if ($merchantId && $apiKey && $apiUrl) {
            try {
                // រូបមន្ត Hash របស់ ABA PayWay
                $hashStr = $reqTime . $merchantId . $tranId . $amount . '' . '' . 'purchase' . 'USD' . $pushbackUrl;
                $hash = base64_encode(hash_hmac('sha512', $hashStr, $apiKey, true));

                $response = Http::asForm()->timeout(10)->post($apiUrl, [
                    'req_time'       => $reqTime,
                    'merchant_id'    => $merchantId,
                    'tran_id'        => $tranId,
                    'amount'         => $amount,
                    'payment_option' => 'abapay_khqr',
                    'currency'       => 'USD',
                    'hash'           => $hash,
                    'return_url'     => base64_encode('https://yourdomain.com'),
                    'push_back_url'  => $pushbackUrl,
                ]);

                $data = $response->json();
                $qrImage  = $data['qrImage'] ?? null;
                $qrString = $data['qrString'] ?? null;
            } catch (\Exception $e) {
                Log::error('ABA API Error: ' . $e->getMessage());
            }
        }

        // ៦. បើ ABA មិនទាន់ឆ្លើយតប ឬស្ថិតក្នុង Local Mock Test: Generate Local QR Code
        if (!$qrImage) {
            $qrPayload = $qrString ?: $this->generateKhqrPayload($order);
            $qrCodeSvg = QrCode::format('svg')
                ->size(250)
                ->errorCorrection('H')
                ->generate($qrPayload);

            $qrImage = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);
            $qrString = $qrPayload;
        }

        // ៧. ឆ្លើយតបទៅកាន់ Vue SPA Frontend
        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'amount'       => $amount,
            'qr_image'     => $qrImage,
            'qr_string'    => $qrString,
        ]);
    }

    /**
     * Endpoint ឆែកមើលស្ថានភាពបង់ប្រាក់ (Polling ពី Frontend)
     * GET /api/orders/{orderNumber}/status
     */
    public function getStatus($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status'       => $order->status, // PENDING, PAID, PROCESSING, COMPLETED, FAILED
        ]);
    }

    /**
     * ជំនួយការបង្កើត Dummy KHQR String សម្រាប់ Test ពេលគ្មាន API
     */
    private function generateKhqrPayload(Order $order): string
    {
        return "https://bakong.nbc.org.kh/pay?merchant=topup_store&amount={$order->amount}&currency=USD&order_id={$order->order_number}";
    }
}
