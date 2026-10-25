/**
 * Controller firmware — the NodeMCU that runs one deposit at a time.
 * System_Plan.md §8 (integration sequence) and §8.2 (modules).
 *
 * This file is the sequence and nothing else. Every piece of hardware sits
 * behind a module, and every rule is the server's: the controller measures,
 * reports and obeys.
 *
 * Three things it must never do, because each one would credit points that were
 * not earned:
 *   - compute points. The server computes them from grams (§5.7 rule 3).
 *   - move the actuator before the server answers `accepted: true` (§5.5).
 *   - treat an unreachable server or an unreachable camera as a pass. Fail
 *     closed (§3.6).
 *
 * The expected sequence (System_Plan.md §8, step 12):
 *   1. wait for a user          8.  server validates
 *   2. scan QR                  9.  server computes points
 *   3. server verifies student  10. actuator pushes the item in
 *   4. weighing starts          11. transaction recorded
 *   5. student places item      12. dashboard and points updated
 *   6. load cell measures       13. ultrasonic updates bin status
 *   7. camera verifies          14. back to standby
 */

#include <Arduino.h>

#include "../include/config.hpp"
#include "../include/pins.hpp"
#include "modules/actuator.hpp"
#include "modules/api.hpp"
#include "modules/bin_level.hpp"
#include "modules/feedback.hpp"
#include "modules/qr_scanner.hpp"
#include "modules/scale.hpp"
#include "modules/settings.hpp"
#include "modules/verifier.hpp"

namespace {

// One instance per module. These are built before setup() runs, so a
// constructor only stores what it is given; hardware starts in begin().
// `settings` is defined before `api`, which keeps a reference to it.
Settings settings;
Api api{settings};
Feedback feedback;
QrScanner qrScanner;
Scale scale;
Verifier verifier;
Actuator actuator;
BinLevel binLevel;

unsigned long lastTelemetryMs = 0;
bool binFull = false;

/**
 * Send a distance reading and remember what the server said about fill level.
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
  if (api.sendTelemetry(distance, status) == Api::Result::Ok) {
    binFull = status.full;
    lastTelemetryMs = millis();
  }
}

/** Show a refusal to the student and return to idle. */
void refuse(const String& reason) {
  feedback.failure();
  feedback.show(Feedback::Screen::Problem, reason);
  delay(2500);
  feedback.show(Feedback::Screen::Idle);
  feedback.idle();
}

/**
 * One deposit, from a scanned token to a final state.
 *
 * TODO: implement. The order of the calls is fixed by API_Design.md §5.4-§5.6
 * and must not be rearranged:
 *
 *   1. api.openSession(token)
 *        -> on ServerError, show the reason from api.lastErrorCode():
 *           INVALID_QR, USER_INACTIVE, BIN_FULL, NO_REWARD_RULE,
 *           DAILY_LIMIT_REACHED, DUPLICATE_TRANSACTION. Do not actuate.
 *        -> on NetworkError, refuse. Fail closed: nothing has moved yet, so
 *           nothing is lost by refusing (§3.6).
 *   2. feedback: Welcome with the display name, then Weighing.
 *   3. scale.readStable(), with feedback.cancelPressed() checked while
 *      waiting, and the session's own timeout respected.
 *   4. verifier.verify().
 *   5. api.sendDeposit(...) with the weight, stability, sample count and the
 *      verdict. Only if this returns accepted may the actuator move.
 *   6. actuator.transfer().
 *   7. api.sendComplete(session, result.ok, result.cycleMs).
 *        -> Ok: Done, with the points awarded and the new balance.
 *        -> NetworkError after its retries: the item IS in the bin and the
 *           request is saved to NVS. Show PointsPending, and do not accept
 *           another deposit until api.flushPendingComplete() succeeds.
 *   8. verifier.captureEmptyReference() and scale.tare() once the chamber is
 *      empty again, then sendTelemetry().
 */
void runDeposit(const String& qrToken) {
  (void)qrToken;
  refuse("Not implemented");
}

}  // namespace

void setup() {
  Serial.begin(SERIAL_LOG_BAUD);
  Serial.printf("\nSmart E-Waste controller %s\n", FIRMWARE_VERSION);

  settings.begin();
  feedback.begin();
  feedback.busy();

  // Opens the setup portal when nothing is stored. The server URL, device code
  // and secret are entered there, never compiled in (System_Plan.md §8).
  // The secret itself is never printed.
  if (settings.ensureProvisioned()) {
    Serial.printf("settings: server %s, device %s\n",
                  settings.apiBaseUrl().c_str(), settings.deviceCode().c_str());
  } else {
    Serial.println("settings: not provisioned, the server cannot be reached");
  }

  qrScanner.begin();
  scale.begin();
  verifier.begin();
  actuator.begin();
  binLevel.begin();

  // Home the mechanism before anything is weighed: an actuator left mid-stroke
  // sits over the platform and every reading would be wrong.
  actuator.returnHome();

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

  feedback.show(Feedback::Screen::Idle);
  feedback.idle();
}

void loop() {
  // A saved `complete` blocks new deposits until it is sent: the student whose
  // item is already in the bin gets their points before anyone else deposits.
  if (settings.hasPendingComplete()) {
    feedback.show(Feedback::Screen::PointsPending);
    if (api.flushPendingComplete() != Api::Result::Ok) {
      delay(5000);
      return;
    }
    feedback.show(Feedback::Screen::Idle);
  }

  if (binFull) {
    feedback.show(Feedback::Screen::BinFull);
  }

  if (qrScanner.hasToken()) {
    const String token = qrScanner.takeToken();
    qrScanner.flush();  // ignore a second scan mid-deposit
    feedback.clearCancel();
    runDeposit(token);
  }

  if (millis() - lastTelemetryMs >= TELEMETRY_INTERVAL_MS) {
    sendTelemetry();
  }

  delay(50);
}
