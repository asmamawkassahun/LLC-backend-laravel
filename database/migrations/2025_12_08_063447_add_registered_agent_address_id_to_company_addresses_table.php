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
        Schema::table('company_addresses', function (Blueprint $table) {
            // Add foreign key to registered_agent_addresses
            $table->foreignId('registered_agent_address_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('registered_agent_addresses')
                  ->onDelete('set null');
            
            // Make address fields nullable (since they may reference registered agent)
            $table->string('street_address')->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->string('state')->nullable()->change();
            $table->string('zip_code')->nullable()->change();
            $table->string('country')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_addresses', function (Blueprint $table) {
            $table->dropForeign(['registered_agent_address_id']);
            $table->dropColumn('registered_agent_address_id');
            
            // Revert nullable changes
            $table->string('street_address')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            $table->string('state')->nullable(false)->change();
            $table->string('zip_code')->nullable(false)->change();
            $table->string('country')->nullable(false)->change();
        });
    }
};
