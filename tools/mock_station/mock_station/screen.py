"""Screen state and validation for the mock station. API_Design.md section 11.7.

The mock keeps the current screen so the control page can show what the
controller last asked for. Physical HDMI/touch kiosk behaviour is not simulated
here; that is a browser pointed at the page server on the Pi.
"""

from __future__ import annotations

import threading
from numbers import Real
from typing import Any

from .errors import ValidationError

SCREENS = {
    "IDLE",
    "WELCOME",
    "WEIGHING",
    "ACCEPTED",
    "DONE",
    "PENDING",
    "PROBLEM",
    "BIN_FULL",
    "OFFLINE",
}

REQUIRED_VALUES = {
    "WELCOME": {"name"},
    "ACCEPTED": {"weight_g", "points"},
    "DONE": {"points", "balance"},
    "PROBLEM": {"message"},
}

_TEXT_VALUES = {"WELCOME": {"name"}, "PROBLEM": {"message"}}
_INT_VALUES = {"ACCEPTED": {"points"}, "DONE": {"points", "balance"}}
_NUMBER_VALUES = {"ACCEPTED": {"weight_g"}}


class Screen:
    """Current screen plus change notification for the control page."""

    def __init__(self) -> None:
        self._screen = "IDLE"
        self._values: dict[str, Any] = {}
        self._version = 0
        self._condition = threading.Condition()

    @property
    def version(self) -> int:
        return self._version

    def get(self) -> tuple[str, dict[str, Any]]:
        with self._condition:
            return self._screen, dict(self._values)

    def wait_change(self, version: int, timeout_s: float) -> int:
        """Wait until the screen version changes; return the current version."""
        with self._condition:
            self._condition.wait_for(lambda: self._version != version, timeout_s)
            return self._version

    def show(self, screen: str, values: dict[str, Any] | None = None) -> None:
        if not isinstance(screen, str) or screen not in SCREENS:
            raise ValidationError("Unknown screen.")
        provided = {} if values is None else values
        if not isinstance(provided, dict):
            raise ValidationError("values must be an object.")

        required = REQUIRED_VALUES.get(screen, set())
        missing = required - provided.keys()
        if missing:
            raise ValidationError(f"Missing value(s): {', '.join(sorted(missing))}.")
        if not required and provided:
            raise ValidationError(f"Screen {screen} does not take values.")

        checked: dict[str, Any] = {}
        for key, value in provided.items():
            if key in _TEXT_VALUES.get(screen, set()) and (
                not isinstance(value, str) or not value.strip()
            ):
                raise ValidationError(f"{key} must be a non-empty string.")
            elif key in _INT_VALUES.get(screen, set()) and (
                isinstance(value, bool) or not isinstance(value, int)
            ):
                raise ValidationError(f"{key} must be an integer.")
            elif key in _NUMBER_VALUES.get(screen, set()) and (
                isinstance(value, bool) or not isinstance(value, Real)
            ):
                raise ValidationError(f"{key} must be a number.")
            checked[key] = value

        with self._condition:
            self._screen = screen
            self._values = checked
            self._version += 1
            self._condition.notify_all()
