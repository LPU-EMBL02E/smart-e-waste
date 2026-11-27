# Firmware TODO

Open work on the firmware. Remove an item once it is merged.

## Controller: actuator cycle timing

Board: YD-ESP32-S3 (N8R2), a copy of the ESP32-S3-DevKitC-1
([docs/ESP32-S3.md](docs/ESP32-S3.md)).

Change the actuator cycle in `controller/src/modules/actuator.hpp` to run in
four steps:

1. **Push**: extend to the end of the stroke.
2. **Stop**: hold at the end for 2–3 s, so the item has time to slide off the
   flap before it moves back.
3. **Pull**: retract back to level.
4. **Rest**: wait about 20 s before the next push.

Notes:

- Today the cycle goes straight from the end of the stroke back to level, with
  no hold, and the rest is `ACTUATOR_REST_MS` = 10 s.
- Both times go in `controller/include/config.hpp`, with names ending in `_MS`.
- `docs/System_Plan.md` §2 says a cycle needs "about 10 s of rest". A 20 s rest
  and the new hold change that, so the doc needs updating too, in its own PR.
- Check that the stroke timeout, the jam check and the service-panel interlock
  still apply during the hold.
- Settle the exact values on the bench, then record them.
