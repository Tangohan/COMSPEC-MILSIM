#!/usr/bin/env python3
"""Textures de saleté de l'écran du téléphone (poussière, traces de doigts, sang, gouttes d'eau).

Usage : python3 gen_dirt.py <hemtt>
Sortie : Sources/addons/main/data/dirt_*.paa (blanches sur fond transparent, teintées en jeu par ctrlSetTextColor).
Dépendances : Pillow, HEMTT (hemtt utils paa convert).
"""
import math
import os
import random
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter

HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "data")


def white(alpha):
    img = Image.new("RGBA", alpha.size, (255, 255, 255, 0))
    img.putalpha(alpha)
    return img


def dust(seed):
    rnd = random.Random(seed)
    w = h = 512
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    # Voile irrégulier (taches larges et floues).
    for _ in range(60):
        x, y, r = rnd.randint(0, w), rnd.randint(0, h), rnd.randint(30, 110)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(20, 55))
    a = a.filter(ImageFilter.GaussianBlur(40))
    d = ImageDraw.Draw(a)
    # Grains et petits débris.
    for _ in range(2600):
        x, y = rnd.randint(0, w - 1), rnd.randint(0, h - 1)
        r = rnd.choice([0, 0, 0, 1, 1, 2])
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(90, 220))
    # Traînées (essuyé du revers de la main).
    for _ in range(5):
        x, y = rnd.randint(0, w), rnd.randint(0, h)
        ang = rnd.uniform(0, math.pi)
        for k in range(120):
            px, py = x + math.cos(ang) * k * 2, y + math.sin(ang) * k * 2
            d.ellipse([px - 6, py - 2, px + 6, py + 2], fill=rnd.randint(25, 45))
    return white(a.filter(ImageFilter.GaussianBlur(0.6)))


def fingerprint(seed):
    rnd = random.Random(seed)
    s = 128
    a = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(a)
    cx, cy = s / 2 + rnd.uniform(-6, 6), s / 2 + rnd.uniform(-6, 6)
    for i in range(3, 30):
        rx, ry = i * 1.9, i * 2.5
        start = rnd.uniform(0, 40)
        for seg in range(0, 360, 12):
            if rnd.random() < 0.12:
                continue
            d.arc([cx - rx, cy - ry, cx + rx, cy + ry], start + seg, start + seg + 10, fill=200, width=1)
    mask = Image.new("L", (s, s), 0)
    ImageDraw.Draw(mask).ellipse([12, 4, s - 12, s - 4], fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(10))
    a = Image.composite(a, Image.new("L", (s, s), 0), mask)
    return white(a.filter(ImageFilter.GaussianBlur(0.5)))


def smear(seed):
    rnd = random.Random(seed)
    w, h = 256, 128
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    # Doigts qui glissent : quatre traînées parallèles, plus chargées au départ.
    base = rnd.uniform(-0.25, 0.25)
    for f in range(rnd.randint(3, 4)):
        y0 = 24 + f * 22 + rnd.uniform(-5, 5)
        length = rnd.uniform(140, 230)
        width = rnd.uniform(13, 20)
        for k in range(int(length)):
            t = k / length
            x = 14 + k
            y = y0 + math.sin(t * 3 + f) * 3 + base * k * 0.3
            ww = width * (1 - 0.6 * t)
            d.ellipse([x - ww / 2, y - ww / 2, x + ww / 2, y + ww / 2], fill=int(230 * (1 - 0.75 * t)))
        # Gouttes au bout de la traînée.
        for _ in range(rnd.randint(0, 3)):
            x, y, r = 14 + length + rnd.uniform(-10, 10), y0 + rnd.uniform(-8, 8), rnd.uniform(1, 3)
            d.ellipse([x - r, y - r, x + r, y + r], fill=180)
    a = a.filter(ImageFilter.GaussianBlur(1.2))
    # Stries (sang séché, rides de la peau).
    d = ImageDraw.Draw(a)
    for _ in range(40):
        x, y = rnd.randint(10, w - 10), rnd.randint(10, h - 10)
        d.line([x, y, x + rnd.randint(8, 30), y + rnd.randint(-2, 2)], fill=0, width=1)
    return white(a.filter(ImageFilter.GaussianBlur(0.4)))


def splat(seed):
    rnd = random.Random(seed)
    s = 128
    a = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(a)
    r = rnd.uniform(16, 24)
    d.ellipse([s / 2 - r, s / 2 - r, s / 2 + r, s / 2 + r], fill=235)
    for _ in range(rnd.randint(10, 18)):
        ang, dist = rnd.uniform(0, 2 * math.pi), rnd.uniform(r * 0.8, s / 2 - 6)
        rr = rnd.uniform(1.5, 6) * (1 - dist / s)
        x, y = s / 2 + math.cos(ang) * dist, s / 2 + math.sin(ang) * dist
        d.ellipse([x - rr, y - rr, x + rr, y + rr], fill=220)
        d.line([s / 2, s / 2, x, y], fill=160, width=max(1, int(rr)))
    return white(a.filter(ImageFilter.GaussianBlur(1.0)))


def drop():
    s = 64
    a = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(a)
    d.ellipse([6, 6, s - 6, s - 6], fill=60)
    d.ellipse([6, 6, s - 6, s - 6], outline=210, width=3)
    d.ellipse([18, 14, 30, 24], fill=255)
    return white(a.filter(ImageFilter.GaussianBlur(1.2)))


def convert(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    paa = os.path.join(OUT, name + ".paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def main():
    with tempfile.TemporaryDirectory() as tmp:
        for i in range(2):
            convert(dust(11 + i), f"dirt_dust_{i}", tmp)
            convert(fingerprint(21 + i), f"dirt_print_{i}", tmp)
        for i in range(3):
            convert(smear(31 + i), f"dirt_smear_{i}", tmp)
        convert(splat(41), "dirt_splat", tmp)
        convert(drop(), "dirt_drop", tmp)


if __name__ == "__main__":
    main()
