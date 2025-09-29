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
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('ride_requests')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('driver_profiles')->onDelete('cascade');
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['assigned', 'en_route', 'arrived', 'started', 'completed', 'cancelled'])->default('assigned');
            $table->integer('actual_distance_m')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('fare_rp', 10, 2)->default(0.00);
            $table->decimal('driver_earning_rp', 10, 2)->default(0.00);
            $table->geometry('pickup_location', 'POINT', 4326)->nullable(); // actual pickup coordinates
            $table->geometry('dropoff_location', 'POINT', 4326)->nullable(); // actual dropoff coordinates
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
