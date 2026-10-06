#!/usr/bin/env python3
"""Pack an addon folder into a PBO without binarizing, like AddonBuilder -packonly.

Used to bundle Overwatch (main + connect) into @COMSPEC_ATAK_Native from Linux,
where Arma 3 Tools are not available. Arma reads text config.cpp files fine.

Usage: pack_pbo.py <addon_dir> <out.pbo> [prefix]
The prefix defaults to the content of $PBOPREFIX$ in the addon folder.
"""
import hashlib
import os
import struct
import sys

SKIP_NAMES = {"$PBOPREFIX$", "$PBOPREFIX$.txt", ".gitkeep", "Thumbs.db", ".DS_Store"}


def main():
    src, out = sys.argv[1], sys.argv[2]
    prefix = sys.argv[3] if len(sys.argv) > 3 else ""
    if not prefix:
        pf = os.path.join(src, "$PBOPREFIX$")
        if os.path.exists(pf):
            prefix = open(pf, encoding="utf-8-sig").read().strip()
    files = []
    for root, dirs, names in os.walk(src):
        dirs[:] = sorted(d for d in dirs if not d.startswith("."))
        for n in sorted(names):
            if n in SKIP_NAMES or n.startswith("."):
                continue
            full = os.path.join(root, n)
            rel = os.path.relpath(full, src).replace("/", "\\")
            files.append((rel, full))
    files.sort(key=lambda f: f[0].lower())
    head = bytearray()
    head += b"\0" + struct.pack("<5I", 0x56657273, 0, 0, 0, 0)
    if prefix:
        head += b"prefix\0" + prefix.encode("utf-8") + b"\0"
    head += b"\0"
    blobs = []
    for rel, full in files:
        data = open(full, "rb").read()
        ts = int(os.path.getmtime(full))
        head += rel.encode("utf-8") + b"\0" + struct.pack("<5I", 0, len(data), 0, ts, len(data))
        blobs.append(data)
    head += b"\0" + struct.pack("<5I", 0, 0, 0, 0, 0)
    body = bytes(head) + b"".join(blobs)
    with open(out, "wb") as f:
        f.write(body)
        f.write(b"\0" + hashlib.sha1(body).digest())
    print(f"{out}: {len(files)} files, prefix {prefix}")


if __name__ == "__main__":
    main()
