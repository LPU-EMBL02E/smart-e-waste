/**
 * Display firmware — the ESP32-S3 panel board. System_Plan.md §2 and §8.2.
 *
 * Draws the screens with LVGL and reports touches. One line of text per screen
 * change in, one line per touch out.
 *
 * A NodeMCU cannot drive a 7" panel itself, and the Raspberry Pi is not used
 * for it — the Pi stays a pure server, which is what lets the web app move to
 * the school's own platform later (System_Plan.md §1).
 *
 * Protocol (firmware/README.md):
 *
 *   -> SCREEN <name> [values...]    draw that screen
 *   -> PING                         <- PONG
 *   <- TOUCH CANCEL                 the student pressed Cancel
 *
 * This board must never hold state the transaction depends on. If it reboots
 * mid-deposit, the controller carries on and the next SCREEN line puts the
 * panel back in step.
 *
 * TODO: implement. The display and touch initialization is board-specific —
 * fill it in from the confirmed panel's own example, and do not guess the RGB
 * timings or the touch controller's address.
 */

#include <Arduino.h>

#include "../include/screens.h"

namespace {

void drawScreen(Screen screen, const String& values) {
  (void) screen;
  (void) values;
  // TODO: build each screen once as an LVGL object tree, then show and hide
  // them. Rebuilding a screen on every line leaks memory and flickers.
  //
  // Keep the text large: a student reads this standing at the bin, from about
  // an arm's length away, often in a corridor.
}

void handleLine(const String& line) {
  if (line.startsWith("SCREEN ")) {
    const int nameStart = 7;
    int separator = line.indexOf(' ', nameStart);
    String name = separator < 0 ? line.substring(nameStart) : line.substring(nameStart, separator);
    String values = separator < 0 ? String() : line.substring(separator + 1);
    drawScreen(screenFromName(name.c_str()), values);
  } else if (line == "PING") {
    Serial.println("PONG");
  }
}

}  // namespace

void setup() {
  Serial.begin(115200);

  // TODO: lv_init(), the panel's display driver, the touch driver, then build
  // the screens and show Screen::Idle.
}

void loop() {
  // TODO: lv_timer_handler() must run regularly — LVGL does nothing between
  // calls, so a blocking read here freezes the display.

  if (Serial.available()) {
    String line = Serial.readStringUntil('\n');
    line.trim();
    handleLine(line);
  }

  // TODO: when the Cancel button is pressed, send exactly:
  //   Serial.println("TOUCH CANCEL");

  delay(5);
}
