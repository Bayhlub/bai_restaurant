<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_id' => Table::factory(),
            'status' => SessionStatus::Open,
            'opened_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
            'closed_at' => null,
        ];
    }

    public function billed(): static
    {
        return $this->state(fn () => ['status' => SessionStatus::Billed]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => SessionStatus::Paid, 'closed_at' => now()]);
    }
}
