"""Simulated and placeholder hardware components for the mock station.

Only ``simulated`` components are functional. ``hardware`` components are
placeholders that always report unavailable until the real device specifications
are established; they never invent GPIO, serial command bytes, camera settings,
or model files.
"""

from __future__ import annotations

from dataclasses import dataclass
from types import SimpleNamespace
from typing import Any

from .errors import CameraUnavailable, ModelUnavailable, ScannerUnavailable
from .events import EventQueue
from .settings import Settings, SettingsError


@dataclass(frozen=True)
class Frame:
    """A simulated camera frame. ``gray`` is one byte per pixel."""

    gray: tuple[int, ...]
    color: bytes


class SimulatedScanner:
    """Scanner adapter driven only by explicit token injection."""

    def __init__(self, events: EventQueue, available: bool = True) -> None:
        self._events = events
        self._open = available
        self._armed = False
        self._disarm_failures = 0

    @property
    def armed(self) -> bool:
        return self._armed

    def is_open(self) -> bool:
        return self._open

    def set_available(self, available: bool) -> None:
        self._open = bool(available)
        if not self._open:
            self._armed = False

    def arm(self) -> bool:
        if not self._open:
            raise ScannerUnavailable("Simulated scanner is unavailable.")
        self._events.clear_tokens()
        self._armed = True
        return True

    def set_disarm_failures(self, count: int) -> None:
        self._disarm_failures = max(0, count)

    def disarm(self) -> bool:
        if self._disarm_failures > 0:
            self._disarm_failures -= 1
            raise ScannerUnavailable("Simulated scanner disarm failed.")
        self._armed = False
        return True

    def inject_token(self, token: str) -> bool:
        """Queue a token only while armed, like a real scanner read."""
        if not self._open or not self._armed:
            return False
        return self._events.put_qr(token)

    def describe(self) -> dict[str, Any]:
        return {
            "type": "simulated",
            "available": self._open,
            "armed": self._armed,
            "disarm_failures": self._disarm_failures,
        }


class UnavailableScanner:
    """Placeholder for a real scanner; always unavailable."""

    def is_open(self) -> bool:
        return False

    def set_available(self, available: bool) -> None:
        return None

    def arm(self) -> bool:
        raise ScannerUnavailable("Real scanner support is not configured.")

    def disarm(self) -> bool:
        return True

    def set_disarm_failures(self, count: int) -> None:
        return None

    def inject_token(self, token: str) -> bool:
        return False

    def describe(self) -> dict[str, Any]:
        return {
            "type": "hardware",
            "available": False,
            "armed": False,
            "note": "GM865 command bytes are TODO(team); placeholder only",
        }


class SimulatedCamera:
    """Deterministic camera stand-in with reference and failure controls."""

    def __init__(
        self,
        *,
        available: bool = True,
        width: int = 64,
        height: int = 64,
        changed_pixels: int = 256,
        capture_failures: int = 0,
        reference_failures: int = 0,
    ) -> None:
        if width <= 0 or height <= 0:
            raise ValueError("camera width and height must be positive")
        self._open = available
        self._width = width
        self._height = height
        self._changed_pixels = max(0, changed_pixels)
        self._capture_failures = max(0, capture_failures)
        self._reference_failures = max(0, reference_failures)
        self._reference: Frame | None = None

    @property
    def width(self) -> int:
        return self._width

    @property
    def height(self) -> int:
        return self._height

    @property
    def changed_pixels(self) -> int:
        return self._changed_pixels

    def is_open(self) -> bool:
        return self._open

    def has_reference(self) -> bool:
        return self._reference is not None

    def reference_gray(self) -> tuple[int, ...] | None:
        return self._reference.gray if self._reference is not None else None

    def set_available(self, available: bool) -> None:
        self._open = bool(available)
        if not self._open:
            self._reference = None

    def set_changed_pixels(self, changed_pixels: int) -> None:
        self._changed_pixels = max(0, min(changed_pixels, self._width * self._height))

    def set_capture_failures(self, count: int) -> None:
        self._capture_failures = max(0, count)

    def set_reference_failures(self, count: int) -> None:
        self._reference_failures = max(0, count)

    def fail_next_capture(self) -> None:
        self._capture_failures += 1

    def fail_next_reference(self) -> None:
        self._reference_failures += 1

    def clear_reference(self) -> None:
        """Drop the reference, simulating a station restart."""
        self._reference = None

    def _blank_frame(self) -> Frame:
        count = self._width * self._height
        return Frame(gray=tuple([0] * count), color=bytes(count))

    def _frame_with_changes(self) -> Frame:
        count = self._width * self._height
        gray = [0] * count
        for index in range(min(self._changed_pixels, count)):
            gray[index] = 255
        return Frame(gray=tuple(gray), color=bytes(gray))

    def take_reference(self) -> bool:
        if not self._open:
            raise CameraUnavailable("Simulated camera is unavailable.")
        if self._reference_failures > 0:
            self._reference_failures -= 1
            raise CameraUnavailable("Simulated reference capture failed.")
        self._reference = self._blank_frame()
        return True

    def capture(self) -> Frame:
        if not self._open:
            raise CameraUnavailable("Simulated camera is unavailable.")
        if self._capture_failures > 0:
            self._capture_failures -= 1
            raise CameraUnavailable("Simulated camera capture failed.")
        if self._reference is None:
            from .errors import NoReference

            raise NoReference("No reference image is stored.")
        return self._frame_with_changes()

    def describe(self) -> dict[str, Any]:
        return {
            "type": "simulated",
            "available": self._open,
            "width": self._width,
            "height": self._height,
            "changed_pixels": self._changed_pixels,
            "has_reference": self.has_reference(),
            "capture_failures": self._capture_failures,
            "reference_failures": self._reference_failures,
        }


