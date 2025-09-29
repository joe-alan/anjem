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
        Schema::create('driver_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->onDelete('cascade');
            $table->timestamp('went_online_at');
            $table->timestamp('went_offline_at')->nullable();
            $table->integer('total_minutes_online')->nullable();
            $table->integer('rides_completed')->default(0);
            $table->decimal('earnings_rp', 10, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_sessions');
    }
};
