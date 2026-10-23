#pragma once

#include <Arduino.h>

#include "../../include/config.hpp"
#include "../../include/pins.hpp"

/**
 * The item-verification module. System_Plan.md §8.2.
 *
 * Hides the ESP32-CAM link and answers one question: is an item actually on the
 * platform? It reports pass or fail and a score, nothing more.
 *
 * It confirms that SOMETHING is there, not WHAT it is. That is why there is one
 * points rate for every item: a higher rate for some categories could not be
 * verified by this hardware (System_Plan.md §5.7 rule 1).
 *
 * The three values this returns are the whole of the server's interface to
 * verification — method, passed, score — so swapping frame differencing for
 * another camera or another sensor changes this file and nothing on the server
 * (System_Plan.md §8.1).
 */
class Verifier {
  /*
   * TODO: implement over UART 1 (PIN_CAMERA_RX / PIN_CAMERA_TX) using the line
   * protocol in firmware/README.md.
   *
   *   -> VERIFY            ask for a verdict
   *   <- OK <0.00-1.00>    an item is present, with a score
   *   <- FAIL <0.00-1.00>  no item
   *   -> REFERENCE         re-learn the empty platform
   *   <- READY             reference stored
   *
   * Treat no reply within a timeout as FAIL, not as a pass: the points rule
   * says points are credited only after verification PASSES, so an unreachable
   * camera must never be read as an accepted item.
   *
   * Testing to record (guidelines, Step 5): that the camera can detect an item
   * on the weighing platform, and the successful verification rate over at
   * least ten trials.
   */

 public:
  struct Verdict {
    bool passed;
    float score;         // 0.0-1.0, stored as verification_score
    const char* method;  // stored as verification_method, e.g. "cam_diff"
  };

  explicit Verifier() = default;

  bool begin() { return false; }

  /** Ask the camera to compare the platform against its empty reference. */
  Verdict verify() { return {false, 0.0f, "cam_diff"}; }

  /**
   * Re-learn the empty platform. Call when the chamber is known to be empty —
   * after a transfer completes, and at boot — or lighting drift slowly turns
   * every frame into a "difference".
   */
  bool captureEmptyReference() { return false; }

  bool isResponding() { return false; }
};