class UnavailableCamera:
    """Placeholder for a real camera; always unavailable."""

    def is_open(self) -> bool:
        return False

    def has_reference(self) -> bool:
        return False

    def reference_gray(self) -> tuple[int, ...] | None:
        return None

    def set_available(self, available: bool) -> None:
        return None

    def set_changed_pixels(self, changed_pixels: int) -> None:
        return None

    def set_capture_failures(self, count: int) -> None:
        return None

    def set_reference_failures(self, count: int) -> None:
        return None

    def clear_reference(self) -> None:
        return None

    def take_reference(self) -> bool:
        raise CameraUnavailable("Real camera support is not configured.")

    def capture(self) -> Frame:
        raise CameraUnavailable("Real camera support is not configured.")

    def describe(self) -> dict[str, Any]:
        return {
            "type": "hardware",
            "available": False,
            "has_reference": False,
            "note": "picamera2 bench values are TODO(team); placeholder only",
        }


class SimulatedModel:
    """Deterministic e-waste model stand-in."""

    def __init__(self, *, available: bool = True, probability: float = 0.93) -> None:
        self._loaded = available
        self._probability = probability
        self._failures = 0
        self.calls = 0

    def is_loaded(self) -> bool:
        return self._loaded

    def set_available(self, available: bool) -> None:
        self._loaded = bool(available)

    def set_probability(self, probability: float) -> None:
        if not 0.0 <= probability <= 1.0:
            raise ValueError("probability must be between 0.0 and 1.0")
        self._probability = probability

    def set_failures(self, count: int) -> None:
        self._failures = max(0, count)

    def fail_next(self) -> None:
        self._failures += 1

    def probability(self, frame: Frame) -> float:
        if not self._loaded:
            raise ModelUnavailable("Simulated model is not loaded.")
        if self._failures > 0:
            self._failures -= 1
            raise ModelUnavailable("Simulated model inference failed.")
        self.calls += 1
        return self._probability

    def describe(self) -> dict[str, Any]:
        return {
            "type": "simulated",
            "loaded": self._loaded,
            "probability": self._probability,
            "failures": self._failures,
            "calls": self.calls,
        }


class UnavailableModel:
    """Placeholder for a real ONNX model; always unavailable."""

    def is_loaded(self) -> bool:
        return False

    def set_available(self, available: bool) -> None:
        return None

    def set_probability(self, probability: float) -> None:
        return None

    def set_failures(self, count: int) -> None:
        return None

    def probability(self, frame: Frame) -> float:
        raise ModelUnavailable("Real model support is not configured.")

    def describe(self) -> dict[str, Any]:
        return {
            "type": "hardware",
            "loaded": False,
            "note": "ONNX model file and thresholds are TODO(team); placeholder only",
        }


def _kind(config: dict[str, Any], name: str) -> str:
    kind = config.get("type", "simulated")
    if kind not in {"simulated", "hardware"}:
        raise SettingsError(f"components.{name}.type must be 'simulated' or 'hardware'")
    return kind


def build_components(settings: Settings) -> SimpleNamespace:
    """Build scanner/camera/model adapters from settings."""
    events = EventQueue()
    kinds: dict[str, str] = {}

    scanner_config = settings.component("scanner")
    kinds["scanner"] = _kind(scanner_config, "scanner")
    if kinds["scanner"] == "simulated":
        scanner = SimulatedScanner(events, scanner_config.get("available", True))
    else:
        scanner = UnavailableScanner()

    camera_config = settings.component("camera")
    kinds["camera"] = _kind(camera_config, "camera")
    if kinds["camera"] == "simulated":
        camera = SimulatedCamera(
            available=camera_config.get("available", True),
            width=int(camera_config.get("width", 64)),
            height=int(camera_config.get("height", 64)),
            changed_pixels=int(camera_config.get("changed_pixels", 256)),
            capture_failures=int(camera_config.get("capture_failures", 0)),
            reference_failures=int(camera_config.get("reference_failures", 0)),
        )
    else:
        camera = UnavailableCamera()

    model_config = settings.component("model")
    kinds["model"] = _kind(model_config, "model")
    if kinds["model"] == "simulated":
        model = SimulatedModel(
            available=model_config.get("available", True),
            probability=float(model_config.get("probability", 0.93)),
        )
    else:
        model = UnavailableModel()

    return SimpleNamespace(
        scanner=scanner,
        camera=camera,
        model=model,
        event_queue=events,
        kinds=kinds,
    )
