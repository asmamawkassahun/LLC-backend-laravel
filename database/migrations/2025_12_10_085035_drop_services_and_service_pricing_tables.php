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
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_pricing');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate service_pricing table
        Schema::create('service_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price', 20, 2);
            $table->timestamps();
        });

        // Recreate services table
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('ein')->nullable()->unique();
            $table->string('itin')->nullable()->unique();
            $table->string('website')->nullable()->unique();
            $table->string('domain_hosting')->nullable()->unique();
            $table->string('business_email')->nullable()->unique();
            $table->timestamps();
        });
    }
};
