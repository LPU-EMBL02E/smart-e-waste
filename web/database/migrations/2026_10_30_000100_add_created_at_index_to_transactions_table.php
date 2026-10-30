<?php

/**
 * transactions (created_at): "today" on the admin dashboard.
 *
 * The live summary counts the COMPLETED transactions created since local
 * midnight (API_Design.md §7.5) and is polled every
 * config('ewaste.dashboard_poll_seconds') seconds by every open dashboard, so
 * it reads a range of this index instead of scanning the table on the Pi
 * (AGENTS.md §5.1). Added in Track D step D4.
 *
 * TODO: add this index to Database_Schema.md "Additional Indexes", in its own
 * docs PR (AGENTS.md §1).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
