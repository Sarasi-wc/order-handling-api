<?php

namespace App\Domain\Orders\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class KPIService
{
    private const PREFIX = 'kpi:daily:';

    /**
     * Record a completed order for KPI tracking
     */
    public function recordOrder(Carbon $date, float $amount): void
    {
        $key = $this->getKeyForDate($date);

        // Increment revenue and count atomically
        Redis::hincrbyfloat($key, 'revenue', $amount);
        Redis::hincrby($key, 'count', 1);

        // Set expiry to 90 days
        Redis::expire($key, 60 * 60 * 24 * 90);
    }

    /**
     * Get KPIs for a specific date
     */
    public function getDailyKPIs(Carbon $date): array
    {
        $key = $this->getKeyForDate($date);
        $data = Redis::hgetall($key);

        $revenue = (float) ($data['revenue'] ?? 0);
        $count = (int) ($data['count'] ?? 0);

        return [
            'date' => $date->toDateString(),
            'revenue' => $revenue,
            'order_count' => $count,
            'average_order_value' => $count > 0 ? round($revenue / $count, 2) : 0,
        ];
    }

    /**
     * Get KPIs for a date range
     */
    public function getKPIRange(Carbon $from, Carbon $to): array
    {
        $kpis = [];
        $currentDate = $from->copy();

        while ($currentDate->lte($to)) {
            $kpis[] = $this->getDailyKPIs($currentDate);
            $currentDate->addDay();
        }

        return $kpis;
    }

    /**
     * Get average order value for a specific date
     */
    public function getAverageOrderValue(Carbon $date): float
    {
        $kpis = $this->getDailyKPIs($date);

        return $kpis['average_order_value'];
    }

    /**
     * Store a pre-computed daily snapshot (for batch jobs)
     */
    public function storeDailySnapshot(Carbon $date, object $kpis): void
    {
        $key = $this->getKeyForDate($date);

        Redis::hmset($key, [
            'revenue' => $kpis->revenue ?? 0,
            'count' => $kpis->order_count ?? 0,
        ]);

        // Set expiry to 90 days
        Redis::expire($key, 60 * 60 * 24 * 90);
    }

    /**
     * Get total revenue for a date range
     */
    public function getTotalRevenue(Carbon $from, Carbon $to): float
    {
        $kpis = $this->getKPIRange($from, $to);

        return array_sum(array_column($kpis, 'revenue'));
    }

    /**
     * Get total order count for a date range
     */
    public function getTotalOrderCount(Carbon $from, Carbon $to): int
    {
        $kpis = $this->getKPIRange($from, $to);

        return array_sum(array_column($kpis, 'order_count'));
    }

    /**
     * Record a refund for KPI tracking (decrement revenue)
     */
    public function recordRefund(Carbon $date, float $amount): void
    {
        $key = $this->getKeyForDate($date);

        // Decrement revenue atomically (negative increment)
        Redis::hincrbyfloat($key, 'revenue', -$amount);

        // Set expiry to 90 days
        Redis::expire($key, 60 * 60 * 24 * 90);
    }

    /**
     * Generate Redis key for a specific date
     */
    private function getKeyForDate(Carbon $date): string
    {
        return self::PREFIX.$date->toDateString();
    }
}
