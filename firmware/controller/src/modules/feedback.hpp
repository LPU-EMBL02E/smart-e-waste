#pragma once

#include <Arduino.h>
#include <ArduinoJson.h>
#include <Ticker.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"
#include "station.hpp"

/**
 * The user-feedback module. System_Plan.md §8.2.
 *
 * Hides the screen, the three LEDs and the buzzer. The screen is the bin
 * station's touchscreen, reached through Station (API_Design.md §11.7): the
 * controller names a screen and passes its values, and never draws anything
 * itself. The screen holds no state that matters, so the next screen change
 * always puts it back in step.
 *
 * The LEDs and the buzzer are wired to the controller and work even when the
 * bin station does not answer — they are the fallback that tells a student at
 * the bin whether anything happened.
 *
 * LEDs (System_Plan.md §8.2): green when ready, amber while a deposit is in
 * progress or points are pending, red for a problem, a fault or an open
 * service panel.
 */
class Feedback {
 public:
  /** The screens without values. Each screen with values has its own
   *  method. */
  enum class Screen {
    Idle,           // waiting for a scan
    Weighing,       // drop the item through the slot
    PointsPending,  // deposited, but `complete` has not reached the server yet
    BinFull,
    Offline,
  };

  explicit Feedback(Station& station) : p_station(station) {}

  /** Sets up the LEDs and the buzzer, all off. */
  bool begin() {
    pinMode(PIN_LED_GREEN, OUTPUT);
    pinMode(PIN_LED_AMBER, OUTPUT);
    pinMode(PIN_LED_RED, OUTPUT);
    pinMode(PIN_BUZZER, OUTPUT);
    p_setLeds(false, false, false);
    digitalWrite(PIN_BUZZER, LOW);
    return true;
  }

  // Screens. Each returns false if the bin station did not show it; the LEDs
  // still tell the student what happened.

  bool show(Screen screen) {
    JsonDocument body;
    body["screen"] = sp_screenName(screen);
    return p_send(body);
  }

  /** Scanned, greeting the student by first name. */
  bool showWelcome(const char* name) {
    JsonDocument body;
    body["screen"] = "WELCOME";
    body["values"]["name"] = name;
    return p_send(body);
  }

  /** Accepted, with the weight and the points preview from `deposit`. */
  bool showAccepted(float weightG, int points) {
    char weight[16];
    snprintf(weight, sizeof(weight), "%.1f", weightG);
    JsonDocument body;
    body["screen"] = "ACCEPTED";
    body["values"]["weight_g"] = serialized(weight);
    body["values"]["points"] = points;
    return p_send(body);
  }

  /** Points credited, with the new balance, from `complete`. */
  bool showDone(int points, int balance) {
    JsonDocument body;
    body["screen"] = "DONE";
    body["values"]["points"] = points;
    body["values"]["balance"] = balance;
    return p_send(body);
  }

  /** Something was refused or went wrong. Plain language for the student,
   *  never an error code (API_Design.md §11.7). */
  bool showProblem(const char* message) {
    JsonDocument body;
    body["screen"] = "PROBLEM";
    body["values"]["message"] = message;
    return p_send(body);
  }

  /**
   * True when the student pressed Cancel on the screen. Reads what
   * Station::pollEvents() collected, without waiting. Stays true until
   * clearCancel().
   *
   * Touch is used for one thing at first: a Cancel button, which makes the
   * controller call the cancel endpoint (System_Plan.md §8.2).
   */
  bool cancelPressed() {
    if (p_station.takeCancel()) {
      p_cancelPressed = true;
    }
    return p_cancelPressed;
  }

  /** Forgets a press, including one collected but not read yet, so a Cancel
   *  pressed between deposits does not cancel the next one. */
  void clearCancel() {
    while (p_station.takeCancel()) {
    }
    p_cancelPressed = false;
  }

  // LEDs. Each stays as set until the next call.
  void ready() { p_setLeds(true, false, false); }
  void busy() { p_setLeds(false, true, false); }
  void problem() { p_setLeds(false, false, true); }

  // The buzzer. Starts a beep and returns at once; a timer ends it, so the
  // loop is never held up.
  void beepShort() { p_beep(BEEP_SHORT_MS); }
  void beepLong() { p_beep(BEEP_LONG_MS); }

 private:
  /** The name on the wire (API_Design.md §11.7). A switch rather than a table,
   *  so -Wall flags a screen added without one. */
  static const char* sp_screenName(Screen screen) {
    switch (screen) {
      case Screen::Idle:
        return "IDLE";
      case Screen::Weighing:
        return "WEIGHING";
      case Screen::PointsPending:
        return "PENDING";
      case Screen::BinFull:
        return "BIN_FULL";
      case Screen::Offline:
        return "OFFLINE";
    }
    // Unreachable while every screen is listed; the bin station refuses it
    // with VALIDATION_ERROR, which shows up in the log.
    return "UNKNOWN";
  }

  static void sp_buzzerOff() { digitalWrite(PIN_BUZZER, LOW); }

  bool p_send(const JsonDocument& body) {
    if (p_station.showScreen(body) != Station::Result::Ok) {
      Serial.printf("feedback: screen %s not shown\n",
                    body["screen"].as<const char*>());
      return false;
    }
    return true;
  }

  void p_setLeds(bool green, bool amber, bool red) {
    digitalWrite(PIN_LED_GREEN, green ? HIGH : LOW);
    digitalWrite(PIN_LED_AMBER, amber ? HIGH : LOW);
    digitalWrite(PIN_LED_RED, red ? HIGH : LOW);
  }

  void p_beep(unsigned long durationMs) {
    digitalWrite(PIN_BUZZER, HIGH);
    p_beepTicker.once_ms(durationMs, sp_buzzerOff);
  }

  Station& p_station;
  Ticker p_beepTicker;
  bool p_cancelPressed = false;
};
