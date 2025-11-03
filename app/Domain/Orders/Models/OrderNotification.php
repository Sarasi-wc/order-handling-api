<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends Model
{
    protected $fillable = [
        'order_id',
        'customer_email',
        'type',
        'status',
        'total',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'total' => 'float',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the order that this notification belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
