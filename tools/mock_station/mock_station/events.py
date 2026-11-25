"""Bounded event queue for QR tokens and touchscreen touches.

API_Design.md section 11.4: at most 16 events, oldest dropped when full,
``take_all`` returns oldest first and empties the queue, and arming the scanner
clears queued QR tokens while keeping touches.
"""

from __future__ import annotations

from collections import deque
from threading import Lock

MAX_EVENTS = 16
MAX_TOKEN_LENGTH = 64


class EventQueue:
    """Thread-safe queue of station events."""

    def __init__(self) -> None:
        self._events: deque[dict[str, str]] = deque(maxlen=MAX_EVENTS)
        self._lock = Lock()

    def put_qr(self, token: str) -> bool:
        """Queue a scanned token. Returns False for unusable tokens."""
        if not isinstance(token, str) or not token or len(token) > MAX_TOKEN_LENGTH:
            return False
        with self._lock:
            self._events.append({"type": "qr", "token": token})
        return True

    def put_touch(self, action: str = "cancel") -> bool:
        """Queue a touchscreen action. Only ``cancel`` is defined."""
        if action != "cancel":
            return False
        with self._lock:
            self._events.append({"type": "touch", "action": "cancel"})
        return True

    def take_all(self) -> list[dict[str, str]]:
        """Return every queued event, oldest first, and empty the queue."""
        with self._lock:
            events = list(self._events)
            self._events.clear()
            return events

    def __len__(self) -> int:
        with self._lock:
            return len(self._events)

    def clear_tokens(self) -> None:
        """Remove queued QR tokens but keep touchscreen touches."""
        with self._lock:
            self._events = deque(
                (event for event in self._events if event.get("type") != "qr"),
                maxlen=MAX_EVENTS,
            )
