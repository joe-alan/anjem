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
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::table('admin_audit_logs')->whereNull('reason')->update(['reason' => '']);

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->text('reason')->nullable(false)->change();
        });
    }
};
