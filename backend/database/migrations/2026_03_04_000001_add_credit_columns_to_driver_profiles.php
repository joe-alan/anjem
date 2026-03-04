<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->integer('credits_balance')->default(0)->after('queue_cooldown_until');
            $table->integer('credits_total_earned')->default(0)->after('credits_balance');
            $table->integer('credits_total_spent')->default(0)->after('credits_total_earned');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['credits_balance', 'credits_total_earned', 'credits_total_spent']);
        });
    }
};
