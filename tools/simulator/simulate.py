#!/usr/bin/env python3
"""
Device simulator for the Smart E-Waste API.

System_Plan.md §10 (build order) asks for this FIRST, before the firmware
exists, so the web app and the firmware can be built in parallel and the awkward
cases — retries, expired sessions, a repeated `complete` — are tested without
needing a bin on the bench.

It speaks exactly what the NodeMCU speaks: the seven endpoints in
API_Design.md §5, signed per §3.2. Standard library only.

    # the happy path, end to end
    python3 simulate.py --url http://127.0.0.1:8000 \
        --device BIN-01 --secret <64-hex> \
        deposit --qr <token> --weight 184.6

    # the cases that are hard to produce with real hardware
    python3 simulate.py ... scenario replay-complete
    python3 simulate.py ... scenario actuator-failure
    python3 simulate.py ... scenario clock-skew
    python3 simulate.py ... scenario bad-signature
    python3 simulate.py ... scenario expired-session --timeout-wait 95

    # one telemetry reading
    python3 simulate.py ... telemetry --distance 412.5

Exit code is 0 when the run did what the scenario expected, 1 otherwise, so it
can be used in CI once the app runs.
"""

from __future__ import annotations

import argparse
import json
import sys
import time
import urllib.error
import urllib.request

from sign import API_PREFIX, headers

TIMEOUT_SECONDS = 10


class Client:
    """One bin's view of the server."""

    def __init__(
        self, base_url: str, device_code: str, secret: str, verbose: bool = True
    ):
        self.base_url = base_url.rstrip("/")
        self.device_code = device_code
        self.secret = secret
        self.verbose = verbose
        # The ESP32 has no clock: it fetches the server time once and counts
        # forward from it. The simulator models that same offset so a wrong
        # host clock shows up here the way it would on the bin.
        self.clock_offset = 0

    # ------------------------------------------------------------- transport

    def request(
        self,
        method: str,
        endpoint: str,
        payload: dict | None = None,
        *,
        skew_seconds: int = 0,
        corrupt_signature: bool = False,
        signed: bool = True,
    ) -> tuple[int, dict]:
        path = f"{API_PREFIX}{endpoint}"
        url = f"{self.base_url}{path}"

        # Serialize once and sign THESE bytes. Never re-encode: key order and
        # whitespace are part of what was signed (API_Design.md §3.2).
        body = (
            json.dumps(payload, separators=(",", ":")).encode()
            if payload is not None
            else b""
        )

        sent: dict[str, str] = {}
        if signed:
            sent = headers(
                self.device_code,
                self.secret,
                method,
                path,
                body,
                timestamp=int(time.time()) + self.clock_offset,
                skew_seconds=skew_seconds,
            )
            if corrupt_signature:
                sent["X-Signature"] = "0" * 64

        request = urllib.request.Request(
            url,
            data=body or None,
            method=method.upper(),
            headers=sent,
        )

        try:
            with urllib.request.urlopen(request, timeout=TIMEOUT_SECONDS) as response:
                status, raw = response.status, response.read()
        except urllib.error.HTTPError as error:
            status, raw = error.code, error.read()
        except urllib.error.URLError as error:
            # The firmware's network failure policy: fail closed before a
            # deposit, retry after one (API_Design.md §3.6).
            self.log(f"  !! unreachable: {error.reason}")
            return 0, {}

        try:
            parsed = json.loads(raw) if raw else {}
        except json.JSONDecodeError:
            # A reply the firmware cannot read is treated as no reply at all (P9).
            self.log(f"  !! unreadable reply ({status}): {raw[:120]!r}")
            return status, {}

        self.log(f"  {method.upper()} {endpoint} -> {status} {json.dumps(parsed)}")
        return status, parsed

    def log(self, message: str) -> None:
        if self.verbose:
            print(message)

    # ------------------------------------------------------------- endpoints

    def sync_clock(self) -> int:
        """GET /time, the one unsigned route, then count forward from it."""
        status, body = self.request("GET", "/time", signed=False)
        if status == 200 and "time" in body:
            self.clock_offset = int(body["time"]) - int(time.time())
            self.log(f"  clock offset {self.clock_offset:+d}s")
        return status

    def config(self) -> tuple[int, dict]:
        return self.request("GET", "/config")

    def telemetry(self, distance_mm: float) -> tuple[int, dict]:
        return self.request("POST", "/telemetry", {"distance_mm": distance_mm})

    def open_session(
        self, qr_token: str, firmware: str = "sim-1.0.0", **kwargs
    ) -> tuple[int, dict]:
        return self.request(
            "POST", "/sessions", {"qr_token": qr_token, "fw": firmware}, **kwargs
        )

    def deposit(
        self,
        session_id: str,
        weight_g: float,
        *,
        stable: bool = True,
        samples: int = 20,
        passed: bool = True,
        score: float = 0.87,
        **kwargs,
    ) -> tuple[int, dict]:
        return self.request(
            "POST",
            f"/sessions/{session_id}/deposit",
            {
                "weight_g": weight_g,
                "weight_stable": stable,
                "samples": samples,
                "verification": {
                    "method": "cam_diff",
                    "passed": passed,
                    "score": score,
                },
            },
            **kwargs,
        )

    def complete(
        self,
        session_id: str,
        *,
        actuator_ok: bool = True,
        cycle_ms: int = 3200,
        **kwargs,
    ):
        return self.request(
            "POST",
            f"/sessions/{session_id}/complete",
            {"actuator_ok": actuator_ok, "cycle_ms": cycle_ms},
            **kwargs,
        )

    def cancel(self, session_id: str, **kwargs) -> tuple[int, dict]:
        # No request body (P27).
        return self.request("POST", f"/sessions/{session_id}/cancel", {}, **kwargs)


