#!/usr/bin/env python3
"""
QR codes fixes à scanner avec son vrai téléphone (512x512, modules noirs sur fond blanc) :
  data/drone_remote_qr_ca.paa  page /telecommande-drone, onglet FLOTTE de l'app Drone (par défaut)
  data/athena_link_qr_ca.paa   page /lier, onglet CODE de l'app Athena :
                               python3 tools/gen_remote_qr.py https://athena-comspec.eu/lier hemtt athena_link_qr_ca
  data/nineline_qr_ca.paa      9-LINE de l'app JTAC :
                               python3 tools/gen_remote_qr.py https://athena-comspec.eu/public/9-line hemtt nineline_qr_ca
Adresse par défaut : celle du site de production ; une autre en premier argument.

  python3 tools/gen_remote_qr.py [adresse] [chemin de hemtt] [nom du fichier sans extension]

Dépend de segno (python3 -m pip install segno) et de « hemtt utils paa convert ».
"""
import os
import subprocess
import sys
import tempfile

import segno
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
URL = sys.argv[1] if len(sys.argv) > 1 else "https://athena-comspec.eu/public/telecommande-drone"
HEMTT = sys.argv[2] if len(sys.argv) > 2 else "hemtt"
NAME = sys.argv[3] if len(sys.argv) > 3 else "drone_remote_qr_ca"


def main():
    qr = segno.make(URL, error="m")
    with tempfile.TemporaryDirectory() as tmp:
        raw = os.path.join(tmp, "raw.png")
        qr.save(raw, scale=1, border=4)
        img = Image.open(raw).convert("RGB")
        # Agrandi sans lissage au plus grand multiple entier tenant dans 512, centré sur fond blanc.
        k = 512 // img.width
        img = img.resize((img.width * k, img.height * k), Image.NEAREST)
        canvas = Image.new("RGBA", (512, 512), (255, 255, 255, 255))
        canvas.paste(img, ((512 - img.width) // 2, (512 - img.height) // 2))
        png = os.path.join(tmp, NAME + ".png")
        canvas.save(png)
        dst = os.path.join(OUT, NAME + ".paa")
        if os.path.exists(dst):
            os.remove(dst)  # « hemtt utils paa convert » n'écrase pas
        subprocess.run([HEMTT, "utils", "paa", "convert", png, dst], check=True)
    print(dst, URL)


if __name__ == "__main__":
    main()
