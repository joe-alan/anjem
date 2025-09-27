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
        // Enable PostGIS extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['beacon', 'p2p_destination'])->index();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();

            // Beacon-specific fields
            $table->integer('beacon_capacity')->nullable();
            $table->integer('current_queue_size')->default(0);

            // JSONB metadata field for additional data
            $table->json('metadata')->nullable();

            $table->timestamps();
        });

        // Add PostGIS spatial column separately
        DB::statement('ALTER TABLE locations ADD COLUMN coordinates GEOMETRY(POINT, 4326)');

        // Create spatial index for efficient proximity queries
        DB::statement('CREATE INDEX locations_coordinates_gist_idx ON locations USING GIST (coordinates)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
