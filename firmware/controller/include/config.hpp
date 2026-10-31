#pragma once

/**
 * Compile-time constants for the controller firmware.
 *
 * Nothing here identifies a particular bin or server. The Wi-Fi credentials,
 * API base URL, bin station URL, device code and secret all live in
 * non-volatile storage and are set through the setup portal, so the bin can be
 * re-pointed at another server without reflashing (System_Plan.md §8). That is
 * also why no secret is ever committed to this repository.
 *
 * Values marked "set on the bench" are starting points. System_Plan.md §10
 * lists them as values to measure on the built bin before Checkpoint 1.
 */

// ----------------------------------------------------------------- serial log
constexpr unsigned long SERIAL_LOG_BAUD = 115200;

// -------------------------------------------------------------- setup portal
// The Wi-Fi network the bin opens while nothing is stored, for entering the
// Wi-Fi details, server URL, bin station URL, device code and secret
// (System_Plan.md §8).
constexpr const char* SETUP_PORTAL_SSID = "EWaste-Setup";
// Every wait has a timeout: without one, a bin with nothing stored would sit in
// the portal forever. A starting point to confirm on the bench.
constexpr unsigned long SETUP_PORTAL_TIMEOUT_S = 300;

// ------------------------------------------------------------------- scale
// How many readings to average, and how close they must be to count as stable.
// `weight_stable` in the deposit call comes from this; the server rejects an
// unstable reading with WEIGHT_UNSTABLE (API_Design.md §5.5).
constexpr int SCALE_SAMPLE_COUNT = 20;
constexpr float SCALE_STABLE_TOLERANCE_G = 2.0f;
constexpr unsigned long SCALE_SETTLE_TIMEOUT_MS = 5000;
// The HX711 gives a reading every 100 ms at its slower rate, so this is five
// missed readings: long enough to ride out one, short enough to notice an
// unplugged amplifier.
constexpr unsigned long SCALE_READY_TIMEOUT_MS = 500;
// A stable reading within this of zero is an empty flap; above it, an item is
// on the flap. Set on the bench.
constexpr float SCALE_EMPTY_TOLERANCE_G = 5.0f;

// --------------------------------------------------------------- bin level
// Pulses per reading; the median is reported (bin_level.hpp).
constexpr int BIN_LEVEL_PULSE_COUNT = 5;
// The HC-SR04's range is about 4 m, an echo of about 23 ms; no echo by this
// time means no reading.
constexpr unsigned long BIN_LEVEL_ECHO_TIMEOUT_US = 30000;
// The HC-SR04 needs about 60 ms between pulses, or it hears the last echo.
constexpr unsigned long BIN_LEVEL_PULSE_GAP_MS = 60;

// ------------------------------------------------------------------ actuator
// The actuator's limit switches are inside it, so each stroke to an end is
// judged by the BTS7960's current sense, read in millivolts at
// PIN_ACTUATOR_CURRENT (System_Plan.md §8.2). All set on the bench.
//
// TODO(team): the thresholds depend on the load resistor, which is not on the
// schematic yet (System_Plan.md §2).
constexpr int ACTUATOR_PWM_DUTY = 200;  // 0-255
// Before this, a low current means the actuator has not started, not that it
// has reached the end.
constexpr unsigned long ACTUATOR_MIN_TRAVEL_MS = 300;
// At or below this after the minimum travel time: the end of the stroke.
constexpr int ACTUATOR_END_CURRENT_MV = 100;
// Above this for ACTUATOR_JAM_MS: a jam.
constexpr int ACTUATOR_JAM_CURRENT_MV = 2500;
constexpr unsigned long ACTUATOR_JAM_MS = 300;
// A full 200 mm stroke at 160 mm/s takes about 1.3 s; this leaves room for the
// load.
constexpr unsigned long ACTUATOR_STROKE_TIMEOUT_MS = 3000;
// The actuator's 20% duty cycle: a cycle of about 2.5 s needs about 10 s of
// rest (System_Plan.md §2).
constexpr unsigned long ACTUATOR_REST_MS = 10000;
// The flap level switch must read closed this long before it counts, so a
// bounce on the way past is not taken as level.
constexpr unsigned long FLAP_LEVEL_DEBOUNCE_MS = 5;

// --------------------------------------------------------------- LED strip
// Brightness for the camera, 0-255, and how long the light needs to settle
// before a picture. Both set on the bench.
constexpr int LED_STRIP_DUTY = 255;
constexpr unsigned long LED_STRIP_SETTLE_MS = 200;

// ------------------------------------------------------- PWM (LEDC) channels
// One per output, so no two modules share one.
constexpr int LEDC_CHANNEL_LED_STRIP = 0;
constexpr int LEDC_CHANNEL_EXTEND = 1;
constexpr int LEDC_CHANNEL_RETRACT = 2;
constexpr int LEDC_FREQUENCY_HZ = 20000;  // above hearing
constexpr int LEDC_RESOLUTION_BITS = 8;   // duty 0-255

// ------------------------------------------------------------- bin station
// API_Design.md §11. The station is on the bin's own network, so a call that
// takes longer than this has failed.
constexpr unsigned long STATION_TIMEOUT_MS = 2000;
// `verify` runs the e-waste model on the Pi's CPU. TODO(team): measure how long
// it takes with the web app busy; this must be longer (System_Plan.md §8.3).
constexpr unsigned long STATION_VERIFY_TIMEOUT_MS = 5000;
// TODO(team): API_Design.md §10 question 5 — the event poll interval is not
// set. It must be shorter than the bin station's controller timeout (§11.9).
constexpr unsigned long STATION_EVENT_POLL_MS = 250;

// ------------------------------------------------------------------- network
// API_Design.md §3.6: fail closed before a deposit, retry after one. Once the
// item is physically in the bin, `complete` is retried up to three times, then
// saved to NVS and resent later — including after a reboot.
constexpr int COMPLETE_RETRY_LIMIT = 3;
constexpr unsigned long COMPLETE_RETRY_BASE_MS = 1000;  // doubles each attempt
constexpr unsigned long HTTP_TIMEOUT_MS = 10000;
// How long Api::begin() waits to join the stored Wi-Fi network at boot. A
// starting point to confirm on the bench.
constexpr unsigned long WIFI_CONNECT_TIMEOUT_MS = 15000;

// TODO(team): API_Design.md §10 question 5 — the telemetry interval and the
// backoff between `complete` retries are not set in the plan. The values here
// are a starting point to be confirmed on the bench.
constexpr unsigned long TELEMETRY_INTERVAL_MS = 60000;
// How often a saved `complete` is sent again while the bin waits on it.
constexpr unsigned long PENDING_RESEND_MS = 10000;

// ------------------------------------------------------------------ feedback
constexpr unsigned long BEEP_SHORT_MS = 120;
constexpr unsigned long BEEP_LONG_MS = 600;
// How long a result screen (problem, done) stays before the bin moves on.
constexpr unsigned long RESULT_SCREEN_MS = 3000;

// The firmware version reported as `fw` when a session opens, and stored in
// devices.firmware_version.
constexpr const char* FIRMWARE_VERSION = "0.2.0-dev";
