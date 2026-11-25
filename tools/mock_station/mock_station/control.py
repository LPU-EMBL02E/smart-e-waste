"""Mock-only control commands for tests and the control page.

These commands are not part of the station API in API_Design.md section 11. They
exist only so a tester can inject scanner/touch events and force component
failure scenarios without real hardware.
"""

from __future__ import annotations

import json
import sys
import threading
from collections.abc import Callable
from pathlib import Path
from typing import Any

from .errors import CameraUnavailable, ValidationError


def _bool(value: Any, name: str) -> bool:
    if not isinstance(value, bool):
        raise ValidationError(f"{name} must be a boolean.")
    return value


def _int(value: Any, name: str, minimum: int = 0) -> int:
    if not isinstance(value, int) or isinstance(value, bool) or value < minimum:
        raise ValidationError(f"{name} must be an integer >= {minimum}.")
    return value


def _float(value: Any, name: str) -> float:
    if not isinstance(value, (int, float)) or isinstance(value, bool):
        raise ValidationError(f"{name} must be a number.")
    return float(value)


def _require_dict(value: Any, name: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise ValidationError(f"{name} must be an object.")
    return value


def _simulated_camera(camera: object) -> object | None:
    if hasattr(camera, "width") and hasattr(camera, "height"):
        return camera
    return None


def apply_control_command(
    command: dict[str, Any],
    *,
    components: Any,
    settings: Any,
    event_queue: Any,
) -> dict[str, Any]:
    """Apply one mock control command and return a small result object."""
    if not isinstance(command, dict) or not command:
        raise ValidationError("Control command must be a non-empty object.")

    if "inject_qr" in command:
        token = command["inject_qr"]
        if not isinstance(token, str) or not token:
            raise ValidationError("inject_qr must be a non-empty string.")
        return {"queued": components.scanner.inject_token(token)}

    if "inject_touch" in command:
        action = command["inject_touch"]
        if action != "cancel":
            raise ValidationError("inject_touch must be 'cancel'.")
        return {"queued": event_queue.put_touch(action)}

    if "set_scanner_disarm_failures" in command:
        scanner = components.scanner
        if not hasattr(scanner, "set_disarm_failures"):
            raise ValidationError("scanner does not support disarm failures.")
        scanner.set_disarm_failures(
            _int(
                command["set_scanner_disarm_failures"],
                "set_scanner_disarm_failures",
            )
        )
        return {"disarm_failures": scanner.describe().get("disarm_failures", 0)}

    if "set_available" in command:
        values = _require_dict(command["set_available"], "set_available")
        allowed = {"scanner", "camera", "model"}
        unknown = set(values) - allowed
        if unknown:
            raise ValidationError(
                f"Unknown component(s): {', '.join(sorted(unknown))}."
            )
        updated: dict[str, Any] = {}
        for name in allowed & set(values):
            component = getattr(components, name)
            component.set_available(_bool(values[name], f"set_available.{name}"))
            updated[name] = component.describe()
        if not updated:
            raise ValidationError("set_available needs scanner, camera or model.")
        return {"components": updated}

    if "set_camera" in command:
        values = _require_dict(command["set_camera"], "set_camera")
        camera = components.camera
        simulated = _simulated_camera(camera)
        empty = values.get("empty")
        item = values.get("item")
        if empty is not None:
            _bool(empty, "set_camera.empty")
        if item is not None:
            _bool(item, "set_camera.item")
        if (empty is True or item is True) and simulated is None:
            raise ValidationError(
                "camera does not support simulated presence controls."
            )
        if "changed_pixels" in values:
            camera.set_changed_pixels(
                _int(values["changed_pixels"], "set_camera.changed_pixels")
            )
        if empty is True:
            camera.set_changed_pixels(0)
        if item is True:
            count = int(simulated.width) * int(simulated.height)
            required = int(settings.presence_threshold * count) + 1
            camera.set_changed_pixels(min(count, required))
        if "capture_failures" in values:
            camera.set_capture_failures(
                _int(values["capture_failures"], "set_camera.capture_failures")
            )
        if "reference_failures" in values:
            camera.set_reference_failures(
                _int(values["reference_failures"], "set_camera.reference_failures")
            )
        return {"camera": camera.describe()}

    if "set_model" in command:
        values = _require_dict(command["set_model"], "set_model")
        model = components.model
        if "probability" in values:
            model.set_probability(
                _float(values["probability"], "set_model.probability")
            )
        if "result" in values:
            result = values["result"]
            if result not in {"pass", "fail"}:
                raise ValidationError("set_model.result must be 'pass' or 'fail'.")
            threshold = float(settings.model_pass_threshold)
            probability = (
                min(1.0, threshold + 0.1)
                if result == "pass"
                else max(0.0, threshold - 0.1)
            )
            model.set_probability(probability)
        if "failures" in values:
            model.set_failures(_int(values["failures"], "set_model.failures"))
        return {"model": model.describe()}

    if "set_reference" in command:
        values = _require_dict(command["set_reference"], "set_reference")
        action = values.get("action")
        camera = components.camera
        if action == "capture":
            stored = camera.take_reference()
            if not stored:
                raise CameraUnavailable("Reference was not stored.")
            return {"stored": True}
        if action == "clear":
            camera.clear_reference()
            return {"cleared": True}
        if action == "fail_next":
            if not hasattr(camera, "fail_next_reference"):
                raise ValidationError(
                    "camera does not support reference failure injection."
                )
            camera.fail_next_reference()
            return {"fail_next": True}
        raise ValidationError(
            "set_reference.action must be capture, clear or fail_next."
        )

    raise ValidationError("Unknown control command.")


class Control:
    """Optional append-only JSONL control file for headless testing."""

    def __init__(
        self,
        path: str | Path,
        poll_s: float,
        apply: Callable[[dict[str, Any]], None],
        *,
        process_existing: bool = False,
    ) -> None:
        self._path = Path(path)
        self._poll_s = poll_s
        self._apply = apply
        self._process_existing = process_existing
        self._offset: int | None = None
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None

    def poll_once(self) -> None:
        if not self._path.is_file():
            return
        with self._path.open("r", encoding="utf-8", errors="replace") as handle:
            if self._offset is None:
                self._offset = 0 if self._process_existing else handle.seek(0, 2)
            handle.seek(self._offset)
            data = handle.read()
            self._offset = handle.tell()
        for line in data.splitlines():
            line = line.strip()
            if not line:
                continue
            try:
                command = json.loads(line)
                if not isinstance(command, dict):
                    raise TypeError("control line must be a JSON object")
                self._apply(command)
            except Exception as error:  # noqa: BLE001 - control file must not kill the mock
                print(f"mock-station control: {error}", file=sys.stderr)

    def start(self) -> None:
        self._thread = threading.Thread(
            target=self._run, name="mock-station-control", daemon=True
        )
        self._thread.start()

    def _run(self) -> None:
        while not self._stop.wait(self._poll_s):
            self.poll_once()

    def stop(self) -> None:
        self._stop.set()
        if self._thread is not None:
            self._thread.join(timeout=2.0)
