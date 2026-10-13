#!/usr/bin/env python3
"""Copie les en-têtes partagés du SSE dans chaque addon.

Arma lit les config.cpp non binarisés au lancement, PBO par PBO. Un #include
vers un autre PBO (\\z\\comspec_sse\\addons\\main\\script_mod.hpp) peut alors
échouer avec « Include file ... not found » si ce PBO n'est pas encore monté.
Chaque addon embarque donc sa propre copie :
  - main/script_mod.hpp  -> <addon>/script_mod.hpp   (tous les addons)
  - ui/ui_macros.hpp     -> <addon>/ui_macros.hpp    (zeus, analysis)

Modifier uniquement les originaux (main/script_mod.hpp, ui/ui_macros.hpp),
puis lancer : python tools/sync_headers.py   (--check : échoue si une copie diffère)
"""
import os
import sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "addons")
SHARED = [
    ("main/script_mod.hpp", None),
    ("ui/ui_macros.hpp", ["zeus", "analysis"]),
]
HEADER = "// COPIE GÉNÉRÉE de addons/{src} par tools/sync_headers.py : ne pas modifier ici.\n"


def main():
    check = "--check" in sys.argv
    addons = sorted(d for d in os.listdir(ROOT) if os.path.isfile(os.path.join(ROOT, d, "config.cpp")))
    stale = []
    for src, targets in SHARED:
        owner = src.split("/")[0]
        body = open(os.path.join(ROOT, src), encoding="utf-8").read()
        want = HEADER.format(src=src) + body
        for addon in targets or addons:
            if addon == owner:
                continue
            dst = os.path.join(ROOT, addon, os.path.basename(src))
            have = open(dst, encoding="utf-8").read() if os.path.exists(dst) else None
            if have == want:
                continue
            stale.append(dst)
            if not check:
                with open(dst, "w", encoding="utf-8", newline="\n") as f:
                    f.write(want)
    for p in stale:
        print(("périmé : " if check else "écrit : ") + os.path.relpath(p, ROOT))
    return 1 if check and stale else 0


if __name__ == "__main__":
    sys.exit(main())
