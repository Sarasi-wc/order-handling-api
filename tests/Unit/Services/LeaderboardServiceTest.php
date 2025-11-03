<?php

namespace Tests\Unit\Services;

use App\Domain\Orders\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaderboardService $leaderboardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaderboardService = new LeaderboardService;

        // Clear Redis before each test
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_increments_customer_spending(): void
    {
        $this->leaderboardService->incrementCustomerSpending('john@example.com', 100.00);

        $total = $this->leaderboardService->getCustomerTotal('john@example.com');

        $this->assertEquals(100.00, $total);
    }

    public function test_increments_customer_spending_multiple_times(): void
    {
        $this->leaderboardService->incrementCustomerSpending('jane@example.com', 50.00);
        $this->leaderboardService->incrementCustomerSpending('jane@example.com', 75.00);
        $this->leaderboardService->incrementCustomerSpending('jane@example.com', 125.00);

        $total = $this->leaderboardService->getCustomerTotal('jane@example.com');

        $this->assertEquals(250.00, $total);
    }

    public function test_returns_top_customers_in_descending_order(): void
    {
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 100.00);
        $this->leaderboardService->incrementCustomerSpending('bob@example.com', 300.00);
        $this->leaderboardService->incrementCustomerSpending('charlie@example.com', 200.00);

        $topCustomers = $this->leaderboardService->getTopCustomers(3);

        $this->assertCount(3, $topCustomers);
        $this->assertEquals('bob@example.com', $topCustomers[0]['email']);
        $this->assertEquals(300.00, $topCustomers[0]['total_spent']);
        $this->assertEquals(1, $topCustomers[0]['rank']);

        $this->assertEquals('charlie@example.com', $topCustomers[1]['email']);
        $this->assertEquals(200.00, $topCustomers[1]['total_spent']);
        $this->assertEquals(2, $topCustomers[1]['rank']);

        $this->assertEquals('alice@example.com', $topCustomers[2]['email']);
        $this->assertEquals(100.00, $topCustomers[2]['total_spent']);
        $this->assertEquals(3, $topCustomers[2]['rank']);
    }

    public function test_limits_top_customers_correctly(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->leaderboardService->incrementCustomerSpending("customer{$i}@example.com", $i * 10);
        }

        $topCustomers = $this->leaderboardService->getTopCustomers(5);

        $this->assertCount(5, $topCustomers);
        $this->assertEquals('customer20@example.com', $topCustomers[0]['email']);
    }

    public function test_calculates_customer_rank_correctly(): void
    {
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 100.00);
        $this->leaderboardService->incrementCustomerSpending('bob@example.com', 300.00);
        $this->leaderboardService->incrementCustomerSpending('charlie@example.com', 200.00);

        $aliceRank = $this->leaderboardService->getCustomerRank('alice@example.com');
        $bobRank = $this->leaderboardService->getCustomerRank('bob@example.com');
        $charlieRank = $this->leaderboardService->getCustomerRank('charlie@example.com');

        $this->assertEquals(3, $aliceRank);
        $this->assertEquals(1, $bobRank);
        $this->assertEquals(2, $charlieRank);
    }

    public function test_returns_null_rank_for_non_existent_customer(): void
    {
        $rank = $this->leaderboardService->getCustomerRank('nonexistent@example.com');

        $this->assertNull($rank);
    }

    public function test_returns_zero_total_for_non_existent_customer(): void
    {
        $total = $this->leaderboardService->getCustomerTotal('nonexistent@example.com');

        $this->assertEquals(0.0, $total);
    }

    public function test_gets_customer_info(): void
    {
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 100.00);
        $this->leaderboardService->incrementCustomerSpending('bob@example.com', 200.00);

        $info = $this->leaderboardService->getCustomerInfo('alice@example.com');

        $this->assertEquals('alice@example.com', $info['email']);
        $this->assertEquals(100.00, $info['total_spent']);
        $this->assertEquals(2, $info['rank']);
    }

    public function test_gets_total_customers_count(): void
    {
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 100.00);
        $this->leaderboardService->incrementCustomerSpending('bob@example.com', 200.00);
        $this->leaderboardService->incrementCustomerSpending('charlie@example.com', 300.00);

        $count = $this->leaderboardService->getTotalCustomers();

        $this->assertEquals(3, $count);
    }

    public function test_gets_leaderboard_range_for_pagination(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $this->leaderboardService->incrementCustomerSpending("customer{$i}@example.com", $i * 10);
        }

        // Get customers ranked 11-20 (indices 10-19)
        $range = $this->leaderboardService->getLeaderboardRange(10, 19);

        $this->assertCount(10, $range);
        $this->assertEquals(11, $range[0]['rank']);
        $this->assertEquals('customer40@example.com', $range[0]['email']);
        $this->assertEquals(20, $range[9]['rank']);
        $this->assertEquals('customer31@example.com', $range[9]['email']);
    }

    public function test_resets_leaderboard(): void
    {
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 100.00);
        $this->leaderboardService->incrementCustomerSpending('bob@example.com', 200.00);

        $result = $this->leaderboardService->reset();

        $this->assertTrue($result);
        $this->assertEquals(0, $this->leaderboardService->getTotalCustomers());
    }

    public function test_handles_decimal_amounts(): void
    {
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 99.99);
        $this->leaderboardService->incrementCustomerSpending('alice@example.com', 150.50);

        $total = $this->leaderboardService->getCustomerTotal('alice@example.com');

        $this->assertEquals(250.49, $total);
    }

    public function test_handles_large_numbers_of_customers(): void
    {
        for ($i = 1; $i <= 1000; $i++) {
            $this->leaderboardService->incrementCustomerSpending("customer{$i}@example.com", rand(10, 1000));
        }

        $count = $this->leaderboardService->getTotalCustomers();
        $topCustomers = $this->leaderboardService->getTopCustomers(10);

        $this->assertEquals(1000, $count);
        $this->assertCount(10, $topCustomers);

        // Verify descending order
        for ($i = 0; $i < 9; $i++) {
            $this->assertGreaterThanOrEqual(
                $topCustomers[$i + 1]['total_spent'],
                $topCustomers[$i]['total_spent']
            );
        }
    }
}
