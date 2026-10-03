#include "settings.h"

/**
 * TODO: implement with Preferences (NVS) and WiFiManager.
 *
 * Suggested NVS namespace "ewaste", keys: api_url, dev_code, dev_secret, and
 * for the pending request pc_session, pc_ok, pc_cycle.
 *
 * Add the three device fields as WiFiManagerParameters so one portal visit sets
 * the Wi-Fi credentials and the server details together.
 */

namespace settings {

bool begin() { return false; }
bool ensureProvisioned() { return false; }
String apiBaseUrl() { return String(); }
String deviceCode() { return String(); }
String deviceSecret() { return String(); }
bool save(const String&, const String&, const String&) { return false; }
bool savePendingComplete(const String&, bool, unsigned long) { return false; }
bool hasPendingComplete() { return false; }
bool loadPendingComplete(String&, bool&, unsigned long&) { return false; }
void clearPendingComplete() {}

}  // namespace settings
