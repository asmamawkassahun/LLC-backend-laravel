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
        Schema::table('pricing_plans', function (Blueprint $table) {
            // Only drop unique constraint if slug column exists
            if (Schema::hasColumn('pricing_plans', 'slug')) {
                // Check if unique constraint exists before dropping
                $constraintName = 'pricing_plans_slug_unique';
                $constraintExists = DB::selectOne("
                    SELECT constraint_name 
                    FROM information_schema.table_constraints 
                    WHERE table_name = 'pricing_plans' 
                    AND constraint_name = ?
                    AND constraint_type = 'UNIQUE'
                ", [$constraintName]);
                
                if ($constraintExists) {
            $table->dropUnique(['slug']);
                }
            $table->string('slug')->nullable()->change();
            }
            
            // Only change features if column exists
            if (Schema::hasColumn('pricing_plans', 'features')) {
            $table->json('features')->nullable()->change();
            }
            
            // Only change type if column exists
            if (Schema::hasColumn('pricing_plans', 'type')) {
            $table->string('type')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
            $table->json('features')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
        });
    }
};
