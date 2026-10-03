#include "feedback.h"

#include "../../include/config.h"
#include "../../include/pins.h"

/**
 * TODO: implement over UART 2 (PIN_PANEL_RX / PIN_PANEL_TX) using the line
 * protocol in firmware/README.md, plus the two LEDs and the buzzer.
 *
 *   -> SCREEN <name> [values]
 *   <- TOUCH CANCEL
 *
 * One line per screen change, one line per touch. Keep it one-way as far as
 * possible: the panel must never become a place where state lives, or a panel
 * reboot mid-deposit would lose part of the transaction.
 *
 * The LEDs and buzzer must work even when the panel is unreachable — they are
 * the fallback that tells a student standing at the bin whether anything
 * happened.
 */

namespace feedback {

bool begin() { return false; }
void show(Screen, const String&) {}
bool cancelPressed() { return false; }
void clearCancel() {}
void success() {}
void failure() {}
void busy() {}
void idle() {}

}  // namespace feedback
