<?php

/**
 * Database_Schema.md §9 — point_ledger. The guidelines' `reward_points`.
 *
 * APPEND-ONLY. Rows are never updated or deleted, there is no balance column
 * anywhere, and a balance is always SUM(points_delta). A correction is a new
 * REVERSAL row (System_Plan.md §5.7 rule 7).
 *
 * The two unique constraints are the real safety net: they stop a transaction
 * being credited twice or voided twice, and a redemption being charged twice or
 * refunded twice. MySQL and MariaDB allow repeated NULLs in a unique index, so
 * `(transaction_id, entry_type)` does not interfere with redemption rows, whose
 * transaction_id is NULL, and vice versa.
 *
 * Runs after transactions and redemptions because it references both.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('redemption_id')->nullable()->constrained('redemptions');
            // EARN | REDEEM | REVERSAL | ADJUSTMENT
            $table->string('entry_type', 20);
            // Signed: positive to earn, negative to redeem. A balance may go below
            // zero if a transaction is voided after its points were spent.
            $table->integer('points_delta');
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['transaction_id', 'entry_type']);
            $table->unique(['redemption_id', 'entry_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_ledger');
    }
};
