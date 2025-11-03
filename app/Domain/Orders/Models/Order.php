<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
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
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'order_date' => 'datetime',
    ];
}
