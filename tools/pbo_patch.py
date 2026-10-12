#!/usr/bin/env python3
"""Remplace ou ajoute des fichiers dans un PBO Arma 3 existant (sans AddonBuilder).

Le PBO garde son préfixe et ses autres fichiers ; seuls les fichiers donnés sont
réécrits (non compressés), puis la somme SHA-1 finale est recalculée.

    python3 tools/pbo_patch.py ADDON.pbo DOSSIER_SOURCE chemin/relatif.sqf [...]
    python3 tools/pbo_patch.py ADDON.pbo DOSSIER_SOURCE --changed
    python3 tools/pbo_patch.py ADDON.pbo DOSSIER_SOURCE --rm chemin/supprime.sqf [...]

Les chemins sont relatifs au dossier source de l'addon (ex. functions/fn_x.sqf).
--changed : tous les fichiers texte du dossier source (sqf, hpp, cpp, ext, inc)
absents du PBO ou dont le contenu diffère.
"""
import hashlib
import os
import struct
import sys
import time

TEXT_EXT = {".sqf", ".hpp", ".cpp", ".ext", ".inc", ".h", ".sqm"}
SKIP = {"$PBOPREFIX$"}


def read_pbo(path):
    data = open(path, "rb").read()
    pos = 0

    def cstr():
        nonlocal pos
        end = data.index(b"\0", pos)
        s = data[pos:end]
        pos = end + 1
        return s

    headers = []
    entries = []
    while True:
        name = cstr()
        mime, orig, res, ts, size = struct.unpack("<5I", data[pos:pos + 20])
        pos += 20
        if name == b"" and mime == 0x56657273:
            while True:
                k = cstr()
                if not k:
                    break
                headers.append((k, cstr()))
            continue
        if name == b"":
            break
        entries.append([name, mime, orig, res, ts, size])
    out = []
    for name, mime, orig, res, ts, size in entries:
        if mime not in (0, 0x43707273) or (mime == 0x43707273 and orig != size):
            raise SystemExit("PBO compressé non pris en charge : %s" % name.decode("latin1"))
        out.append([name, ts, data[pos:pos + size]])
        pos += size
    return headers, out


def write_pbo(path, headers, files):
    buf = bytearray()
    buf += b"\0" + struct.pack("<5I", 0x56657273, 0, 0, 0, 0)
    for k, v in headers:
        buf += k + b"\0" + v + b"\0"
    buf += b"\0"
    for name, ts, content in files:
        buf += name + b"\0" + struct.pack("<5I", 0, len(content), 0, ts, len(content))
    buf += b"\0" + struct.pack("<5I", 0, 0, 0, 0, 0)
    for _, _, content in files:
        buf += content
    digest = hashlib.sha1(bytes(buf)).digest()
    open(path, "wb").write(bytes(buf) + b"\0" + digest)


def main():
    if len(sys.argv) < 4:
        raise SystemExit(__doc__)
    pbo, src, wanted = sys.argv[1], sys.argv[2], sys.argv[3:]
    if os.path.exists(pbo):
        headers, files = read_pbo(pbo)
    else:
        # Nouveau PBO : préfixe lu dans $PBOPREFIX$ du dossier source.
        prefix = open(os.path.join(src, "$PBOPREFIX$"), "rb").read().strip()
        headers, files = [(b"prefix", prefix)], []
        print("nouveau PBO, préfixe", prefix.decode("latin1"))
    index = {f[0].decode("latin1").lower(): i for i, f in enumerate(files)}
    if wanted == ["--changed"]:
        wanted = []
        for root, _, names in os.walk(src):
            for n in names:
                rel = os.path.relpath(os.path.join(root, n), src)
                if os.path.splitext(n)[1].lower() not in TEXT_EXT:
                    continue
                content = open(os.path.join(src, rel), "rb").read()
                key = rel.replace("/", "\\").lower()
                if key not in index or files[index[key]][2] != content:
                    wanted.append(rel)
    if wanted and wanted[0] == "--rm":
        gone = {w.replace("/", "\\").lower() for w in wanted[1:]}
        kept = [f for f in files if f[0].decode("latin1").lower() not in gone]
        for f in files:
            if f[0].decode("latin1").lower() in gone:
                print("supprimé", f[0].decode("latin1"))
        write_pbo(pbo, headers, kept)
        return
    now = int(time.time())
    for rel in wanted:
        content = open(os.path.join(src, rel), "rb").read()
        name = rel.replace("/", "\\")
        key = name.lower()
        if key in index:
            files[index[key]][1] = now
            files[index[key]][2] = content
            print("remplacé", name)
        else:
            files.append([name.encode("latin1"), now, content])
            index[key] = len(files) - 1
            print("ajouté  ", name)
    write_pbo(pbo, headers, files)


if __name__ == "__main__":
    main()
