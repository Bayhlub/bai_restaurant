<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name_lo' => 'ອາຫານ '.fake()->unique()->word(),
            'name_en' => ucfirst(fake()->unique()->words(2, true)),
            'description_lo' => null,
            'description_en' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(5, 100) * 1000,
            'image_path' => null,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
