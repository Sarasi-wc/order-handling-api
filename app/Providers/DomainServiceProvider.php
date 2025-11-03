<?php

namespace App\Providers;

use App\Domain\Orders\Console\ImportOrdersCommand;
use App\Domain\Orders\Console\TestOrderWorkflowCommand;
use App\Domain\Orders\Events\OrderCompleted;
use App\Domain\Orders\Events\OrderFailed;
use App\Domain\Orders\Listeners\SendOrderCompletedNotification;
use App\Domain\Orders\Listeners\SendOrderFailedNotification;
use App\Domain\Orders\Listeners\UpdateKPIMetrics;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->commands([
            ImportOrdersCommand::class,
            TestOrderWorkflowCommand::class,
        ]);

        // Register event listeners
        Event::listen(OrderCompleted::class, [
            UpdateKPIMetrics::class,
            SendOrderCompletedNotification::class,
        ]);

        Event::listen(OrderFailed::class, [
            SendOrderFailedNotification::class,
        ]);
    }
}
