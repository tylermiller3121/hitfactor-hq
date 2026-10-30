import contextlib
import io
import json
import tempfile
import unittest
from pathlib import Path

import evaluate


def clip(shots, beep=1.0):
    return {"beep_s": beep, "shots_s": shots}


class MatchShotsTest(unittest.TestCase):
    def test_pairs_each_detection_once(self):
        self.assertEqual(
            evaluate.match_shots([1.0, 2.0], [1.0, 1.01, 2.0], 0.05),
            [(0, 0), (1, 2)],
        )

    def test_prefers_the_closest_detection(self):
        self.assertEqual(
            evaluate.match_shots([1.0], [0.96, 1.001, 1.04], 0.05),
            [(0, 1)],
        )

    def test_prefers_more_pairs_over_less_error(self):
        # Pairing 1.04 with 1.05 is closest, but would leave 1.0 unmatched.
        self.assertEqual(
            evaluate.match_shots([1.0, 1.05], [1.04, 1.06], 0.05),
            [(0, 0), (1, 1)],
        )

    def test_rejects_detections_outside_tolerance(self):
        self.assertEqual(evaluate.match_shots([1.0], [1.051], 0.05), [])

    def test_handles_empty_input(self):
        self.assertEqual(evaluate.match_shots([], [1.0], 0.05), [])
        self.assertEqual(evaluate.match_shots([1.0], [], 0.05), [])


class ScoreClipTest(unittest.TestCase):
    def summary(self, truth, detections, tolerance_ms=50):
        return evaluate.summarize(
            [evaluate.score_clip(truth, detections, tolerance_ms)]
        )

    def test_perfect_detection(self):
        shots = [2.0, 2.3, 2.6]
        summary = self.summary(clip(shots), clip(shots))

        self.assertEqual(summary["precision"], 1.0)
        self.assertEqual(summary["recall"], 1.0)
        self.assertEqual(summary["f1"], 1.0)
        self.assertEqual(summary["onset_error"]["max_abs_ms"], 0)

    def test_over_detection_lowers_precision_only(self):
        summary = self.summary(
            clip([2.0, 3.0, 4.0, 5.0]),
            clip([2.0, 2.2, 2.4, 2.6, 3.0, 3.2, 3.4, 3.6, 4.0, 5.0]),
        )

        self.assertEqual(summary["false_positives"], 6)
        self.assertEqual(summary["precision"], 0.4)
        self.assertEqual(summary["recall"], 1.0)

    def test_missed_shots_lower_recall_only(self):
        summary = self.summary(clip([2.0, 3.0, 4.0, 5.0]), clip([2.0]))

        self.assertEqual(summary["false_negatives"], 3)
        self.assertEqual(summary["precision"], 1.0)
        self.assertEqual(summary["recall"], 0.25)

    def test_signed_error_shows_detector_bias(self):
        summary = self.summary(clip([2.0, 3.0]), clip([2.01, 3.01]))

        self.assertAlmostEqual(summary["onset_error"]["mean_signed_ms"], 10, places=1)

    def test_splits_only_use_adjacent_matched_shots(self):
        summary = self.summary(
            clip([2.0, 2.3, 2.6, 2.9]),
            clip([2.0, 2.31, 2.9]),
        )

        self.assertEqual(summary["split_error"]["count"], 1)
        self.assertAlmostEqual(summary["split_error"]["mean_signed_ms"], 10, places=1)

    def test_false_first_shot_shows_in_first_shot_error(self):
        summary = self.summary(clip([2.0, 2.3]), clip([1.5, 2.0, 2.3]))

        self.assertAlmostEqual(summary["first_shot_error"]["max_abs_ms"], 500)

    def test_missing_beep_skips_beep_metrics(self):
        summary = self.summary(clip([2.0]), clip([2.0], beep=None))

        self.assertIsNone(summary["beep_error"])
        self.assertIsNone(summary["first_shot_error"])

    def test_no_detections_scores_zero(self):
        summary = self.summary(clip([2.0]), clip([]))

        self.assertEqual(summary["precision"], 0)
        self.assertEqual(summary["recall"], 0)
        self.assertEqual(summary["f1"], 0)
        self.assertIsNone(summary["onset_error"])

    def test_summarize_weights_clips_by_shot_count(self):
        results = [
            evaluate.score_clip(clip([2.0, 3.0, 4.0]), clip([2.0, 3.0, 4.0]), 50),
            evaluate.score_clip(clip([2.0]), clip([]), 50),
        ]

        self.assertEqual(evaluate.summarize(results)["recall"], 0.75)


