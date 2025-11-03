# Testing Guide

This guide covers all testing approaches for the Order Management System.

---

## Table of Contents
1. [Automated Tests](#1-automated-tests)
2. [Manual Workflow Testing](#2-manual-workflow-testing)
3. [Redis KPI Testing](#3-redis-kpi-testing)
4. [Queue & Horizon Testing](#4-queue--horizon-testing)
5. [CSV Import Testing](#5-csv-import-testing)
6. [Event System Testing](#6-event-system-testing)
7. [Daily Scheduler Testing](#7-daily-scheduler-testing)

---

## 1. Automated Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites

**Unit Tests (KPI & Leaderboard Services):**
```bash
php artisan test tests/Unit/Services/
```

**Feature Tests (Workflow & Events):**
```bash
php artisan test tests/Feature/
```

**Specific Test File:**
```bash
php artisan test tests/Unit/Services/KPIServiceTest.php
php artisan test tests/Unit/Services/LeaderboardServiceTest.php
php artisan test tests/Feature/OrderWorkflowTest.php
php artisan test tests/Feature/OrderWorkflowWithEventsTest.php
```

**Run with Coverage:**
```bash
php artisan test --coverage
```

**Expected Results:**
- All tests should pass ✓
- 29+ test cases total

---

## 2. Manual Workflow Testing

### Step 1: Test the Workflow Command
```bash
php artisan orders:order-workflow
```

**Expected Output:**
```
Initial status: pending
Payment simulated successfully for Order #10000  (or failed message)
Final status: completed (or failed)
```

**Verify in Database:**
```bash
php artisan tinker
```

```php
use App\Domain\Orders\Models\Order;

// Check the test order
$order = Order::where('order_id', 10000)->first();

echo "Status: " . $order->status->value . "\n";
echo "Reserved Stock: " . ($order->reserved_stock ? 'Yes' : 'No') . "\n";
echo "Payment Ref: " . $order->payment_reference . "\n";
echo "Completed At: " . $order->completed_at . "\n";
```

### Step 2: Test Individual Actions

**Create a test order:**
```php
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Enums\OrderStatus;

$order = Order::create([
    'order_id' => 'TEST-001',
    'customer_name' => 'Test User',
    'customer_email' => 'test@example.com',
    'product_sku' => 'SKU-TEST',
    'product_name' => 'Test Product',
    'quantity' => 2,
    'unit_price' => 50.00,
    'payment_method' => 'card',
    'order_date' => now(),
    'status' => OrderStatus::PENDING,
]);
```

**Test each action:**
```php
use App\Domain\Orders\Actions\{ReserveStock, SimulatePayment, FinalizeOrder};

// 1. Reserve Stock
(new ReserveStock)->execute($order);
echo "Status: " . $order->fresh()->status->value . "\n"; // Should be: reserved

// 2. Simulate Payment (may fail randomly 30% of time)
try {
    (new SimulatePayment)->execute($order);
    echo "Status: " . $order->fresh()->status->value . "\n"; // Should be: paid
} catch (\Exception $e) {
    echo "Payment failed: " . $e->getMessage() . "\n";
}

// 3. Finalize Order
(new FinalizeOrder)->execute($order);
echo "Status: " . $order->fresh()->status->value . "\n"; // Should be: completed
```

---

## 3. Redis KPI Testing

### Test KPI Service

**Using Artisan Tinker:**
```bash
php artisan tinker
```

```php
use App\Domain\Orders\Services\KPIService;
use Carbon\Carbon;

$kpiService = app(KPIService::class);
$today = Carbon::today();

// Record some test orders
$kpiService->recordOrder($today, 100.00);
$kpiService->recordOrder($today, 200.00);
$kpiService->recordOrder($today, 150.00);

// Get daily KPIs
$kpis = $kpiService->getDailyKPIs($today);
print_r($kpis);
// Expected output:
// [
//     'date' => '2025-01-15',
//     'revenue' => 450.0,
//     'order_count' => 3,
//     'average_order_value' => 150.0
// ]

// Get KPI range
$from = $today->copy()->subDays(7);
$range = $kpiService->getKPIRange($from, $today);
print_r($range);
```

**Direct Redis Inspection:**
```bash
redis-cli

# Check if KPI keys exist
KEYS kpi:daily:*

# Get specific day's data
HGETALL kpi:daily:2025-01-15

# Expected output:
# 1) "revenue"
# 2) "450"
# 3) "count"
# 4) "3"
```

### Test Leaderboard Service

```php
use App\Domain\Orders\Services\LeaderboardService;

$leaderboard = app(LeaderboardService::class);

// Add customer spending
$leaderboard->incrementCustomerSpending('alice@example.com', 500.00);
$leaderboard->incrementCustomerSpending('bob@example.com', 750.00);
$leaderboard->incrementCustomerSpending('charlie@example.com', 300.00);
$leaderboard->incrementCustomerSpending('alice@example.com', 200.00); // Additional purchase

// Get top customers
$top10 = $leaderboard->getTopCustomers(10);
print_r($top10);
// Expected order: bob (750), alice (700), charlie (300)

// Get specific customer info
$aliceInfo = $leaderboard->getCustomerInfo('alice@example.com');
print_r($aliceInfo);
// Expected:
// [
//     'email' => 'alice@example.com',
//     'total_spent' => 700.0,
//     'rank' => 2
// ]

// Get total customers
echo $leaderboard->getTotalCustomers(); // Should be: 3
```

**Direct Redis Inspection:**
```bash
redis-cli

# Check leaderboard
ZRANGE leaderboard:customers 0 -1 WITHSCORES

# Get specific customer score
ZSCORE leaderboard:customers alice@example.com

# Get customer rank (0-indexed)
ZREVRANK leaderboard:customers alice@example.com
```

---

## 4. Queue & Horizon Testing

### Start Horizon
```bash
php artisan horizon
```

Visit: **http://localhost:8000/horizon** (or your APP_URL/horizon)

### Test Queue Jobs

**In another terminal:**
```bash
php artisan tinker
```

```php
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Jobs\ProcessOrderWorkflowJob;

// Create and queue an order
$order = Order::factory()->create();
ProcessOrderWorkflowJob::dispatch($order);

// Check queue status
\Illuminate\Support\Facades\Queue::size('orders'); // Should increment
```

**Monitor in Horizon Dashboard:**
- Navigate to **Jobs** tab
- You should see `ProcessOrderWorkflowJob` in the `orders` queue
- Monitor status: pending → processing → completed

### Test Different Queues

```php
use App\Domain\Orders\Jobs\GenerateDailyKPIsJob;

// This goes to 'kpi' queue
GenerateDailyKPIsJob::dispatch();

// Check both queues
echo "Orders queue: " . \Illuminate\Support\Facades\Queue::size('orders') . "\n";
echo "KPI queue: " . \Illuminate\Support\Facades\Queue::size('kpi') . "\n";
```

### Test Queue Workers (Without Horizon)

```bash
# Start queue worker manually
php artisan queue:work redis --queue=orders,kpi,default --tries=3

# In another terminal, dispatch jobs
php artisan tinker
```

```php
\App\Domain\Orders\Jobs\ProcessOrderWorkflowJob::dispatch(
    \App\Domain\Orders\Models\Order::factory()->create()
);
```

**Watch the first terminal for:**
```
[2025-01-15 10:30:00] Processing: App\Domain\Orders\Jobs\ProcessOrderWorkflowJob
[2025-01-15 10:30:01] Processed: App\Domain\Orders\Jobs\ProcessOrderWorkflowJob
```

---

## 5. CSV Import Testing

### Test with Sample Data

**Import the sample CSV:**
```bash
php artisan orders:import storage/app/sample_orders.csv
```

**Expected Output:**
```
Importing orders from /path/to/storage/app/sample_orders.csv...
Queued chunk #0 (10 rows)
Orders queued for import successfully!
```

**Verify in Database:**
```bash
php artisan tinker
```

```php
use App\Domain\Orders\Models\Order;

// Count imported orders
Order::count(); // Should have 10 new orders

// Check specific order
$order = Order::where('order_id', 'ORD-001')->first();
echo "Customer: " . $order->customer_name . "\n";
echo "Status: " . $order->status->value . "\n";
```

**Monitor Queue Processing:**
- Check Horizon dashboard
- You should see:
  - `ImportOrdersJob` in default queue
  - Multiple `ProcessOrderWorkflowJob` in orders queue

### Test Large CSV Import

**Create larger test file:**
```bash
php artisan tinker
```

```php
use League\Csv\Writer;

$csv = Writer::createFromPath(storage_path('app/large_orders.csv'), 'w+');
$csv->insertOne(['order_id', 'customer_name', 'customer_email', 'product_sku', 'product_name', 'quantity', 'unit_price', 'payment_method', 'order_date', 'status']);

for ($i = 1; $i <= 1000; $i++) {
    $csv->insertOne([
        "ORD-LARGE-{$i}",
        "Customer {$i}",
        "customer{$i}@example.com",
        "SKU-{$i}",
        "Product {$i}",
        rand(1, 5),
        rand(10, 500),
        ['credit_card', 'paypal'][rand(0, 1)],
        now()->subDays(rand(0, 30))->format('Y-m-d H:i:s'),
        'pending'
    ]);
}

echo "Created 1000 orders\n";
exit;
```

**Import:**
```bash
php artisan orders:import storage/app/large_orders.csv
```

**Monitor Performance:**
- Watch Horizon dashboard
- Check throughput (jobs/second)
- Monitor memory usage: `php artisan horizon:status`

---

## 6. Event System Testing

### Test Event Dispatching

```bash
php artisan tinker
```

```php
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Events\OrderCompleted;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Support\Facades\Event;

// Enable event debugging
Event::listen('*', function ($event, $data) {
    if (str_contains($event, 'Order')) {
        echo "Event fired: {$event}\n";
    }
});

// Create order and trigger workflow
$order = Order::factory()->create(['status' => OrderStatus::PENDING]);

(new \App\Domain\Orders\Actions\ReserveStock)->execute($order);
// Should output: Event fired: App\Domain\Orders\Events\OrderReserved

(new \App\Domain\Orders\Actions\SimulatePayment)->execute($order);
// Should output: Event fired: App\Domain\Orders\Events\OrderPaid

(new \App\Domain\Orders\Actions\FinalizeOrder)->execute($order);
// Should output: Event fired: App\Domain\Orders\Events\OrderCompleted
```

### Test KPI Updates via Events

```php
use App\Domain\Orders\Services\{KPIService, LeaderboardService};
use Illuminate\Support\Facades\Redis;

// Clear Redis
Redis::flushdb();

// Create completed order
$order = Order::factory()->create([
    'status' => OrderStatus::COMPLETED,
    'customer_email' => 'event-test@example.com',
    'quantity' => 2,
    'unit_price' => 75.00,
    'completed_at' => now(),
]);

// Manually trigger event (simulating what happens in workflow)
$event = new \App\Domain\Orders\Events\OrderCompleted($order);
$listener = new \App\Domain\Orders\Listeners\UpdateKPIMetrics(
    app(KPIService::class),
    app(LeaderboardService::class)
);
$listener->handle($event);

// Verify KPIs were updated
$kpis = app(KPIService::class)->getDailyKPIs(now());
print_r($kpis);
// Should show: revenue = 150, order_count = 1, average = 150

// Verify leaderboard
$customerInfo = app(LeaderboardService::class)->getCustomerInfo('event-test@example.com');
print_r($customerInfo);
// Should show: total_spent = 150, rank = 1
```

---

## 7. Daily Scheduler Testing

### Test Schedule List
```bash
php artisan schedule:list
```

**Expected Output:**
```
  0 1 * * * ........................... Next Due: 10 hours from now
    › php artisan App\Domain\Orders\Jobs\GenerateDailyKPIsJob

  */5 * * * * ......................... Next Due: 2 minutes from now
    › php artisan horizon:snapshot
```

### Test Manually

```bash
# Run the scheduled job immediately
php artisan tinker
```

```php
use App\Domain\Orders\Jobs\GenerateDailyKPIsJob;
use Carbon\Carbon;

// Generate KPIs for yesterday
$yesterday = Carbon::yesterday();

// Create some completed orders for yesterday
for ($i = 0; $i < 5; $i++) {
    \App\Domain\Orders\Models\Order::create([
        'order_id' => "DAILY-{$i}",
        'customer_name' => "Customer {$i}",
        'customer_email' => "daily{$i}@example.com",
        'product_sku' => "SKU-{$i}",
        'product_name' => "Product {$i}",
        'quantity' => rand(1, 3),
        'unit_price' => rand(50, 200),
        'payment_method' => 'card',
        'order_date' => $yesterday,
        'status' => \App\Domain\Orders\Enums\OrderStatus::COMPLETED,
        'completed_at' => $yesterday,
    ]);
}

// Run the job
$job = new GenerateDailyKPIsJob($yesterday);
$job->handle(app(\App\Domain\Orders\Services\KPIService::class));

// Verify snapshot was created
$kpis = app(\App\Domain\Orders\Services\KPIService::class)->getDailyKPIs($yesterday);
print_r($kpis);
// Should show aggregated data from the 5 orders
```

### Test Scheduler in Real-time

**Terminal 1:**
```bash
php artisan schedule:work
```

This runs the scheduler continuously (checks every minute).

**Terminal 2:**
```bash
# Watch logs
tail -f storage/logs/laravel.log | grep "KPI"
```

You should see daily KPI generation logs when the schedule runs.

---

## 8. Integration Test (Full Flow)

### Complete End-to-End Test

```bash
# 1. Clear Redis
redis-cli FLUSHDB

# 2. Start Horizon
php artisan horizon &

# 3. Import orders
php artisan orders:import storage/app/sample_orders.csv

# 4. Wait for processing (watch Horizon)
sleep 30

# 5. Check results
php artisan tinker
```

```php
use App\Domain\Orders\Services\{KPIService, LeaderboardService};
use App\Domain\Orders\Models\Order;

// Check order statuses
$completed = Order::where('status', 'completed')->count();
$failed = Order::where('status', 'failed')->count();
echo "Completed: {$completed}, Failed: {$failed}\n";

// Check KPIs
$kpiService = app(KPIService::class);
$today = now();
$kpis = $kpiService->getDailyKPIs($today);
print_r($kpis);

// Check leaderboard
$leaderboard = app(LeaderboardService::class);
$top5 = $leaderboard->getTopCustomers(5);
print_r($top5);

// Expected: John Doe should be #1 (has 3 orders in sample data)
```

---

## Troubleshooting

### Common Issues

**1. Redis Connection Error**
```bash
# Check if Redis is running
redis-cli PING

# Start Redis (macOS)
brew services start redis

# Start Redis (Linux)
sudo systemctl start redis
```

**2. Queue Jobs Not Processing**
```bash
# Check queue connection
php artisan queue:monitor

# Restart Horizon
php artisan horizon:terminate
php artisan horizon
```

**3. Tests Failing**
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# Rebuild autoloader
composer dump-autoload

# Re-run migrations
php artisan migrate:fresh
```

**4. Events Not Firing**
```bash
# Clear event cache
php artisan event:clear

# Check listener registration
php artisan event:list
```

---

## Performance Benchmarks

### Expected Performance

**CSV Import:**
- 1,000 orders: ~1-2 minutes
- 10,000 orders: ~10-15 minutes
- Throughput: ~1,000 orders/minute (with 3 workers)

**Queue Processing:**
- Order workflow: ~1-2 seconds per order (includes 1s payment simulation)
- KPI update: <100ms per order
- Throughput: ~50 jobs/second (with proper scaling)

**Redis Operations:**
- KPI retrieval: <10ms
- Leaderboard query: <50ms
- Update operations: <5ms

### Load Testing

```bash
# Install Apache Bench
brew install ab  # macOS

# Test KPI endpoint (if you create one)
ab -n 1000 -c 10 http://localhost:8000/api/kpis/today

# Monitor Redis during load
redis-cli --latency-history
```

---

## Success Criteria

✅ All automated tests pass
✅ CSV imports process without errors
✅ Orders transition through states correctly
✅ Events dispatch and listeners execute
✅ KPIs update in real-time
✅ Leaderboard ranks customers accurately
✅ Horizon shows all queues processing
✅ Daily schedule runs as configured
✅ Redis operations complete in <100ms
✅ No memory leaks during large imports

**You're ready for production when all criteria are met!**
