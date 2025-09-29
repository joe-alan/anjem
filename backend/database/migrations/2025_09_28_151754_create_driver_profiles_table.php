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
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('ktm_url', 500)->nullable(); // student ID verification
            $table->string('vehicle_type', 50)->default('motorcycle');
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('vehicle_color', 50)->nullable();
            $table->decimal('driver_rating_avg', 3, 2)->default(0.00);
            $table->decimal('reliability_score', 5, 2)->default(0.00);
            $table->integer('experience_points')->default(0);
            $table->decimal('on_time_rate', 3, 2)->default(0.00);
            $table->decimal('total_earnings', 12, 2)->default(0.00);
            $table->integer('total_rides_given')->default(0);
            $table->geometry('current_location', 'POINT', 4326)->nullable(); // PostGIS POINT
            $table->boolean('is_verified')->default(false);
            $table->timestamp('went_online_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