class CommandLineTest(unittest.TestCase):
    def setUp(self):
        directory = tempfile.TemporaryDirectory()
        self.addCleanup(directory.cleanup)
        self.root = Path(directory.name)
        self.truth = self.root / "truth"
        self.runs = self.root / "runs"
        self.truth.mkdir()
        self.runs.mkdir()
        self.history = self.root / "results.jsonl"

    def write(self, path, data):
        path.write_text(data if isinstance(data, str) else json.dumps(data))

    def run_cli(self, *extra):
        stdout, stderr = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
            code = evaluate.main(
                [
                    str(self.runs),
                    "--truth",
                    str(self.truth),
                    "--history",
                    str(self.history),
                    *extra,
                ]
            )
        return code, stdout.getvalue(), stderr.getvalue()

    def test_reports_and_records_a_labeled_run(self):
        self.write(self.truth / "a.json", clip([2.0, 3.0]))
        self.write(self.runs / "a.json", clip([2.0, 2.5, 3.0]))

        code, out, _ = self.run_cli("--label", "baseline")

        self.assertEqual(code, 0)
        self.assertIn("precision  0.667", out)
        entry = json.loads(self.history.read_text())
        self.assertEqual(entry["label"], "baseline")
        self.assertEqual(entry["tolerance_ms"], 50)
        self.assertEqual(entry["overall"]["false_positives"], 1)

    def test_unlabeled_runs_do_not_touch_history(self):
        self.write(self.truth / "a.json", clip([2.0]))
        self.write(self.runs / "a.json", clip([2.0]))

        self.run_cli()

        self.assertFalse(self.history.exists())

    def test_appends_each_revision(self):
        self.write(self.truth / "a.json", clip([2.0]))
        self.write(self.runs / "a.json", clip([2.0]))

        self.run_cli("--label", "one")
        self.run_cli("--label", "two")

        self.assertEqual(len(self.history.read_text().splitlines()), 2)

    def test_pools_multiple_clips_into_an_overall_score(self):
        for name, shots in (("a", [2.0, 3.0, 4.0]), ("b", [2.0])):
            self.write(self.truth / f"{name}.json", clip(shots))
        self.write(self.runs / "a.json", clip([2.0, 3.0, 4.0]))
        self.write(self.runs / "b.json", clip([]))

        code, out, _ = self.run_cli()

        self.assertEqual(code, 0)
        self.assertIn("overall", out)
        self.assertIn("recall     0.750", out)

    def test_fails_when_a_clip_has_no_detections_file(self):
        self.write(self.truth / "a.json", clip([2.0]))

        code, _, err = self.run_cli()

        self.assertEqual(code, 2)
        self.assertIn("missing detections", err)

    def test_fails_without_ground_truth(self):
        code, _, err = self.run_cli()

        self.assertEqual(code, 2)
        self.assertIn("no ground truth", err)

    def test_rejects_malformed_input(self):
        bad_files = {
            "not json": "{",
            "no shots": {"beep_s": 1.0},
            "negative time": clip([-1]),
            "text time": clip(["2.0"]),
            "boolean time": clip([True]),
        }
        for name, content in bad_files.items():
            with self.subTest(name):
                self.write(self.truth / "a.json", clip([2.0]))
                self.write(self.runs / "a.json", content)

                code, _, err = self.run_cli()

                self.assertEqual(code, 2)
                self.assertIn("a.json", err)

    def test_ground_truth_needs_a_beep_and_shots(self):
        for content in ({"shots_s": [2.0]}, clip([])):
            with self.subTest(content):
                self.write(self.truth / "a.json", content)
                self.write(self.runs / "a.json", clip([2.0]))

                code, _, err = self.run_cli()

                self.assertEqual(code, 2)
                self.assertIn("a.json", err)


if __name__ == "__main__":
    unittest.main()
