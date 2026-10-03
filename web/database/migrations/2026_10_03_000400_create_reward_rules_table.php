<?php

/**
 * Database_Schema.md §7 — reward_rules.
 *
 * Versioned: changing any rule variable sets `effective_to` on the current row
 * and inserts a new one, so past transactions keep the rule they were priced
 * with. The application guarantees rules never overlap; the database cannot.
 *
 * No values are set here. points_per_gram, minimum_weight_g, maximum_weight_g
 * and daily_points_cap all need instructor approval (System_Plan.md §5.7).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('points_per_gram', 10, 4);
            $table->decimal('minimum_weight_g', 10, 2);
            $table->decimal('maximum_weight_g', 10, 2)->nullable();  // NULL = no upper limit
            $table->unsignedInteger('daily_points_cap')->nullable(); // NULL = no cap
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();           // NULL = still in effect
            $table->timestamps();

            $table->index(['effective_from', 'effective_to']);       // "rule in effect now"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_rules');
    }
};
