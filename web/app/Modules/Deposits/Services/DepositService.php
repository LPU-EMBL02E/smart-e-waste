<?php

namespace App\Modules\Deposits\Services;

use App\Modules\Deposits\Events\DepositCompleted;
use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Support\FailureCode;
use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Services\BinService;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserQrCredential;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Rewards\Models\RewardRule;
use App\Modules\Rewards\Services\PointsService;
use App\Support\Device\DeviceApiException;
use App\Support\Device\DeviceErrorCode;
use App\Support\ReferenceCode;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The deposit state machine. API_Design.md §4 and §5.4–§5.7.
 *
 * Everything the bin is allowed to do to a session goes through here. The
 * sequence is fixed and the server decides every step: the device reports grams
 * and a pass/fail, and never computes points or decides whether a bin is full
 * (System_Plan.md §5.7 rule 3).
 *
 * Writes to point_ledger always go through PointsService, inside this service's
 * own database transaction (System_Plan.md §4, boundary rule 1).
 *
 * Three rules shape every method (API_Design.md §4.1, P24):
 *   - Lock first. A status change locks its row with lockForUpdate() as the
 *     first read of its transaction, so it never acts on a stale snapshot.
 *   - Write first, throw after. A rejection or an expiry is a write that must
 *     stay, so the transaction returns the error and it is thrown only once the
 *     transaction has committed.
 *   - Events go out after the commit.
 *
 * TODO: void() (admin transaction voids; not the deposit path).
 */
class DepositService
{
    public function __construct(
        private PointsService $points,
        private BinService $bins,
        private AccountService $accounts,
    ) {}

