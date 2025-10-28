<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing status enum and recreate with expired
        DB::statement('ALTER TABLE ride_requests DROP CONSTRAINT ride_requests_status_check');
        DB::statement("ALTER TABLE ride_requests ADD CONSTRAINT ride_requests_status_check CHECK (status::text = ANY (ARRAY['pending'::character varying, 'matched'::character varying, 'accepted'::character varying, 'cancelled'::character varying, 'expired'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original constraint
        DB::statement('ALTER TABLE ride_requests DROP CONSTRAINT ride_requests_status_check');
        DB::statement("ALTER TABLE ride_requests ADD CONSTRAINT ride_requests_status_check CHECK (status::text = ANY (ARRAY['pending'::character varying, 'matched'::character varying, 'accepted'::character varying, 'cancelled'::character varying]::text[]))");
    }
};
