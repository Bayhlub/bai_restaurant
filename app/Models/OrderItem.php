<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'menu_item_id', 'name_lo', 'name_en', 'unit_price', 'qty',
    'status', 'rejection_reason', 'note',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'status' => OrderItemStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'lo' ? $this->name_lo : $this->name_en;
    }

    public function lineTotal(): float
    {
        return (float) $this->unit_price * $this->qty;
    }

    public function isRejected(): bool
    {
        return $this->status === OrderItemStatus::Rejected;
    }

    /** Kitchen cannot make this line. The ticket stays in review until the kitchen confirms it. */
    public function reject(string $reason): void
    {
        $this->update(['status' => OrderItemStatus::Rejected, 'rejection_reason' => $reason]);
    }

    /** Undo a rejection while the ticket is still being reviewed. */
    public function unreject(): void
    {
        $this->update(['status' => OrderItemStatus::Pending, 'rejection_reason' => null]);
    }
}
