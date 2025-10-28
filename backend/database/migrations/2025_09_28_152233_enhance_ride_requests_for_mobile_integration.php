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
            // Mobile app enhancements
            $table->integer('passenger_count')->default(1)->after('is_pooled');
            $table->json('special_requests')->nullable()->after('notes'); // JSON for structured requests
            $table->string('pickup_address')->nullable()->after('pickup_location_id'); // Cache address for display
            $table->string('dropoff_address')->nullable()->after('dropoff_location_id');

            // Real-time tracking
            $table->decimal('current_fare_estimate', 10, 2)->nullable()->after('estimated_fare_rp');
            $table->integer('estimated_duration_minutes')->nullable()->after('estimated_distance_m');

            // Soft deletes for data retention
            $table->softDeletes();

            // Better indexing for mobile queries
            $table->index(['rider_id', 'status', 'created_at']);
            $table->index(['matched_driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropIndex(['rider_id', 'status', 'created_at']);
            $table->dropIndex(['matched_driver_id', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'passenger_count', 'special_requests', 'pickup_address',
                'dropoff_address', 'current_fare_estimate', 'estimated_duration_minutes',
            ]);
        });
    }
};
