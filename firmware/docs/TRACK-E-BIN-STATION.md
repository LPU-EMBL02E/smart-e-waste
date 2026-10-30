# Track E — Bin station

Build spec for the bin station in `firmware/station/`: the Python program on the
Pi that runs the QR scanner, the camera check and the touchscreen, and answers
the controller over a small JSON API. It replaces the old `firmware/camera/` and
`firmware/display/` programs.

The rules come from `docs/`, which wins over this sheet if the two ever disagree:

- `System_Plan.md` §1 (what the bin station is), §3 (its packages), §5.5 (fail
  closed), §7 step 10 (setup), §8.3 (the bin station)
- `API_Design.md` §3.5 (error shape), §11 (the whole bin station API), P44, P45
- Coding rules: [`firmware/AGENTS.md`](../AGENTS.md), "Bin station Python"

**What it never does** (`System_Plan.md` §8.3): it has no database, holds no
device secret, never calls the web app and makes no decision about a deposit.
The controller decides; the bin station reports what it sees and shows what it
is told.

**Logic first, hardware later.** Steps E0–E3 run on any laptop with fakes in the
tests. Only E4–E6 need the Pi, the camera and the scanner.

---

## Layout

```
firmware/station/
  README.md                  how to run it on a laptop and on the Pi
  settings.example.toml      every setting, with bench values marked TODO(team)
  ewaste-station.service     systemd unit for the bin station
  ewaste-kiosk.service       systemd unit for Chromium under cage
  bin_station/
    __main__.py              load settings, build the parts, start the servers
    settings.py              Settings: reads the TOML file (tomllib)
    events.py                EventQueue
    contact.py               ControllerContact: the controller timeout
    screen.py                Screen: current screen, validation, page updates
    camera.py                Camera, and the pure score function
    scanner.py               Scanner
    api.py                   the controller API (§11) and the error shape
    page.py                  the kiosk page server (localhost only)
    page/index.html          the kiosk page, with its one small script
  tests/                     unittest, no hardware
```

Each part is one class with a small set of public methods, built once in
`__main__.py` and passed to the parts that need it. No base classes and no
interfaces: each part has one implementation. Tests pass a small fake object
where a part would touch hardware.

---

## Step E0 — Skeleton and checks

