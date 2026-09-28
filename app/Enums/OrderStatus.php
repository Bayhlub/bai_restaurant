<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';     // sent by customer, kitchen has not looked yet
    case Accepted = 'accepted';   // kitchen reviewed items (some may be rejected)
    case Cooking = 'cooking';
    case Ready = 'ready';         // waiting to be served
    case Served = 'served';
    case Cancelled = 'cancelled'; // every item rejected

    public function label(): string
    {
        return __('orders.status.'.$this->value);
    }

    /**
     * Tailwind classes for a status badge (full class names so the JIT compiler keeps them).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Accepted, self::Cooking => 'bg-blue-100 text-blue-800',
            self::Ready => 'bg-green-100 text-green-800',
            self::Served => 'bg-gray-100 text-gray-700',
            self::Cancelled => 'bg-red-100 text-red-800',
        };
    }
}
