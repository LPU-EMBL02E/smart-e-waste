<?php

/**
 * Database_Schema.md §11 — redemptions.
 *
 * points_cost is the TOTAL cost at the time of redemption, so changing a
 * reward's price never affects past redemptions.
 *
 * An admin can cancel a redemption while it is PENDING: the stock is returned
 * and a REVERSAL ledger row gives the points back. A FULFILLED redemption
 * cannot be cancelled (System_Plan.md §6.1).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('redemption_code', 50)->unique();     // RDM-YYYYMMDD-XXXXXX
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('reward_id')->constrained('rewards');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('points_cost');              // total at redemption time
            $table->string('status', 20)->default('PENDING');    // PENDING | FULFILLED | CANCELLED
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();   // created_at = when the reward was redeemed
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
