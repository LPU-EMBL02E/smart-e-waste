#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

/**
 * The user-feedback module. System_Plan.md §8.2.
 *
 * Hides the serial link to the display panel, the LEDs and the buzzer. The
 * controller names a SCREEN and passes values; it never draws anything itself.
 * The panel holds no state that matters, so replacing the display changes the
 * display firmware and this file, and nothing else (System_Plan.md §8.1).
 */
class Feedback {
  /*
   * TODO: implement over UART 2 (PIN_PANEL_RX / PIN_PANEL_TX) using the line
   * protocol in firmware/README.md, plus the two LEDs and the buzzer.
   *
   *   -> SCREEN <name> [values]
   *   <- TOUCH CANCEL
   *
   * One line per screen change, one line per touch. Keep it one-way as far as
   * possible: the panel must never become a place where state lives, or a
   * panel reboot mid-deposit would lose part of the transaction.
   *
   * The LEDs and buzzer must work even when the panel is unreachable — they are
   * the fallback that tells a student standing at the bin whether anything
   * happened.
   */

 public:
  /** The screens the panel can draw. System_Plan.md §8.2. */
  enum class Screen {
    Idle,           // waiting for a scan
    Welcome,        // scanned, greeting the student by first name
    Weighing,       // place the item
    Accepted,       // accepted, with the points preview
    Done,           // points credited, with the new balance
    PointsPending,  // deposited, but `complete` has not reached the server yet
    Problem,        // something was refused, with the reason
    BinFull,
    Offline,
  };

  explicit Feedback() = default;

  bool begin() { return false; }

  /** Change the screen. `line` carries the values that screen shows. */
  void show(Screen screen, const String& line = String()) {}

  /**
   * True when the student pressed Cancel on the panel.
   *
   * Touch is used for one thing at first: a Cancel button, which makes the
   * controller call the cancel endpoint (System_Plan.md §8.2).
   */
  bool cancelPressed() { return false; }

  void clearCancel() {}

  // Indicators. Deliberately plain: an LED and a beep are what a student at the
  // bin actually reacts to, and they keep working if the panel does not.
  void success() {}
  void failure() {}
  void busy() {}
  void idle() {}
};
