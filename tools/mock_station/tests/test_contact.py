"""Unit tests for controller-contact timeout. API_Design.md section 11.9."""

from __future__ import annotations

import time
import unittest

from mock_station.contact import ControllerContact


class ControllerContactTests(unittest.TestCase):
    def test_timeout_fires_once_and_touch_clears_offline(self) -> None:
        calls = []
        contact = ControllerContact(0.05, lambda: calls.append("timeout"))

        contact.touch()
        time.sleep(0.08)
        self.assertTrue(contact.poll())
        self.assertTrue(contact.is_offline())
        self.assertFalse(contact.poll())
        self.assertEqual(calls, ["timeout"])

        contact.touch()
        self.assertFalse(contact.is_offline())
        time.sleep(0.08)
        self.assertTrue(contact.poll())
        self.assertEqual(calls, ["timeout", "timeout"])

    def test_app_timeout_disarms_scanner_and_shows_offline(self) -> None:
        from tests.helpers import make_app, make_settings

        app = make_app(make_settings(controller_timeout_s=0.05))
        app.scanner.arm()
        app.contact.touch()
        time.sleep(0.08)

        self.assertTrue(app.contact.poll())
        self.assertFalse(app.scanner.armed)
        self.assertEqual(app.screen.get(), ("OFFLINE", {}))
        self.assertTrue(app.contact.is_offline())

        app.contact.touch()
        self.assertFalse(app.contact.is_offline())
        self.assertEqual(app.screen.get(), ("OFFLINE", {}))


if __name__ == "__main__":
    unittest.main()
