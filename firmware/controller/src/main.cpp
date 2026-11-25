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
 *
 * The deposit sequence (System_Plan.md §8.2):
 *   1. Ready: readyCheck(), then arm the scanner.               Idle, green
 *   2. Scan: on a token, disarm and open the session.         Welcome, amber
 *   3. Weigh: a stable weight above zero, Cancel or timeout. Weighing, amber
 *   4. Verify: the camera check through the bin station.     Weighing, amber
 *   5. Deposit: a rejection ends the session and returns the item.
 *   6. Transfer: tip the item into the bin, back to level.   Accepted, amber
 *   7. Complete: retried, and saved if the server can't be reached.
 *                                            Done, green / points pending,
 * amber
 *   8. Reset: a new reference, telemetry, the actuator's rest — readyCheck()
 *      does each before the scanner is armed again.
 *   Return: a session that ends without a transfer, with an item on the flap,
 *   returns it to the tray (endWithoutTransfer()).                Problem, red
 */

#include <Arduino.h>

#include <atomic>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>

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
// Isolated client for the armed keepalive: it must not share p_client, the
// event buffers or NVS reads with the station the main loop uses.
Station stationKeepalive{settings};
Interlock interlock;
Actuator actuator{interlock};
QrScanner qrScanner{station};
Verifier verifier{station};
Feedback feedback{station};
Scale scale;
BinLevel binLevel;

/** What the bin shows while it is not taking a deposit, so each is sent to
 *  the screen once rather than on every pass of the loop. */
enum class Shown {
  None,  // a session's own screen, or nothing yet
  Ready,
  PanelOpen,
  OutOfService,
  PointsPending,
  BinFull,
  Offline,
};

// Plain language for the student (API_Design.md §11.7): never an error code.
constexpr const char* MESSAGE_SERVICING = "This bin is being serviced.";
constexpr const char* MESSAGE_OUT_OF_SERVICE = "This bin is out of service.";
constexpr const char* MESSAGE_NO_SERVER =
    "The server can't be reached. Please try again later.";
constexpr const char* MESSAGE_NOT_CHECKED =
    "The item could not be checked. Please try again later.";
constexpr const char* MESSAGE_TRY_AGAIN =
    "Something went wrong. Please try again.";
constexpr const char* MESSAGE_TAKE_ITEM =
    "Please take your item from the tray.";
constexpr const char* MESSAGE_ITEM_GONE =
    "Your item is no longer on the flap.";
// The reason and MESSAGE_TAKE_ITEM, with room to spare.
constexpr size_t MESSAGE_LENGTH = 160;

// Starts closed only once the interlock says so: unknown is open.
bool panelOpen = true;
// Actuator faults latch until the panel has been opened and closed again.
FaultCode latchedFault = FaultCode::None;
bool panelOpenedSinceFault = false;
// Bin station faults clear when it answers normally again.
FaultCode stationFault = FaultCode::None;

bool binFull = false;
std::atomic<bool> scannerArmed{false};
// Keepalive task state. The task only pings GET /health; it never polls
// /events and never sets faults, so it cannot drain the queue or mask a
// station fault the main loop owns.
std::atomic<unsigned long> keepaliveHeartbeatMs{0};
TaskHandle_t keepaliveTask = nullptr;
// Main-loop progress and the lease a blocking device call gets while armed.
// deviceCallOverrun is sticky: once a call outlives its lease the keepalive
// stays off until a successful disarm and a fresh readyCheck re-arm.
std::atomic<unsigned long> mainLoopProgressMs{0};
std::atomic<bool> deviceCallActive{false};
std::atomic<unsigned long> deviceCallStartMs{0};
std::atomic<bool> deviceCallOverrun{false};
// A new reference at boot and after each transfer or return (§11.6).
bool referenceNeeded = true;
bool calibrationApplied = false;
int64_t appliedCalibrationAt = 0;