    /**
     * POST /device/sessions — open a session from a scanned QR token.
     * API_Design.md §5.4 and §4.3.
     *
     * Step 1 is a plain lookup. Then any OPEN session of this device or user
     * past expires_at is expired (P12). Steps 2–6 and the writes run in one
     * transaction that locks the device row, then the user row, always in that
     * order, so two scans on one bin or by one user are handled one after the
     * other:
     *
     *   1. the token matches a user_qr_credentials row     INVALID_QR
     *   2. that user's status is active                    USER_INACTIVE
     *   3. the bin is not full                             BIN_FULL
     *   4. a reward rule is in effect now                  NO_REWARD_RULE
     *   5. the user is under the rule's daily_points_cap   DAILY_LIMIT_REACHED
     *   6. the user has no unexpired OPEN session on
     *      ANOTHER device                                  DUPLICATE_TRANSACTION
     *
     * The first failure ends the request before anything is written. On
     * success: this device's earlier OPEN session is CANCELLED (ACCEPTED ones are
     * left alone), the new session is inserted, and the QR credential's
     * last_used_at and the bin's firmware version are saved.
     */
    public function openSession(Device $device, string $qrToken, ?string $firmwareVersion): DepositSession
    {
        $credential = UserQrCredential::where('token', $qrToken)->first();

        $this->expireOpenSessionsWhere(function (Builder $query) use ($device, $credential) {
            $query->where('device_id', $device->id)
                ->when($credential, fn (Builder $q) => $q->orWhere('user_id', $credential->user_id));
        });

        if ($credential === null) {
            throw DeviceApiException::of(DeviceErrorCode::InvalidQr);
        }

        return $this->throwIfError(DB::transaction(function () use ($device, $credential, $firmwareVersion) {
            $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($credential->user_id)->lockForUpdate()->firstOrFail();

            if (! $user->isActive()) {
                return DeviceApiException::of(DeviceErrorCode::UserInactive);
            }

            if ($this->bins->isFull($device)) {
                return DeviceApiException::of(DeviceErrorCode::BinFull);
            }

            $rule = $this->points->ruleInEffectAt(now());

            if ($rule === null) {
                return DeviceApiException::of(DeviceErrorCode::NoRewardRule);
            }

            if (! $this->points->isUnderDailyCap($user, $rule)) {
                return DeviceApiException::of(DeviceErrorCode::DailyLimitReached);
            }

            $openElsewhere = DepositSession::where('user_id', $user->id)
                ->where('device_id', '!=', $device->id)
                ->where('status', SessionStatus::Open)
                ->where('expires_at', '>', now())
                ->exists();

            if ($openElsewhere) {
                return DeviceApiException::of(DeviceErrorCode::DuplicateTransaction);
            }

            DepositSession::where('device_id', $device->id)
                ->where('status', SessionStatus::Open)
                ->update(['status' => SessionStatus::Cancelled, 'closed_at' => now()]);

            $session = DepositSession::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'status' => SessionStatus::Open,
                'expires_at' => now()->addSeconds(config('ewaste.session_timeout_seconds')),
            ]);

            $this->accounts->markQrCredentialUsed($credential);

            if ($firmwareVersion !== null) {
                $this->bins->recordFirmwareVersion($device, $firmwareVersion);
            }

            return $session->setRelation('user', $user);
        }));
    }

    /**
     * POST /device/sessions/{id}/deposit — record the weight and the
     * verification result, and decide whether the item is accepted.
     * API_Design.md §5.5.
     *
     * The actuator must not move until this returns accepted.
     *
     * All six reported values are stored on the session whether it is accepted
     * or rejected, because they are the evidence for the attempt (P22).
     *
     * Checks in order, against the rule the session OPENED under:
     *   1. verification.passed is true        VERIFICATION_FAILED
     *   2. weight_stable is true              WEIGHT_UNSTABLE
     *   3. weight_g above 0                   NO_ITEM
     *   4. weight_g >= minimum_weight_g       ZERO_WEIGHT
     *   5. maximum_weight_g null, or
     *      weight_g <= it                     OVER_MAX_WEIGHT
     *
     * On success: status ACCEPTED. On failure: status REJECTED, failure_code =
     * the code, closed_at = now, committed before the error is thrown.
     *
     * Other states answer as API_Design.md §4.2: ACCEPTED returns the recorded
     * result, REJECTED repeats its recorded code, the rest are
     * INVALID_SESSION_STATE.
     *
     * @param  array{weight_g: float|int|string, weight_stable: bool, samples: int, verification: array{method: string, passed: bool, score?: float|int|string|null}}  $payload
     */
    public function recordDeposit(Device $device, string $sessionId, array $payload): DepositSession
    {
        return $this->withLockedSession($device, $sessionId, function (DepositSession $session) use ($payload) {
            return match ($session->status) {
                SessionStatus::Open => $this->judgeDeposit($session, $payload),
                SessionStatus::Accepted => $session,
                SessionStatus::Rejected => DeviceApiException::of(DeviceErrorCode::from($session->failure_code)),
                default => DeviceApiException::of(DeviceErrorCode::InvalidSessionState),
            };
        });
    }

    /**
     * The points an ACCEPTED session will earn: floor(net_weight_g ×
     * points_per_gram) under the rule it opened with. A preview for the bin's
     * screen; nothing is credited (API_Design.md §5.5).
     */
    public function pointsPreview(DepositSession $session): int
    {
        return $this->ruleFor($session)->pointsFor((float) $session->net_weight_g);
    }

    /**
     * POST /device/sessions/{id}/complete — the actuator result. This is the
     * call that credits points, and the only one that does.
     * API_Design.md §5.6.
     *
     * When actuator_ok is true, in one database transaction:
     *   1. lock the session row and confirm it is still ACCEPTED;
     *   2. insert the transaction: ReferenceCode::transaction(), session_id,
     *      reward_rule_id = the rule the session opened under, points_awarded =
     *      floor(net_weight_g × points_per_gram), status COMPLETED;
     *   3. PointsService::creditDeposit() writes the EARN row — this service
     *      never inserts into point_ledger itself;
     *   4. session becomes COMPLETED with actuator_ok, actuator_cycle_ms and
     *      closed_at.
     * DepositCompleted is dispatched AFTER the commit.
     *
     * When actuator_ok is false: the session becomes FAILED with failure_code
     * ACTUATOR_FAILED, and no transaction and no ledger row are written (P26).
     *
     * Idempotent: a COMPLETED or FAILED session returns its recorded result,
     * never an error, and the unique transactions.session_id backs that up.
     * `complete` has no deadline — an ACCEPTED session accepts it whenever it
     * arrives (System_Plan.md §5.3 rule 6).
     *
     * Returns the session with its `user` and `transaction` relations set: the
     * new or stored transaction, or null for a FAILED session.
     */
    public function complete(Device $device, string $sessionId, bool $actuatorOk, ?int $cycleMs): DepositSession
    {
        $credited = null;

        $session = $this->withLockedSession(
            $device,
            $sessionId,
            function (DepositSession $session) use ($actuatorOk, $cycleMs, &$credited) {
                return match ($session->status) {
                    SessionStatus::Accepted => $actuatorOk
                        ? $this->credit($session, $cycleMs, $credited)
                        : $this->failActuator($session, $cycleMs),
                    SessionStatus::Completed => $session->load('transaction'),
                    SessionStatus::Failed => $session->setRelation('transaction', null),
                    default => DeviceApiException::of(DeviceErrorCode::InvalidSessionState),
                };
            },
        );

        if ($credited !== null) {
            DepositCompleted::dispatch($credited);
        }

        // The reply carries the user's balance (API_Design.md §5.6).
        return $session->loadMissing('user');
    }

    /**
     * POST /device/sessions/{id}/cancel — the bin's Cancel button.
     * API_Design.md §5.7.
     *
     * Only an OPEN session can be cancelled; it becomes CANCELLED with closed_at
     * set. A repeat on a CANCELLED session returns it unchanged (§4.2).
     */
    public function cancel(Device $device, string $sessionId): DepositSession
    {
        return $this->withLockedSession($device, $sessionId, function (DepositSession $session) {
            return match ($session->status) {
                SessionStatus::Open => $this->close($session, SessionStatus::Cancelled),
                SessionStatus::Cancelled => $session,
                default => DeviceApiException::of(DeviceErrorCode::InvalidSessionState),
            };
        });
    }

    /**
     * Void a completed transaction, from /admin/transactions/{t}/void.
     *
     * One database transaction: set status VOIDED, void_reason, voided_by and
     * voided_at, then PointsService::reverseTransaction() appends the REVERSAL
     * row. Nothing is edited or deleted, and the balance may go below zero.
     *
     * Only a COMPLETED transaction can be voided. A voided deposit is excluded
     * from total collected weight and from the leaderboards, but still counts
     * toward its day's points cap (System_Plan.md §5.7 rule 5).
     */
    public function void(Transaction $transaction, User $admin, string $reason): Transaction
    {
        // TODO
        throw new \LogicException('Not implemented.');
    }

    /**
     * Move OPEN sessions past expires_at to EXPIRED. Run by the scheduler's
     * timeout sweep every minute (routes/console.php).
     *
     * A conditional update, `WHERE status = 'OPEN' AND expires_at <= now`, so it
     * never overwrites a session another request has just moved on
     * (API_Design.md §4.1). ACCEPTED sessions are never touched: the item may
     * already be in the bin, so they wait for `complete` and appear on
     * /admin/sessions/review instead (System_Plan.md §5.3 rule 6).
     *
     * @return int how many sessions were expired
     */
    public function expireOpenSessions(): int
    {
        return $this->expireOpenSessionsWhere(null);
    }

    // ------------------------------------------------------------------ steps

    /**
     * The five deposit checks of API_Design.md §5.5, on an OPEN session.
     */
    private function judgeDeposit(DepositSession $session, array $payload): DepositSession|DeviceApiException
    {
        $weightG = (float) $payload['weight_g'];

        $session->forceFill([
            'net_weight_g' => $weightG,
            'is_weight_stable' => $payload['weight_stable'],
            'sample_count' => $payload['samples'],
            'verification_method' => $payload['verification']['method'],
            'verification_passed' => $payload['verification']['passed'],
            'verification_score' => $payload['verification']['score'] ?? null,
        ]);

        $rule = $this->ruleFor($session);

        $failure = match (true) {
            ! $payload['verification']['passed'] => DeviceErrorCode::VerificationFailed,
            ! $payload['weight_stable'] => DeviceErrorCode::WeightUnstable,
            $weightG <= 0 => DeviceErrorCode::NoItem,
            $weightG < (float) $rule->minimum_weight_g => DeviceErrorCode::ZeroWeight,
            ! $rule->acceptsWeight($weightG) => DeviceErrorCode::OverMaxWeight,
            default => null,
        };

        if ($failure === null) {
            $session->status = SessionStatus::Accepted;
            $session->save();

            return $session;
        }

        $session->failure_code = $failure->value;
        $this->close($session, SessionStatus::Rejected);

        return DeviceApiException::of($failure);
    }

    /**
     * actuator_ok true on an ACCEPTED session: the transaction, the EARN row and
     * COMPLETED, all inside the caller's transaction. $credited carries the new
     * transaction out so DepositCompleted is dispatched after the commit.
     */
    private function credit(DepositSession $session, ?int $cycleMs, ?Transaction &$credited): DepositSession
    {
        $rule = $this->ruleFor($session);

        // A transaction code is random; on the rare duplicate, draw another
        // (ReferenceCode). session_id cannot clash: the session is locked.
        $transaction = retry(3, fn () => Transaction::create([
            'transaction_code' => ReferenceCode::transaction(),
            'session_id' => $session->id,
            'reward_rule_id' => $rule->id,
            'points_awarded' => $rule->pointsFor((float) $session->net_weight_g),
            'status' => Transaction::STATUS_COMPLETED,
        ]), when: fn (\Throwable $e) => $e instanceof UniqueConstraintViolationException);

        $transaction->setRelation('session', $session);
        $this->points->creditDeposit($transaction);

        $session->forceFill(['actuator_ok' => true, 'actuator_cycle_ms' => $cycleMs]);
        $this->close($session, SessionStatus::Completed);

        $credited = $transaction;

        return $session->setRelation('transaction', $transaction);
    }

    /** actuator_ok false: FAILED, no transaction and no points (P26). */
    private function failActuator(DepositSession $session, ?int $cycleMs): DepositSession
    {
        $session->forceFill([
            'actuator_ok' => false,
            'actuator_cycle_ms' => $cycleMs,
            'failure_code' => FailureCode::ACTUATOR_FAILED,
        ]);
        $this->close($session, SessionStatus::Failed);

        return $session->setRelation('transaction', null);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Runs $step on the session inside one database transaction, with the
     * session locked as the first read and scoped to the calling device.
     *
     * Before $step: a session that is missing or another device's is
     * SESSION_NOT_FOUND (P8); an OPEN session past expires_at is moved to EXPIRED
     * and answers SESSION_EXPIRED (P12); an EXPIRED one answers SESSION_EXPIRED.
     *
     * $step returns the session, or the error to throw. Errors are thrown only
     * after the commit, so an expiry or rejection that was written stays.
     *
     * @param  Closure(DepositSession): (DepositSession|DeviceApiException)  $step
     */
    private function withLockedSession(Device $device, string $sessionId, Closure $step): DepositSession
    {
        return $this->throwIfError(DB::transaction(function () use ($device, $sessionId, $step) {
            $session = DepositSession::whereKey($sessionId)
                ->where('device_id', $device->id)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return DeviceApiException::of(DeviceErrorCode::SessionNotFound);
            }

            if ($session->status->canExpire() && $session->hasExpired()) {
                $this->close($session, SessionStatus::Expired);
            }

            if ($session->status === SessionStatus::Expired) {
                return DeviceApiException::of(DeviceErrorCode::SessionExpired);
            }

            return $step($session);
        }));
    }

    private function throwIfError(DepositSession|DeviceApiException $outcome): DepositSession
    {
        if ($outcome instanceof DeviceApiException) {
            throw $outcome;
        }

        return $outcome;
    }

    /** Moves a session to a final state; closed_at is set on every one (P11). */
    private function close(DepositSession $session, SessionStatus $status): DepositSession
    {
        $session->status = $status;
        $session->closed_at = now();
        $session->save();

        return $session;
    }

    /**
     * The rule the session opened under: the one in effect at its created_at
     * (API_Design.md §4.1). Rules are never deleted and never overlap, so one
     * always matches.
     */
    private function ruleFor(DepositSession $session): RewardRule
    {
        return $this->points->ruleInEffectAt($session->created_at)
            ?? throw new \LogicException("No reward rule was in effect when session {$session->id} opened.");
    }

    /**
     * The conditional expiry update, optionally narrowed by $scope. Uses the
     * (status, expires_at) index.
     *
     * @param  (Closure(Builder): void)|null  $scope
     */
    private function expireOpenSessionsWhere(?Closure $scope): int
    {
        $query = DepositSession::where('status', SessionStatus::Open)
            ->where('expires_at', '<=', now());

        // A nested where, so the scope's OR stays inside its own brackets.
        // (Not ->when(): it would call a Closure condition on the outer query.)
        if ($scope !== null) {
            $query->where($scope);
        }

        return $query->update(['status' => SessionStatus::Expired, 'closed_at' => now()]);
    }
}
