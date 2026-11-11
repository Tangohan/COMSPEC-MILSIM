#!/usr/bin/env python3
"""Maquettes PNG des écrans TOC et du PC du poste (refonte du rendu, demande de TTRD du 11/11).

Les nombres ne sont PAS recopiés ici : couleurs, tailles de texte, polices, régions et colonnes viennent des tables JSON
de functions/toc/fn_tocTheme.sqf (écrans muraux) ; grille, mise en page et boutons du PC viennent des tables _grid,
_layout et _btnDefs de functions/toc/fn_tocLaptop.sqf, posées par le même calcul que _place. Seules les données
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
def frame(cv, title, station, view_label, flash=None, link=("accent", "LIAISON SERVEUR · DONNÉES À 2 s"), clock=("12:35:42", "13:35")):
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
    L = dict(WALLS["SPLIT"])
    if dy:
        for k, (x, y, w, h) in list(L.items()):
            if not k.startswith("flux"):
                L[k] = (x, y + dy, w, h - (dy if k in ("map", "divider", "grpArea") else 0))
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
    cv.text(*L["plate"], "ALPHA 1", "title", "text", px=56)
    cv.text(*L["plateSub"], "4 membres · 3 en direct · chef Sgt Dubois", "body", "text2")
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
        cv.text(x, y + h / 2 - 8, w, 36, "liaison coupée · dernière image 12:31Z", "caption", "muted", "C")
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
    cv.text(*L["lDate"], "mardi 14 juin 2035 · lever du soleil 05:21", "body", "text2")
    section(cv, L["nxHead"], L["nxSub"], L["nxRule"], "PROCHAINE LIGNE", "liste d'exécution")
    cv.text(*L["nxCode"], "4 · ROMEO", "title", "accent")
    cv.text(*L["nxEv"], "Franchissement de la ligne de départ · ALPHA", "body", "text")
    cv.text(*L["nxAt"], "prévu 12:45Z · dans 09:18", "data", "text2")
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
    kpi4(cv, L["kpi"], [(3, "amis blessés", "amber"), (1, "ami tué", "red"), (11, "ennemis neutralisés", "accent"), (4, "ennemis blessés / capturés", "text2")])
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
    kpi4(cv, L["kpi"], [(2, "reçues", "amber"), (1, "prise en compte", "blue"), (3, "terminées", "accent"), (1, "refusée", "red")])
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
    T(L["lSub"], "mardi 14 juin", "stripSub", "text2")
    cv.rect(*L["div2"], rgb("line"))
    T(L["cLbl"], "H-HOUR", "stripLabel", "text")
    T(L["cDig"], "H+00:35:42", "stripChrono", "red")
    T(L["cSub"], "heure H 12:00Z · dépassée", "stripSub", "muted")


def plate(cv):
    L = WALLS["PLATE"]
    cv.rect(*L["bar"], rgb("accent"))
    cv.text(*L["name"], "ALPHA 1", "plateName", "text")
    x, y, w, h = L["sub"]
    cv.rect(x + 4, y + h / 2 - 14, 28, 28, rgb("accent"))
    cv.text(x + 44, y, w - 44, h, "4 nœuds ATAK · 3 en direct · chef Sgt Dubois", "plateSub", "text2")


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


def pc_regions(aspect):
    """Même calcul que « load » (fn_tocLaptop), en hauteurs d'écran."""
    g = GRID
    gap = g["gap"]
    x0, y0, w0, h0 = 0.025 * aspect, 0.022, 0.95 * aspect, 0.956
    xi, yi, wi, hi = x0 + g["pad"], y0 + g["pad"], w0 - 2 * g["pad"], h0 - 2 * g["pad"] - g["brand"]
    tabsH = 2 * g["tab"] + gap
    topH = g["title"] + tabsH + 2 * gap
    by = yi + topH + 2 * gap
    bh = hi - topH - g["bar"] - 3 * gap
    pw = min(max(wi * g["panel"], g["panelMin"]), g["panelMax"])
    px = xi + wi - pw
    cx, cw = px + 2 * gap, pw - 4 * gap
    cy = by + gap
    return {
        "shell": (x0, y0, w0, h0), "inner": (xi, yi, wi, hi), "top": (xi, yi, wi, topH), "panel": (px, by, pw, bh),
        "rTitle": (xi + 2 * gap, yi + 0.001, wi - 4 * gap, g["title"] - 0.005),
        "rTabs": (xi + 2 * gap, yi + g["title"] + gap, wi - 4 * gap, tabsH),
        "rBar": (xi, yi + hi - g["bar"], wi, g["bar"]),
        "rLeft": (xi + gap, by, (px - gap) - (xi + gap), bh),
        "rHelp": (cx, by + bh - gap - g["help"], cw, g["help"]),
        "rRight": (cx, cy, cw, (by + bh - gap - g["help"] - 2 * gap) - cy),
    }


