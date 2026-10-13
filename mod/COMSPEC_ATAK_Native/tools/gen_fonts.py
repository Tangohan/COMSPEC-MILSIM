#!/usr/bin/env python3
"""
Polices du téléphone au format Arma 3 (.fxy + .paa), sans FontToTga.

Le format a été relevé sur les polices d'ACE3 (addons/fonts) :
  .fxy  = "BIFo" + 01 01 00 00, puis une entrée de 7 entiers 16 bits (petit-boutiste) par caractère, triées par code :
          code, page (1 = <nom>-01.paa), x, y, largeur, hauteur, avance (pas du curseur) ; codes sans trou par blocs
          de 32 alignés (0-31, 32-63…), les absents en entrée vide
  .paa  = planche de glyphes blancs sur fond transparent, côtés en puissances de deux.
Chaque glyphe occupe une case de la hauteur de ligne (ascendante + descendante), alignée en haut : la ligne de base
est la même pour tous. Les caractères absents d'une police sont laissés à Roboto Condensed (repli déclaré dans
CfgFontFamilies, config.cpp).

Sortie : Sources/addons/main/fonts/<famille>/<Nom><taille>.fxy et <Nom><taille>-01.paa
Usage   : python3 tools/gen_fonts.py --ttf <dossier des .ttf> [--hemtt <chemin de hemtt>]
          (.ttf de Google Fonts, licence OFL : barlowcondensed/, b612/, rajdhani/, sharetech/)
Dépendances : Pillow, fontTools, HEMTT (hemtt utils paa convert).
"""
import argparse, os, struct, subprocess, tempfile
from PIL import Image, ImageDraw, ImageFont
from fontTools.ttLib import TTFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "fonts")
# Tailles (points, comme FontToTga) : le moteur prend la plus proche de la hauteur demandée.
SIZES = [8, 10, 12, 14, 16, 18, 20, 22, 24, 28, 34, 46]
# Famille -> [(nom Arma, fichier .ttf)]
FAMILIES = {
    "barlow": [("BarlowCondensed-Medium", "barlowcondensed/BarlowCondensed-Medium.ttf"),
               ("BarlowCondensed-Bold", "barlowcondensed/BarlowCondensed-Bold.ttf"),
               ("BarlowCondensed-Regular", "barlowcondensed/BarlowCondensed-Regular.ttf")],
    "b612": [("B612-Regular", "b612/B612-Regular.ttf"), ("B612-Bold", "b612/B612-Bold.ttf")],
    "rajdhani": [("Rajdhani-Medium", "rajdhani/Rajdhani-Medium.ttf"), ("Rajdhani-Bold", "rajdhani/Rajdhani-Bold.ttf")],
    "sharetech": [("ShareTech-Regular", "sharetech/ShareTech-Regular.ttf")],
}
# Latin-1 + ponctuation et symboles employés par les apps (le reste passe au repli).
CHARS = [c for c in range(32, 127)] + [c for c in range(160, 256)] + [ord(c) for c in
         "ŒœŸΔ–—‘’‚“”„•…‰‹›€↑↓←→⇄−∞≈≠─│■▲▶▸▼◀○●★☐☑✓✗"]


