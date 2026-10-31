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
  bool begin() {
    pinMode(PIN_DRIVER_ENABLE, OUTPUT);
    digitalWrite(PIN_DRIVER_ENABLE, LOW);
    ledcSetup(LEDC_CHANNEL_EXTEND, LEDC_FREQUENCY_HZ, LEDC_RESOLUTION_BITS);
    ledcSetup(LEDC_CHANNEL_RETRACT, LEDC_FREQUENCY_HZ, LEDC_RESOLUTION_BITS);
    ledcAttachPin(PIN_DRIVER_EXTEND, LEDC_CHANNEL_EXTEND);
    ledcAttachPin(PIN_DRIVER_RETRACT, LEDC_CHANNEL_RETRACT);
    disable();

    if (PIN_FLAP_LEVEL == PIN_UNASSIGNED) {
      Serial.println("actuator: no flap level sensor, the actuator won't move");
      return false;
    }
    pinMode(static_cast<uint8_t>(PIN_FLAP_LEVEL), INPUT_PULLUP);
    return true;
  }

  /**
   * Tips the item into the bin: extend to the end, then back to level. Never
   * called before the server has answered `accepted: true`
   * (API_Design.md §5.5).
   *
   * A failed return to level after a good extend still credits the student,
   * but latches ACTUATOR_RETRACT_FAILED. A jam or timeout on the extend
   * itself latches ACTUATOR_JAM or ACTUATOR_TIMEOUT, and the flap is left
   * where it stopped.
   */
  CycleResult transfer() {
    return p_cycle(Direction::Extend, FaultCode::ActuatorRetractFailed);
  }

  /**
   * Slides the item out to the return tray: retract to the end, then back to
   * level. Also the way back to level from an unknown position, such as after
   * a reset mid-stroke: whatever is on the flap ends up in the tray. A jam or
   * timeout on either stroke latches ACTUATOR_JAM or ACTUATOR_TIMEOUT.
   */
  CycleResult returnItem() {
    return p_cycle(Direction::Retract, FaultCode::None);
  }

  /** The flap level sensor reads closed. False when it has no pin. */
  bool isLevel() {
    if (PIN_FLAP_LEVEL == PIN_UNASSIGNED) {
      return false;
    }
    // A switch that closes when the flap is level (System_Plan.md §2), to
    // ground, so closed reads LOW.
    return digitalRead(static_cast<uint8_t>(PIN_FLAP_LEVEL)) == LOW;
  }

  /** The actuator has rested ACTUATOR_REST_MS since its last cycle. */
  bool isRested() {
    return !p_hasRun || millis() - p_lastCycleEndMs >= ACTUATOR_REST_MS;
  }

  /** Cuts the drive at once. */
  void disable() {
    ledcWrite(LEDC_CHANNEL_EXTEND, 0);
    ledcWrite(LEDC_CHANNEL_RETRACT, 0);
    digitalWrite(PIN_DRIVER_ENABLE, LOW);
  }

 private:
  enum class Direction { Extend, Retract };
  enum class Target { End, Level };
  enum class Stroke { Reached, Jam, Timeout, PanelOpen };

  // How often a stroke checks its current, the level sensor and the panel.
  static constexpr unsigned long sp_STEP_MS = 1;

  static Direction sp_opposite(Direction direction) {
    return direction == Direction::Extend ? Direction::Retract
                                          : Direction::Extend;
  }

  static FaultCode sp_faultFor(Stroke stroke) {
    switch (stroke) {
      case Stroke::Jam:
        return FaultCode::ActuatorJam;
      case Stroke::Timeout:
        return FaultCode::ActuatorTimeout;
      case Stroke::Reached:
      case Stroke::PanelOpen:
        return FaultCode::None;
    }
    return FaultCode::None;
  }

  /**
   * A stroke to the end in `toEnd`, then back to level. `levelFault` replaces
   * the fault of a failed return to level when it is not None — which is how
   * a transfer reports ACTUATOR_RETRACT_FAILED.
   */
  CycleResult p_cycle(Direction toEnd, FaultCode levelFault) {
    CycleResult result{false, false, 0, FaultCode::None};
    // Without a level sensor the flap can't be brought back to level, and the
    // stroke back would run on to the far end: don't move at all.
    if (PIN_FLAP_LEVEL == PIN_UNASSIGNED) {
      return result;
    }

    const unsigned long startMs = millis();
    const Stroke end = p_stroke(toEnd, Target::End);
    result.endReached = end == Stroke::Reached;
    if (result.endReached) {
      const Stroke level = p_stroke(sp_opposite(toEnd), Target::Level);
      result.levelReached = level == Stroke::Reached;
      result.fault = sp_faultFor(level);
      if (result.fault != FaultCode::None && levelFault != FaultCode::None) {
        result.fault = levelFault;
      }
    } else {
      result.fault = sp_faultFor(end);
    }
    result.cycleMs = millis() - startMs;
    p_hasRun = true;
    p_lastCycleEndMs = millis();

    Serial.printf("actuator: %s, end %s, level %s, %lu ms\n",
                  toEnd == Direction::Extend ? "transfer" : "return",
                  result.endReached ? "reached" : "not reached",
                  result.levelReached ? "reached" : "not reached",
                  result.cycleMs);
    return result;
  }

  /** Drives in `direction` until `target`, a jam, the timeout or an open
   *  panel. The driver is disabled whichever comes first. */
  Stroke p_stroke(Direction direction, Target target) {
    if (p_interlock.isOpen()) {
      return Stroke::PanelOpen;
    }
    p_drive(direction);

    const unsigned long startMs = millis();
    bool high = false;
    unsigned long highSinceMs = 0;
    bool level = false;
    unsigned long levelSinceMs = 0;
    while (true) {
      const unsigned long nowMs = millis();
      if (p_interlock.isOpen()) {
        disable();
        return Stroke::PanelOpen;
      }

      const uint32_t currentMv = analogReadMilliVolts(PIN_ACTUATOR_CURRENT);
      if (currentMv > static_cast<uint32_t>(ACTUATOR_JAM_CURRENT_MV)) {
        if (!high) {
          high = true;
          highSinceMs = nowMs;
        } else if (nowMs - highSinceMs >= ACTUATOR_JAM_MS) {
          disable();
          Serial.printf("actuator: jam, %u mV\n", currentMv);
          return Stroke::Jam;
        }
      } else {
        high = false;
      }

      if (target == Target::End) {
        if (nowMs - startMs >= ACTUATOR_MIN_TRAVEL_MS &&
            currentMv <= static_cast<uint32_t>(ACTUATOR_END_CURRENT_MV)) {
          disable();
          return Stroke::Reached;
        }
      } else if (isLevel()) {
        if (!level) {
          level = true;
          levelSinceMs = nowMs;
        } else if (nowMs - levelSinceMs >= FLAP_LEVEL_DEBOUNCE_MS) {
          disable();
          return Stroke::Reached;
        }
      } else {
        level = false;
      }

      if (nowMs - startMs >= ACTUATOR_STROKE_TIMEOUT_MS) {
        disable();
        Serial.println("actuator: stroke timed out");
        return Stroke::Timeout;
      }
      delay(sp_STEP_MS);
    }
  }

  void p_drive(Direction direction) {
    // The idle side first, so the two halves never drive against each other.
    if (direction == Direction::Extend) {
      ledcWrite(LEDC_CHANNEL_RETRACT, 0);
      digitalWrite(PIN_DRIVER_ENABLE, HIGH);
      ledcWrite(LEDC_CHANNEL_EXTEND, ACTUATOR_PWM_DUTY);
    } else {
      ledcWrite(LEDC_CHANNEL_EXTEND, 0);
      digitalWrite(PIN_DRIVER_ENABLE, HIGH);
      ledcWrite(LEDC_CHANNEL_RETRACT, ACTUATOR_PWM_DUTY);
    }
  }

  Interlock& p_interlock;
  bool p_hasRun = false;
  unsigned long p_lastCycleEndMs = 0;
};
