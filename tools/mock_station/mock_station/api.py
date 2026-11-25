"""Controller-facing HTTP API for the mock bin station.

Implements exactly the six endpoints in API_Design.md section 11. It is a test
double only: no database, no device secret, no calls to the web app, and no
decisions about deposits.
"""

from __future__ import annotations

import json
import urllib.parse
from dataclasses import dataclass
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

from .check import run_check
from .errors import CameraUnavailable, ScannerUnavailable, StationError, ValidationError
from .httpjson import read_json, send_empty, send_json


@dataclass
class App:
    settings: Any
    event_queue: Any
    screen: Any
    contact: Any
    scanner: Any
    camera: Any
    model: Any
    kinds: dict[str, str]


class StationHTTPServer(ThreadingHTTPServer):
    daemon_threads = True


ROUTES = {
    ("GET", "/station/v1/health"): "_health",
    ("PUT", "/station/v1/scanner"): "_scanner",
    ("GET", "/station/v1/events"): "_events",
    ("POST", "/station/v1/verify"): "_verify",
    ("POST", "/station/v1/reference"): "_reference",
    ("PUT", "/station/v1/screen"): "_screen",
}
KNOWN_PATHS = {path for _, path in ROUTES}


def make_server(app: App, host: str, port: int) -> StationHTTPServer:
    server = StationHTTPServer((host, port), Handler)
    server.app = app  # type: ignore[attr-defined]
    return server


class Handler(BaseHTTPRequestHandler):
    server_version = "MockBinStation/0.1"

    @property
    def app(self) -> App:
        return self.server.app  # type: ignore[attr-defined, return-value]

    def log_message(self, format: str, *args: Any) -> None:
        import os

        if os.environ.get("MOCK_STATION_LOG"):
            super().log_message(format, *args)

    def do_GET(self) -> None:
        self._dispatch("GET")

    def do_POST(self) -> None:
        self._dispatch("POST")

    def do_PUT(self) -> None:
        self._dispatch("PUT")

    def _dispatch(self, method: str) -> None:
        path = urllib.parse.urlsplit(self.path).path
        route = ROUTES.get((method, path))
        if route is None:
            if path in KNOWN_PATHS:
                self.app.contact.touch()
                self.send_response(405)
                self.send_header(
                    "Allow", ", ".join(sorted(m for m, p in ROUTES if p == path))
                )
                self.send_header("Content-Length", "0")
                self.end_headers()
            else:
                send_empty(self, 404)
            return

        self.app.contact.touch()
        try:
            getattr(self, route)()
        except StationError as error:
            send_json(
                self,
                error.http_status,
                {
                    "error": {
                        "code": error.code,
                        "message": error.message,
                        "retryable": error.retryable,
                    }
                },
            )

    def _health(self) -> None:
        send_json(
            self,
            200,
            {
                "scanner": self.app.scanner.is_open(),
                "camera": self.app.camera.is_open(),
                "reference": self.app.camera.has_reference(),
                "model": self.app.model.is_loaded(),
            },
        )

    def _scanner(self) -> None:
        body = read_json(self)
        if not isinstance(body, dict) or not isinstance(body.get("armed"), bool):
            raise ValidationError("scanner requires a JSON object with boolean armed.")
        armed = body["armed"]
        if armed:
            if not self.app.scanner.arm():
                raise ScannerUnavailable("Scanner did not arm.")
        else:
            self.app.scanner.disarm()
        send_json(self, 200, {"armed": armed})

    def _events(self) -> None:
        send_json(self, 200, {"events": self.app.event_queue.take_all()})

    def _verify(self) -> None:
        verdict = run_check(
            self.app.camera,
            self.app.model,
            pixel_threshold=self.app.settings.pixel_threshold,
            presence_threshold=self.app.settings.presence_threshold,
            model_pass_threshold=self.app.settings.model_pass_threshold,
        )
        send_json(self, 200, verdict)

    def _reference(self) -> None:
        stored = self.app.camera.take_reference()
        if not stored:
            raise CameraUnavailable("Reference was not stored.")
        send_json(self, 200, {"stored": True})

    def _screen(self) -> None:
        body = read_json(self)
        if not isinstance(body, dict) or "screen" not in body:
            raise ValidationError("screen requires a JSON object with screen.")
        self.app.screen.show(body["screen"], body.get("values"))
        send_json(self, 200, {"screen": body["screen"]})


def dumps(payload: Any) -> bytes:  # pragma: no cover - helper for tests/debug
    return json.dumps(payload, separators=(",", ":")).encode("utf-8")
