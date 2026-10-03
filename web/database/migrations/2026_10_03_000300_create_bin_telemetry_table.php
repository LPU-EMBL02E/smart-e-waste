<?php

/**
 * Database_Schema.md §5 — bin_telemetry.
 *
 * The device reports `distance_mm` only; the server computes `fill_percent` and
 * `status` on insert (API_Design.md §5.3).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bin_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->decimal('distance_mm', 10, 2);
            $table->decimal('fill_percent', 5, 2);         // computed by the server
            $table->string('status', 20);                  // OK | FULL
            $table->timestamp('created_at')->nullable();    // when the reading was taken

            // Schema §Additional Indexes: latest reading per bin, and fill history.
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_telemetry');
    }
};
