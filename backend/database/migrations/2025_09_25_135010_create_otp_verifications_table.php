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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('otp_code', 6);
            $table->enum('device_type', ['rider', 'driver']);
            $table->string('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'otp_code', 'expires_at']);
            $table->index(['ip_address', 'created_at']); // For rate limiting
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
