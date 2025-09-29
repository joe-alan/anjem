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
        Schema::table('users', function (Blueprint $table) {
            // Firebase integration
            $table->string('firebase_uid')->nullable()->unique()->after('id');
            $table->string('phone_number')->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');

            // Make password nullable since Firebase handles auth
            $table->string('password')->nullable()->change();

            // Add user type for better role management
            $table->enum('user_type', ['rider', 'driver', 'both'])->default('rider')->after('firebase_uid');

            // Laravel optimizations
            $table->softDeletes()->after('updated_at'); // Soft deletes for data retention
            $table->index(['firebase_uid', 'is_active']); // Composite index for Firebase lookups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['firebase_uid', 'is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn(['firebase_uid', 'phone_number', 'phone_verified_at', 'user_type']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
