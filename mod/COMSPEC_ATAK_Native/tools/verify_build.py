#!/usr/bin/env python3
"""Contrôle de fin de build : les PBO de @COMSPEC_ATAK_Native/addons sont-ils les bons ?

    python tools/verify_build.py <dossier addons> [--retire <dossier>]

--retire : déplace d'abord dans <dossier> tout PBO hors des cinq attendus (build_mod.bat le fait).

Pour chaque PBO : taille, empreinte SHA-256, nombre de fichiers et $PBOPREFIX$. Échoue (code 1) si :
- un PBO attendu manque, ou un PBO inattendu traîne (ex. comspec_atak_native_main.pbo laissé par HEMTT :
  deux PBO du même addon, Arma charge l'ancien, plus de journal) ;
- deux PBO portent le même préfixe (doublon) ou un préfixe n'est pas celui attendu ;
- un PBO contient un fichier absent des sources (dossier temporaire périmé : ancien modèle supprimé,
  ou copie de l'ATAK dans comspec_overwatch_main.pbo).
Un fichier des sources absent du PBO n'est qu'un avertissement (AddonBuilder ignore certains types).

Accepte aussi les PBO de HEMTT (config.bin, *.sqfc, stringtable.bin) pour vérifier ceux du dépôt.
"""
from __future__ import annotations

import hashlib
import pathlib
import shutil
import struct
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
OW = ROOT.parent / "UptoDate" / "Sources" / "comspec-overwatch-addons"

# nom du PBO -> (dossier source, préfixe)
EXPECTED = {
    "main.pbo": (ROOT / "Sources" / "addons" / "main", r"z\comspec_atak_native\addons\main"),
    "towers.pbo": (ROOT / "Sources" / "addons" / "towers", r"z\comspec_atak_native\addons\towers"),
    "items.pbo": (ROOT / "Sources" / "addons" / "items", r"z\comspec_atak_native\addons\items"),
    "comspec_overwatch_main.pbo": (OW / "main", r"z\comspec_overwatch\addons\main"),
    "comspec_overwatch_connect.pbo": (OW / "connect", r"z\comspec_overwatch\addons\connect"),
}
# bis_addoninfo.hpp : ajouté par AddonBuilder à chaque PBO, absent des sources par nature.
SKIP_NAMES = {"$pboprefix$", "$pboprefix$.txt", "thumbs.db", ".ds_store", ".gitkeep", "texheaders.bin", "bis_addoninfo.hpp"}


def read_pbo(path: pathlib.Path) -> tuple[dict[str, str], list[str]]:
    data = path.read_bytes()
    pos = 0
    props: dict[str, str] = {}
    names: list[str] = []

    def cstr() -> str:
        nonlocal pos
        end = data.index(b"\0", pos)
        s = data[pos:end].decode("utf-8", "replace")
        pos = end + 1
        return s

    while True:
        name = cstr()
        packing = struct.unpack_from("<5I", data, pos)[0]
        pos += 20
        if not name and packing == 0x56657273:
            while key := cstr():
                props[key] = cstr()
            continue
        if not name:
            return props, names
        names.append(name)


def source_files(src: pathlib.Path) -> set[str]:
    out = set()
    for f in src.rglob("*"):
        rel = f.relative_to(src)
        if f.is_file() and not any(p.startswith(".") for p in rel.parts) and f.name.lower() not in SKIP_NAMES:
            out.add(rel.as_posix().lower())
    return out


def compare(names: list[str], src: pathlib.Path) -> tuple[list[str], list[str]]:
    packed = {n.replace("\\", "/").lower() for n in names}
    packed -= SKIP_NAMES
    sources = source_files(src)
    covered: set[str] = set()
    stale = []
    for n in sorted(packed):
        stem, ext = n.rsplit(".", 1) if "." in n else (n, "")
        # HEMTT : config.cpp -> config.bin, stringtable.xml -> stringtable.bin, fn_x.sqf -> fn_x.sqfc
        candidates = {n}
        if ext == "bin":
            candidates |= {stem + ".cpp", stem + ".xml"}
        elif ext == "sqfc":
            candidates.add(stem + ".sqf")
        hit = candidates & sources
        if hit:
            covered |= hit
        else:
            stale.append(n)
    binarized = "config.bin" in packed
    missing = [s for s in sorted(sources - covered)
               if not (binarized and s.rsplit(".", 1)[-1] in ("hpp", "h", "inc"))]
    return stale, missing


def main() -> int:
    args = sys.argv[1:]
    if len(args) not in (1, 3) or (len(args) == 3 and args[1] != "--retire"):
        print(__doc__)
        return 2
    addons = pathlib.Path(args[0])
    if len(args) == 3:
        retire = pathlib.Path(args[2])
        for p in addons.glob("*.pbo"):
            if p.name.lower() not in EXPECTED:
                retire.mkdir(parents=True, exist_ok=True)
                shutil.move(str(p), str(retire / p.name))
                print(f"[INFO] PBO inattendu retiré de addons : {p.name} -> {retire}")
    errors: list[str] = []
    warnings: list[str] = []
    pbos = sorted(addons.glob("*.pbo"), key=lambda p: p.name.lower())
    prefixes: dict[str, str] = {}
    print(f"[VERIF] {addons}")
    print(f"  {'PBO':<32}{'taille':>14}  {'SHA-256':<16}  {'fichiers':>8}  préfixe")
    for p in pbos:
        try:
            props, names = read_pbo(p)
        except (ValueError, struct.error) as e:
            errors.append(f"{p.name} illisible ({e})")
            continue
        prefix = props.get("prefix", "").strip("\\")
        sha = hashlib.sha256(p.read_bytes()).hexdigest()[:16]
        print(f"  {p.name:<32}{p.stat().st_size:>14,}  {sha}  {len(names):>8}  {prefix}".replace(",", " "))
        if prefix.lower() in prefixes:
            errors.append(f"DOUBLON : {p.name} et {prefixes[prefix.lower()]} ont le même préfixe {prefix}")
        prefixes[prefix.lower()] = p.name
        exp = EXPECTED.get(p.name.lower())
        if exp is None:
            errors.append(f"PBO inattendu : {p.name} (à retirer de addons)")
            continue
        src, want = exp
        if prefix.lower() != want.lower():
            errors.append(f"{p.name} : préfixe {prefix or '(aucun)'} au lieu de {want}")
        stale, missing = compare(names, src)
        if stale:
            errors.append(f"{p.name} : {len(stale)} fichier(s) absents des sources (build périmé), ex. "
                          + ", ".join(stale[:5]))
        if missing:
            warnings.append(f"{p.name} : {len(missing)} fichier(s) des sources absents du PBO, ex. "
                          + ", ".join(missing[:5]))
    present = {p.name.lower() for p in pbos}
    for name in EXPECTED:
        if name not in present:
            errors.append(f"PBO attendu manquant : {name}")
    for w in warnings:
        print(f"[ATTENTION] {w}")
    if errors:
        for e in errors:
            print(f"[ERREUR] {e}")
        return 1
    print(f"[OK] {len(pbos)} PBO conformes aux sources, aucun doublon.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
