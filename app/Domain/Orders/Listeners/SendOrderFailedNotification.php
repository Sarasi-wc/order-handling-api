<?php

namespace App\Domain\Orders\Listeners;

use App\Domain\Orders\Events\OrderFailed;
use App\Domain\Orders\Jobs\SendOrderNotificationJob;

class SendOrderFailedNotification
{
    /**
     * Handle the event
     */
    public function handle(OrderFailed $event): void
    {
        SendOrderNotificationJob::dispatch($event->order, 'failed');
    }
}
