<?php

namespace Database\Factories;

use App\Enums\OrderItemStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name_lo' => 'ອາຫານ',
            'name_en' => ucfirst(fake()->words(2, true)),
            'unit_price' => fake()->numberBetween(5, 100) * 1000,
            'qty' => fake()->numberBetween(1, 4),
            'status' => OrderItemStatus::Pending,
        ];
    }

    /** Copy name and price from the linked menu item, as the customer UI does. */
    public function forMenuItem(MenuItem $menuItem): static
    {
        return $this->state(fn () => [
            'menu_item_id' => $menuItem->id,
            'name_lo' => $menuItem->name_lo,
            'name_en' => $menuItem->name_en,
            'unit_price' => $menuItem->price,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => OrderItemStatus::Rejected,
            'rejection_reason' => 'Sold out',
        ]);
    }
}
