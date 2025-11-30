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
        Schema::create('route_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('destination_location_id')->constrained('locations')->onDelete('cascade');
            $table->json('route_geometry'); // GeoJSON LineString
            $table->integer('distance_meters');
            $table->integer('duration_minutes');
            $table->string('profile', 20)->default('driving'); // driving, walking, cycling
            $table->timestamp('last_fetched_at')->useCurrent();
            $table->integer('fetch_count')->default(1);
            $table->timestamps();

            // Unique constraint: one cached route per origin-destination-profile combination
            $table->unique(['origin_location_id', 'destination_location_id', 'profile'], 'route_cache_unique');

            // Index for TTL queries (finding stale routes)
            $table->index('last_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_cache');
    }
};
