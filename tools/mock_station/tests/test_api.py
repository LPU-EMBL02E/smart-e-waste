"""HTTP tests for the six station endpoints. API_Design.md section 11."""

from __future__ import annotations

import unittest

from tests.helpers import make_app, make_settings, request, start_api


class ApiTestCase(unittest.TestCase):
    def start(self, app=None):
        app = app or make_app()
        server, thread, base = start_api(app)
        self.addCleanup(thread.join, timeout=2.0)
        self.addCleanup(server.server_close)
        self.addCleanup(server.shutdown)
        return app, base

    def assert_error(self, status: int, obj, code: str, http_status: int) -> None:
        self.assertEqual(status, http_status)
        self.assertIsNotNone(obj)
        self.assertEqual(obj["error"]["code"], code)
        self.assertFalse(obj["error"]["retryable"])


class HealthTests(ApiTestCase):
    def test_health_reports_simulated_components(self) -> None:
        _, base = self.start()
        status, obj, _ = request(base, "GET", "/station/v1/health")

        self.assertEqual(status, 200)
        self.assertEqual(
            obj,
            {"scanner": True, "camera": True, "reference": False, "model": True},
        )

    def test_health_after_reference(self) -> None:
        _, base = self.start()
        request(base, "POST", "/station/v1/reference")
        status, obj, _ = request(base, "GET", "/station/v1/health")

        self.assertEqual(status, 200)
        self.assertTrue(obj["reference"])


class ScannerTests(ApiTestCase):
    def test_arm_clears_qr_tokens_and_keeps_touch(self) -> None:
        app, base = self.start()
        app.event_queue.put_qr("old-token")
        app.event_queue.put_touch("cancel")

        status, obj, _ = request(base, "PUT", "/station/v1/scanner", {"armed": True})
        self.assertEqual(status, 200)
        self.assertEqual(obj, {"armed": True})
        self.assertTrue(app.scanner.armed)

        status, obj, _ = request(base, "GET", "/station/v1/events")
        self.assertEqual(status, 200)
        self.assertEqual(obj["events"], [{"type": "touch", "action": "cancel"}])

        status, obj, _ = request(base, "PUT", "/station/v1/scanner", {"armed": False})
        self.assertEqual(status, 200)
        self.assertEqual(obj, {"armed": False})
        self.assertFalse(app.scanner.armed)

    def test_arm_without_scanner_returns_503(self) -> None:
        app = make_app(
            make_settings(
                components={"scanner": {"type": "simulated", "available": False}}
            )
        )
        _, base = self.start(app)

        status, obj, _ = request(base, "PUT", "/station/v1/scanner", {"armed": True})
        self.assert_error(status, obj, "SCANNER_UNAVAILABLE", 503)

    def test_scanner_rejects_non_boolean_armed(self) -> None:
        _, base = self.start()
        for body in ({}, {"armed": "yes"}, {"armed": 1}):
            status, obj, _ = request(base, "PUT", "/station/v1/scanner", body)
            self.assert_error(status, obj, "VALIDATION_ERROR", 422)


class EventTests(ApiTestCase):
    def test_qr_and_touch_events_oldest_first(self) -> None:
        app, base = self.start()
        app.scanner.arm()
        app.scanner.inject_token("token-1")
        app.event_queue.put_touch("cancel")
        app.scanner.inject_token("token-2")

        status, obj, _ = request(base, "GET", "/station/v1/events")
        self.assertEqual(status, 200)
        self.assertEqual(
            obj["events"],
            [
                {"type": "qr", "token": "token-1"},
                {"type": "touch", "action": "cancel"},
                {"type": "qr", "token": "token-2"},
            ],
        )

        status, obj, _ = request(base, "GET", "/station/v1/events")
        self.assertEqual(obj["events"], [])

    def test_event_queue_overflow_drops_oldest(self) -> None:
        app, base = self.start()
        for index in range(20):
            app.event_queue.put_qr(f"token-{index}")

        status, obj, _ = request(base, "GET", "/station/v1/events")
        self.assertEqual(status, 200)
        self.assertEqual(len(obj["events"]), 16)
        self.assertEqual(obj["events"][0]["token"], "token-4")
        self.assertEqual(obj["events"][-1]["token"], "token-19")


