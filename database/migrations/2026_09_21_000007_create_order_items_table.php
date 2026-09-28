<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the menu item at order time so later price/name edits do not alter old bills.
            $table->string('name_lo');
            $table->string('name_en');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('qty');
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
