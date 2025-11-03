<?php

namespace App\Domain\Orders\Jobs;

use App\Domain\Orders\Actions\FinalizeOrder;
use App\Domain\Orders\Actions\ReserveStock;
use App\Domain\Orders\Actions\RollbackOrder;
use App\Domain\Orders\Actions\SimulatePayment;
use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOrderWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;

        // Set the queue
        $this->onQueue('orders');
    }

    /**
     * @throws Throwable
     */
    public function handle(
        ReserveStock $reserveStock,
        SimulatePayment $simulatePayment,
        FinalizeOrder $finalizeOrder,
        RollbackOrder $rollbackOrder
    ): void {
        try {
            $reserveStock->execute($this->order);
            $simulatePayment->execute($this->order);
            $finalizeOrder->execute($this->order);
        } catch (Throwable $e) {
            $rollbackOrder->execute($this->order);
            throw $e;
        }
    }
}
