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
        // Drop the old table and recreate with correct schema
        Schema::dropIfExists('driver_queue');

        Schema::create('driver_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('beacon_id')->constrained('locations')->onDelete('cascade');
            $table->integer('position')->default(1);
            $table->enum('status', ['waiting', 'called', 'served', 'left'])->default('waiting');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['beacon_id', 'status', 'position']);
            $table->index(['driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_queue');

        // Recreate the original table structure
        Schema::create('driver_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('driver_profiles')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->integer('queue_position');
            $table->decimal('score', 8, 2);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }
};