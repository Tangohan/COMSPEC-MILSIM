#!/usr/bin/env python3
"""
Cadenas de la barre d'état du téléphone (fn_meshKeys "link", fn_statusUpdate), fermé et ouvert.
Blancs sur fond transparent : la couleur vient du contrôle, comme sig_*.paa.

    python gen_lock_icons.py
"""
import os
import sys

from PIL import Image, ImageDraw

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from paa_dxt import save_paa  # noqa: E402

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "data")
S = 256  # dessin suréchantillonné, réduit à 64 px
W = (255, 255, 255, 255)


def draw(opened):
    im = Image.new("RGBA", (S, S), (255, 255, 255, 0))
    d = ImageDraw.Draw(im)
    # Anse : ouverte, elle pivote et ne rejoint plus le corps à droite.
    if opened:
        d.arc((28, 18, 140, 130), 180, 360, fill=W, width=26)
        d.rectangle((28, 72, 54, 120), fill=W)
    else:
        d.arc((64, 18, 192, 146), 180, 360, fill=W, width=26)
        d.rectangle((64, 80, 90, 120), fill=W)
        d.rectangle((166, 80, 192, 120), fill=W)
    # Corps et trou de serrure.
    d.rounded_rectangle((36 if not opened else 72, 112, 220 if not opened else 244, 240), radius=20, fill=W)
    cx = 128 if not opened else 158
    d.ellipse((cx - 18, 150, cx + 18, 186), fill=(255, 255, 255, 0))
    d.rectangle((cx - 7, 170, cx + 7, 212), fill=(255, 255, 255, 0))
    return im.resize((64, 64), Image.LANCZOS)


save_paa(draw(False), os.path.join(OUT, "net_lock_closed_ca.paa"))
save_paa(draw(True), os.path.join(OUT, "net_lock_open_ca.paa"))
