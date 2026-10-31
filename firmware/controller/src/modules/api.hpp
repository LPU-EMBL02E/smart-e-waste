#pragma once

#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WiFi.h>
#include <esp_timer.h>
#include <mbedtls/md.h>

#include "../../include/config.hpp"
#include "../fault_code.hpp"
#include "settings.hpp"

/**
 * Wi-Fi, HTTP, HMAC signing, retries and the saved unsent `complete`.
 * System_Plan.md §8.2, API_Design.md §3 and §5.
 *
 * With Station, the only module that knows about HTTP: Api talks to the web
 * app, Station to the bin station. One method per device endpoint, so
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
   * and the uptime at sync, and derive X-Timestamp from it. On a CLOCK_SKEW
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

  /** The load cell calibration from GET /device/config. `present` is false
   *  until the bin has been calibrated, when the server sends null (§5.2). */
  struct Calibration {
    bool present;
    float weightOffset;
    float weightScaleFactor;
    int64_t calibratedAt;
  };

  explicit Api(Settings& settings) : p_settings(settings) {}

  /**
   * Joins the Wi-Fi network the setup portal stored. False when it has not
   * joined within WIFI_CONNECT_TIMEOUT_MS. A later drop is reconnected by the
   * Wi-Fi driver, never by reopening the portal (see Settings).
   */
  bool begin() {
    if (isConnected()) {
      return true;
    }
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin();

    const unsigned long startMs = millis();
    while (!isConnected()) {
      if (millis() - startMs >= WIFI_CONNECT_TIMEOUT_MS) {
        Serial.println("api: could not join Wi-Fi");
        return false;
      }
      delay(sp_WIFI_POLL_MS);
    }
    Serial.printf("api: joined Wi-Fi as %s\n",
                  WiFi.localIP().toString().c_str());
    return true;
  }

  bool isConnected() { return WiFi.status() == WL_CONNECTED; }

  /**
   * GET /device/time, unsigned, then count forward from it.
   *
   * The ESP32 has no clock, so this must succeed once at boot before any signed
   * request can be made, and again after any CLOCK_SKEW error (§3.2).
   */
  bool syncClock() {
    JsonDocument reply;
    if (p_exchange("GET", "/time", nullptr, false, reply) != Result::Ok) {
      return false;
    }
    if (!reply["time"].is<int64_t>() || reply["time"].as<int64_t>() <= 0) {
      Serial.println("api: /time reply unreadable");
      return false;
    }

    p_serverTimeAtSyncS = reply["time"].as<int64_t>();
    p_uptimeAtSyncUs = esp_timer_get_time();
    p_clockSynced = true;
    return true;
  }

  /** GET /device/config — weight limits, timeout, threshold and calibration. */
  Result fetchConfig() {
    JsonDocument reply;
    const Result result = p_send("GET", "/config", nullptr, reply);
    if (result != Result::Ok) {
      return result;
    }

    // Both weight limits are null when no reward rule is in effect, and the
    // maximum also when the rule has no upper limit (§5.2).
    const JsonVariantConst minimum = reply["minimum_weight_g"];
    const JsonVariantConst maximum = reply["maximum_weight_g"];
    if (!reply["session_timeout_s"].is<int>() ||
        !(minimum.isNull() || minimum.is<float>()) ||
        !(maximum.isNull() || maximum.is<float>())) {
      Serial.println("api: /config reply unreadable");
      return Result::NetworkError;
    }

    p_sessionTimeoutS = reply["session_timeout_s"].as<int>();
    p_minimumWeightG = minimum.isNull() ? NAN : minimum.as<float>();
    p_maximumWeightG = maximum.isNull() ? NAN : maximum.as<float>();
    return Result::Ok;
  }

  /**
   * POST /device/telemetry — one raw distance, the service-panel state and the
   * current fault. The server computes fill level, and only stores the panel
   * state and the fault (§5.3, P42).
   */
  Result sendTelemetry(float distanceMm, bool panelOpen, FaultCode fault,
                       BinStatus& out) {
    // At most two decimal places (§5.3). The fault names are fixed
    // identifiers, so they need no escaping. Built once: these exact bytes
    // are both signed and sent.
    const char* faultName = faultCodeName(fault);
    char faultJson[sp_FAULT_JSON_LENGTH];
    if (faultName != nullptr) {
      snprintf(faultJson, sizeof(faultJson), "\"%s\"", faultName);
    } else {
      strlcpy(faultJson, "null", sizeof(faultJson));
    }
    char body[sp_TELEMETRY_BODY_LENGTH];
    const int length =
        snprintf(body, sizeof(body),
                 "{\"distance_mm\":%.2f,\"panel_open\":%s,\"fault_code\":%s}",
                 distanceMm, panelOpen ? "true" : "false", faultJson);
    if (length < 0 || length >= static_cast<int>(sizeof(body))) {
      Serial.println("api: telemetry body does not fit");
      return Result::NetworkError;
    }

    JsonDocument reply;
    const Result result = p_send("POST", "/telemetry", body, reply);
    if (result != Result::Ok) {
      return result;
    }

    const char* status = reply["status"];
    if (!reply["fill_percent"].is<float>() || status == nullptr ||
        (strcmp(status, "OK") != 0 && strcmp(status, "FULL") != 0)) {
      Serial.println("api: /telemetry reply unreadable");
      return Result::NetworkError;
    }

    out.fillPercent = reply["fill_percent"].as<float>();
    out.full = strcmp(status, "FULL") == 0;
    return Result::Ok;
  }

  /** POST /device/sessions — open a session from a scanned token. */
  Result openSession(const String& qrToken, SessionInfo& out) {
    return Result::NetworkError;
  }

  /**
   * POST /device/sessions/{id}/deposit — the weight and the bin station's
   * verdict, its three values passed on unchanged (§5.5, P49).
   */
  Result sendDeposit(const String& sessionId, float weightG, bool stable,
                     int samples, const char* verificationMethod,
                     bool verificationPassed, float verificationScore,
                     DepositResult& out) {
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
  const char* lastErrorCode() { return p_lastErrorCode; }

  /** Cached from GET /device/config; for the display only. The server
   *  decides. A weight limit is NAN when the server sent null, or before the
   *  first fetch; the timeout is 0 before the first fetch. */
  float minimumWeightG() { return p_minimumWeightG; }
  float maximumWeightG() { return p_maximumWeightG; }
  int sessionTimeoutSeconds() { return p_sessionTimeoutS; }

  /** Cached from GET /device/config, for Scale::applyCalibration(). */
  const Calibration& calibration() { return p_calibration; }

 private:
  static constexpr const char* sp_API_PREFIX = "/api/v1/device";
  static constexpr const char* sp_CODE_CLOCK_SKEW = "CLOCK_SKEW";
  static constexpr unsigned long sp_WIFI_POLL_MS = 100;
  static constexpr size_t sp_SIGNATURE_LENGTH = 64;
  static constexpr size_t sp_HMAC_LENGTH = 32;
  // Settings caps the base URL at 128 characters; the rest is the path.
  static constexpr size_t sp_URL_LENGTH = 224;
  static constexpr size_t sp_PATH_LENGTH = 96;
  // Fits the longest code in API_Design.md §6, with room to spare.
  static constexpr size_t sp_ERROR_CODE_LENGTH = 32;
  static constexpr size_t sp_TELEMETRY_BODY_LENGTH = 128;
  // The longest fault name in fault_code.hpp, quoted.
  static constexpr size_t sp_FAULT_JSON_LENGTH = 32;

  /**
   * A signed request. Syncs the clock first if it never has been, and on
   * CLOCK_SKEW syncs again and resends once — the one retryable 401 (P7).
   * Every other result is returned as it is, for the caller to decide.
   */
  Result p_send(const char* method, const char* endpoint, const char* body,
                JsonDocument& reply) {
    p_lastErrorCode[0] = '\0';
    if (!p_clockSynced && !syncClock()) {
      return Result::NetworkError;
    }

    const Result result = p_exchange(method, endpoint, body, true, reply);
    if (result != Result::ServerError ||
        strcmp(p_lastErrorCode, sp_CODE_CLOCK_SKEW) != 0) {
      return result;
    }
    if (!syncClock()) {
      strlcpy(p_lastErrorCode, sp_CODE_CLOCK_SKEW, sizeof(p_lastErrorCode));
      return result;
    }
    return p_exchange(method, endpoint, body, true, reply);
  }

  /**
   * One HTTP request and its JSON reply.
   *
   * Ok means a 2xx with a JSON body; the caller checks that body's shape. A
   * reply in the error shape (§3.5) is ServerError with its code kept for
   * lastErrorCode(). Anything else — no connection, a timeout, a body that is
   * not JSON — is NetworkError, as P9 requires.
   */
  Result p_exchange(const char* method, const char* endpoint, const char* body,
                    bool sign, JsonDocument& reply) {
    if (!isConnected()) {
      Serial.printf("api: %s skipped, Wi-Fi not connected\n", endpoint);
      return Result::NetworkError;
    }

    char path[sp_PATH_LENGTH];
    char url[sp_URL_LENGTH];
    const String baseUrl = p_settings.apiBaseUrl();
    const int pathLength =
        snprintf(path, sizeof(path), "%s%s", sp_API_PREFIX, endpoint);
    const int urlLength =
        snprintf(url, sizeof(url), "%s%s", baseUrl.c_str(), path);
    if (baseUrl.isEmpty() || pathLength >= static_cast<int>(sizeof(path)) ||
        urlLength >= static_cast<int>(sizeof(url))) {
      Serial.printf("api: %s skipped, no usable server URL\n", endpoint);
      return Result::NetworkError;
    }

    HTTPClient http;
    // HTTP/1.0 rules out a chunked reply, so the JSON can be parsed straight
    // from the stream without first copying the body into a String.
    http.useHTTP10(true);
    http.setConnectTimeout(HTTP_TIMEOUT_MS);
    http.setTimeout(HTTP_TIMEOUT_MS);
    if (!http.begin(p_client, url)) {
      Serial.printf("api: %s could not start the request\n", endpoint);
      return Result::NetworkError;
    }

    const char* sentBody = body != nullptr ? body : "";
    if (sign) {
      char timestamp[21];
      char signature[sp_SIGNATURE_LENGTH + 1];
      snprintf(timestamp, sizeof(timestamp), "%lld",
               static_cast<long long>(p_now()));
      if (!sp_sign(p_settings.deviceSecret(), timestamp, method, path, sentBody,
                   signature)) {
        Serial.printf("api: %s could not be signed\n", endpoint);
        http.end();
        return Result::NetworkError;
      }
      http.addHeader("X-Device-Code", p_settings.deviceCode());
      http.addHeader("X-Timestamp", timestamp);
      http.addHeader("X-Signature", signature);
    }
    http.addHeader("Accept", "application/json");
    if (body != nullptr) {
      http.addHeader("Content-Type", "application/json");
    }

    const int status = http.sendRequest(
        method, reinterpret_cast<uint8_t*>(const_cast<char*>(sentBody)),
        strlen(sentBody));
    if (status <= 0) {
      Serial.printf("api: %s failed, %s\n", endpoint,
                    HTTPClient::errorToString(status).c_str());
      http.end();
      return Result::NetworkError;
    }

    const DeserializationError error = deserializeJson(reply, http.getStream());
    http.end();
    if (error) {
      Serial.printf("api: %s answered %d, not JSON\n", endpoint, status);
      return Result::NetworkError;
    }
    if (status >= 200 && status < 300) {
      return Result::Ok;
    }

    const char* code = reply["error"]["code"];
    if (code == nullptr) {
      Serial.printf("api: %s answered %d, not the error shape\n", endpoint,
                    status);
      return Result::NetworkError;
    }
    strlcpy(p_lastErrorCode, code, sizeof(p_lastErrorCode));
    Serial.printf("api: %s answered %d %s\n", endpoint, status,
                  p_lastErrorCode);
    return Result::ServerError;
  }

  /**
   * HMAC-SHA256 over the canonical string, as lower-case hex (§3.2, P4).
   *
   * The parts are fed to the HMAC one after another rather than joined into
   * one string first: the result is the same bytes, without a copy of the
   * body.
   */
  static bool sp_sign(const String& secret, const char* timestamp,
                      const char* method, const char* path, const char* body,
                      char (&out)[sp_SIGNATURE_LENGTH + 1]) {
    mbedtls_md_context_t context;
    mbedtls_md_init(&context);
    const auto update = [&context](const char* part) {
      return mbedtls_md_hmac_update(
                 &context, reinterpret_cast<const unsigned char*>(part),
                 strlen(part)) == 0;
    };

    // The key is the secret string's own bytes, NOT hex-decoded.
    unsigned char hmac[sp_HMAC_LENGTH];
    const bool signedOk =
        mbedtls_md_setup(&context, mbedtls_md_info_from_type(MBEDTLS_MD_SHA256),
                         1) == 0 &&
        mbedtls_md_hmac_starts(
            &context, reinterpret_cast<const unsigned char*>(secret.c_str()),
            secret.length()) == 0 &&
        update(timestamp) && update("\n") && update(method) && update("\n") &&
        update(path) && update("\n") && update(body) &&
        mbedtls_md_hmac_finish(&context, hmac) == 0;
    mbedtls_md_free(&context);
    if (!signedOk) {
      return false;
    }

    static constexpr char HEX_DIGITS[] = "0123456789abcdef";
    for (size_t i = 0; i < sp_HMAC_LENGTH; i++) {
      out[i * 2] = HEX_DIGITS[hmac[i] >> 4];
      out[i * 2 + 1] = HEX_DIGITS[hmac[i] & 0x0F];
    }
    out[sp_SIGNATURE_LENGTH] = '\0';
    return true;
  }

  /**
   * Unix seconds, counted forward from the last syncClock(). Uses the 64-bit
   * esp_timer rather than millis(), which wraps after 49 days — a bin left
   * running that long would otherwise sign with a wrong time.
   */
  int64_t p_now() {
    return p_serverTimeAtSyncS +
           (esp_timer_get_time() - p_uptimeAtSyncUs) / 1000000;
  }

  // Where the server is and who this bin is; also holds the saved `complete`.
  Settings& p_settings;
  WiFiClient p_client;

  bool p_clockSynced = false;
  int64_t p_serverTimeAtSyncS = 0;
  int64_t p_uptimeAtSyncUs = 0;

  char p_lastErrorCode[sp_ERROR_CODE_LENGTH] = "";

  int p_sessionTimeoutS = 0;
  float p_minimumWeightG = NAN;
  float p_maximumWeightG = NAN;
  Calibration p_calibration{false, 0.0f, 0.0f, 0};
};
