#!/usr/bin/env python3
"""Maquettes PNG des écrans TOC et du PC du poste (refonte du rendu, demande de TTRD du 11/11).

Les nombres ne sont PAS recopiés ici : couleurs, tailles de texte, polices, régions et colonnes viennent des tables JSON
de functions/toc/fn_tocTheme.sqf (écrans muraux) ; grille, mise en page et boutons du PC viennent des tables _grid,
_layout, _btnDefs et _sections de functions/toc/fn_tocLaptop.sqf, posées par le même calcul que _place. Seules les données
(indicatifs, heures, lignes) sont des exemples.

Polices : Roboto Condensed (public/assets/fonts/patch-studio, la police du jeu) ; la monospace du jeu (Etelka Monospace
Pro) n'est pas libre : Consolas, tracée à pas fixe de 0,5 x la hauteur (largeur d'Etelka), à défaut DejaVu Sans Mono.
Hauteur de texte d'Arma = hauteur de ligne (ascendante + descendante) : taille em = hauteur / 1,18 pour Roboto.

Usage : python -I tools/toc_mockups.py [dossier de sortie]   (défaut : E:/Developpement/compsec.ttrd.fr/_toc_maquettes)
"""
import json
import math
import random
import re
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[3]
TOC = ROOT / "mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc"
FONT_DIR = ROOT / "public/assets/fonts/patch-studio"
OUT = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("E:/Developpement/compsec.ttrd.fr/_toc_maquettes")


# ---------------------------------------------------------------------------------------------------------------- tables
def sqf_json(src, name, pattern=None):
    m = re.search(pattern or (r"private " + re.escape(name) + r" = (\[.*?\]);"), src, re.S)
    if not m:
        raise SystemExit(f"table {name} introuvable")
    return json.loads(m.group(1))


THEME_SRC = (TOC / "fn_tocTheme.sqf").read_text(encoding="utf-8")
LAP_SRC = (TOC / "fn_tocLaptop.sqf").read_text(encoding="utf-8")
TOKENS = {k: v for k, v in sqf_json(THEME_SRC, "_tokens")}
FONTS = {k: v for k, v in sqf_json(THEME_SRC, "_fonts")}
TYPE = {k: (px, FONTS[f]) for k, px, f in sqf_json(THEME_SRC, "_type")}
SPACE = {k: v for k, v in sqf_json(THEME_SRC, "_space")}
WALLS = {k: {r[0]: r[1:] for r in v} for k, v in sqf_json(THEME_SRC, "_walls")}
COLS = {k: v for k, v in sqf_json(THEME_SRC, "_cols")}
FLASH_RULE = {k: (fixed, shrink) for k, fixed, shrink in sqf_json(THEME_SRC, "_flashRule")}


def region(view, key, flash=False):
    """Même règle que fn_tocTheme « region » : sous un FLASH, tout descend de 52 px sauf les régions fixes."""
    x, y, w, h = WALLS[view][key]
    fixed, shrink = FLASH_RULE.get(view, ([], []))
    if not flash or any(key.startswith(p) for p in fixed):
        return (x, y, w, h)
    return (x, y + 52, w, h - (52 if key in shrink else 0))


def rgb(token, alpha=1.0):
    h = TOKENS[token]
    return (int(h[1:3], 16), int(h[3:5], 16), int(h[5:7], 16), int(round(alpha * 255)))


# ---------------------------------------------------------------------------------------------------------------- polices
_FC = {}
SYMBOLS = set("●■▲▾○✔")
SYMBOL_TTF = next((p for p in (Path("C:/Windows/Fonts/seguisym.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")) if p.exists()), None)
MONO_TTF = [Path("C:/Windows/Fonts/consola.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf")]
MONOB_TTF = [Path("C:/Windows/Fonts/consolab.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf")]


def font(name, px):
    key = (name, round(px, 2))
    if key in _FC:
        return _FC[key]
    if "Monospace" in name:
        path = next(p for p in (MONOB_TTF if "Bold" in name else MONO_TTF) if p.exists())
        f = ImageFont.truetype(str(path), max(4, int(round(px * 0.86))))
    else:
        f = ImageFont.truetype(str(FONT_DIR / "RobotoCondensed.ttf"), max(4, int(round(px / 1.18))))
        f.set_variation_by_name("Bold" if "Bold" in name else "Regular")
    _FC[key] = f
    return f


def is_mono(name):
    return "Monospace" in name


def text_w(s, name, px):
    if is_mono(name):
        return len(s) * 0.5 * px
    return font(name, px).getlength(s)


def cut(s, name, px, w):
    """Même règle que l'écran : coupé avec « … » pour tenir dans la largeur (marge de 8 px)."""
    room = w - 8
    if text_w(s, name, px) <= room:
        return s
    while len(s) > 1 and text_w(s + "…", name, px) > room:
        s = s[:-1]
    return s.rstrip() + "…"


class Canvas:
    def __init__(self, w, h, sx=1.0, sy=1.0):
        self.img = Image.new("RGBA", (w, h), rgb("bg0"))
        self.sx, self.sy = sx, sy  # pixels virtuels -> pixels de l'image

    def _r(self, x, y, w, h):
        return (round(x * self.sx), round(y * self.sy), round((x + w) * self.sx) - 1, round((y + h) * self.sy) - 1)

    def rect(self, x, y, w, h, fill):
        if w <= 0 or h <= 0:
            return
        layer = Image.new("RGBA", self.img.size, (0, 0, 0, 0))
        ImageDraw.Draw(layer).rectangle(self._r(x, y, w, h), fill=fill)
        self.img = Image.alpha_composite(self.img, layer)

    def line(self, pts, fill, width):
        layer = Image.new("RGBA", self.img.size, (0, 0, 0, 0))
        ImageDraw.Draw(layer).line([(px * self.sx, py * self.sy) for px, py in pts], fill=fill, width=max(1, round(width * self.sx)))
        self.img = Image.alpha_composite(self.img, layer)

    def text(self, x, y, w, h, s, style, token="text", align="L", alpha=1.0, fit=True, px=None, fname=None):
        """Une ligne, centrée verticalement dans sa case comme un RscText d'Arma."""
        tpx, tname = TYPE[style] if style in TYPE else (px, fname)
        if px:
            tpx = px
        if fname:
            tname = fname
        s = cut(s, tname, tpx, w) if fit else s
        spx = tpx * self.sy
        f = font(tname, spx)
        d = ImageDraw.Draw(self.img)
        asc, desc = f.getmetrics()
        tw = text_w(s, tname, spx)
        X = x * self.sx
        if align == "R":
            X = (x + w) * self.sx - tw - 4 * self.sx
        elif align in ("C", "P"):
            X = x * self.sx + (w * self.sx - tw) / 2
        else:
            X += 4 * self.sx
        Y = y * self.sy + (h * self.sy - (asc + desc)) / 2
        col = rgb(token, alpha)
        if not is_mono(tname) and any(ch in SYMBOLS for ch in s):
            # Symboles absents de Roboto Condensed dans Pillow (le jeu les prend dans sa police de repli).
            sym = ImageFont.truetype(str(SYMBOL_TTF), f.size) if SYMBOL_TTF else f
            xx = X
            for ch in s:
                ff = sym if ch in SYMBOLS else f
                d.text((xx, Y), ch, font=ff, fill=col)
                xx += ff.getlength(ch)
        elif is_mono(tname):
            for i, ch in enumerate(s):
                cw = f.getlength(ch)
                d.text((X + i * 0.5 * spx + (0.5 * spx - cw) / 2, Y), ch, font=f, fill=col)
        else:
            d.text((X, Y), s, font=f, fill=col)
        return tw / self.sx


# ------------------------------------------------------------------------------------------------------- composants murs
def frame(cv, title, station, view_label, flash=None, link=("accent", "EN DIRECT · données à 2 s"), clock=("12:35:42", "13:35")):
    F = WALLS["FRAME"]
    cv.rect(*F["header"], rgb("bg1"))
    cv.rect(*F["rule"], rgb("red" if flash else "accent"))
    cv.text(*F["title"], title, "title", "text")
    x, y, w, h = F["clock"]
    # « 12:35:42 Z    13:35 LOCAL » : données en monospace, unités en légende.
    z, loc = clock
    wz = text_w(" LOCAL", TYPE["caption"][1], 28) + text_w(loc, TYPE["data"][1], 28) + 4
    cv.text(x, y, w, h, " LOCAL", "caption", "muted", "R")
    cv.text(x, y, w - text_w(" LOCAL", TYPE["caption"][1], 28) - 4, h, loc, "data", "text2", "R")
    cv.text(x, y, w - wz - 28, h, " Z", "caption", "muted", "R")
    cv.text(x, y, w - wz - 28 - text_w(" Z", TYPE["caption"][1], 28) - 4, h, z, "dataBold", "text", "R")
    if flash:
        cv.rect(*F["flash"], rgb("redDim"))
        cv.rect(*F["flashBar"], rgb("red"))
        cv.text(*F["flashTxt"], "FLASH · " + flash, "heading", "text")
    cv.rect(*F["footRule"], rgb("line"))
    cv.text(*F["footL"], f"{station} · {view_label}", "caption", "muted")
    tok, msg = link
    x, y, w, h = F["footR"]
    tw = text_w(msg, TYPE["caption"][1], 28)
    cv.text(x, y, w, h, msg, "caption", "text2", "R")
    cv.rect(x + w - tw - 4 - 26, y + 14, 12, 12, rgb(tok))


def section(cv, head, sub, rule, title, subtxt):
    cv.text(*head, title, "heading", "text")
    if sub and subtxt:
        cv.text(*sub, subtxt, "caption", "muted", "R")
    x, y, w, h = rule
    cv.rect(x, y, w, 1, rgb("line"))
    cv.rect(x, y - 1, 40, 2, rgb("accent"))


