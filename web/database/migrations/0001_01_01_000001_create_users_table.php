<?php

/**
 * Database_Schema.md §2 — users.
 *
 * This file, together with the password_reset_tokens and sessions migrations
 * beside it, REPLACES Laravel's shipped 0001_01_01_000000_create_users_table.php,
 * which is not in this repository. Laravel's cache and jobs migrations are kept
 * as they are.
 *
 * Differences from Laravel's default users table, all from the schema:
 *   - first_name / last_name instead of a single name column
 *   - password is NULLABLE: it stays NULL until the student sets one from the
 *     emailed link, and login fails while it is NULL (System_Plan.md §4)
 *   - no email_verified_at or remember_token; neither is used by this design
 *   - role, status, organization_id, student_number, last_login_at added
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // NULL for admins; required for students, validated in the application.
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->string('role', 20)->default('student');      // student | admin
            $table->string('student_number', 50)->nullable()->unique();
            $table->string('email', 255)->unique();
            $table->string('password', 255)->nullable();          // NULL = not activated
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('status', 20)->default('active');      // active | inactive
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
