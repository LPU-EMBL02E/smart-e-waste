"""Shared helpers for mock station tests."""

from __future__ import annotations

import http.client
import json
import threading
from types import SimpleNamespace
from typing import Any
from urllib.parse import urlsplit

from mock_station.api import make_server as make_api_server
from mock_station.components import build_components
from mock_station.contact import ControllerContact
from mock_station.page import make_server as make_page_server
from mock_station.screen import Screen
from mock_station.settings import Settings


def make_settings(**overrides: Any) -> Settings:
    """Build settings with all-simulated components by default."""
    return Settings(**overrides)


def make_app(settings: Settings | None = None) -> SimpleNamespace:
    """Build the app object the API and page servers expect."""
    settings = settings or make_settings()
    components = build_components(settings)
    screen = Screen()

    def on_timeout() -> None:
        components.scanner.disarm()
        screen.show("OFFLINE")

    contact = ControllerContact(settings.controller_timeout_s, on_timeout)
    return SimpleNamespace(
        settings=settings,
        event_queue=components.event_queue,
        screen=screen,
        contact=contact,
        scanner=components.scanner,
        camera=components.camera,
        model=components.model,
        kinds=components.kinds,
        components=components,
    )


def start_server(
    app: Any, server_factory: Any, host: str = "127.0.0.1"
) -> tuple[Any, threading.Thread, str]:
    """Start an HTTP server on an ephemeral port and return its base URL."""
    server = server_factory(app, host, 0)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    port = server.server_address[1]
    return server, thread, f"http://{host}:{port}"


def start_api(app: Any, host: str = "127.0.0.1") -> tuple[Any, threading.Thread, str]:
    """Start the station API server on an ephemeral port."""
    return start_server(app, make_api_server, host)


def start_page(app: Any, host: str = "127.0.0.1") -> tuple[Any, threading.Thread, str]:
    """Start the control page server on an ephemeral port."""
    return start_server(app, make_page_server, host)


def request(
    base_url: str,
    method: str,
    path: str,
    body: Any = None,
    *,
    raw_body: bytes | None = None,
) -> tuple[int, Any, bytes]:
    """Send one HTTP request and return status, parsed JSON or None, and raw body."""
    parts = urlsplit(base_url)
    conn = http.client.HTTPConnection(parts.hostname, parts.port, timeout=5)
    data = raw_body
    headers: dict[str, str] = {}
    if body is not None:
        data = json.dumps(body, separators=(",", ":")).encode("utf-8")
    if data is not None:
        headers["Content-Type"] = "application/json"
    conn.request(method, path, body=data, headers=headers)
    response = conn.getresponse()
    payload = response.read()
    status = response.status
    conn.close()
    try:
        parsed = json.loads(payload.decode("utf-8")) if payload else None
    except (UnicodeDecodeError, json.JSONDecodeError):
        parsed = None
    return status, parsed, payload
