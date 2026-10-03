#pragma once

#include <Arduino.h>

/**
 * Wi-Fi, HTTP, HMAC signing, retries and the saved unsent `complete`.
 * System_Plan.md §8.2, API_Design.md §3 and §5.
 *
 * The ONLY module that knows about HTTP. One function per device endpoint, so
 * the main loop reads as the deposit sequence and not as a series of requests.
 *
 * Switching the server to HTTPS is a change to the stored base URL, not to this
 * interface.
 */
namespace api {

/** What came back from a call: a code the caller can branch on, never a string. */
enum class Result {
  Ok,
  NetworkError,      // no reply, or a reply that could not be read (P9)
  ServerError,       // a reply in the error shape; see lastErrorCode()
};

struct SessionInfo {
  String sessionId;
  String displayName;
  int expiresInSeconds;
};

struct DepositResult {
  bool accepted;
  int pointsPreview;
};

struct CompleteResult {
  long transactionId;   // -1 when null, which is what an actuator failure gives
  int pointsAwarded;
  int balance;
};

struct BinStatus {
  float fillPercent;
  bool full;
};

bool begin();
bool isConnected();

/**
 * GET /device/time, unsigned, then count forward from it.
 *
 * The ESP32 has no clock, so this must succeed once at boot before any signed
 * request can be made, and again after any CLOCK_SKEW error (§3.2).
 */
bool syncClock();

/** GET /device/config — weight limits, timeout, threshold and calibration. */
Result fetchConfig();

/** POST /device/telemetry — one raw distance. The server computes fill level. */
Result sendTelemetry(float distanceMm, BinStatus& out);

/** POST /device/sessions — open a session from a scanned token. */
Result openSession(const String& qrToken, SessionInfo& out);

/** POST /device/sessions/{id}/deposit — weight and verification result. */
Result sendDeposit(const String& sessionId, float weightG, bool stable, int samples,
                   bool verificationPassed, float verificationScore, DepositResult& out);

/**
 * POST /device/sessions/{id}/complete — the actuator result.
 *
 * Retries up to COMPLETE_RETRY_LIMIT with backoff. If every attempt fails the
 * request is saved to NVS, and this returns NetworkError: the caller shows
 * "points pending" and must not accept another deposit until it is sent
 * (API_Design.md §3.6).
 */
Result sendComplete(const String& sessionId, bool actuatorOk, unsigned long cycleMs,
                    CompleteResult& out);

/** POST /device/sessions/{id}/cancel — the Cancel button. */
Result cancelSession(const String& sessionId);

/**
 * Resend a `complete` saved by a previous run, if there is one. Call at boot
 * and before accepting a new deposit.
 */
Result flushPendingComplete();

/**
 * The `code` from the last error reply, e.g. "BIN_FULL". Branch on this, never
 * on the message (§3.5).
 */
const char* lastErrorCode();

/** Cached from GET /device/config; for the display only. The server decides. */
float minimumWeightG();
float maximumWeightG();
int sessionTimeoutSeconds();

}  // namespace api
