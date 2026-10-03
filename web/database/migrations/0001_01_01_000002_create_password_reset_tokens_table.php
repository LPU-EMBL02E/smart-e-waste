<?php

/**
 * Laravel's own password reset table, kept because this project replaces the
 * framework's create_users_table migration (which normally defines it).
 *
 * It holds the links for both emails the system sends: "set your password" for a
 * new account and "forgot password" (System_Plan.md §7.2). Columns match
 * Laravel's defaults, which its password broker expects.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
