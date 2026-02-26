<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->timestamp('rider_cooldown_until')->nullable()->after('expires_at');
            $table->unsignedBigInteger('current_driver_id')->nullable()->after('rider_cooldown_until');

            $table->index('rider_cooldown_until');
            $table->index('current_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropIndex(['rider_cooldown_until']);
            $table->dropIndex(['current_driver_id']);
            $table->dropColumn(['rider_cooldown_until', 'current_driver_id']);
        });
    }
};
