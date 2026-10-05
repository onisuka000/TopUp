<?php

namespace App\Http\Controllers\Api;

use App\Contracts\OrderServiceContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;

class OrderController extends Controller
{
    public function __construct(
        protected OrderServiceContract $orderService
    ) {}

    /**
     * Endpoint បង្កើត Order និងទាញយក QR Code សម្រាប់ទូទាត់ប្រាក់
     * POST /api/orders/create
     */
    public function create(CreateOrderRequest $request)
    {
        $result = $this->orderService->create($request->validated());

        return response()->json($result);
    }

    /**
     * Endpoint ឆែកមើលស្ថានភាពបង់ប្រាក់ (Polling ពី Frontend)
     * GET /api/orders/{orderNumber}/status
     */
    public function getStatus(string $orderNumber)
    {
        $status = $this->orderService->getStatus($orderNumber);

        if (!$status) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($status);
    }

    /**
     * ឆែកមើលស្ថានភាពប្រតិបត្តិការផ្ទាល់ជាមួយ ABA PayWay API
     * GET /api/orders/{orderNumber}/check-aba
     */
    public function checkWithAba(string $orderNumber)
    {
        $result = $this->orderService->checkWithAba($orderNumber);

        return response()->json($result['response'], $result['status_code']);
    }
}
