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
        protected CreateOrderAction $createOrderAction
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

        return [
            'order_number' => $order->order_number,
            'status'       => $order->status,
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
                if ($order->status !== 'COMPLETED') {
                    $order->update([
                        'status'          => 'COMPLETED',
                        'provider_ref_id' => $abaData['payment_details']['tran_id'] ?? 'ABA_VERIFIED',
                    ]);
                }

                return [
                    'status_code' => 200,
                    'response'    => [
                        'success'      => true,
                        'order_status' => 'COMPLETED',
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
