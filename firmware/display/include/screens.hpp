#pragma once

/**
 * The screens the panel can draw. System_Plan.md §8.2.
 *
 * These names are the protocol: the controller sends
 * `SCREEN <name> [values]` and this board draws it. The names here and the
 * Screen enum in the controller's feedback module must stay in step — they are
 * the contract between the two boards.
 *
 * Touch is used for one thing at first: a Cancel button, which makes the
 * controller call the cancel endpoint. Everything else is display-only.
 */

enum class Screen {
  Idle,           // "Scan your QR code to begin"
  Welcome,        // "Hello, <first name>"
  Weighing,       // "Place your e-waste on the platform"   + Cancel
  Accepted,       // "Accepted: <grams> g, about <n> points" + Cancel
  Done,           // "<n> points added. Balance: <n>"
  PointsPending,  // "Deposited. Points will appear shortly"
  Problem,        // "<reason>" — a plain-language message, never an error code
  BinFull,        // "This bin is full"
  Offline,        // "Out of service"
  Unknown,
};

/** Parse a screen name from the controller. Unknown names map to Unknown
 *  rather than being ignored, so a protocol mismatch is visible on the panel
 *  instead of silently leaving the previous screen up. */
Screen screenFromName(const char* name);

const char* screenName(Screen screen);
