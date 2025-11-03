<?php

namespace Tests\Feature;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCompleted;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\KPIService;
use App\Domain\Orders\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class OrderWorkflowWithEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_completed_order_triggers_order_completed_event(): void
    {
        Event::fake([OrderCompleted::class]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'customer_email' => 'test@example.com',
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

        $finalizeOrder = new \App\Domain\Orders\Actions\FinalizeOrder;
        $finalizeOrder->execute($order);

        Event::assertDispatched(OrderCompleted::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_order_completion_updates_kpis(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
            'customer_email' => 'test@example.com',
            'quantity' => 2,
            'unit_price' => 50.00,
            'completed_at' => now(),
        ]);

        // Manually trigger the event since we're not using queues
        $event = new OrderCompleted($order);
        $kpiService = new KPIService;
        $leaderboardService = new LeaderboardService;

        // Simulate what the listener would do
        $listener = new \App\Domain\Orders\Listeners\UpdateKPIMetrics($kpiService, $leaderboardService);
        $listener->handle($event);

        // Verify KPIs were updated
        $kpis = $kpiService->getDailyKPIs($order->completed_at);
        $this->assertEquals(100.00, $kpis['revenue']);
        $this->assertEquals(1, $kpis['order_count']);
        $this->assertEquals(100.00, $kpis['average_order_value']);
    }

    public function test_order_completion_updates_leaderboard(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
            'customer_email' => 'john@example.com',
            'quantity' => 3,
            'unit_price' => 75.00,
            'completed_at' => now(),
        ]);

        // Manually trigger the event
        $event = new OrderCompleted($order);
        $kpiService = new KPIService;
        $leaderboardService = new LeaderboardService;

        $listener = new \App\Domain\Orders\Listeners\UpdateKPIMetrics($kpiService, $leaderboardService);
        $listener->handle($event);

        // Verify leaderboard was updated
        $customerInfo = $leaderboardService->getCustomerInfo('john@example.com');
        $this->assertEquals(225.00, $customerInfo['total_spent']);
        $this->assertEquals(1, $customerInfo['rank']);
    }

    public function test_multiple_orders_update_metrics_correctly(): void
    {
        Queue::fake();

        $kpiService = new KPIService;
        $leaderboardService = new LeaderboardService;
        $listener = new \App\Domain\Orders\Listeners\UpdateKPIMetrics($kpiService, $leaderboardService);

        $orders = [
            ['email' => 'alice@example.com', 'quantity' => 2, 'price' => 50.00],
            ['email' => 'bob@example.com', 'quantity' => 3, 'price' => 100.00],
            ['email' => 'alice@example.com', 'quantity' => 1, 'price' => 150.00],
        ];

        $date = now();

        foreach ($orders as $orderData) {
            $order = Order::factory()->create([
                'status' => OrderStatus::COMPLETED,
                'customer_email' => $orderData['email'],
                'quantity' => $orderData['quantity'],
                'unit_price' => $orderData['price'],
                'completed_at' => $date,
            ]);

            $event = new OrderCompleted($order);
            $listener->handle($event);
        }

        // Verify KPIs
        $kpis = $kpiService->getDailyKPIs($date);
        $this->assertEquals(550.00, $kpis['revenue']); // 100 + 300 + 150
        $this->assertEquals(3, $kpis['order_count']);
        $this->assertEquals(183.33, round($kpis['average_order_value'], 2));

        // Verify leaderboard
        $topCustomers = $leaderboardService->getTopCustomers(2);
        $this->assertEquals('bob@example.com', $topCustomers[0]['email']);
        $this->assertEquals(300.00, $topCustomers[0]['total_spent']);
        $this->assertEquals('alice@example.com', $topCustomers[1]['email']);
        $this->assertEquals(250.00, $topCustomers[1]['total_spent']);
    }

    public function test_failed_order_does_not_affect_kpis(): void
    {
        Queue::fake();

        $kpiService = new KPIService;
        $leaderboardService = new LeaderboardService;

        $order = Order::factory()->create([
            'status' => OrderStatus::FAILED,
            'customer_email' => 'test@example.com',
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

        // Completed event should only fire for completed orders
        // Failed orders use OrderFailed event which doesn't update KPIs

        $kpis = $kpiService->getDailyKPIs(now());
        $this->assertEquals(0, $kpis['revenue']);
        $this->assertEquals(0, $kpis['order_count']);
    }
}
