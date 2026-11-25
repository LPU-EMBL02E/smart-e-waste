"""Tests for component selection, hardware placeholders, and settings."""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from mock_station.components import build_components
from mock_station.settings import SettingsError, load

from tests.helpers import make_settings

EXAMPLE = Path(__file__).resolve().parents[1] / "settings.example.json"


class ComponentTests(unittest.TestCase):
    def test_default_components_are_simulated(self) -> None:
        components = build_components(make_settings())
        self.assertEqual(
            components.kinds,
            {"scanner": "simulated", "camera": "simulated", "model": "simulated"},
        )
        self.assertTrue(components.scanner.is_open())
        self.assertTrue(components.camera.is_open())
        self.assertTrue(components.model.is_loaded())

    def test_hardware_components_are_unavailable_placeholders(self) -> None:
        components = build_components(
            make_settings(
                components={
                    "scanner": {"type": "hardware"},
                    "camera": {"type": "hardware"},
                    "model": {"type": "hardware"},
                }
            )
        )
        self.assertEqual(components.kinds["scanner"], "hardware")
        self.assertFalse(components.scanner.is_open())
        self.assertFalse(components.camera.is_open())
        self.assertFalse(components.camera.has_reference())
        self.assertFalse(components.model.is_loaded())
        self.assertIn("TODO(team)", components.scanner.describe()["note"])

    def test_unknown_component_type_rejected(self) -> None:
        with self.assertRaises(SettingsError):
            build_components(make_settings(components={"scanner": {"type": "magic"}}))


class SettingsTests(unittest.TestCase):
    def test_example_settings_load(self) -> None:
        settings = load(EXAMPLE)
        self.assertEqual(settings.api_host, "0.0.0.0")
        self.assertEqual(settings.api_port, 8080)
        self.assertEqual(settings.page_port, 8081)
        self.assertEqual(settings.components["scanner"]["type"], "simulated")

    def test_missing_and_invalid_settings(self) -> None:
        with self.assertRaises(SettingsError):
            load("does-not-exist.json")
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "bad.json"
            path.write_text("{bad json", encoding="utf-8")
            with self.assertRaises(SettingsError):
                load(path)


if __name__ == "__main__":
    unittest.main()