def build(name, ttf, size, outdir, hemtt):
    cmap = TTFont(ttf).getBestCmap()
    em = round(size * 4 / 3)
    font = ImageFont.truetype(ttf, em)
    asc, desc = font.getmetrics()
    lh = asc + desc
    glyphs = []
    for code in sorted(set(CHARS)):
        if code not in cmap and code != 32:
            continue
        ch = chr(code)
        adv = max(1, round(font.getlength(ch)))
        l, _, r, _ = font.getbbox(ch, anchor="la")
        w = max(adv, r, 1)
        glyphs.append((code, ch, w, adv, min(l, 0)))
    # Rangement en lignes sur la plus petite planche carrée qui suffit.
    for side in (128, 256, 512, 1024, 2048):
        x = y = 1
        pos = []
        ok = True
        for g in glyphs:
            if x + g[2] + 1 > side:
                x, y = 1, y + lh + 1
            if y + lh + 1 > side:
                ok = False
                break
            pos.append((x, y))
            x += g[2] + 1
        if ok:
            break
    else:
        raise SystemExit(f"{name}{size} : planche trop grande")
    img = Image.new("RGBA", (side, side), (255, 255, 255, 0))
    mask = Image.new("L", (side, side), 0)
    d = ImageDraw.Draw(mask)
    drawn = {}
    for (code, ch, w, adv, lft), (gx, gy) in zip(glyphs, pos):
        if code != 32:
            d.text((gx - lft, gy), ch, font=font, fill=255, anchor="la")
        drawn[code] = (code, 1, gx, gy, w, lh, adv)
    # Table continue par blocs de 32 codes alignés, comme les .fxy d'ACE3 (0-351, 992-1087, 8176-8191 : aucun trou
    # dans un bloc). Une table à trous (0 puis 32…) décale les caractères en jeu (« RÉGLAGES » lu « 2ÉGLAGES », 1.83.0).
    # Codes absents : entrée vide (largeur et hauteur 0), comme les minuscules de la police d'ACE3.
    blocks = sorted({c // 32 for c in drawn} | {0})
    recs = [drawn.get(c, (c, 1, 1, 1, 0, 0, 0)) for b in blocks for c in range(b * 32, b * 32 + 32)]
    img.putalpha(mask)
    base = os.path.join(outdir, f"{name}{size}")
    with open(base + ".fxy", "wb") as f:
        f.write(b"BIFo" + bytes([1, 1, 0, 0]))
        for r in recs:
            f.write(struct.pack("<7H", *r))
    with tempfile.TemporaryDirectory() as tmp:
        png = os.path.join(tmp, "p.png")
        img.save(png)
        paa = base + "-01.paa"
        if os.path.exists(paa):
            os.remove(paa)
        subprocess.run([hemtt, "utils", "paa", "convert", png, paa], check=True,
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    return lh


# Classes CfgFontFamilies (ui/fonts.hpp) : famille -> (dossier, nom Arma, gras pour le repli Roboto).
# COMSPEC_Barlow* (classes de config et balises <t font=…>) restent sur Roboto Condensed du jeu (ROBOTO ci-dessous) :
# la police embarquée ne s'affiche que si le joueur la choisit (Réglages > Police).
CLASSES = [("COMSPEC_BarlowC", "barlow", "BarlowCondensed-Medium", False), ("COMSPEC_BarlowCBold", "barlow", "BarlowCondensed-Bold", True),
           ("COMSPEC_BarlowCLight", "barlow", "BarlowCondensed-Regular", False),
           ("COMSPEC_B612", "b612", "B612-Regular", False), ("COMSPEC_B612Bold", "b612", "B612-Bold", True),
           ("COMSPEC_Rajdhani", "rajdhani", "Rajdhani-Medium", False), ("COMSPEC_RajdhaniBold", "rajdhani", "Rajdhani-Bold", True),
           ("COMSPEC_ShareTech", "sharetech", "ShareTech-Regular", False)]


ROBOTO_R = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 34, 35, 37, 46]
ROBOTO = [("COMSPEC_Barlow", "Roboto-Condensed", ROBOTO_R), ("COMSPEC_BarlowBold", "Roboto-Condensed-Bold", ROBOTO_R[1:]),
          ("COMSPEC_BarlowLight", "Roboto-Condensed", ROBOTO_R)]


def write_hpp():
    out = ["// Généré par tools/gen_fonts.py (ne pas modifier à la main) : polices embarquées du téléphone (OFL, voir fonts/*/OFL.txt).",
           "// Chaque taille est appariée à Roboto Condensed du jeu, qui dessine les caractères absents de la police.",
           "class CfgFontFamilies {",
           "    // Police par défaut du téléphone : fichiers Roboto Condensed du jeu (mêmes chemins que BCE). Les familles",
           "    // embarquées plus bas ne servent que si le joueur les choisit (Réglages > Police)."]
    for cls, d, sizes in ROBOTO:
        rows = [f'"A3\\Uifonts_f\\Data\\Fonts\\{d}\\{d}{n}"' for n in sizes]
        out.append(f"    class {cls} {{\n        fonts[] = {{\n            " + ",\n            ".join(rows) + "\n        };\n    };")
    for cls, d, name, bold in CLASSES:
        rows = []
        for s in SIZES:
            fb = (f"A3\\Uifonts_f\\Data\\Fonts\\Roboto-Condensed-Bold\\Roboto-Condensed-Bold{max(s, 9)}" if bold
                  else f"A3\\Uifonts_f\\Data\\Fonts\\Roboto-Condensed\\Roboto-Condensed{s}")
            rows.append(f'{{"\\z\\comspec_atak_native\\addons\\main\\fonts\\{d}\\{name}{s}", "{fb}"}}')
        out.append(f"    class {cls} {{\n        fonts[] = {{\n            " + ",\n            ".join(rows)
                   + "\n        };\n        spaceWidth = 0.5;\n        spacing = 0.065;\n    };")
    out.append("};")
    with open(os.path.join(HERE, "..", "Sources", "addons", "main", "ui", "fonts.hpp"), "w", encoding="utf-8") as f:
        f.write("\n".join(out) + "\n")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--ttf", required=True)
    ap.add_argument("--hemtt", default="hemtt")
    a = ap.parse_args()
    for fam, faces in FAMILIES.items():
        outdir = os.path.join(OUT, fam)
        os.makedirs(outdir, exist_ok=True)
        for name, rel in faces:
            hs = [build(name, os.path.join(a.ttf, rel), s, outdir, a.hemtt) for s in SIZES]
            print(name, "hauteurs de ligne", hs)
    write_hpp()


if __name__ == "__main__":
    main()
