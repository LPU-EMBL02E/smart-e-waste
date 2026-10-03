<?php

namespace App\Modules\Deposits\Http\Controllers\Device;

use App\Modules\Deposits\Http\Requests\Device\CompleteSessionRequest;
use App\Modules\Deposits\Http\Requests\Device\OpenSessionRequest;
use App\Modules\Deposits\Http\Requests\Device\RecordDepositRequest;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Devices\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The four session routes the bin calls. API_Design.md §5.4–§5.7.
 *
 *   POST /device/sessions                  open from a QR token
 *   POST /device/sessions/{id}/deposit     weight and verification result
 *   POST /device/sessions/{id}/complete    actuator result; credits points
 *   POST /device/sessions/{id}/cancel      cancel an open session
 *
 * This controller only shapes requests and replies. Every rule and state
 * transition is in DepositService, so the state machine can be tested without
 * HTTP.
 *
 * A session id that does not exist, or that belongs to ANOTHER device, is 404
 * SESSION_NOT_FOUND — never a plain 404 page, because device routes always
 * answer in JSON (P2, P8). Resolve the session with the calling device in the
 * lookup rather than relying on route-model binding, or one bin could act on
 * another bin's session.
 *
 * TODO: all method bodies. The state table in API_Design.md §4.2 says exactly
 * what each call must answer in each of the seven states, including the replay
 * cases that return a recorded outcome instead of an error.
 */
class SessionController
{
    public function __construct(private DepositService $deposits)
    {
    }

    /**
     * -> 201 { session_id, expires_in_s, user: { display_name } }
     *
     * A replayed call cancels the session the first one opened and opens a new
     * one for the same user; only one session is ever open per bin (§4.3).
     */
    public function store(OpenSessionRequest $request): JsonResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * -> 200 { accepted: true, points_preview: 18 }
     *
     * points_preview is a preview only. Nothing is credited here.
     */
    public function deposit(RecordDepositRequest $request, string $sessionId): JsonResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * -> 200 { transaction_id, points_awarded, balance }
     *
     * Also 200 when actuator_ok is false, with transaction_id null and
     * points_awarded 0 (P26). Idempotent: a repeat returns the stored result.
     */
    public function complete(CompleteSessionRequest $request, string $sessionId): JsonResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * -> 200 { status: "CANCELLED" }. No request body (P27).
     */
    public function cancel(Request $request, string $sessionId): JsonResponse
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * The session for this request, or 404 SESSION_NOT_FOUND. Scoped to the
     * calling device, so a bin can never act on another bin's session.
     */
    private function resolveSession(Request $request, string $sessionId): mixed
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        // TODO: DepositSession::where('id',...)->where('device_id',$device->id)->first()
        //       ?? throw DeviceApiException::of(DeviceErrorCode::SessionNotFound)
        throw new \LogicException('Not implemented.');
    }
}
