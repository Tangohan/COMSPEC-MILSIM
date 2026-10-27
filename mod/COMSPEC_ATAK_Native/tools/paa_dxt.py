#!/usr/bin/env python3
"""Écriture de textures Arma 3 (.paa, DXT5 avec alpha) sans HEMTT ni TexView.

Usage en module : from paa_dxt import save_paa ; save_paa(image_pillow_RGBA, "chemin.paa")
Usage en ligne de commande : python3 paa_dxt.py entree.png sortie.paa

Côtés en puissances de 2 (4 à 2048). Mipmaps jusqu'à 4 x 4, données DXT5 non compressées (pas de LZO), mêmes
TAGG que HEMTT (couleur moyenne, couleur max, drapeau alpha, table des mipmaps). Avec le module python-lzo
(pip install python-lzo), chaque mipmap est compressé en LZO1X comme ceux de HEMTT (largeur | 0x8000) : un 2048 x 2048
passe de 5,6 Mo à quelques centaines de Ko. Compression par bloc 4 x 4 :
couleurs extrêmes du bloc sur l'axe principal (RGB565, 4 niveaux), alpha sur 8 niveaux.
Dépendances : Pillow, numpy.
"""
import struct
import sys

import numpy as np
from PIL import Image

try:
    import lzo
except ImportError:
    lzo = None


def _565(c):
    c = np.clip(np.rint(c), 0, 255).astype(np.int32)
    return ((c[..., 0] >> 3) << 11) | ((c[..., 1] >> 2) << 5) | (c[..., 2] >> 3)


def _unpack565(v):
    r = ((v >> 11) & 31) * 255 // 31
    g = ((v >> 5) & 63) * 255 // 63
    b = (v & 31) * 255 // 31
    return np.stack([r, g, b], -1).astype(np.float32)


def _dxt5(rgba):
    """rgba : tableau (h, w, 4) uint8 -> octets DXT5."""
    h, w, _ = rgba.shape
    blocks = rgba.reshape(h // 4, 4, w // 4, 4, 4).transpose(0, 2, 1, 3, 4).reshape(-1, 16, 4).astype(np.float32)
    n = blocks.shape[0]
    # Alpha : a0 > a1, 8 niveaux interpolés.
    al = blocks[..., 3]
    a0 = al.max(1)
    a1 = al.min(1)
    same = a0 == a1
    a0 = np.where(same & (a0 < 255), a0 + 1, a0)
    a1 = np.where(same & (a0 >= 255), a1 - 1, a1)
    a1 = np.clip(a1, 0, 255)
    pal = np.stack([a0, a1] + [((7 - k) * a0 + k * a1) / 7 for k in range(1, 7)], 1)
    aidx = np.abs(al[:, :, None] - pal[:, None, :]).argmin(2).astype(np.uint64)
    abits = np.zeros(n, np.uint64)
    for i in range(16):
        abits |= aidx[:, i] << np.uint64(3 * i)
    # Couleur : axe principal du bloc (pixels pondérés par l'alpha pour ne pas viser le noir des zones transparentes).
    rgb = blocks[..., :3]
    wgt = (blocks[..., 3:4] / 255) + 0.02
    mean = (rgb * wgt).sum(1) / wgt.sum(1)
    d = (rgb - mean[:, None, :]) * wgt
    cov = np.einsum("nki,nkj->nij", d, d)
    axis = np.ones((n, 3), np.float32)
    for _ in range(6):
        axis = np.einsum("nij,nj->ni", cov, axis)
        axis /= np.linalg.norm(axis, axis=1, keepdims=True) + 1e-6
    proj = np.einsum("nki,ni->nk", rgb - mean[:, None, :], axis)
    lo = mean + axis * proj.min(1, keepdims=True)
    hi = mean + axis * proj.max(1, keepdims=True)
    c0 = _565(hi)
    c1 = _565(lo)
    swap = c0 < c1
    c0, c1 = np.where(swap, c1, c0), np.where(swap, c0, c1)
    # c0 == c1 : mode 3 couleurs à éviter, on garde 4 niveaux identiques (index 0 partout).
    p0, p1 = _unpack565(c0), _unpack565(c1)
    cpal = np.stack([p0, p1, (2 * p0 + p1) / 3, (p0 + 2 * p1) / 3], 1)
    cidx = ((rgb[:, :, None, :] - cpal[:, None, :, :]) ** 2).sum(3).argmin(2).astype(np.uint32)
    cidx = np.where((c0 == c1)[:, None], 0, cidx)
    cbits = np.zeros(n, np.uint32)
    for i in range(16):
        cbits |= cidx[:, i] << np.uint32(2 * i)
    out = bytearray()
    for i in range(n):
        out += struct.pack("<BB", int(a0[i]), int(a1[i]))
        out += int(abits[i]).to_bytes(6, "little")
        out += struct.pack("<HHI", int(c0[i]), int(c1[i]), int(cbits[i]))
    return bytes(out)


def save_paa(img, path):
    img = img.convert("RGBA")
    w, h = img.size
    assert w & (w - 1) == 0 and h & (h - 1) == 0 and min(w, h) >= 4, "côtés en puissances de 2"
    arr = np.asarray(img)
    avg = arr.reshape(-1, 4).astype(np.float64)
    avg_c = [int(v) for v in (avg[:, :3] * avg[:, 3:4] / 255).sum(0) / max(1, (avg[:, 3] / 255).sum())] + [int(avg[:, 3].mean())]
    max_c = [int(v) for v in arr.reshape(-1, 4).max(0)]
    mips = []
    cur = img
    while True:
        mips.append(cur)
        if min(cur.size) <= 4 or len(mips) >= 15:
            break
        cur = cur.resize((max(4, cur.size[0] // 2), max(4, cur.size[1] // 2)), Image.LANCZOS)
    head = bytearray(struct.pack("<H", 0xFF05))

    def tagg(name, data):
        return b"GGAT" + name[::-1].encode() + struct.pack("<I", len(data)) + data

    # Couleurs en BGRA, comme TexView.
    head += tagg("AVGC", bytes([avg_c[2], avg_c[1], avg_c[0], avg_c[3]]))
    head += tagg("MAXC", bytes([max_c[2], max_c[1], max_c[0], max_c[3]]))
    head += tagg("FLAG", struct.pack("<I", 1))
    offs_pos = len(head) + 12
    head += tagg("OFFS", bytes(64))
    head += struct.pack("<H", 0)
    body = bytearray()
    offsets = []
    for m in mips:
        offsets.append(len(head) + len(body))
        data = _dxt5(np.asarray(m))
        mw = m.size[0]
        if lzo is not None:
            packed = lzo.compress(bytes(data), 1, False)
            if len(packed) < len(data):
                data, mw = packed, mw | 0x8000
        body += struct.pack("<HH", mw, m.size[1]) + len(data).to_bytes(3, "little") + data
    body += bytes(6)
    for i, o in enumerate(offsets[:16]):
        head[offs_pos + 4 * i:offs_pos + 4 * i + 4] = struct.pack("<I", o)
    with open(path, "wb") as f:
        f.write(head + body)


if __name__ == "__main__":
    save_paa(Image.open(sys.argv[1]), sys.argv[2])
