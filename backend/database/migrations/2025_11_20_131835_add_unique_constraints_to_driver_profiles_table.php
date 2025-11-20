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
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Add unique constraints for KYC fields to prevent duplicate registrations
            $table->unique('student_email', 'driver_profiles_student_email_unique');
            $table->unique('student_id', 'driver_profiles_student_id_unique');
            $table->unique('vehicle_plate', 'driver_profiles_vehicle_plate_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Drop unique constraints
            $table->dropUnique('driver_profiles_student_email_unique');
            $table->dropUnique('driver_profiles_student_id_unique');
            $table->dropUnique('driver_profiles_vehicle_plate_unique');
        });
    }
};
