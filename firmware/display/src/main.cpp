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

#include "controller_link.hpp"

namespace {

ControllerLink controllerLink;

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
    controllerLink.handleLine(line);
  }

  // TODO: when the Cancel button is pressed, send exactly:
  //   Serial.println("TOUCH CANCEL");

  delay(5);
}
