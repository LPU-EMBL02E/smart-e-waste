# E-waste model

How to build the model the bin station uses to decide whether an item is
e-waste: the data, the training, the tests and what to record. It is a working
note for the bin station's owner. The rules come from `docs/`, which wins over
this note if the two ever disagree:

- `System_Plan.md` §5.7 (the check order and the known limitation), §8.3 (the
  camera check, the e-waste model and the load on the Pi), §10 (open items)
- `API_Design.md` §11.2 and §11.5 (`health` and `verify`), P49
- The bin station side: [TRACK-E-BIN-STATION.md](TRACK-E-BIN-STATION.md), step E5

**Training never runs on the Pi,** and nothing from it goes in
`firmware/station/` except the exported model file's path in the settings file.

---

## What the model must do

| | |
|---|---|
| Question | Is the item on the flap e-waste? |
| Classes | Two: `e-waste` and `not e-waste`. No `empty` class: the presence step runs first, and the model only runs when an item is seen |
| Input | One colour picture of the flap, from the bin's camera, with the LED strip on |
| Output | One number from 0.0 to 1.0: the probability that the item is e-waste |
| Pass | At or above `model_pass_threshold`, set from the tests below. `TODO(team)` |
| Format | ONNX, run on the Pi's CPU with `python3-onnxruntime` from `apt`, two threads |
| Network | A small network built for phones, such as MobileNetV3-Small. `TODO(team)` |

Two classes are enough because the bin only accepts or returns, and points are
one rate for every item (`System_Plan.md` §5.7 rule 1). Knowing that an item is
a phone and not a cable changes nothing.

---

## The plan in two phases

1. **Phase 1, now, before the bin exists:** train on public datasets. This
   builds the whole pipeline (data, training, export, running on the Pi) and
   gives a first model for development.
2. **Phase 2, once the camera, LED strip and flap are mounted:** take pictures
   in the bin, continue training the same model on them, and measure it on bin
   pictures only.

The final model must go through phase 2. `System_Plan.md` §8.3 says the model is
trained and measured on pictures taken in this bin. A phase 1 model's score on
public test images is not the bin's accuracy and is never reported as such.

---

## Phase 1 — Public datasets

**Start from a pretrained network.** MobileNetV3-Small with ImageNet weights,
as shipped with torchvision. It already knows shapes and textures, so it needs
far fewer examples than training from zero.

**Candidate datasets.** Check each licence before use and cite every dataset
used in the report. `TODO(team)`: the final list.

