<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_name',
        'customer_email',
        'product_sku',
        'product_name',
        'quantity',
        'unit_price',
        'payment_method',
        'order_date',
        'status',
        'reserved_stock',
        'payment_reference',
        'completed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'order_date' => 'datetime',
        'completed_at' => 'datetime',
        'reserved_stock' => 'boolean',
        'status' => OrderStatus::class, // Enum cast
    ];

    /**
     * Computed attribute: check if order is completed.
     */
    protected function isCompleted(): Attribute
    {
        return Attribute::get(fn () => $this->status === OrderStatus::COMPLETED);
    }

    /**
     * Domain rule: determine if transition is allowed.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    /**
     * Derived total value (Value Object candidate).
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::get(fn () => $this->quantity * $this->unit_price);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
