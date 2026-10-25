#pragma once

#include <Arduino.h>
#include <Ticker.h>

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

  /**
   * Starts the panel link and sets the LEDs and buzzer off. Does not wait for
   * the panel: the indicators must work even if it never answers.
   */
  bool begin() {
    Serial2.begin(SERIAL_PANEL_BAUD, SERIAL_8N1, PIN_PANEL_RX, PIN_PANEL_TX);
    pinMode(PIN_LED_GREEN, OUTPUT);
    pinMode(PIN_LED_RED, OUTPUT);
    pinMode(PIN_BUZZER, OUTPUT);
    digitalWrite(PIN_LED_GREEN, LOW);
    digitalWrite(PIN_LED_RED, LOW);
    digitalWrite(PIN_BUZZER, LOW);
    return true;
  }

  /** Change the screen. `line` carries the values that screen shows. */
  void show(Screen screen, const String& line = String()) {
    Serial2.print("SCREEN ");
    Serial2.print(sp_screenName(screen));
    if (!line.isEmpty()) {
      Serial2.print(' ');
      // A line break inside the values would end the line early, and the panel
      // would read the rest as a separate command.
      for (unsigned int i = 0; i < line.length(); i++) {
        const char c = line[i];
        Serial2.print(c == '\r' || c == '\n' ? ' ' : c);
      }
    }
    Serial2.println();
  }

  /**
   * True when the student pressed Cancel on the panel.
   *
   * Touch is used for one thing at first: a Cancel button, which makes the
   * controller call the cancel endpoint (System_Plan.md §8.2).
   *
   * Reads whatever the panel has sent without waiting for more. Stays true
   * until clearCancel().
   */
  bool cancelPressed() {
    while (Serial2.available() > 0) {
      const char c = static_cast<char>(Serial2.read());
      if (c == '\n') {
        p_endLine();
      } else if (c != '\r' && !p_discardingLine) {
        if (p_lineLength < sizeof(p_line) - 1) {
          p_line[p_lineLength++] = c;
        } else {
          // Longer than any line the panel sends: drop it up to its end.
          p_discardingLine = true;
        }
      }
    }
    return p_cancelPressed;
  }

  /** Forgets a press, including one still waiting unread, so a Cancel pressed
   *  between deposits does not cancel the next one. */
  void clearCancel() {
    while (Serial2.available() > 0) {
      Serial2.read();
    }
    p_lineLength = 0;
    p_discardingLine = false;
    p_cancelPressed = false;
  }

  /*
   * TODO: decide before implementing — are these four a STATE or a TIMED
   * output?
   *
   *   - State: each call sets the LEDs and they stay that way until the next
   *     call, as main.cpp's use suggests (busy() for the whole boot, failure()
   *     held through refuse()'s Problem screen, then idle()). Then a clear
   *     output of some kind is expected for each state.
   *   - Timed: each call lights and beeps for a set time, then turns itself
   *     off.
   *
   * The firmware lead's working assumption is a state: called once, then
   * active until the next call. The patterns for that are ready below as
   * p_signalSuccess(), p_signalFailure(), p_signalBusy() and p_signalIdle() —
   * if confirmed, each of the four calls its own. If not, they stay unused.
   */

  // Indicators. Deliberately plain: an LED and a beep are what a student at the
  // bin actually reacts to, and they keep working if the panel does not.
  void success() {}
  void failure() {}
  void busy() {}
  void idle() {}

 private:
  /** The level of each indicator during one phase of a signal. */
  struct Outputs {
    bool green;
    bool red;
    bool buzzer;
  };

  /** A signal repeats two phases, `on` then `off`, until the next one starts.
   */
  struct Signal {
    Outputs on;
    Outputs off;
    unsigned long onMs;
    unsigned long offMs;
  };

  static constexpr const char* sp_TOUCH_CANCEL = "TOUCH CANCEL";
  // Fits the longest line the panel sends, with room to spare.
  static constexpr size_t sp_LINE_LENGTH = 32;
  // Every signal duration in config.hpp is a multiple of this.
  static constexpr unsigned long sp_SIGNAL_TICK_MS = 10;

  // The four indicator patterns. Each starts once and keeps running on a
  // timer, so nothing has to call it again and the loop is never blocked.

  /** Green on, with a short beep repeating after a long gap. */
  void p_signalSuccess() {
    p_startSignal(Signal{
        {true, false, true}, {true, false, false}, BEEP_SHORT_MS, BEEP_GAP_MS});
  }

  /** Red on, with a long beep repeating after the same gap as success. */
  void p_signalFailure() {
    p_startSignal(Signal{
        {false, true, true}, {false, true, false}, BEEP_LONG_MS, BEEP_GAP_MS});
  }

  /** Green and red flicker together. No beep. */
  void p_signalBusy() {
    p_startSignal(Signal{{true, true, false},
                         {false, false, false},
                         LED_FLICKER_MS,
                         LED_FLICKER_MS});
  }

  /** Green flickers alone. No beep. */
  void p_signalIdle() {
    p_startSignal(Signal{{true, false, false},
                         {false, false, false},
                         LED_FLICKER_MS,
                         LED_FLICKER_MS});
  }

  /**
   * Replaces the running signal. The ticker starts with the first signal; the
   * new one shows from its next tick, at most sp_SIGNAL_TICK_MS later.
   */
  void p_startSignal(const Signal& signal) {
    portENTER_CRITICAL(&p_signalLock);
    p_signal = signal;
    p_signalElapsedMs = 0;
    portEXIT_CRITICAL(&p_signalLock);

    if (!p_signalRunning) {
      p_signalTicker.attach_ms(sp_SIGNAL_TICK_MS, sp_onSignalTick, this);
      p_signalRunning = true;
    }
  }

  /** Runs on the esp_timer task, not the loop — hence the lock. */
  static void sp_onSignalTick(Feedback* self) { self->p_signalTick(); }

  void p_signalTick() {
    portENTER_CRITICAL(&p_signalLock);
    const Outputs outputs =
        p_signalElapsedMs < p_signal.onMs ? p_signal.on : p_signal.off;
    p_signalElapsedMs = (p_signalElapsedMs + sp_SIGNAL_TICK_MS) %
                        (p_signal.onMs + p_signal.offMs);
    portEXIT_CRITICAL(&p_signalLock);

    digitalWrite(PIN_LED_GREEN, outputs.green ? HIGH : LOW);
    digitalWrite(PIN_LED_RED, outputs.red ? HIGH : LOW);
    digitalWrite(PIN_BUZZER, outputs.buzzer ? HIGH : LOW);
  }

  /** The name on the wire. These must match display/include/screen.hpp.
   *  A switch rather than a table, so -Wall flags a screen added without one.
   */
  static const char* sp_screenName(Screen screen) {
    switch (screen) {
      case Screen::Idle:
        return "IDLE";
      case Screen::Welcome:
        return "WELCOME";
      case Screen::Weighing:
        return "WEIGHING";
      case Screen::Accepted:
        return "ACCEPTED";
      case Screen::Done:
        return "DONE";
      case Screen::PointsPending:
        return "PENDING";
      case Screen::Problem:
        return "PROBLEM";
      case Screen::BinFull:
        return "BIN_FULL";
      case Screen::Offline:
        return "OFFLINE";
    }
    // Unreachable while every screen is listed; the panel shows UNKNOWN as a
    // visible protocol mismatch.
    return "UNKNOWN";
  }

  /** One complete line from the panel. Anything but TOUCH CANCEL is ignored. */
  void p_endLine() {
    p_line[p_lineLength] = '\0';
    if (!p_discardingLine && strcmp(p_line, sp_TOUCH_CANCEL) == 0) {
      p_cancelPressed = true;
    }
    p_lineLength = 0;
    p_discardingLine = false;
  }

  char p_line[sp_LINE_LENGTH] = "";
  size_t p_lineLength = 0;
  bool p_discardingLine = false;
  bool p_cancelPressed = false;

  Ticker p_signalTicker;
  portMUX_TYPE p_signalLock = portMUX_INITIALIZER_UNLOCKED;
  Signal p_signal{};
  unsigned long p_signalElapsedMs = 0;
  bool p_signalRunning = false;
};
