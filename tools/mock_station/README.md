# Mock bin station

Test double for the bin station API in `docs/API_Design.md` §11 and the station
build notes in `firmware/docs/TRACK-E-BIN-STATION.md`. It lets the ESP32-S3
controller and integration tests exercise the station contract before the real
Raspberry Pi station exists.

This is **not** the production station. It is not `firmware/station/`, it does
not talk to Laravel or the device API, it holds no secret, and it never decides
whether a deposit earns points. It only answers the six documented
`/station/v1/*` endpoints and provides a localhost control page for injecting
test events and failures.

## Requirements

- Python 3.10 or newer. The code uses only the standard library.
- For ESP32 testing: the laptop running the mock and the ESP32 must be on the
  same reachable network.
- Optional for development checks: `ruff==0.17.0`.

## Install

From the repository root:

```bash
cd tools/mock_station
cp settings.example.json settings.json   # Windows: copy settings.example.json settings.json
```

No `pip install` is required to run the mock.

## Run

### Simulation mode

The default `settings.example.json` uses simulated scanner, camera, and model
components. Start the mock with:

```bash
py -3 -m mock_station --settings settings.json        # Windows
python3 -m mock_station --settings settings.json      # Linux/macOS
```

Expected startup output is similar to:

```text
mock-station API: http://0.0.0.0:8080/station/v1
mock-station control: http://127.0.0.1:8081/
mock-station component scanner: simulated
mock-station component camera: simulated
mock-station component model: simulated
mock-station running; press Ctrl+C to stop
```

Stop it with `Ctrl+C`.

### Hardware-in-the-loop mode

Each component can be set independently in `settings.json`:

```json
"components": {
  "scanner": { "type": "simulated", "available": true },
  "camera": { "type": "hardware" },
  "model": { "type": "simulated", "probability": 0.93 },
  "display": { "type": "simulated" }
}
```

`type: "hardware"` is currently a **placeholder**. It reports the component as
unavailable in `GET /station/v1/health` because the real device specifications
are still `TODO(team)`:

- GM865 scanner command bytes and serial settings are not confirmed.
- `picamera2` bench values for the camera are not confirmed.
- The approved ONNX model path, threads, and pass threshold are not confirmed.
- A physical HDMI/touch kiosk is not implemented; use the control page or the
  stored screen state for display testing.

Do not add GPIO assignments, serial command bytes, camera configuration, or
model files to this mock until those specifications are established.

## Configuration

`settings.json` fields:

| Field | Default | Meaning |
|---|---|---|
| `api_host` / `api_port` | `0.0.0.0` / `8080` in the example | Controller-facing HTTP API bind address. Use `0.0.0.0` only on a trusted test LAN. |
| `page_host` / `page_port` | `127.0.0.1` / `8081` | Localhost control page bind address. Keep this on `127.0.0.1` unless you understand the exposure. |
| `controller_timeout_s` | `10.0` | Mock-side controller-contact timeout from API_Design.md §11.9. When no controller call arrives in time, the mock disarms the scanner and shows `OFFLINE`. |
| `control_file` | `null` | Optional append-only JSONL file for headless control commands. |
| `control_poll_s` | `0.2` | Poll interval for `control_file`. |
| `pixel_threshold` | `10` | Brightness difference threshold for the simulated camera diff check. |
| `presence_threshold` | `0.01` | Fraction of changed pixels required before the model runs. |
| `model_pass_threshold` | `0.8` | Simulated model probability required for `passed: true`. |

Component options:

- `scanner`: `type`, `available`. Simulated scanners only queue tokens injected
  through the control interface while armed.
- `camera`: `type`, `available`, `width`, `height`, `changed_pixels`,
  `capture_failures`, `reference_failures`.
- `model`: `type`, `available`, `probability`.
- `display`: accepted for configuration symmetry; the mock stores screen state
  but does not render a physical display.

## Station API

The controller-facing API is available at `http://<api_host>:<api_port>/station/v1`.
The ESP32 controller appends `/station/v1` itself, so give it only the base URL
`http://<host>:<port>` in its station URL setting.

Implemented endpoints, exactly as documented:

| Endpoint | Behavior |
|---|---|
| `GET /station/v1/health` | Returns `{scanner, camera, reference, model}` booleans. |
| `PUT /station/v1/scanner` | Accepts `{armed: bool}`. Arming clears queued QR tokens and keeps touch events. Returns 503 `SCANNER_UNAVAILABLE` when no scanner can arm. |
| `GET /station/v1/events` | Returns `{events: [...]}` oldest-first, maximum 16 events, dropping the oldest when full. Event types are `qr`+`token` and `touch`+`action:"cancel"`. |
| `POST /station/v1/verify` | Runs simulated presence (`cam_diff`) then the simulated model (`ewaste_model`) only when presence passes. Returns 409 `NO_REFERENCE`, 503 `CAMERA_UNAVAILABLE`, or 503 `MODEL_UNAVAILABLE`. |
| `POST /station/v1/reference` | Stores a simulated blank reference. Returns `{stored: true}` or 503 `CAMERA_UNAVAILABLE`; a failed capture keeps the previous reference. |
| `PUT /station/v1/screen` | Validates and stores the current screen (`IDLE`, `WELCOME`, `WEIGHING`, `ACCEPTED`, `DONE`, `PENDING`, `PROBLEM`, `BIN_FULL`, `OFFLINE`) and required values. Invalid requests return 422 `VALIDATION_ERROR`. |

