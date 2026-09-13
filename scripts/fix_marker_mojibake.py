#!/usr/bin/env python3
"""Fix UTF-8 mojibake in marker catalog JS files."""
from __future__ import annotations

import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[1]
PATHS = [
    ROOT / "public/assets/js/arma-marker-catalog.js",
    ROOT / "public/assets/js/arma-marker-library-index.js",
]

REPLACEMENTS = [
    ("Ã©", "é"),
    ("Ã¨", "è"),
    ("Ãª", "ê"),
    ("Ã ", "à"),
    ("Ã§", "ç"),
    ("Ã´", "ô"),
    ("Ã»", "û"),
    ("Ã®", "î"),
    ("Ã¶", "ö"),
    ("Ã¼", "ü"),
    ("Ã‰", "É"),
    ("Ã€", "À"),
    ("â€™", "'"),
    ("â€”", "—"),
    ("â€“", "–"),
    ("Â ", " "),
]


def main() -> None:
    for path in PATHS:
        if not path.exists():
            print(f"skip {path}")
            continue
        text = path.read_text(encoding="utf-8")
        before = len(re.findall(r"Ã.", text))
        for old, new in REPLACEMENTS:
            text = text.replace(old, new)
        after = len(re.findall(r"Ã.", text))
        path.write_text(text, encoding="utf-8", newline="\n")
        print(f"{path.name}: {before} -> {after}")


if __name__ == "__main__":
    main()
