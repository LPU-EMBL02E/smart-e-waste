#pragma once

#include <Arduino.h>

#include "../include/config.hpp"

/**
 * The serial link to the controller: one line in, one line out.
 * System_Plan.md §8.2, protocol in firmware/README.md.
 *
 *   -> VERIFY            <- OK <score> | FAIL <score>
 *   -> REFERENCE         <- READY | ERROR <reason>
 *   -> PING              <- PONG
 *
 * Every answer is written to Serial, which is also the link itself — so nothing
 * else may be printed there, or a stray line gets parsed as a verdict.
 */
class ControllerLink {
 public:
  explicit ControllerLink() = default;

  /** Answer one trimmed line from the controller. */
  void handleLine(const String& line) {
    if (line == "VERIFY") {
      p_handleVerify();
    } else if (line == "REFERENCE") {
      p_handleReference();
    } else if (line == "PING") {
      Serial.println("PONG");
    } else if (line.length() > 0) {
      Serial.println("ERROR unknown-command");
    }
  }

 private:
  /**
   * TODO: implement.
   *
   *   1. esp_camera_init() with grayscale, FRAME_WIDTH x FRAME_HEIGHT.
   *      The AI-Thinker pin map is the one in the ESP32 core's
   *      camera_pins.h — do not invent pin numbers for the camera itself.
   *   2. captureReference(): discard WARMUP_FRAMES, then store one frame as the
   *      empty-platform reference. Re-taken on the controller's request, which
   *      it sends whenever the chamber is known to be empty — otherwise
   *      lighting drift slowly turns every frame into a "difference".
   *   3. verify(): capture a frame, count pixels differing from the reference
   *      by more than PIXEL_DIFFERENCE_THRESHOLD, and report
   *      score = changed / total. Pass when score >= PRESENCE_SCORE_THRESHOLD.
   *   4. Answer FAIL with score 0 when there is no reference frame yet, never
   *      OK.
   */
  void p_handleVerify() {
    Serial.println("FAIL 0.0000");  // fail closed until implemented
  }

  void p_handleReference() { Serial.println("ERROR not-implemented"); }
};
