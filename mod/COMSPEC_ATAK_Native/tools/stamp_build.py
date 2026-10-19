"""Empreinte du build ATAK natif (docs/VERSIONING.md) : écrit la date et le commit dans build_info.hpp.

    python3 tools/stamp_build.py          # avant d'empaqueter main.pbo : BUILD_STAMP "2026.10.14 a1b2c3d"
    python3 tools/stamp_build.py --reset  # juste après : BUILD_STAMP "" (le dépôt ne garde jamais d'empreinte)

Le jeu affiche alors « 1.94.0 - 2026.10.14 a1b2c3d » (Réglages, Debug, journal). La date est en UTC, le commit est
celui du HEAD au moment du build (« local » sans git). build_mod.bat fait la même chose sans Python.
"""
from __future__ import annotations

import argparse
import datetime as dt
import pathlib
import re
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[1]
HPP = ROOT / "Sources" / "addons" / "main" / "build_info.hpp"
LINE_RE = re.compile(r'^#define BUILD_STAMP ".*"$', re.M)


def commit() -> str:
    try:
        out = subprocess.run(["git", "-C", str(ROOT), "rev-parse", "--short=7", "HEAD"],
                             capture_output=True, text=True, timeout=10)
        sha = out.stdout.strip()
        return sha if out.returncode == 0 and re.fullmatch(r"[0-9a-f]{7,}", sha) else "local"
    except (OSError, subprocess.SubprocessError):
        return "local"


def write(stamp: str) -> None:
    text = HPP.read_text(encoding="utf-8")
    if not LINE_RE.search(text):
        raise SystemExit(f"{HPP} : ligne #define BUILD_STAMP introuvable")
    HPP.write_text(LINE_RE.sub(f'#define BUILD_STAMP "{stamp}"', text, count=1), encoding="utf-8", newline="\n")


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--reset", action="store_true", help="remet BUILD_STAMP à vide (état du dépôt)")
    args = ap.parse_args()
    if args.reset:
        write("")
        print("BUILD_STAMP remis à vide")
        return
    stamp = f"{dt.datetime.now(dt.timezone.utc):%Y.%m.%d} {commit()}"
    write(stamp)
    print(stamp)


if __name__ == "__main__":
    main()
