<?php

namespace App\Services;

use App\Actions\CreateOrderAction;
use App\Contracts\OrderServiceContract;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderService implements OrderServiceContract
{
    public function __construct(
        protected CreateOrderAction $createOrderAction,
        protected TokovoucherService $tokovoucherService
    ) {}

    /**
     * Create a new order using the single action.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        return $this->createOrderAction->execute($data);
    }

    /**
     * Get order status by order number.
     *
     * @param string $orderNumber
     * @return array|null
     */
    public function getStatus(string $orderNumber): ?array
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return null;
        }

        // If order is PENDING and ABA credentials are configured, auto-check ABA
        if ($order->status === 'PENDING' && config('services.aba.api_key')) {
            $this->checkWithAba($orderNumber);
            $order->refresh();
        }

        // If order is PROCESSING, auto-check status with Tokovoucher
        if ($order->status === 'PROCESSING' && config('services.tokovoucher.secret_key')) {
            try {
                $tvStatus = $this->tokovoucherService->checkStatus($orderNumber);
                $statusStr = strtolower((string) ($tvStatus['status'] ?? ''));

                if ($statusStr === 'sukses' || $statusStr === '1') {
                    $order->update([
                        'status'          => 'COMPLETED',
                        'provider_ref_id' => $tvStatus['trx_id'] ?? $tvStatus['sn'] ?? $order->provider_ref_id,
                        'error_message'   => null,
                    ]);
                    $order->refresh();
                } elseif ($statusStr === 'gagal' || $statusStr === '0') {
                    $order->update([
                        'status'        => 'FAILED',
                        'error_message' => $tvStatus['message'] ?? 'Top-up failed at provider',
                    ]);
                    $order->refresh();
                }
            } catch (\Exception $e) {
                Log::warning('Tokovoucher auto-check in getStatus error: ' . $e->getMessage());
            }
        }

        return [
            'order_number'    => $order->order_number,
            'status'          => $order->status,
            'provider_ref_id' => $order->provider_ref_id,
            'error_message'   => $order->error_message,
        ];
    }

    /**
     * Verify transaction with ABA PayWay API.
     *
     * @param string $orderNumber
     * @return array
     */
    public function checkWithAba(string $orderNumber): array
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return [
                'status_code' => 404,
                'response'    => ['message' => 'Order not found'],
            ];
        }

        $merchantId = config('services.aba.merchant_id');
        $apiKey     = config('services.aba.api_key');
        $reqTime    = date('YmdHis');

        $hashStr = $reqTime . $merchantId . $orderNumber;
        $hash    = base64_encode(hash_hmac('sha512', $hashStr, $apiKey, true));

        try {
            $response = Http::asMultipart()->timeout(15)->post(
                'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction',
                [
                    ['name' => 'req_time',    'contents' => $reqTime],
                    ['name' => 'merchant_id', 'contents' => $merchantId],
                    ['name' => 'tran_id',     'contents' => $orderNumber],
                    ['name' => 'hash',        'contents' => $hash],
                ]
            );

            $abaData = $response->json();

            Log::info('ABA Check Transaction Response:', [
                'order_number' => $orderNumber,
                'response'     => $abaData,
            ]);

            $status = $abaData['status'] ?? null;

            if ($status === 0 || $status === '0') {
                if ($order->status === 'PENDING') {
                    $order->update([
                        'status'          => 'PAID',
                        'provider_ref_id' => $abaData['payment_details']['tran_id'] ?? 'ABA_VERIFIED',
                    ]);

                    // Trigger Tokovoucher TopUp
                    $this->tokovoucherService->topUp($order);
                }

                $order->refresh();

                return [
                    'status_code' => 200,
                    'response'    => [
                        'success'      => true,
                        'order_status' => $order->status,
                        'message'      => 'ការទូទាត់ប្រាក់ជោគជ័យ!',
                        'aba_raw'      => $abaData,
                    ],
                ];
            }

            return [
                'status_code' => 200,
                'response'    => [
                    'success'      => false,
                    'order_status' => $order->status,
                    'message'      => $abaData['description'] ?? 'មិនទាន់ទូទាត់ប្រាក់នៅឡើយទេ (Pending)',
                    'aba_raw'      => $abaData,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Check ABA Exception: ' . $e->getMessage());

            return [
                'status_code' => 500,
                'response'    => [
                    'success' => false,
                    'message' => 'Connection to ABA failed: ' . $e->getMessage(),
                ],
            ];
        }
    }
}
