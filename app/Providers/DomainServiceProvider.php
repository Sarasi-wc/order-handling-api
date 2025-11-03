<?php

namespace App\Providers;

use App\Domain\Orders\Console\ImportOrdersCommand;
use App\Domain\Orders\Console\TestOrderWorkflowCommand;
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
    }
}
