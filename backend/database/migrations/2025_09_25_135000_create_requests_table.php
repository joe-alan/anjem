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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
            $table->string('beacon_in'); // Foreign key to beacons
            $table->string('beacon_out'); // Foreign key to beacons
            $table->enum('mode', ['beacon', 'p2p'])->default('beacon');
            $table->enum('status', ['pending', 'matched', 'accepted', 'in_progress', 'completed', 'cancelled'])
                  ->default('pending');
            $table->foreignId('matched_driver_id')->nullable()->constrained('drivers')->onDelete('set null');
            $table->boolean('is_pooled')->default(false);
            $table->integer('max_wait_minutes')->default(10);
            $table->decimal('pickup_lat', 10, 8)->nullable();
            $table->decimal('pickup_lng', 11, 8)->nullable();
            $table->decimal('dropoff_lat', 10, 8)->nullable();
            $table->decimal('dropoff_lng', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('beacon_in')->references('id')->on('beacons');
            $table->foreign('beacon_out')->references('id')->on('beacons');

            $table->index(['status', 'created_at']);
            $table->index(['rider_id', 'status']);
            $table->index('matched_driver_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
