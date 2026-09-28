<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(20, 500) * 1000;

        return [
            'table_session_id' => TableSession::factory()->paid(),
            'cashier_id' => User::factory()->cashier(),
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'cash_received' => $subtotal,
            'change_amount' => 0,
            'paid_at' => now(),
        ];
    }
}
