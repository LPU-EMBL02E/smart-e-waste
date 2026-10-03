<?php

namespace App\Modules\Deposits\Support;

/**
 * What goes in deposit_sessions.failure_code. API_Design.md P11.
 *
 * For a REJECTED session it is the DeviceErrorCode that rejected the deposit
 * (VERIFICATION_FAILED, WEIGHT_UNSTABLE, NO_ITEM, ZERO_WEIGHT, OVER_MAX_WEIGHT).
 * For a FAILED session it is ACTUATOR_FAILED, which exists only here and is
 * never returned to the device as an error code — an actuator failure is a 200
 * from `complete` (API_Design.md §5.6).
 *
 * The column stays NULL for every other state.
 */
final class FailureCode
{
    public const ACTUATOR_FAILED = 'ACTUATOR_FAILED';
}
