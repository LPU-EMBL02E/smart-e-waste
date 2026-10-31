#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

/**
 * The bin-level module. System_Plan.md §8.2.
 *
 * Hides the ultrasonic sensor and gives the main loop a distance in
 * millimetres. It does NOT decide whether the bin is full: the server computes
 * fill level from this distance and the bin's empty_distance_mm, so the
 * threshold can be changed from the admin page without a reflash
 * (System_Plan.md §5.3 rule 5).
 */
class BinLevel {
  /*
   * TODO: implement with pulseIn on PIN_ULTRASONIC_TRIGGER /
   * PIN_ULTRASONIC_ECHO.
   *
   * The HC-SR04 runs on 5 V; its echo reaches PIN_ULTRASONIC_ECHO through a
   * 1 kΩ / 2 kΩ divider (System_Plan.md §2).
   *
   * Take several pulses and return the median, not the mean: an outlier off
   * the side of a pile skews a mean but not a median. Return a negative value
   * on timeout rather than a plausible-looking distance — a wrong distance
   * becomes a wrong fill level, and a wrong fill level either blocks a working
   * bin or hides a full one.
   *
   * Testing to record (guidelines, Step 5): sensor readings against actual
   * measured distances, with percentage error.
   */

 public:
  explicit BinLevel() = default;

  bool begin() { return false; }

  /**
   * One distance reading in millimetres, or a negative value if the sensor did
   * not answer. Median of a few pulses: a single ultrasonic reading off a
   * sloped pile of e-waste is noisy.
   */
  float readDistanceMm() { return -1.0f; }
};
