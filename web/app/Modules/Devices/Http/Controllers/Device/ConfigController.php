<?php

namespace App\Modules\Devices\Http\Controllers\Device;

use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Services\BinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/device/config — what the bin needs to run. API_Design.md §5.2.
 *
 * Reply shape:
 *   session_timeout_s, minimum_weight_g, maximum_weight_g,
 *   fill_threshold_percent, calibration: { weight_offset,
 *   weight_scale_factor, calibrated_at }
 *
 * Answers 200 even when no reward rule is in effect — the two weight limits are
 * then null — so the bin can boot. Deposits are still refused later at
 * POST /device/sessions with NO_REWARD_RULE.
 *
 * The weight limits are for the bin's display only. The server decides whether
 * a weight is accepted, using the rule the session opened under.
 *
 * The firmware fetches this at boot and with each telemetry send
 * (API_Design.md P16).
 */
class ConfigController
{
    public function __construct(private BinService $bins) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device set by VerifyDeviceSignature */
        $device = $request->attributes->get('device');

        return new JsonResponse($this->bins->configFor($device));
    }
}
