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
        // Drop the existing foreign key constraint if it exists
        try {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropForeign(['assigned_to']);
            });
        } catch (\Exception $e) {
            // Foreign key might not exist, continue
        }

        // Clean up invalid assigned_to values that don't exist in admins table
        DB::statement('
            UPDATE support_tickets 
            SET assigned_to = NULL 
            WHERE assigned_to IS NOT NULL 
            AND assigned_to NOT IN (SELECT id FROM admins)
        ');

        // Change the foreign key to reference admins table
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreign('assigned_to')
                  ->references('id')
                  ->on('admins')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the admins foreign key
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
        });

        // Restore the users foreign key
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreign('assigned_to')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }
};
