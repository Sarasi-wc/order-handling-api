<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['full', 'partial']);
        $amount = $this->faker->randomFloat(2, 10, 500);

        return [
            'order_id' => Order::factory(),
            'refund_reference' => 'REF-' . strtoupper($this->faker->unique()->lexify('??????')),
            'type' => $type,
            'amount' => $amount,
            'reason' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['pending', 'processed', 'failed']),
            'processed_at' => $this->faker->boolean(70) ? now() : null,
        ];
    }

    public function processed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_at' => null,
        ]);
    }

    public function fullRefund(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'full',
        ]);
    }

    public function partialRefund(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'partial',
        ]);
    }
}
