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
        Schema::table('ride_requests', function (Blueprint $table) {
            // Rename column to match model expectation
            $table->renameColumn('dropoff_location_id', 'destination_location_id');

            // Add estimated_distance_km (convert from estimated_distance_m)
            $table->decimal('estimated_distance_km', 8, 2)->nullable()->after('destination_location_id');
        });

        // Convert existing distance data from meters to kilometers
        DB::statement('UPDATE ride_requests SET estimated_distance_km = estimated_distance_m / 1000.0 WHERE estimated_distance_m IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Reverse the changes
            $table->renameColumn('destination_location_id', 'dropoff_location_id');
            $table->dropColumn('estimated_distance_km');
        });
    }
};