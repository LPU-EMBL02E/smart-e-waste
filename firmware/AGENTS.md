# AGENTS.md — firmware

C++ rules for `firmware/`. This file adds to the root `AGENTS.md`, it does not
replace it: the project rules (§2), scope rules (§3), tests, PRs and ownership
there apply here too. Section numbers refer to the root file.

---

## Formatting (§4.1)

| Code | Tool | Command |
|---|---|---|
| C++ (`firmware/`) | clang-format, repo `.clang-format` (Google style, 2-space) | `clang-format -i <files>` |

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

## Dependencies (§7)

- Firmware libraries must be pinned to a version in `platformio.ini`.

## Before you open a PR (§8)

```bash
cd firmware/<program> && pio run             # each program you touched
```
