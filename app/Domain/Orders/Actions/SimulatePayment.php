<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Support\Facades\Log;
use Exception;

class SimulatePayment
{
    public function execute(Order $order): void
    {
        sleep(1);

        if (rand(1, 10) <= 3) {
            Log::warning("Payment simulation failed for Order #{$order->order_id}");
            throw new Exception('Payment failed during simulation.');
        }

        // Update order with a payment reference and mark as paid
        $order->update([
            'payment_reference' => 'PAY-' . strtoupper(uniqid()),
            'status' => OrderStatus::PAID,
        ]);

        Log::info("Payment simulated successfully for Order #{$order->order_id}");
    }
}
