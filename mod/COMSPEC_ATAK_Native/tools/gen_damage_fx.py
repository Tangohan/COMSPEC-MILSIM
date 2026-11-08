#!/usr/bin/env python3
"""Calques de dégâts du téléphone, combinables, tirés selon la cause (fn_deviceDamage) et dessinés par fn_screenFx.

Usage : python tools/gen_damage_fx.py [--preview dossier]   (hemtt dans le PATH, Pillow, numpy)
Sortie : Sources/addons/items/data/dmg/<nom>_<variante>_<port|land>.paa, dans items.pbo : main.pbo approche la limite de
100 Mo de GitHub. Graine fixe : même dessin à chaque génération. Paysage : portrait tourné de 90° (comme fn_deviceOverlay).

Vitre : g_bullet (impact de balle en étoile), g_web (toile), g_corner (coin écrasé), g_chips (éclats manquants),
g_scratch (rayures de chute). Dalle : l_vlines et l_hlines (lignes de couleur), l_band (bande morte), l_pixels (pixels
morts), l_ink (tache d'encre, rectangle de zone morte repris dans fn_screenFx : INK_RECTS).
"""
import math
import os
import random
import subprocess
import sys
import tempfile

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(os.path.dirname(HERE), "Sources", "addons", "items", "data", "dmg")
W, H = 512, 1024
GLASS = (235, 242, 250)
# Fractions de l'écran en portrait : x, y, largeur, hauteur. À garder identique dans fn_screenFx.
INK_RECTS = [(0.62, 0.06, 0.38, 0.2), (0.0, 0.62, 0.36, 0.2), (0.28, 0.82, 0.44, 0.18)]


def blank():
    return Image.new("RGBA", (W, H), (0, 0, 0, 0))


