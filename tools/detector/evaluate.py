#!/usr/bin/env python3
"""Score shot detector output against hand-timed ground truth."""

import argparse
import json
import math
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

DEFAULT_TOLERANCE_MS = 50.0


class InputError(Exception):
    pass


def read_times(data, source):
    times = data.get("shots_s")
    if not isinstance(times, list):
        raise InputError(f"{source}: shots_s must be a list")
    for value in times:
        if not valid_time(value):
            raise InputError(f"{source}: invalid shot time {value!r}")
    return sorted(times)


def valid_time(value):
    return (
        isinstance(value, (int, float))
        and not isinstance(value, bool)
        and math.isfinite(value)
        and value >= 0
    )


def load_json(path):
    try:
        return json.loads(Path(path).read_text())
    except OSError as error:
        raise InputError(f"{path}: {error.strerror}") from error
    except ValueError as error:
        raise InputError(f"{path}: invalid JSON ({error})") from error


def load_truth(path):
    data = load_json(path)
    shots = read_times(data, path)
    beep = data.get("beep_s")
    if not valid_time(beep):
        raise InputError(f"{path}: beep_s must be a time in seconds")
    if not shots:
        raise InputError(f"{path}: ground truth needs at least one shot")
    return {"beep_s": beep, "shots_s": shots}


def load_detections(path):
    data = load_json(path)
    shots = read_times(data, path)
    beep = data.get("beep_s")
    if beep is not None and not valid_time(beep):
        raise InputError(f"{path}: beep_s must be a time in seconds or null")
    return {"beep_s": beep, "shots_s": shots}


def match_shots(truth, detected, tolerance_s):
    """Pair shots one-to-one, in order, within tolerance.

    Maximizes the number of pairs, then minimizes total timing error, so a
    burst of false positives cannot steal a true shot from a closer detection.
    Returns (truth_index, detected_index) pairs.
    """
    rows, cols = len(truth), len(detected)
    # Each cell holds (pairs, -total_error) for the remaining suffixes.
    best = [[(0, 0.0)] * (cols + 1) for _ in range(rows + 1)]

    for i in range(rows - 1, -1, -1):
        for j in range(cols - 1, -1, -1):
            best[i][j] = max(best[i + 1][j], best[i][j + 1])
            error = abs(detected[j] - truth[i])
            if error <= tolerance_s:
                pairs, score = best[i + 1][j + 1]
                best[i][j] = max(best[i][j], (pairs + 1, score - error))

    matches = []
    i = j = 0
    while i < rows and j < cols:
        if best[i][j] == best[i + 1][j]:
            i += 1
        elif best[i][j] == best[i][j + 1]:
            j += 1
        else:
            matches.append((i, j))
            i += 1
            j += 1
    return matches


def score_clip(truth, detections, tolerance_ms):
    shots, detected = truth["shots_s"], detections["shots_s"]
    matches = match_shots(shots, detected, tolerance_ms / 1000)

    onset_errors = [(detected[j] - shots[i]) * 1000 for i, j in matches]

    split_errors = []
    for (i, j), (next_i, next_j) in zip(matches, matches[1:]):
        if next_i == i + 1 and next_j == j + 1:
            true_split = shots[next_i] - shots[i]
            detected_split = detected[next_j] - detected[j]
            split_errors.append((detected_split - true_split) * 1000)

    beep_error = None
    first_shot_error = None
    if detections["beep_s"] is not None:
        beep_error = (detections["beep_s"] - truth["beep_s"]) * 1000
        if detected:
            true_first = shots[0] - truth["beep_s"]
            detected_first = detected[0] - detections["beep_s"]
            first_shot_error = (detected_first - true_first) * 1000

    return {
        "true_shots": len(shots),
        "detected_shots": len(detected),
        "true_positives": len(matches),
        "false_positives": len(detected) - len(matches),
        "false_negatives": len(shots) - len(matches),
        "onset_errors_ms": onset_errors,
        "split_errors_ms": split_errors,
        "beep_errors_ms": [] if beep_error is None else [beep_error],
        "first_shot_errors_ms": [] if first_shot_error is None else [first_shot_error],
    }


def percentile(values, fraction):
    ordered = sorted(values)
    return ordered[math.ceil(fraction * len(ordered)) - 1]


