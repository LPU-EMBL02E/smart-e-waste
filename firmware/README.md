# Firmware

Three programs, one per board. `docs/System_Plan.md` §8.2.

| Folder | Board | Job |
|---|---|---|
| `controller/` | ESP32 NodeMCU | Runs the deposit sequence and talks to the server |
| `camera/` | ESP32-CAM | Answers "is an item present?" with pass/fail and a score |
| `display/` | ESP32-S3 panel board | Draws the screens with LVGL and reports touches |

**The NodeMCU is in charge.** It is the only board that joins the Wi-Fi network
and the only one that talks to the server. The camera and the panel each do one
job for it over a serial link. The Raspberry Pi drives no sensor, no camera and
no display — it is only a server, which is what lets the web app move to the
school's own platform at handover without a rewrite.

## Building

PlatformIO, one environment per program:

```bash
pio run -e controller                  # build
pio run -e controller -t upload        # flash
pio device monitor                     # serial log
```

Run these from inside the program's own folder (`firmware/controller`, etc.) —
each has its own `platformio.ini`.

Flashing an AI-Thinker ESP32-CAM needs an external USB-serial adapter and
GPIO 0 pulled to ground to enter the bootloader.

## No secrets in this repository

Wi-Fi credentials, the API base URL, the device code and the signing secret all
live in the NodeMCU's non-volatile storage and are set through the setup portal
(`settings` module). Nothing identifying a bin or a server is compiled in, which
is what lets a bin be re-pointed at another server without reflashing
(`System_Plan.md` §8) — and what keeps a device secret out of git.

## Pin map

Every pin number for the controller is in `controller/include/pins.h` and
nowhere else. Two to keep in mind: GPIO 34 and 35 are **input-only**, so the
load cell data line must stay at 3.3 V and the ultrasonic echo line needs a
divider if the sensor runs on 5 V.

**TODO(team):** verify the map on the bench, and revisit the motor pins once the
actuator and driver are chosen (`System_Plan.md` §10).

---

## Serial protocols

Both links are plain text, one message per line, terminated with `\n`. Text was
chosen over a binary framing on purpose: it can be driven by hand from a serial
monitor, which makes each board testable on its own before integration.

> **Proposed.** `System_Plan.md` §8.2 specifies "one line of text per screen
> change" and a pass/fail-plus-score answer from the camera, but not the exact
> wording. The lines below are a proposal in the style of `API_Design.md`'s
> `[P]` items — confirm them, then keep both sides in step. The names are also
> mirrored in the controller's `feedback` module and `display/include/screens.h`.

### Controller ⇄ camera (UART 1, 115200)

| Direction | Line | Meaning |
|---|---|---|
| → | `VERIFY` | Is an item on the platform? |
| ← | `OK <score>` | Yes. `score` is 0.0000–1.0000 |
| ← | `FAIL <score>` | No |
| → | `REFERENCE` | Re-learn the empty platform |
| ← | `READY` | Reference stored |
| ← | `ERROR <reason>` | Could not |
| → | `PING` / ← `PONG` | Liveness |

The controller sends `REFERENCE` whenever the chamber is known to be empty —
after a transfer and at boot — because lighting drift otherwise turns every
frame into a "difference".

**No reply means FAIL.** Points are credited only after verification *passes*,
so a silent camera must never be read as an accepted item.

### Controller ⇄ display panel (UART 2, 115200)

| Direction | Line | Meaning |
|---|---|---|
| → | `SCREEN <NAME> [values...]` | Draw that screen |
| → | `PING` / ← `PONG` | Liveness |
| ← | `TOUCH CANCEL` | The student pressed Cancel |

Screen names: `IDLE`, `WELCOME`, `WEIGHING`, `ACCEPTED`, `DONE`, `PENDING`,
`PROBLEM`, `BIN_FULL`, `OFFLINE`.

```
SCREEN WELCOME Juan
SCREEN WEIGHING
SCREEN ACCEPTED 184.6 18
SCREEN DONE 18 240
SCREEN PROBLEM This bin is full
```

The panel holds no state that matters. If it reboots mid-deposit the controller
carries on, and the next `SCREEN` line puts the panel back in step.

`PROBLEM` carries a plain-language message, never an API error code — the error
code is for the firmware to branch on, not for a student to read.

---

## Three things the firmware must never do

Each one would credit points that were not earned:

1. **Compute points.** The server computes them from grams
   (`System_Plan.md` §5.7 rule 3). The device sends grams and nothing else.
2. **Move the actuator before the server answers `accepted: true`**
   (`API_Design.md` §5.5).
3. **Read an unreachable server or camera as a pass.** Fail closed
   (`API_Design.md` §3.6).

## The one case that must not lose points

Once an item is physically in the bin, the deposit happened whether or not the
server heard about it. `api::sendComplete()` retries three times with backoff;
if all three fail it saves the request to NVS, the panel shows "points
pending", and the controller refuses the next deposit until it has been sent —
including across a reboot. The server treats a repeated `complete` as
idempotent, so resending is always safe (`API_Design.md` §5.6).

## Signing

The canonical string, the key and the output format are in `api.h` and `api.cpp`.
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
| `scanner` | Detection rate at different distances and lighting |
| `scale` | Measured vs actual for a range of known weights, with % error |
| `verifier` | Successful verification rate, ≥10 trials |
| `actuator` | Successful cycle rate and response time, ≥10 trials |
| `bin_level` | Sensor reading vs actual distance, with % error |
| `api` | Both signing vectors, then the simulator's scenarios |

Keep the raw tables. Averages without the raw data are not accepted as
technical evidence (`guidelines`, Step 13).
