<?php

/**
 * Database_Schema.md §5 — bin_telemetry: the service-panel state and the bin's
 * current fault.
 *
 * The device decides both and reports them with every reading; the server only
 * stores them (API_Design.md §5.3). A bin's current panel state and fault are
 * those of its latest row.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bin_telemetry', function (Blueprint $table) {
            $table->boolean('panel_open')->default(false);  // the service-panel interlock
            $table->string('fault_code', 50)->nullable();   // NULL when the bin has no fault
        });
    }

    public function down(): void
    {
        Schema::table('bin_telemetry', function (Blueprint $table) {
            $table->dropColumn(['panel_open', 'fault_code']);
        });
    }
};
