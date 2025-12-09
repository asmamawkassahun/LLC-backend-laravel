<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes slug, type, and features columns
     * and adds/ensures description column exists.
     */
    public function up(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            // Remove slug column if it exists
            if (Schema::hasColumn('pricing_plans', 'slug')) {
                $table->dropColumn('slug');
            }
            
            // Remove type column if it exists
            if (Schema::hasColumn('pricing_plans', 'type')) {
                $table->dropColumn('type');
            }
            
            // Remove features column if it exists
            if (Schema::hasColumn('pricing_plans', 'features')) {
                $table->dropColumn('features');
            }
            
            // Add or modify description column
            if (!Schema::hasColumn('pricing_plans', 'description')) {
                // Add description column as json if it doesn't exist
                $table->json('description')->nullable()->after('name');
            } else {
                // Change existing description column from string to json
                $table->json('description')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            // Re-add the columns if they were removed
            if (!Schema::hasColumn('pricing_plans', 'slug')) {
                $table->string('slug')->unique()->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('pricing_plans', 'type')) {
                $table->string('type')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('pricing_plans', 'features')) {
                $table->json('features')->nullable()->after('description');
            }
            
            // Revert description column to string type if it was changed by this migration
            if (Schema::hasColumn('pricing_plans', 'description')) {
                $table->string('description')->nullable()->change();
            }
        });
    }
};
