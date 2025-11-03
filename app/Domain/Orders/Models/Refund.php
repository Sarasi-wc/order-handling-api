<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'refund_reference',
        'type',
        'amount',
        'reason',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the order that this refund belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if refund is already processed
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if refund is full refund
     */
    public function isFullRefund(): bool
    {
        return $this->type === 'full';
    }

    /**
     * Check if refund is partial refund
     */
    public function isPartialRefund(): bool
    {
        return $this->type === 'partial';
    }
}
