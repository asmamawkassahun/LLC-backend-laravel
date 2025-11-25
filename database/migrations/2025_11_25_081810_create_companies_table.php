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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->nullable();
            // Foreign key will be added in a later migration after orders table exists
            $table->string('name');
            $table->string('type'); // LLC, LTD, CORP
            $table->unsignedBigInteger('country_id');
            // Foreign key will be added in a later migration after countries table exists
            $table->unsignedBigInteger('state_id')->nullable();
            // Foreign key will be added in a later migration after states table exists
            $table->string('registration_number')->nullable()->unique();
            $table->string('ein')->nullable()->unique();
            $table->string('itin')->nullable();
            $table->string('status')->default('pending'); // pending, processing, formed, rejected
            $table->timestamp('formed_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
