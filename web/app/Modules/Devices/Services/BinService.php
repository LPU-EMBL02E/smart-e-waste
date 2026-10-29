<?php

namespace App\Modules\Devices\Services;

use App\Modules\Devices\Events\BinFault;
use App\Modules\Devices\Events\BinFull;
use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Support\BinFaultCode;
use App\Modules\Devices\Support\DeviceSecret;
use App\Modules\Rewards\Services\RewardRuleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Bin telemetry, fill level, config and registration. API_Design.md §5.2–§5.3,
 * Database_Schema.md §4–§5.
 *
 * The device reports a raw distance, the panel state and its fault. Every
 * judgement about fill level is made here, so recalibrating a bin or changing
 * its threshold never needs a firmware change (System_Plan.md §5.3 rule 5).
 *
 * The only writer of `devices` and `bin_telemetry` (System_Plan.md §4).
 */
class BinService
{
    public function __construct(private RewardRuleService $rules) {}

    /**
     * Record one reading and return the stored row. API_Design.md §5.3.
     *
     *   1. fill_percent = (empty_distance_mm − distance_mm) / empty_distance_mm
     *      × 100, clamped to 0–100.
     *   2. status = FULL when fill_percent >= devices.fill_threshold_percent,
     *      otherwise OK.
     *   3. insert the bin_telemetry row with panel_open and fault_code.
     *   4. dispatch BinFull when this row is FULL and the device's previous row
     *      was not, or there was no previous row (P19).
     *   5. dispatch BinFault when this row has a fault that differs from the
     *      previous row's, or there was no previous row (P43).
     *
     * Dispatching only on a change is what stops an alert on every reading while
     * a bin stays full or faulted. An open service panel dispatches nothing: it
     * is opened on purpose. The insert is a single row, so there is no
     * transaction and the events go out after it is stored.
     */
    public function recordTelemetry(Device $device, float $distanceMm, bool $panelOpen, ?BinFaultCode $fault): BinTelemetry
    {
        $previous = $this->latestReading($device);

        $fillPercent = $this->fillPercentFor($device, $distanceMm);

        $reading = $device->telemetry()->create([
            'distance_mm' => $distanceMm,
            'fill_percent' => $fillPercent,
            'status' => $fillPercent >= (float) $device->fill_threshold_percent
                ? BinTelemetry::STATUS_FULL
                : BinTelemetry::STATUS_OK,
            'panel_open' => $panelOpen,
            'fault_code' => $fault,
        ]);

        if ($reading->isFull() && ! $previous?->isFull()) {
            BinFull::dispatch($device, $reading);
        }

        if ($fault !== null && $fault !== $previous?->fault_code) {
            BinFault::dispatch($device, $reading);
        }

        return $reading;
    }

    /**
     * fill_percent for a distance, clamped to 0–100 and rounded to the column's
     * two decimal places. Pure arithmetic.
     */
    public function fillPercentFor(Device $device, float $distanceMm): float
    {
        $empty = (float) $device->empty_distance_mm;
        $percent = ($empty - $distanceMm) / $empty * 100;

        return round(max(0.0, min(100.0, $percent)), 2);
    }

    /**
     * Whether the bin is full, which blocks new sessions. API_Design.md §5.4
     * step 3.
     *
     * Compares the latest reading's fill_percent against the device's CURRENT
     * threshold, so lowering the threshold takes effect without waiting for a
     * new reading. A device with no reading yet is treated as NOT full (P20).
     */
    public function isFull(Device $device): bool
    {
        $latest = $this->latestReading($device);

        return $latest !== null
            && (float) $latest->fill_percent >= (float) $device->fill_threshold_percent;
    }

    /**
     * The bin's newest reading, or null before its first one. Its panel state
     * and fault are the bin's current ones (Database_Schema.md §5).
     */
    public function latestReading(Device $device): ?BinTelemetry
    {
        return $device->latestTelemetry()->first();
    }

