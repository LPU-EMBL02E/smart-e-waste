#pragma once

/**
 * Compile-time constants for the controller firmware.
 *
 * Nothing here identifies a particular bin or server. The Wi-Fi credentials,
 * API base URL, device code and secret all live in non-volatile storage and are
 * set through the setup portal, so the bin can be re-pointed at another server
 * without reflashing (System_Plan.md §8). That is also why no secret is ever
 * committed to this repository.
 */

// --------------------------------------------------------------- serial links
constexpr unsigned long SERIAL_LOG_BAUD = 115200;
constexpr unsigned long SERIAL_QR_BAUD = 9600;
constexpr unsigned long SERIAL_PANEL_BAUD = 115200;
constexpr unsigned long SERIAL_CAMERA_BAUD = 115200;

// ------------------------------------------------------------------- scale
// How many readings to average, and how close they must be to count as stable.
// `weight_stable` in the deposit call comes from this; the server rejects an
// unstable reading with WEIGHT_UNSTABLE (API_Design.md §5.5).
constexpr int SCALE_SAMPLE_COUNT = 20;
constexpr float SCALE_STABLE_TOLERANCE_G = 2.0f;
constexpr unsigned long SCALE_SETTLE_TIMEOUT_MS = 5000;

// ------------------------------------------------------------------ actuator
// Hard stop on a cycle. If a limit switch is never reached within this time the
// move has failed: report actuator_ok = false rather than driving the motor
// against a jam.
constexpr unsigned long ACTUATOR_TIMEOUT_MS = 8000;
constexpr int ACTUATOR_PWM_DUTY = 200;   // 0-255; set on the bench

// ------------------------------------------------------------------- network
// API_Design.md §3.6: fail closed before a deposit, retry after one. Once the
// item is physically in the bin, `complete` is retried up to three times, then
// saved to NVS and resent later — including after a reboot.
constexpr int COMPLETE_RETRY_LIMIT = 3;
constexpr unsigned long COMPLETE_RETRY_BASE_MS = 1000;   // doubles each attempt
constexpr unsigned long HTTP_TIMEOUT_MS = 10000;

// TODO(team): API_Design.md §10 question 5 — the telemetry interval and the
// backoff between `complete` retries are not set in the plan. The values here
// are a starting point to be confirmed on the bench.
constexpr unsigned long TELEMETRY_INTERVAL_MS = 60000;

// ------------------------------------------------------------------ feedback
constexpr unsigned long BEEP_SHORT_MS = 120;
constexpr unsigned long BEEP_LONG_MS = 600;

// The firmware version reported as `fw` when a session opens, and stored in
// devices.firmware_version.
constexpr const char* FIRMWARE_VERSION = "0.1.0-dev";
