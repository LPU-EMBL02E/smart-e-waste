#pragma once

#include <Arduino.h>
#include <SoftwareSerial.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

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
class QrScanner {
  /*
   * TODO: implement with EspSoftwareSerial on PIN_QR_RX, receive only, 9600
   * baud.
   *
   * Most scanner modules end a scan with CR, LF or both: read until a
   * terminator, trim it, and ignore an empty line. Cap the buffer at 64
   * characters — the server rejects anything longer, since that is the column
   * width.
   *
   * Testing to record (guidelines, Step 5): detection at different practical
   * distances and in different lighting.
   */

 public:
  explicit QrScanner() = default;

  bool begin() { return false; }

  /** True when a complete token has been read and is waiting. */
  bool hasToken() { return false; }

  /** Takes the pending token and clears it. Empty when there is none. */
  String takeToken() { return String(); }

  /** Drop anything buffered — call when a session ends, so a stray scan during
   *  a deposit does not open the next session unexpectedly. */
  void flush() {}
};
