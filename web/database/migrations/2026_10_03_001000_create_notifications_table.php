<?php

/**
 * Database_Schema.md §12 — notifications.
 *
 * This is the project's OWN table, written by event listeners
 * (API_Design.md §7.7). Do NOT run `php artisan notifications:table`: Laravel's
 * built-in notifications table has the same name and different columns
 * (System_Plan.md §8.1).
 *
 * A bin-full alert is one row per admin user.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // DEPOSIT_COMPLETED | REDEMPTION_FULFILLED | BIN_FULL
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);   // unread count on every page
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
