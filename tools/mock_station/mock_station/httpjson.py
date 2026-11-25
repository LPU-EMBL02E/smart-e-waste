"""Small JSON helpers shared by the mock API and control page servers."""

from __future__ import annotations

import json
from http.server import BaseHTTPRequestHandler
from typing import Any

from .errors import ValidationError

MAX_BODY_BYTES = 65536


def send_json(handler: BaseHTTPRequestHandler, status: int, payload: Any) -> None:
    """Send a compact JSON response with a content length."""
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    handler.send_response(status)
    handler.send_header("Content-Type", "application/json; charset=utf-8")
    handler.send_header("Content-Length", str(len(body)))
    handler.end_headers()
    handler.wfile.write(body)


def send_empty(handler: BaseHTTPRequestHandler, status: int) -> None:
    """Send a response with no body, for non-contract paths and methods."""
    handler.send_response(status)
    handler.send_header("Content-Length", "0")
    handler.end_headers()


def read_json(handler: BaseHTTPRequestHandler) -> Any:
    """Read and parse a JSON request body, or None when there is no body."""
    try:
        length = int(handler.headers.get("Content-Length") or 0)
    except ValueError as error:
        raise ValidationError("Content-Length is invalid.") from error
    if length < 0 or length > MAX_BODY_BYTES:
        raise ValidationError("Request body is too large.")
    if length == 0:
        return None
    body = handler.rfile.read(length)
    try:
        return json.loads(body.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise ValidationError("Request body is not valid JSON.") from error
