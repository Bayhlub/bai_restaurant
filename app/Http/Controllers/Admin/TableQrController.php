<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\View\View;

class TableQrController extends Controller
{
    /**
     * Printable sheet with one QR card per active table.
     */
    public function __invoke(): View
    {
        $tables = Table::where('is_active', true)
            ->orderByRaw('CAST(number AS INTEGER)')
            ->orderBy('number')
            ->get();

        return view('admin.tables-qr', compact('tables'));
    }
}
