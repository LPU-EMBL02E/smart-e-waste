#pragma once

/**
 * Pin map for the NodeMCU. System_Plan.md §2.
 *
 * Every pin number in the controller firmware is here and nowhere else, so
 * rewiring is a change to this one file (§8.2).
 *
 * Seventeen pins are used, which fits the board with little to spare.
 *
 * TODO(team): this map is a starting point. Verify it on the bench, and revisit
 * the motor pins once the driver is chosen (System_Plan.md §10, before
 * Checkpoint 1).
 *
 * Reserved — do not use:
 *   GPIO 0, 2, 5, 12, 15   affect how the board boots
 *   GPIO 1, 3              the USB serial port
 * Spare: GPIO 36 and 39, both input-only.
 */

// ---------------------------------------------------------------- load cell
// GPIO 34 is INPUT-ONLY. Power the amplifier from 3.3 V so this line stays at
// 3.3 V; a 5 V data line on an input-only pin has no protection.
constexpr int PIN_SCALE_DATA = 34;
constexpr int PIN_SCALE_CLOCK = 25;

// --------------------------------------------------------------- ultrasonic
constexpr int PIN_ULTRASONIC_TRIGGER = 26;
// GPIO 35 is INPUT-ONLY and needs a voltage divider if the sensor runs on 5 V.
constexpr int PIN_ULTRASONIC_ECHO = 35;

// ------------------------------------------------------------- motor driver
constexpr int PIN_MOTOR_PWM = 27;
constexpr int PIN_MOTOR_DIR_1 = 32;
constexpr int PIN_MOTOR_DIR_2 = 33;

// ------------------------------------------------------------ limit switches
// These two are what make actuator_ok true or false. Without them the API's
// actuator confirmation means nothing (System_Plan.md §2). Both use the
// internal pull-up, so a closed switch reads LOW.
constexpr int PIN_LIMIT_HOME = 13;
constexpr int PIN_LIMIT_END = 22;

// --------------------------------------------------- display panel, UART 2
constexpr int PIN_PANEL_RX = 16;
constexpr int PIN_PANEL_TX = 17;

// ------------------------------------------------------ ESP32-CAM, UART 1
constexpr int PIN_CAMERA_RX = 18;
constexpr int PIN_CAMERA_TX = 19;

// ------------------------------------------- QR scanner, software serial
// Receive only, 9600 baud. The scanner only ever sends, and slowly, so it does
// not need one of the two hardware ports.
constexpr int PIN_QR_RX = 4;

// -------------------------------------------------------------- indicators
constexpr int PIN_LED_GREEN = 21;
constexpr int PIN_LED_RED = 14;
constexpr int PIN_BUZZER = 23;
