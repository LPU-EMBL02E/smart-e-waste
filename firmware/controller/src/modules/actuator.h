#pragma once

#include <Arduino.h>

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
namespace actuator {

struct CycleResult {
  bool ok;                  // sent as actuator_ok: true ONLY if both switches confirmed
  unsigned long cycleMs;    // sent as cycle_ms
};

bool begin();

/** Push the item into the bin and return home. Never called before the server
 *  has answered `accepted: true` (API_Design.md §5.5). */
CycleResult transfer();

/** Drive back to the home switch. Call at boot, before anything is weighed. */
bool returnHome();

bool isAtHome();

/** Cut the drive at once. For a jam and for the Cancel path. */
void stop();

}  // namespace actuator
