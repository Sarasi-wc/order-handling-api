# Order Handling & Processing System

A production-ready order processing system built with Laravel that handles large-scale CSV imports, asynchronous order workflows, real-time analytics, notifications, and refund processing.

## Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Testing](#testing)
- [Architecture](#architecture)
- [Documentation](#documentation)
- [License](#license)

---

## Introduction

This system implements a comprehensive order handling workflow using Domain-Driven Design (DDD) principles, Laravel Horizon for queue management, and Redis for high-performance analytics. It processes orders through a state-driven workflow with automatic rollback on failures, maintains real-time KPIs and customer leaderboards, sends asynchronous notifications, and handles refunds with idempotent operations.

### Built With

- **Laravel 11.x** - PHP framework
- **Laravel Horizon** - Queue monitoring and management
- **Redis** - High-performance caching and analytics
- **MySQL/PostgreSQL** - Relational database
- **PHPUnit** - Testing framework

---

## Features

### Core System (Task 1)

#### CSV Import System
- `orders:import` command reads CSV files and processes them in batches of 100 orders using queued jobs
- Non-blocking import with progress tracking
- Automatic validation and error handling

#### State Machine
- Implemented `OrderStatus` enum with 5 states: `PENDING → RESERVED → PAID → COMPLETED/FAILED`
- Transition guards enforce valid state changes
- Type-safe status management preventing invalid transitions

#### Order Workflow
Built 4-step processing pipeline:
1. **Reserve Stock** - Marks inventory as reserved
2. **Simulate Payment** - Processes payment (30% random failure for testing)
3. **Finalize Order** - Completes successful orders
4. **Automatic Rollback** - Reverts failed transactions with try-catch error handling

#### Event-Driven Architecture
Created 5 domain events to decouple workflow from side effects:
- `OrderReserved`
- `OrderPaid`
- `OrderCompleted`
- `OrderFailed`
- `OrderRefunded`

#### Queue Management
Configured Laravel Horizon with 3 supervisors managing 5 separate queues:
- `orders` - Order workflow processing (auto-scaling: 3 local, 20 production)
- `kpi` - Analytics updates
- `notifications` - Customer notifications
- `refunds` - Refund processing
- `default` - Imports and general tasks

#### Real-Time Analytics
Built `KPIService` and `LeaderboardService` using Redis:
- Daily KPIs stored in Redis hashes with 90-day TTL
- Customer leaderboard using Redis sorted sets
- Atomic operations (`hincrbyfloat`, `zincrby`) for thread-safety
- Metrics: revenue, order count, average order value, top customers
- Sub-10ms read performance

### Notification System (Task 2)

- **Asynchronous Notifications**: Queued notification jobs triggered by order completion/failure events
- **Notification History**: `order_notifications` table stores all notifications for auditing
- **Event Listeners**: 2 listeners (`SendOrderCompletedNotification`, `SendOrderFailedNotification`) dispatch jobs to dedicated `notifications` queue
- **Extensible Design**: Log-based notifications can easily be extended to email/SMS/webhooks

### Refund System (Task 3)

- **Refund Processing**: Full and partial refund handling with comprehensive validation
- **Idempotency**: Unique `refund_reference` field with database constraints prevents duplicate refunds
- **Real-Time Analytics Updates**: Refunds atomically update both KPIs and leaderboard using Redis operations wrapped in database transactions
- **Business Rules**:
  - Order must be completed
  - Amount must be positive and not exceed order total
  - Full refunds must equal exact order total
- **Async Processing**: Refunds processed on dedicated `refunds` queue with comprehensive error handling

### Quality Assurance

- **52 Tests** with 138 assertions (100% pass rate)
- **PSR-12 Compliant** code enforced by Laravel Pint
- **Comprehensive Documentation** (see [Documentation](#documentation) section)
- **Production-Ready** with Supervisor configs and deployment guides

---

## System Requirements

- **PHP**: 8.2 or higher
- **Composer**: 2.x
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Redis**: 6.0 or higher
- **Node.js**: 18.x or higher (for asset compilation)
- **Supervisor**: For queue management (production)

---

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd order-handling-api
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies (Optional)

```bash
npm install
```

### 4. Environment Configuration

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 5. Configure Database

Edit `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_handling
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Create the database:

```bash
# MySQL
mysql -u root -p -e "CREATE DATABASE order_handling;"
```

### 6. Configure Redis

Edit `.env` file with Redis configuration:

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Ensure Redis is running:

```bash
# Check Redis status
redis-cli ping
# Should return: PONG
```

### 7. Run Migrations

```bash
php artisan migrate
```

### 8. Configure Queue Connection

Edit `.env` to use Redis for queues:

```env
QUEUE_CONNECTION=redis
```

### 9. Install and Configure Horizon

Publish Horizon assets:

```bash
php artisan horizon:install
```

Horizon configuration is already set up in `config/horizon.php` with 3 supervisors.

### 10. Set Up Supervisor (Production Only)

Copy supervisor configuration files:

```bash
sudo cp supervisor/horizon.conf /etc/supervisor/conf.d/
sudo cp supervisor/scheduler.conf /etc/supervisor/conf.d/
```

Update the configuration files with your project path, then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon-worker:*
sudo supervisorctl start laravel-scheduler:*
```

### 11. Configure Laravel Scheduler

Add to your crontab (production):

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Configuration

### Queue Configuration

The system uses 5 separate queues configured in `config/horizon.php`:

- **orders**: High-priority order processing (auto-scaling)
- **kpi**: Analytics updates
- **notifications**: Customer notifications
- **refunds**: Refund processing
- **default**: General tasks and imports

### Redis TTL

KPI data is stored with a 90-day TTL. Modify in `app/Domain/Orders/Services/KPIService.php` if needed:

```php
Redis::expire($key, 60 * 60 * 24 * 90); // 90 days
```

### Horizon Dashboard

Access Horizon at: `http://your-domain.com/horizon`

For production, configure authentication in `app/Providers/HorizonServiceProvider.php`.

---

## Usage

### Import Orders from CSV

```bash
php artisan orders:import storage/app/imports/orders.csv
```

The CSV file should have the following columns:
```csv
order_id,customer_name,customer_email,product_sku,product_name,quantity,unit_price,payment_method,order_date,status
```

Example:
```csv
1001,John Doe,john@example.com,SKU123,Wireless Mouse,2,2500.00,card,2025-10-30,pending
```

### Start Queue Workers (Development)

```bash
# Start Horizon
php artisan horizon

# Or start individual queues
php artisan queue:work --queue=orders
php artisan queue:work --queue=kpi
php artisan queue:work --queue=notifications
php artisan queue:work --queue=refunds
```

### Monitor Queue Status

Access the Horizon dashboard:
```
http://localhost:8000/horizon
```

### View KPIs

```bash
php artisan tinker
```

```php
// Get today's KPIs
$kpi = app(\App\Domain\Orders\Services\KPIService::class);
$kpi->getDailyKPIs(today());

// Get top customers
$leaderboard = app(\App\Domain\Orders\Services\LeaderboardService::class);
$leaderboard->getTopCustomers(10);
```

### Process a Refund

```php
use App\Domain\Orders\Jobs\ProcessRefundJob;
use App\Domain\Orders\Models\Order;

$order = Order::where('status', 'completed')->first();

// Full refund
ProcessRefundJob::dispatch($order, 'REF-123', 'full', $order->total_amount, 'Customer request');

// Partial refund
ProcessRefundJob::dispatch($order, 'REF-124', 'partial', 50.00, 'Damaged item');
```

### View Notifications

```php
use App\Domain\Orders\Models\OrderNotification;

// Get all notifications for an order
$notifications = OrderNotification::where('order_id', $orderId)->get();

// Get failed order notifications
$failedNotifications = OrderNotification::where('type', 'failed')->get();
```

---

## Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suites

```bash
# Workflow tests
php artisan test --filter=OrderWorkflowTest

# Notification tests
php artisan test --filter=OrderNotificationTest

# Refund tests
php artisan test --filter=RefundHandlingTest

# KPI tests
php artisan test --filter=KPIServiceTest

# Leaderboard tests
php artisan test --filter=LeaderboardServiceTest
```

### Code Style

Check code style:
```bash
composer style:check
```

Fix code style:
```bash
composer style:fix
```

### Test Coverage

Current test coverage:
- **52 tests** with **138 assertions**
- **100% pass rate**
- **Runtime**: ~2.6 seconds

---

## Architecture

### Domain-Driven Design Structure

```
app/Domain/Orders/
├── Actions/              # Domain actions (ReserveStock, SimulatePayment, etc.)
├── Console/              # Artisan commands
├── Enums/                # Value objects (OrderStatus)
├── Events/               # Domain events
├── Jobs/                 # Queued jobs
├── Listeners/            # Event listeners
├── Models/               # Eloquent models
└── Services/             # Domain services (KPIService, LeaderboardService)
```

### Order State Machine

```
PENDING → RESERVED → PAID → COMPLETED
         ↓         ↓      ↓
         →    FAILED    ←
```

Valid transitions are enforced by `OrderStatus::canTransitionTo()`.

### Queue Architecture

```
┌─────────────────────────────────────────┐
│         Import Command (Entry)          │
└──────────────────┬──────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│              Default Queue              │
│            (ImportOrdersJob)            │
└──────────────────┬──────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│              Orders Queue               │
│      (ProcessOrderWorkflowJob)          │
│   - ReserveStock                        │
│   - SimulatePayment                     │
│   - FinalizeOrder                       │
│   - RollbackOrder (on failure)          │
└──────────────────┬──────────────────────┘
                   ↓
        ┌──────────┴──────────┐
        ↓                     ↓
┌──────────────┐    ┌──────────────────┐
│  KPI Queue   │    │ Notifications Q  │
│ (Analytics)  │    │ (Notifications)  │
└──────────────┘    └──────────────────┘
```

### Event-Driven Flow

1. **Action** executes → Updates order state
2. **Event** fires → Domain event dispatched
3. **Listeners** react → Multiple listeners handle event independently
4. **Jobs** queued → Asynchronous processing

Example:
```php
FinalizeOrder → OrderCompleted event
             ├→ UpdateKPIMetrics listener (kpi queue)
             └→ SendOrderCompletedNotification listener (notifications queue)
```

### Redis Data Structures

#### KPIs (Hash)
```
Key: kpi:daily:2025-11-03
Fields:
  - revenue: 313800.00
  - count: 21
TTL: 90 days
```

#### Leaderboard (Sorted Set)
```
Key: leaderboard:customers
Members: customer@email.com
Scores: total_spending
```

---

## Documentation

- **[REQUIREMENTS_CHECKLIST.md](REQUIREMENTS_CHECKLIST.md)** - Requirements verification and system overview
- **[TASKS_2_AND_3_IMPLEMENTATION.md](TASKS_2_AND_3_IMPLEMENTATION.md)** - Detailed implementation of notifications and refunds
- **[TESTING.md](TESTING.md)** - Comprehensive testing guide
- **[supervisor/README.md](supervisor/README.md)** - Supervisor configuration and deployment

---

## Performance Metrics

- **Import Throughput**: 100 orders per chunk
- **Redis Operations**: < 10ms reads, < 5ms writes
- **Leaderboard Queries**: < 50ms
- **Test Suite Runtime**: ~2.6 seconds
- **Successfully Processed**: 142 orders (21 completed, Rs 313,800 revenue)

---

## Key Design Decisions

1. **Event-Driven Architecture**: Decouples workflow from side effects for flexibility
2. **Queue Isolation**: Separate queues prevent cascading failures
3. **State Machine Pattern**: Enforces valid order transitions
4. **Idempotency**: Refunds use unique references for safe retries
5. **Atomic Operations**: Redis commands prevent race conditions
6. **Database Transactions**: Ensures consistency across refund operations

---

## Troubleshooting

### Horizon not processing jobs

```bash
# Clear Redis cache
php artisan cache:clear

# Restart Horizon
php artisan horizon:terminate
php artisan horizon
```

### Redis connection errors

```bash
# Check Redis is running
redis-cli ping

# Check Redis configuration in .env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Queue workers not starting

```bash
# Check queue configuration
php artisan queue:monitor redis:default,redis:orders,redis:kpi

# Clear failed jobs
php artisan queue:flush
```

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Credits

Built with Laravel, Horizon, and Redis. Implements Domain-Driven Design principles and queue-based architecture for scalable order processing.
