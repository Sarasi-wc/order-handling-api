<?php

namespace App\Domain\Orders\Jobs;

use App\Domain\Orders\Actions\ProcessRefund;
use App\Domain\Orders\Models\Order;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;
    protected string $refundReference;
    protected string $type;
    protected float $amount;
    protected ?string $reason;

    /**
     * Create a new job instance
     */
    public function __construct(
        Order $order,
        string $refundReference,
        string $type,
        float $amount,
        ?string $reason = null
    ) {
        $this->order = $order;
        $this->refundReference = $refundReference;
        $this->type = $type;
        $this->amount = $amount;
        $this->reason = $reason;

        // Set the queue to 'refunds' to process separately
        $this->onQueue('refunds');
    }

    /**
     * Execute the job
     */
    public function handle(ProcessRefund $processRefund): void
    {
        try {
            $refund = $processRefund->execute(
                $this->order,
                $this->refundReference,
                $this->type,
                $this->amount,
                $this->reason
            );

            Log::info("Refund processed successfully", [
                'refund_id' => $refund->id,
                'refund_reference' => $this->refundReference,
                'order_id' => $this->order->order_id,
                'type' => $this->type,
                'amount' => $this->amount,
                'customer' => $this->order->customer_email,
            ]);
        } catch (DomainException $e) {
            Log::warning("Refund processing failed - domain validation error", [
                'order_id' => $this->order->order_id,
                'refund_reference' => $this->refundReference,
                'error' => $e->getMessage(),
            ]);

            // Don't retry domain validation errors
            $this->fail($e);
        } catch (\Exception $e) {
            Log::error("Refund processing failed - unexpected error", [
                'order_id' => $this->order->order_id,
                'refund_reference' => $this->refundReference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Refund job failed permanently", [
            'order_id' => $this->order->order_id,
            'refund_reference' => $this->refundReference,
            'error' => $exception->getMessage(),
        ]);
    }
}
