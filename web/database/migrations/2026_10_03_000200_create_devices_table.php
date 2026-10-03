<?php

/**
 * Database_Schema.md §4 — devices. One physical unit: the bin and its NodeMCU.
 *
 * `secret` is stored with Laravel's `encrypted` cast, so it is encrypted with
 * APP_KEY. A host with a different key cannot read it and every bin must be
 * issued a new secret (System_Plan.md §8).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_code', 50)->unique();   // sent as X-Device-Code
            $table->string('name', 100);
            $table->string('location', 255)->nullable();
            $table->text('secret');                        // encrypted cast on the model
            $table->string('firmware_version', 30)->nullable();
            $table->string('status', 20)->default('active'); // active | inactive | retired
            $table->timestamp('last_seen_at')->nullable();
            // Ultrasonic reading with the bin empty; the fill formula divides by it.
            $table->decimal('empty_distance_mm', 10, 2);
            $table->decimal('fill_threshold_percent', 5, 2)->default(90.00);
            // Load cell calibration, entered by an admin and served by GET /device/config.
            $table->decimal('weight_offset', 12, 4)->nullable();
            $table->decimal('weight_scale_factor', 12, 6)->nullable();
            $table->timestamp('calibrated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
