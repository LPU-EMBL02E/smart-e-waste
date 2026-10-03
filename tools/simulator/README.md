# Device simulator

Speaks the device API the way the bin's NodeMCU does: the seven endpoints in
`docs/API_Design.md` §5, signed per §3.2. Python 3 standard library only — no
`pip install`, so it runs on a dev machine or on the Pi itself.

`docs/System_Plan.md` §10 puts this first in the build order, before the
firmware: it lets the web app be tested against a correct client while the
hardware is still being chosen, and it produces the awkward cases — a lost
reply, a repeated `complete`, an expired session — on demand instead of by
accident.

## Check the signing contract first

```bash
python3 tools/simulator/test_vectors.py
```

This needs no server. It asserts the two vectors in `API_Design.md` §3.4, which
are the same ones the server's `DeviceSignatureTest` and the firmware's
`api` module check. When all three agree, a signature failure during
integration is a key or a clock problem — never a canonical-string problem,
which is the version of this bug that is miserable to find with hardware in the
loop.

## Run against a server

Register a bin at `/admin/bins` first and copy the secret it shows once.

```bash
cd tools/simulator

# one full deposit
python3 simulate.py --url http://127.0.0.1:8000 \
    --device BIN-01 --secret <64-hex-secret> \
    deposit --qr <student-qr-token> --weight 184.6

# one telemetry reading
python3 simulate.py --url http://127.0.0.1:8000 \
    --device BIN-01 --secret <64-hex-secret> \
    telemetry --distance 412.5
```

Exit code 0 means the run did what was expected, 1 means it did not, so these
can go in CI once the app runs.

## Scenarios

Each one is a case from `API_Design.md` §8 ("Checks before integration"):

| Scenario | What it proves |
|---|---|
| `replay-complete` | A repeated `complete` credits points once and returns 200 both times |
| `duplicate-session` | A second `POST /sessions` cancels the first session and opens a new one |
| `expired-session` | An `OPEN` session past `expires_at` answers 410 `SESSION_EXPIRED` |
| `actuator-failure` | `actuator_ok: false` is a 200 with no transaction and no points |
| `bad-signature` | A corrupted signature is 401 `INVALID_SIGNATURE` |
| `clock-skew` | A timestamp outside the window is 401 `CLOCK_SKEW` and `retryable: true` |
| `unsigned-time` | `GET /time` answers unsigned; `GET /config` does not |

```bash
python3 simulate.py --url ... --device ... --secret ... \
    scenario replay-complete --qr <token>
```

Two cases are deliberately **not** automated here, because they need a state the
simulator cannot create on its own: `BIN_FULL` (send telemetry past the bin's
threshold first, then try to open a session) and `DAILY_LIMIT_REACHED` (deposit
until the day's cap is reached). Both are worth doing by hand once.

## Files

| File | Contents |
|---|---|
| `sign.py` | The canonical string and the HMAC. The one piece that must match the firmware exactly |
| `test_vectors.py` | Asserts the two documented vectors. No server needed |
| `simulate.py` | The client and the scenarios |
