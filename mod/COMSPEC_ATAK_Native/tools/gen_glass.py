#!/usr/bin/env python3
"""Verre de l'écran du téléphone (4.2.1, fn_screenSurface) : reflet de la vitre et coins arrondis de l'écran.

Usage : python3 gen_glass.py <hemtt> [dossier d'aperçus PNG]
Sortie : Sources/addons/main/data/screen_glass.paa, screen_corner_<tl|tr|br|bl>.paa (HEMTT : DXT5 compressé LZO).
Dépendances : Pillow, numpy, HEMTT (hemtt utils paa convert).

screen_glass : blanc, l'alpha seul dessine le reflet (le jeu le pose sur tout l'écran, opacité faible, réglable) :
  une large bande douce en biais depuis le haut à gauche, un trait fin plus net juste après, un liseré clair le long
  du bord haut ; rien sur la moitié basse droite, là où se lisent la carte et le texte.
screen_corner_<coin> : blanc opaque hors d'un quart de disque qui touche les deux bords opposés au coin (anticrénelé).
  Le jeu le teinte à la couleur du cadre de la coque et le pose sur le coin de l'écran : l'écran rectangulaire épouse
  l'arrondi de l'ouverture de chaque coque (rayon et couleur par modèle et par coin, mesurés sur ses textures).
"""
import os
import subprocess
import sys
import tempfile

import numpy as np
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
PREVIEW = sys.argv[2] if len(sys.argv) > 2 else None


def glass(size=128):
    yy, xx = (np.mgrid[0:size, 0:size].astype(float) + 0.5) / size
    # Coordonnée le long du biais (0 en haut à gauche, 1 en bas à droite).
    u = (xx * 0.62 + yy * 0.78) / 1.4
    a = 0.60 * np.exp(-(((u - 0.24) / 0.11) ** 2))
    a += 0.38 * np.exp(-(((u - 0.40) / 0.022) ** 2))
    a += 0.20 * np.exp(-yy / 0.025)
    a *= np.clip(1.25 - 1.6 * u, 0, 1)
    rgba = np.zeros((size, size, 4), np.uint8)
    rgba[..., :3] = 255
    rgba[..., 3] = np.clip(a * 255, 0, 255).round().astype(np.uint8)
    return Image.fromarray(rgba, "RGBA")


def corner(where, size=64, k=8):
    """Coin opaque hors du quart de disque centré au coin opposé, de rayon = côté (suréchantillonné k fois)."""
    s = size * k
    yy, xx = np.mgrid[0:s, 0:s].astype(float) + 0.5
    cx = s if where in ("tl", "bl") else 0
    cy = s if where in ("tl", "tr") else 0
    out = (np.hypot(xx - cx, yy - cy) > s).astype(float)
    a = out.reshape(size, k, size, k).mean((1, 3))
    rgba = np.zeros((size, size, 4), np.uint8)
    rgba[..., :3] = 255
    rgba[..., 3] = (a * 255).round().astype(np.uint8)
    return Image.fromarray(rgba, "RGBA")


def convert(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    if PREVIEW:
        img.save(os.path.join(PREVIEW, name + ".png"))
    paa = os.path.join(OUT, name + ".paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def main():
    with tempfile.TemporaryDirectory() as tmp:
        convert(glass(), "screen_glass", tmp)
        for c in ("tl", "tr", "br", "bl"):
            convert(corner(c), f"screen_corner_{c}", tmp)


if __name__ == "__main__":
    main()
