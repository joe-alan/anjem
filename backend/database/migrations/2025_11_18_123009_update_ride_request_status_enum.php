<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Drop existing constraint so we can expand the allowed statuses
            DB::statement('ALTER TABLE ride_requests DROP CONSTRAINT IF EXISTS ride_requests_status_check');

            DB::statement("ALTER TABLE ride_requests ADD CONSTRAINT ride_requests_status_check CHECK (status::text = ANY (ARRAY['pending'::character varying, 'matched'::character varying, 'in_progress'::character varying, 'completed'::character varying, 'cancelled'::character varying, 'expired'::character varying]::text[]))");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            DB::statement('ALTER TABLE ride_requests DROP CONSTRAINT IF EXISTS ride_requests_status_check');

            DB::statement("ALTER TABLE ride_requests ADD CONSTRAINT ride_requests_status_check CHECK (status::text = ANY (ARRAY['pending'::character varying, 'matched'::character varying, 'accepted'::character varying, 'cancelled'::character varying, 'expired'::character varying]::text[]))");
        });
    }
};
