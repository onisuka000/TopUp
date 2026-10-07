<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TokovoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected TokovoucherService $tokovoucherService
    ) {}

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

        if ($hash && $hash !== $expectedHash) {
            Log::warning("Invalid ABA Signature for Order: {$tranId}");
            return response()->json(['message' => 'Invalid signature'], 400);
        }

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

            // ៥. ហៅ Tokovoucher API ដើម្បីបញ្ចូលពេជ្រ Mobile Legends ភ្លាមៗ
            $topUpResult = $this->tokovoucherService->topUp($order);

            Log::info("Tokovoucher TopUp dispatched for order {$order->order_number}:", $topUpResult);

            return response()->json(['status' => 'SUCCESS'], 200);
        }

        return response()->json(['message' => 'Transaction not approved'], 400);
    }

    /**
     * Webhook ទទួល Callback ពី Tokovoucher
     * POST /api/webhook/tokovoucher
     */
    public function handleTokovoucherWebhook(Request $request)
    {
        Log::info('Tokovoucher Webhook Received:', [
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
            'ip'      => $request->ip(),
        ]);

        $refId     = $request->input('ref_id');
        $status    = strtolower((string) $request->input('status'));
        $trxId     = $request->input('trx_id');
        $sn        = $request->input('sn');
        $message   = $request->input('message');
        $signature = $request->header('X-TokoVoucher-Authorization');

        if (empty($refId)) {
            return response()->json(['message' => 'Missing ref_id'], 400);
        }

        // ផ្ទៀងផ្ទាត់ Signature ប្រសិនបើមានផ្ញើមកក្នុង Header
        if ($signature && !$this->tokovoucherService->validateWebhookSignature($refId, $signature)) {
            Log::warning("Invalid Tokovoucher Webhook Signature for Order: {$refId}");
            return response()->json(['message' => 'Invalid Tokovoucher signature'], 401);
        }

        $order = Order::where('order_number', $refId)->first();
        if (!$order) {
            Log::error("Tokovoucher Webhook Order not found: {$refId}");
            return response()->json(['message' => 'Order not found'], 404);
        }

        // ប្រសិនបើ Order បានជោគជ័យរួចហើយ មិនបាច់ update ឡើងវិញទេ (Idempotent)
        if ($order->status === 'COMPLETED') {
            return response()->json(['status' => 'SUCCESS', 'message' => 'Order already completed'], 200);
        }

        if ($status === 'sukses' || $status === '1') {
            $order->update([
                'status'          => 'COMPLETED',
                'provider_ref_id' => $trxId ?: ($sn ?: 'TOKOVOUCHER_WEBHOOK'),
                'error_message'   => null,
            ]);

            Log::info("Tokovoucher Webhook: Order {$refId} completed successfully.");
        } elseif ($status === 'gagal' || $status === '0') {
            $order->update([
                'status'        => 'FAILED',
                'error_message' => $message ?: 'Tokovoucher reported transaction failure',
            ]);

            Log::warning("Tokovoucher Webhook: Order {$refId} failed: {$message}");
        }

        return response()->json(['status' => 'SUCCESS'], 200);
    }
}
