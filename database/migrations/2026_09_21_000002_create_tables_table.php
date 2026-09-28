<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();      // "1", "2", "VIP-1"
            $table->string('token', 40)->unique();       // used in the QR code URL
            $table->unsignedSmallInteger('seats')->default(4);
            $table->string('status', 20)->default('free');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
