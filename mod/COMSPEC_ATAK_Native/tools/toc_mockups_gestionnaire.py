#!/usr/bin/env python3
"""
Maquettes (PNG) du tableau des écrans TOC et de la mire BROADCAST (ATAK natif 4.8.0).
Ce ne sont PAS des captures du jeu : images dessinées hors jeu, avec les mêmes nombres que le code
(mire : table _smpte de fn_tocBroadcast.sqf, lue telle quelle ; couleurs : table _tokens de fn_tocTheme.sqf).

  python -I mod/COMSPEC_ATAK_Native/tools/toc_mockups_gestionnaire.py [dossier de sortie]
  -> tableau_ecrans.png, mire_broadcast.png, aucun_signal.png
"""
import json
import re
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[3]
TOC = ROOT / "mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc"
OUT = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT.parent / "_toc_maquettes" / "gestionnaire"
FONT_DIR = ROOT / "public/assets/fonts/patch-studio"


def sqf_json(path, name):
    src = path.read_text(encoding="utf-8")
    m = re.search(r"private\s+" + re.escape(name) + r"\s*=\s*(\[.*?\]);\s*\n\s*// ---- FIN", src, re.S)
    return json.loads(m.group(1))


TOK = {k: v for k, v in sqf_json(TOC / "fn_tocTheme.sqf", "_tokens")}
SMPTE = sqf_json(TOC / "fn_tocBroadcast.sqf", "_smpte")


def font(px, bold=False, mono=False):
    cands = []
    if mono:
        cands += [Path("C:/Windows/Fonts/consolab.ttf" if bold else "C:/Windows/Fonts/consola.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf")]
    cands += [FONT_DIR / ("RobotoCondensed-Bold.ttf" if bold else "RobotoCondensed.ttf"), FONT_DIR / "RobotoCondensed.ttf",
              Path("C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")]
    for p in cands:
        if p.exists():
            return ImageFont.truetype(str(p), int(px))
    return ImageFont.load_default()


def text(d, xy, s, px, col, bold=False, mono=False, anchor="la"):
    d.text(xy, s, font=font(px, bold, mono), fill=col, anchor=anchor)


def mire(w=1600, h=900):
    im = Image.new("RGB", (w, h), "black")
    d = ImageDraw.Draw(im)
    for _name, ry, rh, cells in SMPTE:
        for cx, cw, rgb in cells:
            d.rectangle([round(cx * w), round(ry * h), round((cx + cw) * w), round((ry + rh) * h)], fill=tuple(round(c * 255) for c in rgb))
    d.rectangle([0.25 * w, 0.26 * h, 0.75 * w, 0.46 * h], fill=(5, 5, 5))
    text(d, (w / 2, 0.307 * h), "TOC ALPHA · CANAL 1", 56, "white", bold=True, anchor="mm")
    text(d, (w / 2, 0.37 * h), "20:52:47 Z  ·  22:52:47 LOCAL", 30, (217, 217, 217), mono=True, anchor="mm")
    d.rectangle([0.405 * w, 0.418 * h, 0.405 * w + 18, 0.418 * h + 18], fill=(230, 26, 26))
    text(d, (w / 2, 0.425 * h), "EN DIRECT", 30, (217, 217, 217), bold=True, anchor="mm")
    return im


def nosig(w=1600, h=900):
    im = Image.new("RGB", (w, h), TOK["bg0"])
    d = ImageDraw.Draw(im)
    d.rectangle([0.04 * w, 0.07 * h, 0.96 * w, 0.93 * h], outline=TOK["lineStrong"], width=3)
    text(d, (w / 2, 0.41 * h), "AUCUN SIGNAL", 120, TOK["text2"], bold=True, anchor="mm")
    text(d, (w / 2, 0.53 * h), "NO SIGNAL · MUR NORD", 40, TOK["muted"], anchor="mm")
    text(d, (w / 2, 0.625 * h), "Le serveur n'a encore publié aucun flux pour ce camp", 30, TOK["muted"], anchor="mm")
    text(d, (w / 2, 0.79 * h), "20:52:47 Z  ·  22:52:47 LOCAL", 36, TOK["text2"], mono=True, anchor="mm")
    d.rectangle([0.33 * w, 0.86 * h, 0.45 * w, 0.866 * h], fill=TOK["accent"])
    return im


