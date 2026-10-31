#pragma once

#include <Arduino.h>
#include <ArduinoJson.h>

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
  Result health(Health& out) { return Result::NoReply; }

  /** Arm or disarm the scanner. Arming also drops any token read earlier. */
  Result setScannerArmed(bool armed) { return Result::NoReply; }

  /**
   * Collects the queued events: a scanned token is kept for takeToken(), a
   * Cancel touch for takeCancel(). Call every STATION_EVENT_POLL_MS while the
   * scanner is armed or a session is open (§11.4).
   */
  Result pollEvents() { return Result::NoReply; }

  /** The first token collected since the last take, if there is one. */
  bool takeToken(String& token) { return false; }

  /** True once per Cancel touch collected. */
  bool takeCancel() { return false; }

  /** Forgets any token or Cancel collected but not yet taken. */
  void clearEvents() {}

  /** The camera check: presence, then the e-waste model. */
  Result verify(Verdict& out) { return Result::NoReply; }

  /** Stores the empty flap as the reference image. */
  Result takeReference() { return Result::NoReply; }

  /** Shows a screen. `body` is the whole request: `screen` and `values`. */
  Result showScreen(const JsonDocument& body) { return Result::NoReply; }

  /** The `code` from the last error reply, e.g. "CAMERA_UNAVAILABLE". */
  const char* lastErrorCode() { return p_lastErrorCode; }

 private:
  // Fits the longest code in §11.8, with room to spare.
  static constexpr size_t sp_ERROR_CODE_LENGTH = 32;

  // Where the bin station is.
  Settings& p_settings;
  char p_lastErrorCode[sp_ERROR_CODE_LENGTH] = "";
};
