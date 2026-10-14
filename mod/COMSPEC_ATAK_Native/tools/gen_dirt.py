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
    """Poussière fine : grains minuscules et quelques débris, un voile à peine visible, deux essuyages légers.
    Retours de jeu : les grosses taches floues (effet bokeh) noircissaient tout l'écran, c'était laid."""
    rnd = random.Random(seed)
    w = h = 1024
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    # Voile très léger, irrégulier (jamais une tache visible).
    for _ in range(40):
        x, y, r = rnd.randint(0, w), rnd.randint(0, h), rnd.randint(80, 220)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(4, 10))
    a = a.filter(ImageFilter.GaussianBlur(70))
    d = ImageDraw.Draw(a)
    # Grains : un pixel la plupart, rarement deux ; plus denses par plaques (là où la main n'a pas essuyé).
    for _ in range(9000):
        x, y = rnd.randint(0, w - 1), rnd.randint(0, h - 1)
        d.point((x, y), fill=rnd.randint(70, 170))
    for _ in range(500):
        x, y = rnd.randint(0, w - 1), rnd.randint(0, h - 1)
        r = rnd.choice([1, 1, 1, 2])
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(90, 190))
    # Fibres et petits débris.
    for _ in range(25):
        x, y = rnd.randint(0, w), rnd.randint(0, h)
        ang, ln = rnd.uniform(0, math.pi), rnd.randint(6, 18)
        d.line([x, y, x + math.cos(ang) * ln, y + math.sin(ang) * ln], fill=rnd.randint(70, 130), width=1)
    # Essuyages : traînées fines et pâles.
    for _ in range(2):
        x, y = rnd.randint(0, w), rnd.randint(0, h)
        ang = rnd.uniform(0, math.pi)
        for k in range(200):
            px, py = x + math.cos(ang) * k * 2.5, y + math.sin(ang) * k * 2.5
            d.ellipse([px - 10, py - 1, px + 10, py + 1], fill=rnd.randint(8, 16))
    return white(a.filter(ImageFilter.GaussianBlur(0.4)))


