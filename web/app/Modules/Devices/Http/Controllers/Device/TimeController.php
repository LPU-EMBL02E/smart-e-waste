<?php

namespace App\Modules\Devices\Http\Controllers\Device;

use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/device/time — server time for clock sync. API_Design.md §5.1.
 *
 * The one UNSIGNED device route: it does not pass through
 * VerifyDeviceSignature. The ESP32 has no real-time clock, so it calls this at
 * boot and after any CLOCK_SKEW error, then counts forward from the value it
 * gets. Without it the device could never sign a request the server accepts.
 *
 * Writes nothing, and reads no table — so it also answers while the database is
 * down.
 */
class TimeController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['time' => time()]);
    }
}
