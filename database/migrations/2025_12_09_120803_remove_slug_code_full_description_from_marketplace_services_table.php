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
            $table->dropColumn(['slug', 'code', 'full_description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_services', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->string('code')->unique()->after('slug');
            $table->json('full_description')->nullable()->after('description');
        });
    }
};