bool telemetryDue = true;
unsigned long lastTelemetryMs = 0;
unsigned long lastPollMs = 0;
unsigned long lastResendMs = 0;
// A result screen stays up for RESULT_SCREEN_MS before the bin moves on.
bool holding = false;
unsigned long holdStartMs = 0;
Shown shown = Shown::None;

FaultCode currentFault() {
  return latchedFault != FaultCode::None ? latchedFault : stationFault;
}

void setStationFault(FaultCode fault) {
  if (fault == stationFault) {
    return;
  }
  stationFault = fault;
  telemetryDue = true;
  Serial.printf("fault: bin station %s\n", fault == FaultCode::None
                                               ? "answers again"
                                               : faultCodeName(fault));
}

/** The keepalive task is alive only while it keeps touching its heartbeat. */
bool keepaliveReady() {
  return keepaliveTask != nullptr &&
         millis() - keepaliveHeartbeatMs.load() <= STATION_KEEPALIVE_STALE_MS;
}

/**
 * Whether the armed keepalive may ping the station this pass. It runs only
 * while the scanner is armed, never after an overrun, and only while the main
 * loop is making progress or a blocking device call is still inside its
 * lease. Once the lease expires the overrun flag stays set, so a late return
 * cannot make the keepalive look healthy again by itself.
 */
bool keepaliveAllowed(unsigned long now) {
  if (!scannerArmed.load() || deviceCallOverrun.load()) {
    return false;
  }
  if (deviceCallActive.load()) {
    if (now - deviceCallStartMs.load() > DEVICE_CALL_LEASE_MS) {
      deviceCallOverrun.store(true);
      return false;
    }
    return true;
  }
  return now - mainLoopProgressMs.load() <= MAIN_LOOP_PROGRESS_MS;
}

/** FreeRTOS task on the non-loop core: GET /health only, never /events. */
void stationKeepaliveTask(void*) {
  for (;;) {
    keepaliveHeartbeatMs.store(millis());
    if (keepaliveAllowed(millis())) {
      Station::Health health{};
      stationKeepalive.health(health, STATION_KEEPALIVE_TIMEOUT_MS);
    }
    vTaskDelay(pdMS_TO_TICKS(STATION_KEEPALIVE_MS));
  }
}

/** Marks the start of a blocking device call made while the scanner is armed. */
void beginDeviceCall() {
  if (!scannerArmed.load()) {
    return;
  }
  deviceCallStartMs.store(millis());
  deviceCallActive.store(true);
}

/** Clears the call lease; an overrun stays set until disarm and re-arm. */
void endDeviceCall() {
  if (!deviceCallActive.load()) {
    return;
  }
  if (millis() - deviceCallStartMs.load() > DEVICE_CALL_LEASE_MS) {
    deviceCallOverrun.store(true);
  }
  deviceCallActive.store(false);
}

void latchFault(FaultCode fault) {
  if (fault == FaultCode::None || fault == latchedFault) {
    return;
  }
  latchedFault = fault;
  panelOpenedSinceFault = false;
  telemetryDue = true;
  Serial.printf("fault: %s, latched until the service panel is cycled\n",
                faultCodeName(fault));
}

/** The bin station fault behind a failed call; None for an error that is not
 *  a fault, such as NO_REFERENCE. */
FaultCode stationFaultFor(Station::Result result) {
  if (result == Station::Result::Ok) {
    return FaultCode::None;
  }
  const char* code = station.lastErrorCode();
  if (result == Station::Result::NoReply || code[0] == '\0') {
    return FaultCode::StationUnreachable;
  }
  if (strcmp(code, "CAMERA_UNAVAILABLE") == 0) {
    return FaultCode::CameraUnavailable;
  }
  if (strcmp(code, "MODEL_UNAVAILABLE") == 0) {
    return FaultCode::ModelUnavailable;
  }
  if (strcmp(code, "SCANNER_UNAVAILABLE") == 0) {
    return FaultCode::ScannerUnavailable;
  }
  return FaultCode::None;
}

