<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            // Set when the customer taps "Request bill"; resolved by paying the session.
            $table->timestamp('bill_requested_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropColumn('bill_requested_at');
        });
    }
};