Errors use the section 3.5 shape `{error:{code,message,retryable}}`. The mock
sets `retryable: false` for every station error because §11.8 does not define
per-code values and the controller fails closed.

Controller-contact timeout: every controller call resets contact. If no call
arrives within `controller_timeout_s`, the mock disarms the scanner and records
`OFFLINE`. The next controller call ends the mock's offline flag; the controller
is still responsible for sending the screen it wants.

## Control interface

Open `http://<page_host>:<page_port>/` (default `http://127.0.0.1:8081/`) in a
browser. The page shows live component/screen/contact state and can:

- mark scanner/camera/model available or unavailable,
- set simulated camera changed pixels, item present, empty flap, and failure counts,
- capture, clear, or fail the next reference,
- set simulated model probability, pass/fail result, and failures,
- inject QR tokens and cancel touches,
- submit the same commands as JSON to `POST /control/command`.

QR injection only queues an event while the scanner is armed. Arm it through the
station API or the controller:

```bash
curl -X PUT http://127.0.0.1:8080/station/v1/scanner \
  -H "Content-Type: application/json" \
  -d '{"armed":true}'
```

Control commands are mock-only and are not part of the station API. Supported
command objects include:

```json
{"inject_qr":"student-token"}
{"inject_touch":"cancel"}
{"set_available":{"scanner":true,"camera":false,"model":true}}
{"set_camera":{"item":true}}
{"set_camera":{"empty":true}}
{"set_camera":{"changed_pixels":512,"capture_failures":1,"reference_failures":0}}
{"set_reference":{"action":"capture"}}
{"set_reference":{"action":"clear"}}
{"set_reference":{"action":"fail_next"}}
{"set_model":{"probability":0.95}}
{"set_model":{"result":"pass"}}
{"set_model":{"result":"fail"}}
{"set_model":{"failures":1}}
```

For headless testing, set `control_file` in `settings.json` to a JSONL path and
append one command object per line after the mock starts. Lines already present
when the mock starts are ignored; the file is append-only. Example:

```json
{"set_available":{"camera":false}}
{"set_model":{"result":"fail"}}
{"inject_touch":"cancel"}
```

Invalid control lines are printed to stderr and do not stop the mock.

## Connect the ESP32-S3 controller

1. Start the mock on the laptop with `api_host` set to `0.0.0.0` (the example
   already does this) and note the laptop's LAN IP address.
2. Connect the ESP32 to the same network as the laptop.
3. In the controller setup portal, set:
   - Server URL: the Laravel device API base URL.
   - Bin station URL: `http://<laptop-ip>:8080` — scheme, host, and port only;
     no `/station/v1` path and no trailing slash.
   - Device code and secret from `/admin/bins`.
4. Watch the controller serial log. The controller calls station `health`,
   takes a reference when needed, arms the scanner only when `scanner`,
   `camera`, and `model` are healthy, then polls events and verifies deposits.
5. From another machine on the LAN, verify the mock is reachable:

   ```bash
   curl http://<laptop-ip>:8080/station/v1/health
   ```

   Expected response:

   ```json
   {"scanner":true,"camera":true,"reference":false,"model":true}
   ```

6. Use the control page at `http://127.0.0.1:8081/` on the laptop to inject QR
   scans, cancel touches, and force failure scenarios while the controller runs.

If Windows Firewall blocks the connection, allow Python on the private/test
network or use a wired test LAN. Do not expose `api_host: 0.0.0.0` beyond the
test network.

## Logs

The mock prints startup lines and control-file errors to stderr. HTTP request
logging is off by default; set `MOCK_STATION_LOG=1` before starting to enable
`BaseHTTPRequestHandler` request logs:

```bash
MOCK_STATION_LOG=1 py -3 -m mock_station --settings settings.json   # Linux/macOS
$env:MOCK_STATION_LOG="1"; py -3 -m mock_station --settings settings.json  # PowerShell
```

On the controller side, station calls appear in the ESP32 serial log through the
`station.hpp` module. A missing reply, timeout, or non-JSON reply is treated as
station unreachable; a reply in the error shape is reported with its error code.
The mock never returns a pass for an unavailable camera, model, scanner, or
missing reference.

## Tests

From `tools/mock_station`:

```bash
py -3 -m unittest discover -s tests -t .
```

From the repository root:

```bash
py -3 -m ruff format --check tools/mock_station
py -3 -m ruff check tools/mock_station
```

The tests cover all six endpoints, event ordering/overflow/clearing, scanner
arming and unavailable errors, reference success/failure retention, verify
pass/fail and fail-closed errors, screen validation, malformed requests,
control commands, the JSONL control file, the control page, hardware
placeholders, and controller-contact timeout.

## Limitations

- No real scanner, camera, model, or touchscreen drivers are implemented.
  `type: "hardware"` placeholders always report unavailable until the device
  specifications are confirmed.
- The control page is a tester surface, not the production kiosk. It does not
  render the station screens; it exposes state and injects test commands.
- The mock does not implement the Laravel device API, HMAC signing, deposits,
  points, rewards, or notifications.
- The mock-side controller timeout is configurable, but the firmware
  `STATION_TIMEOUT_MS`, `STATION_VERIFY_TIMEOUT_MS`, and
  `STATION_EVENT_POLL_MS` values in `firmware/controller/include/config.hpp`
  are still bench `TODO(team)` values.
- `retryable` is always `false` for station errors pending a docs decision for
  §11.8.
