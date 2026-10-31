#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

/**
 * The bin-level module. System_Plan.md §8.2.
 *
 * Hides the HC-SR04 ultrasonic sensor and gives the main loop a distance in
 * millimetres. It does NOT decide whether the bin is full: the server computes
 * fill level from this distance and the bin's empty_distance_mm, so the
 * threshold can be changed from the admin page without a reflash
 * (System_Plan.md §5.3 rule 5).
 *
 * The HC-SR04 runs on 5 V; its echo reaches PIN_ULTRASONIC_ECHO through a
 * 1 kΩ / 2 kΩ divider (System_Plan.md §2).
 *
 * Testing to record (guidelines, Step 5): sensor readings against actual
 * measured distances, with percentage error.
 */
class BinLevel {
 public:
  explicit BinLevel() = default;

  bool begin() {
    pinMode(PIN_ULTRASONIC_TRIGGER, OUTPUT);
    digitalWrite(PIN_ULTRASONIC_TRIGGER, LOW);
    pinMode(PIN_ULTRASONIC_ECHO, INPUT);
    return true;
  }

  /**
   * One distance reading in millimetres, or a negative value if the sensor did
   * not answer.
   *
   * The median of BIN_LEVEL_PULSE_COUNT pulses, not the mean: an outlier off
   * the side of a pile skews a mean but not a median. Fewer than half the
   * pulses answering is no reading — a wrong distance becomes a wrong fill
   * level, and a wrong fill level either blocks a working bin or hides a full
   * one.
   */
  float readDistanceMm() {
    float readings[BIN_LEVEL_PULSE_COUNT];
    int count = 0;
    for (int i = 0; i < BIN_LEVEL_PULSE_COUNT; i++) {
      if (i > 0) {
        delay(BIN_LEVEL_PULSE_GAP_MS);
      }
      const unsigned long echoUs = p_pulse();
      if (echoUs > 0) {
        // There and back, so half the round trip.
        readings[count++] = echoUs * sp_SOUND_MM_PER_US / 2.0f;
      }
    }

    if (count * 2 <= BIN_LEVEL_PULSE_COUNT) {
      Serial.println("bin_level: sensor did not answer");
      return -1.0f;
    }
    return sp_median(readings, count);
  }

 private:
  // The speed of sound at about 20 °C.
  static constexpr float sp_SOUND_MM_PER_US = 0.343f;
  // The HC-SR04 starts a measurement on a trigger pulse of at least 10 µs.
  static constexpr unsigned int sp_TRIGGER_US = 10;

  /** One trigger and its echo time in microseconds; 0 if no echo came. */
  unsigned long p_pulse() {
    digitalWrite(PIN_ULTRASONIC_TRIGGER, LOW);
    delayMicroseconds(2);
    digitalWrite(PIN_ULTRASONIC_TRIGGER, HIGH);
    delayMicroseconds(sp_TRIGGER_US);
    digitalWrite(PIN_ULTRASONIC_TRIGGER, LOW);
    return pulseIn(PIN_ULTRASONIC_ECHO, HIGH, BIN_LEVEL_ECHO_TIMEOUT_US);
  }

  /** The median of the first `count` values. Sorts them in place. */
  static float sp_median(float* values, int count) {
    for (int i = 1; i < count; i++) {
      const float value = values[i];
      int j = i - 1;
      while (j >= 0 && values[j] > value) {
        values[j + 1] = values[j];
        j--;
      }
      values[j + 1] = value;
    }
    if (count % 2 == 1) {
      return values[count / 2];
    }
    return (values[count / 2 - 1] + values[count / 2]) / 2.0f;
  }
};
