"""Two-step camera check for the mock station.

Mirrors TRACK-E step E5: presence by frame differencing against the reference,
then the e-waste model only when presence passes. The model is never run on a
presence failure, and no answer is ever a pass by default.
"""

from __future__ import annotations

from .components import Frame
from .errors import ModelUnavailable, NoReference


def presence_score(
    reference: tuple[int, ...], image: tuple[int, ...], pixel_threshold: int
) -> float:
    """Share of pixels whose brightness differs by more than the threshold."""
    if not reference or not image:
        return 0.0
    if len(reference) != len(image):
        return 1.0
    changed = sum(
        1
        for before, after in zip(reference, image)
        if abs(before - after) > pixel_threshold
    )
    return changed / len(reference)


def _score4(value: float) -> float:
    return round(float(value), 4)


def run_check(
    camera: object,
    model: object,
    *,
    pixel_threshold: int,
    presence_threshold: float,
    model_pass_threshold: float,
) -> dict[str, object]:
    """Run presence then model and return the section 11.5 verdict."""
    frame = camera.capture()  # type: ignore[attr-defined]
    if not isinstance(frame, Frame):
        raise ModelUnavailable("Camera returned an unreadable frame.")

    reference = camera.reference_gray()  # type: ignore[attr-defined]
    if reference is None:
        raise NoReference("No reference image is stored.")

    score = _score4(presence_score(reference, frame.gray, pixel_threshold))
    if score < presence_threshold:
        return {"method": "cam_diff", "passed": False, "score": score}

    probability = model.probability(frame)  # type: ignore[attr-defined]
    probability = float(probability)
    if not 0.0 <= probability <= 1.0:
        raise ModelUnavailable("Model returned an invalid score.")
    probability = _score4(probability)
    return {
        "method": "ewaste_model",
        "passed": probability >= model_pass_threshold,
        "score": probability,
    }
