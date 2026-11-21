<?php

/**
 * Database_Schema.md §2 — users: when the account was first confirmed, and the
 * index for the sweep that deletes accounts never confirmed (API_Design.md P54).
 *
 * email_verified_at is cleared when a user changes their email (P50), so it
 * can't tell a new account from one confirmed before. This column is set once
 * and never cleared. Every account confirmed today gets its email_verified_at;
 * an account that is unconfirmed today after an email change can't be told
 * apart and stays NULL, but the sweep keeps any account with points, deposits,
 * redemptions or notifications.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('first_confirmed_at')->nullable()->after('email_verified_at');
            $table->index(['first_confirmed_at', 'created_at']);
        });

        // A copy from one column to another: plain SQL on MySQL, MariaDB and SQLite.
        DB::table('users')
            ->whereNotNull('email_verified_at')
            ->update(['first_confirmed_at' => DB::raw('email_verified_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['first_confirmed_at', 'created_at']);
            $table->dropColumn('first_confirmed_at');
        });
    }
};
