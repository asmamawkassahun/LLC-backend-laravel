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
            $table->unsignedBigInteger('marketplace_order_id')->nullable()->after('order_id');
            $table->foreign('marketplace_order_id')
                  ->references('id')
                  ->on('marketplace_orders')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['marketplace_order_id']);
            $table->dropColumn('marketplace_order_id');
        });
    }
};
