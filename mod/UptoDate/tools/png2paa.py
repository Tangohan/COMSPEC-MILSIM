#!/usr/bin/env python3
"""
PNG -> PAA (Arma 3) en DXT5, sans outil BI : mipmaps jusqu'à 4x4, données non compressées (pas de LZO).

Usage :  python3 png2paa.py entree.png sortie.paa         (convertit)
         python3 png2paa.py --check fichier.paa apercu.png (relit un PAA DXT5 et le réécrit en PNG)

Dimensions : puissances de 2 (ex. 256x256). Dépendances : Pillow, numpy.
Équivalent officiel : Arma 3 Tools > ImageToPAA (préférable pour une texture de modèle ; ce script suffit pour
les insignes et icônes _ca). Le pack doit ensuite être rebuildé (build_mod.bat) pour que le PAA entre dans le PBO.
"""
import struct
import sys

import numpy as np
from PIL import Image

TYPE_DXT5 = 0xFF05


def _rgb565(c):
    c = c.astype(np.uint32)
    return ((c[..., 0] >> 3) << 11) | ((c[..., 1] >> 2) << 5) | (c[..., 2] >> 3)


def _expand565(v):
    r = ((v >> 11) & 31) * 255 // 31
    g = ((v >> 5) & 63) * 255 // 63
    b = (v & 31) * 255 // 31
    return np.stack([r, g, b], -1).astype(np.int32)