SAMPLE = {
    "gList": [("ALPHA 1-1 · 4 joueur(s) · 4 nœud(s) ATAK", "accent"), ("ALPHA 1-2 · 4 joueur(s) · 4 nœud(s) ATAK", "accent"), ("BRAVO 2 · 3 joueur(s) · 3 nœud(s) ATAK", "amber"),
              ("CHARLIE 3 · 5 joueur(s) · 5 nœud(s) ATAK", "accent"), ("DELTA 4 · 4 IA · 4 nœud(s) ATAK", "lost"), ("ECHO 5 · 2 joueur(s) · 2 nœud(s) ATAK", "accent"), ("VIPER 1 · 1 joueur(s) · 1 nœud(s) ATAK", "accent")],
    "fList": [(f"{t}  {k}{'' if p == 'ROUTINE' else ' ' + p} · {w}{' · ' + g if g else ''}", PRIO_TOK[p] if p != "ROUTINE" else "text") for t, k, p, w, g, x in EVENTS[:12]],
    "bList": [("1re SECTION (1SEC)", "blue"), ("   Chef : Lt Moreau", "accent"), ("   Adjoint : Sgt Dubois", "accent"), ("   Cpl Lefèvre", "text2"), ("   Sdt Garnier", "accent"),
              ("2e SECTION (2SEC)", "blue"), ("   Chef : Sgt Bernard", "accent"), ("   Cpl Petit", "text2"), ("   poste vacant", "muted")],
    "tList": [("H-HOUR", "text"), ("ALPHA EN POSITION", "text"), ("RELÈVE BRAVO", "text"), ("MEDEVAC CHARLIE", "text")],
    "lList": [(f"{t}Z  {k}  {x}", {"FLASH": "red", "CR": "amber", "DEMANDE": "amber", "EXCHECK": "accent", "POSTE": "blue"}.get(k, "text")) for t, k, x in
              [("12:35:02", "FLASH", "CONTACT NORD, TOUS AUX ABRIS"), ("12:34:10", "CR", "TIC ALPHA 1-1 grille 042 116"), ("12:31:44", "DEMANDE", "MEDEVAC CHARLIE 3"),
               ("12:30:00", "EXCHECK", "3 · TANGO passé"), ("12:27:15", "CR", "SITREP BRAVO 2 : en position"), ("12:20:02", "POSTE", "Poste pris par Adj Mercier"),
               ("12:18:40", "MAIN", "BRAVO signale le pont nord détruit"), ("12:11:09", "CR", "CONTACT DELTA 4")]],
    "xList": [("1. QUEBEC · Départ de la base · TOUS", "accent"), ("2. SIERRA · H-HOUR · TOUS", "accent"), ("3. TANGO · Ligne de départ · ALPHA", "accent"),
              ("4. ROMEO · Franchissement LD · ALPHA", "text"), ("5. UNIFORM · Appui feu en place · VIPER", "text"), ("6. VICTOR · Assaut RAVEN · ALPHA", "text")],
    "qList": [("12:31Z  MEDEVAC · CHARLIE 3 · 048 117", "amber"), ("12:29Z  APPUI FEU · ALPHA 1-1 · 044 118", "blue"), ("12:20Z  RAVITAILLEMENT · BRAVO 2", "amber"),
              ("12:02Z  APPUI FEU · DELTA 4 · 039 111", "accent"), ("11:30Z  APPUI AÉRIEN · ALPHA 1-2", "red")],
}
RIGHT = {"tList": ["H+00:35:42", "-00:04:12", "-00:20:00", "+00:03:51"], "xList": ["passé 11:16", "passé 12:00", "passé 12:30", "prévu 12:45", "prévu 12:50", "prévu 13:05"],
         "qList": ["REÇUE", "PRISE EN COMPTE", "REÇUE", "TERMINÉE", "REFUSÉE"], "bList": ["", "en jeu", "en jeu", "hors ligne", "en jeu", "", "en jeu", "hors ligne", "poste vacant"]}
