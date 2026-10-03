#include "scale.h"

#include <HX711.h>

#include "../../include/config.h"
#include "../../include/pins.h"

/**
 * TODO: implement with the HX711 library on PIN_SCALE_DATA / PIN_SCALE_CLOCK.
 *
 * Calibration procedure (guidelines, Step 5):
 *   1. tare the empty platform and record the raw offset;
 *   2. place a known standard weight and record the raw reading;
 *   3. scale factor = (raw_with_weight - offset) / known_grams;
 *   4. enter both values on the bin's admin page, where they are stored on the
 *      device row and served back by GET /device/config.
 *
 * Record measured against actual for a range of known weights and keep the raw
 * table — percentage error is computed from it, and averages without the raw
 * data are not accepted as evidence (guidelines, Step 13).
 *
 * Never report a weight the firmware has adjusted for plausibility. The server
 * decides what is acceptable; the firmware's job is to measure honestly.
 */

namespace scale {

bool begin() { return false; }
void applyCalibration(float, float) {}
void tare() {}
Reading readStable() { return {0.0f, false, 0}; }
long readRaw() { return 0; }

}  // namespace scale
