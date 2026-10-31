#pragma once

#include <Arduino.h>

#include "station.hpp"

/**
 * The QR scanner module. System_Plan.md §8.2.
 *
 * The scanner is the GM865 on the Pi, run by the bin station; this module
 * reaches it through Station (API_Design.md §11.3 and §11.4). It gives the main
 * loop arm, disarm and the scanned token.
 *
 * The scanner is armed only while the bin is ready, and disarmed as soon as a
 * token is taken, so a scan from before the bin was ready, or a second scan
 * mid-deposit, can never open a session.
 *
 * The token is opaque — the firmware must never try to interpret it, and must
 * never treat a scan as proof of anything. Only the server can say whether a
 * token belongs to an active user (API_Design.md §5.4).
 *
 * Testing to record (guidelines, Step 5): detection at different practical
 * distances and in different lighting, through the bin station.
 */
class QrScanner {
 public:
  explicit QrScanner(Station& station) : p_station(station) {}

  /** Starts scanning. False if the bin station did not arm the scanner. */
  bool arm() { return false; }

  /** Stops scanning. */
  bool disarm() { return false; }

  /** The scanned token, if one was collected by Station::pollEvents(). */
  bool takeToken(String& token) { return false; }

 private:
  Station& p_station;
};
