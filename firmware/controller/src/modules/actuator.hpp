#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"
#include "../fault_code.hpp"
#include "interlock.hpp"

/**
 * The sorting flap's actuator. System_Plan.md §2 and §8.2.
 *
 * Hides the BTS7960 driver, its current sense, the flap level sensor and the
 * rest time between cycles. The actuator tips the weighing flap: extending
 * tips it toward the bin, retracting toward the return tray, and level is in
 * the middle of the stroke.
 *
 * The actuator's limit switches are inside it and cut its power at each end,
 * so each stroke to an end is judged by its current, and each stroke back to
 * level by the level sensor:
 *   - end of stroke: after ACTUATOR_MIN_TRAVEL_MS, the current falls to
 *     ACTUATOR_END_CURRENT_MV or below;
 *   - level: the level sensor closes; the driver stops;
 *   - jam: the current stays above ACTUATOR_JAM_CURRENT_MV for ACTUATOR_JAM_MS;
 *     the driver is disabled at once;
 *   - timeout: neither within ACTUATOR_STROKE_TIMEOUT_MS; the driver is
 *     disabled.
 *
 * An open service panel disables the driver at once, mid-stroke included.
 *
 * Testing to record (guidelines, Step 5 and 13): transfer and return success
 * rate and response time over at least ten trials each; the current at the end
 * of a stroke and at a forced stall; that the flap stops level every time.
 */
class Actuator {
 public:
  /** One cycle: a stroke to an end, then back to level. */
  struct CycleResult {
    // The stroke to the end was confirmed by its current. For a transfer this
    // is `actuator_ok` (API_Design.md §5.6).
    bool endReached;
    // The flap came back to level.
    bool levelReached;
    unsigned long cycleMs;  // sent as cycle_ms
    // A fault to latch, or None. An open service panel is not a fault.
    FaultCode fault;
  };

  explicit Actuator(Interlock& interlock) : p_interlock(interlock) {}

  /** Sets up the driver, disabled. */
  bool begin() { return false; }

  /**
   * Tips the item into the bin: extend to the end, then back to level. Never
   * called before the server has answered `accepted: true`
   * (API_Design.md §5.5).
   *
   * A failed return to level after a good extend still credits the student,
   * but latches ACTUATOR_RETRACT_FAILED.
   */
  CycleResult transfer() { return {false, false, 0, FaultCode::None}; }

  /**
   * Slides the item out to the return tray: retract to the end, then back to
   * level. Also the way back to level from an unknown position, such as after
   * a reset mid-stroke: whatever is on the flap ends up in the tray.
   */
  CycleResult returnItem() { return {false, false, 0, FaultCode::None}; }

  /** The flap level sensor reads closed. False when it has no pin. */
  bool isLevel() { return false; }

  /** The actuator has rested ACTUATOR_REST_MS since its last cycle. */
  bool isRested() { return false; }

  /** Cuts the drive at once. */
  void disable() {}

 private:
  Interlock& p_interlock;
};
