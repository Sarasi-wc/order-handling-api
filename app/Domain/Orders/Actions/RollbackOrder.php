<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Support\Facades\Log;

class RollbackOrder
{
    public function execute(Order $order): void
    {
        if (! $order->status->isFinal()) {
            $order->update([
                'status' => OrderStatus::FAILED,
                'reserved_stock' => false,
            ]);

            Log::warning("Order #{$order->order_id} rolled back due to workflow failure.");
        } else {
            Log::info("Order #{$order->order_id} rollback skipped — already in final state ({$order->status->value}).");
        }
    }
}

