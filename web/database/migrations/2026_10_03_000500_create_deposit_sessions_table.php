<?php

/**
 * Database_Schema.md §6 — deposit_sessions. The guidelines' `ewaste_records`.
 *
 * The primary key is a UUID (CHAR(36)) because the device receives it and sends
 * it back in the deposit, complete and cancel calls.
 *
 * Status machine (API_Design.md §4.1):
 *   OPEN -> ACCEPTED -> COMPLETED
 *   OPEN -> REJECTED | CANCELLED | EXPIRED
 *   ACCEPTED -> FAILED
 * Only OPEN sessions expire; an ACCEPTED session may already have the item in
 * the bin, so it waits for `complete` and is listed for admin review instead.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('device_id')->constrained('devices');
            $table->string('status', 20);
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();      // set on every final state
            $table->string('failure_code', 50)->nullable();  // error code, or ACTUATOR_FAILED

            // Reported by POST .../deposit; stored whether accepted or rejected.
            $table->decimal('net_weight_g', 10, 2)->nullable();
            $table->unsignedInteger('sample_count')->nullable();
            $table->boolean('is_weight_stable')->nullable();
            $table->string('verification_method', 50)->nullable();
            $table->boolean('verification_passed')->nullable();
            $table->decimal('verification_score', 5, 4)->nullable();

            // Reported by POST .../complete.
            $table->boolean('actuator_ok')->nullable();
            $table->unsignedInteger('actuator_cycle_ms')->nullable();

            $table->timestamps();   // created_at = when the session opened

            // Schema §Additional Indexes: the timeout sweep and the active-session check.
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_sessions');
    }
};
