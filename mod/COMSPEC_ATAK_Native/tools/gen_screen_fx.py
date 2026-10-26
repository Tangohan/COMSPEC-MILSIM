#!/usr/bin/env python3
"""Textures en couleur de l'écran du téléphone : sang réaliste et variantes de dégâts / brouillage (fn_screenSurface,
fn_screenFx).

Usage : python3 gen_screen_fx.py [dossier d'aperçus PNG]
Sortie : Sources/addons/main/data/bloodc_*.paa, fx_*.paa (DXT5, tools/paa_dxt.py : ni HEMTT ni TexView).
Dépendances : Pillow, numpy.

Sang (bloodc_<genre>_<n>) : les masques de gen_dirt.py (éclaboussure, main, coulure, projection, nappe, traînée, doigt)
mis en couleur au lieu d'une teinte unique : film mince rouge vif et translucide, cœur épais bordeaux presque opaque,
bord plus sombre (sang qui sèche en premier), caillots, reflets humides là où la goutte est bombée. Le jeu les teinte en
blanc (frais) puis vers le brun (sang séché) avec ctrlSetTextColor.
Dégâts : fx_bleed_<n> (fuite de cristaux liquides : tache noire, franges violettes et vertes), fx_backlight_<n>_<port|land>
(fuite de rétroéclairage : halos blancs chauds contre les bords), fx_mura_<n> (arc-en-ciel de pression autour d'un choc),
fx_band_<n> (bandes de lignes mortes colorées).
Brouillage : fx_emi_<n> (4 trames de parasites : lignes de balayage, bandes déchirées, neige colorée).
"""
import math
import os
import random
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
sys.argv = sys.argv[:1] + ["hemtt"] + sys.argv[1:]
import gen_dirt  # noqa: E402  (masques de sang existants)
from paa_dxt import save_paa  # noqa: E402

OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
PREVIEW = sys.argv[2] if len(sys.argv) > 2 else None


def save(img, name):
    save_paa(img, os.path.join(OUT, name + ".paa"))
    if PREVIEW:
        os.makedirs(PREVIEW, exist_ok=True)
        bg = Image.new("RGBA", img.size, (38, 44, 41, 255))
        bg.alpha_composite(img)
        bg.convert("RGB").save(os.path.join(PREVIEW, name + ".png"))