    /**
     * The admin bin list, each bin with its latest reading eager-loaded.
     *
     * @return LengthAwarePaginator<int, Device>
     */
    public function list(): LengthAwarePaginator
    {
        return Device::with('latestTelemetry')->orderBy('name')->paginate();
    }

    /**
     * A bin's fill history, newest first, on the (device_id, created_at) index.
     *
     * @return LengthAwarePaginator<int, BinTelemetry>
     */
    public function readings(Device $device): LengthAwarePaginator
    {
        return $device->telemetry()->latest()->latest('id')->paginate();
    }

    /**
     * What GET /device/config answers. API_Design.md §5.2, P15.
     *
     * Weight limits come from the reward rule in effect now and are for the
     * bin's display only — the server still decides whether a weight is
     * accepted, using the rule the session opened under. With no rule in effect
     * both limits are null, so the bin can still boot.
     *
     * @return array{session_timeout_s: int, minimum_weight_g: ?float, maximum_weight_g: ?float, fill_threshold_percent: float, calibration: array{weight_offset: ?float, weight_scale_factor: ?float, calibrated_at: ?int}}
     */
    public function configFor(Device $device): array
    {
        $rule = $this->rules->current();
        $calibrated = $device->isCalibrated();

        return [
            'session_timeout_s' => (int) config('ewaste.session_timeout_seconds'),
            'minimum_weight_g' => $rule === null ? null : (float) $rule->minimum_weight_g,
            'maximum_weight_g' => $rule?->maximum_weight_g === null ? null : (float) $rule->maximum_weight_g,
            'fill_threshold_percent' => (float) $device->fill_threshold_percent,
            'calibration' => [
                'weight_offset' => $calibrated ? (float) $device->weight_offset : null,
                'weight_scale_factor' => $calibrated ? (float) $device->weight_scale_factor : null,
                'calibrated_at' => $calibrated ? $device->calibrated_at?->getTimestamp() : null,
            ],
        ];
    }

    /**
     * Save the firmware version a bin reported when opening a session.
     * API_Design.md §5.4 (`fw`), P6. No write when it has not changed.
     */
    public function recordFirmwareVersion(Device $device, string $version): void
    {
        $device->forceFill(['firmware_version' => $version])->save();
    }

    /**
     * Register a bin and issue its first secret. API_Design.md §7.6
     * ("Register or edit a bin"), P39.
     *
     * The plain secret is returned once, for the page that shows it to the
     * admin; after that only the encrypted column exists. Calibration values
     * entered at registration set calibrated_at.
     *
     * @return array{0: Device, 1: string} the bin and its plain secret
     */
    public function register(array $attributes): array
    {
        $secret = DeviceSecret::generate();

        $device = new Device($attributes);
        $device->secret = $secret;
        $this->stampCalibration($device);
        $device->save();

        return [$device, $secret];
    }

    /**
     * Save an admin's changes. API_Design.md §7.6. Saving new calibration
     * values sets calibrated_at; saving the same values again does not.
     */
    public function update(Device $device, array $attributes): Device
    {
        $device->fill($attributes);
        $this->stampCalibration($device);
        $device->save();

        return $device;
    }

    /**
     * Issue a new secret for an existing bin. API_Design.md P39.
     *
     * The old one stops working at once, so the bin cannot talk to the server
     * until the new secret is entered in its setup portal.
     *
     * @return string the new plain secret, shown once
     */
    public function rotateSecret(Device $device): string
    {
        $secret = DeviceSecret::generate();

        $device->secret = $secret;
        $device->save();

        return $secret;
    }

    /**
     * calibrated_at follows the calibration values: now when new values are
     * saved, NULL once they are cleared, untouched when they did not change.
     */
    private function stampCalibration(Device $device): void
    {
        if ($device->isDirty(['weight_offset', 'weight_scale_factor'])) {
            $device->calibrated_at = $device->isCalibrated() ? now() : null;
        }
    }
}
