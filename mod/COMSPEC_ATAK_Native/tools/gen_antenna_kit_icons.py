#!/usr/bin/env python3
"""
Icônes d'inventaire des antennes transportables, dessinées ici : aucune photo, aucune marque.

    python gen_antenna_kit_icons.py DOSSIER
    hemtt utils paa convert DOSSIER/item_antkit_sat.png ../Sources/addons/main/data/item_antkit_sat.paa
    hemtt utils paa convert DOSSIER/item_antkit_mast.png ../Sources/addons/main/data/item_antkit_mast.paa
"""
import os
import sys

from PIL import Image, ImageDraw

S = 256
DARK = (40, 42, 36, 255)
METAL = (92, 94, 86, 255)
LIGHT = (150, 152, 140, 255)
OLIVE = (74, 82, 52, 255)


def tripod(d, x, top, bottom):
    for foot in (x - 62, x, x + 62):
        d.line((x, top, foot, bottom), fill=METAL, width=8)


def satellite():
    im = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    tripod(d, 128, 150, 240)
    # Parabole vue de trois quarts, bras et source.
    d.chord((30, 14, 200, 184), 120, 300, fill=LIGHT, outline=DARK, width=5)
    d.line((88, 104, 188, 54), fill=DARK, width=7)
    d.ellipse((178, 40, 204, 66), fill=OLIVE, outline=DARK, width=4)
    return im


def mast():
    im = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    tripod(d, 128, 170, 244)
    # Mât, brins horizontaux et fouet.
    d.rectangle((121, 44, 135, 200), fill=METAL, outline=DARK)
    d.rectangle((125, 10, 131, 48), fill=DARK)
    for y, half in ((70, 62), (104, 48), (138, 34)):
        d.line((128 - half, y, 128 + half, y), fill=OLIVE, width=8)
    return im


out = sys.argv[1]
satellite().save(os.path.join(out, "item_antkit_sat.png"))
mast().save(os.path.join(out, "item_antkit_mast.png"))
