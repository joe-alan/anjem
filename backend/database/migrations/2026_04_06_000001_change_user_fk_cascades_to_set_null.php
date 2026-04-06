<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change onDelete('cascade') to nullOnDelete() for user/driver FK references
 * on historical tables (rides, ratings, audit logs, etc.).
 *
 * Soft deletes don't trigger FK cascades, so this is a safety net —
 * if a user row is ever physically deleted, we preserve historical records
 * instead of cascade-deleting rides, ratings, and audit trails.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop all old FK constraints
        Schema::table('ride_requests', fn (Blueprint $t) => $t->dropForeign(['rider_id']));
        Schema::table('rides', fn (Blueprint $t) => $t->dropForeign(['rider_id']));
        Schema::table('rides', fn (Blueprint $t) => $t->dropForeign(['driver_id']));
        Schema::table('ratings', fn (Blueprint $t) => $t->dropForeign(['rater_id']));
        Schema::table('ratings', fn (Blueprint $t) => $t->dropForeign(['rated_id']));
        Schema::table('admin_audit_logs', fn (Blueprint $t) => $t->dropForeign(['admin_id']));
        Schema::table('credit_transactions', fn (Blueprint $t) => $t->dropForeign(['driver_id']));
        Schema::table('dispatch_attempts', fn (Blueprint $t) => $t->dropForeign(['driver_id']));

        // Step 2: Make columns nullable
        Schema::table('ride_requests', fn (Blueprint $t) => $t->unsignedBigInteger('rider_id')->nullable()->change());
        Schema::table('rides', fn (Blueprint $t) => $t->unsignedBigInteger('rider_id')->nullable()->change());
        Schema::table('rides', fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable()->change());
        Schema::table('ratings', fn (Blueprint $t) => $t->unsignedBigInteger('rater_id')->nullable()->change());
        Schema::table('ratings', fn (Blueprint $t) => $t->unsignedBigInteger('rated_id')->nullable()->change());
        Schema::table('admin_audit_logs', fn (Blueprint $t) => $t->unsignedBigInteger('admin_id')->nullable()->change());
        Schema::table('credit_transactions', fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable()->change());
        Schema::table('dispatch_attempts', fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable()->change());

        // Step 3: Clean up orphaned references from old test data
        DB::statement('UPDATE rides SET driver_id = NULL WHERE driver_id NOT IN (SELECT id FROM driver_profiles)');
        DB::statement('UPDATE rides SET rider_id = NULL WHERE rider_id NOT IN (SELECT id FROM users)');
        DB::statement('UPDATE ride_requests SET rider_id = NULL WHERE rider_id NOT IN (SELECT id FROM users)');
        DB::statement('UPDATE ratings SET rater_id = NULL WHERE rater_id NOT IN (SELECT id FROM users)');
        DB::statement('UPDATE ratings SET rated_id = NULL WHERE rated_id NOT IN (SELECT id FROM users)');
        DB::statement('UPDATE admin_audit_logs SET admin_id = NULL WHERE admin_id NOT IN (SELECT id FROM users)');
        DB::statement('UPDATE credit_transactions SET driver_id = NULL WHERE driver_id NOT IN (SELECT id FROM users)');
        DB::statement('UPDATE dispatch_attempts SET driver_id = NULL WHERE driver_id NOT IN (SELECT id FROM users)');

        // Step 4: Re-add FK constraints with nullOnDelete
        Schema::table('ride_requests', fn (Blueprint $t) => $t->foreign('rider_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('rides', fn (Blueprint $t) => $t->foreign('rider_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('rides', fn (Blueprint $t) => $t->foreign('driver_id')->references('id')->on('driver_profiles')->nullOnDelete());
        Schema::table('ratings', fn (Blueprint $t) => $t->foreign('rater_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('ratings', fn (Blueprint $t) => $t->foreign('rated_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('admin_audit_logs', fn (Blueprint $t) => $t->foreign('admin_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('credit_transactions', fn (Blueprint $t) => $t->foreign('driver_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('dispatch_attempts', fn (Blueprint $t) => $t->foreign('driver_id')->references('id')->on('users')->nullOnDelete());
    }

    public function down(): void
    {
        // Drop nullOnDelete FKs
        Schema::table('ride_requests', fn (Blueprint $t) => $t->dropForeign(['rider_id']));
        Schema::table('rides', fn (Blueprint $t) => $t->dropForeign(['rider_id']));
        Schema::table('rides', fn (Blueprint $t) => $t->dropForeign(['driver_id']));
        Schema::table('ratings', fn (Blueprint $t) => $t->dropForeign(['rater_id']));
        Schema::table('ratings', fn (Blueprint $t) => $t->dropForeign(['rated_id']));
        Schema::table('admin_audit_logs', fn (Blueprint $t) => $t->dropForeign(['admin_id']));
        Schema::table('credit_transactions', fn (Blueprint $t) => $t->dropForeign(['driver_id']));
        Schema::table('dispatch_attempts', fn (Blueprint $t) => $t->dropForeign(['driver_id']));

        // Restore non-nullable + cascade
        Schema::table('ride_requests', fn (Blueprint $t) => $t->unsignedBigInteger('rider_id')->nullable(false)->change());
        Schema::table('rides', fn (Blueprint $t) => $t->unsignedBigInteger('rider_id')->nullable(false)->change());
        Schema::table('rides', fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable(false)->change());
        Schema::table('ratings', fn (Blueprint $t) => $t->unsignedBigInteger('rater_id')->nullable(false)->change());
        Schema::table('ratings', fn (Blueprint $t) => $t->unsignedBigInteger('rated_id')->nullable(false)->change());
        Schema::table('admin_audit_logs', fn (Blueprint $t) => $t->unsignedBigInteger('admin_id')->nullable(false)->change());
        Schema::table('credit_transactions', fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable(false)->change());
        Schema::table('dispatch_attempts', fn (Blueprint $t) => $t->unsignedBigInteger('driver_id')->nullable(false)->change());

        Schema::table('ride_requests', fn (Blueprint $t) => $t->foreign('rider_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('rides', fn (Blueprint $t) => $t->foreign('rider_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('rides', fn (Blueprint $t) => $t->foreign('driver_id')->references('id')->on('driver_profiles')->cascadeOnDelete());
        Schema::table('ratings', fn (Blueprint $t) => $t->foreign('rater_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('ratings', fn (Blueprint $t) => $t->foreign('rated_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('admin_audit_logs', fn (Blueprint $t) => $t->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('credit_transactions', fn (Blueprint $t) => $t->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('dispatch_attempts', fn (Blueprint $t) => $t->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete());
    }
};
