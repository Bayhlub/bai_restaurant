<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    /**
     * Thermal receipt (80 mm) that triggers the browser print dialog on load.
     */
    public function show(Invoice $invoice): View
    {
        $invoice->load('session.table', 'session.orders.items', 'cashier');

        $invoice->update(['printed_at' => now()]);

        // One line per item across all of the session's orders; rejected items never reach the bill.
        $lines = $invoice->session->orders
            ->flatMap->items
            ->reject->isRejected()
            ->values();

        return view('invoices.print', compact('invoice', 'lines'));
    }
}
