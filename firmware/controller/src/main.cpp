/**
 * Controller firmware — the ESP32-S3 that runs one deposit at a time.
 * System_Plan.md §8.2 (modules and the deposit sequence).
 *
 * This file is the sequence and nothing else. Every piece of hardware sits
 * behind a module, and every rule is the server's: the controller measures,
 * reports and obeys. The QR scanner, the camera check and the screen are the
 * bin station's, on the Pi, reached through Station (API_Design.md §11).
 *
 * Three things it must never do, because each one would credit points that were
 * not earned:
 *   - compute points. The server computes them from grams (§5.7 rule 3).
 *   - tip the flap toward the bin before the server answers `accepted: true`
 *     (API_Design.md §5.5).
 *   - treat an unreachable server or bin station as a pass. Fail closed
 *     (API_Design.md §3.6 and §11.1).
 */

#include <Arduino.h>

#include "../include/config.hpp"
#include "fault_code.hpp"
#include "modules/actuator.hpp"
#include "modules/api.hpp"
#include "modules/bin_level.hpp"
#include "modules/feedback.hpp"
#include "modules/interlock.hpp"
#include "modules/qr_scanner.hpp"
#include "modules/scale.hpp"
#include "modules/settings.hpp"
#include "modules/station.hpp"
#include "modules/verifier.hpp"

namespace {

// One instance per module. These are built before setup() runs, so a
// constructor only stores what it is given; hardware starts in begin().
// A module is defined before any module that keeps a reference to it.
Settings settings;
Api api{settings};
Station station{settings};
Interlock interlock;
Actuator actuator{interlock};
QrScanner qrScanner{station};
Verifier verifier{station};
Feedback feedback{station};
Scale scale;
BinLevel binLevel;

unsigned long lastTelemetryMs = 0;
bool binFull = false;

/**
 * Send a distance reading with the panel state and the current fault, and
 * remember what the server said about fill level.
 *
 * The firmware sends the raw distance only; the server decides whether the bin
 * is full and the reply carries that back so the bin can show its bin-full
 * screen (API_Design.md §5.3, P18).
 */
void sendTelemetry() {
  const float distance = binLevel.readDistanceMm();
  if (distance < 0) {
    Serial.println("telemetry: sensor did not answer");
    return;
  }

  Api::BinStatus status{};
  if (api.sendTelemetry(distance, interlock.isOpen(), FaultCode::None,
                        status) == Api::Result::Ok) {
    binFull = status.full;
    lastTelemetryMs = millis();
  }
}

/**
 * One deposit, from a scanned token to a final state.
 *
 * TODO: implement the deposit sequence of System_Plan.md §8.2:
 *
 *   1. Ready: the service panel is closed, no actuator fault is latched, no
 *      `complete` is pending, the actuator has rested, the flap is level and
 *      empty, the bin is not full and the bin station answers. An item already
 *      on the flap is returned first. Then arm the scanner.       Idle, green
 *   2. Scan: on a token, disarm the scanner and open the session. Welcome,
 * amber
 *   3. Weigh: wait for a stable weight above zero, a Cancel touch or the
 *      session timeout.                                        Weighing, amber
 *   4. Verify: the camera check through the bin station.      Weighing, amber
 *   5. Deposit: a rejection ends the session and returns the item.
 *                                                 Accepted or problem,
 * amber/red
 *   6. Transfer: tip the item into the bin, back to level.    Accepted, amber
 *   7. Complete: retried and saved if the server can't be reached.
 *                                                Done or points pending,
 * green/amber
 *   8. Reset: a new reference image of the empty flap, telemetry, the
 *      actuator's rest.                                           Idle, green
 *   Return: whenever a session ends without a transfer and the scale reads an
 *   item on the flap, return it to the tray, then go on to step 8.
 *                                                                 Problem, red
 *
 * Opening the service panel disables the driver at once, disarms the scanner,
 * cancels an OPEN session and shows the problem screen.
 */
void runDeposit(const String& qrToken) {
  (void)qrToken;
  feedback.problem();
  feedback.showProblem("Not available yet");
  delay(RESULT_SCREEN_MS);
}

}  // namespace

void setup() {
  Serial.begin(SERIAL_LOG_BAUD);
  Serial.printf("\nSmart E-Waste controller %s\n", FIRMWARE_VERSION);

  settings.begin();
  feedback.begin();
  feedback.busy();
  interlock.begin();
  actuator.begin();

  // Opens the setup portal when nothing is stored. The server URL, bin station
  // URL, device code and secret are entered there, never compiled in
  // (System_Plan.md §8). The secret itself is never printed.
  if (settings.ensureProvisioned()) {
    Serial.printf("settings: server %s, bin station %s, device %s\n",
                  settings.apiBaseUrl().c_str(),
                  settings.stationBaseUrl().c_str(),
                  settings.deviceCode().c_str());
  } else {
    Serial.println("settings: not provisioned, the server cannot be reached");
  }

  scale.begin();
  verifier.begin();
  binLevel.begin();

  if (!api.begin()) {
    feedback.show(Feedback::Screen::Offline);
  }

  // The ESP32 has no clock. Nothing can be signed until this succeeds.
  api.syncClock();
  api.fetchConfig();

  // A `complete` saved by a previous run — the bin lost the server or lost
  // power after an item was already in the bin. Send it before anything else,
  // so those points are not lost (API_Design.md §3.6).
  if (settings.hasPendingComplete()) {
    Serial.println("resending a saved complete from the last run");
    api.flushPendingComplete();
  }

  sendTelemetry();
}

void loop() {
  if (millis() - lastTelemetryMs >= TELEMETRY_INTERVAL_MS) {
    sendTelemetry();
  }

  String token;
  if (qrScanner.takeToken(token)) {
    qrScanner.disarm();
    runDeposit(token);
  }

  delay(STATION_EVENT_POLL_MS);
}
