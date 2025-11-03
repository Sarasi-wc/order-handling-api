<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderReserved;
use App\Domain\Orders\Models\Order;
use DomainException;

class ReserveStock
{
    public function execute(Order $order): void
    {
        if (! $order->canTransitionTo(OrderStatus::RESERVED)) {
            throw new DomainException("Invalid transition: {$order->status->name} → RESERVED");
        }

        $order->update([
            'reserved_stock' => true,
            'status' => OrderStatus::RESERVED,
        ]);

        event(new OrderReserved($order));
    }
}
