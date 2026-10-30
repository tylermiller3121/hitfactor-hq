# Shot detector evaluation

Scores detector output against hand-timed ground truth so each revision can be
compared on the same clips. It needs Python 3 and no other packages.

## Add a clip

Create `truth/<clip>.json`, with times in seconds from the start of the video:

```json
{
    "beep_s": 0.0,
    "shots_s": [0.0, 0.0],
    "notes": "Where the clip came from and how it was timed"
}
```

Keep the videos themselves out of Git: `clips/` is ignored.

## Score a revision

Write one `<clip>.json` for each truth file, using the same `beep_s` and
`shots_s` fields. `beep_s` may be `null` if no beep was found. `runs/` is ignored.

```bash
python3 tools/detector/evaluate.py tools/detector/runs/v3.1 --label v3.1
```

`--label` appends the scores to `results.jsonl`, which is committed so revisions
can be compared over time. Leave it off for experiments.

## Reading the results

- **Precision:** the share of detections that are real shots. Echoes lower this.
- **Recall:** the share of real shots that were detected.
- **Onset:** detection time minus true time. Bias is the signed average, so a
  consistent offset shows up even when shots are matched correctly.
- **Splits:** error in the time between adjacent shots, using only adjacent
  shots that were both matched to adjacent detections.
- **First shot:** error in beep-to-first-shot time. It uses the first detection
  even if that detection is false, because that is what a user would see.

A detection counts within `--tolerance-ms` of a true shot, 50 ms by default.
Keep it well below half of the fastest real split, or one detection could fit
either of two shots. Compare revisions only at the same tolerance.

## Tests

```bash
python3 -m unittest discover -s tools/detector
```
