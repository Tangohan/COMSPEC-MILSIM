#!/usr/bin/env python3
"""
Textures de l'interface « réaliste » du téléphone (dessins originaux COMSPEC).

  ui_icon_squircle.paa : fond d'icône d'app (carré arrondi façon lanceur Android/iOS) en niveaux de gris, avec
                         dégradé, liseré clair en haut, bord sombre en bas et ombre portée douce. Teinté en jeu par
                         ctrlSetTextColor (couleur de l'app, fn_iconTint) : le gris multiplie la couleur, l'ombre noire
                         reste noire. Carré arrondi centré en (0,5 ; 0,48), côté 80 % de la texture.

Usage : python3 tools/gen_ui_realism.py
Sortie : Sources/addons/main/data/*.paa (+ tools/render/ui_icon_squircle.png pour contrôle)
Dépendances : Pillow, numpy (écriture PAA : tools/paa_dxt.py, sans HEMTT).
"""
import os
import sys

import numpy as np
from PIL import Image, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from paa_dxt import save_paa  # noqa: E402

OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")

# Géométrie partagée avec fn_tileCreate / fn_fx (centre et côté du carré arrondi, en fraction de la texture).
CX, CY, SIDE = 0.5, 0.48, 0.80
EXPONENT = 5.0  # superellipse : 2 = cercle, plus grand = plus carré


def squircle_mask(size, cx, cy, half, n, ss=4):
    """Masque anti-crénelé (0..1) d'une superellipse |x|^n + |y|^n <= 1."""
    s = size * ss
    ys, xs = np.mgrid[0:s, 0:s].astype(np.float32)
    x = ((xs + 0.5) / s - cx) / half
    y = ((ys + 0.5) / s - cy) / half
    inside = (np.abs(x) ** n + np.abs(y) ** n) <= 1.0
    m = inside.reshape(size, ss, size, ss).mean(axis=(1, 3))
    return m, x.reshape(size, ss, size, ss).mean(axis=(1, 3)), y.reshape(size, ss, size, ss).mean(axis=(1, 3))


def icon_squircle(size=256):
    half = SIDE / 2
    mask, x, y = squircle_mask(size, CX, CY, half, EXPONENT)
    # Distance normalisée au bord (1 au bord, 0 au centre) pour les liserés.
    edge = (np.abs(x) ** EXPONENT + np.abs(y) ** EXPONENT) ** (1.0 / EXPONENT)
    t = np.clip((y + 1) / 2, 0, 1)  # 0 en haut, 1 en bas
    lum = 246 - 52 * t  # dégradé vertical
    # Reflet doux en haut à gauche.
    sheen = np.exp(-(((x + 0.45) ** 2) / 0.35 + ((y + 0.55) ** 2) / 0.18))
    lum = lum + 9 * sheen
    # Liseré clair en haut (bord supérieur), bord plus sombre en bas.
    rim = np.clip((edge - 0.93) / 0.07, 0, 1)
    lum = lum + rim * np.where(y < 0, 12, -58)
    lum = np.clip(lum, 0, 255)

    # Ombre portée : masque décalé vers le bas et flouté.
    sh_mask, _, _ = squircle_mask(size, CX, CY + 0.035, half * 0.98, EXPONENT)
    sh = Image.fromarray((sh_mask * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(size * 0.03))
    sh = np.asarray(sh).astype(np.float32) / 255 * 0.55

    a_icon = mask
    a = a_icon + sh * (1 - a_icon)
    col = np.where(a > 0, (lum * a_icon) / np.maximum(a, 1e-6), 0)
    rgba = np.zeros((size, size, 4), np.uint8)
    rgba[..., 0] = rgba[..., 1] = rgba[..., 2] = np.clip(col, 0, 255).astype(np.uint8)
    rgba[..., 3] = np.clip(a * 255, 0, 255).astype(np.uint8)
    return Image.fromarray(rgba, "RGBA")


def main():
    os.makedirs(RENDER, exist_ok=True)
    img = icon_squircle()
    img.save(os.path.join(RENDER, "ui_icon_squircle.png"))
    save_paa(img, os.path.join(OUT, "ui_icon_squircle.paa"))
    print("ui_icon_squircle.paa")


if __name__ == "__main__":
    main()
