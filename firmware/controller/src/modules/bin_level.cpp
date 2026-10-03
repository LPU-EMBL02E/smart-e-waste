#include "bin_level.h"

#include "../../include/config.h"
#include "../../include/pins.h"

/**
 * TODO: implement with pulseIn on PIN_ULTRASONIC_TRIGGER / PIN_ULTRASONIC_ECHO.
 *
 * Remember PIN_ULTRASONIC_ECHO (GPIO 35) is input-only and needs a voltage
 * divider if the sensor runs on 5 V.
 *
 * Take several pulses and return the median, not the mean: an outlier off the
 * side of a pile skews a mean but not a median. Return a negative value on
 * timeout rather than a plausible-looking distance — a wrong distance becomes a
 * wrong fill level, and a wrong fill level either blocks a working bin or hides
 * a full one.
 *
 * Testing to record (guidelines, Step 5): sensor readings against actual
 * measured distances, with percentage error.
 */

namespace bin_level {

bool begin() { return false; }
float readDistanceMm() { return -1.0f; }

}  // namespace bin_level
