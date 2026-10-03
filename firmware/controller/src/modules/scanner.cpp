#include "scanner.h"

#include <SoftwareSerial.h>

#include "../../include/config.h"
#include "../../include/pins.h"

/**
 * TODO: implement with EspSoftwareSerial on PIN_QR_RX, receive only, 9600 baud.
 *
 * Most scanner modules end a scan with CR, LF or both: read until a terminator,
 * trim it, and ignore an empty line. Cap the buffer at 64 characters — the
 * server rejects anything longer, since that is the column width.
 *
 * Testing to record (guidelines, Step 5): detection at different practical
 * distances and in different lighting.
 */

namespace scanner {

bool begin() { return false; }
bool hasToken() { return false; }
String takeToken() { return String(); }
void flush() {}

}  // namespace scanner