# --------------------------------------------------------------- scenarios


def error_code(body: dict) -> str | None:
    return (body.get("error") or {}).get("code")


def run_deposit(
    client: Client, qr: str, weight: float, actuator_ok: bool = True
) -> bool:
    """The full sequence, in the order the bin performs it."""
    print("\n== deposit ==")
    client.sync_clock()
    client.config()

    status, session = client.open_session(qr)
    if status != 201:
        print(f"FAIL  session not opened: {error_code(session)}")
        return False

    session_id = session["session_id"]
    print(f"  session {session_id} for {session.get('user', {}).get('display_name')}")

    status, result = client.deposit(session_id, weight)
    if status != 200 or not result.get("accepted"):
        print(f"FAIL  deposit not accepted: {error_code(result)}")
        return False

    print(f"  accepted, preview {result.get('points_preview')} points")

    # Only now may the actuator move.
    status, done = client.complete(session_id, actuator_ok=actuator_ok)
    if status != 200:
        print(f"FAIL  complete returned {status}: {error_code(done)}")
        return False

    if actuator_ok:
        print(
            f"PASS  transaction {done.get('transaction_id')}, "
            f"{done.get('points_awarded')} points, balance {done.get('balance')}"
        )
        return done.get("transaction_id") is not None
    # An actuator failure is a 200 with no transaction and no points (P26).
    print(
        f"PASS  actuator failure handled: transaction {done.get('transaction_id')}, "
        f"{done.get('points_awarded')} points"
    )
    return done.get("transaction_id") is None and done.get("points_awarded") == 0


def scenario_replay_complete(client: Client, qr: str, weight: float) -> bool:
    """A repeated `complete` must credit once and return 200 both times (§8)."""
    print("\n== replay complete ==")
    client.sync_clock()
    status, session = client.open_session(qr)
    if status != 201:
        print(f"FAIL  session not opened: {error_code(session)}")
        return False
    session_id = session["session_id"]
    client.deposit(session_id, weight)

    first_status, first = client.complete(session_id)
    second_status, second = client.complete(session_id)

    same_transaction = first.get("transaction_id") == second.get("transaction_id")
    same_points = first.get("points_awarded") == second.get("points_awarded")
    ok = (
        first_status == 200
        and second_status == 200
        and same_transaction
        and same_points
    )

    print(
        f"{'PASS' if ok else 'FAIL'}  transaction {first.get('transaction_id')} "
        f"vs {second.get('transaction_id')}, points {first.get('points_awarded')} "
        f"vs {second.get('points_awarded')}"
    )
    print("      (balance may differ between the two replies; that is expected — P10)")
    return ok


def scenario_duplicate_session(client: Client, qr: str) -> bool:
    """A second open on the same bin cancels the first and opens a new one (§4.3)."""
    print("\n== second session on the same bin ==")
    client.sync_clock()
    _, first = client.open_session(qr)
    _, second = client.open_session(qr)

    if "session_id" not in first or "session_id" not in second:
        print("FAIL  one of the two sessions did not open")
        return False

    # The first session is now CANCELLED, so acting on it must be refused.
    _status, body = client.deposit(first["session_id"], 100.0)
    ok = (
        first["session_id"] != second["session_id"]
        and error_code(body) == "INVALID_SESSION_STATE"
    )
    print(f"{'PASS' if ok else 'FAIL'}  first session now {error_code(body)}")
    return ok


def scenario_expired_session(client: Client, qr: str, wait_seconds: int) -> bool:
    """An OPEN session past expires_at must answer SESSION_EXPIRED (§4.2, P12)."""
    print("\n== expired session ==")
    client.sync_clock()
    status, session = client.open_session(qr)
    if status != 201:
        print(f"FAIL  session not opened: {error_code(session)}")
        return False

    wait = wait_seconds or int(session.get("expires_in_s", 90)) + 5
    print(f"  waiting {wait}s for the session to expire...")
    time.sleep(wait)

    status, body = client.deposit(session["session_id"], 184.6)
    ok = status == 410 and error_code(body) == "SESSION_EXPIRED"
    print(f"{'PASS' if ok else 'FAIL'}  got {status} {error_code(body)}")
    return ok


