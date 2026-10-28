#!/usr/bin/env python3
"""
Samsung S23 durci (planche fournie par TTRD, 2026-10-19 : src/s23_planche.png, vues face, dos, côtés, clip) :
découpe la face et le dos sur le fond noir, rend l'écran transparent (rectangle propre sur la zone noire),
agrandit x2 et tourne en paysage : la face le haut à gauche (comme les autres photos de frame_photos.py), le dos
le haut à droite (téléphone retourné, comme le miroir des autres dos).

    python3 cut_s23.py      # écrit src/frame_s23.png (face) et src/frame_s23_back.png (dos)
    python3 frame_photos.py # puis gen_assets.py <hemtt> frames
"""
import os

from PIL import Image, ImageDraw, ImageFilter
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
src = Image.open(os.path.join(HERE, "src", "s23_planche.png")).convert("RGB")
out = os.path.join(HERE, "src")


def cut(box, hole):
    im = src.crop(box)
    a = np.array(im).astype(int).sum(2)
    dark = Image.fromarray(((a < 36) * 255).astype("uint8"), "L")
    # fond : remplissage depuis les bords
    m = dark.copy()
    w, h = m.size
    for p in [(0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1)] + [(x, 0) for x in range(0, w, 7)] + [(x, h - 1) for x in range(0, w, 7)] + [(0, y) for y in range(0, h, 7)] + [(w - 1, y) for y in range(0, h, 7)]:
        if m.getpixel(p) == 255:
            ImageDraw.floodfill(m, p, 128)
    bg = np.array(m) == 128
    if hole:
        ImageDraw.floodfill(m, (w // 2, h // 2), 64)
    scr = np.array(m) == 64
    if hole:  # écran : rectangle propre aux coins à peine arrondis sur la zone noire trouvée
        ys, xs = np.nonzero(scr)
        r = Image.new("L", (w, h), 0)
        ImageDraw.Draw(r).rounded_rectangle([xs.min(), ys.min(), xs.max(), ys.max()], radius=7, fill=255)
        scr = np.array(r) == 255
    alpha = np.where(bg | scr, 0, 255).astype("uint8")
    A = Image.fromarray(alpha, "L")
    # bord adouci (anti-crénelage) sans grignoter l'objet
    A = A.filter(ImageFilter.MaxFilter(3)).filter(ImageFilter.GaussianBlur(0.8))
    A = Image.fromarray(np.where(bg | scr, np.array(A), 255).astype("uint8"), "L")
    if hole:  # écran : bords nets
        A = Image.fromarray(np.where(scr, 0, np.array(A)).astype("uint8"), "L")
    rgba = im.convert("RGBA"); rgba.putalpha(A)
    bb = A.getbbox()
    return rgba.crop(bb)


front = cut((150, 95, 625, 850), True)
back = cut((770, 95, 1220, 850), False)


def up(img):
    # Agrandi x2 puis légèrement accentué : la photo source est petite (746 px de haut).
    big = img.resize((img.width * 2, img.height * 2), Image.LANCZOS)
    rgb = big.convert("RGB").filter(ImageFilter.UnsharpMask(radius=2, percent=60, threshold=2))
    rgb = rgb.convert("RGBA"); rgb.putalpha(big.getchannel("A"))
    return rgb


up(front).rotate(90, expand=True).save(out + "/frame_s23.png")
up(back).rotate(-90, expand=True).save(out + "/frame_s23_back.png")
