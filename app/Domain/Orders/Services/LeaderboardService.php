<?php

namespace App\Domain\Orders\Services;

use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    private const LEADERBOARD_KEY = 'leaderboard:customers';

    /**
     * Increment customer's total spending
     */
    public function incrementCustomerSpending(string $email, float $amount): void
    {
        Redis::zincrby(self::LEADERBOARD_KEY, $amount, $email);
    }

    /**
     * Decrement customer's total spending (for refunds)
     */
    public function decrementCustomerSpending(string $email, float $amount): void
    {
        Redis::zincrby(self::LEADERBOARD_KEY, -$amount, $email);
    }

    /**
     * Get top N customers by spending
     */
    public function getTopCustomers(int $limit = 10): array
    {
        // ZREVRANGE returns highest scores first
        $customers = Redis::zrevrange(
            self::LEADERBOARD_KEY,
            0,
            $limit - 1,
            ['withscores' => true]
        );

        return $this->formatLeaderboard($customers);
    }

    /**
     * Get customer's rank (1-indexed, 1 is the highest spender)
     */
    public function getCustomerRank(string $email): ?int
    {
        $rank = Redis::zrevrank(self::LEADERBOARD_KEY, $email);

        // ZREVRANK returns 0-indexed position or false if not found
        // Redis returns false (not null) for non-existent members
        if ($rank === false || $rank === null) {
            return null;
        }

        return (int) $rank + 1;
    }

    /**
     * Get customer's total spending
     */
    public function getCustomerTotal(string $email): float
    {
        $score = Redis::zscore(self::LEADERBOARD_KEY, $email);

        return $score !== null ? (float) $score : 0.0;
    }

    /**
     * Get customer's full leaderboard info
     */
    public function getCustomerInfo(string $email): array
    {
        return [
            'email' => $email,
            'total_spent' => $this->getCustomerTotal($email),
            'rank' => $this->getCustomerRank($email),
        ];
    }

    /**
     * Get total number of customers in leaderboard
     */
    public function getTotalCustomers(): int
    {
        return (int) Redis::zcard(self::LEADERBOARD_KEY);
    }

    /**
     * Get leaderboard range (for pagination)
     */
    public function getLeaderboardRange(int $start, int $end): array
    {
        $customers = Redis::zrevrange(
            self::LEADERBOARD_KEY,
            $start,
            $end,
            ['withscores' => true]
        );

        return $this->formatLeaderboard($customers, $start);
    }

    /**
     * Reset the leaderboard (use with caution)
     */
    public function reset(): bool
    {
        return (bool) Redis::del(self::LEADERBOARD_KEY);
    }

    /**
     * Format Redis sorted set response into readable array
     */
    private function formatLeaderboard(array $customers, int $offset = 0): array
    {
        $leaderboard = [];
        $rank = $offset + 1;

        // Redis with WITHSCORES returns associative array: [email1 => score1, email2 => score2, ...]
        foreach ($customers as $email => $score) {
            $leaderboard[] = [
                'rank' => $rank++,
                'email' => $email,
                'total_spent' => (float) $score,
            ];
        }

        return $leaderboard;
    }
}
