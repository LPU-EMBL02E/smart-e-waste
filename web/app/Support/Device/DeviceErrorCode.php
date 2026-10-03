<?php

namespace App\Support\Device;

/**
 * Every error code the device API can return. API_Design.md §6.
 *
 * The firmware switches on `code` and never on `message`, so these strings are
 * part of the wire contract: changing one is a breaking change that needs a
 * firmware update.
 *
 * ACTUATOR_FAILED is deliberately absent. An actuator failure is not an error
 * reply — it is a 200 from `complete` — and the string appears only in
 * deposit_sessions.failure_code (see Deposits\Support\FailureCode).
 */
enum DeviceErrorCode: string
{
    // ---- signature middleware, any signed route ---------------------------
    case InvalidSignature = 'INVALID_SIGNATURE';
    case ClockSkew = 'CLOCK_SKEW';
    case DeviceInactive = 'DEVICE_INACTIVE';

    // ---- any route with a body --------------------------------------------
    case ValidationError = 'VALIDATION_ERROR';

    // ---- POST /device/sessions --------------------------------------------
    case InvalidQr = 'INVALID_QR';
    case UserInactive = 'USER_INACTIVE';
    case BinFull = 'BIN_FULL';
    case NoRewardRule = 'NO_REWARD_RULE';
    case DailyLimitReached = 'DAILY_LIMIT_REACHED';
    case DuplicateTransaction = 'DUPLICATE_TRANSACTION';

    // ---- deposit / complete / cancel --------------------------------------
    case SessionNotFound = 'SESSION_NOT_FOUND';
    case InvalidSessionState = 'INVALID_SESSION_STATE';
    case SessionExpired = 'SESSION_EXPIRED';

    // ---- POST .../deposit --------------------------------------------------
    case VerificationFailed = 'VERIFICATION_FAILED';
    case WeightUnstable = 'WEIGHT_UNSTABLE';
    case NoItem = 'NO_ITEM';
    case ZeroWeight = 'ZERO_WEIGHT';
    case OverMaxWeight = 'OVER_MAX_WEIGHT';

    // ---- any route except /device/time ------------------------------------
    case DbUnavailable = 'DB_UNAVAILABLE';

    /**
     * The HTTP status this code is returned with. API_Design.md §6.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidSignature,
            self::ClockSkew,
            self::InvalidQr => 401,

            self::DeviceInactive,
            self::UserInactive => 403,

            self::SessionNotFound => 404,

            self::BinFull,
            self::DuplicateTransaction,
            self::InvalidSessionState => 409,

            self::SessionExpired => 410,

            self::ValidationError,
            self::VerificationFailed,
            self::WeightUnstable,
            self::NoItem,
            self::ZeroWeight,
            self::OverMaxWeight => 422,

            self::DailyLimitReached => 429,

            self::NoRewardRule,
            self::DbUnavailable => 503,
        };
    }

    /**
     * Whether the firmware may send the same request again with no action from
     * the student. True only for DB_UNAVAILABLE, and for CLOCK_SKEW once the
     * firmware has fetched the time again and re-signed. API_Design.md P7.
     */
    public function isRetryable(): bool
    {
        return $this === self::DbUnavailable || $this === self::ClockSkew;
    }

    /**
     * A failing `deposit` records the code on the session as its failure_code
     * and moves the session to REJECTED (API_Design.md P11).
     */
    public function rejectsSession(): bool
    {
        return match ($this) {
            self::VerificationFailed,
            self::WeightUnstable,
            self::NoItem,
            self::ZeroWeight,
            self::OverMaxWeight => true,
            default => false,
        };
    }
}
