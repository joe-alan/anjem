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
        Schema::table('rides', function (Blueprint $table) {
            // Rename request_id to ride_request_id to match model
            $table->renameColumn('request_id', 'ride_request_id');

            // Update foreign key reference to point to ride_requests instead of driver_profiles
            $table->dropForeign(['driver_id']);
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');

            // Add missing columns that model expects
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->after('driver_id');
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->after('pickup_location_id');
            $table->integer('passenger_count')->default(1)->after('destination_location_id');
            $table->integer('estimated_fare_rp')->nullable()->after('passenger_count');
            $table->decimal('actual_distance_km', 8, 2)->nullable()->after('estimated_fare_rp');
            $table->integer('actual_duration_minutes')->nullable()->after('actual_distance_km');
            $table->timestamp('driver_accepted_at')->nullable()->after('actual_duration_minutes');
            $table->timestamp('pickup_time')->nullable()->after('driver_accepted_at');
            $table->timestamp('dropoff_time')->nullable()->after('pickup_time');
            $table->json('special_requests')->nullable()->after('dropoff_time');
            $table->text('driver_notes')->nullable()->after('special_requests');

            // Remove columns not used by model
            $table->dropColumn([
                'actual_distance_m',
                'duration_seconds',
                'fare_rp',
                'driver_earning_rp',
                'pickup_location',
                'dropoff_location',
                'assigned_at',
                'started_at',
                'completed_at',
                'cancelled_at'
            ]);

            // Update status enum
            $table->dropColumn('status');
        });

        // Add correct status enum
        Schema::table('rides', function (Blueprint $table) {
            $table->enum('status', ['matched', 'accepted', 'in_progress', 'completed', 'cancelled'])->default('matched')->after('destination_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Reverse all changes
            $table->renameColumn('ride_request_id', 'request_id');

            $table->dropForeign(['driver_id']);
            $table->foreign('driver_id')->references('id')->on('driver_profiles')->onDelete('cascade');

            $table->dropColumn([
                'pickup_location_id',
                'destination_location_id',
                'passenger_count',
                'estimated_fare_rp',
                'actual_distance_km',
                'actual_duration_minutes',
                'driver_accepted_at',
                'pickup_time',
                'dropoff_time',
                'special_requests',
                'driver_notes',
                'status'
            ]);

            // Add back original columns
            $table->enum('status', ['assigned', 'en_route', 'arrived', 'started', 'completed', 'cancelled'])->default('assigned');
            $table->integer('actual_distance_m')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('fare_rp', 10, 2)->default(0.00);
            $table->decimal('driver_earning_rp', 10, 2)->default(0.00);
            $table->geometry('pickup_location', 'POINT', 4326)->nullable();
            $table->geometry('dropoff_location', 'POINT', 4326)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }
};