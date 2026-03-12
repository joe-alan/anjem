<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('rider_cancel_count')->default(0)->after('total_rides_taken');
            $table->timestamp('rider_cancel_cooldown_until')->nullable()->after('rider_cancel_count');
            $table->string('suspension_reason')->nullable()->after('rider_cancel_cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rider_cancel_count', 'rider_cancel_cooldown_until', 'suspension_reason']);
        });
    }
};
