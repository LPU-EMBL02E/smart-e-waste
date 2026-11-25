"""Unit tests for screen validation. API_Design.md section 11.7."""

from __future__ import annotations

import unittest

from mock_station.errors import ValidationError
from mock_station.screen import SCREENS, Screen


class ScreenTests(unittest.TestCase):
    def test_idle_has_no_values(self) -> None:
        screen = Screen()
        screen.show("IDLE")
        self.assertEqual(screen.get(), ("IDLE", {}))

    def test_welcome_requires_name(self) -> None:
        screen = Screen()
        with self.assertRaises(ValidationError):
            screen.show("WELCOME", {})
        screen.show("WELCOME", {"name": "Student"})
        self.assertEqual(screen.get(), ("WELCOME", {"name": "Student"}))

    def test_accepted_requires_weight_and_points(self) -> None:
        screen = Screen()
        screen.show("ACCEPTED", {"weight_g": 12.5, "points": 5})
        self.assertEqual(screen.get(), ("ACCEPTED", {"weight_g": 12.5, "points": 5}))

    def test_done_requires_points_and_balance(self) -> None:
        screen = Screen()
        screen.show("DONE", {"points": 5, "balance": 20})
        self.assertEqual(screen.get(), ("DONE", {"points": 5, "balance": 20}))

    def test_problem_requires_message(self) -> None:
        screen = Screen()
        screen.show("PROBLEM", {"message": "Try again"})
        self.assertEqual(screen.get(), ("PROBLEM", {"message": "Try again"}))

    def test_unknown_screen_rejected(self) -> None:
        screen = Screen()
        with self.assertRaises(ValidationError):
            screen.show("NOPE")

    def test_values_rejected_for_screens_without_values(self) -> None:
        screen = Screen()
        with self.assertRaises(ValidationError):
            screen.show("IDLE", {"name": "x"})

    def test_bad_value_types_rejected(self) -> None:
        screen = Screen()
        with self.assertRaises(ValidationError):
            screen.show("ACCEPTED", {"weight_g": "heavy", "points": 5})
        with self.assertRaises(ValidationError):
            screen.show("DONE", {"points": True, "balance": 20})

    def test_wait_change_notifies_on_show(self) -> None:
        screen = Screen()
        version = screen.version
        screen.show("WEIGHING")
        self.assertNotEqual(screen.wait_change(version, 0.1), version)

    def test_all_documented_screens_accepted_shape(self) -> None:
        screen = Screen()
        values = {
            "IDLE": None,
            "WELCOME": {"name": "A"},
            "WEIGHING": None,
            "ACCEPTED": {"weight_g": 1.0, "points": 1},
            "DONE": {"points": 1, "balance": 1},
            "PENDING": None,
            "PROBLEM": {"message": "m"},
            "BIN_FULL": None,
            "OFFLINE": None,
        }
        for name in SCREENS:
            screen.show(name, values[name])
            self.assertEqual(screen.get()[0], name)
        self.assertIn(screen.get()[0], SCREENS)


if __name__ == "__main__":
    unittest.main()
