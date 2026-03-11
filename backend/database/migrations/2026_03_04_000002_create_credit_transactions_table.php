<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50); // admin_grant | deduction | refund
            $table->integer('amount');  // negative for deductions
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'created_at']);
            $table->index('ride_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
