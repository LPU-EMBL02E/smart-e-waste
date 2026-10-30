# Firmware

Two programs:

- `controller/`, on the ESP32-S3-DevKitC-1 (N8R2). `docs/System_Plan.md` §2 and
  §8.2. The rest of this file is about it.
- `station/`, the bin station: Python on the Pi, run as a systemd service.
  `docs/System_Plan.md` §8.3 and `docs/API_Design.md` §11. Not started yet; the
  build spec is [docs/TRACK-E-BIN-STATION.md](docs/TRACK-E-BIN-STATION.md).

**The controller is in charge.** It runs the deposit sequence, owns all safety
logic, and is the only client of the device API. The QR scanner, the camera (with
the e-waste model) and the touchscreen are connected to the Pi and run by the bin
station
(`System_Plan.md` §8.3); the controller reaches them through the bin station's
JSON API (`API_Design.md` §11). Everything else at the bin is wired to the
controller.

> `camera/` and `display/` in this folder are not part of the design: the bin
> station in `station/` takes over their jobs. The controller code still targets
> the NodeMCU pin map. Both are reworked to match this file.

## Building

PlatformIO:

```bash
cd firmware/controller
pio run -e controller                  # build
pio run -e controller -t upload        # flash
pio device monitor                     # serial log
```

## No secrets in this repository

Wi-Fi credentials, the API base URL, the bin station URL, the device code and
the signing secret all live in the controller's non-volatile storage and are set
through the setup portal (`settings` module). Nothing identifying a bin or a
server is compiled in, which is what lets a bin be re-pointed at another server
without reflashing (`System_Plan.md` §8) — and what keeps a device secret out of
git.

## Pin map

Every pin number is in `controller/include/pins.hpp` and nowhere else. The map is
in `System_Plan.md` §2. Things to keep in mind:

- **Actuator current sense is on GPIO 1, an ADC1 pin.** ADC2 can't be read while
  Wi-Fi is on.
- The ultrasonic echo comes through a 1 kΩ / 2 kΩ divider, and the HX711 runs on
  3.3 V, so no input sees more than 3.3 V.
- The driver's enable and PWM lines have 10 kΩ pull-downs, so the actuator stays
  off while the board boots or resets.
- Leave alone: GPIO 0, 3, 45 and 46 (boot strapping), 19 and 20 (USB), 26 to 32
  (flash and PSRAM), 43 and 44 (the USB-to-serial port), and the RGB LED pin of
  the board revision in use (38 or 48).

**TODO(team):** the service-panel interlock and the flap level sensor have no pin
yet, and the current-sense wiring is not on the schematic (`System_Plan.md` §10).

---

## Modules

`System_Plan.md` §8.2. Only `Api` and `Station` know about HTTP; only `Settings`
knows where the server and the bin station are.

| Module | Hides |
|---|---|
| `qr_scanner` | The QR scanner, through `station` |
| `scale` | Load cell, HX711, calibration |
| `verifier` | The camera check and the e-waste model, through `station`, and the LED strip |
| `actuator` | BTS7960 driver, current sense, the flap level sensor, rest time between cycles |
| `interlock` | Service-panel switch |
| `bin_level` | Ultrasonic sensor |
| `feedback` | The screen, through `station`; green, amber and red LEDs; buzzer |
| `station` | HTTP to the bin station |
| `api` | Wi-Fi, HTTP, HMAC signing, retries, the saved unsent `complete` |
| `settings` | Non-volatile storage and the setup portal |

## The bin station link

The full contract is `API_Design.md` §11. In short, the controller:

- arms the scanner when the bin is ready and polls `events` for a scanned token
  or a Cancel touch;
- switches the LED strip on, calls `verify`, and switches it off again, then
  copies the answer into the `deposit` call;
- asks for a new `reference` image of the empty flap at boot and after each
  transfer or return, with the flap level and the LED strip on;
- names each screen change with `screen`.

