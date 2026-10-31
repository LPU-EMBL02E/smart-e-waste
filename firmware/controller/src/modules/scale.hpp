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
class Scale {
 public:
  struct Reading {
    float grams;
    bool stable;  // sent as weight_stable; the server rejects an unstable one
    int samples;  // sent as samples
  };

  explicit Scale() = default;

  /** Starts the amplifier and checks that it answers. */
  bool begin() {
    p_amplifier.begin(PIN_SCALE_DATA, PIN_SCALE_CLOCK);
    if (!p_amplifier.wait_ready_timeout(SCALE_READY_TIMEOUT_MS, sp_POLL_MS)) {
      Serial.println("scale: amplifier did not answer");
      return false;
    }
    return true;
  }

  /**
   * Apply the offset and scale factor fetched from the server: grams =
   * (raw - offset) / scaleFactor. A missing or zero factor is refused and the
   * previous calibration kept, since it would divide by zero.
   */
  bool applyCalibration(float offset, float scaleFactor) {
    if (!isfinite(offset) || !isfinite(scaleFactor) || scaleFactor == 0.0f) {
      Serial.println("scale: calibration refused, keeping the previous one");
      return false;
    }
    p_offset = offset;
    p_scaleFactor = scaleFactor;
    p_calibrated = true;
    return true;
  }

  /**
   * Zero the platform: the average of SCALE_SAMPLE_COUNT raw readings becomes
   * the offset. Call when the chamber is known to be empty. Leaves the offset
   * unchanged if the amplifier stops answering.
   */
  bool tare() {
    double sum = 0.0;
    for (int i = 0; i < SCALE_SAMPLE_COUNT; i++) {
      long raw = 0;
      if (!readRaw(raw)) {
        return false;
      }
      sum += raw;
    }
    p_offset = static_cast<float>(sum / SCALE_SAMPLE_COUNT);
    return true;
  }

  /**
   * Average SCALE_SAMPLE_COUNT readings and report whether they agreed within
   * SCALE_STABLE_TOLERANCE_G. Gives up after SCALE_SETTLE_TIMEOUT_MS and
   * returns stable = false rather than blocking the sequence.
   *
   * The window slides: it is stable as soon as the last SCALE_SAMPLE_COUNT
   * readings agree, so a weight that settles quickly is not held back. On a
   * timeout it reports the average of what it has, unstable — an honest
   * reading the server will reject, never a guess.
   *
   * Uncalibrated, it reports 0 g, unstable, from no samples, so a deposit
   * fails closed. TODO: the docs do not say what an uncalibrated bin should
   * do (API_Design.md §5.2 allows null calibration); decide and document it.
   */
  Reading readStable() {
    if (!p_calibrated) {
      Serial.println("scale: not calibrated");
      return {0.0f, false, 0};
    }

    float window[SCALE_SAMPLE_COUNT];
    int count = 0;
    int next = 0;
    const unsigned long started = millis();
    while (millis() - started < SCALE_SETTLE_TIMEOUT_MS) {
      long raw = 0;
      if (!readRaw(raw)) {
        break;
      }
      window[next] = (static_cast<float>(raw) - p_offset) / p_scaleFactor;
      next = (next + 1) % SCALE_SAMPLE_COUNT;
      if (count < SCALE_SAMPLE_COUNT) {
        count++;
      }
      if (count == SCALE_SAMPLE_COUNT &&
          sp_spread(window, count) <= SCALE_STABLE_TOLERANCE_G) {
        return {sp_mean(window, count), true, count};
      }
    }

    if (count == 0) {
      return {0.0f, false, 0};
    }
    return {sp_mean(window, count), false, count};
  }

  /**
   * A single raw reading, for the calibration procedure and for diagnostics.
   * False if the amplifier did not answer in time; `raw` is then unchanged.
   * A raw reading can be any value, negative included, so it cannot carry the
   * failure itself.
   */
  bool readRaw(long& raw) {
    // HX711::read() waits for the amplifier with no limit; this is the limit.
    if (!p_amplifier.wait_ready_timeout(SCALE_READY_TIMEOUT_MS, sp_POLL_MS)) {
      Serial.println("scale: amplifier did not answer");
      return false;
    }
    raw = p_amplifier.read();
    return true;
  }

 private:
  // How often to check whether the amplifier has a reading. 1 ms also lets
  // other tasks run while waiting.
  static constexpr unsigned long sp_POLL_MS = 1;

  /** Average of the first `count` values. */
  static float sp_mean(const float* values, int count) {
    float sum = 0.0f;
    for (int i = 0; i < count; i++) {
      sum += values[i];
    }
    return sum / count;
  }

  /** Largest minus smallest of the first `count` values. */
  static float sp_spread(const float* values, int count) {
    float lowest = values[0];
    float highest = values[0];
    for (int i = 1; i < count; i++) {
      lowest = min(lowest, values[i]);
      highest = max(highest, values[i]);
    }
    return highest - lowest;
  }

  HX711 p_amplifier;
  float p_offset = 0.0f;
  float p_scaleFactor = 1.0f;
  bool p_calibrated = false;
};
