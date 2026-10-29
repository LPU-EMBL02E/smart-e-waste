<?php

namespace App\Modules\Devices\Http\Controllers\Device;

use App\Modules\Devices\Http\Requests\Device\TelemetryRequest;
use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Services\BinService;
use App\Modules\Devices\Support\BinFaultCode;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/device/telemetry — store one distance reading, with the
 * service-panel state and the bin's current fault. API_Design.md §5.3.
 *
 *   -> 201 { "fill_percent": 17.5, "status": "OK" }
 *
 * The computed values are returned so the bin can show its bin-full screen
 * (API_Design.md P18). BinService dispatches BinFull and BinFault only when the
 * state changes, so a bin that stays full or faulted does not alert on every
 * reading.
 *
 * The firmware sends a reading at boot, after each session reaches a final
 * state, whenever the panel state or the fault changes, and on a fixed interval.
 *
 * TODO(team): API_Design.md §10 question 5 — the telemetry interval is not set.
 */
class TelemetryController
{
    public function __construct(private BinService $bins) {}

    public function __invoke(TelemetryRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $reading = $this->bins->recordTelemetry(
            $device,
            (float) $request->validated('distance_mm'),
            $request->boolean('panel_open'),
            $request->enum('fault_code', BinFaultCode::class),
        );

        return new JsonResponse([
            'fill_percent' => (float) $reading->fill_percent,
            'status' => $reading->status,
        ], 201);
    }
}
