<?php

/**
 * Application settings for the Smart E-Waste system.
 *
 * System_Plan.md §6.3: these are settings, not data, so they live here and take
 * their values from .env. Values an admin changes while the system runs stay in
 * the database instead: the four reward rule variables, each reward's price, and
 * each bin's fill threshold and calibration.
 *
 * §8.1 swappability check: env() is called only inside config/. Everything else
 * reads config('ewaste....'). scripts/check-swappability.sh enforces this.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Deposit session
    |--------------------------------------------------------------------------
    */

    // How long an OPEN session lasts. Also returned by GET /device/config as
    // session_timeout_s. System_Plan.md §6.3.
    'session_timeout_seconds' => (int) env('EWASTE_SESSION_TIMEOUT_SECONDS', 90),

    // How long an ACCEPTED session waits for `complete` before it is listed on
    // /admin/sessions/review. An ACCEPTED session is never expired silently,
    // because the item may already be in the bin. System_Plan.md §6.3.
    'review_threshold_minutes' => (int) env('EWASTE_REVIEW_THRESHOLD_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Device API
    |--------------------------------------------------------------------------
    */

    'device' => [
        // How far a device's X-Timestamp may be from server time before the
        // request is rejected with CLOCK_SKEW. System_Plan.md §6.3.
        'signature_window_seconds' => (int) env('EWASTE_SIGNATURE_WINDOW_SECONDS', 60),

        // Retry-After header sent with DB_UNAVAILABLE. API_Design.md P7.
        'retry_after_seconds' => (int) env('EWASTE_RETRY_AFTER_SECONDS', 5),

        // Length in bytes of a generated device secret. Rendered as twice this
        // many hex characters: 32 bytes -> 64 characters. API_Design.md P39.
        'secret_bytes' => 32,
    ],

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    */

    // Every timestamp is stored in UTC and converted only for display. This zone
    // is also the midnight used by the daily points cap and by "today" on the
    // dashboard. System_Plan.md §6.3.
    'display_timezone' => env('EWASTE_DISPLAY_TIMEZONE', 'Asia/Manila'),

    // How often the admin dashboard's polling script calls
    // /admin/dashboard/summary. API_Design.md P34.
    'dashboard_poll_seconds' => (int) env('EWASTE_DASHBOARD_POLL_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Hardware limits
    |--------------------------------------------------------------------------
    */

    // The heaviest deposit the load cell, platform and actuator can handle. Used
    // when an admin saves a reward rule: maximum_weight_g may not exceed it.
    // API_Design.md P37 and §10 question 7.
    //
    // TODO(team): no value is set in the plan or the schema. Measure it once the
    // actuator is chosen, then set EWASTE_HARDWARE_MAX_WEIGHT_G. While this is
    // null the rule form skips the check.
    'hardware_max_weight_g' => env('EWASTE_HARDWARE_MAX_WEIGHT_G') !== null
        ? (float) env('EWASTE_HARDWARE_MAX_WEIGHT_G')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    */

    // Length of a generated QR token: 40 random letters and digits.
    // API_Design.md P35. The column holds up to 64 characters.
    'qr_token_length' => 40,

    /*
    |--------------------------------------------------------------------------
    | Human-readable codes
    |--------------------------------------------------------------------------
    */

    // API_Design.md P25: prefix + YYYYMMDD + '-' + 6 random upper-case letters
    // and digits, e.g. TXN-20261003-7K2QX9. The unique index catches a collision.
    'codes' => [
        'transaction_prefix' => 'TXN',
        'redemption_prefix' => 'RDM',
        'random_length' => 6,
    ],

];
