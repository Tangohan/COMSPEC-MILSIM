"""
Panneau de consignes de la mission de test (missions/COMSPEC_FB_Range.Stratis/images/consignes.paa), 1024 x 512.
    py -3 gen_range_sign.py      (Pillow ; conversion PAA par ImageToPAA d'Arma 3 Tools, ou ARMA3TOOLS)
"""
import os
import subprocess
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
MISSION = os.path.join(HERE, "..", "missions", "COMSPEC_FB_Range.Stratis", "images")
FONTS = r"C:\Windows\Fonts"

LINES = [
    ("COMSPEC FLASHBANG - CHAMP DE TEST", 46, (255, 255, 255)),
    ("", 14, None),
    ("Flash ×1 à ×9 : nombre de détonations    Drill : bleue, inerte", 30, (235, 235, 235)),
    ("G : lancer      Ctrl+G : changer de grenade", 30, (235, 235, 235)),
    ("", 14, None),
    ("A  champ ouvert : cercle à 25 m, IA de 2 à 35 m", 28, (255, 214, 120)),
    ("B  pièce fermée   C  pièce à fenêtres", 28, (255, 214, 120)),
    ("D  couloir   E  hangar   F  délais : 1,5 s / 3 s / 8 s", 28, (255, 214, 120)),
    ("", 14, None),
    ("Caisses Flash et délais : jour / nuit, zones, recharger", 28, (235, 235, 235)),
    ("Comparer : JVN, lunette, dos tourné, derrière un mur", 28, (235, 235, 235)),
    ("Consignes complètes : carte > Flashbang", 28, (170, 200, 255)),
]


def main():
    img = Image.new("RGB", (1024, 512), (24, 30, 36))
    d = ImageDraw.Draw(img)
    d.rectangle([10, 10, 1013, 501], outline=(255, 140, 0), width=6)
    y = 34
    for text, size, col in LINES:
        if text:
            font = ImageFont.truetype(os.path.join(FONTS, "arialbd.ttf" if size >= 40 else "arial.ttf"), size)
            d.text((44, y), text, font=font, fill=col)
        y += size + 10
    os.makedirs(MISSION, exist_ok=True)
    png = os.path.join(HERE, "render", "consignes_co.png")
    img.save(png)
    tools = os.environ.get("ARMA3TOOLS", r"F:\SteamLibrary\steamapps\common\Arma 3 Tools")
    paa = os.path.join(MISSION, "consignes.paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([os.path.join(tools, "ImageToPAA", "ImageToPAA.exe"), png, paa], check=True)
    print("panneau", paa)


if __name__ == "__main__":
    main()
