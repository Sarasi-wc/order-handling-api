<?php

namespace App\Domain\Orders\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case RESERVED = 'reserved';
    case PAID = 'paid';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED => true,
            default => false,
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::RESERVED, self::FAILED]),
            self::RESERVED => in_array($newStatus, [self::PAID, self::FAILED]),
            self::PAID => in_array($newStatus, [self::COMPLETED, self::FAILED]),
            default => false,
        };
    }
}
