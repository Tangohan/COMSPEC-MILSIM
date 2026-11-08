"""Tests de session-stats.py (données inventées). Lancer : python3 -I -m unittest discover -s tools/ia -p test_*.py"""
import importlib.util
import os
import unittest

_spec = importlib.util.spec_from_file_location("session_stats", os.path.join(os.path.dirname(__file__), "session-stats.py"))
ss = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(ss)

C, F = ss.SEP_C, ss.SEP_F


def commit(date, body):
    return f"{date}{F}{body}{C}\n"


FAKE_LOG = (
    commit("2026-11-01T08:00:00+00:00", "feat: a\n\nCo-Authored-By: Claude Opus 5.5 <noreply@example.invalid>\nClaude-Session: https://claude.ai/code/session_AAAA1111BBBB")
    + commit("2026-11-01T10:30:00+00:00", "fix: b\n\nCo-Authored-By: Claude Opus 5.5 <noreply@example.invalid>\nClaude-Session: https://claude.ai/code/session_AAAA1111BBBB")
    + commit("2026-11-03T09:00:00+00:00", "fix: c\n\nCo-Authored-By: Claude Sonnet 5.5 <noreply@example.invalid>\nClaude-Session: https://claude.ai/code/session_CCCC2222DDDD")
    + commit("2026-11-04T09:00:00+00:00", "chore: humain, sans session")
)


class SessionStatsTest(unittest.TestCase):
    def setUp(self):
        self.commits = ss.parse_log(FAKE_LOG)
        self.stats = ss.summarize(self.commits)

    def test_parse_counts_commits(self):
        self.assertEqual(len(self.commits), 4)

    def test_sessions_and_commits_per_session(self):
        rows = self.stats["sessions"]
        self.assertEqual(len(rows), 2)
        self.assertEqual(rows[0]["id"], "AAAA1111")
        self.assertEqual(rows[0]["commits"], 2)
        self.assertEqual(rows[1]["commits"], 1)

    def test_duration_in_hours(self):
        self.assertAlmostEqual(self.stats["sessions"][0]["hours"], 2.5)
        self.assertEqual(self.stats["sessions"][1]["hours"], 0)

    def test_model_shares(self):
        m = self.stats["models"]
        self.assertEqual(m["Claude Opus 5.5"], 2)
        self.assertEqual(m["Claude Sonnet 5.5"], 1)
        self.assertEqual(m["sans signature Claude"], 1)

    def test_output_has_no_email(self):
        out = ss.render(self.stats, 30, 5)
        self.assertNotIn("@", out)
        self.assertNotIn("example.invalid", out)
        self.assertIn("Sessions sur 30 jours : 2 (4 commits au total)", out)
        self.assertIn("Claude Opus 5.5: 2 (50 %)", out)

    def test_empty_log(self):
        out = ss.render(ss.summarize(ss.parse_log("")), 7, 5)
        self.assertIn("Sessions sur 7 jours : 0", out)


if __name__ == "__main__":
    unittest.main()
