<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Events\OrderRefunded;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Refund;
use App\Domain\Orders\Services\KPIService;
use App\Domain\Orders\Services\LeaderboardService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProcessRefund
{
    public function __construct(
        private KPIService $kpiService,
        private LeaderboardService $leaderboardService
    ) {}

    /**
     * Process a refund for an order
     *
     * @throws DomainException
     */
    public function execute(Order $order, string $refundReference, string $type, float $amount, ?string $reason = null): Refund
    {
        // Validate order is completed
        if ($order->status->value !== 'completed') {
            throw new DomainException("Cannot refund order #{$order->order_id}. Order must be completed.");
        }

        // Check for idempotency - if refund with this reference already exists, return it
        $existingRefund = Refund::where('refund_reference', $refundReference)->first();
        if ($existingRefund) {
            return $existingRefund;
        }

        // Validate refund amount
        $orderTotal = $order->total_amount;
        if ($amount <= 0 || $amount > $orderTotal) {
            throw new DomainException("Invalid refund amount: $amount. Must be between 0 and {$orderTotal}.");
        }

        // Validate refund type
        if (!in_array($type, ['full', 'partial'])) {
            throw new DomainException("Invalid refund type: $type. Must be 'full' or 'partial'.");
        }

        // If full refund, amount must equal order total
        if ($type === 'full' && abs($amount - $orderTotal) > 0.01) {
            throw new DomainException("Full refund amount must equal order total: {$orderTotal}.");
        }

        // Process refund in a transaction
        return DB::transaction(function () use ($order, $refundReference, $type, $amount, $reason) {
            // Create refund record
            $refund = Refund::create([
                'order_id' => $order->id,
                'refund_reference' => $refundReference,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            // Update KPIs - subtract refunded amount from revenue
            $this->kpiService->recordRefund($order->completed_at ?? now(), $amount);

            // Update leaderboard - subtract refunded amount from customer spending
            $this->leaderboardService->decrementCustomerSpending($order->customer_email, $amount);

            // Dispatch event
            event(new OrderRefunded($refund));

            return $refund;
        });
    }
}
