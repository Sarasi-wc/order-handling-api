<?php

namespace App\Domain\Orders\Jobs;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\KPIService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDailyKPIsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private ?Carbon $date = null
    ) {
        // Default to yesterday if no date provided
        $this->date = $date ?? now()->subDay();

        // Set the queue
        $this->onQueue('kpi');
    }

    /**
     * Execute the job
     */
    public function handle(KPIService $kpiService): void
    {
        Log::info("Generating daily KPIs for {$this->date->toDateString()}");

        // Query orders completed on the specified date
        $kpis = Order::whereDate('completed_at', $this->date)
            ->where('status', OrderStatus::COMPLETED)
            ->selectRaw('
                COUNT(*) as order_count,
                SUM(quantity * unit_price) as revenue
            ')
            ->first();

        // Store snapshot in Redis
        $kpiService->storeDailySnapshot($this->date, $kpis);

        Log::info("Daily KPIs generated successfully for {$this->date->toDateString()}", [
            'order_count' => $kpis->order_count ?? 0,
            'revenue' => $kpis->revenue ?? 0,
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to generate daily KPIs for {$this->date->toDateString()}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
