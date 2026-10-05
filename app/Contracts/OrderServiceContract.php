<?php

namespace App\Contracts;

interface OrderServiceContract
{
    /**
     * Create a new order and generate payment details.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array;

    /**
     * Get the payment status of an order.
     *
     * @param string $orderNumber
     * @return array|null
     */
    public function getStatus(string $orderNumber): ?array;

    /**
     * Query and verify order transaction status directly with ABA PayWay.
     *
     * @param string $orderNumber
     * @return array
     */
    public function checkWithAba(string $orderNumber): array;
}
