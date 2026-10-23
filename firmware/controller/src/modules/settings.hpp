#pragma once

#include <Arduino.h>

/**
 * Non-volatile storage and the setup portal. System_Plan.md §8.2.
 *
 * Hides where the server is and who this bin is. Nothing else in the firmware
 * knows that these values are stored in NVS, and nothing else may read them
 * from anywhere else — that is what lets the bin be re-pointed at a new server
 * through the portal instead of being reflashed at handover.
 *
 * The secret is never logged, never sent over the wire and never compiled in.
 */
class Settings {
  /*
   * TODO: implement with Preferences (NVS) and WiFiManager.
   *
   * Suggested NVS namespace "ewaste", keys: api_url, dev_code, dev_secret, and
   * for the pending request pc_session, pc_ok, pc_cycle.
   *
   * Add the three device fields as WiFiManagerParameters so one portal visit
   * sets the Wi-Fi credentials and the server details together.
   */

 public:
  explicit Settings() = default;

  bool begin() { return false; }

  /** Opens the WiFiManager portal when no Wi-Fi credentials are stored. */
  bool ensureProvisioned() { return false; }

  /** Scheme, host and optional port only, e.g. "http://192.168.1.10" (P1). */
  String apiBaseUrl() { return String(); }

  /** Sent as the X-Device-Code header. */
  String deviceCode() { return String(); }

  /** The HMAC signing secret, 64 hex characters as issued by the server. */
  String deviceSecret() { return String(); }

  bool save(const String& baseUrl, const String& deviceCode,
            const String& secret) {
    return false;
  }

  /**
   * The unsent `complete` call, kept across reboots.
   *
   * API_Design.md §3.6: after an item is physically deposited, three failed
   * retries mean the request is stored here, the student is shown that points
   * are pending, and it is resent before the next deposit is accepted.
   */
  bool savePendingComplete(const String& sessionId, bool actuatorOk,
                           unsigned long cycleMs) {
    return false;
  }
  bool hasPendingComplete() { return false; }
  bool loadPendingComplete(String& sessionId, bool& actuatorOk,
                           unsigned long& cycleMs) {
    return false;
  }
  void clearPendingComplete() {}
};