def tiles(cv, region, vals, cols=2):
    """Cases à gros chiffre : [(nombre, libellé, jeton)] ; 2 x 2 dans un panneau, 4 en ligne en pleine largeur."""
    x, y, w, h = region
    rows = math.ceil(len(vals) / cols)
    tw = (w - (cols - 1) * 8) / cols
    th = (h - (rows - 1) * 8) / rows
    for i, (n, lbl, tok) in enumerate(vals):
        tx = x + (i % cols) * (tw + 8)
        ty = y + (i // cols) * (th + 8)
        cv.rect(tx, ty, tw, th, rgb("bg2"))
        cv.rect(tx, ty, 4, th, rgb(tok))
        cv.text(tx + 12, ty, 84, th, str(n), "num", tok, "R")
        cv.text(tx + 104, ty, tw - 112, th, lbl, "body", "text2")


def pill(cv, x, y, w, h, label, tok):
    cv.rect(x, y + 4, w, h - 8, rgb(tok, 0.16))
    cv.rect(x, y + 4, 3, h - 8, rgb(tok))
    cv.text(x + 3, y, w - 3, h, label, "body", tok, "C")


def table(cv, cols_key, head_region, area, rows, row_h=40, header=True, more=None, highlight=None):
    """Tableau : en-têtes en légende, lignes de 40 px séparées d'un filet, nombres à droite, pastilles d'état.
    rows : liste de dict clé -> (texte, jeton). Renvoie le nombre de lignes posées (le reste : « + N autres »)."""
    cols = COLS[cols_key]
    if header and head_region:
        hx, hy, hw, hh = head_region
        for key, label, cx, cw, al, st in cols:
            if label:
                cv.text(hx + cx, hy, cw, hh, label, "caption", "muted", "R" if al == "R" else ("C" if al == "P" else "L"))
    ax, ay, aw, ah = area
    cap = int(ah // row_h)
    shown = rows if (len(rows) <= cap or more == "") else rows[: cap - 1]
    shown = shown[:cap]
    for i, r in enumerate(shown):
        ry = ay + i * row_h
        if highlight is not None and i == highlight:
            cv.rect(ax, ry, aw, row_h, rgb("bg2"))
            cv.rect(ax, ry, 3, row_h, rgb("accent"))
        for key, label, cx, cw, al, st in cols:
            if key not in r:
                continue
            txt, tok = r[key]
            if al == "P":
                if txt:
                    pill(cv, ax + cx, ry, cw, row_h, txt, tok)
            elif al == "C" and key == "dot":
                cv.rect(ax + cx + 2, ry + row_h / 2 - 6, 12, 12, rgb(tok))
            else:
                cv.text(ax + cx, ry, cw, row_h, txt, st, tok, al)
        cv.rect(ax, ry + row_h - 1, aw, 1, rgb("line", 0.7))
    if len(rows) > len(shown) and more != "":
        cv.text(ax, ay + len(shown) * row_h, aw, row_h, more or f"+ {len(rows) - len(shown)} autres", "caption", "muted")
    return len(shown)


# --------------------------------------------------------------------------------------------------------------- carte
_TERR = {}


def terrain(w, h, seed=7):
    """Fond de carte : photo satellite assombrie (mêmes réglages que l'écran : teinte 0,62 et voile BG0 à 35 %)."""
    if (w, h, seed) in _TERR:
        return _TERR[(w, h, seed)].copy()
    rnd = random.Random(seed)
    v = None
    for scale, amp in ((8, 0.55), (24, 0.3), (80, 0.15)):
        small = Image.new("L", (max(2, w // scale), max(2, h // scale)))
        small.putdata([rnd.randint(0, 255) for _ in range(small.size[0] * small.size[1])])
        big = small.resize((w, h), Image.BICUBIC)
        v = big if v is None else Image.blend(v, big, amp / (amp + 0.55))
    def lut(veg, bare):
        return [int((veg if i > 133 else bare) * (0.75 + 0.5 * (i / 255 - 0.5))) for i in range(256)]
    base = Image.merge("RGB", (v.point(lut(74, 118)), v.point(lut(88, 112)), v.point(lut(58, 92))))
    img = base.filter(ImageFilter.GaussianBlur(1.2))
    d = ImageDraw.Draw(img)
    # routes et quelques bâtiments
    for _ in range(5):
        pts = [(rnd.randint(0, w), rnd.randint(0, h))]
        for _ in range(6):
            pts.append((pts[-1][0] + rnd.randint(-w // 4, w // 4), pts[-1][1] + rnd.randint(-h // 4, h // 4)))
        d.line(pts, fill=(170, 160, 140), width=3)
    for _ in range(140):
        cx, cy = rnd.randint(int(w * 0.1), int(w * 0.9)), rnd.randint(int(h * 0.1), int(h * 0.9))
        if rnd.random() < 0.6:
            cx, cy = int(w * 0.4) + rnd.randint(-int(w * 0.12), int(w * 0.12)), int(h * 0.46) + rnd.randint(-int(h * 0.12), int(h * 0.12))
        d.rectangle((cx, cy, cx + rnd.randint(6, 14), cy + rnd.randint(6, 14)), fill=(150, 146, 136))
    img = Image.eval(img, lambda c: int(c * 0.62))
    img = img.convert("RGBA")
    img = Image.alpha_composite(img, Image.new("RGBA", img.size, rgb("bg0", 0.35)))
    _TERR[(w, h, seed)] = img
    return img.copy()


def nato(cv, x, y, tok, alpha=1.0, kind="inf"):
    """Symbole ami (cadre bleu) ; l'écran pose l'icône OTAN du jeu teintée de la même couleur."""
    col = rgb(tok, alpha)
    cv.rect(x - 18, y - 12, 36, 24, rgb("bg0", 0.55))
    cv.line([(x - 18, y - 12), (x + 18, y - 12), (x + 18, y + 12), (x - 18, y + 12), (x - 18, y - 12)], col, 3)
    if kind == "inf":
        cv.line([(x - 18, y - 12), (x + 18, y + 12)], col, 2)
        cv.line([(x - 18, y + 12), (x + 18, y - 12)], col, 2)
    elif kind == "veh":
        cv.line([(x - 18, y - 12), (x + 18, y + 12)], col, 2)
        cv.line([(x - 18, y + 12), (x + 18, y - 12)], col, 2)
        cv.line([(x - 8, y), (x + 8, y)], col, 2)
    elif kind == "air":
        cv.line([(x - 10, y), (x - 2, y - 6), (x + 2, y - 6), (x + 10, y)], col, 2)


def map_view(cv, mx, my, mw, mh, nodes, places=(), alerts=(), objective=None, grid_step=500, scale="500 m", legend=True, seed=7):
    tile = terrain(int(mw), int(mh), seed)
    cv.img.paste(tile, (int(mx), int(my)))
    taken = []

    def free(r):
        x, y, w, h = r
        return all(not (x < a + c and x + w > a and y < b + d and y + h > b) for a, b, c, d in taken)

    # quadrillage et numéros (mono 28, plaques BG0)
    gx0 = 40
    i = 0
    for gx in range(int(mx + 110), int(mx + mw - 60), 190):
        cv.rect(gx - 1, my, 2, mh, rgb("lineStrong", 0.45))
        if gx < mx + mw - 120:
            r = (gx - 32, my + 8, 64, 34)
            cv.rect(*r, rgb("bg0", 0.78))
            cv.text(*r, f"{gx0 + i:03d}", "data", "text2", "C")
            taken.append(r)
        i += 1
    i = 0
    for gy in range(int(my + 100), int(my + mh - 70), 190):
        cv.rect(mx, gy - 1, mw, 2, rgb("lineStrong", 0.45))
        if gy < my + mh - 90:
            r = (mx + 8, gy - 17, 64, 34)
            cv.rect(*r, rgb("bg0", 0.78))
            cv.text(*r, f"{118 - i:03d}", "data", "text2", "C")
            taken.append(r)
        i += 1
    # échelle (bas gauche) et nord (haut droite)
    sx, sy = mx + 16, my + mh - 62
    bar = 190
    lbl = scale
    lw = text_w(lbl, TYPE["data"][1], 28) + 16
    cv.rect(sx, sy, 28 + bar + 16 + lw, 46, rgb("bg0", 0.82))
    cv.rect(sx + 14, sy + 21, bar, 4, rgb("text"))
    cv.rect(sx + 14, sy + 12, 3, 22, rgb("text"))
    cv.rect(sx + 14 + bar - 3, sy + 12, 3, 22, rgb("text"))
    cv.text(sx + 28 + bar + 4, sy, lw, 46, lbl, "data", "text2", "L", fit=False)
    taken.append((sx, sy, 28 + bar + 16 + lw, 46))
    nx, ny = mx + mw - 72, my + 12
    cv.rect(nx, ny, 56, 70, rgb("bg0", 0.82))
    cv.line([(nx + 28, ny + 8), (nx + 40, ny + 34), (nx + 28, ny + 28), (nx + 16, ny + 34), (nx + 28, ny + 8)], rgb("text"), 3)
    cv.text(nx, ny + 34, 56, 34, "N", "bodyBold", "text", "C")
    taken.append((nx, ny, 56, 70))
    # légende (bas droite)
    if legend:
        chips = [("friend", 1.0, "En direct"), ("friend", 0.5, "Ancien"), ("lost", 1.0, "Perdu"), ("red", 1.0, "Alerte")]
        widths = [text_w(c[2], TYPE["body"][1], 28) + 40 for c in chips]
        lw = sum(widths) + 16
        lx, ly = mx + mw - lw - 16, my + mh - 62
        cv.rect(lx, ly, lw, 46, rgb("bg0", 0.82))
        cx = lx + 12
        for (tok, al, lbl), ww in zip(chips, widths):
            cv.rect(cx, ly + 16, 14, 14, rgb(tok, al))
            cv.text(cx + 18, ly, ww - 18, 46, lbl, "body", "text2", fit=False)
            cx += ww
        taken.append((lx, ly, lw, 46))
    # alertes
    for ax_, ay_, lbl in alerts:
        X, Y = mx + ax_, my + ay_
        cv.line([(X, Y - 16), (X + 16, Y + 12), (X - 16, Y + 12), (X, Y - 16)], rgb("red"), 3)
        taken.append((X - 18, Y - 18, 36, 36))
    # nœuds : symbole puis plaque (8 places autour, filet de rappel si décalée)
    plates = []
    for nxp, nyp, cs, state, kind in nodes:
        X, Y = mx + nxp, my + nyp
        tok, al = {"live": ("friend", 1.0), "old": ("friend", 0.55), "lost": ("lost", 1.0)}[state]
        nato(cv, X, Y, tok, al, kind)
        taken.append((X - 20, Y - 14, 40, 28))
        plates.append((X, Y, cs, state))
    for X, Y, cs, state in plates:
        lbl = cs + {"live": "", "old": " · ancien", "lost": " · perdu"}[state]
        w = text_w(lbl, TYPE["bodyBold"][1], 28) + 20
        h = 36
        q = 20
        cands = [(X + 26, Y - h / 2), (X - 26 - w, Y - h / 2), (X - w / 2, Y - 16 - h), (X - w / 2, Y + 16),
                 (X + q, Y - q - h), (X + q, Y + q), (X - q - w, Y - q - h), (X - q - w, Y + q),
                 (X + 60, Y - h / 2), (X - 60 - w, Y - h / 2)]
        for i, (px_, py_) in enumerate(cands):
            r = (px_, py_, w, h)
            if px_ >= mx + 2 and py_ >= my + 2 and px_ + w <= mx + mw - 2 and py_ + h <= my + mh - 2 and free(r):
                taken.append(r)
                if i >= 8:
                    cv.line([(X + (20 if px_ > X else -20), Y), (px_ if px_ > X else px_ + w, py_ + h / 2)], rgb("text2", 0.7), 2)
                cv.rect(*r, rgb("bg0", 0.82))
                cv.rect(px_, py_, 3, h, rgb({"live": "friend", "old": "amber", "lost": "lost"}[state]))
                cv.text(px_ + 3, py_, w - 3, h, lbl, "bodyBold", {"live": "text", "old": "amber", "lost": "lost"}[state], fit=False)
                break
    if objective:
        ox, oy = mx + objective[0], my + objective[1]
        cv.line([(ox - 16, oy - 16), (ox + 16, oy + 16)], rgb("amber"), 4)
        cv.line([(ox - 16, oy + 16), (ox + 16, oy - 16)], rgb("amber"), 4)
        w = text_w("OBJECTIF", TYPE["bodyBold"][1], 28) + 20
        cv.rect(ox + 24, oy - 18, w, 36, rgb("bg0", 0.82))
        cv.text(ox + 24, oy - 18, w, 36, "OBJECTIF", "bodyBold", "amber", fit=False)
    for pxp, pyp, name, big in places:
        X, Y = mx + pxp, my + pyp
        w = text_w(name, TYPE["body"][1], 28) + 20
        r = (X - w / 2, Y - 18, w, 36)
        if free(r):
            taken.append(r)
            cv.rect(*r, rgb("bg0", 0.5))
            cv.text(*r, name, "bodyBold" if big else "body", "text" if big else "text2", "C", fit=False)


NODES = [(380, 300, "ALPHA 1-1", "live", "inf"), (430, 340, "ALPHA 1-2", "live", "inf"), (520, 420, "BRAVO 2", "old", "veh"),
         (690, 250, "CHARLIE 3", "live", "inf"), (250, 520, "DELTA 4", "lost", "inf"), (780, 560, "VIPER 1", "live", "air"),
         (610, 610, "ECHO 5", "live", "veh")]
PLACES = [(300, 160, "Agia Marina", True), (820, 420, "Kamino", False), (540, 700, "Camp Rogain", False)]

GROUPS = [("ALPHA 1-1", 4, "042 116", "accent"), ("ALPHA 1-2", 4, "043 115", "accent"), ("BRAVO 2", 3, "045 113", "amber"),
          ("CHARLIE 3", 5, "048 117", "accent"), ("DELTA 4", 4, "039 111", "lost"), ("ECHO 5", 2, "047 110", "accent"),
          ("VIPER 1", 1, "050 112", "accent"), ("GOLF 7", 3, "036 109", "accent"), ("HOTEL 8", 2, "052 114", "amber")]
EVENTS = [("12:34", "TIC", "FLASH", "ALPHA 1-1", "042 116", "Contact NE 300 m, 2 PAX armés"),
          ("12:31", "MEDEVAC", "URGENT", "CHARLIE 3", "048 117", "1 blessé urgence A, HLZ au sud"),
          ("12:27", "SITREP", "ROUTINE", "BRAVO 2", "045 113", "En position, observation de la route"),
          ("12:22", "BDA", "ROUTINE", "VIPER 1", "050 112", "2 véhicules neutralisés"),
          ("12:18", "ORDRE", "IMPORTANT", "TOC BRAVO", "", "ALPHA : tenir le carrefour jusqu'à relève"),
          ("12:11", "CONTACT", "URGENT", "DELTA 4", "039 111", "Tirs sporadiques depuis le bois ouest"),
          ("12:05", "SITREP", "ROUTINE", "ECHO 5", "047 110", "Convoi parti, ETA 15 min"),
          ("11:58", "SALUTE", "ROUTINE", "ALPHA 1-2", "043 115", "Pick-up armé immobile au carrefour"),
          ("11:51", "MESSAGE", "ROUTINE", "TOC BRAVO", "", "Fréquence de repli 47.300"),
          ("11:44", "SITREP", "ROUTINE", "CHARLIE 3", "048 117", "RAS, poursuite de la patrouille"),
          ("11:40", "LACE", "ROUTINE", "ALPHA 1-1", "042 116", "Munitions vert, eau orange"),
          ("11:32", "SITREP", "ROUTINE", "GOLF 7", "036 109", "Point de contrôle établi"),
          ("11:26", "SITREP", "ROUTINE", "HOTEL 8", "052 114", "Relève effectuée"),
          ("11:20", "MESSAGE", "ROUTINE", "TOC BRAVO", "", "Début de l'opération"),
          ("11:15", "SITREP", "ROUTINE", "ALPHA 1-1", "041 117", "Départ de la base")]
PRIO_TOK = {"FLASH": "red", "URGENT": "amber", "IMPORTANT": "blue", "ROUTINE": "muted"}
STATE_TOK = {"accent": "en direct", "amber": "ancien", "lost": "perdu"}


def kpi4(cv, region, vals):
    tiles(cv, region, vals, cols=4)


def net_tiles():
    return [(18, "en ligne", "accent"), (3, "anciens", "amber"), (1, "perdus", "red"), (2, "hors ligne", "muted")]


# ------------------------------------------------------------------------------------------------------------ murs
def wall(name, title, view_label, drawer, flash=None, station="BRAVO"):
    cv = Canvas(1600, 900)
    drawer(cv)
    frame(cv, title, station, view_label, flash=flash)
    save(cv.img, name)


def draw_split(cv, pip=False, dy=0):
    # Sous un FLASH (bandeau de 52 px) : carte et blocs du haut descendent, le flux reste en bas, les groupes perdent
    # les lignes en trop (même règle que fn_tocScreen « draw »).
    L = {k: region("SPLIT", k, dy > 0) for k in WALLS["SPLIT"]}
    mx, my, mw, mh = L["map"]
    map_view(cv, mx, my, mw, mh, NODES, PLACES, alerts=[(470, 230, "TIC")], objective=(600, 330) if pip else None)
    cv.rect(*L["divider"], rgb("line"))
    section(cv, L["netHead"], L["netSub"], L["netRule"], "RÉSEAU", "14 joueurs · 6 IA · 2 relais")
    tiles(cv, L["tiles"], net_tiles())
    section(cv, L["grpHead"], L["grpSub"], L["grpRule"], "GROUPES", f"{len(GROUPS)} éléments")
    rows = [{"name": (g, tok if tok != "accent" else "text"), "n": (str(n), "text2"), "grid": (gr, "text2")} for g, n, gr, tok in GROUPS]
    table(cv, "GRP_S", L["grpCols"], L["grpArea"], rows)
    section(cv, L["fluxHead"], L["fluxSub"], L["fluxRule"], "FLUX", f"{len(EVENTS)} sur 20 min")
    rows = [{"time": (t, "muted"), "kind": (k, PRIO_TOK[p] if p != "ROUTINE" else "text"), "who": (w, "text2")} for t, k, p, w, g, x in EVENTS]
    table(cv, "FLUX_S", None, L["fluxArea"], rows, header=False, more="")


def draw_map(cv):
    mx, my, mw, mh = WALLS["MAP"]["map"]
    nodes = [(x * 1.45 + 60, y * 1.0 + 20, cs, st, k) for x, y, cs, st, k in NODES]
    map_view(cv, mx, my, mw, mh, nodes, [(p[0] * 1.5, p[1], p[2], p[3]) for p in PLACES], alerts=[(700, 260, "TIC")], seed=11)


def draw_feed(cv):
    L = WALLS["FEED"]
    kpi4(cv, L["kpi"], [(15, "comptes rendus 20 min", "text"), (1, "FLASH", "red"), (2, "urgents", "amber"), (18, "nœuds en ligne", "accent")])
    section(cv, L["head"], L["sub"], L["rule"], "FLUX DU CAMP", "du plus récent au plus ancien · heures ZULU")
    rows = [{"time": (t, "text2"), "kind": (k, "text"), "prio": (p.lower() if p != "ROUTINE" else "", PRIO_TOK[p]), "who": (w, "text2"), "grid": (g, "text2"), "text": (x, "text")}
            for t, k, p, w, g, x in EVENTS]
    table(cv, "FEED", L["cols"], L["area"], rows)


def draw_net(cv):
    L = WALLS["NET"]
    kpi4(cv, L["kpi"], net_tiles())
    section(cv, L["head"], L["sub"], L["rule"], "GROUPES DU RÉSEAU", "14 joueurs · 6 IA · 2 relais · 0 brouilleur")
    gs = GROUPS + [("INDIA 9", 2, "033 108", "accent"), ("JULIET 10", 3, "031 119", "lost")]
    rows = [{"name": (g, "text"), "n": (str(n), "text2"), "state": (STATE_TOK[tok], tok), "grid": (gr, "text2")} for g, n, gr, tok in gs]
    half = 7
    table(cv, "NET", L["cols"], L["area"], rows[:half])
    table(cv, "NET", L["cols2"], L["area2"], rows[half:])


def draw_orbat(cv):
    L = WALLS["ORBAT"]
    section(cv, L["head"], L["sub"], L["rule"], "ORBAT DU SITE", "31 membres · 19 en jeu")
    units = [("1re SECTION (1SEC)", ["Lt Moreau", "Sgt Dubois", "Cpl Lefèvre", "Sdt Garnier", "Sdt Roux", "Sdt Fontaine"]),
             ("2e SECTION (2SEC)", ["Sgt Bernard", "Cpl Petit", "Sdt Durand", "Sdt Leroy", "", "Sdt Simon"]),
             ("APPUI (APP)", ["Adj Laurent", "Cpl Michel", "Sdt Garcia"]), ("ÉQUIPAGES (EQP)", ["Sgt Thomas", "Cpl Robert", "Sdt Richard"]),
             ("COMMANDEMENT (CDT)", ["Cne Martin", "Adj Mercier", "Sgt Blanc"])]
    lines = []
    rnd = random.Random(3)
    for u, ms in units:
        lines.append(("u", u))
        for m in ms:
            lines.append(("v", "poste vacant") if not m else ("m", m, rnd.random() < 0.65))
    cols = [L["col1"], L["col2"], L["col3"]]
    per = int(cols[0][3] // 38)
    for ci, (x, y, w, h) in enumerate(cols):
        for i, ln in enumerate(lines[ci * per:(ci + 1) * per]):
            ry = y + i * 38
            if ln[0] == "u":
                cv.text(x, ry, w, 38, ln[1], "bodyBold", "blue")
            elif ln[0] == "v":
                cv.rect(x + 8, ry + 13, 12, 12, rgb("line"))
                cv.text(x + 24, ry, w - 24, 38, "poste vacant", "body", "muted")
            else:
                cv.rect(x + 8, ry + 13, 12, 12, rgb("accent" if ln[2] else "lineStrong"))
                cv.text(x + 24, ry, w - 160, 38, ln[1], "body", "text" if ln[2] else "text2")
                cv.text(x + w - 136, ry, 136, 38, "en jeu" if ln[2] else "hors ligne", "caption", "accent" if ln[2] else "muted", "R")


def draw_squad(cv, cam=False):
    L = WALLS["SQUAD"]
    mx, my, mw, mh = L["map"]
    nodes = [(420, 330, "ALPHA 1-1", "live", "inf"), (480, 380, "ALPHA 1-2", "live", "inf"), (560, 300, "ALPHA 1-3", "old", "inf"), (360, 440, "ALPHA 1-4", "live", "inf")]
    map_view(cv, mx, my, mw, mh, nodes, [(700, 600, "Agia Marina", True)], seed=5, legend=False)
    cv.rect(*L["divider"], rgb("line"))
    cv.rect(*L["plateBar"], rgb("accent"))
    cv.text(*L["plate"], "ALPHA 1", "squadName", "text")
    cv.text(*L["plateSub"], "4 nœud(s) ATAK · 3 en direct", "body", "text2")
    tiles(cv, L["tiles"], [(3, "en ligne", "accent"), (1, "anciens", "amber"), (0, "perdus", "red"), (0, "hors ligne", "muted")])
    section(cv, L["memHead"], L["memSub"], L["memRule"], "MEMBRES", "grille · état")
    mem = [("ALPHA 1-1", "042 116", "accent"), ("ALPHA 1-2", "043 115", "accent"), ("ALPHA 1-3", "045 117", "amber"), ("ALPHA 1-4", "041 114", "accent")]
    rows = [{"dot": ("", t), "cs": (c, "text"), "grid": (g, "text2"), "state": (STATE_TOK[t], t)} for c, g, t in mem]
    table(cv, "MEM", None, L["memAreaCam"] if cam else L["memArea"], rows, header=False)
    if cam:
        x, y, w, h = L["cam"]
        cam_tile(cv, x, y, w, h, "Sgt Dubois · poitrine", "bonne", live=True, seed=4)
    else:
        section(cv, L["crHead"], L["crSub"], L["crRule"], "DERNIER CR", "12:34Z · ALPHA 1-1")
        x, y, w, h = L["crText"]
        cv.text(x, y, w, 32, "TIC · FLASH · grille 042 116", "bodyBold", "red")
        cv.text(x, y + 32, w, 32, "Contact NE 300 m, 2 PAX armés", "body", "text")


def brackets(cv, x, y, w, h, tok="text", al=0.85, L=28, t=3):
    c = rgb(tok, al)
    for (cx, cy, dx, dy) in ((x, y, 1, 1), (x + w, y, -1, 1), (x, y + h, 1, -1), (x + w, y + h, -1, -1)):
        cv.rect(min(cx, cx + dx * L), min(cy, cy + dy * t) if dy > 0 else cy - t, L, t, c)
        cv.rect(min(cx, cx + dx * t) if dx > 0 else cx - t, min(cy, cy + dy * L), t, L, c)


def cam_tile(cv, x, y, w, h, cs, link, live=True, free_=False, seed=1):
    if free_:
        cv.rect(x, y, w, h, rgb("bg1"))
        cv.rect(x, y, w, 1, rgb("line")); cv.rect(x, y + h - 1, w, 1, rgb("line")); cv.rect(x, y, 1, h, rgb("line")); cv.rect(x + w - 1, y, 1, h, rgb("line"))
        cv.text(x, y, w, h, "CASE LIBRE", "body", "muted", "C")
        return
    if live:
        img = terrain(int(w), int(h), seed).filter(ImageFilter.GaussianBlur(1.5))
        img = Image.eval(img, lambda c: min(255, int(c * 1.5)))
        cv.img.paste(img, (int(x), int(y)))
    else:
        cv.rect(x, y, w, h, rgb("bg1"))
    brackets(cv, x + 10, y + 10, w - 20, h - 60, "text" if live else "lineStrong", 0.85)
    if live:
        cv.rect(x + 16, y + 16, 110, 34, rgb("bg0", 0.75))
        cv.rect(x + 26, y + 27, 12, 12, rgb("red"))
        cv.text(x + 40, y + 16, 86, 34, "DIRECT", "bodyBold", "text", fit=False)
    else:
        cv.text(x, y + h / 2 - 50, w, 40, "PAS DE SIGNAL", "heading", "red", "C")
        cv.text(x, y + h / 2 - 8, w, 36, "liaison coupée", "caption", "muted", "C")
    # bandeau : indicatif, barres de liaison, heure
    cv.rect(x, y + h - 40, w, 40, rgb("bg0", 0.85))
    lvl = {"bonne": 4, "moyenne": 3, "faible": 2, "coupée": 0}[link]
    tok = {"bonne": "accent", "moyenne": "amber", "faible": "amber", "coupée": "red"}[link]
    zw = 120
    cv.text(x + 8, y + h - 40, w - zw - 80, 40, cs, "bodyBold", "text")
    bx = x + w - zw - 64
    for i in range(4):
        bh = 8 + i * 6
        cv.rect(bx + i * 10, y + h - 8 - bh, 6, bh, rgb(tok if i < lvl else "lineStrong"))
    cv.text(x + w - zw, y + h - 40, zw - 4, 40, "12:35Z", "data", "text2", "R")


def draw_mosaic(cv, n=3):
    x, y, w, h = WALLS["MOSAIC"]["area"]
    g = 16
    tw, th = (w - (n - 1) * g) / n, (h - (n - 1) * g) / n
    srcs = [("ALPHA 1-1", "bonne", True), ("BRAVO 2", "moyenne", True), ("VIPER 1 · drone", "bonne", True), ("CHARLIE 3", "coupée", False),
            ("Caméra portail", "bonne", True), ("ECHO 5", "faible", True), ("DELTA 4", "coupée", False), ("ALPHA 1-2", "bonne", True), None]
    for i in range(n * n):
        tx, ty = x + (i % n) * (tw + g), y + (i // n) * (th + g)
        s = srcs[i]
        if s is None:
            cam_tile(cv, tx, ty, tw, th, "", "bonne", free_=True)
        else:
            cam_tile(cv, tx, ty, tw, th, s[0], s[1], live=s[2], seed=i + 20)


def draw_clock(cv):
    L = WALLS["CLOCK"]
    cv.text(*L["zLbl"], "ZULU", "heading", "accent")
    cv.text(*L["zDig"], "12:35:42", "display", "text", fit=False)
    cv.text(*L["dtg"], "DTG 111235Z NOV 26", "data", "text2")
    cv.rect(*L["lRule"], rgb("line"))
    cv.text(*L["lLbl"], "LOCAL (MISSION)", "heading", "muted")
    cv.text(*L["lDig"], "06:12:08", "hero", "text2", fit=False)
    cv.text(*L["lDate"], "date de la mission · 14 JUN 2035", "body", "text2")
    section(cv, L["nxHead"], L["nxSub"], L["nxRule"], "PROCHAINE LIGNE", "liste d'exécution")
    cv.text(*L["nxCode"], "4 · ROMEO", "title", "accent")
    cv.text(*L["nxEv"], "Franchissement de la ligne de départ · ALPHA", "body", "text")
    cv.text(*L["nxAt"], "prévu 12:45Z · dans 9:18", "data", "text2")
    cv.rect(*L["divider"], rgb("line"))
    section(cv, L["tHead"], L["tSub"], L["tRule"], "CHRONOS", "6 chronos du poste")
    timers = [("H-HOUR", "heure H 12:00Z · dépassée", "H+00:35:42", "red"), ("ALPHA EN POSITION", "compte à rebours · en marche", "-00:04:12", "accent"),
              ("RELÈVE BRAVO", "compte à rebours · arrêté", "-00:20:00", "muted"), ("MEDEVAC CHARLIE", "chrono · en marche", "+00:03:51", "accent"),
              ("EXFIL", "heure H 13:30Z", "H-00:54:18", "amber"), ("SILENCE RADIO", "chrono · arrêté", "+00:12:00", "muted")]
    ax, ay, aw, ah = L["tArea"]
    cols = {c[0]: c for c in COLS["TIMER"]}
    for i, (nm, sub, val, tok) in enumerate(timers):
        ry = ay + i * 76
        cv.text(ax + cols["name"][2], ry + 4, cols["name"][3], 40, nm, "heading", "text")
        cv.text(ax + cols["sub"][2], ry + 42, cols["sub"][3], 30, sub, "caption", "muted")
        cv.text(ax + cols["val"][2], ry + 10, cols["val"][3], 56, val, "num", tok, "R", fit=False)
        cv.rect(ax, ry + 75, aw, 1, rgb("line", 0.7))


def draw_log(cv, flash=False):
    L = WALLS["LOG"]
    dy = 52 if flash else 0
    sh = lambda r: (r[0], r[1] + dy, r[2], r[3] - (dy if len(r) and r[3] > 100 else 0))
    section(cv, sh(L["head"]), sh(L["sub"]), sh(L["rule"]), "MAIN COURANTE", "38 entrées · heures ZULU")
    kinds = {"FLASH": "red", "CR": "amber", "DEMANDE": "amber", "EXCHECK": "accent", "POSTE": "blue", "CHRONO": "text2", "MAIN": "muted"}
    entries = [("12:35:02", "FLASH", "CONTACT NORD, TOUS AUX ABRIS", "Cne Martin"), ("12:34:10", "CR", "TIC ALPHA 1-1 grille 042 116 : contact NE 300 m", "ALPHA 1-1"),
               ("12:31:44", "DEMANDE", "MEDEVAC CHARLIE 3 : 1 urgence A, HLZ sud", "CHARLIE 3"), ("12:30:00", "EXCHECK", "3 · TANGO passé : ALPHA sur la ligne de départ", "Adj Mercier"),
               ("12:27:15", "CR", "SITREP BRAVO 2 : en position, observation route", "BRAVO 2"), ("12:25:00", "CHRONO", "ALPHA EN POSITION lancé (20:00)", "Adj Mercier"),
               ("12:22:31", "CR", "BDA VIPER 1 : 2 véhicules neutralisés", "VIPER 1"), ("12:20:02", "POSTE", "Poste pris par Adj Mercier", "Adj Mercier"),
               ("12:18:40", "MAIN", "BRAVO signale le pont nord détruit", "Cne Martin"), ("12:11:09", "CR", "CONTACT DELTA 4 : tirs depuis le bois ouest", "DELTA 4"),
               ("12:05:33", "CR", "SITREP ECHO 5 : convoi parti, ETA 15 min", "ECHO 5"), ("12:00:00", "EXCHECK", "2 · SIERRA passé : H-HOUR", "Cne Martin"),
               ("11:58:12", "CR", "SALUTE ALPHA 1-2 : pick-up armé au carrefour", "ALPHA 1-2"), ("11:51:00", "MAIN", "Fréquence de repli 47.300 communiquée", "Cne Martin"),
               ("11:44:27", "CR", "SITREP CHARLIE 3 : RAS", "CHARLIE 3"), ("11:40:03", "CR", "LACE ALPHA 1-1 : munitions vert, eau orange", "ALPHA 1-1"),
               ("11:32:00", "CR", "SITREP GOLF 7 : point de contrôle établi", "GOLF 7"), ("11:20:00", "POSTE", "Poste ouvert", "Cne Martin")]
    rows = [{"time": (t, "text2"), "kind": (k.lower() if k != "CR" else "compte rendu", kinds[k]), "text": (x, "text"), "who": (w, "text2")} for t, k, x, w in entries]
    area = L["area"]
    table(cv, "LOG", sh(L["cols"]), (area[0], area[1] + dy, area[2], area[3] - dy), rows, more="+ {n} plus anciennes au PC du poste".format(n=len(rows) - (int((area[3] - dy) // 40) - 1)))


def draw_excheck(cv):
    L = WALLS["EXCHECK"]
    section(cv, L["head"], L["sub"], L["rule"], "LISTE D'EXÉCUTION", "3 / 9 passés")
    x, y, w, h = L["prog"]
    cv.rect(x, y, w, h, rgb("line"))
    cv.rect(x, y, w * 3 / 9, h, rgb("accent"))
    ex = [(1, "QUEBEC", "Départ de la base", "TOUS", "11:15", "11:16"), (2, "SIERRA", "H-HOUR", "TOUS", "12:00", "12:00"),
          (3, "TANGO", "Ligne de départ franchie", "ALPHA", "12:30", "12:30"), (4, "ROMEO", "Franchissement de la ligne de départ", "ALPHA", "12:45", ""),
          (5, "UNIFORM", "Appui feu en place", "VIPER", "12:50", ""), (6, "VICTOR", "Assaut de l'objectif RAVEN", "ALPHA", "13:05", ""),
          (7, "WHISKEY", "Objectif RAVEN tenu", "ALPHA", "13:20", ""), (8, "XRAY", "Début de l'exfiltration", "TOUS", "13:30", ""),
          (9, "YANKEE", "Retour à la base", "TOUS", "14:15", "")]
    rows = []
    nxt = None
    for i, (n, cw, ev, el, at, done) in enumerate(ex):
        ok = done != ""
        if not ok and nxt is None:
            nxt = i
        rows.append({"n": (str(n), "text2"), "cw": (cw, "accent" if ok else "text"), "ev": (ev, "text2" if ok else "text"), "el": (el, "text2"),
                     "at": (at, "text2"), "done": (done or "—", "accent" if ok else "muted"), "st": ("passé" if ok else ("prochain" if i == nxt else "prévu"), "accent" if ok else ("amber" if i == nxt else "muted"))})
    table(cv, "EXCHECK", L["cols"], L["area"], rows, highlight=nxt)


def draw_kills(cv):
    L = WALLS["KILLS"]
    kpi4(cv, L["kpi"], [(3, "amis blessés", "amber"), (1, "amis tués", "red"), (11, "ennemis neutralisés", "accent"), (4, "ennemis blessés / pris", "text2")])
    section(cv, L["head"], L["sub"], L["rule"], "PAR ÉLÉMENT", "réseau ATAK, BDA et corrections du poste")
    gx, gy, gw, gh = L["groups"]
    for key, label, cx, cw, al, st in COLS["KILLS_G"]:
        cv.text(gx + cx, gy, cw, gh, label, "caption", "text2", "R")
        cv.rect(gx + cx + 8, gy + gh - 2, cw - 8, 1, rgb("lineStrong"))
    data = [("ALPHA 1-1", 1, 0, 4, 1), ("ALPHA 1-2", 0, 0, 2, 0), ("BRAVO 2", 1, 1, 1, 2), ("CHARLIE 3", 1, 0, 0, 0), ("VIPER 1", 0, 0, 4, 1)]
    rows = [{"el": (e, "text"), "wia": (str(a), "amber" if a else "muted"), "kia": (str(b), "red" if b else "muted"), "ekia": (str(c), "accent" if c else "muted"), "ewia": (str(d), "text" if d else "muted")} for e, a, b, c, d in data]
    n = table(cv, "KILLS", L["cols"], L["area"], rows)
    # Ligne TOTAL sous un filet fort.
    ax, ay, aw, ah = L["area"]
    ty = ay + n * 40
    cv.rect(ax, ty, aw, 2, rgb("lineStrong"))
    tot = [sum(r[i] for r in data) for i in range(1, 5)]
    for (key, label, cx, cw, al, st), v in zip(COLS["KILLS"], ["TOTAL"] + tot):
        cv.text(ax + cx, ty, cw, 40, str(v), "bodyBold" if key == "el" else "dataBold", "text", al)


def draw_req(cv):
    L = WALLS["REQ"]
    kpi4(cv, L["kpi"], [(2, "reçues", "amber"), (1, "prises en compte", "blue"), (3, "terminées", "accent"), (1, "refusées", "red")])
    section(cv, L["head"], L["sub"], L["rule"], "DEMANDES DES ÉLÉMENTS", "MEDEVAC, appuis feu, ravitaillements")
    st = {"RECUE": ("reçue", "amber"), "PRISE": ("prise en compte", "blue"), "TERMINEE": ("terminée", "accent"), "REFUSEE": ("refusée", "red")}
    reqs = [("12:31", "MEDEVAC", "CHARLIE 3", "048 117", "1 urgence A, HLZ sud, fumée verte", "RECUE"),
            ("12:29", "APPUI FEU", "ALPHA 1-1", "044 118", "Mortier sur lisière NE, danger close 300 m", "PRISE"),
            ("12:20", "RAVITAILLEMENT", "BRAVO 2", "045 113", "Munitions 5.56 x 6, eau x 4", "RECUE"),
            ("12:02", "APPUI FEU", "DELTA 4", "039 111", "Fumigène sur bois ouest → réponse : approuvé", "TERMINEE"),
            ("11:50", "RAVITAILLEMENT", "ECHO 5", "047 110", "Carburant 200 L", "TERMINEE"),
            ("11:41", "MEDEVAC", "GOLF 7", "036 109", "Blessé léger, évacuation par route", "TERMINEE"),
            ("11:30", "APPUI AÉRIEN", "ALPHA 1-2", "043 115", "Reconnaissance drone sur le carrefour → hors délai", "REFUSEE")]
    rows = [{"time": (t, "text2"), "kind": (k, "text"), "by": (b, "text2"), "grid": (g, "text2"), "text": (x, "text"), "st": st[s]} for t, k, b, g, x, s in reqs]
    table(cv, "REQ", L["cols"], L["area"], rows)


# ------------------------------------------------------------------------------------------------------- satellite (ISR)
def isr_image(w, h, seed=31):
    """Image thermique « blanc chaud » : le satellite rend la scène (fn_tocSat), ici une imitation en niveaux de gris."""
    img = terrain(int(w), int(h), seed).convert("L")
    img = Image.eval(img, lambda c: min(255, int(90 + c * 3.2)))
    return img.convert("RGBA")


def isr_hud(cv, x, y, w, h, fpx=34, lpx=28, th=3, m=18, txt_scale=1.0):
    """HUD du capteur (fn_tocSat « hudPaint ») : coins, réticule, échelle, lignes de données en monospace sur plaque BG0,
    pistes amies (cadre, symbole OTAN, indicatif). Encre : texte du thème (image sombre) ou BG0 (blanc chaud)."""
    ink = rgb("bg0", 0.88)
    bl = 60
    x0, y0, x1, y1 = x + m, y + m, x + w - m, y + h - m
    for cx, cy, sx, sy in ((x0, y0, 1, 1), (x1, y0, -1, 1), (x0, y1, 1, -1), (x1, y1, -1, -1)):
        cv.rect(cx if sx > 0 else cx - bl, cy if sy > 0 else cy - th, bl, th, ink)
        cv.rect(cx if sx > 0 else cx - th, cy if sy > 0 else cy - bl, th, bl, ink)
    mx, my = x + w / 2, y + h / 2
    for dx, dy, ww, hh in ((-46, -1.5, 30, 3), (16, -1.5, 30, 3), (-1.5, -46, 3, 30), (-1.5, 16, 3, 30)):
        cv.rect(mx + dx, my + dy, ww, hh, ink)
    mono, monob = FONTS["mono"], FONTS["monoBold"]

    def plate(px_, py_, s_, right=False, size=fpx):
        tw = len(s_) * 0.5 * size + 16
        X = px_ - tw if right else px_
        cv.rect(X, py_, tw, size * 1.3, rgb("bg0", 0.55))
        cv.text(X, py_, tw, size * 1.3, s_, None, "text", "C", px=size, fname=mono, fit=False)
        return size * 1.3

    tx, ty = x0 + 14, y0 + 14
    h1 = plate(tx, ty, "IMAGE SAT · AUTO BC · 0.50X")
    plate(tx, ty + h1 + 2, "SAT 1 · NÉB. 7 % · BRUME 2 %")
    h2 = plate(x1 - 10, ty, "N · ALT 400 M · FOV 12", True)
    plate(x1 - 10, ty + h2 + 2, "SOURCE SAT 1 · Adj Mercier", True)
    plate(tx, y1 - 8 - fpx * 1.3, "GR 042116 · ÉLÉV +0013 M")
    plate(x1 - 10, y1 - 8 - fpx * 1.3, "DIRECT 12:35:42Z", True)
    bw = 200
    byb = y1 - 70
    cv.rect(mx - bw / 2, byb, bw, th, ink)
    cv.rect(mx - bw / 2, byb - 8, th, 8 + th, ink)
    cv.rect(mx + bw / 2 - th, byb - 8, th, 8 + th, ink)
    plate(mx - 50, byb - 8 - lpx * 1.35, "100 M", size=lpx)
    for (px_, py_, cs, tok) in ((x + w * 0.62, y + h * 0.33, "ALPHA 1-1", "friend"), (x + w * 0.68, y + h * 0.36, "ALPHA 1-2", "friend"), (x + w * 0.4, y + h * 0.6, "BRAVO 2 · ancien", "lost")):
        cv.line([(px_ - 18, py_ - 18), (px_ + 18, py_ - 18), (px_ + 18, py_ + 18), (px_ - 18, py_ + 18), (px_ - 18, py_ - 18)], rgb(tok), 2)
        nato(cv, px_ + 40, py_ - 34, tok)
        lw = len(cs) * 0.5 * lpx + 16
        cv.rect(px_ + 64, py_ - 52, lw, lpx * 1.3, rgb("bg0", 0.55))
        cv.text(px_ + 64, py_ - 52, lw, lpx * 1.3, cs, None, "text", "C", px=lpx, fname=mono, fit=False)


def draw_sat_wall(cv):
    cv.img.paste(isr_image(1600, 900), (0, 0))
    isr_hud(cv, 0, 0, 1600, 854)
    # Bandeau de lecture seule = pied de l'écran : satellite à gauche, nom de l'écran à droite.
    cv.rect(0, 854, 1600, 46, rgb("bg0", 0.78))
    cv.text(16, 854, 1000, 46, "Lecture seule", None, "amber", "L", px=32, fname=FONTS["sans"], fit=False)
    lw = text_w("Lecture seule", FONTS["sans"], 32)
    cv.text(20 + lw, 854, 1000, 46, " · SAT 1 · BLANC CHAUD · piloté par Adj Mercier (PC BRAVO-6)", None, "text2", "L", px=32, fname=FONTS["sans"])
    cv.text(1000, 854, 584, 46, "SAT CENTRE", None, "muted", "R", px=32, fname=FONTS["sans"])


# ------------------------------------------------------------------------------------------------------- faces 4:1
def strip(cv):
    L = WALLS["STRIP"]
    s = 2048 / 1600
    def T(r, txt, st, tok, al="L"):
        x, y, w, h = r
        cv.text(x, y, w, h, txt, st, tok, al, fit=False)
    T(L["zLbl"], "ZULU", "stripLabel", "accent")
    T(L["zDig"], "12:35:42", "stripBig", "text")
    T(L["zSub"], "DTG 111235Z NOV 26", "stripSub", "text2")
    cv.rect(*L["div1"], rgb("line"))
    T(L["lLbl"], "LOCAL", "stripLabel", "muted")
    T(L["lDig"], "06:12", "stripMid", "text2")
    T(L["lSub"], "14 JUN 2035", "stripSub", "text2")
    cv.rect(*L["div2"], rgb("line"))
    T(L["cLbl"], "H-HOUR", "stripLabel", "text")
    T(L["cDig"], "H+00:35:42", "stripChrono", "red")
    T(L["cSub"], "dépassé", "stripSub", "muted")


def plate(cv):
    L = WALLS["PLATE"]
    cv.rect(*L["bar"], rgb("accent"))
    cv.text(*L["name"], "ALPHA 1", "plateName", "text")
    x, y, w, h = L["sub"]
    cv.rect(x + 4, y + h / 2 - 14, 28, 28, rgb("accent"))
    cv.text(x + 44, y, w - 44, h, "4 nœud(s) ATAK · 3 en direct", "plateSub", "text2")


# ------------------------------------------------------------------------------------------------- texte à LED
def led_panel(w, h, kind, lines, tok="accent", border=True, scroll_x=None, gap_chars=6):
    """Affichage TEXTE (fn_tocText.sqf, mêmes nombres que « _render ») : panneau noir, caractères à chasse fixe gras,
    bordure fine de la couleur du message. Longueurs en hauteurs de panneau. lines : une ou deux lignes fixes ;
    scroll_x : texte défilant (une ligne), x du premier texte en largeurs de caractère (deux textes séparés du blanc)."""
    ins, bth, pad_x = (0.07, 0.02, 0.16) if kind == "LED" else (0.03, 0.006, 0.05)
    f1 = {"LED": 0.6, "STD": 0.26, "COL": 0.15}[kind]
    f2 = {"LED": 0.0, "STD": 0.19, "COL": 0.12}[kind]
    char_k = 0.6
    img = Image.new("RGBA", (w, h), rgb("bg0"))
    d = ImageDraw.Draw(img)
    if border:
        col = rgb(tok, 0.55)
        i, t = ins * h, max(1, bth * h)
        for box in ((i, i, w - i, i + t), (i, h - i - t, w - i, h - i), (i, i, i + t, h - i), (w - i - t, i, w - i, h - i)):
            d.rectangle([round(v) for v in box], fill=col)
    x0, x1 = (ins + bth + pad_x) * h, w - (ins + bth + pad_x) * h
    y0, y1 = (ins + bth) * h, h - (ins + bth) * h
    clip = Image.new("RGBA", (round(x1 - x0), round(y1 - y0)), (0, 0, 0, 0))
    cd = ImageDraw.Draw(clip)

    def put(txt, f, x, cy):
        fnt = font("EtelkaMonospaceProBold", f * h / 0.86 * 0.98)
        cw = char_k * f * h
        asc, desc = fnt.getmetrics()
        for k, ch in enumerate(txt):
            gw = fnt.getlength(ch)
            cd.text((x + k * cw + (cw - gw) / 2, cy - (asc + desc) / 2), ch, font=fnt, fill=rgb(tok))
        return len(txt) * cw

    cw_, ch_ = clip.size
    if scroll_x is not None:
        cw = char_k * f1 * h
        txt = lines[0]
        loop = max((len(txt) + gap_chars) * cw, cw_)
        put(txt, f1, scroll_x * cw, ch_ / 2)
        put(txt, f1, scroll_x * cw + loop, ch_ / 2)
    elif len(lines) == 1:
        tw = len(lines[0]) * char_k * f1 * h
        put(lines[0], f1, (cw_ - tw) / 2, ch_ / 2)
    else:
        lh = f2 * 1.3 * h
        for k, ln in enumerate(lines):
            tw = len(ln) * char_k * f2 * h
            put(ln, f2, (cw_ - tw) / 2, (ch_ - 2 * lh) / 2 + (k + 0.5) * lh)
    img.alpha_composite(clip, (round(x0), round(y0)))
    return img


def stack(images, pad=28, bg=(22, 24, 27, 255)):
    """Plusieurs panneaux l'un sous l'autre sur un fond neutre (planche de maquette)."""
    w = max(i.size[0] for i in images) + 2 * pad
    h = sum(i.size[1] for i in images) + pad * (len(images) + 1)
    out = Image.new("RGBA", (w, h), bg)
    y = pad
    for i in images:
        out.alpha_composite(i, ((w - i.size[0]) // 2, y))
        y += i.size[1] + pad
    return out


def text_mockups():
    # Bandeau 4:1 (bandeau horloge existant) et bandeau LED 8:1 : texte qui tient, fixe et centré.
    save(stack([led_panel(2048, 512, "LED", ["TOC BRAVO"]),
                led_panel(2048, 256, "LED", ["BRIEFING 14:30Z"], "amber"),
                led_panel(2048, 256, "LED", ["FLASH · CONTACT NORD"], "red")]), "bandeau_texte")
    # Bandeau LED 16:1 : texte plus large que le panneau, en plein défilement (deux passages séparés d'un blanc).
    long_txt = "OPÉRATION HORIZON · H-00:42:10 · COUVRE-FEU RADIO À 21:00Z · MÉTÉO : VENT 270/15 KT, VISI 8 KM"
    save(stack([led_panel(4096, 256, "LED", [long_txt], scroll_x=-31.0),
                led_panel(4096, 256, "LED", ["ZONE CONTRÔLÉE · BADGE OBLIGATOIRE · 12:35Z"], "text")]), "bandeau_texte_long")
    # Écran 16:9 : deux lignes fixes (taille automatique).
    save(led_panel(1600, 900, "STD", ["RELÈVE DES", "POSTES 18:00Z"]), "mur_texte")


# ------------------------------------------------------------------------------------------------------------- PC
GRID = {k: v for k, v in re.findall(r'\["(\w+)", ([0-9.]+)\]', re.search(r"private _grid = createHashMapFromArray \[(.*?)\n\];", LAP_SRC, re.S).group(1))}
GRID = {k: float(v) for k, v in GRID.items()}
LAYOUT = {k: v for k, v in json.loads(re.search(r"private _layout = createHashMapFromArray (\[\n.*?\n        \]);", LAP_SRC, re.S).group(1))}
BTNS = {b[0]: b for b in json.loads(re.search(r"private _btnDefs = (\[\n.*?\n        \]);", LAP_SRC, re.S).group(1))}
KIND = {}
for cls, key in re.findall(r'\["(COMSPEC_\w+|RscPicture)", "([^"]+)"(?:, true)?\] call _mk\b', LAP_SRC):
    KIND[key] = cls
for key in re.findall(r'\["([^"]+)"\] call _stx', LAP_SRC):
    KIND[key] = "stx"
for key, txt in re.findall(r'\["(\w+)", "([^"]+)"\] call _lblOf', LAP_SRC):
    KIND[key] = ("lbl", txt)
for key in re.findall(r'\["(\w+)"(?:, [^\n]*?)?\] call _combo', LAP_SRC):
    KIND.setdefault(key, "combo")
for key in re.findall(r'\["(\w+)"(?:, [^\n]*?)?\] call _edit', LAP_SRC):
    KIND.setdefault(key, "edit")
for f in ("fn_tocDraw.sqf", "fn_tocHallUi.sqf"):
    for cls, key in re.findall(r'\["(COMSPEC_\w+)", "(\w+)"', (TOC / f).read_text(encoding="utf-8")):
        KIND.setdefault(key, cls)


def pc_place(rows, rx, ry, rw, rh):
    """Même calcul que _place (fn_tocLaptop) : [clé => [x, y, l, h]] en hauteurs d'écran."""
    gap = GRID["gap"]
    fixed = sum(GRID[r[0]] for r in rows if isinstance(r[0], str))
    flex = sum(r[0] for r in rows if not isinstance(r[0], str))
    fu = max(0, rh - fixed - max(0, len(rows) - 1) * gap) / max(flex, 0.0001)
    out = {}
    y = ry
    for hk, wts, cells in rows:
        hrow = GRID[hk] if isinstance(hk, str) else fu * hk
        fw = sum(-w for w in wts if w < 0)
        share = sum(w for w in wts if w > 0)
        free = max(0, rw - fw - (len(wts) - 1) * gap)
        xs, ws, x = [], [], rx
        for w in wts:
            cw = -w if w < 0 else free * w / max(share, 0.0001)
            xs.append(x); ws.append(cw); x += cw + gap
        col = 0
        for cell in cells:
            key = cell[0] if isinstance(cell, list) else cell
            span = cell[1] if isinstance(cell, list) and len(cell) > 1 else 1
            hc = cell[2] if isinstance(cell, list) and len(cell) > 2 else ""
            last = min(col + span - 1, len(wts) - 1)
            ch = GRID[hc] if hc else hrow
            if key:
                out[key] = (xs[col], y + (hrow - ch) / 2, xs[last] + ws[last] - xs[col], ch)
            col = last + 1
        y += hrow + gap
    return out


SECTIONS = json.loads(re.search(r"private _sections = (\[\n.*?\n\]);", LAP_SRC.replace("\r\n", "\n"), re.S).group(1))


def sec_of(tab):
    return next((s for s, tabs in SECTIONS if tab in tabs), SECTIONS[0][0])


def pc_regions(aspect):
    """Même calcul que « load » (fn_tocLaptop, console plein écran de la 4.9.0), en hauteurs d'écran."""
    g = GRID
    gap = g["gap"]
    xi, yi, wi, hi = 0.0, 0.0, aspect, 1.0
    topH = g["title"] + 2 * gap
    bodyY = yi + topH + 2 * gap
    bodyH = (yi + hi - g["bar"] - gap) - bodyY
    bx = xi + g["rail"] + 2 * gap
    by = bodyY + g["tab"] + 2 * gap
    bh = (yi + hi - g["bar"] - gap) - by
    pw = min(max(wi * g["panel"], g["panelMin"]), g["panelMax"])
    px = xi + wi - pw
    cx, cw = px + 2 * gap, pw - 4 * gap
    cy = by + gap
    return {
        "inner": (xi, yi, wi, hi), "top": (xi, yi, wi, topH), "panel": (px, by, pw, bh),
        "railBg": (xi, yi + topH + g["hair"], g["rail"], hi - topH - g["bar"] - g["hair"]),
        "rTitle": (xi + 2 * gap, yi + gap, wi - 4 * gap, g["title"]),
        "rRail": (xi + gap, bodyY, g["rail"] - 2 * gap, bodyH),
        "rTabs": (bx, bodyY, (xi + wi - 2 * gap) - bx, g["tab"]),
        "rBar": (xi, yi + hi - g["bar"], wi, g["bar"]),
        "rLeft": (bx, by, (px - gap) - bx, bh),
        "rHelp": (cx, by + bh - gap - g["help"], cw, g["help"]),
        "rRight": (cx, cy, cw, (by + bh - gap - g["help"] - 2 * gap) - cy),
    }


SAMPLE = {
    "gList": [("ALPHA 1-1 · 4 joueur(s)", "accent"), ("ALPHA 1-2 · 4 joueur(s)", "accent"), ("BRAVO 2 · 3 joueur(s)", "amber"), ("CHARLIE 3 · 5 joueur(s)", "accent"),
              ("DELTA 4 · 4 IA", "text"), ("ECHO 5 · 2 joueur(s)", "accent"), ("VIPER 1 · 1 joueur(s)", "accent")],
    "uList": [("ALPHA 1-1 · Alpha 1-1", "accent"), ("ALPHA 1-2 · Alpha 1-2", "accent"), ("BRAVO 2 · Bravo 2 · 3 à bord", "amber"), ("CHARLIE 3 · Charlie 3", "accent"),
              ("DELTA 4 · Delta 4", "text2"), ("ECHO 5 · Echo 5 · 2 à bord", "accent"), ("VIPER 1 · Viper 1", "accent"), ("Sdt Garnier · Alpha 1-1", "text2"),
              ("Cpl Lefèvre · Alpha 1-2", "text2"), ("GOLF 7 · Golf 7", "lost"), ("HOTEL 8 · Hotel 8", "muted")],
    "fList": [(f"{t}  {k}{'' if p == 'ROUTINE' else ' ' + p} · {w}{' · ' + g if g else ''}", PRIO_TOK[p] if p != "ROUTINE" else "text") for t, k, p, w, g, x in EVENTS[:12]],
    "bList": [("1re SECTION (1SEC)", "blue"), ("   Chef : Lt Moreau", "accent"), ("   Adjoint : Sgt Dubois", "accent"), ("   Cpl Lefèvre", "text2"), ("   Sdt Garnier", "accent"),
              ("2e SECTION (2SEC)", "blue"), ("   Chef : Sgt Bernard", "accent"), ("   Cpl Petit", "text2"), ("   poste vacant", "muted")],
    "tList": [("H-HOUR", "text"), ("ALPHA EN POSITION", "text"), ("RELÈVE BRAVO", "text"), ("MEDEVAC CHARLIE", "text")],
    "lList": [(f"{t}Z  {k}  {x}", {"FLASH": "red", "CR": "amber", "DEMANDE": "amber", "EXCHECK": "accent", "POSTE": "blue"}.get(k, "text")) for t, k, x in
              [("12:35:02", "FLASH", "CONTACT NORD, TOUS AUX ABRIS"), ("12:34:10", "CR", "TIC ALPHA 1-1 grille 042 116"), ("12:31:44", "DEMANDE", "MEDEVAC CHARLIE 3"),
               ("12:30:00", "EXCHECK", "3 · TANGO passé"), ("12:27:15", "CR", "SITREP BRAVO 2 : en position"), ("12:20:02", "POSTE", "Poste pris par Adj Mercier"),
               ("12:18:40", "NOTE", "RAS : relève faite au poste nord"), ("12:11:09", "CR", "CONTACT DELTA 4")]],
    "xList": [("1. QUEBEC · Départ de la base · TOUS", "accent"), ("2. SIERRA · H-HOUR · TOUS", "accent"), ("3. TANGO · Ligne de départ · ALPHA", "accent"),
              ("4. ROMEO · Franchissement LD · ALPHA", "text"), ("5. UNIFORM · Appui feu en place · VIPER", "text"), ("6. VICTOR · Assaut RAVEN · ALPHA", "text")],
    "qList": [("12:31Z  MEDEVAC · CHARLIE 3 · 048 117", "amber"), ("12:29Z  APPUI FEU · ALPHA 1-1 · 044 118", "blue"), ("12:20Z  RAVITAILLEMENT · TOC BRAVO-6", "amber"),
              ("12:02Z  APPUI FEU · DELTA 4 · 039 111", "accent"), ("11:30Z  RENFORT · ALPHA 1-2", "red")],
    "pList": [("Objectif · OBJ RAVEN", "text"), ("Ligne de phase · PL BLUE", "text"), ("Ligne de phase · PL RED", "text"), ("Axe d'attaque · AXE NORD", "text"),
              ("Zone d'opération · AO BRAVO", "text"), ("Point de contrôle · CP 1", "text"), ("Zone de poser · HLZ SUD", "text")],
}
RIGHT = {"tList": ["H+00:35:42", "-00:04:12", "-00:20:00", "+00:03:51"], "xList": ["passé 11:16", "passé 12:00", "passé 12:30", "prévu 12:45", "prévu 12:50", "prévu 13:05"],
         "qList": ["REÇUE", "PRISE EN COMPTE", "REÇUE", "TERMINÉE", "REFUSÉE"], "bList": ["", "en jeu", "en jeu", "hors ligne", "en jeu", "", "en jeu", "hors ligne", "poste vacant"],
         "gList": ["téléphone", "téléphone", "téléphone", "téléphone", "source directe", "téléphone", "téléphone"],
         "uList": ["en direct", "en direct", "point ancien", "en direct", "sans téléphone · source directe", "en direct", "en direct", "sans téléphone · source directe",
                   "sans téléphone · source directe", "liaison perdue", "téléphone éteint"],
         "pList": ["Adj Mercier", "Adj Mercier", "Adj Mercier", "Cne Martin", "le site", "Sgt Dubois", "Sgt Dubois"]}
INFO = {
    "gInfo": [("ALPHA 1-1", "heading"), ("Chef Sgt Dubois · n° 6", "body"), ("4 vivant(s) dont 4 joueur(s) · téléphone sur le réseau", "body"), ("Grille 042 116 · en direct", "body")],
    "uInfo": [("DELTA 4   Fusilier · groupe Delta 4", "heading"), ("Grille 0391 1112 · 6 km/h · hauteur 0 m", "body"), ("Santé : indemne · 6 chargeur(s) de rechange", "body"),
              ("Liaison : sans téléphone · source directe", "body")],
    "fInfo": [("TIC  12:34 · ALPHA 1-1 · grille 042 116", "heading"), ("Contact NE 300 m, 2 PAX armés, demande appui mortier", "body")],
    "bInfo": [("Sgt Dubois", "heading"), ("Adjoint · 1re SECTION", "body"), ("En jeu", "accent")],
    "oInfo": [("ORDRES ÉMIS  2", "heading"), ("12:18 TENIR → ALPHA 1-1 · IMPORTANT", "body"), ("   vu 4 · reçu 4 · en cours 1 · terminé 0", "muted"), ("12:02 DÉPLACER → BRAVO 2 · ROUTINE", "body"), ("   vu 3 · reçu 3 · en cours 0 · terminé 3", "muted")],
    "cInfo": [("MESSAGES DU POSTE", "heading"), ("11:51 ROUTINE · Cne Martin", "body"), ("   Fréquence de repli 47.300", "muted")],
    "rInfo": [("RÉSEAU", "heading"), ("18 en ligne · 3 anciens · 1 perdu · 2 hors ligne", "body"), ("14 joueurs · 6 IA", "body"), ("2 relais · 0 brouilleur actif", "body"), ("", "body"),
              ("MÉTÉO", "heading"), ("Nuages 30 % · pluie 0 % · brouillard 5 %", "body"), ("Vent 4 m/s du 270°", "body"), ("", "body"),
              ("Relais et portée sur la carte : SITUATION, onglet CARTE, calques RELAIS et COUVERTURE.", "muted"), ("Unités sans téléphone lues dans le jeu : 11", "body")],
    "tInfo": [("Chrono : temps écoulé depuis le lancement. Compte à rebours : temps restant,", "muted"), ("rouge une fois dépassé. Heure H : temps jusqu'à une heure ZULU.", "muted")],
    "kInfo": [("AN/PRC-152  canal 3 · ALPHA NET", "heading"), ("● RX   ● TX   47.300 MHz · alternat 1 · radio active", "body"), ("", "body"), ("AN/PRC-117F  canal 1 · CMD NET", "heading"), ("● RX   ● TX   32.500 MHz · alternat 2", "body"), ("", "body"), ("EN ÉMISSION", "heading"), ("● ALPHA 1-1 · 47.300 MHz · 4 s", "body")],
    "lInfo": [("Heures en ZULU. S'inscrivent seuls : comptes rendus, demandes, prises de poste, FLASH.", "muted")],
    "xInfo": [("PASSÉ inscrit l'heure ZULU réelle et une entrée à la main courante.", "muted")],
    "kcInfo": [("ÉLÉMENT                 AMIS BL. · TUÉS    ENN. NEUTR. · BL.", "muted"), ("ALPHA 1-1                    1 · 0              4 · 1", "body"), ("BRAVO 2                        1 · 1              1 · 2", "body"), ("VIPER 1                        0 · 0              4 · 1", "body")],
    "qInfo": [("MEDEVAC  12:31Z · CHARLIE 3 · grille 048 117 · REÇUE", "heading"), ("1 urgence A, HLZ sud, fumée verte", "body")],
    "hInfo": [("TOC BRAVO", "heading"), ("6 écran(s) lié(s) · 2 scène(s)", "muted")],
    "hSel": [("MUR CENTRE · dalle 2 · carte et flux", "heading"), ("lié à la salle", "muted")],
    "sInfo": [("SAT 1 · vous avez la main", "heading"), ("800 m de large · grille 042 116", "body")],
    "sZoom": [("0.50X", "heading")],
    "pInfo": [("PL BLUE   Ligne de phase", "heading"), ("4 point(s) · grille 043 115 · posé par Adj Mercier", "body")],
    "pHow": [("Les outils sont sous la carte ; leurs réglages apparaissent sur la ligne du dessous.", "body"), ("", "body"),
             ("MARQUEUR : un symbole avec son nom et sa durée. Exemple : CP NORD, 30 min.", "text"),
             ("TRAIT : clic sur A, Ctrl+clic pour chaque étape, clic sur B. ROUTES suit les routes.", "text"),
             ("DESSIN : clic gauche maintenu, à main levée. FERMÉ referme la forme en zone.", "text"),
             ("PLAN : zones, lignes de phase, limites, axes et points. Exemple : PL BLUE, OBJ RAVEN.", "text"),
             ("EFFACER : clic sur un marqueur, un trait ou un élément du plan. ANNULER : mon dernier geste.", "text"), ("", "body"),
             ("Tout ce qui est dessiné ici est partagé en direct : cartes des joueurs, écrans de la salle en carte, console web. Aucun téléphone n'est nécessaire.", "muted")],
    "wInfo": [("TOC BRAVO   8 écran(s) ou dalle(s) 16:9 · 2 en diffusion", "heading"),
              ("TOUT SUR SITUATION : carte et flux partout. MUR DE CONDUITE : carte, main courante, exécution, demandes, horloge et flux, dans l'ordre des écrans. TOUT SUR CARTE : carte seule partout.", "body"),
              ("Un écran précis, son texte, sa mise en page ou sa luminosité : onglet SALLE, en cliquant l'écran sur le plan.", "body")],
}
PANEL_HEAD = {"CARTE": ("UNITÉS DU CAMP", "42 sur la carte · 11 sans téléphone"), "GROUPES": ("GROUPES", "9 groupes · 29 en ligne"), "ORBAT": ("ORBAT DU SITE", "31 membres"),
              "FLUX": ("FLUX", "15 entrées · 3 alertes"), "ORDRES": ("ORDRES", "2 émis"), "CAMP": ("MESSAGES AU CAMP", ""), "RÉSEAU": ("RÉSEAU", "14 joueurs · 6 IA · 2 relais"),
              "CHRONOS": ("CHRONOS", "4 chronos"), "RADIO": ("RADIOS DE L'OPÉRATEUR", ""), "SALLE": ("SALLE TOC", ""), "MUR": ("MUR D'IMAGES", ""),
              "MAIN COURANTE": ("MAIN COURANTE", "38 entrées · ZULU"), "EXCHECK": ("EXCHECK", "3 / 9 passés"), "PERTES": ("PERTES ET RÉSULTATS", "5 éléments"),
              "DEMANDES": ("DEMANDES", "2 reçues"), "DESSIN": ("DESSINER SUR LA CARTE", "7 éléments au plan"), "PLAN": ("PLAN DU CAMP", "7 éléments au plan")}
TAB_HELP = dict(re.findall(r'\["([^"]+)", "([^"]+)"\]', re.search(r"private _tabHelp = createHashMapFromArray \[(.*?)\n            \];", LAP_SRC.replace("\r\n", "\n"), re.S).group(1)))
COMBO = {"oKind": "DÉPLACER", "oPrio": "ROUTINE", "oTarget": "Groupe ALPHA 1-1", "cDest": "Tout le camp", "cPrio": "ROUTINE", "fFilter": "Tout le flux",
         "gAi": "Aller au point", "rShow": "Carte et flux", "cCam": "Caméra · ALPHA 1-1 (casque)", "tKind": "Compte à rebours", "kcEl": "ALPHA 1-1",
         "kcKind": "Ami blessé", "hRad": "20 m", "hWhat": "Carte et flux", "hMosRot": "Rotation 10 s", "hMosCase": "Case 1", "hMosSrc": "ALPHA 1-1",
         "hScene": "OPÉRATION", "dColor": "Rouge", "dWidth": "Moyen", "dKind": "Ligne · Ligne de phase", "mAff": "Ami", "mType": "Objectif", "mColor": "Bleu", "mTtl": "Permanent", "mChan": "Camp",
         "fKindNew": "SITREP", "qKind": "MEDEVAC", "wSrc": "MUR CENTRE · carte et flux", "wLayout": "Situation + Réseau", "wLum": "Luminosité 75 %", "sFollow": "Groupe ALPHA 1-1", "sScr": "Tous les écrans du poste"}
EDIT = {"search": "Grille, groupe, indicatif ou marqueur…", "flashTxt": "Texte du FLASH…", "oText": "Tenir le carrefour jusqu'à la relève", "oGrid": "0441 1183",
        "cText": "", "cCs": "BRAVO-6", "tName": "EXFIL", "tVal": "30:00", "lText": "Entrée à la main…", "xEv": "Événement…", "xCw": "Mot de code", "xEl": "Élément",
        "xAt": "12:45", "qAns": "Hélico décolle, ETA 8 min", "hName": "TOC BRAVO", "hScrName": "MUR CENTRE", "hSceneName": "Nom de la scène…", "dPlanTxt": "PL BLUE", "mText": "CP NORD",
        "fText": "Pont nord détruit, itinéraire BLEU fermé", "fGrid": "0441 1183", "qGrid": "0481 1172", "qText": "1 blessé urgent, zone de poser au sud…", "sGrid": "042 116", "sNote": "Note…"}
LAYERS_ON = {"lyPL": True, "lyAI": True, "lyDIR": True, "lyVEH": True, "lyAIR": True, "lyEV": True, "lyPLAN": True, "lyREL": False, "lyCOV": False}
PC_NODES = [n if n[2] != "DELTA 4" else (n[0], n[1], n[2], "direct", n[4]) for n in NODES] + [(300, 420, "Sdt Garnier", "direct", "inf"), (470, 520, "Cpl Lefèvre", "direct", "inf"), (860, 300, "GOLF 7", "direct", "veh")]


def wrap(s, name, px, w):
    out, cur = [], ""
    for word in s.split(" "):
        t = (cur + " " + word).strip()
        if text_w(t, name, px) > w - 8 and cur:
            out.append(cur)
            cur = word
        else:
            cur = t
    return out + ([cur] if cur else [])


def pc_map(cv, x, y, w, h, u, plan=False, sel=None):
    """Carte de la console : unités au téléphone et en source directe, plan du camp, échelle et nord dessinés sur la carte."""
    cv.img.paste(terrain(int(w), int(h), 9), (int(x), int(y)))
    for gx in range(1, 8):
        cv.rect(x + gx * w / 8, y, 1, h, rgb("lineStrong", 0.35))
    for gy in range(1, 6):
        cv.rect(x, y + gy * h / 6, w, 1, rgb("lineStrong", 0.35))
    fp = 0.019 * u
    if plan:
        cv.line([(x + w * 0.18, y + h * 0.2), (x + w * 0.3, y + h * 0.42), (x + w * 0.26, y + h * 0.7), (x + w * 0.34, y + h * 0.9)], rgb("blue"), 3)
        cv.text(x + w * 0.3, y + h * 0.4, 160, 26, "PL BLUE", None, "blue", "L", px=fp, fname=FONTS["sansBold"], fit=False)
        cv.line([(x + w * 0.5, y + h * 0.15), (x + w * 0.56, y + h * 0.45), (x + w * 0.52, y + h * 0.85)], rgb("red"), 3)
        cv.text(x + w * 0.56, y + h * 0.43, 160, 26, "PL RED", None, "red", "L", px=fp, fname=FONTS["sansBold"], fit=False)
        ox, oy = x + w * 0.74, y + h * 0.2
        cv.line([(ox - 70, oy - 45), (ox + 70, oy - 45), (ox + 70, oy + 45), (ox - 70, oy + 45), (ox - 70, oy - 45)], rgb("amber"), 3)
        cv.text(ox - 70, oy - 14, 140, 28, "OBJ RAVEN", None, "amber", "C", px=fp, fname=FONTS["sansBold"], fit=False)
        cv.line([(x + w * 0.22, y + h * 0.56), (x + w * 0.62, y + h * 0.42)], rgb("accent"), 4)
        cv.line([(x + w * 0.62, y + h * 0.42), (x + w * 0.60, y + h * 0.455)], rgb("accent"), 4)
        cv.line([(x + w * 0.62, y + h * 0.42), (x + w * 0.595, y + h * 0.41)], rgb("accent"), 4)
        cv.text(x + w * 0.36, y + h * 0.5, 160, 26, "AXE NORD", None, "accent", "L", px=fp, fname=FONTS["sansBold"], fit=False)
    for nx, ny, cs, st, k in PC_NODES:
        X, Y = x + nx / 1040 * w, y + ny / 774 * h
        if cs == sel:
            cv.line([(X - 30, Y - 22), (X + 30, Y - 22), (X + 30, Y + 22), (X - 30, Y + 22), (X - 30, Y - 22)], rgb("accent"), 2)
        nato(cv, X, Y, "lost" if st == "lost" else "friend", 1.0 if st in ("live", "direct") else 0.6, k)
        lbl = cs + {"old": " · ancien", "lost": " · perdu"}.get(st, "")
        cv.text(X + 22, Y - 14, 240, 28, lbl, None, "text", "L", px=fp, fname=FONTS["sansBold"], fit=False)
    # échelle (bas gauche) et nord (haut droite), dessinés sur la carte comme en jeu
    sx, sy = x + w * 0.02, y + h - 0.03 * u
    bar = w * 0.14
    cv.rect(sx - 8, sy - 0.018 * u, bar + 0.09 * u, 0.036 * u, rgb("bg0", 0.8))
    cv.rect(sx, sy - 2, bar, 4, rgb("text"))
    cv.rect(sx, sy - 8, 3, 16, rgb("text"))
    cv.rect(sx + bar - 3, sy - 8, 3, 16, rgb("text"))
    cv.text(sx + bar + 6, sy - 0.015 * u, 0.08 * u, 0.03 * u, "500 m", None, "text", "L", px=0.02 * u, fname=FONTS["monoBold"], fit=False)
    nx, ny = x + w - 0.045 * u, y + 0.035 * u
    cv.line([(nx, ny - 12), (nx + 10, ny + 10), (nx - 10, ny + 10), (nx, ny - 12)], rgb("text"), 3)
    cv.text(nx + 12, ny - 14, 30, 28, "N", None, "text", "L", px=0.022 * u, fname=FONTS["sansBold"], fit=False)


def pc(tab, out, aspect=16 / 9, H=1080, tool="SELECT"):
    W = round(H * aspect)
    cv = Canvas(W, H)
    u = H
    R = lambda r: (r[0] * u, r[1] * u, r[2] * u, r[3] * u)
    reg = pc_regions(aspect)
    g = GRID
    fpx = lambda k: g[k] * u
    sec = sec_of(tab)
    cv.rect(*R(reg["top"]), rgb("bg1"))
    tx, ty, tw, th = reg["top"]
    cv.rect(*R((tx, ty + th, tw, g["hair"])), rgb("line"))
    cv.rect(*R(reg["railBg"]), rgb("bg1"))
    cv.rect(*R(reg["panel"]), rgb("bg1"))
    if tab == "SAT":
        left = "L:SAT"
    elif tab in ("SALLE", "MUR"):
        left = "L:SALLE"
    elif sec == "PLANS":
        left = "L:MAP:" + (tool if tool in ("LINE", "DRAW", "PLAN", "MARKER", "ERASE") else "SELECT")
    else:
        left = "L:MAP:NAV"
    zones = [("F:TITLE", "rTitle"), ("F:RAIL", "rRail"), ("F:TABS:" + sec, "rTabs"), ("F:HELP", "rHelp"), ("F:BAR", "rBar"), (left, "rLeft"), ("R:" + tab, "rRight")]
    rects = {}
    for lk, rk in zones:
        rects.update(pc_place(LAYOUT[lk], *reg[rk]))
    body = fpx("font")
    sb = 0.024 * u  # base du texte structuré du PC (COMSPEC_RscStructuredText)
    hair = max(1, 0.001 * u)

    def fit_label(b, w, f0):
        lbl, ft = b[2], f0
        while text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and ft > fpx("fontMin"):
            ft -= 0.0005 * u
        if text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and b[3]:
            lbl, ft = b[3], f0
            while text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and ft > fpx("fontMin"):
                ft -= 0.0005 * u
        return lbl, ft

    for key, r in rects.items():
        x, y, w, h = R(r)
        kind = KIND.get(key, "stx")
        if key in BTNS:
            b = BTNS[key]
            if b[1] == "S":
                on = key == "sec" + sec
                cv.rect(x, y, w, h, rgb("bg2" if on else "bg1"))
                if on:
                    cv.rect(x, y, 0.004 * u, h, rgb("accent"))
                lbl, ft = fit_label(b, w, body)
                cv.text(x, y, w, h, lbl, None, "accent" if on else "text2", "C", px=ft, fname=FONTS["sansBold"], fit=False)
            elif b[1] == "T":
                on = key == "tab" + tab
                cv.rect(x, y, w, h, rgb("bg2" if on else "bg1"))
                if on:
                    cv.rect(x, y + h - 0.003 * u, w, 0.003 * u, rgb("accent"))
                cnt = {"tabGROUPES": " (9)", "tabORBAT": " (31)", "tabFLUX": " (15)", "tabORDRES": " (2)", "tabDEMANDES": " (2)", "tabCHRONOS": " (4)"}.get(key, "")
                lbl, ft = fit_label([b[0], b[1], b[2] + cnt, b[3] + cnt if b[3] else ""], w, fpx("fontTab"))
                cv.text(x, y, w, h, lbl, None, "accent" if on else "text2", "C", px=ft, fname=FONTS["sansBold"], fit=False)
            elif key == "alertsGo":
                cv.rect(x, y, w, h, rgb("redDim"))
                cv.text(x, y, w, h, "ALERTES (3)", None, "red", "C", px=body, fname=FONTS["sansBold"], fit=False)
            elif key in LAYERS_ON:
                on = LAYERS_ON[key]
                cv.rect(x, y, w, h, rgb("bg2"))
                cv.rect(x, y, w, hair, rgb("line"))
                lbl, ft = fit_label([b[0], b[1], ("● " if on else "○ ") + b[2], (("● " if on else "○ ") + b[3]) if b[3] else ""], w, body)
                cv.text(x, y, w, h, lbl, None, "accent" if on else "muted", "C", px=ft, fname=FONTS["sansBold"], fit=False)
            else:
                prim = b[5] == 1
                active = key == "tool" + tool
                cv.rect(x, y, w, h, rgb("accent" if prim else ("accentDim" if active else "bg2")))
                if not prim:
                    cv.rect(x, y, w, hair, rgb("line"))
                lbl, ft = fit_label(b, w, body)
                cv.text(x, y, w, h, lbl, None, "bg0" if prim else ("accent" if active else "text"), "C", px=ft, fname=FONTS["sansBold"], fit=False)
            continue
        if isinstance(kind, tuple):
            cv.text(x, y, w, h, kind[1], None, "muted", "L", px=0.017 * u, fname=FONTS["sansBold"])
            continue
        if key == "map":
            pc_map(cv, x, y, w, h, u, plan=True, sel="DELTA 4" if tab == "CARTE" else None)
            continue
        if key == "pHead":
            title, stat = PANEL_HEAD.get(tab, (tab, ""))
            cv.text(x, y, w, h, title, None, "text", "L", px=0.95 * sb, fname=FONTS["sansBold"])
            cv.text(x, y, w, h, stat, None, "muted", "R", px=0.8 * sb, fname=FONTS["sans"])
            continue
        if key == "pRule":
            cv.rect(x, y + h / 2 - 0.0005 * u, w, hair, rgb("line"))
            cv.rect(x, y + h / 2 - 0.001 * u, 0.04 * u, 0.002 * u, rgb("accent"))
            continue
        if key == "title":
            cv.text(x, y, w, h * 0.55, "POSTE TOC · BRAVO-6", None, "text", "L", px=1.1 * sb, fname=FONTS["sansBold"])
            cv.text(x, y + h * 0.5, w, h * 0.45, "TOC BRAVO · poste pris par Adj Mercier à 12:20Z", None, "accent", "L", px=0.8 * sb, fname=FONTS["sans"])
            continue
        if key == "clock":
            cv.text(x, y, w, h * 0.55, "12:35:42 ZULU   13:35:42 LOCAL", None, "text", "R", px=0.9 * sb, fname=FONTS["monoBold"], fit=False)
            cv.text(x, y + h * 0.5, w, h * 0.45, "ACRE2 · • SERVEUR EN DIRECT · • ATHENA", None, "accent", "R", px=0.75 * sb, fname=FONTS["sans"], fit=False)
            continue
        if key == "bar":
            cv.rect(x, y, w, h, rgb("bg1"))
            cv.rect(x, y, w, hair, rgb("line"))
            xx = x + 0.012 * u
            for s, tok in [("TÉLÉPHONES  ", "text"), ("26 en ligne", "accent"), (" · ", "muted"), ("3 anciens", "amber"), (" · ", "muted"), ("1 sans liaison", "lost"), (" · ", "muted"), ("1 éteint", "muted"),
                           ("     SOURCE DIRECTE  ", "text"), ("11 sans téléphone", "text2"), ("     2 relais · 0 brouilleur", "muted"), ("     3 alerte(s) sur 20 min", "red")]:
                xx += cv.text(xx, y, w, h, s, None, tok, "L", px=0.85 * sb, fname=FONTS["sans"], fit=False)
            continue
        if key == "help":
            cv.rect(x, y, w, h, rgb("bg0"))
            yy = y + 0.004 * u
            for ln in wrap(TAB_HELP.get(tab, ""), FONTS["sans"], 0.8 * sb, w - 0.01 * u)[:2]:
                cv.text(x + 0.004 * u, yy, w, 0.8 * sb * 1.15, ln, None, "muted", "L", px=0.8 * sb, fname=FONTS["sans"], fit=False)
                yy += 0.8 * sb * 1.15
            continue
        if key == "railInfo":
            cv.text(x, y + 0.01 * u, w, 0.04 * u, "42", None, "text", "L", px=1.3 * sb, fname=FONTS["monoBold"], fit=False)
            yy = y + 0.05 * u
            for s, tok in [("unités du camp", "text2"), ("31 au téléphone", "accent"), ("11 sans téléphone", "text2")]:
                cv.text(x, yy, w, 0.022 * u, s, None, tok, "L", px=0.75 * sb, fname=FONTS["sans"], fit=False)
                yy += 0.022 * u
            yy += 0.02 * u
            for s in ["F1 à F4 : rubriques", "Page préc. / suiv. :", "onglets", "+ et − : zoom", "Ctrl+F : chercher", "Échap : fermer"]:
                cv.text(x, yy, w, 0.02 * u, s, None, "muted", "L", px=0.7 * sb, fname=FONTS["sans"], fit=False)
                yy += 0.02 * u
            continue
        if key == "mapInfo":
            xx = x + 0.004 * u
            for s, tok, fn in [("Curseur ", "text2", "sans"), ("0441 1183", "text", "mono"), (" · carte large de 2.6 km · ", "text2", "sans"),
                               ("clic droit : point désigné", "muted", "sans")]:
                xx += cv.text(xx, y, w, h, s, None, tok, "L", px=0.8 * sb, fname=FONTS[fn], fit=False) + (6 if fn == "mono" else 0)
            continue
        if kind in ("COMSPEC_RscTocList",):
            cv.rect(x, y, w, h, rgb("bg0"))
            rh = 0.027 * u
            pick = {"uList": 4}.get(key, 1)
            for i, (s, tok) in enumerate(SAMPLE.get(key, [])):
                if (i + 1) * rh > h:
                    break
                if i == pick:
                    cv.rect(x, y + i * rh, w, rh, rgb("accentDim"))
                cv.text(x, y + i * rh, w * 0.62, rh, s, None, tok, "L", px=0.021 * u, fname=FONTS["sans"])
                rs = RIGHT.get(key, [])
                if i < len(rs) and rs[i]:
                    cv.text(x + w * 0.5, y + i * rh, w * 0.5, rh, rs[i], None, tok if key == "uList" else "text2", "R", px=0.021 * u, fname=FONTS["mono"] if key == "tList" else FONTS["sans"])
            continue
        if kind in ("combo", "COMSPEC_RscTocCombo"):
            cv.rect(x, y, w, h, rgb("bg0"))
            cv.rect(x, y + h - hair, w, hair, rgb("line"))
            cv.text(x, y, w - 0.02 * u, h, COMBO.get(key, ""), None, "text", "L", px=0.024 * u * 0.85, fname=FONTS["sans"])
            cv.text(x + w - 0.022 * u, y, 0.02 * u, h, "▾", None, "muted", "C", px=0.02 * u, fname=FONTS["sans"], fit=False)
            continue
        if kind in ("edit", "COMSPEC_RscTocEdit", "COMSPEC_RscTocEditMulti"):
            cv.rect(x, y, w, h, rgb("bg0"))
            cv.rect(x, y + h - hair, w, hair, rgb("line"))
            sample = EDIT.get(key, "")
            cv.text(x, y, w, min(h, 0.034 * u), sample, None, "muted" if sample.endswith("…") else "text", "L", px=0.024 * u * 0.85, fname=FONTS["sans"])
            continue
        if key == "sBack":
            cv.img.paste(isr_image(int(w), int(h), 33), (int(x), int(y)))
            isr_hud(cv, x, y, w, h, fpx=0.0175 * u, lpx=0.0155 * u, th=2, m=12)
            continue
        if key == "sStrip":
            cv.rect(x, y, w, h, rgb("bg0", 0.7))
            cv.text(x, y, w, h, "  Vous avez la main · SAT 1 · BLANC CHAUD · 0.50X", None, "accent", "L", px=0.85 * sb, fname=FONTS["sans"])
            continue
        if key == "hPlanBg":
            # Plan du mur vu depuis le poste : bandeau LED, mur de 6 dalles, deux écrans latéraux, un écran proche non lié.
            cv.rect(x, y, w, h, rgb("bg0"))
            tiles_ = [(0.2, 0.1, 0.6, 0.07, "TEXTE · OPÉRATION HORIZON · H-00:42", "std"), (0.02, 0.3, 0.15, 0.2, "HORLOGE", "std"), (0.83, 0.3, 0.15, 0.2, "○ PIP DU POSTE", "free")]
            names = ["SAT 1", "CARTE ET FLUX", "MOSAÏQUE", "MAIN COURANTE", "EXÉCUTION", "DIFFUSION DE 2"]
            for i in range(6):
                tiles_.append((0.2 + (i % 3) * 0.2, 0.22 + (i // 3) * 0.2, 0.2, 0.2, f"{i + 1} · {names[i]}", "sel" if i == 1 else "std"))
            tiles_ += [(0.3, 0.72, 0.18, 0.14, "1 · SITUATION", "std"), (0.48, 0.72, 0.18, 0.14, "2 · RÉSEAU", "std")]
            for fx, fy, fw, fh, lbl, stt in tiles_:
                bx_, by_, bw_, bh_ = x + w * fx + 1, y + h * fy + 1, w * fw - 2, h * fh - 2
                cv.rect(bx_, by_, bw_, bh_, rgb({"sel": "accentDim", "free": "bg1"}.get(stt, "bg3"), 0.85 if stt == "free" else 1))
                if stt == "sel":
                    cv.rect(bx_, by_, bw_, max(2, 0.002 * u), rgb("accent"))
                cv.text(bx_, by_, bw_, bh_, lbl, None, {"sel": "accent", "free": "muted"}.get(stt, "text"), "C", px=0.0155 * u, fname=FONTS["sansBold"])
            cv.text(x + w * 0.3, y + h * 0.87, w * 0.36, 0.03 * u, "ÉCRAN INCURVÉ DU POSTE", None, "muted", "C", px=0.0145 * u, fname=FONTS["sans"])
            continue
        if key == "hPlanTitle":
            cv.rect(x, y, w, h, rgb("bg0", 0.5))
            cv.text(x, y, w, h, "  Mur vu depuis ce poste · 6 lié(s) · 1 proche(s) non lié(s) (en sombre) · cliquez un écran ou une dalle", None, "text2", "L", px=0.85 * sb, fname=FONTS["sans"])
            continue
        if key in INFO:
            yy = y
            for s0, st in INFO[key]:
                big = st == "heading"
                lh = (1.05 if big else 0.85) * sb * 1.15
                for s in (wrap(s0, FONTS["sansBold" if big else "sans"], (1.05 if big else 0.85) * sb, w) or [""]):
                    if yy + lh > y + h:
                        break
                    cv.text(x, yy, w, lh, s, None, {"heading": "text", "body": "text2"}.get(st, st), "L", px=(1.05 if big else 0.85) * sb, fname=FONTS["sansBold" if big else "sans"], fit=False)
                    yy += lh
            continue
        if key in ("toolHint", "dInfo"):
            hints = {"SELECT": "Choisissez un outil ci-dessus : MARQUEUR, TRAIT, DESSIN, PLAN ou EFFACER. Sans outil : clic gauche sur une unité pour sa fiche.",
                     "PLAN": "PLAN : choisissez le graphisme, cliquez ses points, puis clic droit ou TERMINER · 2 clics pour une zone = rectangle",
                     "LINE": "TRAIT : clic sur A, Ctrl+clic pour chaque étape, clic sur B · ROUTES : le trait suit les routes"}
            msg = {"toolHint": hints.get(tool, hints["SELECT"]), "dInfo": "Réglages de l'outil : choisissez TRAIT, DESSIN, PLAN ou MARQUEUR."}[key]
            cv.text(x, y, w, h, msg, None, "text2" if key == "toolHint" else "muted", "L", px=0.8 * sb, fname=FONTS["sans"])
            continue
    save(cv.img, out)


# ---------------------------------------------------------------------------------------------------------- sortie
SAVED = []


def save(img, name):
    OUT.mkdir(parents=True, exist_ok=True)
    p = OUT / f"{name}.png"
    p.parent.mkdir(parents=True, exist_ok=True)
    img.convert("RGB").save(p, optimize=True)
    SAVED.append(p)


def contact_sheet():
    thumbs = []
    for p in SAVED:
        im = Image.open(p)
        im.thumbnail((480, 270))
        thumbs.append((p.stem, im))
    cols = 5
    rows = math.ceil(len(thumbs) / cols)
    W, H = cols * 500 + 20, rows * 320 + 20
    sheet = Image.new("RGB", (W, H), (12, 14, 16))
    d = ImageDraw.Draw(sheet)
    f = font(FONTS["sansBold"], 26)
    for i, (n, im) in enumerate(thumbs):
        x, y = 20 + (i % cols) * 500, 20 + (i // cols) * 320
        sheet.paste(im, (x + (480 - im.size[0]) // 2, y + (270 - im.size[1]) // 2))
        d.text((x, y + 276), n, font=f, fill=(200, 210, 215))
    sheet.save(OUT / "_planche.png", optimize=True)


# ------------------------------------------------------------------------------------------------- planche des tailles
def tailles_ecrans():
    """Planche « tailles_ecrans » : silhouettes de face de tous les écrans TOC (tools/model_toc_screens.py), à l'échelle,
    avec un soldat de 1,80 m. Zones d'image numérotées par index (0 = écran lui-même). Dimensions : celles des modèles."""
    S = 100                      # pixels par mètre
    T55 = (1.2166, 0.6874)       # pas d'une dalle 55" (jointure comprise)
    CEIL = 3.40                  # plafond dessiné pour les écrans suspendus
    W, ROW = 3960, 520
    img = Image.new("RGB", (W, 150 + 5 * ROW + 30), rgb("bg0")[:3])
    d = ImageDraw.Draw(img)
    fT, fN, fS, fI = font(FONTS["sansBold"], 64), font(FONTS["sansBold"], 34), font(FONTS["sans"], 30), font(FONTS["monoBold"], 30)
    col = lambda k: rgb(k)[:3]
    d.text((50, 30), "Tailles des écrans TOC", font=fT, fill=col("text"))
    d.text((50, 102), "Vues de face à l'échelle (1 m = %d px) · soldat de 1,80 m · chiffres dans l'image : index des zones (0 = écran principal)" % S,
           font=fS, fill=col("text2"))

    def soldier(x, floor):
        """Silhouette de 1,80 m, pieds en (x, floor)."""
        m = lambda a, b: (x + a * S, floor - b * S)
        c = col("lineStrong")
        d.polygon([m(-0.20, 0), m(-0.03, 0), m(-0.02, 0.86), m(0.02, 0.86), m(0.03, 0), m(0.20, 0), m(0.17, 0.92), m(0.19, 1.02),
                   m(0.23, 1.46), m(0.36, 1.02), m(0.33, 0.80), m(0.26, 0.82), m(0.27, 1.0), m(0.21, 1.5), m(0.08, 1.56), m(-0.08, 1.56),
                   m(-0.21, 1.5), m(-0.27, 1.0), m(-0.26, 0.82), m(-0.33, 0.80), m(-0.36, 1.02), m(-0.23, 1.46), m(-0.19, 1.02), m(-0.17, 0.92)], fill=c)
        d.ellipse([x - 0.105 * S, floor - 1.80 * S, x + 0.105 * S, floor - 1.575 * S], fill=c)             # tête
        d.pieslice([x - 0.125 * S, floor - 1.815 * S, x + 0.125 * S, floor - 1.60 * S], 180, 360, fill=col("muted"))   # casque
        d.line([x + 0.48 * S, floor, x + 0.48 * S, floor - 1.80 * S], fill=col("muted"), width=2)
        for yy in (0, 1.80):
            d.line([x + 0.42 * S, floor - yy * S, x + 0.54 * S, floor - yy * S], fill=col("muted"), width=2)
        d.text((x + 0.58 * S, floor - 1.0 * S), "1,80 m", font=fS, fill=col("muted"))

    def screen(x, floor, w, z0, h, zones=None, grid=None, strip=False):
        """Face de l'écran : coin bas gauche à (x, z0 au-dessus du sol). zones : [(fx, fy, fw, fh, index)] en fractions.
        grid : (colonnes, rangées) de dalles (jointures)."""
        x0, y1 = x, floor - z0 * S
        x1, y0 = x + w * S, y1 - h * S
        d.rectangle([x0 - 3, y0 - 3, x1 + 3, y1 + 3], fill=col("lineStrong"))
        d.rectangle([x0, y0, x1, y1], fill=(6, 16, 12) if strip else col("bg2"))
        if grid:
            for c in range(1, grid[0]):
                d.line([x0 + (x1 - x0) * c / grid[0], y0, x0 + (x1 - x0) * c / grid[0], y1], fill=col("bg0"), width=2)
            for r in range(1, grid[1]):
                d.line([x0, y0 + (y1 - y0) * r / grid[1], x1, y0 + (y1 - y0) * r / grid[1]], fill=col("bg0"), width=2)
        for fx, fy, fw, fh, i in (zones or []):
            zx0, zy0 = x0 + fx * (x1 - x0), y0 + fy * (y1 - y0)
            zx1, zy1 = zx0 + fw * (x1 - x0), zy0 + fh * (y1 - y0)
            d.rectangle([zx0 + 2, zy0 + 2, zx1 - 2, zy1 - 2], outline=col("accent"), width=3)
            if i is not None:
                t = str(i)
                tw = fI.getlength(t)
                d.text(((zx0 + zx1 - tw) / 2, (zy0 + zy1) / 2 - 17), t, font=fI, fill=col("accent"))
        if strip:
            t = "TEXTE DÉFILANT" if w > 2 else "12:35"
            f = font(FONTS["monoBold"], h * S * 0.8)
            d.text((x0 + (x1 - x0 - f.getlength(t)) / 2, (y0 + y1) / 2 - h * S * 0.36), t, font=f, fill=col("accent"))
        return x0, y0, x1, y1

    def label(x, floor, name, dims):
        d.text((x, floor + 12), name, font=fN, fill=col("text"))
        d.text((x, floor + 52), dims, font=fS, fill=col("text2"))
        return max(fN.getlength(name), fS.getlength(dims))

    def poles(xs, floor, top, bottom):
        for px in xs:
            d.rectangle([px - 3, floor - top * S, px + 3, floor - bottom * S], fill=col("lineStrong"))
            d.rectangle([px - 12, floor - top * S, px + 12, floor - top * S + 4], fill=col("lineStrong"))

    def feet(xs, floor, top):
        for px in xs:
            d.rectangle([px - 4, floor - top * S, px + 4, floor], fill=col("lineStrong"))
            d.rectangle([px - 22, floor - 6, px + 22, floor], fill=col("lineStrong"))

    def row(i, title):
        floor = 150 + i * ROW + 400
        d.line([40, floor, W - 40, floor], fill=col("line"), width=3)
        soldier(110, floor)
        return floor, 330

    # ---- Rangée 1 : écrans suspendus (plafond à 3,40 m) ----
    floor, x = row(0, "")
    d.line([300, floor - CEIL * S, W - 300, floor - CEIL * S], fill=col("lineStrong"), width=3)
    d.text((W - 285, floor - CEIL * S - 18), "plafond à 3,40 m", font=fS, fill=col("muted"))
    top65 = CEIL - 0.72
    for name, n, wtot, dims in (("65\" suspendu", 1, 1.459, "1,44 x 0,81 m"), ("Double 65\" suspendu", 2, 2.926, "2 x 65\" · 2,93 m"),
                                ("Triple 65\" suspendu", 3, 4.389, "3 x 65\" · 4,39 m (aussi en arc, 15°)")):
        for k in range(n):
            screen(x + k * 1.463 * S + 1, floor, 1.439, top65 - 0.81, 0.81, zones=[(0, 0, 1, 1, k)] if n > 1 else None)
        poles([x + wtot * S * 0.3, x + wtot * S * 0.7], floor, CEIL, top65)
        if n > 1:
            d.rectangle([x, floor - (top65 + 0.09) * S, x + wtot * S, floor - (top65 + 0.03) * S], fill=col("lineStrong"))
        x += max(wtot * S, label(x, floor, name, dims)) + 90
    screen(x, floor, 2.1695, CEIL - 0.72 - 1.22, 1.22)
    poles([x + 0.6 * S, x + 1.57 * S], floor, CEIL, CEIL - 0.72)
    label(x, floor, "98\" suspendu", "2,17 x 1,22 m")
    x += 2.17 * S + 90
    for name, w in (("Bandeau LED texte 2,4 m suspendu", 2.40), ("Bandeau LED texte 4,8 m suspendu", 4.80)):
        screen(x, floor, w, CEIL - 0.47 - 0.30, 0.30, strip=True)
        poles([x + w * S * 0.14, x + w * S * 0.86], floor, CEIL, CEIL - 0.47)
        x += max(w * S, label(x, floor, name, "%s x 0,30 m (%d:1)" % (("%.2f" % w).replace(".", ","), round(w / 0.3)))) + 90

    # ---- Rangée 2 : écrans géants ----
    floor, x = row(1, "")
    for name, w, h, z0, dims, kind in (("98\" mural", 2.1695, 1.2203, 0.95, "2,17 x 1,22 m", ""), ("98\" sur pied roulant", 2.1695, 1.2203, 0.86, "2,17 x 1,22 m", "feet2"),
                                       ("136\" mural", 3.011, 1.6937, 0.75, "3,01 x 1,69 m", ""), ("136\" sur pied lourd", 3.011, 1.6937, 0.70, "3,01 x 1,69 m", "feet2"),
                                       ("Mur LED 220\" mural", 4.8704, 2.7396, 0.40, "4,87 x 2,74 m", ""), ("Mur LED 220\" sur treillis", 4.8704, 2.7396, 0.50, "4,87 x 2,74 m", "feet3")):
        if kind == "feet2":
            feet([x + w * S * 0.2, x + w * S * 0.8], floor, z0 + 0.3)
        if kind == "feet3":
            feet([x + w * S * 0.125, x + w * S * 0.5, x + w * S * 0.875], floor, z0 + 0.3)
        screen(x, floor, w, z0, h)
        x += max(w * S, label(x, floor, name, dims)) + 90

    # ---- Rangée 3 : murs d'images de dalles 55" ----
    floor, x = row(2, "")
    walls = [("Mur 2x2", 2, 2, 0.90, [(0, 0, 1, 1, 0)], "2,43 x 1,37 m · 1 image ou 4 dalles"),
             ("Mur 3x2", 3, 2, 0.90, [(0, 0, 2 / 3, 1, 0), (2 / 3, 0, 1 / 3, 0.5, 1), (2 / 3, 0.5, 1 / 3, 0.5, 2)], "3,65 x 1,37 m · bloc 2x2 + 2, ou 6 dalles"),
             ("Mur 4x3", 4, 3, 0.90, [(0, 0, 0.75, 1, 0)] + [(0.75, r / 3, 0.25, 1 / 3, 1 + r) for r in range(3)], "4,87 x 2,06 m · bloc 3x3 + 3, ou 12 dalles"),
             ("Mur 5x3", 5, 3, 0.90, [(0.2, 0, 0.6, 1, 0)] + [(0, r / 3, 0.2, 1 / 3, 1 + r) for r in range(3)] + [(0.8, r / 3, 0.2, 1 / 3, 4 + r) for r in range(3)],
              "6,08 x 2,06 m · bloc 3x3 + 6, ou 15 dalles"),
             ("Mur 6x4", 6, 4, 0.60, [(1 / 6, 0, 4 / 6, 1, 0)] + [(0, r / 4, 1 / 6, 0.25, 1 + r) for r in range(4)] + [(5 / 6, r / 4, 1 / 6, 0.25, 5 + r) for r in range(4)],
              "7,30 x 2,75 m · bloc 4x4 + 8, ou 6 zones 2x2")]
    for name, c, r, z0, zones, dims in walls:
        screen(x, floor, c * T55[0], z0, r * T55[1], zones=zones, grid=(c, r))
        x += max(c * T55[0] * S, label(x, floor, name, dims)) + 80

    # ---- Rangée 4 : bandeaux muraux, grand écran incurvé (déroulé, puis vu de dessus) ----
    floor, x = row(3, "")
    for name, w, h, dims in (("Plaque de nom", 0.86, 0.215, "0,86 x 0,22 m"), ("Bandeau horloge", 1.12, 0.28, "1,12 x 0,28 m (4:1)"),
                             ("Bandeau LED texte 2,4 m", 2.40, 0.30, "2,40 x 0,30 m (8:1)"), ("Bandeau LED texte 4,8 m", 4.80, 0.30, "4,80 x 0,30 m (16:1)")):
        screen(x, floor, w, 2.55 - h / 2, h, strip=name != "Plaque de nom")
        x += max(w * S, label(x, floor, name, dims)) + 70
    feet([x + 7.2 * S * k / 12 for k in (1, 3, 5, 7, 9, 11)], floor, 1.0)
    screen(x, floor, 7.20, 0.75, 2.025, zones=[(0.25, 0, 0.5, 1, 0), (0, 0, 0.25, 1, 1), (0.75, 0, 0.25, 1, 2)])
    label(x, floor, "Grand écran incurvé (déroulé)", "arc 7,20 x 2,03 m (32:9) · carte + 2 colonnes, ou 2 images 16:9")
    x += 7.2 * S + 130
    # Vue de dessus : arc de rayon 7 m, soldat à 4 m.
    cx, top = x + 3.6 * S, floor - 3.55 * S
    R, T = 7.0, 7.2 / 7.0 / 2
    pts = [(cx + R * S * math.sin(-T + 2 * T * k / 40), top + (R - R * math.cos(-T + 2 * T * k / 40)) * S) for k in range(41)]
    d.line(pts, fill=col("accent"), width=9, joint="curve")
    d.line([(pts[0][0], pts[0][1] + 28), (pts[-1][0], pts[-1][1] + 28)], fill=col("muted"), width=2)
    d.text((cx - fS.getlength("corde 6,92 m") / 2, pts[0][1] + 34), "corde 6,92 m", font=fS, fill=col("muted"))
    d.line([(cx, top - 6), (cx, pts[0][1])], fill=col("muted"), width=2)
    d.text((cx + 12, top + 28), "flèche 0,91 m", font=fS, fill=col("muted"))
    sy = top + 2.7 * S
    d.ellipse([cx - 26, sy - 14, cx + 26, sy + 14], fill=col("lineStrong"))
    d.ellipse([cx - 11, sy - 11, cx + 11, sy + 11], fill=col("muted"))
    d.text((cx + 40, sy - 18), "soldat à 2,70 m du fond", font=fS, fill=col("muted"))
    label(x, floor, "Grand écran incurvé (vu de dessus)", "rayon 7 m · 59° · sur pieds ou mural")

    # ---- Rangée 5 : bureau (plateau à 0,80 m), postes PC, trépieds, téléviseurs, toiles de projection ----
    floor, x = row(4, "")

    def desk(x0, w):
        d.rectangle([x0 - 0.15 * S, floor - 0.80 * S, x0 + (w + 0.15) * S, floor - 0.76 * S], fill=col("lineStrong"))
        feet([x0 - 0.05 * S, x0 + (w + 0.05) * S], floor, 0.76)

    M27 = (0.598, 0.336)
    for name, n, dims in (("Moniteur 27\" / poste PC 1 écran", 1, "0,60 x 0,34 m"), ("Double 27\" / poste PC 2 écrans", 2, "2 x 27\" · 1,23 m"),
                          ("Triple 27\" / poste PC 3 écrans", 3, "3 x 27\" en arc · 1,77 m")):
        wtot = n * (M27[0] + 0.015)
        desk(x, wtot)
        for k in range(n):
            screen(x + k * (M27[0] + 0.015) * S, floor, M27[0], 0.80 + 0.13, M27[1], zones=[(0, 0, 1, 1, k)] if n > 1 else None)
        x += max(wtot * S, label(x, floor, name, dims)) + 70
    desk(x, 1.196)
    screen(x, floor, 1.196, 0.93, 0.336, zones=[(0, 0, 0.5, 1, 0), (0.5, 0, 0.5, 1, 1)])
    x += max(1.196 * S, label(x, floor, "Poste PC écran incurvé 49\"", "1,20 x 0,34 m (32:9) · 2 images 16:9")) + 70
    feet([x + 0.72 * S], floor, 1.13)
    screen(x, floor, 1.439, 1.13, 0.809)
    x += max(1.439 * S, label(x, floor, "65\" sur trépied", "1,44 x 0,81 m")) + 70
    feet([x + 0.98 * S], floor, 1.34)
    for k in range(2):
        screen(x + k * 0.98 * S, floor, 0.952, 1.34, 0.536)
    x += max(1.96 * S, label(x, floor, "Double 43\" sur trépied", "2 x 0,95 x 0,54 m")) + 70
    for name, w, h, dims in (("Téléviseur 55\" mural", 1.210, 0.680, "1,21 x 0,68 m"), ("Téléviseur 75\" mural", 1.660, 0.934, "1,66 x 0,93 m")):
        screen(x, floor, w, 1.20, h)
        x += max(w * S, label(x, floor, name, dims)) + 70
    feet([x + 0.885 * S], floor, 1.10)
    screen(x, floor, 1.771, 1.10, 0.996)
    x += max(1.771 * S, label(x, floor, "Toile 80\" sur trépied", "1,77 x 1,00 m · projection")) + 70
    screen(x, floor, 2.214, 1.00, 1.245)
    label(x, floor, "Toile 100\" murale", "2,21 x 1,25 m · projection")
    save(img, "tailles_ecrans")
    return img


def main():
    wall("mur_split", "TOC · BRAVO", "carte et flux", draw_split)
    wall("mur_split_flash", "TOC · BRAVO", "carte et flux", lambda cv: draw_split(cv, dy=52), flash="CONTACT NORD, TOUS AUX ABRIS · Cne Martin · 12:35Z")
    wall("mur_pip", "TOC · BRAVO-6", "PIP du poste · Adj Mercier · onglet GROUPES", lambda cv: draw_split(cv, pip=True))
    wall("mur_carte", "TOC · BRAVO", "carte seule", draw_map)
    wall("mur_flux", "FLUX · BRAVO", "flux seul", draw_feed)
    wall("mur_reseau", "RÉSEAU · BRAVO", "réseau", draw_net)
    wall("mur_orbat", "ORBAT · BRAVO", "ORBAT du site", draw_orbat)
    wall("mur_escouade", "ESCOUADE · ALPHA 1", "écran d'escouade", draw_squad)
    wall("mur_escouade_camera", "ESCOUADE · ALPHA 1", "écran d'escouade", lambda cv: draw_squad(cv, cam=True))
    wall("mur_mosaique", "CAMÉRAS · MUR CENTRE", "mosaïque de caméras", draw_mosaic)
    wall("mur_mosaique_2x2", "CAMÉRAS · MUR CENTRE", "mosaïque de caméras", lambda cv: draw_mosaic(cv, 2))
    wall("mur_horloge", "HORLOGE · BRAVO", "horloge et chronos", draw_clock)
    wall("mur_main_courante", "MAIN COURANTE · BRAVO", "main courante", draw_log)
    wall("mur_excheck", "EXÉCUTION · BRAVO", "liste d'exécution", draw_excheck)
    wall("mur_pertes", "PERTES ET RÉSULTATS · BRAVO", "pertes et résultats", draw_kills)
    wall("mur_demandes", "DEMANDES · BRAVO", "demandes des éléments", draw_req)
    cv = Canvas(1600, 900)
    draw_sat_wall(cv)
    save(cv.img, "mur_sat")
    cv = Canvas(2048, 512, 2048 / 1600, 512 / 400)
    strip(cv)
    save(cv.img, "bandeau_horloge")
    cv = Canvas(2048, 512, 2048 / 1600, 512 / 400)
    plate(cv)
    save(cv.img, "plaque_nom")
    text_mockups()
    # Console du poste (refonte 4.9.0) : chaque onglet, puis les quatre rubriques dans app_poste/ (maquettes, pas des captures du jeu).
    for tab, n in [("CARTE", "pc_carte"), ("GROUPES", "pc_groupes"), ("ORBAT", "pc_orbat"), ("RÉSEAU", "pc_reseau"), ("FLUX", "pc_flux"), ("ORDRES", "pc_ordres"), ("CAMP", "pc_camp"),
                   ("DEMANDES", "pc_demandes"), ("CHRONOS", "pc_chronos"), ("EXCHECK", "pc_excheck"), ("MAIN COURANTE", "pc_main_courante"), ("PERTES", "pc_pertes"),
                   ("PLAN", "pc_plan"), ("SALLE", "pc_salle"), ("MUR", "pc_mur"), ("RADIO", "pc_radio"), ("SAT", "pc_sat")]:
        pc(tab, n)
    pc("DESSIN", "pc_carte_dessin", tool="PLAN")
    pc("CARTE", "pc_groupes_4x3", aspect=4 / 3, H=1080)
    pc("CARTE", "pc_groupes_21x9", aspect=21 / 9, H=1080)
    pc("CARTE", "app_poste/situation")
    pc("MAIN COURANTE", "app_poste/conduite")
    pc("DESSIN", "app_poste/plans", tool="PLAN")
    pc("MUR", "app_poste/salle")
    pc("SALLE", "app_poste/salle_ecran")
    pc("DEMANDES", "app_poste/conduite_demandes")
    pc("CARTE", "app_poste/situation_1440p", H=1440)
    tailles_ecrans()
    contact_sheet()
    print("\n".join(str(p) for p in SAVED))


if __name__ == "__main__":
    main()
