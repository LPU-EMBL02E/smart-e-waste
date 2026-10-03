#include "api.h"

#include "../../include/config.h"
#include "settings.h"

/**
 * TODO: implement with WiFi, HTTPClient and mbedtls' HMAC-SHA256
 * (`mbedtls/md.h`), which is already in the ESP32 Arduino core — no extra
 * library needed.
 *
 * The signing contract, API_Design.md §3.2 and P4:
 *
 *   canonical = timestamp + "\n" + METHOD + "\n" + path + "\n" + raw_body
 *   key       = the secret string's bytes, NOT hex-decoded
 *   path      = from "/api/v1/device" onward, no scheme, host or query string
 *   body      = the exact bytes sent; empty for a GET, so the string ends "\n"
 *   signature = lower-case hex, 64 characters
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
 * The clock: keep the offset between the server time from GET /device/time and
 * millis() at boot, and derive X-Timestamp from it. On a CLOCK_SKEW error,
 * syncClock() again and resend once — that is the one case where a 401 is
 * retryable (P7).
 */

namespace api {

bool begin() { return false; }
bool isConnected() { return false; }
bool syncClock() { return false; }
Result fetchConfig() { return Result::NetworkError; }
Result sendTelemetry(float, BinStatus&) { return Result::NetworkError; }
Result openSession(const String&, SessionInfo&) { return Result::NetworkError; }
Result sendDeposit(const String&, float, bool, int, bool, float, DepositResult&) {
  return Result::NetworkError;
}
Result sendComplete(const String&, bool, unsigned long, CompleteResult&) {
  return Result::NetworkError;
}
Result cancelSession(const String&) { return Result::NetworkError; }
Result flushPendingComplete() { return Result::NetworkError; }
const char* lastErrorCode() { return ""; }
float minimumWeightG() { return 0.0f; }
float maximumWeightG() { return 0.0f; }
int sessionTimeoutSeconds() { return 0; }

}  // namespace api
