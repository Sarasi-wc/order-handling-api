<?php

namespace App\Domain\Orders\Console;

use App\Domain\Orders\Actions\FinalizeOrder;
use App\Domain\Orders\Actions\ReserveStock;
use App\Domain\Orders\Actions\RollbackOrder;
use App\Domain\Orders\Actions\SimulatePayment;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Console\Command;

class TestOrderWorkflowCommand extends Command
{
    protected $signature = 'orders:order-workflow';

    protected $description = 'Test the order workflow actions manually';

    public function handle(): void
    {
        Order::where('order_id', 10000)->delete(); // remove previous test order

        $order = Order::create([
            'order_id' => 10000,
            'customer_name' => 'CLI Test',
            'customer_email' => 'cli@example.com',
            'product_sku' => 'SKU10000',
            'product_name' => 'Workflow Tester',
            'quantity' => 1,
            'unit_price' => 5000,
            'payment_method' => 'card',
            'order_date' => now(),
            'status' => OrderStatus::PENDING,
        ]);

        $this->info("Initial status: {$order->status->value}");

        try {
            (new ReserveStock)->execute($order);
            (new SimulatePayment)->execute($order);
            (new FinalizeOrder)->execute($order);
        } catch (\Throwable $e) {
            (new RollbackOrder)->execute($order);
            $this->error("Workflow failed: {$e->getMessage()}");
        }

        $order->refresh();
        $this->info("Final status: {$order->status->value}");
    }
}
