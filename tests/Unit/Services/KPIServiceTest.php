<?php

namespace Tests\Unit\Services;

use App\Domain\Orders\Services\KPIService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class KPIServiceTest extends TestCase
{
    use RefreshDatabase;

    private KPIService $kpiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kpiService = new KPIService;

        // Clear Redis before each test
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_records_order_metrics_correctly(): void
    {
        $date = Carbon::parse('2025-01-15');
        $amount = 150.50;

        $this->kpiService->recordOrder($date, $amount);

        $kpis = $this->kpiService->getDailyKPIs($date);

        $this->assertEquals($amount, $kpis['revenue']);
        $this->assertEquals(1, $kpis['order_count']);
        $this->assertEquals($amount, $kpis['average_order_value']);
    }

    public function test_records_multiple_orders_for_same_day(): void
    {
        $date = Carbon::parse('2025-01-15');

        $this->kpiService->recordOrder($date, 100.00);
        $this->kpiService->recordOrder($date, 200.00);
        $this->kpiService->recordOrder($date, 300.00);

        $kpis = $this->kpiService->getDailyKPIs($date);

        $this->assertEquals(600.00, $kpis['revenue']);
        $this->assertEquals(3, $kpis['order_count']);
        $this->assertEquals(200.00, $kpis['average_order_value']);
    }

    public function test_calculates_average_order_value_correctly(): void
    {
        $date = Carbon::parse('2025-01-15');

        $this->kpiService->recordOrder($date, 50.00);
        $this->kpiService->recordOrder($date, 100.00);
        $this->kpiService->recordOrder($date, 150.00);

        $avg = $this->kpiService->getAverageOrderValue($date);

        $this->assertEquals(100.00, $avg);
    }

    public function test_returns_zero_for_day_with_no_orders(): void
    {
        $date = Carbon::parse('2025-01-15');

        $kpis = $this->kpiService->getDailyKPIs($date);

        $this->assertEquals(0, $kpis['revenue']);
        $this->assertEquals(0, $kpis['order_count']);
        $this->assertEquals(0, $kpis['average_order_value']);
    }

    public function test_retrieves_kpi_range_correctly(): void
    {
        $date1 = Carbon::parse('2025-01-15');
        $date2 = Carbon::parse('2025-01-16');
        $date3 = Carbon::parse('2025-01-17');

        $this->kpiService->recordOrder($date1, 100.00);
        $this->kpiService->recordOrder($date2, 200.00);
        $this->kpiService->recordOrder($date3, 300.00);

        $kpis = $this->kpiService->getKPIRange($date1, $date3);

        $this->assertCount(3, $kpis);
        $this->assertEquals(100.00, $kpis[0]['revenue']);
        $this->assertEquals(200.00, $kpis[1]['revenue']);
        $this->assertEquals(300.00, $kpis[2]['revenue']);
    }

    public function test_stores_daily_snapshot(): void
    {
        $date = Carbon::parse('2025-01-15');
        $snapshot = (object) [
            'revenue' => 1500.00,
            'order_count' => 10,
        ];

        $this->kpiService->storeDailySnapshot($date, $snapshot);

        $kpis = $this->kpiService->getDailyKPIs($date);

        $this->assertEquals(1500.00, $kpis['revenue']);
        $this->assertEquals(10, $kpis['order_count']);
        $this->assertEquals(150.00, $kpis['average_order_value']);
    }

    public function test_calculates_total_revenue_for_date_range(): void
    {
        $date1 = Carbon::parse('2025-01-15');
        $date2 = Carbon::parse('2025-01-16');
        $date3 = Carbon::parse('2025-01-17');

        $this->kpiService->recordOrder($date1, 100.00);
        $this->kpiService->recordOrder($date2, 200.00);
        $this->kpiService->recordOrder($date3, 300.00);

        $total = $this->kpiService->getTotalRevenue($date1, $date3);

        $this->assertEquals(600.00, $total);
    }

    public function test_calculates_total_order_count_for_date_range(): void
    {
        $date1 = Carbon::parse('2025-01-15');
        $date2 = Carbon::parse('2025-01-16');

        $this->kpiService->recordOrder($date1, 100.00);
        $this->kpiService->recordOrder($date1, 150.00);
        $this->kpiService->recordOrder($date2, 200.00);

        $count = $this->kpiService->getTotalOrderCount($date1, $date2);

        $this->assertEquals(3, $count);
    }

    public function test_handles_decimal_amounts_correctly(): void
    {
        $date = Carbon::parse('2025-01-15');

        $this->kpiService->recordOrder($date, 99.99);
        $this->kpiService->recordOrder($date, 150.50);

        $kpis = $this->kpiService->getDailyKPIs($date);

        $this->assertEquals(250.49, $kpis['revenue']);
        $this->assertEquals(125.25, round($kpis['average_order_value'], 2));
    }
}
