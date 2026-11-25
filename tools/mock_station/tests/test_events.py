"""Unit tests for the bounded station event queue. API_Design.md section 11.4."""

from __future__ import annotations

import unittest

from mock_station.events import MAX_EVENTS, MAX_TOKEN_LENGTH, EventQueue


class EventQueueTests(unittest.TestCase):
    def test_take_all_returns_oldest_first_and_empties(self) -> None:
        queue = EventQueue()
        queue.put_qr("first")
        queue.put_touch("cancel")
        queue.put_qr("second")

        self.assertEqual(
            queue.take_all(),
            [
                {"type": "qr", "token": "first"},
                {"type": "touch", "action": "cancel"},
                {"type": "qr", "token": "second"},
            ],
        )
        self.assertEqual(queue.take_all(), [])

    def test_overflow_drops_oldest(self) -> None:
        queue = EventQueue()
        for index in range(MAX_EVENTS + 4):
            queue.put_qr(f"token-{index}")

        events = queue.take_all()
        self.assertEqual(len(events), MAX_EVENTS)
        self.assertEqual(events[0]["token"], "token-4")
        self.assertEqual(events[-1]["token"], f"token-{MAX_EVENTS + 3}")

    def test_clear_tokens_keeps_touches(self) -> None:
        queue = EventQueue()
        queue.put_qr("token")
        queue.put_touch("cancel")
        queue.clear_tokens()

        self.assertEqual(queue.take_all(), [{"type": "touch", "action": "cancel"}])

    def test_rejects_empty_and_overlong_tokens(self) -> None:
        queue = EventQueue()
        self.assertFalse(queue.put_qr(""))
        self.assertFalse(queue.put_qr("x" * (MAX_TOKEN_LENGTH + 1)))
        self.assertTrue(queue.put_qr("x" * MAX_TOKEN_LENGTH))
        self.assertEqual(len(queue.take_all()), 1)

    def test_touch_only_supports_cancel(self) -> None:
        queue = EventQueue()
        self.assertFalse(queue.put_touch("confirm"))
        self.assertTrue(queue.put_touch("cancel"))
        self.assertEqual(queue.take_all(), [{"type": "touch", "action": "cancel"}])


if __name__ == "__main__":
    unittest.main()