1. The layout above, with `python3 -m bin_station --settings <file>` as the
   entry point. Python 3.13 (Trixie's), standard library plus the three `apt`
   packages only.
2. **`Settings`** reads a TOML file with `tomllib` and fails at start with a
   clear message for a missing or invalid value. Keys:

   | Key | Holds |
   |---|---|
   | `api_host`, `api_port` | Where the controller API listens: the Pi's address on the bin's network (P45) |
   | `page_port` | The kiosk page's port, on `127.0.0.1` only |
   | `scanner_device` | The GM865's serial device, e.g. `/dev/ttyACM0` |
   | `scanner_timeout_ms` | Serial read and write timeout |
   | `lens_position` | Fixed focus (`System_Plan.md` §8.3). `TODO(team)`, set on the bench |
   | `image_width`, `image_height` | The small capture size for the check |
   | `pixel_threshold` | Brightness difference that counts a pixel as changed. `TODO(team)` |
   | `pass_threshold` | Score at or above which the check passes. `TODO(team)` |
   | `controller_timeout_s` | §11.9. Longer than the controller's event poll interval. `TODO(team)` |

3. **CI is already set up.** The `ruff` job in `.github/workflows/tools.yml`
   formats and lints `firmware/station/` as soon as the folder exists, and runs
   `python3 -m unittest discover -s firmware/station/tests` once `tests/` has a
   file, on Python 3.13 with numpy 2.2.4, as on Trixie. Nothing to add here.

Tests (`tests/test_settings.py`):
- `test_it_loads_the_example_settings`
- `test_it_refuses_a_missing_key`

---

## Step E1 — Events and the controller timeout (no hardware)

**`EventQueue`** (§11.4):
- `put_qr(token)`, `put_touch("cancel")`; `take_all()` returns every event,
  oldest first, and empties the queue.
- At most 16 events; a full queue drops the oldest.
- `clear_tokens()` removes queued `qr` events. Arming the scanner calls it, so a
  scan from before the bin was ready can't open a session (§11.3).
- Thread-safe: the scanner thread and the page server put events while the API
  thread takes them.

**`ControllerContact`** (§11.9):
- `touch()` on every controller call.
- A background check: once no call has come for `controller_timeout_s`, disarm
  the scanner and show `OFFLINE`. The next call ends it; the controller then
  sends the screen it wants.

Tests (`tests/test_events.py`, `tests/test_contact.py`):
- `test_it_returns_events_oldest_first_and_empties_the_queue`
- `test_it_drops_the_oldest_event_when_full`
- `test_it_clears_queued_tokens_but_keeps_touches`
- `test_it_disarms_and_shows_offline_after_the_timeout`
- `test_it_ends_offline_on_the_next_call`

---

## Step E2 — The controller API (no hardware)

`api.py`: a `ThreadingHTTPServer` on `api_host:api_port`, serving exactly the six
endpoints in §11, under `/station/v1`. Each request calls
`ControllerContact.touch()` first.

| Endpoint | Does |
|---|---|
| `GET /health` | `scanner`, `camera`, `reference` as booleans (§11.2) |
| `PUT /scanner` | Body `{ "armed": bool }`. Arming clears queued tokens; arming with no scanner is 503 `SCANNER_UNAVAILABLE` (§11.3) |
| `GET /events` | `EventQueue.take_all()` (§11.4) |
| `POST /verify` | `Camera.check()`: `{ "method": "cam_diff", "passed", "score" }`, score rounded to 4 places; 409 `NO_REFERENCE`, 503 `CAMERA_UNAVAILABLE` (§11.5) |
| `POST /reference` | `Camera.take_reference()`: `{ "stored": true }`; 503 `CAMERA_UNAVAILABLE` keeps the old reference (§11.6) |
| `PUT /screen` | `Screen.show()`: `{ "screen" }`; 422 `VALIDATION_ERROR` for an unknown screen or a missing value (§11.7) |

- Every error uses the shape in `API_Design.md` §3.5, with only the codes in
  §11.8. A body that isn't valid JSON is 422 `VALIDATION_ERROR`.
- Replies are JSON in UTF-8. Nothing is logged that a student typed or scanned
  beyond what debugging needs; never log a full QR token.

Tests (`tests/test_api.py`, a real server on a free port with fake scanner and
camera objects):
- `test_it_reports_health`
- `test_it_arms_and_clears_old_tokens`
- `test_it_refuses_to_arm_without_a_scanner`
- `test_it_returns_and_empties_events`
- `test_it_returns_the_check_in_the_documented_shape`
- `test_it_answers_no_reference_with_409`
- `test_it_answers_a_camera_failure_with_503_and_never_a_pass`
- `test_it_refuses_an_unknown_screen_and_a_missing_value`
- `test_it_answers_invalid_json_with_a_validation_error`
- `test_it_resets_the_controller_timeout_on_every_call`

---

## Step E3 — The screen and the kiosk page (no hardware)

**`Screen`** holds the current screen and its values, validates them against
the table in §11.7, and pushes each change to the page at once.

**`page.py`**: a second small server on `127.0.0.1:page_port`, never on the
bin's network. It serves:
- the page, `page/index.html`;
- a server-sent events stream of screen changes, sending the current screen
  first so a reloaded page is right at once;
- a `POST` for the Cancel button, which puts a `touch` event in the queue.

These three paths are internal to the bin station; the controller never calls
them, and they are not part of §11.

**The page**: one HTML file with one small inline script (`EventSource` and a
`fetch` for Cancel), no library and no build step. Every value is written with
`textContent`, never `innerHTML`, because `name` and `message` come from
outside. Screens, per `System_Plan.md` §8.2: a Cancel button only. Plain layout
for now; the visual design comes later.

Tests (`tests/test_screen.py`, `tests/test_page.py`):
- `test_it_accepts_each_documented_screen_with_its_values`
- `test_it_sends_the_current_screen_first_on_a_new_stream`
- `test_it_queues_a_cancel_touch`
- `test_it_serves_the_page_on_localhost_only`

---

## Step E4 — The camera (Pi and IMX519)

**The score**, a pure function in `camera.py`, tested without a camera:
`score(reference, image, pixel_threshold) -> float`, the share of pixels whose
grayscale brightness differs by more than the threshold, from 0.0 to 1.0
(`System_Plan.md` §8.3). numpy, on small grayscale arrays.

**`Camera`**, the only code that imports `picamera2`, imported inside the
class so the rest runs without it:
- `open()` at start; `is_open()` for `health`.
- `take_reference()`: switch off autofocus, set `lens_position`, let exposure
  and white balance settle once, then **fix** them; capture small and grayscale;
  keep the image and the fixed controls in memory. On a failure, raise and keep
  the old reference.
- `check()`: capture with the same fixed controls, score it against the
  reference, pass at or above `pass_threshold`. No reference raises
  `NoReference`; a failed capture raises `CameraUnavailable`, never a pass.
- The reference lives in memory only. A restart loses it, and `health` reports
  `reference: false` until the controller takes a new one (§8.3).

The LED strip is the controller's job (§11.5); the bin station never switches
it.

Tests (`tests/test_score.py`):
- `test_it_scores_identical_images_as_zero`
- `test_it_scores_the_share_of_changed_pixels`
- `test_it_ignores_changes_under_the_pixel_threshold`

On the Pi, once: take a reference of the empty platform, then check it empty
and with a phone, a charger and a sheet of paper on it. Record the scores in the
PR; the team sets both thresholds from them.

---

## Step E5 — The scanner (Pi and GM865)

**`Scanner`**, the only code that opens the serial port (`pyserial`):
- `open()` at start; `is_open()` for `health`. A failed open leaves it closed
  and `health` says so.
- `arm()` / `disarm()`. While armed, a reader thread triggers a scan in the
  GM865's command-triggered mode and puts each token read into the queue. While
  disarmed it sends no trigger and drops anything read.
- Every read and write uses `scanner_timeout_ms`.
- **The trigger command bytes and the mode setup come from the GM865 manual.**
  Keep them as named constants at the top of `scanner.py`, marked
  `TODO(team)` until they are confirmed on the bench. Do not guess them.

Tests (`tests/test_scanner.py`, with a fake serial object):
- `test_it_queues_a_token_read_while_armed`
- `test_it_drops_a_read_while_disarmed`
- `test_it_reports_closed_when_the_port_fails`

On the Pi, once: arm it, scan a student QR printed from the profile page and one
on a phone, and record the read distance and lighting that work.

---

## Step E6 — Running on the Pi

Follows `System_Plan.md` §7 step 10, and fills in `SETUP.md` §7:

1. `sudo apt install python3-picamera2 python3-numpy python3-serial chromium cage`
2. Copy `settings.example.toml` to `/etc/ewaste-station/settings.toml` and set
   the bench values.
3. `ewaste-station.service`: runs `python3 -m bin_station`, restarts on failure,
   starts at boot.
4. `ewaste-kiosk.service`: `cage` running Chromium in kiosk mode at the page's
   `localhost` address, after the bin station is up.
5. Enter the bin station's URL in the controller's setup portal.

---

## Step E7 — End to end with the controller

With the reworked controller (Track F), or by hand with `curl` before that:
walk the deposit flow screen by screen, then check the fail-closed cases from
`API_Design.md` §8: stop the bin station mid-session and check the controller
cancels; remove the reference and check `verify` is refused; stop the
controller and check the screen goes to `OFFLINE`.

---

## Questions for the owner

1. **`retryable` on bin station errors.** The error shape in §3.5 has a
   `retryable` field, but §11.8 doesn't say its value for the four codes. The
   controller fails closed on any error, so it doesn't change behaviour, but the
   value must be fixed in the docs, not picked in code.
2. **A scan longer than 64 characters.** §11.4 says a token is at most 64
   characters. Is a longer read dropped, or queued cut to 64? A real QR token is
   40, so either way it can't open a session.

## Done when

- Every step's tests pass, with `ruff format --check firmware/station/`,
  `ruff check firmware/station/` and the unit tests green in CI.
- The six endpoints answer exactly as `API_Design.md` §11, including every
  error code, and the kiosk page's server listens on `127.0.0.1` only.
- The bench scores from E4 and the scanner check from E5 are recorded in the PR.
- No secret, no Wi-Fi password and no real bench value is in the repository.