def crack_line(d, r, x, y, angle, length, alpha, width=1, steps=5):
    for _ in range(steps):
        angle += r.uniform(-22, 22)
        nx = x + math.cos(math.radians(angle)) * length / steps
        ny = y + math.sin(math.radians(angle)) * length / steps
        d.line((x, y, nx, ny), fill=GLASS + (alpha,), width=width)
        if r.random() < 0.3:
            a2 = angle + r.choice((-1, 1)) * r.uniform(25, 60)
            d.line((nx, ny, nx + math.cos(math.radians(a2)) * length / 6, ny + math.sin(math.radians(a2)) * length / 6), fill=GLASS + (alpha // 2,), width=1)
        x, y = nx, ny


def g_bullet(seed):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    cx, cy = r.uniform(0.2, 0.8) * W, r.uniform(0.15, 0.85) * H
    for i in range(r.randint(14, 20)):
        crack_line(d, r, cx, cy, i * 360 / 16 + r.uniform(-8, 8), r.uniform(80, 300), r.randint(130, 220), 2 if i % 3 == 0 else 1)
    for ring in (22, 40):
        pts = [(cx + math.cos(math.radians(a)) * ring * r.uniform(0.85, 1.2), cy + math.sin(math.radians(a)) * ring * r.uniform(0.85, 1.2)) for a in range(0, 361, 30)]
        d.line(pts, fill=GLASS + (150,), width=1)
    d.ellipse((cx - 13, cy - 13, cx + 13, cy + 13), fill=(4, 4, 6, 245), outline=GLASS + (220,), width=2)
    return img


def g_web(seed):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    cx, cy = r.uniform(0.25, 0.75) * W, r.uniform(0.2, 0.8) * H
    n = r.randint(9, 13)
    dirs = [i * 360 / n + r.uniform(-9, 9) for i in range(n)]
    for a in dirs:
        d.line((cx, cy, cx + math.cos(math.radians(a)) * 700, cy + math.sin(math.radians(a)) * 700), fill=GLASS + (150,), width=1)
    for rad in (60, 120, 190, 280, 390):
        pts = [(cx + math.cos(math.radians(a)) * rad * r.uniform(0.86, 1.14), cy + math.sin(math.radians(a)) * rad * r.uniform(0.86, 1.14)) for a in dirs]
        d.line(pts + pts[:1], fill=GLASS + (max(70, 170 - rad // 4),), width=1)
    return img


def g_corner(seed):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    cx, cy = (0, H) if seed % 2 else (W, 0)
    to = math.degrees(math.atan2(H / 2 - cy, W / 2 - cx))
    for i in range(16):
        crack_line(d, r, cx, cy, to - 50 + i * 100 / 15, r.uniform(120, 480), r.randint(120, 210), 2 if i % 4 == 0 else 1, 7)
    # Dalle écrasée dans le coin.
    mask = Image.new("L", (W, H), 0)
    ImageDraw.Draw(mask).ellipse((cx - 70, cy - 70, cx + 70, cy + 70), fill=210)
    dark = Image.new("RGBA", (W, H), (0, 0, 0, 255))
    dark.putalpha(mask.filter(ImageFilter.GaussianBlur(16)))
    img = Image.alpha_composite(dark, img)
    return img


def g_chips(seed):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    for _ in range(r.randint(4, 7)):
        cx, cy = r.uniform(0.05, 0.95) * W, r.uniform(0.05, 0.95) * H
        s = r.uniform(10, 34)
        pts = [(cx + math.cos(math.radians(a)) * s * r.uniform(0.5, 1.3), cy + math.sin(math.radians(a)) * s * r.uniform(0.5, 1.3)) for a in range(0, 360, 50)]
        d.polygon(pts, fill=(6, 7, 8, 235))
        d.line(pts + pts[:1], fill=GLASS + (210,), width=1)
        for _ in range(3):
            crack_line(d, r, cx, cy, r.uniform(0, 360), r.uniform(30, 90), 140)
    return img


def g_scratch(seed):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    base = r.uniform(0, 180)
    for _ in range(r.randint(24, 40)):
        x, y = r.uniform(0, W), r.uniform(0, H)
        a = math.radians(base + r.uniform(-9, 9))
        ln = r.uniform(50, 420)
        d.line((x, y, x + math.cos(a) * ln, y + math.sin(a) * ln), fill=(255, 255, 255, r.randint(45, 120)), width=1)
    return img


COLORS = [(255, 40, 200), (40, 255, 120), (60, 150, 255), (255, 240, 70), (240, 240, 255)]


def l_lines(seed, vertical):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    for _ in range(r.randint(3, 6)):
        col = r.choice(COLORS) + (r.randint(170, 235),)
        p = r.randint(10, (W if vertical else H) - 10)
        wd = r.choice((1, 1, 2, 3))
        if vertical:
            d.rectangle((p, 0, p + wd, H), fill=col)
        else:
            d.rectangle((0, p, W, p + wd), fill=col)
    return img


def l_band(seed):
    r = random.Random(seed)
    arr = np.zeros((H, W, 4), dtype=np.uint8)
    y0 = r.randint(int(H * 0.1), int(H * 0.85))
    h = r.randint(int(H * 0.025), int(H * 0.05))
    rng = np.random.default_rng(seed)
    arr[y0:y0 + h, :, 3] = 240
    arr[y0 - 3:y0, :, :3] = 255
    arr[y0 - 3:y0, :, 3] = rng.integers(0, 110, (3, W))
    arr[y0 + h:y0 + h + 3, :, :3] = 255
    arr[y0 + h:y0 + h + 3, :, 3] = rng.integers(0, 110, (3, W))
    return Image.fromarray(arr, "RGBA")


def l_pixels(seed):
    r = random.Random(seed)
    img = blank()
    d = ImageDraw.Draw(img)
    for _ in range(r.randint(5, 9)):
        cx, cy = r.uniform(0, W), r.uniform(0, H)
        for _ in range(r.randint(6, 40)):
            x, y = cx + r.gauss(0, 14), cy + r.gauss(0, 14)
            s = r.choice((2, 2, 3, 4))
            col = r.choice(((0, 0, 0, 255), (0, 0, 0, 255), (0, 0, 0, 255), (40, 255, 120, 230), (255, 40, 200, 230), (240, 240, 255, 230)))
            d.rectangle((x, y, x + s, y + s), fill=col)
    return img


def l_ink(variant):
    rng = np.random.default_rng(100 + variant)
    x, y, w, h = INK_RECTS[variant]
    noise = Image.fromarray((rng.random((H // 16, W // 16)) * 255).astype(np.uint8), "L").resize((W, H), Image.BICUBIC).filter(ImageFilter.GaussianBlur(10))
    n = np.asarray(noise, dtype=np.float32) / 255
    yy, xx = np.mgrid[0:H, 0:W]
    cx, cy = (x + w / 2) * W, (y + h / 2) * H
    dist = np.sqrt(((xx - cx) / (w * W / 2)) ** 2 + ((yy - cy) / (h * H / 2)) ** 2)
    core = np.clip((1.05 - dist + (n - 0.5) * 1.1) * 7, 0, 1)
    arr = np.zeros((H, W, 4), dtype=np.uint8)
    arr[..., 3] = (core * 252).astype(np.uint8)
    # Frange violette et verte au bord de la tache.
    edge = (core > 0.02) & (core < 0.55)
    arr[edge, 0] = 110
    arr[edge, 1] = 40
    arr[edge, 2] = 170
    return Image.fromarray(arr, "RGBA")


def layers():
    for v in range(3):
        yield "g_bullet", v, g_bullet(10 + v)
    for v in range(2):
        yield "g_web", v, g_web(20 + v)
        yield "g_corner", v, g_corner(30 + v)
        yield "g_chips", v, g_chips(40 + v)
        yield "l_vlines", v, l_lines(60 + v, True)
        yield "l_hlines", v, l_lines(70 + v, False)
        yield "l_band", v, l_band(80 + v)
        yield "l_pixels", v, l_pixels(90 + v)
    for v in range(3):
        yield "g_scratch", v, g_scratch(50 + v)
        yield "l_ink", v, l_ink(v)


def to_paa(img, path, tmp):
    png = os.path.join(tmp, os.path.basename(path) + ".png")
    img.save(png)
    if os.path.exists(path):
        os.remove(path)
    subprocess.run(["hemtt", "utils", "paa", "convert", png, path], check=True, stdout=subprocess.DEVNULL)


def main():
    preview = sys.argv[sys.argv.index("--preview") + 1] if "--preview" in sys.argv else ""
    os.makedirs(OUT, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        for name, v, img in layers():
            to_paa(img, os.path.join(OUT, f"{name}_{v}_port.paa"), tmp)
            to_paa(img.rotate(-90, expand=True), os.path.join(OUT, f"{name}_{v}_land.paa"), tmp)
            if preview:
                back = Image.new("RGBA", img.size, (30, 60, 45, 255))
                Image.alpha_composite(back, img).convert("RGB").save(os.path.join(preview, f"{name}_{v}.png"))


if __name__ == "__main__":
    main()
