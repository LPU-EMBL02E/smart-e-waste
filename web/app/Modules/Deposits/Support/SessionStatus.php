<?php

namespace App\Modules\Deposits\Support;

/**
 * The seven states of a deposit session. API_Design.md §4.1.
 *
 * OPEN and ACCEPTED are live; the other five are final. The column is VARCHAR
 * and the allowed values are validated here, in the application
 * (Database_Schema.md conventions).
 *
 *   (none)   -> OPEN        POST /device/sessions passed every check
 *   OPEN     -> ACCEPTED    deposit passed verification and the weight checks
 *   OPEN     -> REJECTED    deposit failed one of them
 *   OPEN     -> CANCELLED   cancel, or a new session on the same device
 *   OPEN     -> EXPIRED     the sweep, or a call arriving after expires_at
 *   ACCEPTED -> COMPLETED   complete with actuator_ok: true
 *   ACCEPTED -> FAILED      complete with actuator_ok: false
 */
enum SessionStatus: string
{
    case Open = 'OPEN';
    case Accepted = 'ACCEPTED';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /**
     * A live session. Only these can still be advanced by a device call.
     */
    public function isLive(): bool
    {
        return $this === self::Open || $this === self::Accepted;
    }

    /**
     * A final state. `closed_at` is set when a session reaches one
     * (API_Design.md P11).
     */
    public function isFinal(): bool
    {
        return ! $this->isLive();
    }

    /**
     * Only OPEN sessions are expired by the timeout sweep. An ACCEPTED session
     * may already have the item in the bin, so it waits for `complete` and is
     * listed for admin review instead (System_Plan.md §5.3 rule 6).
     */
    public function canExpire(): bool
    {
        return $this === self::Open;
    }
}
