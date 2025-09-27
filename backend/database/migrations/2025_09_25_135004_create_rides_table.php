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
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['assigned', 'en_route', 'arrived', 'started', 'completed', 'cancelled'])
                ->default('assigned');
            $table->integer('distance_m')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('fare_rp', 10, 2)->default(0.00); // Fare in Rupiah
            $table->decimal('driver_earning_rp', 10, 2)->default(0.00);
            $table->decimal('pickup_lat', 10, 8)->nullable();
            $table->decimal('pickup_lng', 11, 8)->nullable();
            $table->decimal('dropoff_lat', 10, 8)->nullable();
            $table->decimal('dropoff_lng', 11, 8)->nullable();
            $table->integer('rider_rating')->nullable(); // 1-5 stars
            $table->integer('driver_rating')->nullable(); // 1-5 stars
            $table->text('rider_notes')->nullable();
            $table->text('driver_notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['rider_id', 'status']);
            $table->index(['request_id']);
            $table->index(['status', 'created_at']);
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
