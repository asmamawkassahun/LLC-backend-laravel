<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['order_id']);
        });

        // Use raw SQL to alter the column to be nullable
        \DB::statement('ALTER TABLE payments ALTER COLUMN order_id DROP NOT NULL');

        Schema::table('payments', function (Blueprint $table) {
            // Re-add the foreign key constraint
            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['order_id']);
        });

        // Use raw SQL to alter the column to be NOT NULL
        \DB::statement('ALTER TABLE payments ALTER COLUMN order_id SET NOT NULL');

        Schema::table('payments', function (Blueprint $table) {
            // Re-add the foreign key constraint
            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');
        });
    }
};
