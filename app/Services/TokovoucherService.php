<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokovoucherService
{
    protected string $memberCode;
    protected string $secretKey;
    protected string $baseUrl;
    protected string $rootUrl;
    protected string $v1Url;

    public function __construct()
    {
        $this->memberCode = (string) config('services.tokovoucher.member_code');
        $this->secretKey  = (string) config('services.tokovoucher.secret_key');

        $rawUrl = config('services.tokovoucher.url', 'https://api.tokovoucher.net');
        $this->baseUrl = rtrim((string) $rawUrl, '/');
        $this->rootUrl = preg_replace('#/v1$#', '', $this->baseUrl) ?: 'https://api.tokovoucher.net';
        $this->v1Url   = $this->rootUrl . '/v1';
    }

    /**
     * Generate MD5 signature for transaction / status check:
     * md5(MEMBER_CODE:SECRET_KEY:REF_ID)
     */
    public function generateSignature(string $refId): string
    {
        return md5($this->memberCode . ':' . $this->secretKey . ':' . $refId);
    }

    /**
     * Generate default signature for catalog / member queries.
     */
    public function generateDefaultSignature(): string
    {
        $configured = config('services.tokovoucher.signature_default');
        if (!empty($configured)) {
            return (string) $configured;
        }

        return md5($this->memberCode . ':' . $this->secretKey);
    }

    /**
     * Validate incoming webhook authorization signature.
     */
    public function validateWebhookSignature(string $refId, ?string $receivedSignature): bool
    {
        if (empty($receivedSignature)) {
            return false;
        }

        return hash_equals($this->generateSignature($refId), trim($receivedSignature));
    }

    /**
     * Execute Mobile Legends (or any game) top-up via Tokovoucher POST API.
     *
     * @param Order $order
     * @return array
     */
    public function topUp(Order $order): array
    {
        $order->loadMissing('product');
        $product = $order->product;

        if (!$product || empty($product->provider_code)) {
            $errorMsg = 'Product does not have a valid Tokovoucher provider_code (SKU).';
            Log::error("Tokovoucher TopUp Error: {$errorMsg}", [
                'order_number' => $order->order_number,
                'product_id'   => $order->product_id,
            ]);

            $order->update([
                'status'        => 'FAILED',
                'error_message' => $errorMsg,
            ]);

            return [
                'success' => false,
                'status'  => 'gagal',
                'message' => $errorMsg,
            ];
        }

        $refId     = $order->order_number;
        $signature = $this->generateSignature($refId);
        $url       = $this->v1Url . '/transaksi';

        $payload = [
            'ref_id'      => $refId,
            'produk'      => $product->provider_code,
            'tujuan'      => (string) $order->game_user_id,
            'server_id'   => (string) ($order->zone_id ?? ''),
            'member_code' => $this->memberCode,
            'signature'   => $signature,
        ];

        Log::info('Tokovoucher TopUp Request Dispatching:', [
            'order_number' => $order->order_number,
            'produk'       => $product->provider_code,
            'tujuan'       => $order->game_user_id,
            'server_id'    => $order->zone_id,
            'endpoint'     => $url,
        ]);

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post($url, $payload);

            $data = $response->json();

            Log::info('Tokovoucher TopUp Response Received:', [
                'order_number' => $order->order_number,
                'http_status'  => $response->status(),
                'response'     => $data,
            ]);

            if (!is_array($data)) {
                $rawBody = $response->body();
                Log::warning('Tokovoucher raw response non-JSON: ' . $rawBody);
                $order->update([
                    'status'        => 'PROCESSING',
                    'error_message' => 'Pending: Non-standard provider response',
                ]);

                return [
                    'success' => false,
                    'status'  => 'pending',
                    'message' => 'Non-standard provider response',
                    'raw'     => $rawBody,
                ];
            }

            $status = strtolower((string) ($data['status'] ?? ''));

            // 1. Transaction Success
            if ($status === 'sukses' || $status === '1') {
                $order->update([
                    'status'          => 'COMPLETED',
                    'provider_ref_id' => $data['trx_id'] ?? $data['sn'] ?? 'TOKOVOUCHER_SUCCESS',
                    'error_message'   => null,
                ]);

                return [
                    'success' => true,
                    'status'  => 'sukses',
                    'data'    => $data,
                ];
            }

            // 2. Transaction Pending (Being processed by operator/supplier)
            if ($status === 'pending') {
                $order->update([
                    'status'          => 'PROCESSING',
                    'provider_ref_id' => $data['trx_id'] ?? null,
                    'error_message'   => $data['message'] ?? 'Sedang diproses oleh Tokovoucher',
                ]);

                return [
                    'success' => true,
                    'status'  => 'pending',
                    'data'    => $data,
                ];
            }

            // 3. Transaction Failed
            $errorMsg = $data['message'] ?? $data['error_msg'] ?? 'Transaction failed at Tokovoucher';
            $order->update([
                'status'        => 'FAILED',
                'error_message' => $errorMsg,
            ]);

            return [
                'success' => false,
                'status'  => 'gagal',
                'message' => $errorMsg,
                'data'    => $data,
            ];

        } catch (\Exception $e) {
            // Note: Tokovoucher guidelines state:
            // "Semua HTTP Error / NETWORK TIMEOUT harus diset sebagai transaksi PENDING"
            Log::error('Tokovoucher TopUp HTTP Exception: ' . $e->getMessage(), [
                'order_number' => $order->order_number,
            ]);

            $order->update([
                'status'        => 'PROCESSING',
                'error_message' => 'Timeout/Network error: awaiting status verification (' . $e->getMessage() . ')',
            ]);

            return [
                'success' => false,
                'status'  => 'pending',
                'message' => 'Network error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check transaction status with Tokovoucher.
     * POST /v1/transaksi/status
     */
    public function checkStatus(string $orderNumber): array
    {
        $signature = $this->generateSignature($orderNumber);
        $url       = $this->v1Url . '/transaksi/status';

        $payload = [
            'ref_id'      => $orderNumber,
            'member_code' => $this->memberCode,
            'signature'   => $signature,
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post($url, $payload);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Tokovoucher Check Status Exception: ' . $e->getMessage());
            return [
                'status'    => 0,
                'error_msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Tokovoucher member balance.
     * GET /member?member_code=...&signature=...
     */
    public function checkBalance(): array
    {
        $url = $this->rootUrl . '/member';
        $signature = $this->generateDefaultSignature();

        try {
            $response = Http::timeout(15)->get($url, [
                'member_code' => $this->memberCode,
                'signature'   => $signature,
            ]);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return [
                'status'    => 0,
                'error_msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search products by code prefix (e.g. 'ML' for Mobile Legends).
     * GET /produk/code?member_code=...&signature=...&kode=...
     */
    public function getProducts(string $code = 'ML'): array
    {
        $url = $this->rootUrl . '/produk/code';
        $signature = $this->generateDefaultSignature();

        try {
            $response = Http::timeout(15)->get($url, [
                'member_code' => $this->memberCode,
                'signature'   => $signature,
                'kode'        => $code,
            ]);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return [
                'status'    => 0,
                'error_msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get configured IDR per 1 USD exchange rate (defaults to 16,000 IDR).
     */
    public function getExchangeRate(): float
    {
        $currencyRate = Currency::getRate('IDR', 0);
        if ($currencyRate > 0) {
            return $currencyRate;
        }

        $val = Setting::get('tokovoucher_exchange_rate');
        return $val && is_numeric($val) && (float) $val > 0 ? (float) $val : 16000.0;
    }

    /**
     * Update configured IDR per 1 USD exchange rate.
     */
    public function setExchangeRate(float $rate): void
    {
        Setting::set('tokovoucher_exchange_rate', $rate, 'IDR per 1 USD Exchange Rate');

        Currency::updateOrCreate(
            ['currency_code' => 'IDR'],
            [
                'currency'      => 'Indonesian Rupiah',
                'symbol'        => 'Rp',
                'exchange_rate' => $rate,
                'is_default'    => false,
                'is_active'     => true,
            ]
        );
    }

    /**
     * Get configured profit margin percent (defaults to 15%).
     */
    public function getProfitMargin(): float
    {
        $val = Setting::get('tokovoucher_profit_margin');
        return $val !== null && is_numeric($val) ? (float) $val : 15.0;
    }

    /**
     * Update configured profit margin percent.
     */
    public function setProfitMargin(float $margin): void
    {
        Setting::set('tokovoucher_profit_margin', $margin, 'Default Profit Margin (%)');
    }

    /**
     * Recalculate USD cost price for all products that have a recorded Tokovoucher IDR price.
     */
    public function recalculateCosts(float $rate): int
    {
        $products = Product::whereNotNull('tokovoucher_price')
            ->where('tokovoucher_price', '>', 0)
            ->get();

        $count = 0;
        foreach ($products as $product) {
            $costUsd = round(((float) $product->tokovoucher_price) / $rate, 2);
            $product->update([
                'cost_price' => $costUsd,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Sync products prices directly from Tokovoucher catalog.
     *
     * @param string $code Prefix to fetch (e.g. 'ML' for Mobile Legends)
     * @param float|null $customRate IDR to USD rate
     * @param float|null $customMargin Profit margin percentage
     * @param bool $autoUpdateSellingPrice Whether to auto-bump selling_price if below cost
     * @return array
     */
    public function syncPrices(
        string $code = 'ML',
        ?float $customRate = null,
        ?float $customMargin = null,
        bool $autoUpdateSellingPrice = false
    ): array {
        $rate   = $customRate ?: $this->getExchangeRate();
        $margin = $customMargin !== null ? $customMargin : $this->getProfitMargin();

        $catalog = $this->getProducts($code);

        if (($catalog['status'] ?? 0) != 1) {
            return [
                'success' => false,
                'message' => $catalog['error_msg'] ?? 'Failed to communicate with Tokovoucher API.',
                'synced'  => 0,
            ];
        }

        $items = $catalog['data'] ?? [];
        if (empty($items)) {
            return [
                'success' => true,
                'message' => 'No products returned by Tokovoucher for this code.',
                'synced'  => 0,
            ];
        }

        // Index Tokovoucher items by normalized uppercase code
        $catalogMap = [];
        foreach ($items as $item) {
            if (!empty($item['code'])) {
                $catalogMap[strtoupper(trim($item['code']))] = $item;
            }
        }

        $ourProducts = Product::all();
        $syncedCount = 0;
        $updatedList = [];

        foreach ($ourProducts as $product) {
            $providerCode = strtoupper(trim((string) $product->provider_code));
            $matchedItem  = null;

            // 1. Direct exact match
            if (isset($catalogMap[$providerCode])) {
                $matchedItem = $catalogMap[$providerCode];
            } else {
                // 2. Intelligent alias matching for Mobile Legends
                // e.g. 'mlbb_86' or 'ML86' matches 'MLBBGLO86' or 'MLGLOKP86' or 'MLA86'
                $digits = preg_replace('/\D/', '', $providerCode);
                $isPass = str_contains(strtolower($providerCode), 'pass') || str_contains(strtolower($providerCode), 'wdp');
                $isTwilight = str_contains(strtolower($providerCode), 'twilight');

                foreach ($catalogMap as $k => $cItem) {
                    if ($cItem['status'] != 1) continue;

                    if ($isPass && (str_contains($k, 'GLOWDP') || str_contains($k, 'KP_WDP') || str_contains($k, 'MLWDP') || str_ends_with($k, 'WDP'))) {
                        $matchedItem = $cItem;
                        break;
                    }
                    if ($isTwilight && (str_contains($k, 'GLOKPTP') || str_contains($k, 'MLATP') || str_ends_with($k, 'TP'))) {
                        $matchedItem = $cItem;
                        break;
                    }
                    if ($digits !== '') {
                        if ($k === "MLBBGLO{$digits}" || $k === "MLGLOKP{$digits}" || $k === "MLA{$digits}" || $k === "ML{$digits}") {
                            $matchedItem = $cItem;
                            break;
                        }
                    }
                }
            }

            if ($matchedItem) {
                $idrPrice = (float) ($matchedItem['price'] ?? 0);
                $costUsd  = round($idrPrice / $rate, 2);

                $updateData = [
                    'provider_code'     => $matchedItem['code'], // bind exact valid Tokovoucher code
                    'tokovoucher_price' => $idrPrice,
                    'cost_price'        => $costUsd,
                    'last_synced_at'    => now(),
                ];

                // If current selling price is less than cost price, or auto update requested
                if ($autoUpdateSellingPrice || (float) $product->selling_price < $costUsd) {
                    $multiplier = 1 + ($margin / 100);
                    $updateData['selling_price'] = round($costUsd * $multiplier, 2);
                }

                $product->update($updateData);
                $syncedCount++;
                $updatedList[] = [
                    'product_id'        => $product->id,
                    'name'              => $product->name,
                    'code'              => $matchedItem['code'],
                    'tokovoucher_price' => $idrPrice,
                    'cost_price'        => $costUsd,
                    'selling_price'     => $product->fresh()->selling_price,
                ];
            }
        }

        return [
            'success'      => true,
            'message'      => "Successfully synchronized {$syncedCount} products from Tokovoucher.",
            'synced_count' => $syncedCount,
            'rate'         => $rate,
            'updated'      => $updatedList,
        ];
    }
}
