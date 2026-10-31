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
 */
class Interlock {
 public:
  explicit Interlock() = default;

  bool begin() { return false; }

  /** True when the service panel is open, or its state is unknown. */
  bool isOpen() { return true; }
};
