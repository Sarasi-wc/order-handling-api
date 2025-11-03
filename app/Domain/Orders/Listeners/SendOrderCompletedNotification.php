<?php

namespace App\Domain\Orders\Listeners;

use App\Domain\Orders\Events\OrderCompleted;
use App\Domain\Orders\Jobs\SendOrderNotificationJob;

class SendOrderCompletedNotification
{
    /**
     * Handle the event
     */
    public function handle(OrderCompleted $event): void
    {
        SendOrderNotificationJob::dispatch($event->order, 'completed');
    }
}
