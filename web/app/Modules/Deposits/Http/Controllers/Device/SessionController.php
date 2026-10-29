<?php

namespace App\Modules\Deposits\Http\Controllers\Device;

use App\Modules\Deposits\Http\Requests\Device\CompleteSessionRequest;
use App\Modules\Deposits\Http\Requests\Device\OpenSessionRequest;
use App\Modules\Deposits\Http\Requests\Device\RecordDepositRequest;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Devices\Models\Device;
use App\Modules\Rewards\Services\PointsService;
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
 * This controller only shapes requests and replies. Every rule, state
 * transition and lookup is in DepositService, so the state machine can be
 * tested without HTTP.
 *
 * A session id that does not exist, or that belongs to ANOTHER device, is 404
 * SESSION_NOT_FOUND — never a plain 404 page, because device routes always
 * answer in JSON (P2, P8). DepositService scopes every lookup to the calling
 * device instead of relying on route-model binding, so one bin can never act
 * on another bin's session.
 *
 * Every call answers per the state table in API_Design.md §4.2, including the
 * replays that return a recorded outcome instead of an error.
 */
class SessionController
{
    public function __construct(
        private DepositService $deposits,
        private PointsService $points,
    ) {}

    /**
     * -> 201 { session_id, expires_in_s, user: { display_name } }
     *
     * A replayed call cancels the session the first one opened and opens a new
     * one for the same user; only one session is ever open per bin (§4.3).
     */
    public function store(OpenSessionRequest $request): JsonResponse
    {
        $session = $this->deposits->openSession(
            $this->device($request),
            $request->validated('qr_token'),
            $request->validated('fw'),
        );

        return new JsonResponse([
            'session_id' => $session->id,
            'expires_in_s' => (int) config('ewaste.session_timeout_seconds'),
            'user' => ['display_name' => $session->user->displayName()],
        ], 201);
    }

    /**
     * -> 200 { accepted: true, points_preview: 18 }
     *
     * points_preview is a preview only. Nothing is credited here. A rejection is
     * an error reply, thrown by the service after the REJECTED state is saved.
     */
    public function deposit(RecordDepositRequest $request, string $sessionId): JsonResponse
    {
        $session = $this->deposits->recordDeposit($this->device($request), $sessionId, $request->validated());

        return new JsonResponse([
            'accepted' => true,
            'points_preview' => $this->deposits->pointsPreview($session),
        ]);
    }

    /**
     * -> 200 { transaction_id, points_awarded, balance }
     *
     * Also 200 when actuator_ok is false, with transaction_id null and
     * points_awarded 0 (P26). Idempotent: a repeat returns the stored result.
     * The balance is read at reply time (P10).
     */
    public function complete(CompleteSessionRequest $request, string $sessionId): JsonResponse
    {
        $session = $this->deposits->complete(
            $this->device($request),
            $sessionId,
            $request->boolean('actuator_ok'),
            $request->validated('cycle_ms'),
        );

        return new JsonResponse([
            'transaction_id' => $session->transaction?->id,
            'points_awarded' => $session->transaction?->points_awarded ?? 0,
            'balance' => $this->points->balanceFor($session->user),
        ]);
    }

    /**
     * -> 200 { status: "CANCELLED" }. No request body (P27).
     */
    public function cancel(Request $request, string $sessionId): JsonResponse
    {
        $session = $this->deposits->cancel($this->device($request), $sessionId);

        return new JsonResponse(['status' => $session->status]);
    }

    /** The calling bin, set by VerifyDeviceSignature. */
    private function device(Request $request): Device
    {
        return $request->attributes->get('device');
    }
}
