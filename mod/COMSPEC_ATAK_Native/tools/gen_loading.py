"""Écrans de chargement ATHENA : pose le vrai QR (https://athena.ttrd.fr) à la place du QR décoratif des affiches,
puis sort les images en 2048 x 1024 (taille en puissances de deux pour le PAA ; le jeu les remet en 16:9)."""
import json, os, subprocess, sys
from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
URL = "https://athena.ttrd.fr"
SRC = os.path.join(HERE, "src", "loading")
rows = json.loads(subprocess.run(["php", os.path.join(SRC, "qr.php"), URL], capture_output=True, text=True, check=True).stdout)
n = len(rows)
print("QR", n, "modules")


def plate(size, module):
    """Plaque blanche carrée, QR sombre centré (zone calme comprise)."""
    img = Image.new("RGB", (size, size), (255, 255, 255))
    d = ImageDraw.Draw(img)
    off = (size - n * module) // 2
    assert off >= 2 * module, "zone calme trop petite"
    for y, row in enumerate(rows):
        for x, ch in enumerate(row):
            if ch == "1":
                d.rectangle((off + x * module, off + y * module, off + (x + 1) * module - 1, off + (y + 1) * module - 1), fill=(8, 10, 9))
    return img


# Usage : python gen_loading.py <dossier des trois affiches d'origine 1.png 2.png 3.png, 1672 x 941> [hemtt]
# (affiche, coin haut gauche de la plaque, côté) : relevés sur les affiches d'origine. Les sources gardées dans
# src/loading (loading_N.jpg) portent déjà le vrai QR : ne pas les repasser ici.
JOBS = {"2": ((772, 695), 137), "3": ((769, 685), 139)}
ORIG = sys.argv[1]
HEMTT = sys.argv[2] if len(sys.argv) > 2 else "hemtt"
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
for name in "123":
    im = Image.open(os.path.join(ORIG, name + ".png")).convert("RGB")
    if name in JOBS:
        (x, y), size = JOBS[name]
        im.paste(plate(size, 4), (x, y))
    im.save(os.path.join(SRC, "loading_%s.jpg" % name), quality=92)
    png = os.path.join(ORIG, "loading_%s_co.png" % name)
    im.resize((2048, 1024), Image.LANCZOS).save(png)
    paa = os.path.join(OUT, "loading_%s_co.paa" % name)
    if os.path.exists(paa):
        os.remove(paa)  # hemtt ne remplace pas un PAA existant
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True)
print("ok")
