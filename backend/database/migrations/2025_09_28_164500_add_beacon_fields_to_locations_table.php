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
        Schema::table('locations', function (Blueprint $table) {
            // Add beacon-specific fields
            $table->integer('beacon_capacity')->default(10)->after('is_beacon');
            $table->integer('current_queue_size')->default(0)->after('beacon_capacity');
            $table->text('description')->nullable()->after('address');
            $table->json('metadata')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['beacon_capacity', 'current_queue_size', 'description', 'metadata']);
        });
    }
};
