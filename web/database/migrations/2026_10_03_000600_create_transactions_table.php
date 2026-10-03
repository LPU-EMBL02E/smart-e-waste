<?php

/**
 * Database_Schema.md §8 — transactions.
 *
 * The UNIQUE session_id is what makes the device's `complete` call idempotent: a
 * session can produce only one transaction, so a retry finds the existing row
 * and gets the original response (System_Plan.md §5.3 rule 3).
 *
 * points_awarded = floor(net_weight_g × points_per_gram), computed by the server
 * from the rule that was in effect when the session opened. The device never
 * sends points.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 50)->unique();   // TXN-YYYYMMDD-XXXXXX
            $table->foreignUuid('session_id')->unique()->constrained('deposit_sessions');
            $table->foreignId('reward_rule_id')->constrained('reward_rules');
            $table->unsignedInteger('points_awarded')->default(0);
            $table->string('status', 20)->default('COMPLETED'); // COMPLETED | VOIDED
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();   // created_at = when the deposit completed
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
