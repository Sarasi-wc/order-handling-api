<?php

namespace Tests\Feature;

use App\Domain\Orders\Events\OrderCompleted;
use App\Domain\Orders\Events\OrderFailed;
use App\Domain\Orders\Jobs\SendOrderNotificationJob;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_job_is_dispatched_when_order_completed_event_fires(): void
    {
        Queue::fake();
        Event::fake([SendOrderNotificationJob::class]);

        $order = Order::factory()->create(['status' => 'completed']);

        // Manually dispatch the listener
        $listener = new \App\Domain\Orders\Listeners\SendOrderCompletedNotification();
        $listener->handle(new OrderCompleted($order));

        Queue::assertPushed(SendOrderNotificationJob::class, function ($job) use ($order) {
            return $job->order->id === $order->id && $job->type === 'completed';
        });
    }

    public function test_notification_job_is_dispatched_when_order_failed_event_fires(): void
    {
        Queue::fake();
        Event::fake([SendOrderNotificationJob::class]);

        $order = Order::factory()->create(['status' => 'failed']);

        // Manually dispatch the listener
        $listener = new \App\Domain\Orders\Listeners\SendOrderFailedNotification();
        $listener->handle(new OrderFailed($order));

        Queue::assertPushed(SendOrderNotificationJob::class, function ($job) use ($order) {
            return $job->order->id === $order->id && $job->type === 'failed';
        });
    }

    public function test_completed_notification_is_stored_in_database(): void
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'customer_email' => 'test@example.com',
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

        $job = new SendOrderNotificationJob($order, 'completed');
        $job->handle();

        $this->assertDatabaseHas('order_notifications', [
            'order_id' => $order->id,
            'customer_email' => 'test@example.com',
            'type' => 'completed',
            'status' => 'completed',
            'total' => 100.00,
        ]);

        $notification = OrderNotification::where('order_id', $order->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('completed successfully', $notification->message);
    }

    public function test_failed_notification_is_stored_in_database(): void
    {
        $order = Order::factory()->create([
            'status' => 'failed',
            'customer_email' => 'failed@example.com',
            'quantity' => 1,
            'unit_price' => 75.00,
        ]);

        $job = new SendOrderNotificationJob($order, 'failed');
        $job->handle();

        $this->assertDatabaseHas('order_notifications', [
            'order_id' => $order->id,
            'customer_email' => 'failed@example.com',
            'type' => 'failed',
            'status' => 'failed',
            'total' => 75.00,
        ]);

        $notification = OrderNotification::where('order_id', $order->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('has failed', $notification->message);
    }

    public function test_notification_includes_all_required_fields(): void
    {
        $order = Order::factory()->create([
            'order_id' => 'TEST-123',
            'customer_email' => 'customer@example.com',
            'status' => 'completed',
            'quantity' => 3,
            'unit_price' => 100.00,
        ]);

        $job = new SendOrderNotificationJob($order, 'completed');
        $job->handle();

        $notification = OrderNotification::where('order_id', $order->id)->first();

        $this->assertNotNull($notification->order_id);
        $this->assertEquals('customer@example.com', $notification->customer_email);
        $this->assertEquals('completed', $notification->type);
        $this->assertEquals('completed', $notification->status);
        $this->assertEquals(300.00, $notification->total);
        $this->assertNotNull($notification->message);
        $this->assertNotNull($notification->sent_at);
    }

    public function test_multiple_notifications_can_be_sent_for_different_orders(): void
    {
        $order1 = Order::factory()->create(['status' => 'completed']);
        $order2 = Order::factory()->create(['status' => 'failed']);

        $job1 = new SendOrderNotificationJob($order1, 'completed');
        $job1->handle();

        $job2 = new SendOrderNotificationJob($order2, 'failed');
        $job2->handle();

        $this->assertCount(1, OrderNotification::where('order_id', $order1->id)->get());
        $this->assertCount(1, OrderNotification::where('order_id', $order2->id)->get());
        $this->assertCount(2, OrderNotification::all());
    }

    public function test_notification_job_uses_notifications_queue(): void
    {
        $order = Order::factory()->create(['status' => 'completed']);
        $job = new SendOrderNotificationJob($order, 'completed');

        $this->assertEquals('notifications', $job->queue);
    }
}
