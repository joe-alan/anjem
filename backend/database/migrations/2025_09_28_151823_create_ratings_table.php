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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->onDelete('cascade');
            $table->foreignId('rater_id')->constrained('users')->onDelete('cascade'); // who gave the rating
            $table->foreignId('rated_id')->constrained('users')->onDelete('cascade'); // who received the rating
            $table->enum('rating_type', ['rider_to_driver', 'driver_to_rider']);
            $table->integer('score')->checkBetween(1, 5);
            $table->json('tags')->nullable(); // flexible tags like ["on-time", "friendly", "clean-bike"]
            $table->text('feedback')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
