"""Run the mock bin station. Test double only; not the production station."""

from __future__ import annotations

import argparse
import sys
import threading
import time
from pathlib import Path
from types import SimpleNamespace
from typing import Any

from .api import make_server as make_api_server
from .components import build_components
from .contact import ControllerContact
from .control import Control, apply_control_command
from .page import make_server as make_page_server
from .screen import Screen
from .settings import SettingsError
from .settings import load as load_settings


def _on_timeout(components: Any, screen: Screen) -> None:
    try:
        components.scanner.disarm()
        screen.show("OFFLINE")
    except Exception as error:  # noqa: BLE001 - timeout path must not crash the mock
        print(f"mock-station timeout handler: {error}", file=sys.stderr)


def run(settings_path: str) -> int:
    settings = load_settings(settings_path)
    components = build_components(settings)
    screen = Screen()
    contact = ControllerContact(
        settings.controller_timeout_s,
        lambda: _on_timeout(components, screen),
    )
    app = SimpleNamespace(
        settings=settings,
        event_queue=components.event_queue,
        screen=screen,
        contact=contact,
        scanner=components.scanner,
        camera=components.camera,
        model=components.model,
        kinds=components.kinds,
    )

    api_server = make_api_server(app, settings.api_host, settings.api_port)
    page_server = make_page_server(app, settings.page_host, settings.page_port)

    control: Control | None = None
    if settings.control_file:
        control_path = Path(settings.control_file)
        if not control_path.is_absolute():
            control_path = Path(settings_path).resolve().parent / control_path
        control = Control(
            control_path,
            settings.control_poll_s,
            lambda command: apply_control_command(
                command,
                components=components,
                settings=settings,
                event_queue=components.event_queue,
            ),
        )
        control.start()

    stop = threading.Event()

    def serve(server: Any) -> None:
        server.serve_forever()

    api_thread = threading.Thread(target=serve, args=(api_server,), daemon=True)
    page_thread = threading.Thread(target=serve, args=(page_server,), daemon=True)
    api_thread.start()
    page_thread.start()

    poll_s = min(1.0, max(0.1, settings.controller_timeout_s / 4))

    def contact_loop() -> None:
        while not stop.wait(poll_s):
            contact.poll()

    contact_thread = threading.Thread(target=contact_loop, daemon=True)
    contact_thread.start()

    print(
        f"mock-station API: http://{settings.api_host}:{settings.api_port}/station/v1"
    )
    print(f"mock-station control: http://{settings.page_host}:{settings.page_port}/")
    for name, kind in components.kinds.items():
        print(f"mock-station component {name}: {kind}")
    if control is not None:
        print(f"mock-station control file: {control_path}")
    print("mock-station running; press Ctrl+C to stop")

    try:
        while True:
            time.sleep(0.5)
    except KeyboardInterrupt:
        stop.set()
    finally:
        api_server.shutdown()
        page_server.shutdown()
        api_server.server_close()
        page_server.server_close()
        if control is not None:
            control.stop()
        api_thread.join(timeout=2.0)
        page_thread.join(timeout=2.0)
        contact_thread.join(timeout=2.0)
        print("mock-station stopped")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Mock bin station for integration testing"
    )
    parser.add_argument(
        "--settings",
        default="settings.json",
        help="Path to the JSON settings file (default: settings.json)",
    )
    args = parser.parse_args(argv)
    try:
        return run(args.settings)
    except SettingsError as error:
        print(f"mock-station settings error: {error}", file=sys.stderr)
        return 2
    except OSError as error:
        print(f"mock-station could not start: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
