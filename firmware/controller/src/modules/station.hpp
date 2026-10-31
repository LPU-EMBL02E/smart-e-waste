#pragma once

#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WiFi.h>

#include "../../include/config.hpp"
#include "settings.hpp"

/**
 * HTTP to the bin station. System_Plan.md §8.2 and §8.3, API_Design.md §11.
 *
 * The bin station is the Python program on the Pi that runs the QR scanner,
 * the camera check and the touchscreen. This module is the controller's only
 * way to reach them; QrScanner, Verifier and Feedback all go through it. One
 * method per bin station endpoint.
 *
 * Only Api and Station know about HTTP. No signing: the bin station listens on
 * the bin's own network only (P45).
 *
 * Fail closed (§11.1): no reply, an unreadable reply and an error reply are all
 * failures. A missing camera or model answer is never a pass.
 */
class Station {
 public:
  /** What came back from a call. */
  enum class Result {
    Ok,
    NoReply,  // no reply, or one that could not be read: STATION_UNREACHABLE
    Error,    // a reply in the error shape; see lastErrorCode()
  };

  /** GET /station/v1/health (§11.2). */
  struct Health {
    bool scanner;
    bool camera;
    bool reference;
    bool model;
  };

  // `verification.method` is at most 50 characters (API_Design.md §5.5).
  static constexpr size_t s_METHOD_LENGTH = 51;

  /** POST /station/v1/verify (§11.5), copied unchanged into `deposit`. */
  struct Verdict {
    char method[s_METHOD_LENGTH];  // "cam_diff" or "ewaste_model"
    bool passed;
    float score;  // 0.0000 to 1.0000
  };

  explicit Station(Settings& settings) : p_settings(settings) {}

  /** What is working on the bin station. */
  Result health(Health& out) {
    JsonDocument reply;
    const Result result =
        p_exchange("GET", "/health", nullptr, STATION_TIMEOUT_MS, reply);
    if (result != Result::Ok) {
      return result;
    }
    if (!reply["scanner"].is<bool>() || !reply["camera"].is<bool>() ||
        !reply["reference"].is<bool>() || !reply["model"].is<bool>()) {
      Serial.println("station: /health reply unreadable");
      return Result::NoReply;
    }
    out.scanner = reply["scanner"];
    out.camera = reply["camera"];
    out.reference = reply["reference"];
    out.model = reply["model"];
    return Result::Ok;
  }

  /** Arm or disarm the scanner. Arming also drops any token read earlier. */
  Result setScannerArmed(bool armed) {
    if (armed) {
      p_hasToken = false;
    }
    JsonDocument reply;
    const Result result = p_exchange(
        "PUT", "/scanner", armed ? "{\"armed\":true}" : "{\"armed\":false}",
        STATION_TIMEOUT_MS, reply);
    if (result != Result::Ok) {
      return result;
    }
    if (!reply["armed"].is<bool>() || reply["armed"].as<bool>() != armed) {
      Serial.println("station: /scanner reply unreadable");
      return Result::NoReply;
    }
    return Result::Ok;
  }

  /**
   * Collects the queued events: a scanned token is kept for takeToken(), a
   * Cancel touch for takeCancel(). Call every STATION_EVENT_POLL_MS while the
   * scanner is armed or a session is open (§11.4).
   *
   * Only the first token is kept: the scanner is disarmed as soon as one is
   * taken, so any later one is a second scan that must not open a session.
   */
  Result pollEvents() {
    JsonDocument reply;
    const Result result =
        p_exchange("GET", "/events", nullptr, STATION_TIMEOUT_MS, reply);
    if (result != Result::Ok) {
      return result;
    }
    if (!reply["events"].is<JsonArrayConst>()) {
      Serial.println("station: /events reply unreadable");
      return Result::NoReply;
    }

    for (JsonObjectConst event : reply["events"].as<JsonArrayConst>()) {
      const char* type = event["type"];
      if (type == nullptr) {
        continue;
      }
      if (strcmp(type, "qr") == 0) {
        const char* token = event["token"];
        if (!p_hasToken && token != nullptr && token[0] != '\0' &&
            strlen(token) < sizeof(p_token)) {
          strlcpy(p_token, token, sizeof(p_token));
          p_hasToken = true;
        }
      } else if (strcmp(type, "touch") == 0) {
        const char* action = event["action"];
        if (action != nullptr && strcmp(action, "cancel") == 0) {
          p_hasCancel = true;
        }
      }
    }
    return Result::Ok;
  }

  /** The first token collected since the last take, if there is one. */
  bool takeToken(String& token) {
    if (!p_hasToken) {
      return false;
    }
    token = p_token;
    p_hasToken = false;
    return true;
  }

  /** True once per Cancel touch collected. */
  bool takeCancel() {
    const bool hadCancel = p_hasCancel;
    p_hasCancel = false;
    return hadCancel;
  }

  /** Forgets any token or Cancel collected but not yet taken. */
  void clearEvents() {
    p_hasToken = false;
    p_hasCancel = false;
  }

