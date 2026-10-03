<?php

/**
 * Database_Schema.md §10 — rewards.
 *
 * Rewards are switched off with is_active, never deleted (API_Design.md P33).
 * Stock is decremented with a guarded `WHERE stock_quantity >= ?` so it can
 * never go negative (System_Plan.md §6.1).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('points_cost');
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
