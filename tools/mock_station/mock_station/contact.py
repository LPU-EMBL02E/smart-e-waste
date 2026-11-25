"""Controller-contact timeout for the mock station. API_Design.md section 11.9.

Every controller call resets the contact. When no call arrives for
``controller_timeout_s``, the station disarms the scanner and shows ``OFFLINE``
on its own. The next controller call ends the offline state; the controller then
sends the screen it wants, so this class does not restore the old screen.
"""

from __future__ import annotations

import time
from collections.abc import Callable
from threading import Lock


class ControllerContact:
    """Tracks when the controller last called and fires one timeout action."""

    def __init__(self, timeout_s: float, on_timeout: Callable[[], None]) -> None:
        self._timeout_s = timeout_s
        self._on_timeout = on_timeout
        self._last_contact = time.monotonic()
        self._offline = False
        self._lock = Lock()

    def touch(self) -> None:
        """Record a controller call and end any active offline state."""
        with self._lock:
            self._last_contact = time.monotonic()
            self._offline = False

    def is_offline(self) -> bool:
        """True while the station is showing OFFLINE by itself."""
        with self._lock:
            return self._offline

    def poll(self) -> bool:
        """Fire the timeout action once when contact has expired."""
        expired = False
        with self._lock:
            if (
                not self._offline
                and time.monotonic() - self._last_contact >= self._timeout_s
            ):
                self._offline = True
                expired = True
        if expired:
            self._on_timeout()
        return expired
