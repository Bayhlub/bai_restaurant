<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['table_id', 'status', 'opened_at', 'closed_at', 'bill_requested_at'])]
class TableSession extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'bill_requested_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('created_at');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** Items that count towards the bill (everything the kitchen did not reject). */
    public function billableItems()
    {
        return $this->items()->where('order_items.status', '!=', OrderItemStatus::Rejected);
    }

    public function subtotal(): float
    {
        return (float) $this->billableItems()
            ->selectRaw('COALESCE(SUM(unit_price * qty), 0) as total')
            ->value('total');
    }

    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Open;
    }

    /** Customer asked for the bill from their phone. */
    public function requestBill(): void
    {
        $this->update(['bill_requested_at' => $this->bill_requested_at ?? now()]);
    }

    public function billRequested(): bool
    {
        return $this->bill_requested_at !== null && $this->status !== SessionStatus::Paid;
    }
}
