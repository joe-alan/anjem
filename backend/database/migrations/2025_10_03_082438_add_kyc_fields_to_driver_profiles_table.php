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
            $table->string('student_email', 255)->nullable()->after('user_id');
            $table->string('student_id', 50)->nullable()->after('student_email');
            $table->string('student_name', 255)->nullable()->after('student_id');
            $table->timestamp('email_verified_at')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['student_email', 'student_id', 'student_name', 'email_verified_at']);
        });
    }
};