/** Plain language for a device API error code (API_Design.md §6). */
const char* messageFor(const char* code) {
  static constexpr struct {
    const char* code;
    const char* message;
  } MESSAGES[] = {
      {"INVALID_QR", "This QR code is not recognised."},
      {"USER_INACTIVE", "This account is not active."},
      {"BIN_FULL", "This bin is full."},
      {"NO_REWARD_RULE", "This bin is not taking deposits right now."},
      {"DAILY_LIMIT_REACHED", "You have reached today's points limit."},
      {"DUPLICATE_TRANSACTION", "You have a deposit open at another bin."},
      {"VERIFICATION_FAILED", "This item can't be accepted here."},
      {"WEIGHT_UNSTABLE", "The item could not be weighed. Please try again."},
      {"NO_ITEM", "No item was found on the flap."},
      {"ZERO_WEIGHT", "This item is too light."},
      {"OVER_MAX_WEIGHT", "This item is too heavy."},
      {"SESSION_EXPIRED", "Time ran out. Please scan again."},
  };
  for (const auto& entry : MESSAGES) {
    if (strcmp(code, entry.code) == 0) {
      return entry.message;
    }
  }
  return MESSAGE_TRY_AGAIN;
}

/** The message for a failed device API call. */
const char* messageFor(Api::Result result) {
  return result == Api::Result::ServerError ? messageFor(api.lastErrorCode())
                                            : MESSAGE_NO_SERVER;
}

void startHold() {
  holding = true;
  holdStartMs = millis();
  shown = Shown::None;
}

/** Shows a state of the bin outside a session, once. */
void showState(Shown state) {
  if (state == shown) {
    return;
  }
  shown = state;
  switch (state) {
    case Shown::None:
      break;
    case Shown::Ready:
      feedback.ready();
      feedback.show(Feedback::Screen::Idle);
      break;
    case Shown::PanelOpen:
      feedback.problem();
      feedback.showProblem(MESSAGE_SERVICING);
      break;
    case Shown::OutOfService:
      feedback.problem();
      feedback.showProblem(MESSAGE_OUT_OF_SERVICE);
      break;
    case Shown::PointsPending:
      feedback.busy();
      feedback.show(Feedback::Screen::PointsPending);
      break;
    case Shown::BinFull:
      feedback.problem();
      feedback.show(Feedback::Screen::BinFull);
      break;
    case Shown::Offline:
      feedback.problem();
      feedback.show(Feedback::Screen::Offline);
      break;
  }
}

void disarmScanner() {
  if (!scannerArmed.load()) {
    return;
  }
  const Station::Result result = qrScanner.disarm();
  scannerArmed.store(false);
  if (result == Station::Result::Ok) {
    // A confirmed disarm is the recovery the overrun flag was waiting for.
    deviceCallOverrun.store(false);
  } else {
    setStationFault(stationFaultFor(result));
  }
}

/**
 * Reads the service panel. Opening it disables the driver at once and
 * disarms the scanner; closing it after an actuator fault clears the fault,
 * since someone has looked at the bin (System_Plan.md §8.2). True while open.
 */
bool updatePanel() {
  const bool open = interlock.isOpen();
  if (open == panelOpen) {
    return open;
  }
  panelOpen = open;
  telemetryDue = true;
  if (open) {
    actuator.disable();
    disarmScanner();
    panelOpenedSinceFault = true;
    Serial.println("panel: open");
  } else {
    Serial.println("panel: closed");
    if (faultLatches(latchedFault) && panelOpenedSinceFault) {
      Serial.printf("fault: %s cleared\n", faultCodeName(latchedFault));
      latchedFault = FaultCode::None;
    }
  }
  return open;
}

/**
 * Applies the server's calibration when it is new (API_Design.md §5.2).
 * False means the scale is not running the calibration the server just sent —
 * missing, unchanged-but-never-applied, or refused — so a deposit must not
 * weigh with it.
 */