def error_summary(errors):
    if not errors:
        return None
    absolute = [abs(error) for error in errors]
    return {
        "count": len(errors),
        "mean_signed_ms": round(sum(errors) / len(errors), 2),
        "mean_abs_ms": round(sum(absolute) / len(absolute), 2),
        "p95_abs_ms": round(percentile(absolute, 0.95), 2),
        "max_abs_ms": round(max(absolute), 2),
    }


def summarize(results):
    """Combine clip results, pooling counts so long clips carry more weight."""
    counts = {
        key: sum(result[key] for result in results)
        for key in (
            "true_shots",
            "detected_shots",
            "true_positives",
            "false_positives",
            "false_negatives",
        )
    }
    hits = counts["true_positives"]
    precision = hits / counts["detected_shots"] if counts["detected_shots"] else 0.0
    recall = hits / counts["true_shots"] if counts["true_shots"] else 0.0
    f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0

    def pooled(key):
        return error_summary([error for result in results for error in result[key]])

    return {
        **counts,
        "precision": round(precision, 4),
        "recall": round(recall, 4),
        "f1": round(f1, 4),
        "onset_error": pooled("onset_errors_ms"),
        "split_error": pooled("split_errors_ms"),
        "beep_error": pooled("beep_errors_ms"),
        "first_shot_error": pooled("first_shot_errors_ms"),
    }


def evaluate(truth_dir, detections_dir, tolerance_ms):
    truth_paths = sorted(Path(truth_dir).glob("*.json"))
    if not truth_paths:
        raise InputError(f"{truth_dir}: no ground truth files found")

    clips = {}
    for truth_path in truth_paths:
        detection_path = Path(detections_dir) / truth_path.name
        if not detection_path.is_file():
            raise InputError(f"{detection_path}: missing detections for this clip")
        clips[truth_path.stem] = score_clip(
            load_truth(truth_path), load_detections(detection_path), tolerance_ms
        )

    return {
        "clips": {name: summarize([result]) for name, result in clips.items()},
        "overall": summarize(list(clips.values())),
    }


def current_commit():
    result = subprocess.run(
        ["git", "rev-parse", "--short", "HEAD"], capture_output=True, text=True
    )
    return result.stdout.strip() or None


def format_error(summary):
    if summary is None:
        return "n/a"
    return (
        f"mean {summary['mean_abs_ms']:.1f} ms, p95 {summary['p95_abs_ms']:.1f} ms, "
        f"bias {summary['mean_signed_ms']:+.1f} ms (n={summary['count']})"
    )


def format_summary(name, summary):
    return "\n".join(
        [
            name,
            f"  shots      {summary['detected_shots']} detected / "
            f"{summary['true_shots']} true "
            f"({summary['true_positives']} TP, {summary['false_positives']} FP, "
            f"{summary['false_negatives']} FN)",
            f"  precision  {summary['precision']:.3f}",
            f"  recall     {summary['recall']:.3f}",
            f"  f1         {summary['f1']:.3f}",
            f"  onset      {format_error(summary['onset_error'])}",
            f"  splits     {format_error(summary['split_error'])}",
            f"  beep       {format_error(summary['beep_error'])}",
            f"  first shot {format_error(summary['first_shot_error'])}",
        ]
    )


def main(argv=None):
    root = Path(__file__).parent
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("detections", help="directory of <clip>.json detector output")
    parser.add_argument("--truth", default=root / "truth", help="ground truth directory")
    parser.add_argument(
        "--tolerance-ms",
        type=float,
        default=DEFAULT_TOLERANCE_MS,
        help="largest gap between a detection and a true shot that still counts",
    )
    parser.add_argument("--label", help="revision name, such as v3.1-echo-gate")
    parser.add_argument(
        "--history", default=root / "results.jsonl", help="file --label appends to"
    )
    args = parser.parse_args(argv)

    if args.tolerance_ms <= 0:
        parser.error("--tolerance-ms must be positive")

    try:
        report = evaluate(args.truth, args.detections, args.tolerance_ms)
    except InputError as error:
        print(f"error: {error}", file=sys.stderr)
        return 2

    for name, summary in report["clips"].items():
        print(format_summary(name, summary))
    if len(report["clips"]) > 1:
        print(format_summary("overall", report["overall"]))

    if args.label:
        entry = {
            "label": args.label,
            "recorded_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
            "commit": current_commit(),
            "tolerance_ms": args.tolerance_ms,
            **report,
        }
        with open(args.history, "a") as history:
            history.write(json.dumps(entry) + "\n")

    return 0


if __name__ == "__main__":
    sys.exit(main())
