#!/usr/bin/env python3
"""
Textures du style tactique de la carte : pion rond (disque, anneau, triangle de direction) et cercle fin des points clés.
Blanches : la couleur vient de drawIcon. Le triangle se dessine à 1,5 fois la taille du pion et tourne avec le cap.

    python gen_map_pawn.py DOSSIER_PNG
    hemtt utils paa convert DOSSIER_PNG/map_pawn_disc.png ../Sources/addons/main/data/map_pawn_disc.paa
    (idem map_pawn_ring, map_pawn_tick, map_keyring)
"""
import os
import sys

from PIL import Image, ImageDraw

S = 128
K = 4  # suréchantillonnage (bords lissés)
B = S * K
C = B / 2


def ring(d, r_out, r_in):
    d.ellipse((C - r_out, C - r_out, C + r_out, C + r_out), fill=255)
    if r_in > 0:
        d.ellipse((C - r_in, C - r_in, C + r_in, C + r_in), fill=0)


def tick(d):
    # Pointe en haut, à l'extérieur de l'anneau (anneau à 0,94 / 1,5 du demi-côté).
    top = B * 0.015
    base = B * 0.135
    half = B * 0.075
    d.polygon([(C, top), (C + half, base), (C - half, base)], fill=255)


SHAPES = {
    "map_pawn_disc": lambda d: ring(d, C * 0.90, 0),
    "map_pawn_ring": lambda d: ring(d, C * 0.94, C * 0.70),
    "map_pawn_tick": tick,
    "map_keyring": lambda d: ring(d, C * 0.96, C * 0.90),
}

out = sys.argv[1]
os.makedirs(out, exist_ok=True)
for name, draw in SHAPES.items():
    alpha = Image.new("L", (B, B), 0)
    draw(ImageDraw.Draw(alpha))
    im = Image.new("RGBA", (S, S), (255, 255, 255, 0))
    im.putalpha(alpha.resize((S, S), Image.LANCZOS))
    im.save(os.path.join(out, name + ".png"))
