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
        Schema::table('marketplace_services', function (Blueprint $table) {
            // Use PostgreSQL array type to store multiple country IDs
            $table->json('country_id')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_services', function (Blueprint $table) {
            $table->dropColumn('country_id');
        });
    }
};
