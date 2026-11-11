#!/usr/bin/env python3
"""Maquette PNG du texte des écrans TOC hors 16:9 : bandeaux LED 8:1 et 16:1, plaques de nom et bandeau horloge 4:1.

Chaque panneau est dessiné à son vrai rapport largeur / hauteur, avec le même calcul que le jeu : rien n'est recopié,
les nombres viennent des tables JSON de functions/toc/fn_tocText.sqf (_k : case d'un caractère ; _fits : tailles et
marges par type de panneau ; _panels : rapports des dalles) et de fn_tocTheme.sqf (couleurs, régions des faces 4:1).
Règle d'ajustement rejouée telle quelle (« _planOf », « label », « _marquee ») : le texte qui tient est fixe et centré ;
trop long, il est réduit ; plus long encore, il défile en boucle (bandeaux) ou finit par « … » (plaques).
Caractères : même police et même case que tools/gen_toc_led_glyphs.py (les images du jeu).

Usage : python -I tools/toc_mockups_texte.py [dossier de sortie]
        (défaut : E:/Developpement/compsec.ttrd.fr/_toc_maquettes/gestionnaire) -> texte_bandeaux.png
"""
import importlib.util
import json
import math
import re
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
TOC = ROOT / "mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc"
FONT_DIR = ROOT / "public/assets/fonts/patch-studio"
OUT = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("E:/Developpement/compsec.ttrd.fr/_toc_maquettes/gestionnaire")

_spec = importlib.util.spec_from_file_location("gen_toc_led_glyphs", HERE / "gen_toc_led_glyphs.py")
GEN = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(GEN)
LED_TTF = "C:/Windows/Fonts/CascadiaMono.ttf"


def sqf_json(src, name):
    m = re.search(r"private " + re.escape(name) + r" = (\[.*?\]);", src, re.S)
    if not m:
        raise SystemExit(f"table {name} introuvable")
    return json.loads(m.group(1))


TEXT_SRC = (TOC / "fn_tocText.sqf").read_text(encoding="utf-8")
THEME_SRC = (TOC / "fn_tocTheme.sqf").read_text(encoding="utf-8")
K = dict(sqf_json(TEXT_SRC, "_k"))
FITS = {r[0]: r[1:] for r in sqf_json(TEXT_SRC, "_fits")}
PANELS = {r[0]: r[1:] for r in sqf_json(TEXT_SRC, "_panels")}
TOKENS = dict(sqf_json(THEME_SRC, "_tokens"))
WALLS = {k: {r[0]: r[1:] for r in v} for k, v in sqf_json(THEME_SRC, "_walls")}
CELL_W = K["cellW"]
LED_BG = (0, 0, 0, 255)


def rgb(token, alpha=1.0):
    h = TOKENS[token]
    return (int(h[1:3], 16), int(h[3:5], 16), int(h[5:7], 16), int(round(alpha * 255)))


# ------------------------------------------------------------------------------------------------ caractères à LED
_FONTS, _CELLS = {}, {}


def cell(ch, h):
    """Image (niveaux de gris) d'un caractère dans sa case : hauteur h px, largeur cellW x h (comme les images du jeu)."""
    key = (ch, h)
    if key not in _CELLS:
        em = max(4, round(h * 1000 / GEN.CELL_H))
        if em not in _FONTS:
            f = ImageFont.truetype(LED_TTF, em)
            try:
                f.set_variation_by_name("Bold")
            except OSError:
                pass
            _FONTS[em] = f
        f = _FONTS[em]
        w = max(1, round(CELL_W * h))
        img = Image.new("L", (w, h), 0)
        ImageDraw.Draw(img).text(((w - f.getlength(ch)) / 2, h * GEN.ASC / GEN.CELL_H), ch, font=f, fill=255, anchor="ls")
        _CELLS[key] = img
    return _CELLS[key]


def run(dst, txt, x, y, cell_h, col):
    """Bande de caractères posée dans dst (qui coupe ce qui dépasse) : une case par caractère, depuis (x, y)."""
    cw = CELL_W * cell_h
    hh = max(1, round(cell_h))
    for i, ch in enumerate(txt):
        if ch == " ":
            continue
        g = cell(ch, hh)
        tint = Image.new("RGBA", g.size, col)
        tint.putalpha(g)
        dst.alpha_composite(tint, (round(x + i * cw), round(y))) if -g.size[0] < x + i * cw < dst.size[0] else None
    return len(txt) * cw


# ------------------------------------------------------------------------------------- règles du jeu, rejouées
def plan(txt, wi, ux, kind, always=False):
    """fn_tocText « _planOf » : (0 fixe une ligne | 1 fixe deux lignes | 2 défilement, hauteur de case, lignes)."""
    f1, f_min, f2 = FITS[kind][0:3]
    n = len(txt)
    w1 = n * CELL_W * ux
    if always:
        return 2, f1, []
    f_fit = wi / max(w1, 1e-6)
    if f_fit >= f1:
        return 0, f1, []
    if f2 > 0 and f_fit < f2:
        best = -1
        for i in range(1, n - 1):
            if txt[i] == " " and (best < 0 or abs(i - n / 2) < abs(best - n / 2)):
                best = i
        if best > 0:
            f_l = min(f2, f_fit * n / max(best, n - best - 1))
            if f_l >= f_min:
                return 1, f_l, [txt[:best], txt[best + 1:]]
    if f_fit >= f_min:
        return 0, f_fit, []
    return 2, f1, []


