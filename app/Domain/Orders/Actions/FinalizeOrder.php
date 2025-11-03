<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use DomainException;

class FinalizeOrder
{
    public function execute(Order $order): void
    {
        if (! $order->canTransitionTo(OrderStatus::COMPLETED)) {
            throw new DomainException("Invalid transition: {$order->status->name} → COMPLETED");
        }

        $order->update([
            'status' => OrderStatus::COMPLETED,
        ]);
    }
}
