#pragma once

/**
 * Camera firmware constants.
 *
 * The verification method is frame differencing against the empty platform in
 * an LED-lit chamber (System_Plan.md §2). A controlled, closed chamber is what
 * makes this work at all: the lighting does not change between the reference
 * frame and the comparison, so a difference means an object rather than a
 * shadow.
 *
 * TODO(team): every threshold here has to be set on the real chamber, with the
 * real LEDs, against real e-waste items. Record the successful verification
 * rate over at least ten trials (guidelines, Steps 5 and 13).
 */

constexpr unsigned long SERIAL_LOG_BAUD = 115200;
constexpr unsigned long SERIAL_LINK_BAUD = 115200;

// Grayscale at a small frame size is plenty: this detects presence, not
// identity, and a smaller frame is faster and fits in RAM comfortably.
constexpr int FRAME_WIDTH = 320;
constexpr int FRAME_HEIGHT = 240;

// A pixel counts as changed when it differs from the reference by more than
// this. Too low and sensor noise reads as an item; too high and a dark item on
// a dark platform reads as nothing.
constexpr int PIXEL_DIFFERENCE_THRESHOLD = 35;

// Fraction of changed pixels needed to call an item present. This is the score
// reported to the controller and stored as verification_score.
constexpr float PRESENCE_SCORE_THRESHOLD = 0.08f;

// Let the sensor's auto-exposure settle before a frame is used. A frame grabbed
// too early differs from the reference for reasons that have nothing to do with
// an item being there.
constexpr int WARMUP_FRAMES = 3;
