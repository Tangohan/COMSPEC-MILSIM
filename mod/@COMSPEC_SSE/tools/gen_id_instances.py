#!/usr/bin/env python3
"""Écrit addons/identity/CfgWeapons_instances.hpp : une classe d'objet par exemplaire de document.

Arma ne garde aucune donnée sur un objet d'inventaire : chaque exemplaire matérialisé prend une classe libre
(COMSPEC_ID_Card_001…) que le registre du serveur (fn_idRegistry) relie à son document. Même nom et même icône que
la classe du catalogue (héritage), scope = 1 : invisibles dans l'Arsenal et le Zeus.

  python tools/gen_id_instances.py          # régénère le fichier (versionné)
  python tools/gen_id_instances.py --check  # échoue si le fichier versionné diffère
Stock (décision Lot 1) : 512 cartes, 256 passeports, 256 permis, 256 cartes militaires. Changer aussi STOCK dans fn_idRegistry.sqf.
"""
import os
import sys

STOCK = [("COMSPEC_ID_Card", 512), ("COMSPEC_ID_Passport", 256), ("COMSPEC_ID_DrivingLicense", 256), ("COMSPEC_ID_MilitaryCard", 256)]
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "addons", "identity", "CfgWeapons_instances.hpp")


def build():
    lines = ["// GÉNÉRÉ par tools/gen_id_instances.py : ne pas modifier à la main.",
             "// Exemplaires de documents (classe libre par exemplaire, reliée au document par le registre du serveur)."]
    for base, n in STOCK:
        for i in range(1, n + 1):
            lines.append(f"class {base}_{i:03d}: {base} {{ scope = 1; scopeArsenal = 0; scopeCurator = 0; }};")
    return "\n".join(lines) + "\n"


def main():
    want = build()
    if "--check" in sys.argv:
        have = open(OUT, encoding="utf-8").read() if os.path.exists(OUT) else ""
        if have != want:
            print("CfgWeapons_instances.hpp périmé : lancez python tools/gen_id_instances.py")
            sys.exit(1)
        return
    with open(OUT, "w", encoding="utf-8", newline="\n") as fh:
        fh.write(want)
    print(f"{OUT} : {sum(n for _, n in STOCK)} classes")


if __name__ == "__main__":
    main()
