"""JSON settings for the mock bin station.

The production station uses TOML (TRACK-E step E0). This mock lives under
``tools/`` and stays standard-library only, so it uses JSON instead: it must run
on any Python 3 without ``tomllib`` version concerns and without Pi-only packages.
"""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any


class SettingsError(Exception):
    """Raised when the settings file is missing or invalid."""


@dataclass(frozen=True)
class Settings:
    """Runtime settings for the mock station."""

    api_host: str = "127.0.0.1"
    api_port: int = 8080
    page_host: str = "127.0.0.1"
    page_port: int = 8081
    controller_timeout_s: float = 10.0
    control_file: str | None = None
    control_poll_s: float = 0.2
    pixel_threshold: int = 10
    presence_threshold: float = 0.01
    model_pass_threshold: float = 0.8
    components: dict[str, dict[str, Any]] = field(default_factory=dict)

    def component(self, name: str) -> dict[str, Any]:
        """Return the configuration object for one component."""
        return self.components.get(name, {})


def _int(value: Any, name: str, default: int, minimum: int, maximum: int) -> int:
    if value is None:
        return default
    if not isinstance(value, int) or isinstance(value, bool):
        raise SettingsError(f"{name} must be an integer")
    if not minimum <= value <= maximum:
        raise SettingsError(f"{name} must be between {minimum} and {maximum}")
    return value


def _float(
    value: Any, name: str, default: float, minimum: float, maximum: float
) -> float:
    if value is None:
        return default
    if not isinstance(value, (int, float)) or isinstance(value, bool):
        raise SettingsError(f"{name} must be a number")
    result = float(value)
    if not minimum <= result <= maximum:
        raise SettingsError(f"{name} must be between {minimum} and {maximum}")
    return result


def _str(value: Any, name: str, default: str) -> str:
    if value is None:
        return default
    if not isinstance(value, str) or not value:
        raise SettingsError(f"{name} must be a non-empty string")
    return value


def load(path: str | Path) -> Settings:
    """Load and validate a JSON settings file."""
    settings_path = Path(path)
    if not settings_path.is_file():
        raise SettingsError(f"settings file not found: {settings_path}")
    try:
        data = json.loads(settings_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as error:
        raise SettingsError(f"settings file is not valid JSON: {error}") from error
    if not isinstance(data, dict):
        raise SettingsError("settings file must contain a JSON object")

    components = data.get("components", {})
    if not isinstance(components, dict):
        raise SettingsError("components must be a JSON object")
    for name, config in components.items():
        if not isinstance(config, dict):
            raise SettingsError(f"components.{name} must be a JSON object")

    control_file = data.get("control_file")
    if control_file is not None and not isinstance(control_file, str):
        raise SettingsError("control_file must be a string or null")

    return Settings(
        api_host=_str(data.get("api_host"), "api_host", "127.0.0.1"),
        api_port=_int(data.get("api_port"), "api_port", 8080, 1, 65535),
        page_host=_str(data.get("page_host"), "page_host", "127.0.0.1"),
        page_port=_int(data.get("page_port"), "page_port", 8081, 1, 65535),
        controller_timeout_s=_float(
            data.get("controller_timeout_s"),
            "controller_timeout_s",
            10.0,
            0.1,
            3600.0,
        ),
        control_file=control_file,
        control_poll_s=_float(
            data.get("control_poll_s"), "control_poll_s", 0.2, 0.05, 60.0
        ),
        pixel_threshold=_int(
            data.get("pixel_threshold"), "pixel_threshold", 10, 0, 255
        ),
        presence_threshold=_float(
            data.get("presence_threshold"),
            "presence_threshold",
            0.01,
            0.0,
            1.0,
        ),
        model_pass_threshold=_float(
            data.get("model_pass_threshold"),
            "model_pass_threshold",
            0.8,
            0.0,
            1.0,
        ),
        components=components,
    )