bool applyCalibration() {
  const Api::Calibration& calibration = api.calibration();
  if (!calibration.present) {
    return false;
  }
  if (calibrationApplied && calibration.calibratedAt == appliedCalibrationAt) {
    return true;
  }
  if (!scale.applyCalibration(calibration.weightOffset,
                              calibration.weightScaleFactor)) {
    return false;
  }
  calibrationApplied = true;
  appliedCalibrationAt = calibration.calibratedAt;
  Serial.println("scale: calibration applied");
  return true;
}

/** Asks the bin station what is working and sets its fault (§11.2). True
 *  when everything answers. */
bool checkStation(Station::Health& health) {
  const Station::Result result = station.health(health);
  FaultCode fault = stationFaultFor(result);
  if (result == Station::Result::Ok) {
    if (!health.scanner) {
      fault = FaultCode::ScannerUnavailable;
    } else if (!health.camera) {
      fault = FaultCode::CameraUnavailable;
    } else if (!health.model) {
      fault = FaultCode::ModelUnavailable;
    }
  } else if (fault == FaultCode::None) {
    fault = FaultCode::StationUnreachable;
  }
  setStationFault(fault);
  return fault == FaultCode::None;
}

/**
 * The bin station's health, the config and a telemetry reading: at boot,
 * after each session, whenever the panel state or the fault changes, and on
 * the interval (API_Design.md §5.3, §5.2 and §11.2, P16).
 */
void sendTelemetry() {
  lastTelemetryMs = millis();
  // While armed, the device calls below can block for HTTP_TIMEOUT_MS each.
  // The lease lets the keepalive cover them, but only up to DEVICE_CALL_LEASE_MS.
  const bool leased = scannerArmed.load();
  if (leased) {
    beginDeviceCall();
  }
  Station::Health health{};
  checkStation(health);
  if (api.fetchConfig() == Api::Result::Ok) {
    applyCalibration();
  }

  const float distance = binLevel.readDistanceMm();
  telemetryDue = false;
  Api::BinStatus status{};
  if (distance >= 0 &&
      api.sendTelemetry(distance, panelOpen, currentFault(), status) ==
          Api::Result::Ok) {
    binFull = status.full;
  }
  if (leased) {
    endDeviceCall();
  }
  if (distance < 0) {
    // distance_mm is required, so there is nothing to send until the sensor
    // answers; the next try is on the interval.
    Serial.println("telemetry: not sent, the bin level sensor did not answer");
  }
}

/** True when a stable reading says the flap is empty. */
bool isEmpty(const Scale::Reading& reading) {
  return reading.stable && fabsf(reading.grams) <= SCALE_EMPTY_TOLERANCE_G;
}

/**
 * Ends a session that did not transfer an item: shows why and, if anything is
 * on the flap, returns it to the tray first (the Return row of §8.2). An
 * unsure reading is treated as an item, since returning is always safe.
 */
void endWithoutTransfer(const char* reason) {
  telemetryDue = true;
  char message[MESSAGE_LENGTH];
  strlcpy(message, reason, sizeof(message));

  const Scale::Reading reading = scale.readStable();
  if (!isEmpty(reading) && !updatePanel()) {
    const Actuator::CycleResult cycle = actuator.returnItem();
    latchFault(cycle.fault);
    referenceNeeded = true;
    if (cycle.levelReached) {
      snprintf(message, sizeof(message), "%s %s", reason, MESSAGE_TAKE_ITEM);
    }
  }

  feedback.problem();
  feedback.showProblem(message);
  feedback.beepLong();
  startHold();
}

/** The panel opened during a session: no motion, cancel an OPEN session. */
void endForPanel(const String& sessionId, bool cancel) {
  if (cancel) {
    api.cancelSession(sessionId);
  }
  telemetryDue = true;
  startHold();
  showState(Shown::PanelOpen);
}

/**
 * Whether the bin may take a deposit, in the order of step 1 (System_Plan.md
 * §8.2). Fixes what it can on the way — resends a saved `complete`, returns
 * an item left on the flap, takes a new reference — and shows why not.
 */
