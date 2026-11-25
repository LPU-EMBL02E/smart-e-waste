# ESP32-S3 station link test

Temporary PlatformIO project that checks whether an ESP32-S3-DevKitC-1 N8R2 can
join Wi-Fi and reach the bin-station health endpoint. It is **not** the
production controller firmware and must not be deployed as one.

Hardware target:

- Board: ESP32-S3-DevKitC-1 N8R2
- Serial: USB `COM7` at `115200` baud
- Station API: `http://192.168.1.34:8080/station/v1/health`
- Wi-Fi: configured at runtime through WiFiManager; no credentials are stored in
  this repository.

The sketch uses the same PlatformIO pins as `firmware/controller/platformio.ini`:
`espressif32@7.1.3`, Arduino framework, `esp32-s3-devkitc-1`,
`board_build.arduino.memory_type = qio_qspi`, `-DBOARD_HAS_PSRAM`, and WiFiManager
`v2.0.17`. It does not initialize or use GPIO pins or physical peripherals.

## Prerequisites

- PlatformIO CLI installed (`pio --version`). If `pio` is not on `PATH`, use
  `C:\Users\barri\.platformio\penv\Scripts\pio.exe` on Windows.
- USB cable to the ESP32-S3 on `COM7`.
- The station API reachable at `http://192.168.1.34:8080/station/v1/health`
  before flashing. For the mock station, run it on the Pi or laptop that owns
  `192.168.1.34:8080` with `api_host` bound to a LAN address.
- A 2.4 GHz Wi-Fi network the ESP32 can join.

Quick reachability check from the PC before flashing:

```bash
curl http://192.168.1.34:8080/station/v1/health
```

Expected JSON is similar to:

```json
{"scanner":true,"camera":true,"reference":false,"model":true}
```

## Build

From the repository root:

```bash
pio run -d tools/esp32_station_link_test
```

Or from the project directory:

```bash
cd tools/esp32_station_link_test
pio run
```

## Upload

From the repository root:

```bash
pio run -d tools/esp32_station_link_test -t upload --upload-port COM7
```

Or from the project directory:

```bash
cd tools/esp32_station_link_test
pio run -t upload --upload-port COM7
```

If the upload fails to enter download mode, hold `BOOT`, tap `EN`/`RST`, release
`BOOT`, and retry.

## Serial monitor

From the project directory:

```bash
cd tools/esp32_station_link_test
pio device monitor --port COM7 --baud 115200
```

Or from the repository root:

```bash
pio device monitor -d tools/esp32_station_link_test --port COM7 --baud 115200
```

## First boot / Wi-Fi setup

1. Open the serial monitor.
2. On first boot, WiFiManager opens a configuration portal named
   `ESP32 Station Link Test`.
3. Connect a phone/laptop to that AP and open `http://192.168.4.1`.
4. Enter the 2.4 GHz Wi-Fi SSID and password. WiFiManager stores the credentials
   on the ESP32; they are not written to this repository.
5. After connecting, the serial log prints Wi-Fi status, ESP32 IP, the station
   API URL, then one health request. The sketch repeats the health GET every
   five seconds.

Expected serial output after Wi-Fi connects:

```text
ESP32-S3 station link test
Wi-Fi status: connected
Wi-Fi status code: 3
ESP32 IP address: 192.168.1.xx
Raspberry Pi API URL: http://192.168.1.34:8080/station/v1/health
--- station health request ---
HTTP response status: 200
JSON response body: {"scanner":true,"camera":true,"reference":false,"model":true}
```

If the station is unreachable, the log prints an `HTTP connection error` with the
`HTTPClient` error string instead of a JSON body.

## Changing the target URL

The URL is a compile-time constant in `src/main.cpp`:

```cpp
const char* kStationHealthUrl = "http://192.168.1.34:8080/station/v1/health";
```

Change it only for this temporary test if the station address changes, then
rebuild and re-upload.
