<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Vehicle information
            $table->string('vehicle_type'); // 'car', 'motorcycle', 'bajaj'
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->integer('vehicle_year')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->string('license_plate');
            $table->string('driver_license_number');

            // Driver status and verification
            $table->enum('status', ['offline', 'online', 'busy'])->default('offline')->index();
            $table->boolean('is_verified')->default(false)->index();

            // Cached stats for performance
            $table->decimal('rating_average', 3, 2)->default(5.00);
            $table->integer('rating_count')->default(0);
            $table->integer('total_rides')->default(0);

            // Location tracking
            $table->timestamp('last_location_update')->nullable();

            // Additional notes (for admin/support)
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'is_verified']);
        });

        // Add PostGIS spatial column for current location
        DB::statement('ALTER TABLE driver_profiles ADD COLUMN current_location GEOMETRY(POINT, 4326)');

        // Create spatial index for driver location queries
        DB::statement('CREATE INDEX driver_profiles_current_location_gist_idx ON driver_profiles USING GIST (current_location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
