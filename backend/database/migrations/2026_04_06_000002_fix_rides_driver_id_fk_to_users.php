<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix rides.driver_id FK to reference users.id instead of driver_profiles.id.
 *
 * The column was always used as a user ID throughout the codebase (Ride model,
 * RideService, etc.) but the original FK mistakenly referenced driver_profiles.
 * This only worked in early testing because user IDs and driver_profile IDs
 * happened to match numerically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->foreign('driver_id')->references('id')->on('driver_profiles')->nullOnDelete();
        });
    }
};
