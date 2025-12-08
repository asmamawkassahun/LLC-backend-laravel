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
        Schema::table('companies', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['user_id']);
            // Drop user_id column
            $table->dropColumn('user_id');
            // Add company_owner_ids as JSON column to store array of owner IDs
            $table->json('company_owner_ids')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Drop company_owner_ids column
            $table->dropColumn('company_owner_ids');
            // Re-add user_id column
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
