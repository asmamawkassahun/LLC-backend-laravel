<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, convert existing string to JSON array
        DB::statement("ALTER TABLE marketplace_orders ALTER COLUMN file TYPE jsonb USING CASE WHEN file IS NULL THEN '[]'::jsonb ELSE jsonb_build_array(file) END");
    }

    public function down(): void
    {
        // Convert back to string (take first element if array)
        DB::statement("ALTER TABLE marketplace_orders ALTER COLUMN file TYPE VARCHAR(255) USING CASE WHEN jsonb_typeof(file) = 'array' AND jsonb_array_length(file) > 0 THEN file->>0 ELSE NULL END");
    }
};
