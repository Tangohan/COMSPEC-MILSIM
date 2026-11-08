#!/usr/bin/env python3
"""
Icône d'inventaire du GPS de poignet, dessinée ici : aucune photo, aucune marque.

    python gen_wrist_gps_icon.py SORTIE.png
    hemtt utils paa convert SORTIE.png ../Sources/addons/main/data/item_wristgps.paa
"""
import sys

from PIL import Image, ImageDraw

S = 256
im = Image.new("RGBA", (S, S), (0, 0, 0, 0))
d = ImageDraw.Draw(im)
# Sangle
d.rectangle((88, 8, 168, 248), fill=(38, 38, 34, 255))
for y in range(14, 248, 12):
    d.line((88, y, 168, y), fill=(52, 52, 47, 255), width=2)
# Boîtier
d.rounded_rectangle((26, 62, 230, 194), radius=18, fill=(22, 22, 22, 255), outline=(66, 66, 66, 255), width=3)
# Écran
d.rectangle((42, 76, 214, 158), fill=(160, 170, 142, 255), outline=(8, 8, 8, 255), width=2)
d.rectangle((48, 82, 208, 94), fill=(26, 32, 20, 255))
for i, w in enumerate((130, 104, 142)):
    d.rectangle((50, 102 + i * 17, 50 + w, 110 + i * 17), fill=(30, 36, 24, 255))
# Boutons
for x in (50, 86, 122, 158, 190):
    d.rounded_rectangle((x, 168, x + 22, 182), radius=4, fill=(74, 74, 72, 255))
im.save(sys.argv[1])
