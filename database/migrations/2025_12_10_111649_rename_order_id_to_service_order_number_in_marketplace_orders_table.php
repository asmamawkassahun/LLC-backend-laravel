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
        Schema::table('marketplace_orders', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['order_id']);
        });

        // Rename the column and change its type
        DB::statement('ALTER TABLE marketplace_orders RENAME COLUMN order_id TO service_order_number');
        
        // Change the column type from bigint to string
        DB::statement('ALTER TABLE marketplace_orders ALTER COLUMN service_order_number TYPE VARCHAR(255)');
        
        // Make it nullable (it already is, but ensure it)
        DB::statement('ALTER TABLE marketplace_orders ALTER COLUMN service_order_number DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change back to bigint type
        DB::statement('ALTER TABLE marketplace_orders ALTER COLUMN service_order_number TYPE BIGINT USING NULL');
        
        Schema::table('marketplace_orders', function (Blueprint $table) {
            // Rename back
            DB::statement('ALTER TABLE marketplace_orders RENAME COLUMN service_order_number TO order_id');
        });

        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');
        });
    }
};