INFO = {
    "gInfo": [("ALPHA 1-1", "heading"), ("Chef Sgt Dubois · n° 6", "body"), ("4 vivant(s) dont 4 joueur(s) · 4 nœud(s) ATAK", "body"), ("Grille 042 116 · en direct", "body")],
    "fInfo": [("TIC  12:34 · ALPHA 1-1 · grille 042 116", "heading"), ("Contact NE 300 m, 2 PAX armés, demande appui mortier", "body")],
    "bInfo": [("Sgt Dubois", "heading"), ("Adjoint · 1re SECTION", "body"), ("En jeu", "accent")],
    "oInfo": [("ORDRES ÉMIS  2", "heading"), ("12:18 TENIR → ALPHA 1-1 · IMPORTANT", "body"), ("   vu 4 · reçu 4 · en cours 1 · terminé 0", "muted"), ("12:02 DÉPLACER → BRAVO 2 · ROUTINE", "body"), ("   vu 3 · reçu 3 · en cours 0 · terminé 3", "muted")],
    "cInfo": [("MESSAGES DU POSTE", "heading"), ("11:51 ROUTINE · Cne Martin", "body"), ("   Fréquence de repli 47.300", "muted")],
    "rInfo": [("18 en ligne · 3 anciens · 1 perdu · 2 hors ligne", "body"), ("14 joueurs · 6 IA · 2 relais · 0 brouilleur", "body"), ("", "body"), ("MÉTÉO", "heading"), ("Nuages 30 % · pluie 0 % · brouillard 5 %", "body"), ("Vent 4 m/s du 270°", "body")],
    "tInfo": [("Chrono : temps écoulé depuis le lancement. Compte à rebours : temps restant,", "muted"), ("rouge une fois dépassé. Heure H : temps jusqu'à une heure ZULU.", "muted")],
    "kInfo": [("AN/PRC-152  canal 3 · ALPHA NET", "heading"), ("● RX   ● TX   47.300 MHz · alternat 1 · radio active", "body"), ("", "body"), ("AN/PRC-117F  canal 1 · CMD NET", "heading"), ("● RX   ● TX   32.500 MHz · alternat 2", "body"), ("", "body"), ("EN ÉMISSION", "heading"), ("● ALPHA 1-1 · 47.300 MHz · 4 s", "body")],
    "lInfo": [("Heures en ZULU. S'inscrivent seuls : comptes rendus, demandes, prises de poste, FLASH.", "muted")],
    "xInfo": [("PASSÉ inscrit l'heure ZULU réelle et une entrée à la main courante.", "muted")],
    "kcInfo": [("ÉLÉMENT                 AMIS BL. · TUÉS    ENN. NEUTR. · BL.", "muted"), ("ALPHA 1-1                    1 · 0              4 · 1", "body"), ("BRAVO 2                        1 · 1              1 · 2", "body"), ("VIPER 1                        0 · 0              4 · 1", "body")],
    "qInfo": [("MEDEVAC  12:31Z · CHARLIE 3 · grille 048 117 · REÇUE", "heading"), ("1 urgence A, HLZ sud, fumée verte", "body")],
    "hInfo": [("SALLE TOC BRAVO · ce PC est le poste maître", "heading"), ("6 écrans liés · 2 dalles de mur", "muted")],
    "hSel": [("MUR 3x2 · dalle 2 · carte et flux", "heading"), ("allumé · lié à la salle", "muted")],
}
PANEL_HEAD = {"GROUPES": ("GROUPES", "9 groupes · 18 en ligne"), "ORBAT": ("ORBAT DU SITE", "31 membres · 19 en jeu"), "FLUX": ("FLUX", "15 entrées · 3 alertes"),
              "ORDRES": ("ORDRES", "2 émis"), "CAMP": ("CAMP", "messages et caméras"), "RÉSEAU": ("RÉSEAU", "en direct"), "CHRONOS": ("CHRONOS", "4 chronos"),
              "RADIO": ("RADIO", "ACRE2 · 2 radios"), "SALLE": ("SALLE", "6 écrans"), "MAIN COURANTE": ("MAIN COURANTE", "38 entrées"), "EXCHECK": ("EXCHECK", "3 / 9 passés"),
              "PERTES": ("PERTES ET RÉSULTATS", "5 éléments"), "DEMANDES": ("DEMANDES", "2 reçues")}


