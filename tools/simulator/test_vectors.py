"""
The signing test vectors from API_Design.md §3.4.

Run this first, before anything is wired up:

    python3 tools/simulator/test_vectors.py

If it passes here and in the firmware and the server, a signature mismatch
during integration is a key or a clock problem, not a canonical-string problem.
That is the one bug that is genuinely miserable to find with real hardware in
the loop, so it is worth pinning down before the hardware exists.

Both expected signatures were reproduced independently while this file was
written, and both matched the document.
"""

from __future__ import annotations

import sys

from sign import sign

SECRET = "test-secret-do-not-use-0123456789abcdef"
TIMESTAMP = 1790956800

VECTOR_1 = {
    "name": "no body",
    "method": "GET",
    "path": "/api/v1/device/config",
    "body": b"",
    "expected": "30f42c3aca93595dedf2c7a61ad832036cf3de02cb9f8dce7f478b8e049b680f",
}

VECTOR_2 = {
    "name": "with a body",
    "method": "POST",
    "path": "/api/v1/device/sessions",
    "body": b'{"qr_token":"TESTTOKEN0001","fw":"1.0.3"}',
    "expected": "8c7cd80ead0a593505a15b215d85476e1a897caf2e71baf11c1eeff699659e7f",
}


def main() -> int:
    failures = 0

    # The document states the body is 41 bytes, with no spaces and no trailing
    # newline. Check that too: a stray newline is the easiest way to get this
    # wrong when copying the vector into firmware.
    if len(VECTOR_2["body"]) != 41:
        print(f"FAIL  vector 2 body is {len(VECTOR_2['body'])} bytes, expected 41")
        failures += 1

    for vector in (VECTOR_1, VECTOR_2):
        actual = sign(SECRET, TIMESTAMP, vector["method"], vector["path"], vector["body"])
        ok = actual == vector["expected"]
        print(f"{'PASS' if ok else 'FAIL'}  {vector['method']:4} {vector['path']}  ({vector['name']})")
        if not ok:
            print(f"      expected {vector['expected']}")
            print(f"      actual   {actual}")
            failures += 1

    print()
    print("All vectors match." if failures == 0 else f"{failures} failure(s).")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
