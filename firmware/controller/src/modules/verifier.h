#pragma once

#include <Arduino.h>

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
namespace verifier {

struct Verdict {
  bool passed;
  float score;          // 0.0-1.0, stored as verification_score
  const char* method;   // stored as verification_method, e.g. "cam_diff"
};

bool begin();

/** Ask the camera to compare the platform against its empty reference. */
Verdict verify();

/**
 * Re-learn the empty platform. Call when the chamber is known to be empty —
 * after a transfer completes, and at boot — or lighting drift slowly turns
 * every frame into a "difference".
 */
bool captureEmptyReference();

bool isResponding();

}  // namespace verifier
