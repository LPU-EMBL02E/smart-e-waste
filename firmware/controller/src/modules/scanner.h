#pragma once

#include <Arduino.h>

/**
 * The QR scanner module. System_Plan.md §8.2.
 *
 * Hides the scanner and its serial link, and gives the main loop one thing: the
 * token that was scanned. Replacing the scanner with another model, or with a
 * camera-based decoder, is a change to this file only.
 *
 * The token is opaque — the firmware must never try to interpret it, and must
 * never treat a scan as proof of anything. Only the server can say whether a
 * token belongs to an active user (API_Design.md §5.4).
 */
namespace scanner {

bool begin();

/** True when a complete token has been read and is waiting. */
bool hasToken();

/** Takes the pending token and clears it. Empty when there is none. */
String takeToken();

/** Drop anything buffered — call when a session ends, so a stray scan during
 *  a deposit does not open the next session unexpectedly. */
void flush();

}  // namespace scanner
