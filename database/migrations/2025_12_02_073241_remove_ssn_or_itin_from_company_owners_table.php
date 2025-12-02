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
        Schema::table('company_owners', function (Blueprint $table) {
            $table->dropColumn('ssn_or_itin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_owners', function (Blueprint $table) {
            $table->string('ssn_or_itin')->nullable()->after('is_company');
        });
    }
};
