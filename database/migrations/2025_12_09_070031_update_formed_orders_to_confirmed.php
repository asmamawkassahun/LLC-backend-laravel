<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing orders with 'formed' status to 'confirmed'
        DB::table('orders')
            ->where('status', 'formed')
            ->update(['status' => 'confirmed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'formed' if needed
        DB::table('orders')
            ->where('status', 'confirmed')
            ->update(['status' => 'formed']);
    }
};
