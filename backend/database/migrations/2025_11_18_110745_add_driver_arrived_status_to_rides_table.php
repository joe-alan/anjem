<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL requires raw SQL to modify enum types
        DB::statement("ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_status_check");
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('matched', 'accepted', 'driver_arrived', 'in_progress', 'completed', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original status values
        DB::statement("ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_status_check");
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('matched', 'accepted', 'in_progress', 'completed', 'cancelled'))");
    }
};
