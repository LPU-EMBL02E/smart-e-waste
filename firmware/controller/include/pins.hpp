#pragma once

/**
 * Pin map for the ESP32-S3-DevKitC-1. System_Plan.md §2.
 *
 * Every pin number in the controller firmware is here and nowhere else, so
 * rewiring is a change to this one file (§8.2).
 *
 * TODO(team): verify this map on the bench (System_Plan.md §10, before
 * Checkpoint 1).
 *
 * Reserved — do not use:
 *   GPIO 0, 3, 45, 46   boot strapping
 *   GPIO 19, 20         USB
 *   GPIO 26 to 32       the module's flash and PSRAM
 *   GPIO 43, 44         the USB-to-serial port (the serial log)
 *   GPIO 38 or 48       the RGB LED, depending on the board revision
 */

// A signal with no pin assigned yet. Every module that reads one treats it as
// the unsafe state, so a missing pin can never let the bin take a deposit.
constexpr int PIN_UNASSIGNED = -1;

// ---------------------------------------------------------------- load cell
// The HX711 runs on 3.3 V, so its data line never exceeds 3.3 V.
constexpr int PIN_SCALE_DATA = 4;
constexpr int PIN_SCALE_CLOCK = 5;

// --------------------------------------------------------------- ultrasonic
constexpr int PIN_ULTRASONIC_TRIGGER = 6;
// The HC-SR04 runs on 5 V; a 1 kΩ / 2 kΩ divider brings its echo to 3.3 V.
constexpr int PIN_ULTRASONIC_ECHO = 7;

// -------------------------------------------------------------- indicators
constexpr int PIN_LED_GREEN = 8;
constexpr int PIN_LED_AMBER = 9;
constexpr int PIN_LED_RED = 10;
// A 5 V active buzzer through a 2N2222A.
constexpr int PIN_BUZZER = 14;

// --------------------------------------------------------------- LED strip
// The 12 V strip that lights the flap for the camera, through an IRLZ44N.
// PWM, for dimming.
constexpr int PIN_LED_STRIP = 15;

// --------------------------------------------------------- actuator driver
// BTS7960. All three lines have 10 kΩ pull-downs, so the actuator stays off
// while the board boots or resets.
constexpr int PIN_DRIVER_ENABLE = 16;   // R_EN and L_EN together
constexpr int PIN_DRIVER_EXTEND = 17;   // RPWM: tips the flap toward the bin
constexpr int PIN_DRIVER_RETRACT = 18;  // LPWM: tips it toward the return tray
// R_IS and L_IS through a load resistor. Must be an ADC1 pin: ADC2 can't be
// read while Wi-Fi is on.
constexpr int PIN_ACTUATOR_CURRENT = 1;

// -------------------------------------------------- service panel and flap
// TODO(team): neither has a pin yet, and neither is on the schematic
// (System_Plan.md §2 and §10). Until they do, the interlock reads as open and
// the flap never reads as level, so the bin stays out of service.
constexpr int PIN_INTERLOCK = PIN_UNASSIGNED;
constexpr int PIN_FLAP_LEVEL = PIN_UNASSIGNED;
