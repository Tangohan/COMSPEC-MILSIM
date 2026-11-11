#!/usr/bin/env python3
"""Pictogrammes des nœuds du graphe du réseau (fn_meshGraphView) : relais (triangle), radio posée (carré), téléphone (disque),
anneau du nœud choisi. Blancs sur fond transparent, teintés par le thème à l'affichage. Usage : python -I tools/gen_wave_relay_icons.py
"""
import os
import sys

from PIL import Image, ImageDraw

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from paa_dxt import save_paa  # noqa: E402

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "data")
S = 64
K = 4  # suréchantillonnage


def canvas():
    im = Image.new("RGBA", (S * K, S * K), (255, 255, 255, 0))
    return im, ImageDraw.Draw(im)


def done(im, name):
    save_paa(im.resize((S, S), Image.LANCZOS), os.path.join(OUT, name))
    print(name)


im, d = canvas()
d.polygon([(32 * K, 5 * K), (59 * K, 57 * K), (5 * K, 57 * K)], fill=(255, 255, 255, 255))
done(im, "wr_node_relay.paa")
im, d = canvas()
d.rounded_rectangle([9 * K, 9 * K, 55 * K, 55 * K], radius=9 * K, fill=(255, 255, 255, 255))
done(im, "wr_node_mpu.paa")
im, d = canvas()
d.ellipse([7 * K, 7 * K, 57 * K, 57 * K], fill=(255, 255, 255, 255))
done(im, "wr_node_phone.paa")
im, d = canvas()
d.ellipse([3 * K, 3 * K, 61 * K, 61 * K], outline=(255, 255, 255, 255), width=4 * K)
done(im, "wr_node_ring.paa")
