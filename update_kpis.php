<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\KPIService;
use App\Domain\Orders\Services\LeaderboardService;

echo '=== UPDATING COMPLETED ORDERS ==='.PHP_EOL;

// Update completed orders to have completed_at timestamp
$completedOrders = Order::where('status', OrderStatus::COMPLETED)
    ->whereNull('completed_at')
    ->get();

echo "Found {$completedOrders->count()} completed orders without timestamp".PHP_EOL;

$kpi = app(KPIService::class);
$lb = app(LeaderboardService::class);

foreach ($completedOrders as $order) {
    // Set completed_at based on order_date
    $completedAt = \Carbon\Carbon::parse($order->order_date);
    $order->completed_at = $completedAt;
    $order->save();

    // Manually update KPIs and leaderboard
    $amount = $order->quantity * $order->unit_price;
    $kpi->recordOrder($completedAt, $amount);
    $lb->incrementCustomerSpending($order->customer_email, $amount);

    echo "Updated Order {$order->order_id}: Rs. ".number_format($amount, 2)." - {$order->customer_email}".PHP_EOL;
}

echo PHP_EOL.'=== KPI SUMMARY ==='.PHP_EOL;
$dates = $completedOrders->pluck('order_date')->unique()->sort();
foreach ($dates as $date) {
    $d = \Carbon\Carbon::parse($date);
    $kpis = $kpi->getDailyKPIs($d);
    echo "{$d->toDateString()}: Rs. ".number_format($kpis['revenue'], 2)." ({$kpis['order_count']} orders, avg: Rs. ".number_format($kpis['average_order_value'], 2).')'.PHP_EOL;
}

echo PHP_EOL.'=== TOP 10 CUSTOMERS ==='.PHP_EOL;
$top10 = $lb->getTopCustomers(10);
foreach ($top10 as $customer) {
    echo "#{$customer['rank']} {$customer['email']}: Rs. ".number_format($customer['total_spent'], 2).PHP_EOL;
}

echo PHP_EOL.'Done!'.PHP_EOL;
