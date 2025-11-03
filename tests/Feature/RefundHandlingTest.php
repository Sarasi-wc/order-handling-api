<?php

namespace Tests\Feature;

use App\Domain\Orders\Actions\ProcessRefund;
use App\Domain\Orders\Events\OrderRefunded;
use App\Domain\Orders\Jobs\ProcessRefundJob;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Refund;
use App\Domain\Orders\Services\KPIService;
use App\Domain\Orders\Services\LeaderboardService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RefundHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_full_refund_can_be_processed_for_completed_order(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => 'completed',
            'customer_email' => 'customer@example.com',
            'quantity' => 2,
            'unit_price' => 100.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);
        $refund = $processRefund->execute($order, 'REF-123', 'full', 200.00, 'Customer request');

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'refund_reference' => 'REF-123',
            'type' => 'full',
            'amount' => 200.00,
            'status' => 'processed',
            'reason' => 'Customer request',
        ]);

        Event::assertDispatched(OrderRefunded::class, function ($event) use ($refund) {
            return $event->refund->id === $refund->id;
        });
    }

    public function test_partial_refund_can_be_processed(): void
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 4,
            'unit_price' => 50.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);
        $refund = $processRefund->execute($order, 'REF-PARTIAL-456', 'partial', 100.00, 'Damaged item');

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'refund_reference' => 'REF-PARTIAL-456',
            'type' => 'partial',
            'amount' => 100.00,
            'status' => 'processed',
        ]);
    }

    public function test_refund_is_idempotent_same_reference_returns_existing_refund(): void
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 1,
            'unit_price' => 100.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);

        // Process first time
        $refund1 = $processRefund->execute($order, 'REF-IDEMPOTENT', 'full', 100.00);

        // Process second time with same reference
        $refund2 = $processRefund->execute($order, 'REF-IDEMPOTENT', 'full', 100.00);

        $this->assertEquals($refund1->id, $refund2->id);
        $this->assertCount(1, Refund::where('refund_reference', 'REF-IDEMPOTENT')->get());
    }

    public function test_refund_cannot_be_processed_for_non_completed_order(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order must be completed');

        $order = Order::factory()->create(['status' => 'pending']);
        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-999', 'full', 100.00);
    }

    public function test_refund_amount_cannot_exceed_order_total(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid refund amount');

        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 1,
            'unit_price' => 50.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-OVER', 'full', 100.00);
    }

    public function test_full_refund_must_equal_order_total(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Full refund amount must equal order total');

        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 2,
            'unit_price' => 50.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-MISMATCH', 'full', 50.00);
    }

    public function test_refund_updates_kpi_metrics(): void
    {
        $kpiService = app(KPIService::class);
        $date = now();

        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 2,
            'unit_price' => 100.00,
            'completed_at' => $date,
        ]);

        // Record initial order
        $kpiService->recordOrder($date, 200.00);

        // Process refund
        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-KPI', 'full', 200.00);

        // Check KPI was updated
        $kpis = $kpiService->getDailyKPIs($date);
        $this->assertEquals(0.00, $kpis['revenue']);
    }

    public function test_refund_updates_leaderboard(): void
    {
        $leaderboardService = app(LeaderboardService::class);
        $customerEmail = 'leaderboard@example.com';

        $order = Order::factory()->create([
            'status' => 'completed',
            'customer_email' => $customerEmail,
            'quantity' => 3,
            'unit_price' => 100.00,
            'completed_at' => now(),
        ]);

        // Record initial spending
        $leaderboardService->incrementCustomerSpending($customerEmail, 300.00);

        // Process partial refund
        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-LEADER', 'partial', 100.00);

        // Check leaderboard was updated
        $customerTotal = $leaderboardService->getCustomerTotal($customerEmail);
        $this->assertEquals(200.00, $customerTotal);
    }

    public function test_refund_job_is_queued_on_refunds_queue(): void
    {
        $order = Order::factory()->create(['status' => 'completed']);
        $job = new ProcessRefundJob($order, 'REF-QUEUE', 'full', 100.00);

        $this->assertEquals('refunds', $job->queue);
    }

    public function test_refund_job_processes_successfully(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 1,
            'unit_price' => 150.00,
            'completed_at' => now(),
        ]);

        ProcessRefundJob::dispatch($order, 'REF-JOB-TEST', 'full', 150.00, 'Test reason');

        Queue::assertPushed(ProcessRefundJob::class, function ($job) use ($order) {
            return $job->order->id === $order->id;
        });
    }

    public function test_multiple_partial_refunds_can_be_processed_for_same_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 5,
            'unit_price' => 100.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);

        $refund1 = $processRefund->execute($order, 'REF-MULTI-1', 'partial', 100.00);
        $refund2 = $processRefund->execute($order, 'REF-MULTI-2', 'partial', 150.00);

        $this->assertCount(2, Refund::where('order_id', $order->id)->get());
        $this->assertEquals(100.00, $refund1->amount);
        $this->assertEquals(150.00, $refund2->amount);
    }

    public function test_refund_model_helper_methods(): void
    {
        $fullRefund = Refund::factory()->create(['type' => 'full', 'status' => 'processed']);
        $partialRefund = Refund::factory()->create(['type' => 'partial', 'status' => 'pending']);

        $this->assertTrue($fullRefund->isFullRefund());
        $this->assertFalse($fullRefund->isPartialRefund());
        $this->assertTrue($fullRefund->isProcessed());

        $this->assertTrue($partialRefund->isPartialRefund());
        $this->assertFalse($partialRefund->isFullRefund());
        $this->assertFalse($partialRefund->isProcessed());
    }

    public function test_invalid_refund_type_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid refund type');

        $order = Order::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-BAD-TYPE', 'invalid', 100.00);
    }

    public function test_refund_amount_must_be_positive(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid refund amount');

        $order = Order::factory()->create([
            'status' => 'completed',
            'quantity' => 1,
            'unit_price' => 100.00,
            'completed_at' => now(),
        ]);

        $processRefund = app(ProcessRefund::class);
        $processRefund->execute($order, 'REF-NEGATIVE', 'partial', -50.00);
    }
}
