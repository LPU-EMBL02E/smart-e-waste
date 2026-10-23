#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "settings.hpp"

/**
 * Wi-Fi, HTTP, HMAC signing, retries and the saved unsent `complete`.
 * System_Plan.md §8.2, API_Design.md §3 and §5.
 *
 * The ONLY module that knows about HTTP. One method per device endpoint, so
 * the main loop reads as the deposit sequence and not as a series of requests.
 *
 * Switching the server to HTTPS is a change to the stored base URL, not to this
 * interface.
 */
class Api {
  /*
   * TODO: implement with WiFi, HTTPClient and mbedtls' HMAC-SHA256
   * (`mbedtls/md.h`), which is already in the ESP32 Arduino core — no extra
   * library needed.
   *
   * The signing contract, API_Design.md §3.2 and P4:
   *
   *   canonical = timestamp + "\n" + METHOD + "\n" + path + "\n" + raw_body
   *   key       = the secret string's bytes, NOT hex-decoded
   *   path      = from "/api/v1/device" onward, no scheme, host or query string
   *   body      = the exact bytes sent; empty for a GET, so the string ends
   * "\n" signature = lower-case hex, 64 characters
   *
   * Two rules that cause almost every signing bug:
   *
   *   1. Build the JSON body ONCE into a String, sign THOSE bytes, and send the
   *      same String. Re-serializing changes key order and whitespace, and the
   *      signature stops matching.
   *   2. Sign the path with its "/api/v1/device" prefix, exactly as the vectors
   *      in §3.4 do.
   *
   * Check against both vectors in §3.4 before testing against a real server —
   * tools/simulator/test_vectors.py asserts the same two, so a mismatch is
   * immediately local to the firmware.
   *
   * The clock: keep the offset between the server time from GET /device/time
   * and millis() at boot, and derive X-Timestamp from it. On a CLOCK_SKEW
   * error, syncClock() again and resend once — that is the one case where a
   * 401 is retryable (P7).
   */

 public:
  /** What came back from a call: a code the caller can branch on, never a
   *  string. */
  enum class Result {
    Ok,
    NetworkError,  // no reply, or a reply that could not be read (P9)
    ServerError,   // a reply in the error shape; see lastErrorCode()
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
    long
        transactionId;  // -1 when null, which is what an actuator failure gives
    int pointsAwarded;
    int balance;
  };

  struct BinStatus {
    float fillPercent;
    bool full;
  };

  explicit Api(Settings& settings) : p_settings(settings) {}

  bool begin() { return false; }
  bool isConnected() { return false; }

  /**
   * GET /device/time, unsigned, then count forward from it.
   *
   * The ESP32 has no clock, so this must succeed once at boot before any signed
   * request can be made, and again after any CLOCK_SKEW error (§3.2).
   */
  bool syncClock() { return false; }

  /** GET /device/config — weight limits, timeout, threshold and calibration. */
  Result fetchConfig() { return Result::NetworkError; }

  /** POST /device/telemetry — one raw distance. The server computes fill
   *  level. */
  Result sendTelemetry(float distanceMm, BinStatus& out) {
    return Result::NetworkError;
  }

  /** POST /device/sessions — open a session from a scanned token. */
  Result openSession(const String& qrToken, SessionInfo& out) {
    return Result::NetworkError;
  }

  /** POST /device/sessions/{id}/deposit — weight and verification result. */
  Result sendDeposit(const String& sessionId, float weightG, bool stable,
                     int samples, bool verificationPassed,
                     float verificationScore, DepositResult& out) {
    return Result::NetworkError;
  }

  /**
   * POST /device/sessions/{id}/complete — the actuator result.
   *
   * Retries up to COMPLETE_RETRY_LIMIT with backoff. If every attempt fails the
   * request is saved to NVS, and this returns NetworkError: the caller shows
   * "points pending" and must not accept another deposit until it is sent
   * (API_Design.md §3.6).
   */
  Result sendComplete(const String& sessionId, bool actuatorOk,
                      unsigned long cycleMs, CompleteResult& out) {
    return Result::NetworkError;
  }

  /** POST /device/sessions/{id}/cancel — the Cancel button. */
  Result cancelSession(const String& sessionId) { return Result::NetworkError; }

  /**
   * Resend a `complete` saved by a previous run, if there is one. Call at boot
   * and before accepting a new deposit.
   */
  Result flushPendingComplete() { return Result::NetworkError; }

  /**
   * The `code` from the last error reply, e.g. "BIN_FULL". Branch on this,
   * never on the message (§3.5).
   */
  const char* lastErrorCode() { return ""; }

  /** Cached from GET /device/config; for the display only. The server
   *  decides. */
  float minimumWeightG() { return 0.0f; }
  float maximumWeightG() { return 0.0f; }
  int sessionTimeoutSeconds() { return 0; }

 private:
  // Where the server is and who this bin is; also holds the saved `complete`.
  Settings& p_settings;
};
