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
            // Drop columns that aren't used by the model and cause NOT NULL constraints
            $table->dropColumn([
                'request_type',
                'is_pooled',
                'max_wait_minutes',
                'estimated_distance_m',
                'notes',
                'current_fare_estimate',
                'pickup_address',
                'dropoff_address',
                'deleted_at',
            ]);

            // Remove foreign key constraint on matched_driver_id if it exists
            $table->dropForeign(['matched_driver_id']);
            $table->dropColumn('matched_driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Add back the columns
            $table->enum('request_type', ['beacon_to_beacon', 'beacon_to_p2p', 'p2p_to_p2p'])->nullable();
            $table->boolean('is_pooled')->default(false);
            $table->integer('max_wait_minutes')->default(10);
            $table->integer('estimated_distance_m')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('current_fare_estimate', 10, 2)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();
            $table->softDeletes();
            $table->foreignId('matched_driver_id')->nullable()->constrained('driver_profiles')->onDelete('set null');
        });
    }
};