def scenario_bad_signature(client: Client) -> bool:
    """A wrong signature must be 401 INVALID_SIGNATURE, and nothing else (§3.3)."""
    print("\n== bad signature ==")
    status, body = client.request("GET", "/config", corrupt_signature=True)
    ok = status == 401 and error_code(body) == "INVALID_SIGNATURE"
    print(f"{'PASS' if ok else 'FAIL'}  got {status} {error_code(body)}")
    return ok


def scenario_clock_skew(client: Client, skew: int) -> bool:
    """A timestamp outside the window must be 401 CLOCK_SKEW, retryable (§3.5)."""
    print(f"\n== clock skew ({skew:+d}s) ==")
    status, body = client.request("GET", "/config", skew_seconds=skew)
    code = error_code(body)
    retryable = (body.get("error") or {}).get("retryable")
    ok = status == 401 and code == "CLOCK_SKEW" and retryable is True
    print(f"{'PASS' if ok else 'FAIL'}  got {status} {code}, retryable={retryable}")
    return ok


def scenario_unsigned_time(client: Client) -> bool:
    """GET /time must answer without a signature; nothing else may (§5.1)."""
    print("\n== unsigned /time ==")
    status, body = client.request("GET", "/time", signed=False)
    time_ok = status == 200 and isinstance(body.get("time"), int)

    status2, _body2 = client.request("GET", "/config", signed=False)
    config_refused = status2 == 401

    ok = time_ok and config_refused
    print(f"{'PASS' if ok else 'FAIL'}  /time {status}, unsigned /config {status2}")
    return ok


SCENARIOS = {
    "replay-complete": "a repeated complete credits once",
    "duplicate-session": "a second open cancels the first",
    "expired-session": "an OPEN session past expires_at",
    "actuator-failure": "complete with actuator_ok false",
    "bad-signature": "a corrupted signature",
    "clock-skew": "a timestamp outside the window",
    "unsigned-time": "/time unsigned, /config refused",
}


def main() -> int:
    parser = argparse.ArgumentParser(
        description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter
    )
    parser.add_argument(
        "--url", required=True, help="scheme://host[:port] of the server"
    )
    parser.add_argument(
        "--device", required=True, help="device_code, sent as X-Device-Code"
    )
    parser.add_argument(
        "--secret", required=True, help="the bin's 64-hex signing secret"
    )
    parser.add_argument("--quiet", action="store_true", help="hide the request log")

    sub = parser.add_subparsers(dest="command", required=True)

    deposit = sub.add_parser("deposit", help="run one full deposit")
    deposit.add_argument("--qr", required=True, help="a student's QR token")
    deposit.add_argument("--weight", type=float, required=True, help="grams")

    telemetry = sub.add_parser("telemetry", help="send one distance reading")
    telemetry.add_argument("--distance", type=float, required=True, help="millimetres")

    sub.add_parser("config", help="fetch the bin config")

    scenario = sub.add_parser("scenario", help="run one of the awkward cases")
    scenario.add_argument("name", choices=sorted(SCENARIOS))
    scenario.add_argument(
        "--qr", help="a student's QR token (needed by most scenarios)"
    )
    scenario.add_argument("--weight", type=float, default=184.6)
    scenario.add_argument(
        "--timeout-wait", type=int, default=0, help="seconds to wait for expiry"
    )
    scenario.add_argument("--skew", type=int, default=600, help="clock skew in seconds")

    args = parser.parse_args()
    client = Client(args.url, args.device, args.secret, verbose=not args.quiet)

    if args.command == "deposit":
        return 0 if run_deposit(client, args.qr, args.weight) else 1

    if args.command == "telemetry":
        client.sync_clock()
        status, _ = client.telemetry(args.distance)
        return 0 if status == 201 else 1

    if args.command == "config":
        client.sync_clock()
        status, _ = client.config()
        return 0 if status == 200 else 1

    needs_qr = {
        "replay-complete",
        "duplicate-session",
        "expired-session",
        "actuator-failure",
    }
    if args.name in needs_qr and not args.qr:
        parser.error(f"scenario {args.name} needs --qr")

    if args.name == "replay-complete":
        ok = scenario_replay_complete(client, args.qr, args.weight)
    elif args.name == "duplicate-session":
        ok = scenario_duplicate_session(client, args.qr)
    elif args.name == "expired-session":
        ok = scenario_expired_session(client, args.qr, args.timeout_wait)
    elif args.name == "actuator-failure":
        ok = run_deposit(client, args.qr, args.weight, actuator_ok=False)
    elif args.name == "bad-signature":
        ok = scenario_bad_signature(client)
    elif args.name == "clock-skew":
        ok = scenario_clock_skew(client, args.skew)
    else:
        ok = scenario_unsigned_time(client)

    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
