#pragma once

#include <Arduino.h>

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
namespace scale {

struct Reading {
  float grams;
  bool stable;     // sent as weight_stable; the server rejects an unstable one
  int samples;     // sent as samples
};

bool begin();

/** Apply the offset and scale factor fetched from the server. */
void applyCalibration(float offset, float scaleFactor);

/** Zero the platform. Call when the chamber is known to be empty. */
void tare();

/**
 * Average SCALE_SAMPLE_COUNT readings and report whether they agreed within
 * SCALE_STABLE_TOLERANCE_G. Gives up after SCALE_SETTLE_TIMEOUT_MS and returns
 * stable = false rather than blocking the sequence.
 */
Reading readStable();

/** A single raw reading, for the calibration procedure and for diagnostics. */
long readRaw();

}  // namespace scale
