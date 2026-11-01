<?php

/**
 * Database_Schema.md §2 — users: when the email was confirmed.
 *
 * NULL until a self-registered student follows the confirmation link. An account
 * an admin creates gets the time of creation instead (API_Design.md P47).
 *
 * No index: nothing filters on this column, it is only read from the row of the
 * user already loaded.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
