"""Tests for mock-only control commands and the JSONL control file."""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from mock_station.check import run_check
from mock_station.control import Control, apply_control_command
from mock_station.errors import CameraUnavailable, ValidationError

from tests.helpers import make_app


def apply(app, command):
    return apply_control_command(
        command,
        components=app.components,
        settings=app.settings,
        event_queue=app.event_queue,
    )


class ControlCommandTests(unittest.TestCase):
    def test_inject_qr_requires_armed_scanner(self) -> None:
        app = make_app()
        self.assertEqual(apply(app, {"inject_qr": "token"}), {"queued": False})
        app.scanner.arm()
        self.assertEqual(apply(app, {"inject_qr": "token"}), {"queued": True})
        self.assertEqual(app.event_queue.take_all(), [{"type": "qr", "token": "token"}])

    def test_inject_touch_cancel(self) -> None:
        app = make_app()
        self.assertEqual(apply(app, {"inject_touch": "cancel"}), {"queued": True})
        self.assertEqual(
            app.event_queue.take_all(), [{"type": "touch", "action": "cancel"}]
        )

    def test_set_available(self) -> None:
        app = make_app()
        result = apply(app, {"set_available": {"camera": False, "model": False}})
        self.assertFalse(app.camera.is_open())
        self.assertFalse(app.model.is_loaded())
        self.assertFalse(result["components"]["camera"]["available"])

    def test_set_camera_item_and_empty_change_verify(self) -> None:
        app = make_app()
        app.camera.take_reference()
        kwargs = {
            "pixel_threshold": app.settings.pixel_threshold,
            "presence_threshold": app.settings.presence_threshold,
            "model_pass_threshold": app.settings.model_pass_threshold,
        }

        apply(app, {"set_camera": {"empty": True}})
        verdict = run_check(app.camera, app.model, **kwargs)
        self.assertEqual(verdict["method"], "cam_diff")
        self.assertFalse(verdict["passed"])

        apply(app, {"set_camera": {"item": True}})
        verdict = run_check(app.camera, app.model, **kwargs)
        self.assertEqual(verdict["method"], "ewaste_model")
        self.assertTrue(verdict["passed"])

    def test_set_model_result_pass_and_fail(self) -> None:
        app = make_app()
        app.camera.take_reference()
        app.camera.set_changed_pixels(256)
        kwargs = {
            "pixel_threshold": app.settings.pixel_threshold,
            "presence_threshold": app.settings.presence_threshold,
            "model_pass_threshold": app.settings.model_pass_threshold,
        }

        apply(app, {"set_model": {"result": "fail"}})
        verdict = run_check(app.camera, app.model, **kwargs)
        self.assertFalse(verdict["passed"])

        apply(app, {"set_model": {"result": "pass"}})
        verdict = run_check(app.camera, app.model, **kwargs)
        self.assertTrue(verdict["passed"])

    def test_set_reference_capture_clear_fail_next(self) -> None:
        app = make_app()
        self.assertEqual(
            apply(app, {"set_reference": {"action": "capture"}}), {"stored": True}
        )
        self.assertTrue(app.camera.has_reference())

        apply(app, {"set_reference": {"action": "fail_next"}})
        with self.assertRaises(CameraUnavailable):
            apply(app, {"set_reference": {"action": "capture"}})
        self.assertTrue(app.camera.has_reference())

        self.assertEqual(
            apply(app, {"set_reference": {"action": "clear"}}), {"cleared": True}
        )
        self.assertFalse(app.camera.has_reference())

    def test_invalid_commands_raise_validation_error(self) -> None:
        app = make_app()
        for command in (
            {},
            {"unknown": True},
            {"inject_qr": ""},
            {"inject_touch": "confirm"},
            {"set_available": {"display": False}},
            {"set_model": {"result": "maybe"}},
            {"set_reference": {"action": "nope"}},
        ):
            with self.assertRaises(ValidationError, msg=command):
                apply(app, command)


class ControlFileTests(unittest.TestCase):
    def test_poll_once_applies_appended_jsonl(self) -> None:
        app = make_app()
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "control.jsonl"
            path.write_text('{"inject_touch":"cancel"}\n', encoding="utf-8")
            control = Control(
                path,
                0.01,
                lambda command: apply_control_command(
                    command,
                    components=app.components,
                    settings=app.settings,
                    event_queue=app.event_queue,
                ),
                process_existing=True,
            )
            control.poll_once()
            self.assertEqual(
                app.event_queue.take_all(), [{"type": "touch", "action": "cancel"}]
            )

            app.scanner.arm()
            with path.open("a", encoding="utf-8") as handle:
                handle.write('{"inject_qr":"token-1"}\n')
            control.poll_once()
            self.assertEqual(
                app.event_queue.take_all(), [{"type": "qr", "token": "token-1"}]
            )

    def test_bad_line_does_not_stop_file(self) -> None:
        app = make_app()
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "control.jsonl"
            path.write_text('not-json\n{"inject_touch":"cancel"}\n', encoding="utf-8")
            control = Control(
                path,
                0.01,
                lambda command: apply_control_command(
                    command,
                    components=app.components,
                    settings=app.settings,
                    event_queue=app.event_queue,
                ),
                process_existing=True,
            )
            control.poll_once()
            self.assertEqual(
                app.event_queue.take_all(), [{"type": "touch", "action": "cancel"}]
            )


if __name__ == "__main__":
    unittest.main()