class VerifyTests(ApiTestCase):
    def test_verify_passes_after_reference(self) -> None:
        _, base = self.start()
        request(base, "POST", "/station/v1/reference")

        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assertEqual(status, 200)
        self.assertEqual(obj["method"], "ewaste_model")
        self.assertTrue(obj["passed"])
        self.assertEqual(obj["score"], 0.93)

    def test_verify_without_reference_returns_409(self) -> None:
        _, base = self.start()
        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assert_error(status, obj, "NO_REFERENCE", 409)

    def test_verify_empty_flap_returns_cam_diff_fail(self) -> None:
        app = make_app()
        app.camera.set_changed_pixels(0)
        _, base = self.start(app)
        request(base, "POST", "/station/v1/reference")

        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assertEqual(status, 200)
        self.assertEqual(obj, {"method": "cam_diff", "passed": False, "score": 0.0})
        self.assertEqual(app.model.calls, 0)

    def test_verify_model_failure_returns_fail(self) -> None:
        app = make_app(
            make_settings(
                components={"model": {"type": "simulated", "probability": 0.2}}
            )
        )
        _, base = self.start(app)
        request(base, "POST", "/station/v1/reference")

        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assertEqual(status, 200)
        self.assertEqual(obj["method"], "ewaste_model")
        self.assertFalse(obj["passed"])
        self.assertEqual(obj["score"], 0.2)

    def test_verify_camera_unavailable_returns_503(self) -> None:
        app = make_app(
            make_settings(
                components={"camera": {"type": "simulated", "available": False}}
            )
        )
        _, base = self.start(app)

        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assert_error(status, obj, "CAMERA_UNAVAILABLE", 503)

    def test_verify_model_unavailable_returns_503(self) -> None:
        app = make_app(
            make_settings(
                components={"model": {"type": "simulated", "available": False}}
            )
        )
        _, base = self.start(app)
        request(base, "POST", "/station/v1/reference")

        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assert_error(status, obj, "MODEL_UNAVAILABLE", 503)


class ReferenceTests(ApiTestCase):
    def test_reference_success_and_failure_keeps_old(self) -> None:
        app, base = self.start()
        status, obj, _ = request(base, "POST", "/station/v1/reference")
        self.assertEqual(status, 200)
        self.assertEqual(obj, {"stored": True})

        app.camera.set_reference_failures(1)
        status, obj, _ = request(base, "POST", "/station/v1/reference")
        self.assert_error(status, obj, "CAMERA_UNAVAILABLE", 503)

        status, obj, _ = request(base, "GET", "/station/v1/health")
        self.assertTrue(obj["reference"])

        status, obj, _ = request(base, "POST", "/station/v1/verify")
        self.assertEqual(status, 200)
        self.assertTrue(obj["passed"])

    def test_reference_camera_unavailable_returns_503(self) -> None:
        app = make_app(
            make_settings(
                components={"camera": {"type": "simulated", "available": False}}
            )
        )
        _, base = self.start(app)

        status, obj, _ = request(base, "POST", "/station/v1/reference")
        self.assert_error(status, obj, "CAMERA_UNAVAILABLE", 503)


class ScreenTests(ApiTestCase):
    def test_screen_valid_and_invalid(self) -> None:
        app, base = self.start()

        status, obj, _ = request(
            base,
            "PUT",
            "/station/v1/screen",
            {"screen": "WELCOME", "values": {"name": "A"}},
        )
        self.assertEqual(status, 200)
        self.assertEqual(obj, {"screen": "WELCOME"})
        self.assertEqual(app.screen.get(), ("WELCOME", {"name": "A"}))

        for body in (
            {},
            {"screen": "NOPE"},
            {"screen": "WELCOME"},
            {"screen": "IDLE", "values": {"x": 1}},
        ):
            status, obj, _ = request(base, "PUT", "/station/v1/screen", body)
            self.assert_error(status, obj, "VALIDATION_ERROR", 422)


class RequestShapeTests(ApiTestCase):
    def test_unknown_path_404_and_wrong_method_405(self) -> None:
        _, base = self.start()

        status, _, payload = request(base, "GET", "/station/v1/nope")
        self.assertEqual(status, 404)
        self.assertEqual(payload, b"")

        for method, path in (
            ("GET", "/station/v1/verify"),
            ("POST", "/station/v1/events"),
            ("POST", "/station/v1/health"),
            ("PUT", "/station/v1/events"),
        ):
            status, _, payload = request(base, method, path)
            self.assertEqual(status, 405)
            self.assertEqual(payload, b"")

    def test_malformed_json_returns_422(self) -> None:
        _, base = self.start()
        status, obj, _ = request(base, "PUT", "/station/v1/scanner", raw_body=b"{")
        self.assert_error(status, obj, "VALIDATION_ERROR", 422)


if __name__ == "__main__":
    unittest.main()
