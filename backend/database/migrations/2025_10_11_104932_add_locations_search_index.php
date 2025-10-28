<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create GIN index for full-text search on locations table
        // Using Indonesian language for better search results
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_locations_search
            ON locations
            USING GIN(
                to_tsvector('indonesian',
                    name || ' ' ||
                    COALESCE(address, '') || ' ' ||
                    COALESCE(description, '')
                )
            )
        ");

        // Add index for usage_count to optimize popularity sorting
        Schema::table('locations', function (Blueprint $table) {
            $table->index('usage_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_locations_search');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['usage_count']);
        });
    }
};
