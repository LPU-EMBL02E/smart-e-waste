<?php

/**
 * Database_Schema.md §3 — user_qr_credentials.
 *
 * One row per user, holding the current token. Regenerating overwrites the row,
 * so the old token stops matching immediately and no revocation flag is needed.
 * The token is an opaque random string, never the student number
 * (System_Plan.md §5.5).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_qr_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            // updated_at doubles as the time the token was last regenerated.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_qr_credentials');
    }
};
