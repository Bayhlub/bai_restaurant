<?php

namespace App\Livewire\Actions;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlaceOrder
{
    /**
     * Items the customer asked for that are no longer orderable (sold out or removed).
     *
     * @var array<int, string>
     */
    public array $droppedItemNames = [];

    /**
     * Create an order for the table from the customer's cart.
     *
     * Opens a table session if the table does not have one yet, snapshots the
     * name and price of every item, and silently drops items that became
     * unavailable between browsing and submitting (see $droppedItemNames).
     *
     * @param  array<int, array{qty: int, note?: string}>  $cart  keyed by menu item id
     *
     * @throws InvalidArgumentException when nothing in the cart can be ordered
     */
    public function __invoke(Table $table, array $cart, ?string $note = null): Order
    {
        $this->droppedItemNames = [];

        $requestedIds = array_keys($cart);
        $orderableItems = MenuItem::orderable()->whereIn('id', $requestedIds)->get()->keyBy('id');

        $this->droppedItemNames = MenuItem::whereIn('id', $requestedIds)
            ->whereNotIn('id', $orderableItems->keys())
            ->get()
            ->map(fn (MenuItem $item) => $item->name)
            ->all();

        $lines = collect($cart)
            ->filter(fn (array $line, int $id) => $orderableItems->has($id) && $line['qty'] > 0)
            ->map(fn (array $line, int $id) => [
                'menu_item_id' => $id,
                'name_lo' => $orderableItems[$id]->name_lo,
                'name_en' => $orderableItems[$id]->name_en,
                'unit_price' => $orderableItems[$id]->price,
                'qty' => min((int) $line['qty'], 99),
                'note' => filled($line['note'] ?? null) ? trim($line['note']) : null,
            ])
            ->values();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('Nothing in the cart can be ordered.');
        }

        return DB::transaction(function () use ($table, $lines, $note) {
            $session = $table->openOrStartSession();

            $order = $session->orders()->create([
                'note' => filled($note) ? trim($note) : null,
            ]);

            $order->items()->createMany($lines->all());

            return $order->load('items');
        });
    }
}
