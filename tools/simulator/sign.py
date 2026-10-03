"""
Request signing for the device API. API_Design.md §3.2 and P4.

The canonical string is:

    timestamp + "\n" + METHOD + "\n" + path + "\n" + raw_body

    key       the bytes of the secret exactly as issued, not hex-decoded
    timestamp the same decimal string sent in X-Timestamp
    METHOD    upper case
    path      from /api/v1/device onward, no scheme, host or query string
    raw_body  the exact bytes sent; empty for a request with no body, so the
              signed string then ends with "\n"
    output    lower-case hex, 64 characters

The one rule that matters on both sides: sign the RAW body bytes. Re-encoding
JSON reorders keys and changes whitespace, and the signature stops matching.
Standard library only — no dependencies to install on the Pi or a dev machine.
"""

from __future__ import annotations

import hashlib
import hmac
import time

API_PREFIX = "/api/v1/device"


def canonical_string(timestamp: int | str, method: str, path: str, body: bytes = b"") -> bytes:
    """The exact bytes that get signed."""
    head = f"{timestamp}\n{method.upper()}\n{path}\n".encode()
    return head + body


def sign(secret: str, timestamp: int | str, method: str, path: str, body: bytes = b"") -> str:
    """Lower-case hex HMAC-SHA256 of the canonical string."""
    return hmac.new(
        secret.encode(),
        canonical_string(timestamp, method, path, body),
        hashlib.sha256,
    ).hexdigest()


def headers(
    device_code: str,
    secret: str,
    method: str,
    path: str,
    body: bytes = b"",
    timestamp: int | None = None,
    skew_seconds: int = 0,
) -> dict[str, str]:
    """
    The three signed headers, plus Content-Type when there is a body.

    `skew_seconds` deliberately moves the timestamp away from the real time, to
    test that the server answers CLOCK_SKEW outside its signature window.
    """
    ts = (timestamp if timestamp is not None else int(time.time())) + skew_seconds

    sent = {
        "X-Device-Code": device_code,
        "X-Timestamp": str(ts),
        "X-Signature": sign(secret, ts, method, path, body),
    }

    if body:
        sent["Content-Type"] = "application/json"

    return sent