def dxt5_encode(img: np.ndarray) -> bytes:
    """img : HxWx4 uint8 (RGBA). Renvoie les blocs DXT5 (16 octets par bloc 4x4)."""
    h, w, _ = img.shape
    blocks = img.reshape(h // 4, 4, w // 4, 4, 4).transpose(0, 2, 1, 3, 4).reshape(-1, 16, 4).astype(np.int32)
    out = bytearray()
    for blk in blocks:
        a = blk[:, 3]
        # Alpha : mode 8 valeurs (a0 > a1).
        a0, a1 = int(a.max()), int(a.min())
        if a0 == a1:
            aidx = np.zeros(16, dtype=np.int64)
            if a0 == 255:
                a1 = 254 if a0 > 0 else 0
            else:
                a0 = min(255, a1 + 1)
        else:
            pal = np.array([a0, a1] + [((7 - i) * a0 + i * a1) // 7 for i in range(1, 7)])
            aidx = np.abs(a[:, None] - pal[None, :]).argmin(1)
        abits = 0
        for i, v in enumerate(aidx):
            abits |= int(v) << (3 * i)
        out += struct.pack('<BB', a0, a1) + abits.to_bytes(6, 'little')
        # Couleur : boîte englobante sur les pixels visibles.
        rgb = blk[:, :3]
        vis = rgb[a > 8] if (a > 8).any() else rgb
        cmax, cmin = vis.max(0), vis.min(0)
        c0, c1 = int(_rgb565(cmax)), int(_rgb565(cmin))
        if c0 < c1:
            c0, c1 = c1, c0
        if c0 == c1:
            cidx = np.zeros(16, dtype=np.int64)
        else:
            e0, e1 = _expand565(np.array(c0)), _expand565(np.array(c1))
            pal = np.stack([e0, e1, (2 * e0 + e1) // 3, (e0 + 2 * e1) // 3])
            cidx = ((rgb[:, None, :] - pal[None, :, :]) ** 2).sum(2).argmin(1)
        cbits = 0
        for i, v in enumerate(cidx):
            cbits |= int(v) << (2 * i)
        out += struct.pack('<HHI', c0, c1, cbits)
    return bytes(out)


def dxt5_decode(data: bytes, w: int, h: int) -> np.ndarray:
    img = np.zeros((h, w, 4), dtype=np.uint8)
    n = 0
    for by in range(h // 4):
        for bx in range(w // 4):
            blk = data[n:n + 16]
            n += 16
            a0, a1 = blk[0], blk[1]
            abits = int.from_bytes(blk[2:8], 'little')
            if a0 > a1:
                apal = [a0, a1] + [((7 - i) * a0 + i * a1) // 7 for i in range(1, 7)]
            else:
                apal = [a0, a1] + [((5 - i) * a0 + i * a1) // 5 for i in range(1, 5)] + [0, 255]
            c0, c1, cbits = struct.unpack('<HHI', blk[8:16])
            e0, e1 = _expand565(np.array(c0)), _expand565(np.array(c1))
            cpal = [e0, e1, (2 * e0 + e1) // 3, (e0 + 2 * e1) // 3] if c0 > c1 else [e0, e1, (e0 + e1) // 2, np.zeros(3, int)]
            for i in range(16):
                y, x = by * 4 + i // 4, bx * 4 + i % 4
                img[y, x, :3] = cpal[(cbits >> (2 * i)) & 3]
                img[y, x, 3] = apal[(abits >> (3 * i)) & 7]
    return img


def write_paa(src: str, dst: str) -> None:
    im = Image.open(src).convert('RGBA')
    w, h = im.size
    if w & (w - 1) or h & (h - 1) or w < 4 or h < 4:
        raise SystemExit('Dimensions en puissances de 2 attendues (ex. 256x256).')
    levels = []
    cur = im
    while True:
        levels.append(np.asarray(cur, dtype=np.uint8))
        if cur.size[0] <= 4 or cur.size[1] <= 4 or len(levels) == 16:
            break
        cur = cur.resize((cur.size[0] // 2, cur.size[1] // 2), Image.LANCZOS)
    arr = levels[0].astype(np.float64)
    alpha = arr[..., 3:4] / 255.0
    avg = (arr[..., :3] * alpha).sum((0, 1)) / max(1e-6, alpha.sum())
    avg_a = int(arr[..., 3].mean())
    # Tags : couleur moyenne et max (BGRA), drapeau de transparence, puis la table des 16 décalages.
    tags = b'GGATCGVA' + struct.pack('<I', 4) + bytes([int(avg[2]), int(avg[1]), int(avg[0]), avg_a])
    tags += b'GGATCXAM' + struct.pack('<I', 4) + b'\xff\xff\xff\xff'
    tags += b'GGATGALF' + struct.pack('<I', 4) + struct.pack('<I', 1 if avg_a < 255 else 0)
    head_len = 2 + len(tags) + 12 + 64 + 2  # type, tags, GGATSFFO + longueur + 16 décalages, taille de palette
    offsets, body, pos = [], bytearray(), head_len
    for lv in levels:
        lh, lw = lv.shape[:2]
        data = dxt5_encode(lv)
        offsets.append(pos)
        chunk = struct.pack('<HH', lw, lh) + len(data).to_bytes(3, 'little') + data
        body += chunk
        pos += len(chunk)
    offsets += [0] * (16 - len(offsets))
    out = struct.pack('<H', TYPE_DXT5) + tags + b'GGATSFFO' + struct.pack('<I', 64) + struct.pack('<16I', *offsets)
    out += struct.pack('<H', 0) + bytes(body) + struct.pack('<HH', 0, 0)
    assert len(out) - len(body) - 4 == head_len
    with open(dst, 'wb') as fh:
        fh.write(out)
    print(f'{dst} : DXT5 {w}x{h}, {len(levels)} niveaux, {len(out)} octets')


def read_paa(src: str, dst_png: str) -> None:
    raw = open(src, 'rb').read()
    typ = struct.unpack_from('<H', raw, 0)[0]
    if typ != TYPE_DXT5:
        raise SystemExit(f'type {typ:#x} non pris en charge par la relecture')
    p = 2
    while raw[p:p + 4] == b'GGAT':
        ln = struct.unpack_from('<I', raw, p + 8)[0]
        p += 12 + ln
    pal = struct.unpack_from('<H', raw, p)[0]
    p += 2 + pal * 3
    w, h = struct.unpack_from('<HH', raw, p)
    size = int.from_bytes(raw[p + 4:p + 7], 'little')
    if w & 0x8000:
        raise SystemExit('niveau compressé LZO : relecture non prise en charge')
    img = dxt5_decode(raw[p + 7:p + 7 + size], w, h)
    Image.fromarray(img, 'RGBA').save(dst_png)
    print(f'{src} relu : {w}x{h}, {size} octets au premier niveau -> {dst_png}')


if __name__ == '__main__':
    if len(sys.argv) == 4 and sys.argv[1] == '--check':
        read_paa(sys.argv[2], sys.argv[3])
    elif len(sys.argv) == 3:
        write_paa(sys.argv[1], sys.argv[2])
    else:
        print(__doc__)
        sys.exit(2)
