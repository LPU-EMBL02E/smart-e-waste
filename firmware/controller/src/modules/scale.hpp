#pragma once

#include <Arduino.h>
#include <HX711.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

/**
 * The weighing module. System_Plan.md §8.2.
 *
 * Hides the load cell, the amplifier and the calibration, and gives the main
 * loop a stable weight in grams.
 *
 * Calibration values come from the SERVER (GET /device/config) and are entered
 * by an admin on the bin's page, so recalibrating never needs a reflash
 * (Database_Schema.md §4).
 */
class Scale {
  /*
   * TODO: implement with the HX711 library on PIN_SCALE_DATA /
   * PIN_SCALE_CLOCK.
   *
   * Calibration procedure (guidelines, Step 5):
   *   1. tare the empty platform and record the raw offset;
   *   2. place a known standard weight and record the raw reading;
   *   3. scale factor = (raw_with_weight - offset) / known_grams;
   *   4. enter both values on the bin's admin page, where they are stored on
   *      the device row and served back by GET /device/config.
   *
   * Record measured against actual for a range of known weights and keep the
   * raw table — percentage error is computed from it, and averages without the
   * raw data are not accepted as evidence (guidelines, Step 13).
   *
   * Never report a weight the firmware has adjusted for plausibility. The
   * server decides what is acceptable; the firmware's job is to measure
   * honestly.
   */

 public:
  struct Reading {
    float grams;
    bool stable;  // sent as weight_stable; the server rejects an unstable one
    int samples;  // sent as samples
  };

  explicit Scale() = default;

  bool begin() { return false; }

  /** Apply the offset and scale factor fetched from the server. */
  void applyCalibration(float offset, float scaleFactor) {}

  /** Zero the platform. Call when the chamber is known to be empty. */
  void tare() {}

  /**
   * Average SCALE_SAMPLE_COUNT readings and report whether they agreed within
   * SCALE_STABLE_TOLERANCE_G. Gives up after SCALE_SETTLE_TIMEOUT_MS and
   * returns stable = false rather than blocking the sequence.
   */
  Reading readStable() { return {0.0f, false, 0}; }

  /** A single raw reading, for the calibration procedure and for
   *  diagnostics. */
  long readRaw() { return 0; }
};