def smooth(a, r):
    return np.asarray(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L").filter(ImageFilter.GaussianBlur(r)), dtype=np.float32) / 255


def colorize_blood(mask_img, seed, film=0.55):
    """Masque blanc (alpha = quantité de sang) -> sang en couleur.
    Bords nets (le sang sur du verre ne fait pas de flou) ; l'épaisseur vient de la distance au bord : film mince
    près du bord, rouge vif et translucide (l'écran éclaire au travers), cœur épais bordeaux presque opaque ; anneau
    plus sombre sur le pourtour (le bord sèche en premier) ; caillots ; petits reflets humides sur le sang épais."""
    rnd = random.Random(seed)
    t = np.asarray(mask_img.split()[-1], dtype=np.float32) / 255
    h, w = t.shape
    px = max(1.0, w / 256)
    # Couverture aux bords nets : seuil doux sur le masque.
    cov = np.clip((t - 0.22) / 0.16, 0, 1)
    cov = smooth(cov, 0.6 * px)
    # Épaisseur : érosions successives (distance au bord approchée) mélangées à la quantité du masque.
    m = Image.fromarray(np.uint8(cov * 255), "L")
    acc = np.zeros_like(t)
    for k in range(1, 7):
        m = m.filter(ImageFilter.MinFilter(3))
        acc += np.asarray(m, dtype=np.float32) / 255
    thick = np.clip(smooth(acc / 6, 1.5 * px) * 0.75 + t * 0.35, 0, 1)
    ring = np.clip(cov - np.asarray(Image.fromarray(np.uint8(cov * 255), "L").filter(ImageFilter.MinFilter(3)), dtype=np.float32) / 255, 0, 1)
    clot = gen_dirt.blood_noise(w, h, seed + 3, 5, 1.6)
    thin_c = np.array([214, 34, 26], np.float32)
    thick_c = np.array([104, 6, 9], np.float32)
    rim_c = np.array([72, 10, 7], np.float32)
    clot_c = np.array([44, 3, 4], np.float32)
    k = np.clip(thick * 1.4, 0, 1)[..., None]
    col = thin_c * (1 - k) + thick_c * k
    r = np.clip(ring * 1.3, 0, 1)[..., None]
    col = col * (1 - r) + rim_c * r
    cm = smooth(np.clip((clot - 0.68) * 3, 0, 1), 1.2) * np.clip(thick * 1.5, 0, 1)
    col = col * (1 - cm[..., None] * 0.45) + clot_c * (cm[..., None] * 0.45)
    # Reflets humides : pente de l'épaisseur éclairée d'en haut à gauche, à l'intérieur du sang épais seulement.
    core = np.clip((thick - 0.45) * 3, 0, 1)
    gy, gx = np.gradient(smooth(thick, max(2.0, w / 64)))
    light = np.clip(-(gx * 0.7 + gy * 0.7) * (w / 5), 0, 1) ** 3 * core
    spec = np.zeros_like(t)
    yy, xx = np.mgrid[0:h, 0:w]
    for _ in range(rnd.randint(1, 3)):
        x, y = rnd.uniform(0.25, 0.75) * w, rnd.uniform(0.25, 0.75) * h
        rr = rnd.uniform(0.006, 0.014) * w
        spec += np.exp(-(((xx - x) ** 2 + (yy - y) ** 2) / (2 * rr * rr))) * core
    gloss = np.clip(light * 0.3 + spec * 0.55, 0, 1)[..., None]
    col = col * (1 - gloss) + np.array([238, 176, 166], np.float32) * gloss
    # Opacité : film mince translucide, cœur presque opaque, anneau du bord bien visible.
    alpha = cov * np.clip(film * 0.8 + (1 - film * 0.8) * np.clip(thick * 1.5, 0, 1) + ring * 0.3, 0, 1)
    alpha = np.maximum(alpha, gloss[..., 0] * cov)
    rgba = np.dstack([np.clip(col, 0, 255), alpha * 255]).astype(np.uint8)
    return Image.fromarray(rgba, "RGBA")


def blood():
    gens = [
        ("splat", lambda i: gen_dirt.splat(42 + i).resize((256, 256), Image.BICUBIC), 2),
        ("hand", lambda i: gen_dirt.hand(61 + i), 2),
        ("drip", lambda i: gen_dirt.drip(71 + i), 2),
        ("spatter", lambda i: gen_dirt.spatter(81 + i), 2),
        ("pool", lambda i: gen_dirt.pool(91 + i), 2),
        ("smear", lambda i: gen_dirt.smear(31 + i), 3),
        ("print", lambda i: gen_dirt.fingerprint(21 + i), 4),
    ]
    for kind, fn, n in gens:
        for i in range(n):
            m = fn(i)
            sz = m.size
            # Côtés en puissances de 2.
            p2 = tuple(1 << max(2, (s - 1).bit_length()) for s in sz)
            if p2 != sz:
                m = m.resize(p2, Image.BICUBIC)
            film = {"spatter": 0.8, "print": 0.45, "smear": 0.4}.get(kind, 0.55)
            save(colorize_blood(m, 300 + i * 7 + len(kind), film), f"bloodc_{kind}_{i}")


def bleed(seed):
    """Fuite de cristaux liquides : tache noire irrégulière qui s'étale, franges violettes / vertes / bleues."""
    rnd = random.Random(seed)
    s = 256
    yy, xx = np.mgrid[0:s, 0:s].astype(np.float32)
    n = gen_dirt.blood_noise(s, s, seed, 9, 5)
    cx, cy = s / 2 + rnd.uniform(-10, 10), s / 2 + rnd.uniform(-10, 10)
    r = np.sqrt((xx - cx) ** 2 + (yy - cy) ** 2) / (s * 0.30)
    # Doigts d'encre (le cristal coule le long des fêlures).
    ang = np.arctan2(yy - cy, xx - cx)
    fingers = sum(np.cos(ang * rnd.randint(3, 7) + rnd.uniform(0, 6)) * rnd.uniform(0.08, 0.18) for _ in range(3))
    f = r - fingers - (n - 0.5) * 0.9
    core = np.clip((1 - f) * 3.5, 0, 1)
    halo = np.clip(1 - np.abs(f - 1.08) * 9, 0, 1)
    halo2 = np.clip(1 - np.abs(f - 1.22) * 10, 0, 1) * 0.5
    col = np.zeros((s, s, 3), np.float32)
    col += np.array([4, 2, 8], np.float32) * core[..., None]
    col += np.array([150, 40, 210], np.float32) * halo[..., None]
    col += np.array([40, 210, 120], np.float32) * halo2[..., None]
    blue = np.clip(1 - np.abs(f - 0.98) * 12, 0, 1) * 0.6
    col += np.array([30, 60, 220], np.float32) * blue[..., None]
    alpha = np.clip(core * 0.97 + halo * 0.6 + halo2 * 0.4 + blue * 0.5, 0, 1)
    tot = np.maximum(core + halo + halo2 + blue, 1e-3)[..., None]
    col = np.where(tot > 1, col / tot, col)
    a = smooth(alpha, 1.2)
    return Image.fromarray(np.dstack([np.clip(col, 0, 255), a * 255]).astype(np.uint8), "RGBA")


def backlight(seed, land):
    """Fuite de rétroéclairage : halos blancs chauds contre les bords et dans un coin (dalle décollée)."""
    rnd = random.Random(seed)
    w, h = (512, 256) if land else (256, 512)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    a = np.zeros((h, w), np.float32)
    for _ in range(rnd.randint(3, 6)):
        side = rnd.randint(0, 3)
        if side == 0:
            x, y = rnd.uniform(0, w), 0
        elif side == 1:
            x, y = rnd.uniform(0, w), h
        elif side == 2:
            x, y = 0, rnd.uniform(0, h)
        else:
            x, y = w, rnd.uniform(0, h)
        rx, ry = rnd.uniform(0.08, 0.25) * w, rnd.uniform(0.04, 0.12) * h
        if side >= 2:
            rx, ry = rnd.uniform(0.04, 0.12) * w, rnd.uniform(0.08, 0.25) * h
        a += np.exp(-(((xx - x) / rx) ** 2 + ((yy - y) / ry) ** 2)) * rnd.uniform(0.5, 1)
    cx, cy = rnd.choice([0, w]), rnd.choice([0, h])
    a += np.exp(-(((xx - cx) / (0.3 * w)) ** 2 + ((yy - cy) / (0.3 * h)) ** 2)) * 0.8
    a = np.clip(a, 0, 1) * 0.85
    col = np.dstack([np.full_like(a, 255), np.full_like(a, 246), np.full_like(a, 222)])
    return Image.fromarray(np.dstack([col, a * 255]).astype(np.uint8), "RGBA")


def mura(seed):
    """Arc-en-ciel de pression : anneaux irisés autour d'un point écrasé (dalle comprimée)."""
    rnd = random.Random(seed)
    s = 256
    yy, xx = np.mgrid[0:s, 0:s].astype(np.float32)
    n = gen_dirt.blood_noise(s, s, seed, 12, 6)
    r = np.sqrt((xx - s / 2) ** 2 + (yy - s / 2) ** 2) / (s / 2) + (n - 0.5) * 0.25
    ph = r * rnd.uniform(5, 8) * 2 * math.pi
    col = np.dstack([0.5 + 0.5 * np.cos(ph), 0.5 + 0.5 * np.cos(ph - 2.1), 0.5 + 0.5 * np.cos(ph - 4.2)]) * 255
    a = np.clip(1 - r, 0, 1) ** 1.3 * 0.4
    a *= np.clip(r * 4, 0, 1)
    return Image.fromarray(np.dstack([col, a * 255]).astype(np.uint8), "RGBA")


def band(seed):
    """Bande de lignes mortes colorées (nappe de la dalle abîmée) : verticale, plusieurs couleurs serrées."""
    rnd = random.Random(seed)
    w, h = 64, 512
    a = np.zeros((h, w, 4), np.float32)
    x = 0
    palette = [(30, 240, 90), (240, 40, 220), (240, 240, 240), (10, 10, 10), (60, 120, 255), (255, 210, 30)]
    while x < w:
        lw = rnd.randint(1, 4)
        c = rnd.choice(palette)
        al = rnd.uniform(0.55, 0.95)
        a[:, x:x + lw, :3] = c
        a[:, x:x + lw, 3] = al * 255
        x += lw + rnd.randint(0, 3)
    # Ligne qui s'interrompt par endroits (contact intermittent).
    for _ in range(rnd.randint(1, 3)):
        y0 = rnd.randint(0, h - 40)
        a[y0:y0 + rnd.randint(10, 60), :, 3] *= rnd.uniform(0, 0.3)
    return Image.fromarray(np.clip(a, 0, 255).astype(np.uint8), "RGBA")


def emi(frame):
    """Parasites du brouillage : lignes de balayage, bandes déchirées décalées, neige colorée par plaques."""
    rnd = random.Random(500 + frame)
    w, h = 256, 512
    a = np.zeros((h, w, 4), np.float32)
    # Lignes de balayage fines.
    for y in range(0, h, 3):
        a[y, :, :3] = 255
        a[y, :, 3] = rnd.uniform(0.04, 0.12)
    # Bandes déchirées : un aplat clair ou coloré, bord dentelé.
    for _ in range(rnd.randint(3, 6)):
        y0 = rnd.randint(0, h - 30)
        bh = rnd.randint(4, 26)
        c = rnd.choice([(255, 255, 255), (120, 255, 160), (255, 90, 230), (90, 200, 255), (20, 20, 20)])
        for y in range(y0, min(h, y0 + bh)):
            x0 = rnd.randint(0, w // 3)
            x1 = rnd.randint(w // 2, w)
            a[y, x0:x1, :3] = c
            a[y, x0:x1, 3] = rnd.uniform(0.25, 0.6)
    # Neige colorée par plaques.
    rng = np.random.default_rng(900 + frame)
    snow = rng.random((h, w))
    patch = gen_dirt.blood_noise(w, h, 700 + frame, 10, 4)
    m = (snow > 0.82) & (patch > 0.55)
    cols = rng.integers(0, 255, (h, w, 3))
    a[m, :3] = cols[m]
    a[m, 3] = 0.55
    return Image.fromarray(np.dstack([a[..., :3], a[..., 3] * 255]).clip(0, 255).astype(np.uint8), "RGBA")


def main():
    blood()
    for i in range(2):
        save(bleed(1100 + i), f"fx_bleed_{i}")
        save(backlight(1200 + i, False), f"fx_backlight_{i}_port")
        save(backlight(1200 + i, True), f"fx_backlight_{i}_land")
        save(mura(1300 + i), f"fx_mura_{i}")
        save(band(1400 + i), f"fx_band_{i}")
    for f in range(4):
        save(emi(f), f"fx_emi_{f}")


if __name__ == "__main__":
    main()
