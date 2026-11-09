#!/usr/bin/env python3
"""
Icônes de la radio MPU5 dans la barre d'état du téléphone (fn_mpuLink "badge", fn_statusUpdate). Blancs sur fond transparent :
la couleur vient du contrôle. Cinq FORMES, pour ne pas dépendre de la couleur seule :
  mpu_idle  radio en contour (allumée, téléphone non relié)
  mpu_cable radio pleine + câble et fiche (câble EUD)
  mpu_wifi  radio pleine + trois arcs (Wi-Fi)
  mpu_tx    radio pleine + flèche montante (le téléphone transmet)
  mpu_cut   radio pleine barrée (liaison coupée)

    python gen_mpu_status_icons.py
"""
import os
import sys

from PIL import Image, ImageDraw

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from paa_dxt import save_paa  # noqa: E402

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "data")
S = 256  # dessin suréchantillonné, réduit à 64 px
W = (255, 255, 255, 255)
CLEAR = (255, 255, 255, 0)


def radio(d, filled, dx=0):
    """Corps de radio à gauche (x 40-130) avec antenne et écran."""
    body = (40 + dx, 80, 130 + dx, 236)
    ant = (100 + dx, 14, 118 + dx, 84)
    if filled:
        d.rounded_rectangle(body, radius=14, fill=W)
        d.rectangle(ant, fill=W)
        d.rectangle((56 + dx, 100, 114 + dx, 140), fill=CLEAR)
    else:
        d.rounded_rectangle(body, radius=14, outline=W, width=14)
        d.rectangle(ant, fill=W)
        d.rectangle((60 + dx, 100, 110 + dx, 130), outline=W, width=10)


def draw(kind):
    im = Image.new("RGBA", (S, S), CLEAR)
    d = ImageDraw.Draw(im)
    radio(d, kind != "idle")
    if kind == "cable":
        d.line((130, 200, 188, 200), fill=W, width=18)
        d.rounded_rectangle((184, 170, 226, 230), radius=8, fill=W)
        d.rectangle((226, 180, 244, 190), fill=W)
        d.rectangle((226, 210, 244, 220), fill=W)
    elif kind == "wifi":
        for r in (36, 68, 100):
            d.arc((130 + 24 - r, 90 - r, 130 + 24 + r, 90 + r), -50, 50, fill=W, width=16)
        d.ellipse((146, 80, 166, 100), fill=W)
    elif kind == "tx":
        d.polygon([(190, 20), (144, 100), (236, 100)], fill=W)
        d.rectangle((176, 100, 204, 220), fill=W)
    elif kind == "cut":
        d.line((20, 236, 236, 20), fill=CLEAR, width=64)
        d.line((20, 236, 236, 20), fill=W, width=26)
    return im.resize((64, 64), Image.LANCZOS)


for k in ("idle", "cable", "wifi", "tx", "cut"):
    path = os.path.join(OUT, f"mpu_{k}.paa")
    if os.path.exists(path):
        os.remove(path)
    save_paa(draw(k), path)
