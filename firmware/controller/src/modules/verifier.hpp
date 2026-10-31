#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"
#include "station.hpp"

/**
 * The item-verification module. System_Plan.md §8.2 and §8.3.
 *
 * Hides the camera check, run by the bin station on the Pi, and the LED strip
 * that lights the flap for it. The check has two steps: presence by frame
 * differencing, then the e-waste model. The answer names the step that
 * decided: `cam_diff` or `ewaste_model` (P49).
 *
 * The strip is on only for a check or a new reference, so the camera sees the
 * same light every time.
 *
 * The three values a check returns — method, passed, score — are copied
 * unchanged into the deposit call (API_Design.md §5.5). No answer is never a
 * pass: on any failure the caller cancels the session and returns the item.
 *
 * Testing to record (guidelines, Step 5 and 13): successful verification rate
 * over at least ten trials with and without an item; e-waste and non-e-waste
 * items, with the model's accept and reject counts; the time a check takes.
 */
class Verifier {
 public:
  explicit Verifier(Station& station) : p_station(station) {}

  /** Sets up the LED strip, off. */
  bool begin() {
    ledcSetup(LEDC_CHANNEL_LED_STRIP, LEDC_FREQUENCY_HZ, LEDC_RESOLUTION_BITS);
    ledcAttachPin(PIN_LED_STRIP, LEDC_CHANNEL_LED_STRIP);
    p_light(false);
    return true;
  }

  /** Lights the flap, asks the bin station for the camera check, and switches
   *  the light off again. */
  Station::Result verify(Station::Verdict& out) {
    p_light(true);
    const Station::Result result = p_station.verify(out);
    p_light(false);
    if (result == Station::Result::Ok) {
      Serial.printf("verifier: %s %s, score %.4f\n", out.method,
                    out.passed ? "passed" : "failed", out.score);
    }
    return result;
  }

  /**
   * Lights the empty flap and asks the bin station to store it as the
   * reference. Call only when the scale reads an empty flap and the flap is
   * level: at boot, after each transfer or return, and whenever health
   * reports no reference (API_Design.md §11.6).
   */
  Station::Result captureEmptyReference() {
    p_light(true);
    const Station::Result result = p_station.takeReference();
    p_light(false);
    return result;
  }

 private:
  /** Switches the strip, and on switching it on, waits for the light to
   *  settle so the picture sees the same light every time. */
  void p_light(bool on) {
    ledcWrite(LEDC_CHANNEL_LED_STRIP, on ? LED_STRIP_DUTY : 0);
    if (on) {
      delay(LED_STRIP_SETTLE_MS);
    }
  }

  Station& p_station;
};
