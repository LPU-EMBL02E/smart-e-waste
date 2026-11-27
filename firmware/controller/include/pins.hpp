#pragma once

/**
 * Pin map for the controller board, a YD-ESP32-S3. System_Plan.md §2.
 *
 * Every pin the bin wires to the controller is assigned here and nowhere else,
 * so rewiring is a change to this one file (§8.2). The board's own pins, and
 * which of them are reserved, are in pinlayout.hpp; the checks at the end of
 * this file stop the build if an assignment lands on one of those.
 *
 * TODO(team): verify this map on the bench (System_Plan.md §10, before
 * Checkpoint 1).
 */

#include "pinlayout.hpp"

// A signal with no pin assigned yet. Every module that reads one treats it as
// the unsafe state, so a missing pin can never let the bin take a deposit.
constexpr int PIN_UNASSIGNED = -1;

// ---------------------------------------------------------------- load cell
// The HX711 runs on 3.3 V, so its data line never exceeds 3.3 V.
constexpr int PIN_SCALE_DATA = GPIO4;
constexpr int PIN_SCALE_CLOCK = GPIO5;

// --------------------------------------------------------------- ultrasonic
constexpr int PIN_ULTRASONIC_TRIGGER = GPIO6;
// The HC-SR04 runs on 5 V; a 1 kΩ / 2 kΩ divider brings its echo to 3.3 V.
constexpr int PIN_ULTRASONIC_ECHO = GPIO7;

// -------------------------------------------------------------- indicators
constexpr int PIN_LED_GREEN = GPIO8;
constexpr int PIN_LED_AMBER = GPIO9;
constexpr int PIN_LED_RED = GPIO10;
// A 5 V active buzzer through a 2N2222A.
constexpr int PIN_BUZZER = GPIO14;

// --------------------------------------------------------------- LED strip
// The 12 V strip that lights the flap for the camera, through an IRLZ44N.
// PWM, for dimming.
constexpr int PIN_LED_STRIP = GPIO15;

// --------------------------------------------------------- actuator driver
// BTS7960. All three lines have 10 kΩ pull-downs, so the actuator stays off
// while the board boots or resets.
constexpr int PIN_DRIVER_ENABLE = GPIO16;  // R_EN and L_EN together
constexpr int PIN_DRIVER_EXTEND = GPIO17;  // RPWM: tips the flap toward the bin
constexpr int PIN_DRIVER_RETRACT =
    GPIO18;  // LPWM: tips it toward the return tray
// R_IS and L_IS through a load resistor. Must be an ADC1 pin: ADC2 can't be
// read while Wi-Fi is on.
constexpr int PIN_ACTUATOR_CURRENT = GPIO1;

// -------------------------------------------------- service panel and flap
// TODO(team): neither has a pin yet, and neither is on the schematic
// (System_Plan.md §2 and §10). Until they do, the interlock reads as open and
// the flap never reads as level, so the bin stays out of service.
constexpr int PIN_INTERLOCK = PIN_UNASSIGNED;
constexpr int PIN_FLAP_LEVEL = PIN_UNASSIGNED;

// ---------------------------------------------------------- build checks
// Each assigned pin is on the header and not reserved by the board
// (pinlayout.hpp). An unassigned pin is allowed: its module fails closed.
static_assert(boardIsUsable(PIN_SCALE_DATA), "PIN_SCALE_DATA is not usable");
static_assert(boardIsUsable(PIN_SCALE_CLOCK), "PIN_SCALE_CLOCK is not usable");
static_assert(boardIsUsable(PIN_ULTRASONIC_TRIGGER),
              "PIN_ULTRASONIC_TRIGGER is not usable");
static_assert(boardIsUsable(PIN_ULTRASONIC_ECHO),
              "PIN_ULTRASONIC_ECHO is not usable");
static_assert(boardIsUsable(PIN_LED_GREEN), "PIN_LED_GREEN is not usable");
static_assert(boardIsUsable(PIN_LED_AMBER), "PIN_LED_AMBER is not usable");
static_assert(boardIsUsable(PIN_LED_RED), "PIN_LED_RED is not usable");
static_assert(boardIsUsable(PIN_BUZZER), "PIN_BUZZER is not usable");
static_assert(boardIsUsable(PIN_LED_STRIP), "PIN_LED_STRIP is not usable");
static_assert(boardIsUsable(PIN_DRIVER_ENABLE),
              "PIN_DRIVER_ENABLE is not usable");
static_assert(boardIsUsable(PIN_DRIVER_EXTEND),
              "PIN_DRIVER_EXTEND is not usable");
static_assert(boardIsUsable(PIN_DRIVER_RETRACT),
              "PIN_DRIVER_RETRACT is not usable");
static_assert(boardIsUsable(PIN_ACTUATOR_CURRENT) &&
                  boardIsAdc1(PIN_ACTUATOR_CURRENT),
              "PIN_ACTUATOR_CURRENT must be a usable ADC1 pin");
static_assert(PIN_INTERLOCK == PIN_UNASSIGNED || boardIsUsable(PIN_INTERLOCK),
              "PIN_INTERLOCK is not usable");
static_assert(PIN_FLAP_LEVEL == PIN_UNASSIGNED || boardIsUsable(PIN_FLAP_LEVEL),
              "PIN_FLAP_LEVEL is not usable");
