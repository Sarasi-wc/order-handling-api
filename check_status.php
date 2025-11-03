<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\KPIService;
use App\Domain\Orders\Services\LeaderboardService;

echo '=== ORDER STATUS SUMMARY ==='.PHP_EOL;
echo 'Total orders: '.Order::count().PHP_EOL;
$statuses = \DB::table('orders')->selectRaw('status, count(*) as count')->groupBy('status')->get();
foreach ($statuses as $s) {
    echo ucfirst($s->status).': '.$s->count.PHP_EOL;
}

echo PHP_EOL.'=== SAMPLE ORDERS ==='.PHP_EOL;
$orders = Order::take(5)->get();
foreach ($orders as $o) {
    echo "Order {$o->order_id}: {$o->customer_name} <{$o->customer_email}> - Status: {$o->status->value}".PHP_EOL;
}

echo PHP_EOL.'=== COMPLETED ORDERS (last 5) ==='.PHP_EOL;
$completed = Order::where('status', 'completed')->latest('completed_at')->take(5)->get();
foreach ($completed as $o) {
    $amount = $o->quantity * $o->unit_price;
    echo "Order {$o->order_id}: Rs. ".number_format($amount, 2)." - {$o->customer_email}".PHP_EOL;
}

echo PHP_EOL.'=== KPI METRICS (Today) ==='.PHP_EOL;
$kpi = app(KPIService::class);
$today = now();
$kpis = $kpi->getDailyKPIs($today);
echo 'Revenue: Rs. '.number_format($kpis['revenue'], 2).PHP_EOL;
echo 'Order Count: '.$kpis['order_count'].PHP_EOL;
echo 'Average Order Value: Rs. '.number_format($kpis['average_order_value'], 2).PHP_EOL;

echo PHP_EOL.'=== TOP 5 CUSTOMERS ==='.PHP_EOL;
$lb = app(LeaderboardService::class);
$top5 = $lb->getTopCustomers(5);
if (count($top5) > 0) {
    foreach ($top5 as $customer) {
        echo "#{$customer['rank']} {$customer['email']}: Rs. ".number_format($customer['total_spent'], 2).PHP_EOL;
    }
} else {
    echo 'No customers in leaderboard yet.'.PHP_EOL;
}

echo PHP_EOL.'=== QUEUE STATUS ==='.PHP_EOL;
echo 'Orders queue: '.\Illuminate\Support\Facades\Queue::size('orders').' jobs'.PHP_EOL;
echo 'KPI queue: '.\Illuminate\Support\Facades\Queue::size('kpi').' jobs'.PHP_EOL;
echo 'Default queue: '.\Illuminate\Support\Facades\Queue::size('default').' jobs'.PHP_EOL;
