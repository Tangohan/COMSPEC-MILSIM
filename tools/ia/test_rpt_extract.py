"""Tests de rpt-extract.py (données inventées). Lancer : python3 -I -m unittest discover -s tools/ia -p "test_*.py""""
import importlib.util
import os
import unittest

_spec = importlib.util.spec_from_file_location("rpt_extract", os.path.join(os.path.dirname(__file__), "rpt-extract.py"))
rpt = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(rpt)

FAKE_RPT = """\
 8:00:01 COMSPEC ATAK 9.9.9.ZZ - Test started
 8:00:02 Mission file: mission_inventee.Altis
 8:00:03 Connexion de 203.0.113.7:2302 pour joueur test@example.invalid id 76561190000000001
 8:01:10 Error in expression <_x = _unit getVariable "foo"; _x + 1>
 8:01:10   Error position: <_x + 1>
 8:01:10   Error Undefined variable in expression: _x
 8:01:10 File comspec\\addons\\main\\fn_demo.sqf..., line 42
 8:02:00 Error in expression <_y / 0>
 8:02:00   Error position: <_y / 0>
 8:02:00   Error Zero divisor
 8:02:00 File comspec\\addons\\main\\fn_autre.sqf..., line 7
 8:03:00 Error in expression <_x + 1>
 8:03:00   Error position: <_x + 1>
 8:03:00   Error Undefined variable in expression: _x
 8:03:00 File comspec\\addons\\main\\fn_demo.sqf..., line 42
"""


class RptExtractTest(unittest.TestCase):
    def setUp(self):
        self.info = rpt.extract(FAKE_RPT.splitlines())

    def test_version_and_mission(self):
        self.assertEqual(self.info["version"], "9.9.9.ZZ")
        self.assertEqual(self.info["mission"], "mission_inventee.Altis")

    def test_errors_grouped_and_counted(self):
        self.assertEqual(self.info["total"], 3)
        self.assertEqual(len(self.info["errors"]), 2)
        key = ("Undefined variable in expression: _x", "comspec\\addons\\main\\fn_demo.sqf:42")
        entry = self.info["errors"][key]
        self.assertEqual(entry["count"], 2)
        self.assertEqual(entry["line"], 4)
        self.assertEqual(entry["time"], "8:01:10")

    def test_card_contains_sqf_file(self):
        card = rpt.render(self.info)
        self.assertIn("x2 Undefined variable", card)
        self.assertIn("fn_demo.sqf:42", card)
        self.assertIn("x1 Zero divisor", card)

    def test_scrub_removes_personal_data(self):
        text = "ip 203.0.113.7:2302 mail test@example.invalid steam 76561190000000001 fin"
        out = rpt.scrub(text)
        self.assertNotIn("203.0.113.7", out)
        self.assertNotIn("test@example.invalid", out)
        self.assertNotIn("76561190000000001", out)
        self.assertIn("<ip>", out)
        self.assertIn("<email>", out)
        self.assertIn("<steamid>", out)

    def test_scrub_keeps_other_numbers(self):
        self.assertEqual(rpt.scrub("id 12345678901234567 v2.0.1"), "id 12345678901234567 v2.0.1")

    def test_card_has_no_personal_data(self):
        rpt_text = FAKE_RPT + " 8:04:00 Error in expression <x 76561190000000002 a@b.invalid 198.51.100.4>\n"
        card = rpt.render(rpt.extract(rpt_text.splitlines()))
        for secret in ("76561190000000002", "a@b.invalid", "198.51.100.4"):
            self.assertNotIn(secret, card)

    def test_version_not_taken_for_ip(self):
        info = rpt.extract([" 8:00:01 COMSPEC ATAK 2.0.1.12 started"])
        self.assertIn("Mod : 2.0.1.12", rpt.render(info))

    def test_empty_rpt(self):
        card = rpt.render(rpt.extract([]))
        self.assertIn("non trouvée", card)
        self.assertIn("erreurs de script : 0", card)


if __name__ == "__main__":
    unittest.main()
