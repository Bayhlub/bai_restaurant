<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Ready = 'ready';
    case Served = 'served';

    public function label(): string
    {
        return __('orders.item_status.'.$this->value);
    }

    /**
     * Tailwind classes for a status badge (full class names so the JIT compiler keeps them).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Accepted => 'bg-blue-100 text-blue-800',
            self::Ready, self::Served => 'bg-green-100 text-green-800',
            self::Rejected => 'bg-red-100 text-red-800',
        };
    }
}