def marquee(tw, pw, spd, gap, t):
    """fn_tocText « _marquee » (boucle) : x du premier texte, longueur de la boucle."""
    loop = max(tw + gap, pw)
    return -((t * spd) % loop), loop


def led_panel(ratio, h, txt, tok="accent", kind="LED", t=None, speed=1, align=0):
    """Affichage TEXTE (« _render ») sur une dalle de rapport donné, haute de h px. t : heure du serveur (défilement).
    Rend (image, description de ce que la règle a décidé)."""
    w = round(ratio * h)
    f1, f_min, f2, ins, bth, pad_x = FITS[kind]
    img = Image.new("RGBA", (w, h), LED_BG)
    d = ImageDraw.Draw(img)
    col = rgb(tok, 0.55)
    i, b = ins * h, max(1, bth * h)
    for box in ((i, i, w - i, i + b), (i, h - i - b, w - i, h - i), (i, i, i + b, h - i), (w - i - b, i, w - i, h - i)):
        d.rectangle([round(v) for v in box], fill=col)
    x0 = (ins + bth + pad_x) * h
    wi, hi = w - 2 * x0, h * (1 - 2 * (ins + bth))
    clip = Image.new("RGBA", (round(wi), round(hi)), (0, 0, 0, 0))
    # Tout en pixels : une « hauteur de dalle » vaut h px dans les deux sens (pixels carrés, dalle au vrai rapport).
    mode, f, lines = plan(txt, wi, h, kind)
    lh = f * h
    if mode == 1:
        y0 = (hi - 2.08 * lh) / 2
        for k, ln in enumerate(lines):
            tw = len(ln) * CELL_W * lh
            run(clip, ln, (wi - tw) / 2, y0 + k * 1.08 * lh, lh, rgb(tok))
        what = f"deux lignes fixes, case {f:.2f}"
    elif mode == 0:
        tw = len(txt) * CELL_W * lh
        run(clip, txt, [(wi - tw) / 2, 0, wi - tw][align], (hi - lh) / 2, lh, rgb(tok))
        what = "fixe, centré, pleine taille" if f >= f1 else f"fixe, réduit pour tenir (case {f:.2f} au lieu de {f1:.2f})"
    else:
        step = CELL_W * f1 * h
        spd = K["cps"][speed] * step
        gap = K["gapChars"] * step
        tw = len(txt) * step
        t0 = math.floor((t or 0) * spd / step) * step / spd
        px, loop = marquee(tw, wi, spd, gap, t0)
        run(clip, txt, px, (hi - lh) / 2, lh, rgb(tok))
        run(clip, txt, px + loop, (hi - lh) / 2, lh, rgb(tok))
        what = f"défilement en boucle, {K['cps'][speed]} caractères par seconde"
    img.alpha_composite(clip, (round(x0), round(h * (ins + bth))))
    return img, what


def label(dst, rect, txt, tok, asp_h, minimum=0.6):
    """fn_tocText « label » : ligne fixe dans rect (px) ; case à la hauteur du rectangle, réduite, puis « … »."""
    x, y, rw, rh = rect
    cw = CELL_W * rh
    fit = min(rw / (max(len(txt), 1) * cw), 1.0)
    if fit < minimum:
        fit = minimum
        keep = max(math.floor(rw / (cw * fit)) - 1, 1)
        txt = txt[:keep].rstrip() + "…"
    clip = Image.new("RGBA", (round(rw), round(rh)), (0, 0, 0, 0))
    run(clip, txt, 0, rh * (1 - fit) / 2, rh * fit, rgb(tok))
    dst.alpha_composite(clip, (round(x), round(y)))
    return txt, fit


def plate(h, name, sub, live=True):
    """Plaque de nom 4:1 (fn_tocBoard « PLATE ») : régions du thème (1600 x 400 virtuels) à l'échelle."""
    s = h / 400
    img = Image.new("RGBA", (round(1600 * s), h), LED_BG)
    d = ImageDraw.Draw(img)
    L = WALLS["PLATE"]
    bx, by, bw, bh = L["bar"]
    d.rectangle([bx * s, by * s, (bx + bw) * s, (by + bh) * s], fill=rgb("accent"))
    shown, fit = label(img, [v * s for v in L["name"]], name.upper(), "text", h, 0.5)
    if sub:
        x, y, w, hh = L["sub"]
        d.rectangle([(x + 4) * s, (y + hh / 2 - 14) * s, (x + 32) * s, (y + hh / 2 + 14) * s], fill=rgb("accent" if live else "lost"))
        label(img, [(x + 44) * s, y * s, (w - 44) * s, hh * s], sub, "text2", h)
    return img, ("pleine taille" if fit >= 1 else ("réduit à %d %%" % round(fit * 100) + (", coupé avec « … »" if shown.endswith("…") else "")))


