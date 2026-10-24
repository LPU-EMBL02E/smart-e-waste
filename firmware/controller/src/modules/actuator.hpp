#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

/**
 * The transfer mechanism. System_Plan.md §8.2.
 *
 * Hides the motor driver and the two limit switches, and reports whether the
 * item actually went into the bin, plus how long the cycle took.
 *
 * The limit switches are what make the report meaningful. Without them
 * `actuator_ok` would be a guess, and the API's actuator confirmation — the
 * last gate before points are credited — would mean nothing
 * (System_Plan.md §2).
 */
class Actuator {
  /*
   * TODO: implement with ledcWrite on PIN_MOTOR_PWM and the two direction pins,
   * and INPUT_PULLUP on both limit switches (a closed switch reads LOW).
   *
   * The cycle:
   *   1. confirm the home switch is closed; if not, returnHome() first;
   *   2. drive toward the end switch, stop as soon as it closes;
   *   3. drive back until the home switch closes;
   *   4. ok = both switches were reached inside ACTUATOR_TIMEOUT_MS.
   *
   * On a timeout: stop() immediately and report ok = false. Do not retry on
   * the spot — a mechanism that did not reach its switch is jammed or
   * obstructed, and driving it again risks the motor and the enclosure. The
   * session becomes FAILED, no points are credited, and an admin sees it.
   *
   * Debounce both switches. A bounce read as an early arrival would report a
   * transfer that did not happen, which is the one failure mode here that
   * credits points wrongly.
   *
   * TODO(team): System_Plan.md §10 — choose the actuator model and check its
   * stall current against the driver before fabrication. The TB6612FNG in the
   * original parts list handles about 1.2 A continuous per channel.
   *
   * Testing to record (guidelines, Step 5 and 13): repeated extension and
   * retraction, successful operation rate and response time over at least ten
   * trials.
   */

 public:
  struct CycleResult {
    bool ok;  // sent as actuator_ok: true ONLY if both switches confirmed
    unsigned long cycleMs;  // sent as cycle_ms
  };

  explicit Actuator() = default;

  bool begin() { return false; }

  /** Push the item into the bin and return home. Never called before the
   *  server has answered `accepted: true` (API_Design.md §5.5). */
  CycleResult transfer() { return {false, 0}; }

  /** Drive back to the home switch. Call at boot, before anything is
   *  weighed. */
  bool returnHome() { return false; }

  bool isAtHome() { return false; }

  /** Cut the drive at once. For a jam and for the Cancel path. */
  void stop() {}
};