def pc(tab, out, aspect=16 / 9, H=1080, tool="SELECT"):
    W = round(H * aspect)
    cv = Canvas(W, H)
    cv.img = Image.new("RGBA", (W, H), (20, 22, 24, 255))
    u = H
    R = lambda r: (r[0] * u, r[1] * u, r[2] * u, r[3] * u)
    reg = pc_regions(aspect)
    g = GRID
    fpx = lambda k: g[k] * u
    cv.rect(*R(reg["shell"]), (22, 23, 24, 255))
    x0, y0, w0, h0 = reg["shell"]
    cv.rect(*R((x0 + g["pad"] * 0.4, y0 + g["pad"] * 0.4, w0 - g["pad"] * 0.8, h0 - g["pad"] * 0.8)), (31, 32, 33, 255))
    cv.rect(*R(reg["inner"]), rgb("bg0"))
    xi, yi, wi, hi = reg["inner"]
    cv.text(*R((x0, yi + hi + 0.001, w0, g["brand"])), "COMSPEC · POSTE DE COMMANDEMENT", None, "muted", "C", px=fpx("fontMin"), fname=FONTS["sans"])
    cv.rect(*R(reg["top"]), rgb("bg1"))
    tx, ty, tw, th = reg["top"]
    cv.rect(*R((tx, ty + th, tw, g["hair"])), rgb("line"))
    cv.rect(*R(reg["panel"]), rgb("bg1"))
    left = "L:SAT" if tab == "SAT" else ("L:SALLE" if tab == "SALLE" else ("L:MAP:" + tool if tool in ("LINE", "DRAW", "PLAN", "MARKER") else "L:MAP:OTHER"))
    zones = [("F:TITLE", "rTitle"), ("F:TABS", "rTabs"), ("F:HELP", "rHelp"), ("F:BAR", "rBar"), (left, "rLeft"), ("R:" + tab, "rRight")]
    rects = {}
    for lk, rk in zones:
        rects.update(pc_place(LAYOUT[lk], *reg[rk]))
    body = fpx("font")
    sb = 0.024 * u  # base du texte structuré du PC (COMSPEC_RscStructuredText)
    for key, r in rects.items():
        x, y, w, h = R(r)
        kind = KIND.get(key, "stx")
        if key in BTNS:
            b = BTNS[key]
            if b[1] == "T":
                on = key == "tab" + tab
                cv.rect(x, y, w, h, rgb("bg2" if on else "bg1"))
                if on:
                    cv.rect(x, y + h - 0.003 * u, w, 0.003 * u, rgb("accent"))
                lbl = b[2]
                ft = fpx("fontTab")
                while text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and ft > fpx("fontMin"):
                    ft -= 0.0005 * u
                if text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and b[3]:
                    lbl = b[3]
                cv.text(x, y, w, h, lbl, None, "accent" if on else "text2", "C", px=ft, fname=FONTS["sansBold"], fit=False)
            else:
                prim = b[5] == 1
                dis = tab == "SAT" and False
                cv.rect(x, y, w, h, rgb("accent" if prim else "bg2"))
                if not prim:
                    cv.rect(x, y, w, max(1, 0.001 * u), rgb("line"))
                lbl = b[2]
                ft = body
                while text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and ft > fpx("fontMin"):
                    ft -= 0.0005 * u
                if text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and b[3]:
                    lbl, ft = b[3], body
                    while text_w(lbl, FONTS["sansBold"], ft) > 0.92 * w and ft > fpx("fontMin"):
                        ft -= 0.0005 * u
                cv.text(x, y, w, h, lbl, None, "bg0" if prim else "text", "C", px=ft, fname=FONTS["sansBold"], fit=False)
            continue
        if isinstance(kind, tuple):
            cv.text(x, y, w, h, kind[1], None, "muted", "L", px=0.017 * u, fname=FONTS["sansBold"])
            continue
        if key == "map":
            img = terrain(int(w), int(h), 9)
            cv.img.paste(img, (int(x), int(y)))
            map_view_small = Canvas(1, 1)
            for i, (nx, ny, cs, st, k) in enumerate(NODES):
                X, Y = x + nx / 1040 * w, y + ny / 774 * h
                nato(cv, X, Y, "friend" if st != "lost" else "lost", 1.0 if st == "live" else 0.6, k)
                cv.text(X + 22, Y - 14, 200, 28, cs, None, "text", "L", px=0.019 * u, fname=FONTS["sansBold"], fit=False)
            continue
        if key in ("pHead",):
            title, stat = PANEL_HEAD.get(tab, (tab, ""))
            cv.text(x, y, w, h, title, None, "text", "L", px=0.95 * sb, fname=FONTS["sansBold"])
            cv.text(x, y, w, h, stat, None, "muted", "R", px=0.8 * sb, fname=FONTS["sans"])
            continue
        if key == "pRule":
            cv.rect(x, y + h / 2 - 0.0005 * u, w, max(1, 0.001 * u), rgb("line"))
            cv.rect(x, y + h / 2 - 0.001 * u, 0.04 * u, 0.002 * u, rgb("accent"))
            continue
        if key == "title":
            cv.text(x, y, w, h * 0.55, "POSTE TOC · BRAVO-6", None, "text", "L", px=1.05 * sb, fname=FONTS["sansBold"])
            cv.text(x, y + h * 0.5, w, h * 0.45, "BLUFOR · poste pris par Adj Mercier à 12:20Z", None, "accent", "L", px=0.8 * sb, fname=FONTS["sans"])
            continue
        if key == "clock":
            cv.text(x, y, w, h * 0.55, "12:35:42 ZULU   13:35:42 LOCAL", None, "text", "R", px=0.9 * sb, fname=FONTS["monoBold"], fit=False)
            cv.text(x, y + h * 0.5, w, h * 0.45, "■ ACRE2 · ● EN DIRECT", None, "accent", "R", px=0.75 * sb, fname=FONTS["sans"])
            continue
        if key == "bar":
            cv.rect(x, y, w, h, rgb("bg1"))
            cv.rect(x, y, w, max(1, 0.001 * u), rgb("line"))
            s = "   RÉSEAU   18 en ligne · 3 anciens · 1 perdu · 2 hors ligne      14 joueurs · 6 IA · 2 relais · 0 brouilleur      3 alerte(s) sur 20 min"
            cv.text(x, y, w, h, s, None, "text2", "L", px=0.8 * sb, fname=FONTS["sans"])
            continue
        if key == "help":
            cv.rect(x, y, w, h, rgb("bg0"))
            cv.text(x + 0.004 * u, y, w, h, "Choisissez un groupe : la carte se centre sur lui. Clic droit : point de l'ORDRE IA.", None, "muted", "L", px=0.8 * sb, fname=FONTS["sans"])
            continue
        if kind in ("COMSPEC_RscTocList",):
            cv.rect(x, y, w, h, rgb("bg0"))
            rh = 0.027 * u
            for i, (s, tok) in enumerate(SAMPLE.get(key, [])):
                if (i + 1) * rh > h:
                    break
                if i == 1:
                    cv.rect(x, y + i * rh, w, rh, rgb("accentDim"))
                cv.text(x, y + i * rh, w * 0.72, rh, s, None, tok, "L", px=0.021 * u, fname=FONTS["sans"])
                rs = RIGHT.get(key, [])
                if i < len(rs) and rs[i]:
                    cv.text(x + w * 0.6, y + i * rh, w * 0.4, rh, rs[i], None, "text2", "R", px=0.021 * u, fname=FONTS["mono"] if key == "tList" else FONTS["sans"])
            continue
        if kind in ("combo", "COMSPEC_RscTocCombo"):
            cv.rect(x, y, w, h, rgb("bg0"))
            cv.rect(x, y + h - max(1, 0.001 * u), w, max(1, 0.001 * u), rgb("line"))
            sample = {"oKind": "DÉPLACER", "oPrio": "ROUTINE", "oTarget": "Groupe ALPHA 1-1", "cDest": "Tout le camp", "cPrio": "ROUTINE", "fFilter": "Tout le flux",
                      "gAi": "Aller au point", "rShow": "Carte et flux", "cCam": "Carte et flux du poste", "tKind": "Compte à rebours", "kcEl": "ALPHA 1-1",
                      "kcKind": "Ami blessé", "hRad": "20 m", "hWhat": "Carte et flux", "hMosRot": "Rotation 10 s", "hMosCase": "Case 1", "hMosSrc": "ALPHA 1-1",
                      "hScene": "OPÉRATION", "dColor": "Rouge", "dWidth": "Moyen", "dKind": "Zone", "mAff": "Ami", "mType": "Objectif", "mColor": "Bleu", "mTtl": "Permanent", "mChan": "Camp"}.get(key, "")
            cv.text(x, y, w - 0.02 * u, h, sample, None, "text", "L", px=0.024 * u * 0.85, fname=FONTS["sans"])
            cv.text(x + w - 0.022 * u, y, 0.02 * u, h, "▾", None, "muted", "C", px=0.02 * u, fname=FONTS["sans"], fit=False)
            continue
        if kind in ("edit", "COMSPEC_RscTocEdit", "COMSPEC_RscTocEditMulti"):
            cv.rect(x, y, w, h, rgb("bg0"))
            cv.rect(x, y + h - max(1, 0.001 * u), w, max(1, 0.001 * u), rgb("line"))
            sample = {"search": "Grille, groupe ou indicatif…", "flashTxt": "Texte du FLASH…", "oText": "Tenir le carrefour jusqu'à la relève", "oGrid": "044 118",
                      "cText": "", "cCs": "BRAVO-6", "tName": "EXFIL", "tVal": "30:00", "lText": "Entrée à la main…", "xEv": "Événement…", "xCw": "Mot de code", "xEl": "Élément",
                      "xAt": "12:45", "qAns": "Hélico décolle, ETA 8 min", "hName": "TOC BRAVO", "hScrName": "MUR CENTRE", "hSceneName": "Nom de la scène…", "dPlanTxt": "OBJ RAVEN", "mText": "CP NORD"}.get(key, "")
            ph = sample.endswith("…")
            cv.text(x, y, w, min(h, 0.034 * u), sample, None, "muted" if ph else "text", "L", px=0.024 * u * 0.85, fname=FONTS["sans"])
            continue
        if key == "sBack":
            cv.rect(x, y, w, h, (0, 0, 0, 255))
            cv.text(x, y + h * 0.4, w, 0.04 * u, "Onglet SAT : refonte en cours sur la branche feat/toc-sat-isr", None, "muted", "C", px=0.022 * u, fname=FONTS["sans"])
            continue
        if key == "hPlanBg":
            cv.rect(x, y, w, h, rgb("bg1"))
            for i in range(6):
                bx = x + w * 0.08 + (i % 3) * w * 0.29
                by = y + h * 0.18 + (i // 3) * h * 0.33
                on = i == 1
                cv.rect(bx, by, w * 0.26, h * 0.28, rgb("accentDim" if on else "bg2"))
                cv.rect(bx, by, w * 0.26, max(1, 0.002 * u), rgb("accent" if on else "line"))
                cv.text(bx, by, w * 0.26, h * 0.28, ["SAT 1", "CARTE ET FLUX", "HORLOGE", "MOSAÏQUE", "MAIN COURANTE", "EXCHECK"][i], None, "accent" if on else "text2", "C", px=0.017 * u, fname=FONTS["sansBold"])
            continue
        if key == "hPlanTitle":
            cv.text(x, y, w, h, "Mur vu depuis ce poste · 6 lié(s) · 1 proche(s) non lié(s)", None, "text2", "L", px=0.85 * sb, fname=FONTS["sans"])
            continue
        if key in INFO:
            yy = y
            for s, st in INFO[key]:
                big = st == "heading"
                lh = (1.05 if big else 0.85) * sb * 1.15
                if yy + lh > y + h:
                    break
                cv.text(x, yy, w, lh, s, None, {"heading": "text", "body": "text2"}.get(st, st), "L", px=(1.05 if big else 0.85) * sb, fname=FONTS["sansBold" if big else "sans"])
                yy += lh
            continue
        if key in ("toolHint", "dInfo", "sStrip", "sLegend", "sInfo"):
            msg = {"toolHint": "Outil SÉLECTION : clic gauche sur un groupe pour sa fiche · clic droit : objectif de l'ordre",
                   "dInfo": "Réglages de l'outil : choisissez TRAIT, DESSIN, PLAN ou MARQUEUR."}.get(key, "")
            cv.text(x, y, w, h, msg, None, "muted", "L", px=0.8 * sb, fname=FONTS["sans"])
            continue
    save(cv.img, out)


# ---------------------------------------------------------------------------------------------------------- sortie
SAVED = []


def save(img, name):
    OUT.mkdir(parents=True, exist_ok=True)
    p = OUT / f"{name}.png"
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


def main():
    wall("mur_split", "TOC · BRAVO", "carte et flux", draw_split)
    wall("mur_split_flash", "TOC · BRAVO", "carte et flux", lambda cv: draw_split(cv, dy=52), flash="CONTACT NORD, TOUS AUX ABRIS · Cne Martin · 12:35Z")
    wall("mur_pip", "TOC · BRAVO-6", "PIP du poste · onglet GROUPES", lambda cv: draw_split(cv, pip=True))
    wall("mur_carte", "TOC · BRAVO", "carte seule", draw_map)
    wall("mur_flux", "FLUX · BRAVO", "flux seul", draw_feed)
    wall("mur_reseau", "RÉSEAU · BRAVO", "réseau", draw_net)
    wall("mur_orbat", "ORBAT · BRAVO", "ORBAT du site", draw_orbat)
    wall("mur_escouade", "ESCOUADE · ALPHA 1", "écran d'escouade", draw_squad)
    wall("mur_escouade_camera", "ESCOUADE · ALPHA 1", "écran d'escouade · caméra du chef", lambda cv: draw_squad(cv, cam=True))
    wall("mur_mosaique", "CAMÉRAS · MUR CENTRE", "mosaïque 3 x 3 · tout le camp", draw_mosaic)
    wall("mur_mosaique_2x2", "CAMÉRAS · MUR CENTRE", "mosaïque 2 x 2 · groupe ALPHA", lambda cv: draw_mosaic(cv, 2))
    wall("mur_horloge", "HORLOGE · BRAVO", "horloge et chronos", draw_clock)
    wall("mur_main_courante", "MAIN COURANTE · BRAVO", "main courante", draw_log)
    wall("mur_excheck", "EXÉCUTION · BRAVO", "liste d'exécution", draw_excheck)
    wall("mur_pertes", "PERTES ET RÉSULTATS · BRAVO", "pertes et résultats", draw_kills)
    wall("mur_demandes", "DEMANDES · BRAVO", "demandes des éléments", draw_req)
    cv = Canvas(2048, 512, 2048 / 1600, 512 / 400)
    strip(cv)
    save(cv.img, "bandeau_horloge")
    cv = Canvas(2048, 512, 2048 / 1600, 512 / 400)
    plate(cv)
    save(cv.img, "plaque_nom")
    for tab, n in [("GROUPES", "pc_groupes"), ("ORBAT", "pc_orbat"), ("FLUX", "pc_flux"), ("ORDRES", "pc_ordres"), ("CAMP", "pc_camp"), ("RÉSEAU", "pc_reseau"),
                   ("RADIO", "pc_radio"), ("SALLE", "pc_salle"), ("CHRONOS", "pc_chronos"), ("MAIN COURANTE", "pc_main_courante"), ("EXCHECK", "pc_excheck"),
                   ("PERTES", "pc_pertes"), ("DEMANDES", "pc_demandes")]:
        pc(tab, n)
    pc("GROUPES", "pc_carte_dessin", tool="LINE")
    pc("GROUPES", "pc_groupes_4x3", aspect=4 / 3, H=1080)
    pc("GROUPES", "pc_groupes_21x9", aspect=21 / 9, H=1080)
    contact_sheet()
    print("\n".join(str(p) for p in SAVED))


if __name__ == "__main__":
    main()
