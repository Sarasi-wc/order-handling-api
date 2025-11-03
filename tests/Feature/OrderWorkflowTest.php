<?php

namespace Tests\Feature;

use App\Domain\Orders\Actions\FinalizeOrder;
use App\Domain\Orders\Actions\ReserveStock;
use App\Domain\Orders\Actions\RollbackOrder;
use App\Domain\Orders\Actions\SimulatePayment;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_progress_through_workflow(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        (new ReserveStock)->execute($order);

        // Payment may fail randomly (30% chance), therefore handle both cases
        try {
            (new SimulatePayment)->execute($order);
            (new FinalizeOrder)->execute($order);

            $this->assertEquals(OrderStatus::COMPLETED, $order->refresh()->status);
        } catch (\Exception $e) {
            // Payment failed, verify order is in correct state for rollback
            $this->assertEquals(OrderStatus::RESERVED, $order->refresh()->status);
            $this->assertStringContainsString('Payment failed', $e->getMessage());
        }
    }

    public function test_order_rolls_back_on_failure(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        (new RollbackOrder)->execute($order);

        $this->assertEquals(OrderStatus::FAILED, $order->refresh()->status);
    }
}