def clock_strip(h):
    """Bandeau horloge 4:1 (fn_tocBoard « STRIP ») : mêmes régions, caractères à LED."""
    s = h / 400
    img = Image.new("RGBA", (round(1600 * s), h), LED_BG)
    d = ImageDraw.Draw(img)
    L = WALLS["STRIP"]
    for key, txt, tok in (("zLbl", "ZULU", "accent"), ("zDig", "20:41:17", "text"), ("zSub", "DTG 112041Z NOV 26", "text2"),
                          ("lLbl", "LOCAL", "muted"), ("lDig", "12:03", "text2"), ("lSub", "11 NOV 2035", "text2"),
                          ("cLbl", "HEURE H", "text"), ("cDig", "H+00:35:42", "amber"), ("cSub", "en marche", "muted")):
        label(img, [v * s for v in L[key]], txt, tok, h)
    for key in ("div1", "div2"):
        x, y, w, hh = L[key]
        d.rectangle([x * s, y * s, (x + w) * s, (y + hh) * s], fill=rgb("line"))
    return img


# -------------------------------------------------------------------------------------------------------- planche
def caption(txt, w):
    f = ImageFont.truetype(str(FONT_DIR / "RobotoCondensed.ttf"), 22)
    img = Image.new("RGBA", (w, 30), (0, 0, 0, 0))
    ImageDraw.Draw(img).text((0, 2), txt, font=f, fill=rgb("text2"))
    return img


def row(images, gap=24):
    w = sum(i.size[0] for i in images) + gap * (len(images) - 1)
    out = Image.new("RGBA", (w, max(i.size[1] for i in images)), (0, 0, 0, 0))
    x = 0
    for i in images:
        out.alpha_composite(i, (x, 0))
        x += i.size[0] + gap
    return out


def sheet(blocks, width, pad=32, bg=(22, 24, 27, 255)):
    """Blocs (légende, image) l'un sous l'autre, alignés à gauche, sur le fond neutre des planches de maquette."""
    h = pad + sum(30 + 8 + img.size[1] + pad for _, img in blocks)
    out = Image.new("RGBA", (width + 2 * pad, h), bg)
    y = pad
    for txt, img in blocks:
        out.alpha_composite(caption(txt, width), (pad, y))
        out.alpha_composite(img, (pad, y + 38))
        y += 38 + img.size[1] + pad
    return out


def main():
    W = 1920
    r8, r16, r4 = PANELS["LED8"][1], PANELS["LED16"][1], PANELS["STRIP"][1]
    blocks = []
    img, what = led_panel(r8, round(W / r8), "ÉLÉMENT")
    blocks.append((f"Bandeau LED {r8:g}:1 · « ÉLÉMENT » tient : {what}", img))
    img, what = led_panel(r8, round(W / r8), "MESSAGE · 112037Z NOV 26", "amber")
    blocks.append((f"Bandeau LED {r8:g}:1 · 24 caractères (texte de la salle posée) : {what}", img))
    long_txt = "OPÉRATION HORIZON · H-00:42:10 · COUVRE-FEU RADIO À 21:00Z · MÉTÉO : VENT 270/15 KT, VISI 8 KM · RELÈVE DES POSTES 18:00Z"
    img, what = led_panel(r16, round(W / r16), long_txt, t=0.0)
    blocks.append((f"Bandeau LED {r16:g}:1 · {len(long_txt)} caractères : {what} · image 1 (départ sur un bord de caractère)", img))
    img, _ = led_panel(r16, round(W / r16), long_txt, t=15.3)
    blocks.append((f"Bandeau LED {r16:g}:1 · même texte 15 s plus tard : fin du texte, blanc de {K['gapChars']} caractères, reprise sans couture", img))
    ph = round((W - 2 * 24) / 3 / r4)
    plates, notes = [], []
    for name, sub, live in (("ÉLÉMENT 1", "4 nœud(s) ATAK · 3 en direct", True), ("Section appui mortier 81", "2 nœud(s) ATAK · 0 en direct", True),
                            ("Groupement tactique interarmes de reconnaissance nord", "hors réseau", False)):
        img, what = plate(ph, name, sub, live)
        plates.append(img)
        notes.append(what)
    blocks.append((f"Plaques de nom {r4:g}:1 · nom court : {notes[0]} · nom long : {notes[1]} · nom trop long : {notes[2]}", row(plates)))
    blocks.append((f"Bandeau horloge {r4:g}:1 · mêmes caractères à LED, chaque valeur réduite pour tenir dans sa région", clock_strip(round(W / 2 / r4) * 2 // 2)))
    OUT.mkdir(parents=True, exist_ok=True)
    out = sheet(blocks, W)
    out.convert("RGB").save(OUT / "texte_bandeaux.png")
    print(OUT / "texte_bandeaux.png", out.size)


if __name__ == "__main__":
    main()
