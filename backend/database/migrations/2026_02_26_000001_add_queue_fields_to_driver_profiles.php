<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->timestamp('queue_joined_at')->nullable()->after('went_online_at');
            $table->decimal('max_pickup_radius_km', 4, 2)->default(5.0)->after('queue_joined_at');
            $table->integer('decline_count')->default(0)->after('max_pickup_radius_km');
            $table->timestamp('decline_window_start')->nullable()->after('decline_count');
            $table->timestamp('queue_cooldown_until')->nullable()->after('decline_window_start');

            $table->index('queue_joined_at');
            $table->index('queue_cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropIndex(['queue_joined_at']);
            $table->dropIndex(['queue_cooldown_until']);
            $table->dropColumn([
                'queue_joined_at',
                'max_pickup_radius_km',
                'decline_count',
                'decline_window_start',
                'queue_cooldown_until',
            ]);
        });
    }
};
