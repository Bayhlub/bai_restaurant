<?php

namespace App\Livewire\Actions;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Invoice;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseBill
{
    /**
     * Settle a table session in cash: create the invoice, mark the session paid and free the table.
     *
     * @throws InvalidArgumentException when the session is already settled, the discount exceeds
     *                                  the subtotal, or the cash received does not cover the total
     */
    public function __invoke(TableSession $session, User $cashier, float $discount, float $cashReceived): Invoice
    {
        if ($session->status === SessionStatus::Paid) {
            throw new InvalidArgumentException('This bill has already been paid.');
        }

        $subtotal = $session->subtotal();

        if ($discount < 0 || $discount > $subtotal) {
            throw new InvalidArgumentException('Discount cannot exceed the subtotal.');
        }

        $total = $subtotal - $discount;

        if ($cashReceived < $total) {
            throw new InvalidArgumentException('Cash received is less than the total.');
        }

        return DB::transaction(function () use ($session, $cashier, $subtotal, $discount, $total, $cashReceived) {
            $invoice = $session->invoice()->create([
                'cashier_id' => $cashier->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'cash_received' => $cashReceived,
                'change_amount' => $cashReceived - $total,
                'paid_at' => now(),
            ]);

            $session->update(['status' => SessionStatus::Paid, 'closed_at' => now()]);
            $session->table->update(['status' => TableStatus::Free]);

            return $invoice;
        });
    }
}
