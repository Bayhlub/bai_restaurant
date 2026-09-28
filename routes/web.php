<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\TableQrController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ---------------------------------------------------------------------------
// Customer (no login — the table's QR code carries a secret token)
// ---------------------------------------------------------------------------
Volt::route('t/{token}', 'customer.table')->name('customer.table');

// Language toggle (customers and staff); returns to the page the link was on.
Route::get('lang/{locale}', LocaleController::class)->name('lang.switch');

// ---------------------------------------------------------------------------
// Staff
// ---------------------------------------------------------------------------
Route::get('/', function () {
    $user = auth()->user();

    return redirect()->route(match ($user?->role) {
        UserRole::Kitchen => 'kitchen',
        UserRole::Cashier => 'cashier',
        UserRole::Admin => 'admin.menu',
        default => 'login',
    });
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn () => redirect('/'))->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    Volt::route('kitchen', 'kitchen.board')
        ->middleware('role:kitchen')
        ->name('kitchen');

    Route::middleware('role:cashier')->group(function () {
        Volt::route('cashier', 'cashier.tables')->name('cashier');
        Volt::route('cashier/session/{session}', 'cashier.session')->name('cashier.session');
        Route::get('cashier/invoice/{invoice}/print', [InvoicePrintController::class, 'show'])
            ->name('invoice.print');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Volt::route('menu', 'admin.menu')->name('menu');
        Volt::route('tables', 'admin.tables')->name('tables');
        Route::get('tables/qr', TableQrController::class)->name('tables.qr');
        Volt::route('reports', 'admin.reports')->name('reports');
    });
});

require __DIR__.'/auth.php';