  /** The camera check: presence, then the e-waste model. */
  Result verify(Verdict& out) {
    JsonDocument reply;
    const Result result = p_exchange("POST", "/verify", nullptr,
                                     STATION_VERIFY_TIMEOUT_MS, reply);
    if (result != Result::Ok) {
      return result;
    }
    const char* method = reply["method"];
    const float score = reply["score"] | -1.0f;
    if (method == nullptr || method[0] == '\0' ||
        strlen(method) >= sizeof(out.method) || !reply["passed"].is<bool>() ||
        !reply["score"].is<float>() || score < 0.0f || score > 1.0f) {
      Serial.println("station: /verify reply unreadable");
      return Result::NoReply;
    }
    strlcpy(out.method, method, sizeof(out.method));
    out.passed = reply["passed"];
    out.score = score;
    return Result::Ok;
  }

  /** Stores the empty flap as the reference image. */
  Result takeReference() {
    JsonDocument reply;
    const Result result = p_exchange("POST", "/reference", nullptr,
                                     STATION_VERIFY_TIMEOUT_MS, reply);
    if (result != Result::Ok) {
      return result;
    }
    if (!(reply["stored"] | false)) {
      Serial.println("station: /reference reply unreadable");
      return Result::NoReply;
    }
    return Result::Ok;
  }

  /** Shows a screen. `body` is the whole request: `screen` and `values`. */
  Result showScreen(const JsonDocument& body) {
    char text[sp_SCREEN_BODY_LENGTH];
    if (measureJson(body) >= sizeof(text)) {
      Serial.println("station: screen body does not fit");
      return Result::NoReply;
    }
    serializeJson(body, text, sizeof(text));
    JsonDocument reply;
    return p_exchange("PUT", "/screen", text, STATION_TIMEOUT_MS, reply);
  }

  /** The `code` from the last error reply, e.g. "CAMERA_UNAVAILABLE". */
  const char* lastErrorCode() { return p_lastErrorCode; }

 private:
  static constexpr const char* sp_PATH_PREFIX = "/station/v1";
  // Fits the longest code in §11.8, with room to spare.
  static constexpr size_t sp_ERROR_CODE_LENGTH = 32;
  // Settings caps the base URL at 128 characters; the rest is the path.
  static constexpr size_t sp_URL_LENGTH = 160;
  // A screen with the longest plain-language message, with room to spare.
  static constexpr size_t sp_SCREEN_BODY_LENGTH = 256;
  // A token is at most 64 characters (§11.4).
  static constexpr size_t sp_TOKEN_LENGTH = 65;

  /**
   * One HTTP request and its JSON reply.
   *
   * Ok means a 2xx with a JSON body; the caller checks that body's shape. A
   * reply in the error shape is Error, with its code kept for
   * lastErrorCode(). Anything else — no connection, a timeout, a body that is
   * not JSON — is NoReply.
   */
  Result p_exchange(const char* method, const char* endpoint, const char* body,
                    unsigned long timeoutMs, JsonDocument& reply) {
    p_lastErrorCode[0] = '\0';
    if (WiFi.status() != WL_CONNECTED) {
      Serial.printf("station: %s skipped, Wi-Fi not connected\n", endpoint);
      return Result::NoReply;
    }

    char url[sp_URL_LENGTH];
    const String baseUrl = p_settings.stationBaseUrl();
    const int urlLength = snprintf(url, sizeof(url), "%s%s%s", baseUrl.c_str(),
                                   sp_PATH_PREFIX, endpoint);
    if (baseUrl.isEmpty() || urlLength >= static_cast<int>(sizeof(url))) {
      Serial.printf("station: %s skipped, no usable bin station URL\n",
                    endpoint);
      return Result::NoReply;
    }

    HTTPClient http;
    // HTTP/1.0 rules out a chunked reply, so the JSON can be parsed straight
    // from the stream without first copying the body into a String.
    http.useHTTP10(true);
    http.setConnectTimeout(timeoutMs);
    http.setTimeout(timeoutMs);
    if (!http.begin(p_client, url)) {
      Serial.printf("station: %s could not start the request\n", endpoint);
      return Result::NoReply;
    }
    http.addHeader("Accept", "application/json");
    if (body != nullptr) {
      http.addHeader("Content-Type", "application/json");
    }

    const char* sentBody = body != nullptr ? body : "";
    const int status = http.sendRequest(
        method, reinterpret_cast<uint8_t*>(const_cast<char*>(sentBody)),
        strlen(sentBody));
    if (status <= 0) {
      Serial.printf("station: %s failed, %s\n", endpoint,
                    HTTPClient::errorToString(status).c_str());
      http.end();
      return Result::NoReply;
    }

    const DeserializationError error = deserializeJson(reply, http.getStream());
    http.end();
    if (error) {
      Serial.printf("station: %s answered %d, not JSON\n", endpoint, status);
      return Result::NoReply;
    }
    if (status >= 200 && status < 300) {
      return Result::Ok;
    }

    const char* code = reply["error"]["code"];
    if (code == nullptr) {
      Serial.printf("station: %s answered %d, not the error shape\n", endpoint,
                    status);
      return Result::NoReply;
    }
    strlcpy(p_lastErrorCode, code, sizeof(p_lastErrorCode));
    Serial.printf("station: %s answered %d %s\n", endpoint, status,
                  p_lastErrorCode);
    return Result::Error;
  }

  // Where the bin station is.
  Settings& p_settings;
  WiFiClient p_client;
  char p_lastErrorCode[sp_ERROR_CODE_LENGTH] = "";

  char p_token[sp_TOKEN_LENGTH] = "";
  bool p_hasToken = false;
  bool p_hasCancel = false;
};
