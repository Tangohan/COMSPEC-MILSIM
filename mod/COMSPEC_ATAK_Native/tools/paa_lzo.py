#!/usr/bin/env python3
"""Compresse en LZO les mipmaps DXT d'un PAA écrit sans compression (paa_dxt.py sans python-lzo), sans réencoder.

Les blocs DXT restent ceux du fichier, octet pour octet : seule l'enveloppe change (largeur | 0x8000, taille du
mipmap, table OFFS). Un mipmap n'est compressé que si c'est plus court ; un PAA dont un mipmap est déjà compressé
reste tel quel.
« hemtt utils paa compress » décode puis réencode les blocs (écarts d'alpha jusqu'à 29 sur gw_frame) : à éviter ici.
Chaque mipmap compressé est relu (décompression LZO) et comparé à l'original avant l'écriture.

    python3 paa_lzo.py fichier.paa [...]       # réécrit les fichiers sur place, affiche le gain
    python3 paa_lzo.py --dry-run fichier.paa   # gain seulement

Dépendance : python-lzo (pip install python-lzo) ou, à défaut, lzallright (même flux LZO1X-1).
"""
import struct
import sys

try:
    import lzo

    def _pack(b):
        return lzo.compress(b, 1, False)

    def _unpack(b, n):
        return lzo.decompress(b, False, n)
except ImportError:
    import lzallright

    _LZ = lzallright.LZOCompressor()

    def _pack(b):
        return _LZ.compress(b)

    def _unpack(b, n):
        return _LZ.decompress(b, output_size_hint=n)

# Types DXT : octets par bloc 4 x 4.
_BLOCK = {0xFF01: 8, 0xFF02: 16, 0xFF03: 16, 0xFF04: 16, 0xFF05: 16}


def _parse(raw):
    kind = struct.unpack_from("<H", raw, 0)[0]
    pos, taggs = 2, []
    while raw[pos:pos + 4] == b"GGAT":
        name = raw[pos + 4:pos + 8]
        n = struct.unpack_from("<I", raw, pos + 8)[0]
        taggs.append((name, raw[pos + 12:pos + 12 + n]))
        pos += 12 + n
    pal = struct.unpack_from("<H", raw, pos)[0]
    palette = raw[pos:pos + 2 + pal * 3]
    pos += 2 + pal * 3
    mips = []
    while True:
        w, h = struct.unpack_from("<HH", raw, pos)
        if w == 0 and h == 0:
            break
        n = int.from_bytes(raw[pos + 4:pos + 7], "little")
        mips.append((w, h, raw[pos + 7:pos + 7 + n]))
        pos += 7 + n
    return kind, taggs, palette, mips, raw[pos:]


def compress(raw):
    """Octets du PAA recompressé (ou les mêmes octets s'il n'y a rien à gagner)."""
    kind, taggs, palette, mips, tail = _parse(raw)
    # Déjà passé par HEMTT (au moins un mipmap compressé) : ses petits mipmaps laissés bruts le restent.
    if kind not in _BLOCK or any(w & 0x8000 for w, _, _ in mips):
        return raw
    out_mips, changed = [], False
    for w, h, data in mips:
        if not (w & 0x8000):
            size = max(1, w // 4) * max(1, h // 4) * _BLOCK[kind]
            if len(data) == size:
                packed = _pack(data)
                if len(packed) < len(data):
                    if _unpack(packed, size) != data:
                        raise ValueError("relecture LZO différente")
                    out_mips.append((w | 0x8000, h, packed))
                    changed = True
                    continue
        out_mips.append((w, h, data))
    if not changed:
        return raw
    head = bytearray(struct.pack("<H", kind))
    offs_at = None
    for name, data in taggs:
        if name == b"SFFO":
            offs_at = len(head) + 12
        head += b"GGAT" + name + struct.pack("<I", len(data)) + data
    head += palette
    body, offsets = bytearray(), []
    for w, h, data in out_mips:
        offsets.append(len(head) + len(body))
        body += struct.pack("<HH", w, h) + len(data).to_bytes(3, "little") + data
    if offs_at is not None:
        for i, o in enumerate(offsets[:16]):
            head[offs_at + 4 * i:offs_at + 4 * i + 4] = struct.pack("<I", o)
    return bytes(head + body + tail)


def main(args):
    dry = "--dry-run" in args
    total = 0
    for path in [a for a in args if a != "--dry-run"]:
        with open(path, "rb") as f:
            raw = f.read()
        new = compress(raw)
        if len(new) < len(raw):
            total += len(raw) - len(new)
            print(f"{path}\t{len(raw)}\t{len(new)}\t{len(raw) - len(new)}")
            if not dry:
                with open(path, "wb") as f:
                    f.write(new)
    print("gain", total)


if __name__ == "__main__":
    main(sys.argv[1:])