bool readyCheck() {
  if (panelOpen) {
    showState(Shown::PanelOpen);
    return false;
  }
  if (latchedFault != FaultCode::None) {
    showState(Shown::OutOfService);
    return false;
  }
  if (settings.hasPendingComplete()) {
    showState(Shown::PointsPending);
    if (millis() - lastResendMs >= PENDING_RESEND_MS) {
      lastResendMs = millis();
      api.flushPendingComplete();
    }
    return false;
  }
  if (!actuator.isRested()) {
    return false;
  }

  const Scale::Reading reading = scale.readStable();
  if (reading.samples == 0) {
    // Not calibrated, or the amplifier does not answer.
    showState(Shown::OutOfService);
    return false;
  }
  if (!actuator.isLevel() || !isEmpty(reading)) {
    if (actuator.isLevel() && !reading.stable) {
      return false;  // still settling; read again
    }
    // An item already on the flap, or a flap left tipped: return it first.
    const Actuator::CycleResult cycle = actuator.returnItem();
    latchFault(cycle.fault);
    referenceNeeded = true;
    if (cycle.cycleMs == 0) {
      showState(Shown::OutOfService);  // the actuator could not move
    } else if (!isEmpty(reading) && cycle.levelReached) {
      feedback.problem();
      feedback.showProblem(MESSAGE_TAKE_ITEM);
      startHold();
    }
    return false;
  }

  if (binFull) {
    showState(Shown::BinFull);
    return false;
  }
  Station::Health health{};
  if (!checkStation(health)) {
    showState(stationFault == FaultCode::StationUnreachable
                  ? Shown::Offline
                  : Shown::OutOfService);
    return false;
  }
  if (referenceNeeded || !health.reference) {
    const Station::Result result = verifier.captureEmptyReference();
    if (result != Station::Result::Ok) {
      setStationFault(stationFaultFor(result));
      showState(Shown::OutOfService);
      return false;
    }
    referenceNeeded = false;
  }
  return true;
}

/**
 * Steps 2 to 7 for one scanned token. Each early end goes through
 * endWithoutTransfer() or endForPanel(); nothing tips toward the bin unless
 * `deposit` answered accepted.
 */
