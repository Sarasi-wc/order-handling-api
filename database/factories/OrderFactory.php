<?php

namespace Database\Factories;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_id' => $this->faker->unique()->numberBetween(1000, 9999),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'product_sku' => strtoupper($this->faker->lexify('SKU###')),
            'product_name' => $this->faker->words(3, true),
            'quantity' => $this->faker->numberBetween(1, 3),
            'unit_price' => $this->faker->randomFloat(2, 1000, 20000),
            'payment_method' => $this->faker->randomElement(['card', 'cash', 'bank']),
            'order_date' => now(),
            'status' => OrderStatus::PENDING,
        ];
    }
}
