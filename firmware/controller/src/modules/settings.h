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
namespace settings {

bool begin();

/** Opens the WiFiManager portal when no Wi-Fi credentials are stored. */
bool ensureProvisioned();

/** Scheme, host and optional port only, e.g. "http://192.168.1.10" (P1). */
String apiBaseUrl();

/** Sent as the X-Device-Code header. */
String deviceCode();

/** The HMAC signing secret, 64 hex characters as issued by the server. */
String deviceSecret();

bool save(const String& baseUrl, const String& deviceCode, const String& secret);

/**
 * The unsent `complete` call, kept across reboots.
 *
 * API_Design.md §3.6: after an item is physically deposited, three failed
 * retries mean the request is stored here, the student is shown that points are
 * pending, and it is resent before the next deposit is accepted.
 */
bool savePendingComplete(const String& sessionId, bool actuatorOk, unsigned long cycleMs);
bool hasPendingComplete();
bool loadPendingComplete(String& sessionId, bool& actuatorOk, unsigned long& cycleMs);
void clearPendingComplete();

}  // namespace settings
