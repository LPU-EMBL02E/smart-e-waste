#pragma once

#include <Arduino.h>

/**
 * The bin-level module. System_Plan.md §8.2.
 *
 * Hides the ultrasonic sensor and gives the main loop a distance in
 * millimetres. It does NOT decide whether the bin is full: the server computes
 * fill level from this distance and the bin's empty_distance_mm, so the
 * threshold can be changed from the admin page without a reflash
 * (System_Plan.md §5.3 rule 5).
 */
namespace bin_level {

bool begin();

/**
 * One distance reading in millimetres, or a negative value if the sensor did
 * not answer. Median of a few pulses: a single ultrasonic reading off a sloped
 * pile of e-waste is noisy.
 */
float readDistanceMm();

}  // namespace bin_level
