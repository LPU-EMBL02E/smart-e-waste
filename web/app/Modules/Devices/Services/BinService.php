<?php

namespace App\Modules\Devices\Services;

use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;

/**
 * Bin telemetry and fill level. API_Design.md §5.3, Database_Schema.md §5.
 *
 * The device reports a raw distance and nothing else. Every judgement about
 * fill level is made here, so recalibrating a bin or changing its threshold
 * never needs a firmware change (System_Plan.md §5.3 rule 5).
 *
 * TODO: all method bodies.
 */
class BinService
{
    /**
     * Record one reading and return the stored row.
     *
     *   1. fill_percent = (empty_distance_mm − distance_mm) / empty_distance_mm
     *      × 100, clamped to 0–100.
     *   2. status = FULL when fill_percent >= devices.fill_threshold_percent,
     *      otherwise OK.
     *   3. insert the bin_telemetry row.
     *   4. dispatch BinFull when this row is FULL and the device's previous row
     *      was not, or there was no previous row (API_Design.md P19). The
     *      listener writes one notification per admin user.
     *
     * Dispatching only on the transition is what stops an alert on every reading
     * while a bin stays full.
     */
    public function recordTelemetry(Device $device, float $distanceMm): BinTelemetry
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * fill_percent for a distance, clamped to 0–100. Pure arithmetic, kept
     * separate so it can be unit-tested without a database.
     */
    public function fillPercentFor(Device $device, float $distanceMm): float
    {
        // TODO: clamp((empty − distance) / empty × 100, 0, 100)
        throw new \LogicException('Not implemented.');
    }

    /**
     * Whether the bin is full, which blocks new sessions.
     *
     * Compares the latest reading's fill_percent against the device's CURRENT
     * threshold, so lowering the threshold takes effect without waiting for a
     * new reading. A device with no reading yet is treated as NOT full
     * (API_Design.md P20).
     */
    public function isFull(Device $device): bool
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * What GET /device/config answers. Weight limits come from the reward rule
     * in effect now and are for the bin's display only — the server still
     * decides whether a weight is accepted, using the rule the session opened
     * under.
     *
     * Answers even when no rule is in effect, with nulls for the two weight
     * limits, so the bin can boot (API_Design.md §5.2).
     */
    public function configFor(Device $device): array
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * Register a bin and issue its first secret. The plain secret is returned
     * once, for the page that shows it to the admin; after that only the
     * encrypted column exists (API_Design.md P39).
     *
     * @return array{device: Device, secret: string}
     */
    public function register(array $attributes): array
    {
        // TODO: DeviceSecret::generate()
        throw new \LogicException('Not implemented.');
    }

    /**
     * Issue a new secret for an existing bin. The old one stops working at once,
     * so the bin cannot talk to the server until the new secret is entered in
     * its setup portal.
     *
     * @return string the new plain secret, shown once
     */
    public function rotateSecret(Device $device): string
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }
}