**No answer means no deposit.** If the bin station does not answer, or `verify`
returns an error, the controller cancels the session and returns the item; the
flap never tips toward the bin.

## The actuator and the flap

The actuator tips the weighing flap (`System_Plan.md` §2 and §8.2). Extending
tips it toward the bin; retracting tips it toward the return tray; level is in
the middle, read from the flap level sensor.

- **Transfer**, after `accepted: true`: extend to the end, then back to level.
- **Return**, whenever a session ends without a transfer and the scale reads an
  item on the flap (a rejected `deposit`, Cancel, a timeout, an unreachable
  server or bin station): retract to the end, then back to level.

The actuator's limit switches are inside it and cut its power at each end, so the
controller judges each stroke to an end by its current, and each stroke back to
level by the level sensor:

- **end of stroke:** after a minimum travel time, the current falls to near zero;
- **level:** the level sensor closes — stop the driver;
- **jam:** the current stays above the jam threshold for too long — disable the
  driver at once;
- **timeout:** neither happens in time — disable the driver.

`actuator_ok` is true when the extend stroke reached its end. A failed return to
level after a good extend still credits the student but latches
`ACTUATOR_RETRACT_FAILED`. A return stroke that jams or times out latches
`ACTUATOR_JAM` or `ACTUATOR_TIMEOUT`. The 20% duty cycle means a rest time between cycles.
The thresholds and times are constants in `include/config.hpp`, set on the bench.

## Faults and the service panel

Opening the service panel disables the driver at once, disarms the scanner and
cancels an `OPEN` session. Actuator faults latch until the panel has been opened
and closed again. Bin station faults clear when it answers normally again. The
panel state and the current fault go out in telemetry at once on any change
(`API_Design.md` §5.3).

---

## Three things the firmware must never do

Each one would credit points that were not earned:

1. **Compute points.** The server computes them from grams
   (`System_Plan.md` §5.7 rule 3). The device sends grams and nothing else.
2. **Tip the flap toward the bin before the server answers `accepted: true`**
   (`API_Design.md` §5.5).
3. **Read an unreachable server or bin station as a pass.** Fail closed
   (`API_Design.md` §3.6 and §11.1).

## The one case that must not lose points

Once an item is physically in the bin, the deposit happened whether or not the
server heard about it. `Api::sendComplete()` retries three times with backoff;
if all three fail it saves the request to NVS, the screen shows "points
pending", and the controller refuses the next deposit until it has been sent —
including across a reboot. The server treats a repeated `complete` as
idempotent, so resending is always safe (`API_Design.md` §5.6).

## Signing

The canonical string, the key and the output format are in `api.hpp`.
Check the firmware against the two vectors in `API_Design.md` §3.4 before
testing against a real server: `tools/simulator/test_vectors.py` asserts the
same two, so a mismatch is immediately local to the firmware rather than a
guess about which side is wrong.

`mbedtls/md.h` ships with the ESP32 Arduino core, so HMAC-SHA256 needs no extra
library.

## Bench testing before integration

`guidelines` Step 5 asks for each component to be tested on its own first, with
results kept. Per module:

| Module | Record |
|---|---|
| `qr_scanner` | Detection rate at different distances and lighting, through the bin station |
| `scale` | Measured vs actual for a range of known weights, with % error |
| `verifier` | Successful verification rate, ≥10 trials, with and without an item; e-waste and non-e-waste items, with the model's accept and reject counts; the time `verify` takes |
| `actuator` | Successful transfer and return rate and response time, ≥10 trials each; current at end of stroke and at a forced stall; the flap stops level every time |
| `interlock` | The driver disables at once when the panel opens, mid-stroke included |
| `bin_level` | Sensor reading vs actual distance, with % error |
| `api` | Both signing vectors, then the simulator's scenarios |

Keep the raw tables. Averages without the raw data are not accepted as
technical evidence (`guidelines`, Step 13).
