#!/usr/bin/env python3
"""
Icône d'inventaire du déclencheur réseau, dessinée ici : aucune photo, aucune marque.

    python gen_net_detonator_icon.py SORTIE.png
    hemtt utils paa convert SORTIE.png ../Sources/addons/main/data/item_netdetonator.paa
"""
import sys

from PIL import Image, ImageDraw

S = 256
im = Image.new("RGBA", (S, S), (0, 0, 0, 0))
d = ImageDraw.Draw(im)
# Antenne et ondes
d.rectangle((166, 14, 176, 92), fill=(40, 40, 36, 255))
for r, a in ((26, 255), (46, 190), (66, 120)):
    d.arc((171 - r, 22 - r, 171 + r, 22 + r), 200, 340, fill=(232, 176, 58, a), width=6)
# Boîtier
d.rounded_rectangle((58, 84, 198, 232), radius=14, fill=(44, 48, 40, 255), outline=(84, 88, 76, 255), width=3)
# Voyant et témoin de liaison
d.rectangle((76, 102, 180, 146), fill=(22, 26, 20, 255), outline=(8, 8, 8, 255), width=2)
for i, h in enumerate((10, 18, 26, 34)):
    d.rectangle((88 + i * 22, 140 - h, 102 + i * 22, 140), fill=(112, 196, 120, 255))
# Bouton de mise à feu sous son garde
d.rounded_rectangle((90, 162, 166, 216), radius=8, fill=(26, 28, 24, 255))
d.ellipse((108, 170, 148, 208), fill=(202, 58, 44, 255), outline=(120, 28, 20, 255), width=3)
im.save(sys.argv[1])
