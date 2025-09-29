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
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pickup_location_id')->constrained('locations');
            $table->foreignId('dropoff_location_id')->constrained('locations');
            $table->foreignId('matched_driver_id')->nullable()->constrained('driver_profiles')->onDelete('set null');
            $table->enum('request_type', ['beacon_to_beacon', 'beacon_to_p2p', 'p2p_to_p2p']);
            $table->enum('status', ['pending', 'matched', 'accepted', 'cancelled'])->default('pending');
            $table->boolean('is_pooled')->default(false);
            $table->integer('max_wait_minutes')->default(10);
            $table->decimal('estimated_fare_rp', 10, 2)->nullable();
            $table->integer('estimated_distance_m')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
