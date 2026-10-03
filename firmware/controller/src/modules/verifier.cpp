#include "verifier.h"

#include "../../include/config.h"
#include "../../include/pins.h"

/**
 * TODO: implement over UART 1 (PIN_CAMERA_RX / PIN_CAMERA_TX) using the line
 * protocol in firmware/README.md.
 *
 *   -> VERIFY            ask for a verdict
 *   <- OK <0.00-1.00>    an item is present, with a score
 *   <- FAIL <0.00-1.00>  no item
 *   -> REFERENCE         re-learn the empty platform
 *   <- READY             reference stored
 *
 * Treat no reply within a timeout as FAIL, not as a pass: the points rule says
 * points are credited only after verification PASSES, so an unreachable camera
 * must never be read as an accepted item.
 *
 * Testing to record (guidelines, Step 5): that the camera can detect an item on
 * the weighing platform, and the successful verification rate over at least ten
 * trials.
 */

namespace verifier {

bool begin() { return false; }
Verdict verify() { return {false, 0.0f, "cam_diff"}; }
bool captureEmptyReference() { return false; }
bool isResponding() { return false; }

}  // namespace verifier
