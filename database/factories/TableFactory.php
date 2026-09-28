<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(1, 999),
            'token' => Str::random(32),
            'seats' => fake()->numberBetween(2, 8),
            'status' => TableStatus::Free,
            'is_active' => true,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => TableStatus::Occupied]);
    }
}
