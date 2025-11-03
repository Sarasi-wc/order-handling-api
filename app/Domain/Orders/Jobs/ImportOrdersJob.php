<?php

namespace App\Domain\Orders\Jobs;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ImportOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $orders;

    /**
     * Create a new job instance.
     */
    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            foreach ($this->orders as $data) {
                // Convert CSV string to enum
                $status = OrderStatus::tryFrom($data['status'] ?? 'pending') ?? OrderStatus::PENDING;

                $order = Order::updateOrCreate(
                    ['order_id' => $data['order_id']],
                    [
                        'customer_name' => $data['customer_name'],
                        'customer_email' => $data['customer_email'],
                        'product_sku' => $data['product_sku'],
                        'product_name' => $data['product_name'],
                        'quantity' => (int) $data['quantity'],
                        'unit_price' => (float) $data['unit_price'],
                        'payment_method' => $data['payment_method'],
                        'order_date' => $data['order_date'],
                        'status' => $status,
                    ]
                );

                ProcessOrderWorkflowJob::dispatch($order);
            }
        });
    }
}
