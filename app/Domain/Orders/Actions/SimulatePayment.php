<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderPaid;
use App\Domain\Orders\Models\Order;
use Exception;
use Illuminate\Support\Facades\Log;

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
            'payment_reference' => 'PAY-'.strtoupper(uniqid()),
            'status' => OrderStatus::PAID,
        ]);

        Log::info("Payment simulated successfully for Order #{$order->order_id}");

        event(new OrderPaid($order));
    }
}
