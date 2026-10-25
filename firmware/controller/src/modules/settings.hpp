#pragma once

#include <Arduino.h>
#include <Preferences.h>
#include <WiFi.h>
#include <WiFiManager.h>

#include "../../include/config.hpp"

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
 public:
  explicit Settings() = default;

  /** Opens the NVS namespace. Everything else here fails until this has. */
  bool begin() {
    p_ready = p_prefs.begin(sp_NAMESPACE, false);
    if (!p_ready) {
      Serial.println("settings: could not open NVS");
    }
    return p_ready;
  }

  /**
   * Opens the WiFiManager portal when the Wi-Fi details or the server details
   * are not stored yet, and stores what is entered there. True when everything
   * is stored, whether it already was or was just entered.
   *
   * Joining Wi-Fi for normal use is Api::begin(), not this. A Wi-Fi drop later
   * never reopens the portal: an open setup network in a corridor would let
   * anyone nearby re-point the bin at their own server.
   *
   * TODO: a way to reopen the portal once everything is stored, for handover
   * and for a newly issued secret (API_Design.md P39). Not decided yet.
   */
  bool ensureProvisioned() {
    if (!p_ready) {
      return false;
    }

    // The Wi-Fi driver must be running before the stored network can be read.
    WiFi.mode(WIFI_STA);
    WiFiManager portal;
    if (portal.getWiFiIsSaved() && p_hasServerDetails()) {
      Serial.println("settings: using stored Wi-Fi and server details");
      return true;
    }

    // Field sizes follow the docs: device code VARCHAR(50)
    // (Database_Schema.md), secret 64 hex characters (P39). The secret is not
    // pre-filled, so it is never sent back to a browser.
    WiFiManagerParameter urlField("api_url",
                                  "Server URL, e.g. http://192.168.1.10",
                                  apiBaseUrl().c_str(), sp_URL_MAX_LENGTH);
    WiFiManagerParameter codeField("dev_code", "Device code, e.g. BIN-01",
                                   deviceCode().c_str(),
                                   sp_DEVICE_CODE_MAX_LENGTH);
    WiFiManagerParameter secretField("dev_secret", "Device secret", "",
                                     sp_SECRET_LENGTH, "type='password'");
    portal.addParameter(&urlField);
    portal.addParameter(&codeField);
    portal.addParameter(&secretField);

    // NOTIFY is the library default. Above it, the library logs every field
    // value it receives — the secret included.
    portal.setDebugOutput(true, WM_DEBUG_NOTIFY);
    portal.setConfigPortalTimeout(SETUP_PORTAL_TIMEOUT_S);

    Serial.printf("settings: setup portal open as \"%s\" for %lu s\n",
                  SETUP_PORTAL_SSID, SETUP_PORTAL_TIMEOUT_S);

    // Returns true only once the entered Wi-Fi network has been joined; a wrong
    // password keeps the portal open for another try until the timeout.
    if (!portal.startConfigPortal(SETUP_PORTAL_SSID)) {
      Serial.println("settings: setup portal closed without joining Wi-Fi");
      return false;
    }
    if (!save(urlField.getValue(), codeField.getValue(),
              secretField.getValue())) {
      return false;
    }
    Serial.println("settings: server details saved from the portal");
    return true;
  }

  /** Scheme, host and optional port only, e.g. "http://192.168.1.10" (P1). */
  String apiBaseUrl() { return p_read(sp_KEY_API_URL); }

  /** Sent as the X-Device-Code header. */
  String deviceCode() { return p_read(sp_KEY_DEVICE_CODE); }

  /** The HMAC signing secret, 64 hex characters as issued by the server. */
  String deviceSecret() { return p_read(sp_KEY_DEVICE_SECRET); }

  /**
   * Stores the server details. Refuses them, and stores nothing, unless the
   * URL is scheme, host and optional port only (P1), the device code fits its
   * column, and the secret is 64 hex characters (P39).
   */
  bool save(const String& baseUrl, const String& deviceCode,
            const String& secret) {
    if (!p_ready) {
      return false;
    }
    if (!sp_isBaseUrl(baseUrl) || deviceCode.isEmpty() ||
        deviceCode.length() > sp_DEVICE_CODE_MAX_LENGTH ||
        !sp_isSecret(secret)) {
      Serial.println("settings: server details refused, nothing saved");
      return false;
    }

    const bool saved = p_prefs.putString(sp_KEY_API_URL, baseUrl) > 0 &&
                       p_prefs.putString(sp_KEY_DEVICE_CODE, deviceCode) > 0 &&
                       p_prefs.putString(sp_KEY_DEVICE_SECRET, secret) > 0;
    if (!saved) {
      Serial.println("settings: could not write server details to NVS");
    }
    return saved;
  }

  /**
   * The unsent `complete` call, kept across reboots.
   *
   * API_Design.md §3.6: after an item is physically deposited, three failed
   * retries mean the request is stored here, the student is shown that points
   * are pending, and it is resent before the next deposit is accepted.
   *
   * The session ID is written last and removed first: it is what marks the
   * record as present, so a power cut halfway never leaves half a record that
   * reads as whole.
   */
  bool savePendingComplete(const String& sessionId, bool actuatorOk,
                           unsigned long cycleMs) {
    if (!p_ready) {
      return false;
    }
    const bool saved = p_prefs.putBool(sp_KEY_PENDING_OK, actuatorOk) > 0 &&
                       p_prefs.putULong(sp_KEY_PENDING_CYCLE, cycleMs) > 0 &&
                       p_prefs.putString(sp_KEY_PENDING_SESSION, sessionId) > 0;
    if (!saved) {
      Serial.println("settings: could not write the pending complete to NVS");
    }
    return saved;
  }

  bool hasPendingComplete() {
    return p_ready && p_prefs.isKey(sp_KEY_PENDING_SESSION);
  }

  bool loadPendingComplete(String& sessionId, bool& actuatorOk,
                           unsigned long& cycleMs) {
    if (!hasPendingComplete()) {
      return false;
    }
    sessionId = p_prefs.getString(sp_KEY_PENDING_SESSION);
    actuatorOk = p_prefs.getBool(sp_KEY_PENDING_OK);
    cycleMs = p_prefs.getULong(sp_KEY_PENDING_CYCLE);
    return true;
  }

  void clearPendingComplete() {
    if (!hasPendingComplete()) {
      return;
    }
    p_prefs.remove(sp_KEY_PENDING_SESSION);
    p_prefs.remove(sp_KEY_PENDING_OK);
    p_prefs.remove(sp_KEY_PENDING_CYCLE);
  }

 private:
  static constexpr const char* sp_NAMESPACE = "ewaste";
  static constexpr const char* sp_KEY_API_URL = "api_url";
  static constexpr const char* sp_KEY_DEVICE_CODE = "dev_code";
  static constexpr const char* sp_KEY_DEVICE_SECRET = "dev_secret";
  static constexpr const char* sp_KEY_PENDING_SESSION = "pc_session";
  static constexpr const char* sp_KEY_PENDING_OK = "pc_ok";
  static constexpr const char* sp_KEY_PENDING_CYCLE = "pc_cycle";

  // The docs set no limit on the URL; this fits an IP or hostname with a port.
  static constexpr unsigned int sp_URL_MAX_LENGTH = 128;
  static constexpr unsigned int sp_DEVICE_CODE_MAX_LENGTH = 50;
  static constexpr unsigned int sp_SECRET_LENGTH = 64;

  /** "http://" or "https://", then a host and optional port, and no path. */
  static bool sp_isBaseUrl(const String& url) {
    unsigned int hostStart = 0;
    if (url.startsWith("http://")) {
      hostStart = 7;
    } else if (url.startsWith("https://")) {
      hostStart = 8;
    } else {
      return false;
    }
    if (url.length() <= hostStart || url.length() > sp_URL_MAX_LENGTH) {
      return false;
    }
    for (unsigned int i = hostStart; i < url.length(); i++) {
      if (url[i] == '/' || url[i] == '?' || url[i] == '#' || url[i] == ' ') {
        return false;
      }
    }
    return true;
  }

  static bool sp_isSecret(const String& secret) {
    if (secret.length() != sp_SECRET_LENGTH) {
      return false;
    }
    for (unsigned int i = 0; i < secret.length(); i++) {
      if (!isxdigit(static_cast<unsigned char>(secret[i]))) {
        return false;
      }
    }
    return true;
  }

  bool p_hasServerDetails() {
    return p_prefs.isKey(sp_KEY_API_URL) && p_prefs.isKey(sp_KEY_DEVICE_CODE) &&
           p_prefs.isKey(sp_KEY_DEVICE_SECRET);
  }

  /** Empty when the key is missing. Checked first, because Preferences logs an
   *  error for every read of a missing key. */
  String p_read(const char* key) {
    if (!p_ready || !p_prefs.isKey(key)) {
      return String();
    }
    return p_prefs.getString(key);
  }

  Preferences p_prefs;
  bool p_ready = false;
};
