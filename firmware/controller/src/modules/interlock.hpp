#pragma once

#include <Arduino.h>

#include "../../include/pins.hpp"

/**
 * The service-panel interlock. System_Plan.md §2 and §8.2.
 *
 * Hides the switch on the service panel and gives one answer: open or closed.
 * Opening the panel disables the actuator at once, mid-stroke included, so the
 * Actuator asks this on every step of a stroke.
 *
 * Fail closed: with no pin assigned, or a reading it can't trust, the panel is
 * open.
 *
 * Testing to record (firmware/README.md): the driver disables at once when the
 * panel opens, mid-stroke included.
 */
class Interlock {
 public:
  explicit Interlock() = default;

  bool begin() {
    if (PIN_INTERLOCK == PIN_UNASSIGNED) {
      Serial.println("interlock: no pin assigned, the panel reads as open");
      return false;
    }
    pinMode(static_cast<uint8_t>(PIN_INTERLOCK), INPUT_PULLUP);
    return true;
  }

  /**
   * True when the service panel is open, or its state is unknown.
   *
   * TODO(team): confirm against the wiring once the pin is assigned. This
   * expects a switch to ground that is closed while the panel is shut, so a
   * cut wire or an unplugged switch reads as open.
   */
  bool isOpen() {
    if (PIN_INTERLOCK == PIN_UNASSIGNED) {
      return true;
    }
    return digitalRead(static_cast<uint8_t>(PIN_INTERLOCK)) != LOW;
  }
};
