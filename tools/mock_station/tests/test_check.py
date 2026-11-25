"""Unit tests for the two-step verify check. TRACK-E step E5."""

from __future__ import annotations

import unittest

from mock_station.check import presence_score, run_check
from mock_station.components import Frame, SimulatedCamera, SimulatedModel
from mock_station.errors import CameraUnavailable, ModelUnavailable, NoReference


class CheckTests(unittest.TestCase):
    def _camera(self, *, pixels: int = 256, reference: bool = True) -> SimulatedCamera:
        camera = SimulatedCamera(width=8, height=8, changed_pixels=pixels)
        if reference:
            camera.take_reference()
        return camera

    def test_presence_failure_returns_cam_diff_without_model_call(self) -> None:
        camera = self._camera(pixels=0)
        model = SimulatedModel(probability=0.99)

        verdict = run_check(
            camera,
            model,
            pixel_threshold=10,
            presence_threshold=0.01,
            model_pass_threshold=0.8,
        )

        self.assertEqual(verdict, {"method": "cam_diff", "passed": False, "score": 0.0})
        self.assertEqual(model.calls, 0)

    def test_presence_pass_runs_model_and_passes(self) -> None:
        camera = self._camera(pixels=8)
        model = SimulatedModel(probability=0.9)

        verdict = run_check(
            camera,
            model,
            pixel_threshold=10,
            presence_threshold=0.01,
            model_pass_threshold=0.8,
        )

        self.assertEqual(verdict["method"], "ewaste_model")
        self.assertTrue(verdict["passed"])
        self.assertEqual(verdict["score"], 0.9)
        self.assertEqual(model.calls, 1)

    def test_presence_pass_model_failure_returns_fail(self) -> None:
        camera = self._camera(pixels=8)
        model = SimulatedModel(probability=0.2)

        verdict = run_check(
            camera,
            model,
            pixel_threshold=10,
            presence_threshold=0.01,
            model_pass_threshold=0.8,
        )

        self.assertEqual(verdict["method"], "ewaste_model")
        self.assertFalse(verdict["passed"])
        self.assertEqual(verdict["score"], 0.2)

    def test_missing_reference_raises_no_reference(self) -> None:
        camera = self._camera(reference=False)
        model = SimulatedModel()

        with self.assertRaises(NoReference):
            run_check(
                camera,
                model,
                pixel_threshold=10,
                presence_threshold=0.01,
                model_pass_threshold=0.8,
            )

    def test_camera_unavailable_raises_camera_unavailable(self) -> None:
        camera = SimulatedCamera(available=False)
        camera.take_reference = lambda: True  # type: ignore[method-assign]
        model = SimulatedModel()

        with self.assertRaises(CameraUnavailable):
            run_check(
                camera,
                model,
                pixel_threshold=10,
                presence_threshold=0.01,
                model_pass_threshold=0.8,
            )

    def test_model_unavailable_raises_model_unavailable(self) -> None:
        camera = self._camera(pixels=8)
        model = SimulatedModel(available=False)

        with self.assertRaises(ModelUnavailable):
            run_check(
                camera,
                model,
                pixel_threshold=10,
                presence_threshold=0.01,
                model_pass_threshold=0.8,
            )

    def test_model_not_called_when_presence_fails(self) -> None:
        camera = self._camera(pixels=0)
        model = SimulatedModel(probability=1.0)

        run_check(
            camera,
            model,
            pixel_threshold=10,
            presence_threshold=0.01,
            model_pass_threshold=0.8,
        )

        self.assertEqual(model.calls, 0)

    def test_invalid_model_score_raises_model_unavailable(self) -> None:
        class BadModel:
            def probability(self, frame: Frame) -> float:
                return 1.5

        camera = self._camera(pixels=8)
        with self.assertRaises(ModelUnavailable):
            run_check(
                camera,
                BadModel(),
                pixel_threshold=10,
                presence_threshold=0.01,
                model_pass_threshold=0.8,
            )

    def test_presence_score_counts_changed_pixels(self) -> None:
        reference = tuple([0] * 8)
        image = (0, 0, 0, 0, 0, 0, 0, 255)
        self.assertAlmostEqual(presence_score(reference, image, 10), 0.125)


if __name__ == "__main__":
    unittest.main()
