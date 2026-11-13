#!/usr/bin/env python3
"""Écrit addons/identity/CfgWeapons_instances.hpp : une classe d'objet par exemplaire de document.

Arma ne garde aucune donnée sur un objet d'inventaire : chaque exemplaire matérialisé prend une classe libre
(COMSPEC_ID_Card_001…) que le registre du serveur (fn_idRegistry) relie à son document. Même nom et même icône que
la classe du catalogue (héritage), scope = 1 : invisibles dans l'Arsenal et le Zeus.

  python tools/gen_id_instances.py          # régénère le fichier (versionné)
  python tools/gen_id_instances.py --check  # échoue si le fichier versionné diffère
Stock (décision Lot 1) : 512 cartes, 256 passeports, 256 permis, 256 cartes militaires ; pays réels : 128 titres de séjour,
128 certificats d'immatriculation. Changer aussi STOCK dans fn_idRegistry.sqf.

Extension de documents (addon tiers, voir extensions/example_documents/README.md) : même principe, dans son dossier.
  python tools/gen_id_instances.py --extension extensions/example_documents --stock COMSPEC_EXT_PressCard=64,COMSPEC_EXT_LaissezPasser=64
    -> écrit <dossier>/CfgWeapons_instances.hpp (à inclure dans class CfgWeapons du config.cpp de l'extension, après les
       classes vierges). Le registre compte les exemplaires en lisant les classes <item>_001… (fn_idDocTypes « stock »).
"""
import os
import sys

STOCK = [("COMSPEC_ID_Card", 512), ("COMSPEC_ID_Passport", 256), ("COMSPEC_ID_DrivingLicense", 256), ("COMSPEC_ID_MilitaryCard", 256),
         ("COMSPEC_ID_ResidencePermit", 128), ("COMSPEC_ID_VehicleRegistration", 128)]
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "addons", "identity", "CfgWeapons_instances.hpp")


def build(stock):
    lines = ["// GÉNÉRÉ par tools/gen_id_instances.py : ne pas modifier à la main.",
             "// Exemplaires de documents (classe libre par exemplaire, reliée au document par le registre du serveur)."]
    for base, n in stock:
        for i in range(1, n + 1):
            lines.append(f"class {base}_{i:03d}: {base} {{ scope = 1; scopeArsenal = 0; scopeCurator = 0; }};")
    return "\n".join(lines) + "\n"


def parse_stock(text):
    """'A=64,B=32' -> [("A", 64), ("B", 32)]"""
    out = []
    for part in text.split(","):
        part = part.strip()
        if not part:
            continue
        name, _, n = part.partition("=")
        if not name.strip() or not n.strip().isdigit():
            sys.exit(f"--stock : attendu Classe=nombre, reçu « {part} »")
        out.append((name.strip(), int(n)))
    return out


def arg(name):
    if name in sys.argv:
        i = sys.argv.index(name)
        if i + 1 < len(sys.argv):
            return sys.argv[i + 1]
        sys.exit(f"{name} : valeur manquante")
    return None


def main():
    stock = STOCK
    out = OUT
    ext = arg("--extension")
    if ext:
        if not os.path.isdir(ext):
            sys.exit(f"--extension : dossier introuvable « {ext} »")
        stock_arg = arg("--stock")
        if not stock_arg:
            sys.exit("--extension demande --stock Classe=nombre[,Classe=nombre…]")
        stock = parse_stock(stock_arg)
        out = os.path.join(ext, "CfgWeapons_instances.hpp")
    want = build(stock)
    if "--check" in sys.argv:
        have = open(out, encoding="utf-8").read() if os.path.exists(out) else ""
        if have != want:
            print(f"{out} périmé : lancez python tools/gen_id_instances.py" + (f" --extension {ext} --stock {arg('--stock')}" if ext else ""))
            sys.exit(1)
        return
    with open(out, "w", encoding="utf-8", newline="\n") as fh:
        fh.write(want)
    print(f"{out} : {sum(n for _, n in stock)} classes")


if __name__ == "__main__":
    main()
