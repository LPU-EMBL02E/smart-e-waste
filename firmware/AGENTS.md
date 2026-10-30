# AGENTS.md — firmware

Rules for `firmware/`: C++ for the ESP32 programs, and Python for the bin
station in `station/`. This file adds to the root `AGENTS.md`, it does not
replace it: the project rules (§2), scope rules (§3), tests, PRs and ownership
there apply here too. Section numbers refer to the root file.

---

## Formatting (§4.1)

| Code | Tool | Command |
|---|---|---|
| C++ (`firmware/`) | clang-format, repo `.clang-format` (Google style, 2-space) | `clang-format -i <files>` |
| Python (`firmware/station/`) | ruff | `ruff format firmware/station/ && ruff check firmware/station/` |

## Firmware C++ (§4.4)

- Each hardware part sits behind one module in `src/modules/`: one concrete
  class with a small fixed set of public methods (`System_Plan.md` §8.2).
  `main.cpp` is the sequence only — no hardware calls, no HTTP.
- **Header-only.** A class is declared and defined in its `.hpp`. The only
  `.cpp` in a program is `src/main.cpp`, which Arduino needs for `setup()` and
  `loop()`. A free function in a header is marked `inline`.
- **One top-level type per file**, named after the type in `snake_case`:
  `BinLevel` lives in `bin_level.hpp`. A type used only by one class is nested
  in it (`Scale::Reading`); a type shared by several gets its own file.

  One exception: a second top-level type may share the file only when all of
  these hold:
  - it is a plain `struct` — data members only, no functions or constructors;
  - it is used, now and in future, only by the file's own type and only inside
    that file;
  - its name starts with the full name of the file's type (`ScaleReading` in
    `scale.hpp`);
  - it cannot work as a nested type, and a comment above it says why.
- **Every class declares its own constructor**, and constructors are
  `explicit`, except copy and move constructors. A plain data `struct` has no
  constructor and is initialised with braces.
- **`main.cpp` owns the instances** as plain objects, one per module, never
  with `new`. A module that needs another receives it by reference in its
  constructor (`Api` takes a `Settings&`).
- No virtual methods and no base classes: each module has one implementation
  (root §3).
- Only `Api` knows about HTTP; only `Settings` knows where the server is.
- Use `constexpr` constants in `UPPER_SNAKE_CASE`; functions and variables in
  `camelCase`; types in `PascalCase`.
- **Member prefixes**, for data members and methods alike: `p_` for private,
  `s_` for static, `sp_` for private static. The rest of the name follows the
  rule above: `p_offset`, `p_readPulse()`, `s_instanceCount`,
  `sp_MAX_SAMPLES`.
- Return result values (e.g. `Api::Result`) rather than silently failing; log
  failures to `Serial` with the module name as prefix.

## Firmware on the ESP32 (§5.2)

- **Every wait has a timeout.** Sensors, serial links, HTTP, the actuator — use
  the constants in `config.hpp`. A loop that can wait forever is a bug.
- **Don't block the loop.** Prefer `millis()`-based timing over long `delay()`.
  Short settle delays inside a module are fine; delays in `main.cpp` are not.
- **Avoid heap churn.** No `new`/`malloc` and no Arduino `String` concatenation
  in code that runs repeatedly. Use fixed-size buffers and `snprintf`. Heap
  fragmentation causes crashes days later, not during testing.
- **Keep RAM and flash small.** Constant strings as `const char*` / `F()`; no
  large libraries for one function.
- **Never write NVS in a loop.** Flash wears out; write only when a value
  changes.
- Logging stays at `CORE_DEBUG_LEVEL=2` in committed code.

## Bin station Python (`station/`)

The Pi's half of the bin (`docs/System_Plan.md` §8.3), answering the controller
over the API in `docs/API_Design.md` §11.

- **Python 3 from Raspberry Pi OS, and only the `apt` packages in
  `System_Plan.md` §3**: `python3-picamera2`, `python3-numpy`,
  `python3-serial`, `python3-onnxruntime`. Everything else from the standard library. No `pip install`
  on the Pi, no virtualenv, no web framework.
- **One module per part**, each one class: the scanner, the camera, the e-waste
  model, the screen, the event queue, the HTTP API. Only the scanner module
  touches the serial port; only the camera module imports `picamera2`; only the
  model module imports `onnxruntime`.
- **No hardware or runtime import at module load** outside those three modules,
  so the tests and the API run on a laptop with no camera, scanner or model.
- **Never train on the Pi.** Training code and its libraries stay off the Pi and
  out of the bin station's dependencies; the Pi gets the exported model file.
- **It decides nothing about a deposit,** holds no secret and no database, and
  never calls the web app. It answers the controller and nothing else.
- **Fail closed.** No reference image means the check fails; a camera that
  can't take a picture, or a model that is missing or fails to run, is an
  error, never a pass.
- **Every wait has a timeout**, the serial port included.
- Bench values (lens position, thresholds, the model file's path and threads,
  the controller timeout, the serial device, the listen address) live in the
  bin station's settings file, never in code. The repository holds an example file only.
- Type hints on functions, a module docstring on each file, and tests with the
  standard library's `unittest`.

## Dependencies (§7)

- Firmware libraries must be pinned to a version in `platformio.ini`.
- The bin station adds no dependency beyond the four `apt` packages above.

## Before you open a PR (§8)

```bash
cd firmware/<program> && pio run             # each C++ program you touched
ruff format --check firmware/station/ && ruff check firmware/station/   # station/
python3 -m unittest discover -s firmware/station/tests                 # station/
```
