<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
    /**
     * Webhook ទទួលដំណឹងពី ABA PayWay
     * POST /api/webhook/aba
     */
    public function handleAbaWebhook(Request $request)
    {
        // កត់ត្រា Log សម្រាប់ត្រួតពិនិត្យ (Audit Log)
        Log::info('ABA Webhook Received:', $request->all());

        $tranId = $request->input('tran_id'); // Order Number របស់យើង
        $status = $request->input('status');   // 'APPROVED'
        $hash   = $request->input('hash');
        $amount = $request->input('amount');

        // ១. ផ្ទៀងផ្ទាត់ Hash Signature ការពារការបន្លំ Request
        $apiKey = config('services.aba.api_key', env('ABA_API_KEY'));
        
        // រូបមន្តគណនា Hash របស់ ABA PayWay: Base64(HMAC-SHA512(tran_id + amount + status, apiKey))
        $expectedHash = base64_encode(
            hash_hmac('sha512', $tranId . $amount . $status, $apiKey, true)
        );

        // if ($hash !== $expectedHash) {
        //     Log::warning("Invalid ABA Signature for Order: {$tranId}");
        //     return response()->json(['message' => 'Invalid signature'], 400);
        // }

        // ២. ស្វែងរក Order ក្នុង Database
        $order = Order::where('order_number', $tranId)->first();
        if (!$order) {
            Log::error("Order not found: {$tranId}");
            return response()->json(['message' => 'Order not found'], 404);
        }

        // ៣. Idempotency Check៖ ប្រសិនបើ Order នេះបានដំណើរការរួចហើយ ឆ្លើយតប OK ភ្លាម
        if (in_array($order->status, ['COMPLETED', 'PAID', 'PROCESSING'])) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        // ៤. ពិនិត្យស្ថានភាពបង់ប្រាក់
        if ($status === 'APPROVED' && (float)$amount === (float)$order->amount) {
            // Update ស្ថានភាពទៅជា PAID
            $order->update(['status' => 'PAID']);

            // ៥. ហៅ Provider API ដើម្បីបញ្ចូលពេជ្រភ្លាមៗ
            $topUpSuccess = $this->triggerProviderTopUp($order);

            if ($topUpSuccess) {
                $order->update(['status' => 'COMPLETED']);
                Log::info("Topup Completed successfully for order: {$order->order_number}");
            } else {
                $order->update(['status' => 'FAILED']);
                Log::error("Topup Failed at Provider for order: {$order->order_number}");
            }

            return response()->json(['status' => 'SUCCESS'], 200);
        }

        return response()->json(['message' => 'Transaction not approved'], 400);
    }

    /**
     * មុខងារបាញ់បញ្ជាទៅ Game Provider API (ឧ. Smile One)
     */
    private function triggerProviderTopUp(Order $order): bool
    {
        try {
            $product = $order->product;
            $time    = time();
            $email   = env('SMILE_ONE_EMAIL');
            $uid     = env('SMILE_ONE_UID');
            $key     = env('SMILE_ONE_KEY');

            // គណនា Signature តាម Provider
            $sign = md5("email={$email}&time={$time}&uid={$uid}{$key}");

            $payload = [
                'email'      => $email,
                'uid'        => $uid,
                'userid'     => $order->game_user_id,
                'zoneid'     => $order->zone_id,
                'product_id' => $product->provider_code, // SKU ពេជ្រ
                'time'       => $time,
                'sign'       => $sign,
            ];

            // ហៅ API Provider
            $response = Http::timeout(15)->post('https://api.smile.one/api/topup', $payload);

            if ($response->successful() && $response->json('status') == 200) {
                $order->update([
                    'provider_ref_id' => $response->json('data.order_id') ?? 'PROV_SUCCESS'
                ]);
                return true;
            }

            $order->update([
                'error_message' => $response->json('message') ?? 'Provider returned error'
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Provider Exception: ' . $e->getMessage());
            $order->update(['error_message' => $e->getMessage()]);
            return false;
        }
    }
}
