# Linear actuator — BST-YP-A1-200-305-160-100-12

Reference sheet for the actuator that tips the weighing flap. The controller
code for it is `firmware/controller/src/modules/actuator.hpp`, and its settings
are in `firmware/controller/include/config.hpp`.

`docs/System_Plan.md` §2 wins over this sheet if the two ever disagree.

**No datasheet found.** A web search (2026-11-27) found no datasheet or listing
for this exact model code. Each value below is marked with where it comes from:

- **Plan**: `docs/System_Plan.md` §2.
- **Code**: read from the model code (see below). Inferred, not confirmed.
- **Listing**: a seller listing for a similar actuator that may be the same
  unit. Unconfirmed: check it on the bench or with the seller.
- **Bench**: not known yet; measure it.

## The model code

Chinese actuator sellers usually build the code from the main specifications.
Read that way:

| Part | Probable meaning |
|---|---|
| `BST-YP` | Seller or series prefix. Unknown |
| `A1` | Body or option variant. Unknown |
| `200` | Stroke, mm |
| `305` | Retracted length, mm |
| `160` | No-load speed, mm/s |
| `100` | Rated force, N |
| `12` | Supply, V DC |

The 200, 160, 100 and 12 match the actuator in `System_Plan.md` §2, which
supports this reading.

## Specifications

| Item | Value | Source |
|---|---|---|
| Supply | 12 V DC | Plan, Code |
| Stroke | 200 mm | Plan, Code |
| Retracted length | 305 mm | Code, Listing |
| Extended length | 505 mm (305 + 200) | Listing |
| Force | 100 N | Plan, Code |
| Speed | 160 mm/s | Plan, Code |
| Maximum current | 3 A | Plan |
| Rated power | 20 W, 30 W maximum | Listing |
| Duty cycle | 20% | Plan |
| End-of-stroke limit switches | Built in; they cut power at each end | Plan |
| Full-stroke time | About 1.3 s (200 mm at 160 mm/s, no load) | Worked out from Plan |
| No-load and running current | — | Bench |
| Stall current | — | Bench |
| Wire colours and polarity | — | Bench |
| Mounting hole diameter | — | Bench |
| IP rating, weight, operating temperature | — | Seller |

## What this means for the firmware

- **Limit switches are inside the actuator.** At each end it cuts its own
  power, so the controller detects the end of a stroke by the current falling,
  read through the BTS7960's current sense (`ACTUATOR_END_CURRENT_MV`).
- **Duty cycle.** 20% means for every second of running, about four seconds of
  rest. The rest time between cycles is `ACTUATOR_REST_MS`.
- **Stroke timeout.** A full stroke takes about 1.3 s with no load, so
  `ACTUATOR_STROKE_TIMEOUT_MS` (3 s) leaves room for the load.
- **Driver.** The BTS7960 is rated well above 3 A, so the driver isn't the limit.
  The 12 V supply must give at least 3 A to the actuator on top of everything
  else on that rail.

## To confirm

- [ ] Get the datasheet or the full listing from whoever bought the actuator,
      and replace the Code and Listing values with it.
- [ ] Measure on the bench: running current at no load and with the flap,
      stall current, and the current-sense voltage at each (needed for
      `ACTUATOR_END_CURRENT_MV` and `ACTUATOR_JAM_CURRENT_MV`).
- [ ] Record the wire colours, and which polarity extends.
- [ ] Time a full stroke with the flap mounted.

## Sources

- [Amazon: 12V Linear Actuator 200mm Stroke 100/200/300/500/700/1000N](https://www.amazon.com/Linear-Actuator-Stroke-Electric-Controller/dp/B0BBN477J8)
  — the Listing values: 305 mm retracted, 505 mm extended, 20 W rated and
  30 W maximum, as shown in search results. The page itself could not be
  loaded, and it doesn't name this model code.
