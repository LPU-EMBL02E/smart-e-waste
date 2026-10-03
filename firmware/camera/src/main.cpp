/**
 * Camera firmware — the ESP32-CAM. System_Plan.md §2 and §8.2.
 *
 * One job: answer "is an item present on the weighing platform?" with pass or
 * fail and a score, over a serial link to the controller.
 *
 * What it must NOT do:
 *   - identify what the item is. The system has one points rate for every item
 *     precisely because this hardware cannot tell them apart
 *     (System_Plan.md §5.7 rule 1);
 *   - join the Wi-Fi network or talk to the server. Only the NodeMCU does that;
 *   - answer OK when it is unsure. A silent or uncertain camera must read as
 *     FAIL on the controller, because points are credited only after
 *     verification passes.
 *
 * Protocol (firmware/README.md), one line in, one line out:
 *
 *   -> VERIFY            <- OK <score> | FAIL <score>
 *   -> REFERENCE         <- READY | ERROR <reason>
 *   -> PING              <- PONG
 *
 * Method: frame differencing against a stored reference frame of the empty
 * platform, in an LED-lit closed chamber.
 */

#include <Arduino.h>

#include "../include/config.h"

namespace {

/**
 * TODO: implement.
 *
 *   1. esp_camera_init() with grayscale, FRAME_WIDTH x FRAME_HEIGHT.
 *      The AI-Thinker pin map is the one in the ESP32 core's
 *      camera_pins.h — do not invent pin numbers for the camera itself.
 *   2. captureReference(): discard WARMUP_FRAMES, then store one frame as the
 *      empty-platform reference. Re-taken on the controller's request, which it
 *      sends whenever the chamber is known to be empty — otherwise lighting
 *      drift slowly turns every frame into a "difference".
 *   3. verify(): capture a frame, count pixels differing from the reference by
 *      more than PIXEL_DIFFERENCE_THRESHOLD, and report
 *      score = changed / total. Pass when score >= PRESENCE_SCORE_THRESHOLD.
 *   4. Answer FAIL with score 0 when there is no reference frame yet, never OK.
 */
void handleVerify() {
  Serial.println("FAIL 0.0000");   // fail closed until implemented
}

void handleReference() {
  Serial.println("ERROR not-implemented");
}

void handleLine(const String& line) {
  if (line == "VERIFY") {
    handleVerify();
  } else if (line == "REFERENCE") {
    handleReference();
  } else if (line == "PING") {
    Serial.println("PONG");
  } else if (line.length() > 0) {
    Serial.println("ERROR unknown-command");
  }
}

}  // namespace

void setup() {
  // The link to the controller is this board's only serial port, so it is also
  // the only place a log line could go — keep logs off it entirely, or a stray
  // line gets parsed as a verdict.
  Serial.begin(SERIAL_LINK_BAUD);

  // TODO: esp_camera_init(), then capture the first reference frame.
}

void loop() {
  if (Serial.available()) {
    String line = Serial.readStringUntil('\n');
    line.trim();
    handleLine(line);
  }
}
