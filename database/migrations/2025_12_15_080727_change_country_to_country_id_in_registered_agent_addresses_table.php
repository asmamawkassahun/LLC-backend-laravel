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
        Schema::table('registered_agent_addresses', function (Blueprint $table) {
            // First, try to map existing country strings to country IDs
            // Get all unique country names from registered_agent_addresses
            $existingCountries = DB::table('registered_agent_addresses')
                ->select('country')
                ->distinct()
                ->whereNotNull('country')
                ->get();

            // Create a mapping of country names to IDs
            $countryMapping = [];
            foreach ($existingCountries as $row) {
                $country = DB::table('countries')
                    ->where('name', $row->country)
                    ->first();
                
                if ($country) {
                    $countryMapping[$row->country] = $country->id;
                }
            }

            // Add country_id column (nullable initially)
            $table->foreignId('country_id')->nullable()->after('postal_code');
        });

        // Update existing records with country_id based on mapping
        $existingCountries = DB::table('registered_agent_addresses')
            ->select('country')
            ->distinct()
            ->whereNotNull('country')
            ->get();

        $countryMapping = [];
        foreach ($existingCountries as $row) {
            $country = DB::table('countries')
                ->where('name', $row->country)
                ->first();
            
            if ($country) {
                $countryMapping[$row->country] = $country->id;
            }
        }

        foreach ($countryMapping as $countryName => $countryId) {
            DB::table('registered_agent_addresses')
                ->where('country', $countryName)
                ->update(['country_id' => $countryId]);
        }

        Schema::table('registered_agent_addresses', function (Blueprint $table) {
            // Drop the old country column
            $table->dropColumn('country');

            // Make country_id required and add foreign key constraint
            $table->foreignId('country_id')->nullable(false)->change();
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registered_agent_addresses', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['country_id']);

            // Add country column back
            $table->string('country')->after('postal_code');

            // Populate country from country_id
            $addresses = DB::table('registered_agent_addresses')
                ->join('countries', 'registered_agent_addresses.country_id', '=', 'countries.id')
                ->select('registered_agent_addresses.id', 'countries.name')
                ->get();

            foreach ($addresses as $address) {
                DB::table('registered_agent_addresses')
                    ->where('id', $address->id)
                    ->update(['country' => $address->name]);
            }

            // Drop country_id
            $table->dropColumn('country_id');
        });
    }
};
