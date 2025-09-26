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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('ktm_url')->nullable(); // Student ID verification
            $table->string('vehicle_type')->default('motorcycle');
            $table->string('vehicle_plate')->nullable();
            $table->string('fcm_token')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->decimal('reliability_score', 5, 2)->default(0.00);
            $table->integer('experience_points')->default(0);
            $table->decimal('on_time_rate', 3, 2)->default(0.00);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('went_online_at')->nullable();
            $table->timestamps();

            $table->index(['is_online', 'is_active']);
            $table->index(['current_lat', 'current_lng']);
            $table->index('reliability_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