void runDeposit(const String& qrToken) {
  shown = Shown::None;
  feedback.busy();

  // 2. Scan.
  Api::SessionInfo session{};
  Api::Result result = api.openSession(qrToken, session);
  if (result != Api::Result::Ok) {
    if (result == Api::Result::ServerError &&
        strcmp(api.lastErrorCode(), "BIN_FULL") == 0) {
      binFull = true;
    }
    endWithoutTransfer(messageFor(result));
    return;
  }
  Serial.println("deposit: session open");
  feedback.showWelcome(session.displayName.c_str());
  feedback.clearCancel();

  // Fresh calibration before weighing: the reported weight depends on it, and
  // the scanner is already disarmed so this cannot interrupt armed polling.
  const Api::Result configResult = api.fetchConfig();
  if (configResult != Api::Result::Ok) {
    api.cancelSession(session.sessionId);
    endWithoutTransfer(messageFor(configResult));
    return;
  }
  if (!applyCalibration()) {
    api.cancelSession(session.sessionId);
    endWithoutTransfer(MESSAGE_OUT_OF_SERVICE);
    return;
  }

  // 3. Weigh.
  feedback.show(Feedback::Screen::Weighing);
  const unsigned long sessionStartMs = millis();
  const unsigned long sessionMs = session.expiresInSeconds * 1000UL;
  Scale::Reading reading{};
  while (true) {
    if (updatePanel()) {
      endForPanel(session.sessionId, true);
      return;
    }
    if (millis() - lastPollMs >= STATION_EVENT_POLL_MS) {
      lastPollMs = millis();
      const Station::Result polled = station.pollEvents();
      if (polled != Station::Result::Ok) {
        setStationFault(stationFaultFor(polled));
        api.cancelSession(session.sessionId);
        endWithoutTransfer(MESSAGE_OUT_OF_SERVICE);
        return;
      }
    }
    if (feedback.cancelPressed()) {
      api.cancelSession(session.sessionId);
      endWithoutTransfer("Cancelled.");
      return;
    }
    if (millis() - sessionStartMs >= sessionMs) {
      // The server expires the session itself (§4.1).
      endWithoutTransfer(messageFor("SESSION_EXPIRED"));
      return;
    }
    reading = scale.readStable();
    if (reading.stable && reading.grams > SCALE_EMPTY_TOLERANCE_G) {
      break;
    }
  }

  // 4. Verify. No answer is never a pass: cancel and return the item.
  Station::Verdict verdict{};
  const Station::Result verified = verifier.verify(verdict);
  if (verified != Station::Result::Ok) {
    setStationFault(stationFaultFor(verified));
    if (strcmp(station.lastErrorCode(), "NO_REFERENCE") == 0) {
      referenceNeeded = true;
    }
    api.cancelSession(session.sessionId);
    endWithoutTransfer(MESSAGE_NOT_CHECKED);
    return;
  }

  // Weigh once more after the check: verify can take seconds, and the item
  // may have been lifted or swapped while the station was looking.
  const Scale::Reading weighed = scale.readStable();
  if (weighed.samples == 0) {
    api.cancelSession(session.sessionId);
    endWithoutTransfer(MESSAGE_OUT_OF_SERVICE);
    return;
  }
  if (!weighed.stable || weighed.grams <= SCALE_EMPTY_TOLERANCE_G) {
    api.cancelSession(session.sessionId);
    endWithoutTransfer(
        messageFor(weighed.stable ? "NO_ITEM" : "WEIGHT_UNSTABLE"));
    return;
  }
  reading = weighed;

  // 5. Deposit. The server decides.
  if (updatePanel()) {
    endForPanel(session.sessionId, true);
    return;
  }
  Api::DepositResult deposit{};
  result = api.sendDeposit(session.sessionId, reading.grams, reading.stable,
                           reading.samples, verdict.method, verdict.passed,
                           verdict.score, deposit);
  if (result != Api::Result::Ok || !deposit.accepted) {
    endWithoutTransfer(result == Api::Result::Ok ? MESSAGE_TRY_AGAIN
                                                 : messageFor(result));
    return;
  }
  Serial.printf("deposit: accepted, %.2f g\n", reading.grams);
  feedback.showAccepted(reading.grams, deposit.pointsPreview);

  // The server accepted the deposit, but the flap can still be emptied
  // before the actuator runs. If it is, do not move anything and do not send
  // `complete`: the session stays ACCEPTED for the server's admin review.
  // The actuator was never attempted, so there is no actuator_ok to report.
  const Scale::Reading stillThere = scale.readStable();
  if (stillThere.samples == 0 || !stillThere.stable ||
      stillThere.grams <= SCALE_EMPTY_TOLERANCE_G) {
    telemetryDue = true;
    feedback.problem();
    feedback.showProblem(MESSAGE_ITEM_GONE);
    feedback.beepLong();
    startHold();
    return;
  }

  // 6. Transfer. An open panel now leaves the session ACCEPTED with no
  // `complete`, which the server lists for an admin to review.
  if (updatePanel()) {
    endForPanel(session.sessionId, false);
    return;
  }
  const Actuator::CycleResult cycle = actuator.transfer();
  latchFault(cycle.fault);
  referenceNeeded = true;

  // 7. Complete. This is the call that credits points.
  Api::CompleteResult completed{};
  result = api.sendComplete(session.sessionId, cycle.endReached, cycle.cycleMs,
                            completed);
  telemetryDue = true;
  if (result == Api::Result::Ok && cycle.endReached) {
    feedback.ready();
    feedback.showDone(completed.pointsAwarded, completed.balance);
    feedback.beepShort();
  } else if (result == Api::Result::Ok) {
    feedback.problem();
    feedback.showProblem(
        "Your item could not be moved into the bin. No points were added.");
    feedback.beepLong();
  } else if (result == Api::Result::NetworkError) {
    // Saved to NVS; readyCheck() resends it before the next deposit.
    feedback.busy();
    feedback.show(Feedback::Screen::PointsPending);
  } else {
    feedback.problem();
    feedback.showProblem(messageFor(result));
    feedback.beepLong();
  }
  startHold();
}

}  // namespace

