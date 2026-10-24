#pragma once

#include <Arduino.h>

#include "../include/screen.hpp"

/**
 * The serial link to the controller: one line in per screen change.
 * System_Plan.md §8.2, protocol in firmware/README.md.
 *
 *   -> SCREEN <name> [values...]    draw that screen
 *   -> PING                         <- PONG
 *
 * Nothing here may hold state the transaction depends on: the next SCREEN line
 * always puts the panel back in step.
 */
class ControllerLink {
 public:
  explicit ControllerLink() = default;

  /** Act on one trimmed line from the controller. */
  void handleLine(const String& line) {
    if (line.startsWith("SCREEN ")) {
      const int nameStart = 7;
      int separator = line.indexOf(' ', nameStart);
      String name = separator < 0 ? line.substring(nameStart)
                                  : line.substring(nameStart, separator);
      String values = separator < 0 ? String() : line.substring(separator + 1);
      p_drawScreen(screenFromName(name.c_str()), values);
    } else if (line == "PING") {
      Serial.println("PONG");
    }
  }

 private:
  void p_drawScreen(Screen screen, const String& values) {
    (void)screen;
    (void)values;
    // TODO: build each screen once as an LVGL object tree, then show and hide
    // them. Rebuilding a screen on every line leaks memory and flickers.
    //
    // Keep the text large: a student reads this standing at the bin, from
    // about an arm's length away, often in a corridor.
  }
};
