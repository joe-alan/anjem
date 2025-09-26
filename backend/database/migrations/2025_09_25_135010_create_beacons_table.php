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
        Schema::create('beacons', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g., 'campus-gate', 'library-north'
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->integer('radius_m')->default(100); // Coverage radius in meters
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['lat', 'lng']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beacons');
    }
};
