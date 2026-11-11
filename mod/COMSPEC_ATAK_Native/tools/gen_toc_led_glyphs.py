#!/usr/bin/env python3
"""
Caractères des panneaux à LED des écrans TOC (affichage TEXTE, plaque de nom, bandeau horloge) : une image par caractère.

Pourquoi des images : dans une texture d'interface (« ui » sur un objet), le moteur dessine le texte comme sur un écran
4:3 : la largeur d'un caractère vaut hauteur x 3/4 x chasse, en part de la largeur de la texture, quelle que soit la
taille de la texture. Sur une dalle 8:1 le texte est donc étiré 6 fois (4:1 : 3 fois ; 16:1 : 12 fois ; 16:9 : 1,33 fois)
et aucune taille de police ne le corrige. Une image, elle, prend exactement le rectangle qu'on lui donne : fn_tocText
pose une image par caractère, dans une case au vrai format de la dalle.

Chaque caractère occupe une case de CELL_W x CELL_H (chasse fixe ; du haut des accents des capitales au bas des jambages),
blanc sur noir (teinté par le jeu : vert, ambre, rouge, blanc), rendue en 128 x 256 puis convertie en PAA (DXT1, mipmaps).
Les nombres de la case (largeur / hauteur, haut et bas des capitales) sont ceux de la table _k de fn_tocText.sqf
(cellW, capTop, capBot) : tests/Unit/AtakTocTextAspectTest.php vérifie qu'ils concordent et que chaque code de la table
_glyphs a son image.

Sortie : Sources/addons/main/data/toc/led/<code Unicode>.paa (0.paa : caractère inconnu)
Usage  : python3 tools/gen_toc_led_glyphs.py [--ttf CascadiaMono.ttf] [--hemtt hemtt]
Police : Cascadia Mono Bold (Microsoft, licence SIL OFL 1.1), variable ; toute police à chasse fixe convient.
Dépendances : Pillow (FreeType), HEMTT (hemtt utils paa convert).
"""
import argparse, os, subprocess, tempfile
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data", "toc", "led")
# Case d'un caractère, en millièmes du cadratin : au-dessus de la ligne de base (accent d'une capitale), dessous (jambage).
ASC, DESC = 950, 240
CELL_H = ASC + DESC
# Rendu : case de 1024 px de haut, réduite en 128 x 256.
SUPER, TEX_W, TEX_H = 1024, 128, 256
# Plages de codes (les mêmes que la table _glyphs de fn_tocText.sqf) : ASCII, Latin-1, puis ponctuation et symboles.
RANGES = [[33, 126], [161, 255], [338, 339], [376, 376], [8211, 8212], [8216, 8218], [8220, 8222], [8226, 8226],
          [8230, 8230], [8249, 8250], [8364, 8364], [8592, 8595], [9632, 9632], [9650, 9650], [9660, 9660], [9679, 9679]]


def metrics(ttf):
    """[largeur / hauteur de la case, haut des capitales, bas des capitales] (parts de la hauteur de case)."""
    f = ImageFont.truetype(ttf, 1000)
    try:
        f.set_variation_by_name("Bold")
    except OSError:
        pass
    cap = -f.getbbox("H", anchor="ls")[1]
    return f, round(f.getlength("M") / CELL_H, 4), round((ASC - cap) / CELL_H, 3), round(ASC / CELL_H, 3)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--ttf", default="C:/Windows/Fonts/CascadiaMono.ttf")
    ap.add_argument("--hemtt", default="hemtt")
    ap.add_argument("--print", action="store_true", help="affiche seulement les nombres de la case")
    a = ap.parse_args()
    f1000, cell_w, cap_top, cap_bot = metrics(a.ttf)
    print(f"cellW {cell_w}  capTop {cap_top}  capBot {cap_bot}")
    if a.print:
        return
    em = SUPER * 1000 / CELL_H
    font = ImageFont.truetype(a.ttf, round(em))
    try:
        font.set_variation_by_name("Bold")
    except OSError:
        pass
    adv = font.getlength("M")
    sw = round(adv)
    base = round(SUPER * ASC / CELL_H)

    def sig(ch):
        im = Image.new("L", (sw * 2, SUPER), 0)
        ImageDraw.Draw(im).text((0, base), ch, font=font, fill=255, anchor="ls")
        return im.tobytes()

    missing = sig(chr(0xFFFF))
    os.makedirs(OUT, exist_ok=True)
    codes = [0] + [c for lo, hi in RANGES for c in range(lo, hi + 1)]
    done, skipped = 0, []
    with tempfile.TemporaryDirectory() as tmp:
        for code in codes:
            img = Image.new("L", (sw, SUPER), 0)
            d = ImageDraw.Draw(img)
            if code == 0:
                # Caractère inconnu : cadre creux à la hauteur des capitales.
                m = round(sw * 0.16)
                t = round(sw * 0.1)
                d.rectangle([m, round(SUPER * cap_top), sw - m, round(SUPER * cap_bot)], outline=255, width=t)
            else:
                ch = chr(code)
                # Caractère absent de la police (même dessin que le caractère « inconnu » de la police) : pas d'image.
                if sig(ch) == missing:
                    skipped.append(code)
                    continue
                # Centré sur la chasse (les symboles plus larges que la case sont ramenés à sa largeur).
                w = font.getlength(ch)
                if w > adv * 1.05:
                    big = Image.new("L", (round(w), SUPER), 0)
                    ImageDraw.Draw(big).text((0, base), ch, font=font, fill=255, anchor="ls")
                    img = big.resize((sw, SUPER), Image.LANCZOS)
                else:
                    d.text(((adv - w) / 2, base), ch, font=font, fill=255, anchor="ls")
            png = os.path.join(tmp, f"{code}.png")
            Image.merge("RGB", [img.resize((TEX_W, TEX_H), Image.LANCZOS)] * 3).save(png)
            paa = os.path.join(OUT, f"{code}.paa")
            if os.path.exists(paa):
                os.remove(paa)
            subprocess.run([a.hemtt, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            done += 1
    print(f"{done} images dans {os.path.normpath(OUT)}")
    if skipped:
        print("absents de la police (à retirer de RANGES et de _glyphs) :", skipped)


if __name__ == "__main__":
    main()
