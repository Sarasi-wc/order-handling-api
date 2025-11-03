<?php

namespace App\Domain\Orders\Listeners;

use App\Domain\Orders\Events\OrderCompleted;
use App\Domain\Orders\Services\KPIService;
use App\Domain\Orders\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateKPIMetrics implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'kpi';

    public function __construct(
        private KPIService $kpiService,
        private LeaderboardService $leaderboardService
    ) {}

    /**
     * Handle the event
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;
        $amount = $order->total_amount;
        $date = $order->completed_at ?? now();

        try {
            // Update daily KPI metrics
            $this->kpiService->recordOrder($date, $amount);

            // Update customer leaderboard
            $this->leaderboardService->incrementCustomerSpending(
                $order->customer_email,
                $amount
            );

            Log::info("KPI metrics updated for Order #{$order->order_id}", [
                'revenue' => $amount,
                'customer' => $order->customer_email,
                'date' => $date->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update KPI metrics for Order #{$order->order_id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(OrderCompleted $event, \Throwable $exception): void
    {
        Log::error("KPI metrics listener failed for Order #{$event->order->order_id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
