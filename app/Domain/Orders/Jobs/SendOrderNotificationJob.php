<?php

namespace App\Domain\Orders\Jobs;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;
    public string $type;

    /**
     * Create a new job instance
     */
    public function __construct(Order $order, string $type)
    {
        $this->order = $order;
        $this->type = $type;

        // Set the queue to 'notifications' to avoid blocking order processing
        $this->onQueue('notifications');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            $message = $this->buildMessage();

            // Store notification in history
            OrderNotification::create([
                'order_id' => $this->order->id,
                'customer_email' => $this->order->customer_email,
                'type' => $this->type,
                'status' => $this->order->status->value,
                'total' => $this->order->total_amount,
                'message' => $message,
                'sent_at' => now(),
            ]);

            // Log the notification (in production, send email here)
            Log::info("Order notification sent", [
                'type' => $this->type,
                'order_id' => $this->order->order_id,
                'customer' => $this->order->customer_email,
                'total' => $this->order->total_amount,
                'status' => $this->order->status->value,
            ]);

            // In a real application, send email notification here:
            // Mail::to($this->order->customer_email)->send(new OrderNotificationMail($this->order, $this->type));
        } catch (\Exception $e) {
            Log::error("Failed to send order notification", [
                'order_id' => $this->order->order_id,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build notification message based on type
     */
    private function buildMessage(): string
    {
        return match ($this->type) {
            'completed' => sprintf(
                'Your order #%s has been completed successfully. Total: $%.2f',
                $this->order->order_id,
                $this->order->total_amount
            ),
            'failed' => sprintf(
                'Your order #%s has failed. Total: $%.2f. Please contact support.',
                $this->order->order_id,
                $this->order->total_amount
            ),
            default => 'Order status update',
        };
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Order notification job failed", [
            'order_id' => $this->order->order_id,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
