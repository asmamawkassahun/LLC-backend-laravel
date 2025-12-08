<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing orders with 'draft' status to 'pending'
        DB::table('orders')
            ->where('status', 'draft')
            ->update(['status' => 'pending']);

        // Update the default value for the status column
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'draft' if needed
        DB::table('orders')
            ->where('status', 'pending')
            ->whereNull('company_id') // Only revert orders that haven't been processed
            ->update(['status' => 'draft']);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }
};
