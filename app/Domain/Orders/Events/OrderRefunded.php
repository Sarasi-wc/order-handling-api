<?php

namespace App\Domain\Orders\Events;

use App\Domain\Orders\Models\Refund;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Refund $refund
    ) {}
}