| Dataset | Its classes | Becomes |
|---|---|---|
| [E-Tadweer (ETID)](https://data.mendeley.com/datasets/bndsgcspbx), 6,269 images | Battery, cable, keyboard, laptop, mobile phone, mouse, circuit board | All `e-waste` |
| [EWasteNet's dataset](https://arxiv.org/abs/2311.12823), 1,053 images | Mobile, TV, laptop, keyboard, mouse, microwave, smartwatch, camera | `e-waste`; drop TV and microwave, too big for the slot |
| [Hugging Face waste dataset](https://huggingface.co/datasets/4w4kt/waste-dataset-image), 28,840 images | 9 recycling categories, one of them e-waste | E-waste is `e-waste`; the other 8 are `not e-waste` |
| [TrashNet](https://github.com/garythung/trashnet) | Cardboard, glass, metal, paper, plastic, trash | All `not e-waste` |

**Preparing the data:**
- Sort every image into two folders, `e-waste/` and `not-e-waste/`.
- Drop anything that can't fit through the slot.
- Balance the two folders to roughly equal counts, a few thousand images in
  all, so the model does not lean toward the bigger class.
- Keep a list of which images came from which dataset, for the citations and
  the model record.

**Then:** fine-tune in a cloud notebook such as Colab, export to ONNX, copy the
file to the Pi and time one run (see "On the Pi" below). As an early check
outside the datasets, take a few top-down phone pictures of items on a plain
surface under white light, roughly like the bin, and see what it gets wrong.

---

## Phase 2 — Pictures taken in the bin

**Only once the camera, the LED strip and the flap are in their final
positions.** If the camera moves or the light changes, the pictures stop
matching and must be taken again.

**How many:**

| Set | E-waste | Not e-waste | Use |
|---|---|---|---|
| Training | 300–400 | 300–400 | Teach the model this camera and this light |
| Test | about 100 | about 100 | Measure the model; never trained on |

About 1,000 pictures in all. The workable minimum is about 200 a class for
training and 50 for testing, with less reliable measurements.

**Variety matters more than count.** Many different items, each pictured 8–12
times in different positions, sides up and turns, including near the flap's
edges:
- `e-waste`: about 30–50 items. Phones, chargers, cables, earphones, batteries,
  mice, remotes, small gadgets, circuit boards.
- `not e-waste`: about 30–50 items. Bottles, cups, wrappers, paper, food
  containers, pens, keys, coins. Include look-alikes, because they cause most
  mistakes: a shoelace (like a cable), a lipstick tube (like a battery), an
  empty phone box.

**The test set uses its own items.** Keep some items out of training entirely,
and test only on those. Another picture of an item the model has seen makes the
score look better than the bin will be.

**Rules for taking pictures:**
- Taken by the bin's own camera, at the fixed lens position, with the LED strip
  on, the same way the bin station captures for a check.
- Taken on the bench, never from students' deposits (`System_Plan.md` §8.3).
- Only items on the flap: no people, hands or faces in a picture.
- `TODO(team)`: how they are captured on the Pi, and where they are kept.

One person can take about 200–300 pictures an hour, so this is one or two
afternoons.

---

## Training

- Continue from the phase 1 model, on the phase 2 training set, or on phase 2
  mixed with the public data. Keep whichever measures better on the test set.
- **The picture must be prepared the same way in training and on the Pi:**
  the input size, the colour channel order and the brightness scaling.
  Record all three with the model file (see "The model record"), and check the
  camera's channel order on the Pi rather than assuming it.
- After export, run a few pictures through the original model and through ONNX
  Runtime and check that they give the same probabilities.
- `TODO(team)`: where the training code and the data are kept. Not in
  `firmware/station/`, and not in `tools/`, which is standard library only.

---

## Measuring it

On the phase 2 test set only:

| Measure | Meaning | Why it matters |
|---|---|---|
| False accept rate | Share of `not e-waste` items it passes | Points earned for non-e-waste (`System_Plan.md` §5.7, known limitation) |
| False reject rate | Share of `e-waste` items it fails | Real e-waste returned to the student |

- Raising `model_pass_threshold` lowers false accepts and raises false rejects.
  Pick the threshold from the test results, and record the two rates at the
  threshold chosen.
- List the items it gets wrong. If the rates fall short, the quickest fix is
  more varied items, above all the look-alikes it confuses, not more pictures
  of the same ones.
- `TODO(team)`: the target for each rate, approved by the instructor
  (`System_Plan.md` §10).

---

## On the Pi

Recorded in the Track E step E5 PR:

- How long `verify` takes for an empty flap, for e-waste and for items that are
  not, with the web app serving pages at the same time. The controller's
  timeout for `verify` must be longer.
- Memory used by the bin station with the model loaded.
- That `health` reports `model: false` and `verify` answers 503
  `MODEL_UNAVAILABLE` when the model file is missing (`API_Design.md` §11.5).

---

## The model record

Each model file is kept with a short record. The one in use goes to the school
at handover (`System_Plan.md` §8):

- A version and the date it was trained
- The network and its starting weights
- The datasets used, with their licences, and the number of pictures of each
  class from each source
- The input size, the colour channel order and the brightness scaling
- The threshold chosen, and the false accept and false reject rates at it,
  with the size of the test set
- The items it is known to get wrong

---

## Open items (`TODO(team)`)

- The network, and the final list of public datasets after checking licences.
- How bin pictures are captured on the Pi, and where pictures, training code
  and model files are kept.
- The target false accept and false reject rates, and the instructor's approval
  of the model as the verification method.
- `model_pass_threshold`, set from the phase 2 test results.