def tableau(w=1920, h=1080):
    im = Image.new("RGB", (w, h), (24, 28, 30))
    d = ImageDraw.Draw(im)
    x0, y0, ww, hh = 134, 108, 1652, 864
    u = 6.5
    d.rectangle([x0, y0, x0 + ww, y0 + hh], fill=TOK["bg0"])
    d.rectangle([x0, y0, x0 + ww, y0 + 6 * u], fill=TOK["bg2"])
    d.rectangle([x0, y0 + 6 * u, x0 + ww, y0 + 6.4 * u], fill=TOK["accent"])
    text(d, (x0 + 14, y0 + 3 * u), "Tableau des écrans TOC · 14 dalle(s) · 1 salle(s)", 24, TOK["text"], bold=True, anchor="lm")
    rows = [["Ajouter", "Dupliquer", "Supprimer", "Lier à la salle", "Générer la salle", "Générer tous les flux", "Disposition"],
            ["Aller", "Identifier", "Écrire un texte", "Diffuser dans la salle", "Arrêter la diffusion", "Enregistrer", "Charger", "Fermer"]]
    bw = (ww - 2 * u) / 8
    for ri, row in enumerate(rows):
        for i, lbl in enumerate(row):
            bx, by = x0 + u + i * bw, y0 + 7.4 * u + ri * 5.6 * u
            d.rectangle([bx, by, bx + bw - 4, by + 5 * u], fill=TOK["bg3"], outline=TOK["line"])
            text(d, (bx + (bw - 4) / 2, by + 2.5 * u), lbl, 19, TOK["text"], anchor="mm")
    top = y0 + 19.4 * u
    lw = ww * 0.66
    lh = hh - 19.4 * u - 13 * u
    cols = [0, 0.04, 0.24, 0.5, 0.63, 0.7, 0.87, 0.93]
    heads = ["", "Nom", "Type et dalle", "Salle", "Dist.", "Source montrée", "Lum.", "État"]
    for c, hd in zip(cols, heads):
        text(d, (x0 + u + c * lw + 6, top + 14), hd, 18, TOK["muted"], anchor="lm")
    data = [
        ("●", "MUR NORD", "Mur d'images 3x2 · dalle 1/6", "SALLE TOC", "6 m", "carte et flux", "100 %", "en ligne", "accent"),
        ("●", "   MUR NORD · 2", "Mur d'images 3x2 · dalle 2/6", "SALLE TOC", "6 m", "satellite 1", "100 %", "en ligne", "accent"),
        ("", "   MUR NORD · 3", "Mur d'images 3x2 · dalle 3/6", "SALLE TOC", "6 m", "mosaïque 2 × 2", "100 %", "en ligne", "accent"),
        ("", "   MUR NORD · 4", "Mur d'images 3x2 · dalle 4/6", "SALLE TOC", "6 m", "réseau", "100 %", "sans signal", "amber"),
        ("", "   MUR NORD · 5", "Mur d'images 3x2 · dalle 5/6", "SALLE TOC", "6 m", "horloge et chronos", "100 %", "en ligne", "accent"),
        ("", "   MUR NORD · 6", "Mur d'images 3x2 · dalle 6/6", "SALLE TOC", "6 m", "mire (barres de couleur)", "100 %", "en ligne", "accent"),
        ("", "ÉCRAN GAUCHE", "Écran 98 pouces", "SALLE TOC", "7 m", "recopie de MUR NORD", "75 %", "en ligne", "accent"),
        ("", "ÉCRAN DROIT", "Écran 98 pouces", "SALLE TOC", "7 m", "recopie de MUR NORD", "75 %", "en ligne", "accent"),
        ("", "BANDEAU", "Bandeau horloge", "SALLE TOC", "6 m", "bandeau horloge", "100 %", "en ligne", "accent"),
        ("", "MESSAGE", "Bandeau LED 4,8 m", "SALLE TOC", "6 m", "texte à LED", "100 %", "en ligne", "accent"),
        ("", "ALPHA 1-1", "Plaque de nom", "SALLE TOC", "5 m", "escouade Alpha 1-1", "100 %", "en ligne", "accent"),
        ("", "POSTE 1", "Poste PC écran incurvé · dalle 1/3", "SALLE TOC", "3 m", "PIP du poste", "100 %", "en ligne", "accent"),
        ("", "   POSTE 1 · 2", "Poste PC écran incurvé · dalle 2/3", "SALLE TOC", "3 m", "colonne réseau et flux", "100 %", "en ligne", "accent"),
        ("", "SUSPENDU", "Écran 65 pouces suspendu", "SALLE TOC", "8 m", "éteint (veille)", "50 %", "éteint", "muted"),
    ]
    for ri, r in enumerate(data):
        ry = top + 30 + ri * 34
        if ri == 1:
            d.rectangle([x0 + u, ry, x0 + u + lw, ry + 33], fill=TOK["bg3"])
        off = r[5].startswith("éteint")
        for ci, c in enumerate(cols):
            col = TOK["accent"] if ci == 0 else (TOK[r[8]] if ci == 7 else (TOK["muted"] if off else TOK["text"]))
            text(d, (x0 + u + c * lw + 6, ry + 17), r[ci], 18, col, anchor="lm")
    # Panneau de droite
    px = x0 + lw + 3 * u
    pw = ww - lw - 4 * u
    d.rectangle([px - u, top, px + pw, top + lh], fill=TOK["bg1"])
    text(d, (px, top + 22), "MUR NORD · 2", 28, TOK["text"], bold=True, anchor="lm")
    text(d, (px, top + 50), "Mur d'images 3x2 · dalle 2/6 · en ligne", 17, TOK["text2"], anchor="lm")
    wy = top + 10.5 * u
    cw = (pw - u) / 3
    ch = min(20 * u / 2, cw * 0.5625)
    cells = ["1 · carte et flux", "2 · satellite 1", "3 · mosaïque 2 × 2", "4 · réseau", "5 · horloge", "6 · mire"]
    for i, c in enumerate(cells):
        cx, cy = px + (i % 3) * cw, wy + (i // 3) * ch
        d.rectangle([cx, cy, cx + cw * 0.97, cy + ch * 0.94], fill=TOK["accentDim"] if i == 1 else TOK["bg2"], outline=TOK["accent"] if i == 1 else TOK["line"])
        text(d, (cx + cw * 0.485, cy + ch * 0.47), c, 16, TOK["text"], anchor="mm")
    fy = wy + 20 * u + 2 * u
    fields = [("Nom", "MUR NORD · 2"), ("Source", "satellite 1"), ("Luminosité", "100 %"), ("Salle", "SALLE TOC"), ("Titre de diffusion", "TOC ALPHA · CANAL 1"),
              ("Appliquer à", "les lignes cochées"), ("Type à poser", "Écran 98 pouces sur pied"), ("Préréglage", "BRIEFING")]
    for lbl, val in fields:
        text(d, (px, fy + 15), lbl, 17, TOK["text2"], anchor="lm")
        d.rectangle([px + pw * 0.35, fy, px + pw * 0.98, fy + 4.6 * u], fill=TOK["bg2"], outline=TOK["line"])
        text(d, (px + pw * 0.35 + 8, fy + 15), val, 17, TOK["text"], anchor="lm")
        fy += 5.4 * u
    for i, lbl in enumerate(["Appliquer", "Cocher / décocher"]):
        bx = px + i * pw * 0.5
        d.rectangle([bx, fy + u, bx + pw * 0.48, fy + 6 * u], fill=TOK["bg3"], outline=TOK["line"])
        text(d, (bx + pw * 0.24, fy + 3.5 * u), lbl, 18, TOK["text"], anchor="mm")
    text(d, (x0 + u, y0 + hh - 10 * u), "Ce que montre la dalle : un affichage, un satellite, une escouade, une caméra, la mire, ou « recopie de nom » pour recopier un autre écran. Exemple : satellite 1.", 17, TOK["text2"], anchor="lm")
    text(d, (x0 + u, y0 + hh - 4 * u), "Source « satellite 1 » : aussi sur aucun autre écran.", 17, TOK["muted"], anchor="lm")
    text(d, (x0 + ww * 0.62, y0 + hh - 4 * u), "Appliqué à 2 dalle(s)", 17, TOK["accent"], anchor="lm")
    return im


if __name__ == "__main__":
    OUT.mkdir(parents=True, exist_ok=True)
    for name, img in (("tableau_ecrans.png", tableau()), ("mire_broadcast.png", mire()), ("aucun_signal.png", nosig())):
        img.save(OUT / name)
        print(OUT / name)
