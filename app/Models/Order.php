<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

#[Fillable([
    'table_session_id', 'daily_number', 'status', 'note',
    'accepted_at', 'cooking_at', 'ready_at', 'served_at',
])]
class Order extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'accepted_at' => 'datetime',
            'cooking_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            // Ticket number restarts at 1 every day; simple for the kitchen to call out.
            $order->daily_number ??= (int) static::whereDate('created_at', today())->max('daily_number') + 1;
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function table(): HasOneThrough
    {
        return $this->hasOneThrough(
            Table::class, TableSession::class,
            'id', 'id', 'table_session_id', 'table_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Orders the kitchen still has to deal with. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready,
        ]);
    }

    public function total(): float
    {
        return (float) $this->items
            ->where('status', '!=', OrderItemStatus::Rejected)
            ->sum(fn (OrderItem $item) => $item->lineTotal());
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function hasBillableItems(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => ! $item->isRejected());
    }

    // Kitchen state transitions -------------------------------------------

    /**
     * Accept every item the kitchen has not rejected and start cooking.
     * If nothing is left to cook the order is cancelled instead.
     */
    public function startCooking(): void
    {
        $this->items()->where('status', OrderItemStatus::Pending)->update(['status' => OrderItemStatus::Accepted]);
        $this->unsetRelation('items');

        if (! $this->hasBillableItems()) {
            $this->cancel();

            return;
        }

        $this->update([
            'status' => OrderStatus::Cooking,
            'accepted_at' => $this->accepted_at ?? now(),
            'cooking_at' => now(),
        ]);
    }

    public function markReady(): void
    {
        $this->items()->where('status', '!=', OrderItemStatus::Rejected)->update(['status' => OrderItemStatus::Ready]);

        $this->update(['status' => OrderStatus::Ready, 'ready_at' => now()]);
    }

    public function markServed(): void
    {
        $this->items()->where('status', '!=', OrderItemStatus::Rejected)->update(['status' => OrderItemStatus::Served]);

        $this->update(['status' => OrderStatus::Served, 'served_at' => now()]);
    }

    public function cancel(): void
    {
        $this->update(['status' => OrderStatus::Cancelled]);
    }
}
