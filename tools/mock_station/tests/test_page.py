"""Tests for the localhost control page server."""

from __future__ import annotations

import unittest

from tests.helpers import make_app, request, start_page


class PageTests(unittest.TestCase):
    def start(self):
        app = make_app()
        server, thread, base = start_page(app)
        self.addCleanup(thread.join, timeout=2.0)
        self.addCleanup(server.server_close)
        self.addCleanup(server.shutdown)
        return app, base

    def test_index_and_state(self) -> None:
        app, base = self.start()

        status, _, payload = request(base, "GET", "/")
        self.assertEqual(status, 200)
        self.assertIn(b"Mock Bin Station Control", payload)
        self.assertIn(b"/control/command", payload)

        status, obj, _ = request(base, "GET", "/control/state")
        self.assertEqual(status, 200)
        self.assertEqual(obj["kinds"]["scanner"], "simulated")
        self.assertTrue(obj["scanner"]["available"])
        self.assertTrue(obj["camera"]["available"])
        self.assertTrue(obj["model"]["loaded"])
        self.assertEqual(obj["screen"], {"screen": "IDLE", "values": {}})
        self.assertFalse(obj["contact"]["offline"])
        self.assertEqual(obj["events_pending"], 0)

        app.screen.show("WEIGHING")
        status, obj, _ = request(base, "GET", "/control/state")
        self.assertEqual(obj["screen"]["screen"], "WEIGHING")

    def test_control_command_inject_and_state(self) -> None:
        app, base = self.start()

        status, obj, _ = request(
            base, "POST", "/control/command", {"inject_qr": "token"}
        )
        self.assertEqual(status, 200)
        self.assertTrue(obj["ok"])
        self.assertFalse(obj["result"]["queued"])

        app.scanner.arm()
        status, obj, _ = request(
            base, "POST", "/control/command", {"inject_qr": "token"}
        )
        self.assertTrue(obj["result"]["queued"])

        status, obj, _ = request(base, "GET", "/control/state")
        self.assertEqual(obj["events_pending"], 1)

        status, obj, _ = request(
            base, "POST", "/control/command", {"set_available": {"camera": False}}
        )
        self.assertTrue(obj["ok"])
        status, obj, _ = request(base, "GET", "/control/state")
        self.assertFalse(obj["camera"]["available"])

        status, obj, _ = request(
            base, "POST", "/control/command", {"set_model": {"result": "fail"}}
        )
        self.assertTrue(obj["ok"])
        status, obj, _ = request(base, "GET", "/control/state")
        self.assertLess(obj["model"]["probability"], app.settings.model_pass_threshold)

    def test_control_command_invalid(self) -> None:
        _, base = self.start()
        status, obj, _ = request(base, "POST", "/control/command", {"bogus": True})
        self.assertEqual(status, 422)
        self.assertFalse(obj["ok"])
        self.assertEqual(obj["error"]["code"], "VALIDATION_ERROR")

        status, _, _ = request(base, "GET", "/nope")
        self.assertEqual(status, 404)


if __name__ == "__main__":
    unittest.main()
