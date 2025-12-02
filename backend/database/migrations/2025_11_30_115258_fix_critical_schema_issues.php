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
        // Fix: Drop user_type column from users table (we use role column now)
        // The rides table already has ride_request_id (no need to rename)
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropColumn('user_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: Re-add user_type column
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['rider', 'driver', 'both'])
                ->default('rider')
                ->after('email');
        });
    }
};
