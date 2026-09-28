<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_session_id' => TableSession::factory(),
            'status' => OrderStatus::Pending,
            'note' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Ready,
            'accepted_at' => now(),
            'cooking_at' => now(),
            'ready_at' => now(),
        ]);
    }

    public function served(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Served,
            'accepted_at' => now(),
            'cooking_at' => now(),
            'ready_at' => now(),
            'served_at' => now(),
        ]);
    }
}
