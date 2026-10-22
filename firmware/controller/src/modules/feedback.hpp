#pragma once

#include <Arduino.h>

/**
 * The user-feedback module. System_Plan.md §8.2.
 *
 * Hides the serial link to the display panel, the LEDs and the buzzer. The
 * controller names a SCREEN and passes values; it never draws anything itself.
 * The panel holds no state that matters, so replacing the display changes the
 * display firmware and this file, and nothing else (System_Plan.md §8.1).
 */
namespace feedback {

/** The screens the panel can draw. System_Plan.md §8.2. */
enum class Screen {
  Idle,          // waiting for a scan
  Welcome,       // scanned, greeting the student by first name
  Weighing,      // place the item
  Accepted,      // accepted, with the points preview
  Done,          // points credited, with the new balance
  PointsPending, // deposited, but `complete` has not reached the server yet
  Problem,       // something was refused, with the reason
  BinFull,
  Offline,
};

bool begin();

/** Change the screen. `line` carries the values that screen shows. */
void show(Screen screen, const String& line = String());

/**
 * True when the student pressed Cancel on the panel.
 *
 * Touch is used for one thing at first: a Cancel button, which makes the
 * controller call the cancel endpoint (System_Plan.md §8.2).
 */
bool cancelPressed();

void clearCancel();

// Indicators. Deliberately plain: an LED and a beep are what a student at the
// bin actually reacts to, and they keep working if the panel does not.
void success();
void failure();
void busy();
void idle();

}  // namespace feedback
