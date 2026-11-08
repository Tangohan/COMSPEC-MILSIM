#!/usr/bin/env python3
"""
Textures du style tactique de la carte : pion rond (disque, anneau, triangle de direction) et cercle fin des points clés.
Blanches : la couleur vient de drawIcon. Le triangle se dessine à 1,5 fois la taille du pion et tourne avec le cap.

    python gen_map_pawn.py DOSSIER_PNG
    hemtt utils paa convert DOSSIER_PNG/map_pawn_disc.png ../Sources/addons/main/data/map_pawn_disc.paa
    (idem map_pawn_ring, map_pawn_tick, map_keyring, map_shape_*, map_preview_bg)
Formes du sélecteur (fn_mapShape) : triangle plein, losange, carré, croix ; map_preview_bg : fond de carte des aperçus.
"""
import os
import random
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


def tri(d):
    d.polygon([(C, B * 0.06), (B * 0.92, B * 0.94), (B * 0.08, B * 0.94)], fill=255)


def diamond(d):
    d.polygon([(C, B * 0.04), (B * 0.96, C), (C, B * 0.96), (B * 0.04, C)], fill=255)


def square(d):
    d.rectangle((B * 0.1, B * 0.1, B * 0.9, B * 0.9), fill=255)


def cross(d):
    w = B * 0.13
    d.polygon([(B * 0.12, B * 0.12 + w), (B * 0.12 + w, B * 0.12), (B * 0.88, B * 0.88 - w), (B * 0.88 - w, B * 0.88)], fill=255)
    d.polygon([(B * 0.88 - w, B * 0.12), (B * 0.88, B * 0.12 + w), (B * 0.12 + w, B * 0.88), (B * 0.12, B * 0.88 - w)], fill=255)


SHAPES = {
    "map_shape_tri": tri,
    "map_shape_diamond": diamond,
    "map_shape_square": square,
    "map_shape_cross": cross,
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

# Fond des aperçus : terrain sombre, parcelles et une route, sans donnée de carte réelle (256 x 128).
random.seed(7)
bg = Image.new("RGB", (256, 128), (38, 48, 40))
dr = ImageDraw.Draw(bg)
for _ in range(26):
    x, y = random.randint(-20, 240), random.randint(-20, 110)
    w, h = random.randint(24, 80), random.randint(16, 52)
    g = random.randint(36, 66)
    dr.rectangle((x, y, x + w, y + h), fill=(g - 6, g + 4, g - 12))
dr.line([(0, 96), (70, 74), (150, 80), (256, 40)], fill=(112, 108, 94), width=3)
bg.save(os.path.join(out, "map_preview_bg.png"))
