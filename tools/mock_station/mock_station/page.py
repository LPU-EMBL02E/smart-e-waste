"""Localhost control page and command endpoint for the mock station.

This is a tester control surface, not part of the controller contract. It listens
on ``page_host``/``page_port`` (localhost by default) and can inject scanner and
touch events and force component failure scenarios.
"""

from __future__ import annotations

import json
from dataclasses import dataclass
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any

from .control import apply_control_command
from .errors import StationError
from .httpjson import read_json, send_empty, send_json

INDEX_PATH = Path(__file__).resolve().parent / "page" / "index.html"


@dataclass
class PageApp:
    settings: Any
    event_queue: Any
    screen: Any
    contact: Any
    scanner: Any
    camera: Any
    model: Any
    kinds: dict[str, str]


class PageHTTPServer(ThreadingHTTPServer):
    daemon_threads = True


def make_server(app: PageApp, host: str, port: int) -> PageHTTPServer:
    server = PageHTTPServer((host, port), Handler)
    server.app = app  # type: ignore[attr-defined]
    return server


def _state(app: PageApp) -> dict[str, Any]:
    screen, values = app.screen.get()
    return {
        "kinds": app.kinds,
        "scanner": app.scanner.describe(),
        "camera": app.camera.describe(),
        "model": app.model.describe(),
        "screen": {"screen": screen, "values": values},
        "contact": {"offline": app.contact.is_offline()},
        "events_pending": len(app.event_queue),
    }


class Handler(BaseHTTPRequestHandler):
    server_version = "MockStationControl/0.1"

    @property
    def app(self) -> PageApp:
        return self.server.app  # type: ignore[attr-defined, return-value]

    def log_message(self, format: str, *args: Any) -> None:
        import os

        if os.environ.get("MOCK_STATION_LOG"):
            super().log_message(format, *args)

    def do_GET(self) -> None:
        if self.path in {"/", "/control"}:
            self._index()
        elif self.path == "/control/state":
            send_json(self, 200, _state(self.app))
        else:
            send_empty(self, 404)

    def do_POST(self) -> None:
        if self.path != "/control/command":
            send_empty(self, 404)
            return
        body = read_json(self)
        if not isinstance(body, dict):
            send_json(
                self,
                422,
                {
                    "ok": False,
                    "error": {
                        "code": "VALIDATION_ERROR",
                        "message": "Command must be a JSON object.",
                    },
                },
            )
            return
        try:
            result = apply_control_command(
                body,
                components=self.app,
                settings=self.app.settings,
                event_queue=self.app.event_queue,
            )
        except StationError as error:
            send_json(
                self,
                error.http_status,
                {
                    "ok": False,
                    "error": {
                        "code": error.code,
                        "message": error.message,
                        "retryable": error.retryable,
                    },
                },
            )
            return
        except Exception as error:  # noqa: BLE001 - control UI should show failures
            send_json(
                self,
                400,
                {
                    "ok": False,
                    "error": {"code": "VALIDATION_ERROR", "message": str(error)},
                },
            )
            return
        send_json(self, 200, {"ok": True, "result": result})

    def _index(self) -> None:
        if not INDEX_PATH.is_file():
            send_empty(self, 404)
            return
        body = INDEX_PATH.read_bytes()
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def dumps(payload: Any) -> bytes:  # pragma: no cover - helper for tests/debug
    return json.dumps(payload, separators=(",", ":")).encode("utf-8")