void setup() {
  Serial.begin(SERIAL_LOG_BAUD);
  Serial.printf("\nSmart E-Waste controller %s\n", FIRMWARE_VERSION);

  // The driver first: off before anything else runs.
  actuator.begin();
  interlock.begin();
  settings.begin();
  feedback.begin();
  feedback.busy();

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
  station.begin();
  stationKeepalive.begin();

  if (!api.begin()) {
    feedback.problem();
  }

  // The keepalive pings GET /health only while the scanner is armed. If the
  // task cannot be created the bin must not arm: fail closed, not masked.
  if (xTaskCreatePinnedToCore(stationKeepaliveTask, "station-ka",
                              STATION_KEEPALIVE_STACK, nullptr,
                              STATION_KEEPALIVE_PRIORITY, &keepaliveTask,
                              STATION_KEEPALIVE_CORE) != pdPASS) {
    keepaliveTask = nullptr;
    Serial.println("keepalive: task create failed, arming disabled");
  }

  // The ESP32 has no clock. Nothing can be signed until this succeeds.
  api.syncClock();
  if (api.fetchConfig() == Api::Result::Ok) {
    applyCalibration();
  }

  // A `complete` saved by a previous run — the bin lost the server or lost
  // power after an item was already in the bin. Send it before anything else,
  // so those points are not lost (API_Design.md §3.6).
  if (settings.hasPendingComplete()) {
    Serial.println("resending a saved complete from the last run");
    api.flushPendingComplete();
  }

  updatePanel();
}

void loop() {
  mainLoopProgressMs.store(millis());
  updatePanel();
  if (telemetryDue || millis() - lastTelemetryMs >= TELEMETRY_INTERVAL_MS) {
    sendTelemetry();
  }
  if (holding) {
    if (millis() - holdStartMs < RESULT_SCREEN_MS) {
      return;
    }
    holding = false;
  }

  if (!scannerArmed.load()) {
    if (!readyCheck()) {
      return;
    }
    if (deviceCallOverrun.load()) {
      // A blocking call outlived its lease while armed. Confirm the station
      // scanner is disarmed before re-arming so queued scans cannot replay.
      const Station::Result recovered = qrScanner.disarm();
      if (recovered != Station::Result::Ok) {
        setStationFault(stationFaultFor(recovered));
        return;
      }
      deviceCallOverrun.store(false);
    }
    if (!keepaliveReady()) {
      showState(Shown::OutOfService);
      return;
    }
    if (!qrScanner.arm()) {
      setStationFault(stationFaultFor(Station::Result::Error));
      return;
    }
    scannerArmed.store(true);
    feedback.clearCancel();
    lastPollMs = millis();
    showState(Shown::Ready);
    return;
  }

  // Armed: stop at once if the bin stops being ready or a device call
  // outlived its lease. The overrun disarms first; readyCheck() re-arms only
  // after a confirmed disarm and a fresh keepalive heartbeat.
  if (deviceCallOverrun.load() || panelOpen ||
      currentFault() != FaultCode::None || binFull) {
    disarmScanner();
    return;
  }
  if (millis() - lastPollMs < STATION_EVENT_POLL_MS) {
    return;
  }
  lastPollMs = millis();
  const Station::Result polled = station.pollEvents();
  if (polled != Station::Result::Ok) {
    setStationFault(stationFaultFor(polled));
    disarmScanner();
    return;
  }
  String token;
  if (qrScanner.takeToken(token)) {
    disarmScanner();
    runDeposit(token);
  }
}