def fingerprint(seed):
    """Trace de pouce : ovale allongé, crêtes en boucle déformées, grasse et partielle (un bord appuyé, l'autre effacé)."""
    import numpy as np
    rnd = random.Random(seed)
    w, h = 128, 256  # PAA : côtés en puissances de 2 ; l'ovale occupe ~200 px de haut
    ys, xs = np.mgrid[0:h, 0:w].astype(np.float32)
    u = (xs - w / 2) / (w / 2)
    v = (ys - h * 0.56) / (h * 0.40)
    # Bruit lisse pour tordre les crêtes (pas de cercles parfaits).
    noise = Image.effect_noise((4, 6), 70).resize((w, h), Image.BICUBIC).filter(ImageFilter.GaussianBlur(12))
    warp = (np.asarray(noise, dtype=np.float32) - 128) / 128
    # Boucle : distance elliptique, crêtes qui s'ouvrent vers le bas.
    r = np.sqrt((u * 1.0) ** 2 + (np.minimum(v, 0) * 1.25) ** 2 + (np.maximum(v, 0) * 0.35) ** 2)
    r = r + 0.025 * warp + 0.012 * np.sin(u * 3 + rnd.uniform(0, 6))
    period = 0.062 + rnd.uniform(-0.005, 0.005)
    ridge = 0.5 + 0.5 * np.sin(2 * np.pi * r / period)
    ridge = np.clip((ridge - 0.35) * 2.2, 0, 1)
    # Contour du doigt : ovale flou ; pression inégale (un côté plus marqué), lacunes de crêtes.
    shape = np.clip(1 - ((u / 0.92) ** 2 + ((ys - h * 0.5) / (h * 0.39)) ** 2), 0, 1) ** 0.6
    ang = rnd.uniform(0, 2 * np.pi)
    press = np.clip(0.35 + 0.9 * (u * np.cos(ang) + v * np.sin(ang)), 0, 1) ** 1.4
    gaps = np.clip(1.4 - 1.0 * (np.asarray(Image.effect_noise((w // 6, h // 6), 90).resize((w, h), Image.BICUBIC), dtype=np.float32) / 255), 0, 1)
    a = ridge * shape * press * gaps
    # Voile gras sous les crêtes.
    a = 0.7 * a + 0.3 * shape * press
    img = Image.fromarray(np.uint8(np.clip(a, 0, 1) * 210), "L").filter(ImageFilter.GaussianBlur(0.7))
    return white(img)


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


def case_dust(src, seed):
    """Saleté de la coque d'un modèle (même cadre que sa texture) : poussière plus dense vers les bords et les
    creux, éclaboussures de boue ; masquée par la silhouette de la coque (l'ouverture de l'écran reste propre)."""
    rnd = random.Random(seed)
    S = 1024
    shape = Image.open(src).convert("RGBA").getchannel("A").resize((S, S), Image.LANCZOS)
    shape = shape.point(lambda v: 255 if v > 200 else 0).filter(ImageFilter.MinFilter(3))
    # Bords : 1 - flou de la silhouette (la saleté s'accumule contre les arêtes et dans les angles).
    soft = shape.filter(ImageFilter.GaussianBlur(26))
    edge = Image.eval(soft, lambda v: 255 - v)
    base = dust(seed).getchannel("A").resize((S, S), Image.LANCZOS)
    a = Image.new("L", (S, S), 0)
    d = ImageDraw.Draw(a)
    # Taches de boue séchée, surtout près des bords.
    for _ in range(30):
        x, y, r = rnd.randint(0, S), rnd.randint(0, S), rnd.randint(2, 8)
        d.ellipse([x - r, y - r * 0.7, x + r, y + r * 0.7], fill=rnd.randint(50, 110))
    for _ in range(900):
        x, y = rnd.randint(0, S - 1), rnd.randint(0, S - 1)
        r = rnd.choice([0, 1, 1, 2])
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(60, 160))
    a = a.filter(ImageFilter.GaussianBlur(0.8))
    from PIL import ImageChops
    mix = ImageChops.add(ImageChops.add(base, Image.eval(edge, lambda v: int(v * 0.35))), ImageChops.multiply(a, Image.eval(edge, lambda v: min(255, 40 + v))))
    return white(ImageChops.multiply(mix, shape))


CASE_SRC = {
    "s7": os.path.join(os.path.dirname(os.path.abspath(__file__)), "src", "android_s7_ca.png"),
    "rugged": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_rugged_front.png"),
    "iphone": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_iphone_front.png"),
    "tablet": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_tablet_front.png"),
    "sonim": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_sonim_front.png"),
    "keys": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_keys_front.png"),
    "plate": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_plate_front.png"),
    "flip": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_flip_front.png"),
    "ribbed": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_ribbed_front.png"),
}


def case_assets(tmp, only=None):
    for i, (m, src) in enumerate(CASE_SRC.items()):
        if (only and m not in only) or not os.path.exists(src):
            continue
        land = case_dust(src, 51 + i)
        convert(land, f"dirt_case_{m}_land", tmp)
        convert(land.rotate(-90, expand=True), f"dirt_case_{m}_port", tmp)


def convert(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    paa = os.path.join(OUT, name + ".paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def main():
    with tempfile.TemporaryDirectory() as tmp:
        if len(sys.argv) > 2 and sys.argv[2] == "prints":
            for i in range(4):
                convert(fingerprint(21 + i), f"dirt_print_{i}", tmp)
            return
        # « dust » : poussière de l'écran et de toutes les coques seulement.
        if len(sys.argv) > 2 and sys.argv[2] == "dust":
            for i in range(2):
                convert(dust(11 + i), f"dirt_dust_{i}", tmp)
            case_assets(tmp)
            return
        # « case keys » : seulement la poussière de la coque de la tablette à touches.
        if len(sys.argv) > 3 and sys.argv[2] == "case":
            case_assets(tmp, only=sys.argv[3:])
            return
        for i in range(2):
            convert(dust(11 + i), f"dirt_dust_{i}", tmp)
        for i in range(4):
            convert(fingerprint(21 + i), f"dirt_print_{i}", tmp)
        for i in range(3):
            convert(smear(31 + i), f"dirt_smear_{i}", tmp)
        convert(splat(41), "dirt_splat", tmp)
        convert(drop(), "dirt_drop", tmp)
        case_assets(tmp)


if __name__ == "__main__":
    main()
