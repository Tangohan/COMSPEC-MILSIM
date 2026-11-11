#!/usr/bin/env python3
"""Maquettes PNG du nouveau gestionnaire des écrans TOC (« Gérer les écrans », fn_tocManager).

Ce ne sont PAS des captures du jeu : images dessinées hors jeu pour valider la mise en page avant d'écrire le SQF.
Spécification : docs/audit/2026-11-gestionnaire-ecrans-ui.md. Couleurs : table _tokens de fn_tocTheme.sqf (lue telle
quelle). Tailles en « pixels de conception » sur une hauteur de 1080 ; tout est multiplié par hauteur / 1080, comme une
fenêtre posée en fractions de safeZoneH. Hauteur de texte = hauteur de ligne d'Arma (em Roboto = hauteur / 1,18).
Polices : Roboto Condensed (police du jeu) ; la monospace du jeu (Etelka) n'est pas libre : Consolas ou DejaVu Sans Mono.

Usage : python -I mod/COMSPEC_ATAK_Native/tools/toc_manager_mockups.py [dossier de sortie]
        (défaut : ../_toc_maquettes/gestionnaire_ui à côté du dépôt)
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
FONT = ROOT / "public/assets/fonts/patch-studio/RobotoCondensed.ttf"
OUT = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("E:/Developpement/compsec.ttrd.fr/_toc_maquettes/gestionnaire_ui")
MONO = [Path("C:/Windows/Fonts/consola.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf")]
MONOB = [Path("C:/Windows/Fonts/consolab.ttf"), Path("/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf")]

_m = re.search(r"private _tokens = (\[.*?\]);", (TOC / "fn_tocTheme.sqf").read_text(encoding="utf-8"), re.S)
TOK = {k: v for k, v in json.loads(_m.group(1))}


def col(tok, a=1.0):
    h = TOK[tok]
    return (int(h[1:3], 16), int(h[3:5], 16), int(h[5:7], 16), int(round(a * 255)))


_FC = {}


def font(px, bold=False, mono=False):
    key = (round(px, 1), bold, mono)
    if key not in _FC:
        if mono:
            p = next(p for p in (MONOB if bold else MONO) if p.exists())
            _FC[key] = ImageFont.truetype(str(p), max(8, int(round(px * 0.80))))
        else:
            f = ImageFont.truetype(str(FONT), max(8, int(round(px / 1.18))))
            f.set_variation_by_name("Bold" if bold else "Regular")
            _FC[key] = f
    return _FC[key]


# ---------------------------------------------------------------------------------------------------------------- toile
class Cv:
    """Toile en pixels de conception (hauteur 1080). k = hauteur réelle / 1080 ; plancher de texte 0,0145 x hauteur."""

    def __init__(self, W, H):
        self.W, self.H, self.k = W, H, H / 1080
        self.img = Image.new("RGB", (W, H), (22, 24, 27))
        self.d = ImageDraw.Draw(self.img, "RGBA")
        backdrop(self)

    def P(self, v):
        return int(round(v * self.k))

    def fh(self, h):
        return max(h * self.k, 0.0145 * self.H)

    def rect(self, x, y, w, h, tok, a=1.0, border=None, bw=1, ba=1.0):
        x0, y0, x1, y1 = self.P(x), self.P(y), self.P(x + w) - 1, self.P(y + h) - 1
        x1, y1 = max(x0, x1), max(y0, y1)
        if tok:
            self.d.rectangle([x0, y0, x1, y1], fill=col(tok, a))
        if border:
            t = max(1, self.P(bw))
            for i in range(t):
                self.d.rectangle([x0 + i, y0 + i, x1 - i, y1 - i], outline=col(border, ba))

    def tw(self, s, h=20, bold=False, mono=False):
        return self.d.textlength(s, font=font(self.fh(h), bold, mono)) / self.k

    def cut(self, s, w, h=20, bold=False, mono=False):
        if self.tw(s, h, bold, mono) <= w:
            return s
        while len(s) > 1 and self.tw(s + "…", h, bold, mono) > w:
            s = s[:-1]
        return s.rstrip() + "…"

    def text(self, x, y, s, h=20, tok="text", bold=False, mono=False, anchor="l", w=None, a=1.0, box=None):
        """y = haut de la boîte de ligne ; box = hauteur de la boîte où centrer (défaut h)."""
        if w:
            s = self.cut(s, w, h, bold, mono)
        bh = box if box else h
        self.d.text((self.P(x), self.P(y + bh / 2)), s, font=font(self.fh(h), bold, mono), fill=col(tok, a), anchor=anchor + "m")
        return self.tw(s, h, bold, mono)

    def wrap(self, s, w, h=20, bold=False, mono=False, lines=2):
        out, cur = [], ""
        for word in s.split(" "):
            t = (cur + " " + word).strip()
            if self.tw(t, h, bold, mono) <= w or not cur:
                cur = t
            else:
                out.append(cur)
                cur = word
        out.append(cur)
        if len(out) > lines:
            out = out[:lines - 1] + [self.cut(" ".join(out[lines - 1:]), w, h, bold, mono)]
        return out

    def dot(self, x, y, tok, r=5, a=1.0):
        cx, cy, rr = self.P(x), self.P(y), max(2, self.P(r))
        self.d.ellipse([cx - rr, cy - rr, cx + rr, cy + rr], fill=col(tok, a))

    def tri(self, x, y, tok, kind="down", s=5):
        cx, cy, r = self.P(x), self.P(y), max(3, self.P(s))
        pts = {"down": [(cx - r, cy - r * 0.6), (cx + r, cy - r * 0.6), (cx, cy + r * 0.7)],
               "right": [(cx - r * 0.6, cy - r), (cx + r * 0.7, cy), (cx - r * 0.6, cy + r)],
               "up": [(cx - r, cy + r * 0.6), (cx + r, cy + r * 0.6), (cx, cy - r * 0.7)],
               "left": [(cx + r * 0.6, cy - r), (cx - r * 0.7, cy), (cx + r * 0.6, cy + r)]}[kind]
        self.d.polygon(pts, fill=col(tok))

    def power(self, x, y, tok, r=8):
        cx, cy, rr = self.P(x), self.P(y), max(4, self.P(r))
        t = max(2, self.P(2.2))
        self.d.arc([cx - rr, cy - rr, cx + rr, cy + rr], -55, 235, fill=col(tok), width=t)
        self.d.line([cx, cy - rr - 1, cx, cy - 1], fill=col(tok), width=t)

    def check(self, x, y, tok):
        p = self.P
        self.d.line([p(x - 5), p(y), p(x - 1), p(y + 4), p(x + 6), p(y - 5)], fill=col(tok), width=max(2, p(2.4)))

    def paste(self, im, x, y):
        self.img.paste(im.convert("RGB"), (self.P(x), self.P(y)))

    def dim(self, x, y, w, h, a=0.66):
        self.rect(x, y, w, h, "bg0", a)


def backdrop(cv):
    """Fond du jeu assombri derrière la fenêtre (uni : la maquette ne prétend pas montrer le jeu)."""
    n = Image.effect_noise((cv.W // 8, cv.H // 8), 26).resize((cv.W, cv.H), Image.BICUBIC).filter(ImageFilter.GaussianBlur(24))
    base = Image.merge("RGB", (n.point(lambda v: 14 + v // 14), n.point(lambda v: 17 + v // 13), n.point(lambda v: 19 + v // 12)))
    cv.img.paste(base)


# ------------------------------------------------------------------------------------------------------------ contrôles
def button(cv, x, y, w, h, label, kind="normal", fs=18):
    """normal bg2 · primary accent · danger rouge · ghost filet seul · off désactivé."""
    bg, fg, bd = {"normal": ("bg2", "text", "line"), "primary": ("accent", "bg0", None), "danger": ("redDim", "red", "red"),
                  "ghost": (None, "text2", "line"), "off": ("bg1", "muted", "line"), "hover": ("bg3", "text", "lineStrong"),
                  "armed": ("accentDim", "accent", "accent")}[kind]
    cv.rect(x, y, w, h, bg, border=bd)
    cv.text(x + w / 2, y, label, fs, fg, bold=True, anchor="m", box=h, a=0.55 if kind == "off" else 1)


def edit(cv, x, y, w, h, value, mono=False, placeholder=False, focus=False, fs=19):
    cv.rect(x, y, w, h, "bg0", border="accent" if focus else "lineStrong")
    cv.text(x + 10, y, value, fs, "muted" if placeholder else "text", mono=mono, box=h, w=w - 20)
    if focus:
        cx = x + 12 + cv.tw(value, fs, mono=mono)
        cv.rect(cx, y + 7, 2, h - 14, "accent")


def combo(cv, x, y, w, h, value, fs=19, tok="text"):
    cv.rect(x, y, w, h, "bg0", border="lineStrong")
    cv.text(x + 10, y, value, fs, tok, box=h, w=w - 36)
    cv.tri(x + w - 16, y + h / 2, "text2", "down", 5)


def slider(cv, x, y, w, h, frac, value, vw=96):
    """Glissière (RscXSliderH) + valeur saisissable (RscEdit) toujours visible à droite."""
    sw = w - vw - 10
    cy = y + h / 2
    cv.rect(x, cy - 3, sw, 6, "bg0", border="lineStrong")
    cv.rect(x, cy - 3, sw * frac, 6, "accent")
    cv.rect(x + sw * frac - 6, y + 4, 12, h - 8, "text", border="bg0")
    edit(cv, x + sw + 10, y, vw, h, value, mono=True, fs=18)


def toolbox(cv, x, y, w, h, opts, sel, fs=17, swatches=None):
    """Bouton à segments (RscToolbox) : un seul choix, flèches du clavier."""
    n = len(opts)
    cv.rect(x, y, w, h, "bg0", border="lineStrong")
    cw = w / n
    for i, o in enumerate(opts):
        on = i == sel or (isinstance(sel, (list, tuple, set)) and i in sel)
        if on:
            cv.rect(x + i * cw + 2, y + 2, cw - 4, h - 4, "accentDim", border="accent")
        elif i:
            cv.rect(x + i * cw, y + 6, 1, h - 12, "line")
        tx = x + i * cw + cw / 2
        if swatches:
            sw = cv.tw(o, fs, bold=on) + 18
            cv.rect(tx - sw / 2, y + h / 2 - 5, 10, 10, swatches[i])
            cv.text(tx - sw / 2 + 18, y, o, fs, "accent" if on else "text2", bold=on, box=h)
        else:
            cv.text(tx, y, o, fs, "accent" if on else "text2", bold=on, anchor="m", box=h)


def checkbox(cv, x, y, label, on, fs=19, w=None, tok="text"):
    cv.rect(x, y + 3, 20, 20, "bg0", border="accent" if on else "lineStrong")
    if on:
        cv.check(x + 10, y + 13, "accent")
    cv.text(x + 30, y, label, fs, tok if on else "text2", box=26, w=w)


def pill(cv, x, y, label, tok, h=22, fs=15, right=False):
    w = cv.tw(label, fs, bold=True) + 18
    if right:
        x -= w
    cv.rect(x, y, w, h, tok, 0.16)
    cv.rect(x, y, 3, h, tok)
    cv.text(x + 11, y, label, fs, tok, bold=True, box=h)
    return w


def section(cv, x, y, w, title, status=None, stok="muted"):
    """En-tête de section du thème : titre, état à droite, filet avec trait accent de 40 px. Rend le y suivant."""
    cv.text(x, y, title, 21, "text", bold=True, box=28)
    if status:
        cv.text(x + w, y, status, 16, stok, anchor="r", box=28)
    cv.rect(x, y + 30, w, 1, "line")
    cv.rect(x, y + 29, 40, 2, "accent")
    return y + 42


def field(cv, x, y, w, label, lw=132):
    """Libellé à gauche d'un champ (jamais au-dessus d'un titre). Rend (x du contrôle, largeur du contrôle)."""
    cv.text(x, y, label, 19, "text2", box=34)
    return x + lw, w - lw


def helpline(cv, x, y, w, s, tok="muted"):
    ls = cv.wrap(s, w, 16)
    for n, l in enumerate(ls):
        cv.text(x, y + n * 20, l, 16, tok, box=20)
    return y + 20 * len(ls) + 6


def table(cv, x, y, w, cols, rows, rh=32, sel=None, head=True, hover=None, fs=18):
    """Liste à colonnes (RscListNBox). cols : (titre, largeur, 'l'|'r', mono). Cellule : texte, (texte, jeton) ou ('dot', jeton)."""
    if head:
        cx = x
        for t, cw, al, _ in cols:
            cv.text(cx + (cw - 10 if al == "r" else 10), y, t, 15, "muted", anchor=al, box=26)
            cx += cw
        y += 28
        cv.rect(x, y - 1, w, 1, "line")
    for i, r in enumerate(rows):
        ry = y + i * rh
        if i == sel or (isinstance(sel, (list, tuple, set)) and i in sel):
            cv.rect(x, ry, w, rh, "accentDim")
            cv.rect(x, ry, 3, rh, "accent")
        elif i == hover:
            cv.rect(x, ry, w, rh, "bg2")
        cx = x
        for (t, cw, al, mono), c in zip(cols, r):
            if isinstance(c, tuple) and c[0] == "dot":
                cv.dot(cx + cw / 2, ry + rh / 2, c[1], 5)
            elif isinstance(c, tuple) and c[0] == "chk":
                cv.rect(cx + cw / 2 - 9, ry + rh / 2 - 9, 18, 18, "bg0", border="accent" if c[1] else "lineStrong")
                if c[1]:
                    cv.check(cx + cw / 2, ry + rh / 2, "accent")
            else:
                s, tok = (c if isinstance(c, tuple) else (c, "text"))
                cv.text(cx + (cw - 10 if al == "r" else 10), ry, s, fs - (1 if mono else 0), tok, mono=mono, anchor=al, box=rh, w=cw - 16)
            cx += cw
        cv.rect(x, ry + rh - 1, w, 1, "line", 0.6)
    return y + len(rows) * rh


# ------------------------------------------------------------------------------------------------------------- vignettes
def _noise(w, h, seed, sigma=40, blur=6, scale=6):
    random.seed(seed)
    n = Image.effect_noise((max(2, w // scale), max(2, h // scale)), sigma)
    return n.resize((w, h), Image.BICUBIC).filter(ImageFilter.GaussianBlur(blur))


def thumb(kind, w, h, seed=3, lab="ALPHA 1-1 · Sgt Morel"):
    """Vignette d'une dalle. En jeu : la texture déjà rendue de l'écran (RscPicture), donc aucun rendu de plus."""
    im = Image.new("RGB", (w, h), col("bg0")[:3])
    d = ImageDraw.Draw(im, "RGBA")
    u = h / 100.0

    def bar(x, y, bw, bh, tok, a=1.0):
        d.rectangle([x * w, y * h, (x + bw) * w, (y + bh) * h], fill=col(tok, a))

    def t(x, y, s, px, tok, bold=False, mono=False, anchor="lm"):
        d.text((x * w, y * h), s, font=font(max(8, px * u), bold, mono), fill=col(tok), anchor=anchor)

    def head(title):
        bar(0, 0, 1, 0.11, "bg1")
        bar(0, 0.11, 1, 0.006, "accent")
        t(0.02, 0.055, title, 7.5, "text", bold=True)
        t(0.98, 0.055, "12:35:42Z", 7, "text2", mono=True, anchor="rm")

    def mapbg(x0, y0, x1, y1, s):
        mw, mh = int((x1 - x0) * w), int((y1 - y0) * h)
        n = _noise(mw, mh, s, 60, 5, 10)
        g = Image.merge("RGB", (n.point(lambda v: 26 + v // 7), n.point(lambda v: 36 + v // 5), n.point(lambda v: 34 + v // 7)))
        e = n.filter(ImageFilter.FIND_EDGES).point(lambda v: min(255, v * 9))
        g.paste(Image.new("RGB", (mw, mh), (74, 92, 88)), mask=e.point(lambda v: v // 3))
        im.paste(g, (int(x0 * w), int(y0 * h)))
        for i in range(1, 8):
            gx = x0 + (x1 - x0) * i / 8
            d.line([gx * w, y0 * h, gx * w, y1 * h], fill=col("lineStrong", 0.55))
        for i in range(1, 5):
            gy = y0 + (y1 - y0) * i / 5
            d.line([x0 * w, gy * h, x1 * w, gy * h], fill=col("lineStrong", 0.55))
        random.seed(s)
        d.line([(x0 + 0.05) * w, (y1 - 0.1) * h, (x0 + (x1 - x0) * 0.4) * w, (y0 + (y1 - y0) * 0.55) * h, (x1 - 0.04) * w, (y0 + 0.2) * h], fill=(196, 170, 110, 170), width=max(1, int(1.2 * u)))
        for i in range(7):
            px, py = x0 + (x1 - x0) * random.uniform(0.12, 0.88), y0 + (y1 - y0) * random.uniform(0.15, 0.85)
            s2 = 2.4 * u
            d.rectangle([px * w - s2 * 1.4, py * h - s2, px * w + s2 * 1.4, py * h + s2], outline=col("friend"), width=max(1, int(0.9 * u)), fill=col("bg0", 0.7))
        px, py = x0 + (x1 - x0) * 0.72, y0 + (y1 - y0) * 0.32
        s2 = 3.2 * u
        d.polygon([(px * w, py * h - s2), (px * w + s2, py * h), (px * w, py * h + s2), (px * w - s2, py * h)], outline=col("hostile"), fill=col("bg0", 0.7))

    def rows(x0, y0, x1, n, s, dots=True):
        random.seed(s)
        rh = 0.085
        for i in range(n):
            y = y0 + i * rh
            if dots:
                tok = random.choice(["accent", "accent", "accent", "amber", "blue"])
                d.ellipse([x0 * w, (y + 0.02) * h, x0 * w + 3.4 * u, (y + 0.02) * h + 3.4 * u], fill=col(tok))
            bar(x0 + 0.03, y + 0.012, (x1 - x0) * random.uniform(0.35, 0.8), 0.028, "text2", 0.75)
            bar(x0 + 0.03, y + 0.05, (x1 - x0) * random.uniform(0.2, 0.55), 0.016, "muted", 0.6)

    def camimg(x0, y0, x1, y1, s, label, live=True):
        mw, mh = int((x1 - x0) * w), int((y1 - y0) * h)
        if not live:
            d.rectangle([x0 * w, y0 * h, x1 * w, y1 * h], fill=col("bg1"))
            t((x0 + x1) / 2, (y0 + y1) / 2, "PAS DE SIGNAL", 8, "red", bold=True, anchor="mm")
        else:
            n = _noise(mw, mh, s, 70, 3, 5)
            sky = Image.linear_gradient("L").resize((mw, mh))
            g = Image.merge("RGB", (Image.blend(n, sky, 0.45).point(lambda v: 30 + v // 3), Image.blend(n, sky, 0.45).point(lambda v: 36 + int(v / 2.6)), Image.blend(n, sky, 0.45).point(lambda v: 34 + v // 3)))
            im.paste(g, (int(x0 * w), int(y0 * h)))
            L = 5 * u
            for cx, cy, sx, sy in ((x0, y0, 1, 1), (x1, y0, -1, 1), (x0, y1, 1, -1), (x1, y1, -1, -1)):
                px, py = cx * w + sx * 3 * u, cy * h + sy * 3 * u
                d.line([px, py, px + sx * L, py], fill=col("text", 0.85), width=max(1, int(u)))
                d.line([px, py, px, py + sy * L], fill=col("text", 0.85), width=max(1, int(u)))
        d.rectangle([x0 * w, y1 * h - 9 * u, x1 * w, y1 * h], fill=col("bg0", 0.78))
        t(x0 + 0.012, y1 - 4.5 / 100, label, 6.2, "text", bold=True)
        d.rectangle([x0 * w, y0 * h, x1 * w - 1, y1 * h - 1], outline=col("line"))

    if kind == "split":
        head("CARTE ET FLUX · BRAVO")
        mapbg(0, 0.117, 0.68, 0.94, seed)
        bar(0.68, 0.117, 0.32, 0.83, "bg1")
        t(0.70, 0.17, "FLUX", 7.5, "text", bold=True)
        rows(0.70, 0.23, 0.98, 8, seed)
    elif kind == "map":
        head("CARTE · BRAVO")
        mapbg(0, 0.117, 1, 0.94, seed)
    elif kind == "squad":
        head("ESCOUADE · ADAM")
        t(0.03, 0.2, "ADAM", 13, "text", bold=True)
        t(0.03, 0.3, "8 présents · 1 blessé", 6.5, "text2")
        for i, (v, lb, tok) in enumerate((("8", "PRÉSENTS", "accent"), ("1", "BLESSÉ", "amber"), ("0", "PERDU", "red"))):
            x = 0.36 + i * 0.21
            bar(x, 0.15, 0.195, 0.2, "bg2")
            bar(x, 0.15, 0.008, 0.2, tok)
            t(x + 0.025, 0.225, v, 13, "text", bold=True, mono=True)
            t(x + 0.025, 0.31, lb, 5.6, "text2")
        bar(0.03, 0.39, 0.94, 0.004, "line")
        rows(0.03, 0.42, 0.97, 6, seed)
    elif kind == "sat":
        n = _noise(w, h, seed, 70, max(2, w // 160), 14)
        n = Image.blend(n, _noise(w, h, seed + 1, 90, 1, 3), 0.22)
        im.paste(Image.merge("RGB", (n.point(lambda v: 18 + int(v * 0.62)), n.point(lambda v: 20 + int(v * 0.62)), n.point(lambda v: 18 + int(v * 0.62)))))
        random.seed(seed)
        for _ in range(5):
            bx_, by_ = random.uniform(0.1, 0.9) * w, random.uniform(0.15, 0.85) * h
            d.rectangle([bx_, by_, bx_ + 3.2 * u, by_ + 1.8 * u], fill=(236, 238, 236, 235))
        d.line([w / 2 - 9 * u, h / 2, w / 2 + 9 * u, h / 2], fill=col("text", 0.9), width=max(1, int(u)))
        d.line([w / 2, h / 2 - 9 * u, w / 2, h / 2 + 9 * u], fill=col("text", 0.9), width=max(1, int(u)))
        d.rectangle([w / 2 - 22 * u, h / 2 - 15 * u, w / 2 + 22 * u, h / 2 + 15 * u], outline=col("text", 0.5))
        t(0.02, 0.06, "SAT 1 · THERMIQUE BC · 045218", 6.5, "text", mono=True)
        t(0.98, 0.94, "CHAMP 8° · ALT 9 200 m", 6.5, "text", mono=True, anchor="rm")
    elif kind in ("cam", "nosig"):
        camimg(0, 0, 1, 1, seed, lab, live=kind == "cam")
    elif kind == "mosaic":
        labs = ["ALPHA 1-1", "ALPHA 1-2", "DRONE AR-2", "PORTE NORD"]
        for i in range(4):
            x0, y0 = (i % 2) * 0.5, (i // 2) * 0.5
            camimg(x0 + 0.004, y0 + 0.006, x0 + 0.496, y0 + 0.494, seed + i, labs[i], live=i != 3)
    elif kind == "clock":
        head("HORLOGE ET CHRONOS")
        t(0.5, 0.36, "12:35:42", 26, "text", bold=True, mono=True, anchor="mm")
        t(0.5, 0.52, "ZULU", 8, "accent", bold=True, anchor="mm")
        for i, (lb, v) in enumerate((("LOCAL", "13:35"), ("H", "-00:42:18"), ("RELÈVE", "02:14:00"))):
            x = 0.04 + i * 0.32
            bar(x, 0.64, 0.3, 0.26, "bg2")
            bar(x, 0.64, 0.008, 0.26, "accent")
            t(x + 0.025, 0.72, v, 10, "text", bold=True, mono=True)
            t(x + 0.025, 0.84, lb, 6, "text2")
    elif kind == "net":
        head("RÉSEAU · BRAVO")
        for i, (v, lb, tok) in enumerate((("26", "EN LIGNE", "accent"), ("3", "ANCIENS", "amber"), ("1", "PERDU", "red"), ("2", "RELAIS", "blue"))):
            x = 0.03 + i * 0.24
            bar(x, 0.16, 0.225, 0.2, "bg2")
            bar(x, 0.16, 0.008, 0.2, tok)
            t(x + 0.025, 0.235, v, 13, "text", bold=True, mono=True)
            t(x + 0.025, 0.32, lb, 5.6, "text2")
        rows(0.03, 0.42, 0.97, 6, seed)
    elif kind in ("log", "exec", "req"):
        head({"log": "MAIN COURANTE", "exec": "EXÉCUTION", "req": "DEMANDES"}[kind])
        rows(0.03, 0.17, 0.97, 9, seed)
    elif kind == "bars":
        cs = [(192, 192, 192), (192, 192, 0), (0, 192, 192), (0, 192, 0), (192, 0, 192), (192, 0, 0), (0, 0, 192)]
        for i, c in enumerate(cs):
            d.rectangle([i * w / 7, 0, (i + 1) * w / 7, h * 0.7], fill=c)
        d.rectangle([0, h * 0.7, w, h], fill=(16, 16, 16))
        d.rectangle([w * 0.25, h * 0.74, w * 0.75, h * 0.92], fill=(0, 0, 0))
        t(0.5, 0.83, "TOC BRAVO · CANAL 1", 8, "text", bold=True, mono=True, anchor="mm")
    elif kind == "text":
        t(0.5, 0.5, "OPÉRATION HORIZON · H-00:42", 10.5, "accent", bold=True, mono=True, anchor="mm")
        d.rectangle([2, 2, w - 3, h - 3], outline=col("accent", 0.35))
    elif kind == "stripclock":
        t(0.5, 0.5, "12:35:42 Z · H-00:42:18", 12, "text", bold=True, mono=True, anchor="mm")
        d.rectangle([2, 2, w - 3, h - 3], outline=col("text", 0.3))
    elif kind in ("off", "standby"):
        t(0.5, 0.5, "ÉTEINTE" if kind == "off" else "EN VEILLE", 9, "muted", bold=True, anchor="mm")
    return im


def led(w, h, s, tok="accent", px=0.56):
    im = Image.new("RGB", (w, h), col("bg0")[:3])
    d = ImageDraw.Draw(im, "RGBA")
    d.text((w / 2, h / 2), s, font=font(h * px / 0.8, True, True), fill=col(tok), anchor="mm")
    d.rectangle([1, 1, w - 2, h - 2], outline=col(tok, 0.35))
    return im


STATE = {"live": ("EN DIRECT", "accent"), "frozen": ("FIGÉE", "amber"), "far": ("LOINTAINE", "lost"), "nosig": ("PAS DE SIGNAL", "red"),
         "off": ("ÉTEINTE", "muted"), "standby": ("VEILLE", "amber")}


def tile(cv, x, y, w, num, label, kind, state="live", sel=False, bright="100 %", seed=3, armed=False, ratio=16 / 9, small=False, lab=None):
    """Dalle du schéma : bandeau (n° · source, état), vignette, pied (luminosité, bouton d'alimentation). Rend la hauteur."""
    th, fh_ = (28, 0) if small else (34, 32)
    ih = w / ratio
    h = th + ih + fh_
    cv.rect(x, y, w, h, "bg1")
    cv.rect(x, y, w, th, "accentDim" if sel else "bg2")
    stlab, tok = STATE[state]
    if small:
        wide = w >= 200
        cv.text(x + 10, y, label, 17, "accent" if sel else "text", bold=True, box=th, w=w - 34 - ((cv.tw(stlab.lower(), 15) + 10) if wide else 0))
        if wide:
            cv.text(x + w - 24, y, stlab.lower(), 15, tok, anchor="r", box=th)
        cv.dot(x + w - 13, y + th / 2, tok, 4.5)
    else:
        pw = cv.tw(stlab, 15, bold=True) + 26
        cv.text(x + 10, y, f"{num} · {label}", 19, "accent" if sel else "text", bold=True, box=th, w=w - pw - 16)
        pill(cv, x + w - 6, y + (th - 22) / 2, stlab, tok, h=22, fs=15, right=True)
    iw_, ih_ = cv.P(x + w) - cv.P(x), cv.P(y + th + ih) - cv.P(y + th)
    knd = {"off": "off", "standby": "standby"}.get(state, kind)
    im = thumb(knd, iw_, ih_, seed, lab or "ALPHA 1-1 · Sgt Morel")
    if state in ("far", "frozen"):
        im = Image.blend(im, Image.new("RGB", im.size, col("bg0")[:3]), 0.45 if state == "far" else 0.2)
    cv.img.paste(im, (cv.P(x), cv.P(y + th)))
    if fh_:
        fy = y + th + ih
        cv.text(x + 10, fy, f"LUM. {bright}", 15, "text2", mono=True, box=fh_)
        cv.rect(x + w - 40, fy + 3, 34, fh_ - 6, "bg2", border="line")
        cv.power(x + w - 23, fy + fh_ / 2 + 1, {"off": "muted", "standby": "amber"}.get(state, "accent"), 7)
    cv.rect(x, y, w, h, None, border="accent" if (sel or armed) else "lineStrong", bw=3 if sel else 1, ba=1 if sel else (0.9 if armed else 1))
    return h


# ----------------------------------------------------------------------------------------------------------------- coque
D = dict(x=40, y=28, w=1840, h=1024, top=62, bot=62, rail=304, insp=540)
TABS = ["SOURCE", "VUE", "DISPOSITION", "TEXTE", "ALIMENTATION", "AVANCÉ"]
TREE = [  # (retrait, flèche, libellé, jeton d'état, à droite, alimentation)
    (0, "down", "SALLE TOC BRAVO", None, "9 écrans", None),
    (1, "right", "MUR NORD", "accent", "6 dalles", "on"),
    (1, "down", "MUR INCURVÉ", "accent", "2 dalles", "on"),
    (2, None, "1 · Escouade ADAM", "accent", "", "on"),
    (2, None, "2 · Carte et flux", "accent", "", "on"),
    (1, None, "ÉCRAN GAUCHE · SAT 1", "accent", "", "on"),
    (1, None, "ÉCRAN DROIT · Mosaïque 2 × 2", "amber", "figée", "on"),
    (1, None, "BANDEAU · Horloge", "accent", "", "on"),
    (1, None, "MESSAGE · Texte", "accent", "", "on"),
    (1, None, "PLAQUE · Alpha 1-1", "lost", "loin", "on"),
    (1, "right", "POSTE 1", "accent", "3 dalles", "on"),
    (1, None, "SUSPENDU · Réseau", "amber", "veille", "sb"),
    (1, None, "ENTRÉE · éteint", "muted", "", "off"),
    (0, "right", "SALLE BRIEFING", None, "4 écrans", None),
    (0, "right", "SANS SALLE", None, "2 écrans", None),
]


def shell(cv, sel=(4,), crumb=("SALLE TOC BRAVO", "MUR INCURVÉ", "Dalle 2"), status=("Appliqué à 1 dalle · 12:35:42 Z", "accent"),
          pending=None, tree=None, room="SALLE TOC BRAVO · 9 écrans", scene="Scène : VEILLE COURANTE", search=None, apply="APPLIQUER", armed_tree=None):
    """Fenêtre : barre du haut, arbre à gauche, barre d'actions en bas. Rend (centre, inspecteur) en pixels de conception."""
    x, y, w, h = D["x"], D["y"], D["w"], D["h"]
    cv.rect(x - 1, y - 1, w + 2, h + 2, "bg0", border="lineStrong")
    # barre du haut
    cv.rect(x, y, w, D["top"], "bg1")
    cv.rect(x, y + D["top"], w, 2, "accent")
    cv.text(x + 20, y, "ÉCRANS DU TOC", 28, "text", bold=True, box=D["top"])
    bx, by, bh = x + 214, y + 13, 36
    combo(cv, bx, by, 290, bh, room)
    combo(cv, bx + 302, by, 250, bh, scene)
    button(cv, bx + 560, by, 96, bh, "CHARGER", "normal", 16)
    button(cv, bx + 662, by, 124, bh, "ENREGISTRER", "normal", 16)
    if search:
        edit(cv, bx + 806, by, 300, bh, search, focus=True)
    else:
        edit(cv, bx + 806, by, 300, bh, "Chercher un écran, une source…", placeholder=True, fs=18)
    rx = x + w - 14
    for lab, bw in (("FERMER", 104), ("JOURNAL", 110), ("SALLES", 100)):
        rx -= bw
        button(cv, rx, by, bw, bh, lab, "normal", 16)
        rx -= 8
    # arbre
    ry = y + D["top"] + 2
    bodyh = h - D["top"] - 2 - D["bot"]
    cv.rect(x, ry, D["rail"], bodyh, "bg1")
    cv.rect(x + D["rail"], ry, 1, bodyh, "line")
    cv.text(x + 16, ry + 10, "Écrans", 22, "text", bold=True, box=28)
    cv.text(x + D["rail"] - 16, ry + 10, "17 dalles · 12 en direct", 16, "muted", anchor="r", box=28)
    cv.rect(x + 12, ry + 44, D["rail"] - 24, 1, "line")
    ty = ry + 52
    rh = 31
    rows = tree or TREE
    for i, (ind, arrow, lab, tok, right, pw) in enumerate(rows):
        yy = ty + i * rh
        on = i in sel
        if on:
            cv.rect(x + 6, yy, D["rail"] - 12, rh - 1, "accentDim")
            cv.rect(x + 6, yy, 3, rh - 1, "accent")
        ix = x + 18 + ind * 18
        if arrow:
            cv.tri(ix + 4, yy + rh / 2, "text2", arrow, 4.5)
        ix += 16
        if tok:
            cv.dot(ix + 5, yy + rh / 2, tok, 4.5)
            ix += 18
        rw = cv.tw(right, 15) if right else 0
        px_ = x + D["rail"] - 26
        if pw:
            cv.power(px_, yy + rh / 2 + 1, {"on": "accent", "sb": "amber", "off": "muted"}[pw], 6)
        if right:
            cv.text(px_ - (16 if pw else -8), yy, right, 15, "muted", anchor="r", box=rh)
        cv.text(ix, yy, lab, 18, ("accent" if on else ("muted" if pw == "off" else "text")), bold=(ind == 0 or on), box=rh,
                w=x + D["rail"] - ix - 34 - rw - (10 if right else 0))
    fy = ry + bodyh - 96
    cv.rect(x + 12, fy, D["rail"] - 24, 1, "line")
    bw3 = (D["rail"] - 24 - 12) / 3
    for i, (lab, kind) in enumerate((("AJOUTER", "normal"), ("DUPLIQUER", "normal"), ("SUPPRIMER", "normal"))):
        button(cv, x + 12 + i * (bw3 + 6), fy + 10, bw3, 34, lab, kind, 15)
    bw2 = (D["rail"] - 24 - 6) / 2
    button(cv, x + 12, fy + 52, bw2, 34, "ALLER À L'ÉCRAN", "ghost", 15)
    button(cv, x + 12 + bw2 + 6, fy + 52, bw2, 34, "IDENTIFIER", "ghost", 15)
    # barre d'actions
    ay = y + h - D["bot"]
    cv.rect(x, ay, w, D["bot"], "bg1")
    cv.rect(x, ay, w, 1, "lineStrong")
    cx = x + 20
    for i, c in enumerate(crumb):
        last = i == len(crumb) - 1
        cx += cv.text(cx, ay, c, 19, "text" if last else "text2", bold=last, box=D["bot"])
        if not last:
            cv.tri(cx + 12, ay + D["bot"] / 2, "muted", "right", 4)
            cx += 24
    cx += 28
    cv.rect(cx - 14, ay + 18, 1, D["bot"] - 36, "line")
    if pending:
        cv.dot(cx + 6, ay + D["bot"] / 2, "amber", 5)
        cv.text(cx + 20, ay, pending, 19, "amber", box=D["bot"])
    else:
        cv.dot(cx + 6, ay + D["bot"] / 2, status[1], 5)
        cv.text(cx + 20, ay, status[0], 19, status[1], box=D["bot"])
    bx = x + w - 14
    for lab, bw, kind in ((apply, 190, "primary" if pending else "off"), ("ANNULER", 120, "normal" if pending else "off"), ("COPIER VERS…", 150, "normal"), ("RÉINITIALISER", 150, "ghost")):
        bx -= bw
        button(cv, bx, ay + 12, bw, D["bot"] - 24, lab, kind, 17)
        bx -= 8
    cxr = x + D["rail"] + 1
    cw = w - D["rail"] - 1 - D["insp"]
    cv.rect(cxr, ry, cw, bodyh, "bg0")
    ixr = cxr + cw
    cv.rect(ixr, ry, D["insp"], bodyh, "bg1")
    cv.rect(ixr, ry, 1, bodyh, "line")
    return (cxr, ry, cw, bodyh), (ixr, ry, D["insp"], bodyh)


def centre_head(cv, c, title, sub, mode=0, modes=("ÉCRAN", "SALLE", "ROUTAGE")):
    x, y, w, _ = c
    cv.text(x + 24, y + 14, title, 30, "text", bold=True, box=36)
    cv.text(x + 24, y + 52, sub, 18, "text2", box=22, w=w - 420)
    toolbox(cv, x + w - 24 - 330, y + 18, 330, 36, list(modes), mode, 16)
    cv.rect(x + 24, y + 86, w - 48, 1, "line")
    return y + 100


def insp_head(cv, i, title, sub, tab, stok="text2", warn=None):
    """Titre = nom ou source de la dalle (jamais le nom anglais du modèle), état, onglets. Rend (x, y, largeur) du contenu."""
    x, y, w, _ = i
    px = x + 20
    cv.text(px, y + 14, title, 26, "text", bold=True, box=32, w=w - 40)
    cv.text(px, y + 50, sub, 17, stok, box=22, w=w - 40)
    ty = y + 84
    ws = [cv.tw(t, 16, bold=True) + 20 for t in TABS]
    k = (w - 40) / sum(ws)
    tx = px
    cv.rect(px, ty + 36, w - 40, 1, "line")
    for n, (t, tw_) in enumerate(zip(TABS, ws)):
        tw_ *= k
        if n == tab:
            cv.rect(tx, ty, tw_, 36, "bg2")
            cv.rect(tx, ty + 34, tw_, 3, "accent")
        cv.text(tx + tw_ / 2, ty, t, 16, "accent" if n == tab else "text2", bold=True, anchor="m", box=36)
        tx += tw_
    return px, ty + 54, w - 40


def insp_foot(cv, i, help1, help2=None, scope=0, tok="text2", scopes=("Cette dalle", "Sélection", "Tout l'écran", "La salle"), title=None):
    """Pied fixe de l'inspecteur : portée de l'application, puis ligne d'aide du champ (ce que c'est + un exemple)."""
    x, y, w, h = i
    by = y + h - 172
    cv.rect(x + 20, by, w - 40, 1, "line")
    cv.text(x + 20, by + 12, "Appliquer à", 19, "text2", box=34)
    toolbox(cv, x + 132, by + 12, w - 152, 34, list(scopes), scope, 16)
    cv.rect(x + 20, by + 58, w - 40, 100, "bg0", border="red" if tok == "red" else ("amber" if tok == "amber" else "line"))
    if tok in ("red", "amber"):
        cv.rect(x + 20, by + 58, 3, 100, tok)
    cv.text(x + 34, by + 68, help1, 18, tok if tok in ("red", "amber") else "text", box=24, w=w - 66, bold=tok in ("red", "amber"))
    if help2:
        for n, l in enumerate(cv.wrap(help2, w - 68, 16)):
            cv.text(x + 34, by + 98 + n * 22, l, 16, "text2" if tok in ("red", "amber") else "muted", box=22)


def wall_curved(cv, c, y0, sel=(1,), states=("live", "live"), kinds=("squad", "split"), labels=("ESCOUADE ADAM", "CARTE ET FLUX"), armed=None, hint=None, lab=None):
    x, _, w, _ = c
    gap = 14
    tw_ = (w - 48 - gap) / 2
    hh = 0
    for n in range(2):
        hh = tile(cv, x + 24 + n * (tw_ + gap), y0, tw_, n + 1, labels[n], kinds[n], states[n], sel=n in sel, seed=5 + n, armed=armed is not None, lab=lab)
    yy = y0 + hh + 10
    if armed:
        cv.rect(x + 24, yy, w - 48, 34, "accentDim", border="accent")
        cv.text(x + 38, yy, f"Source prête : {armed}", 18, "accent", bold=True, box=34)
        cv.text(x + w - 38, yy, "cliquez une dalle pour la poser · Échap pour abandonner", 17, "text2", anchor="r", box=34)
    else:
        cv.text(x + 24, yy, hint or "Clic : choisir une dalle · Ctrl + clic : en ajouter · double-clic : changer sa source · molette : zoom de la carte", 16, "muted", box=34, w=w - 48)
    return yy + 46


OTHERS = [("MUR NORD", "split", "live"), ("ÉCRAN GAUCHE", "sat", "live"), ("ÉCRAN DROIT", "mosaic", "frozen"), ("POSTE 1", "net", "live"),
          ("BANDEAU", "stripclock", "live"), ("MESSAGE", "text", "live"), ("SUSPENDU", "net", "standby"), ("ENTRÉE", "clock", "off")]


def others(cv, c, y0, skip=()):
    """Autres écrans de la salle : vignettes cliquables (4 par rangée s'il y a la place pour deux rangées, sinon une rangée)."""
    x, y, w, h = c
    yy = section(cv, x + 24, y0, w - 48, "Autres écrans de la salle", "clic : ouvrir · vignettes rafraîchies toutes les 2 s")
    items = [o for o in OTHERS if o[0] not in skip]
    gap = 10
    room = y + h - yy - 8
    per = 4
    tw_ = (w - 48 - gap * (per - 1)) / per
    if 2 * (28 + tw_ * 9 / 16) + gap > room:
        per = len(items)
        tw_ = (w - 48 - gap * (per - 1)) / per
        if 28 + tw_ * 9 / 16 > room:
            return
    for n, (lab, kind, st) in enumerate(items):
        hh = 28 + tw_ * 9 / 16
        tile(cv, x + 24 + (n % per) * (tw_ + gap), yy + (n // per) * (hh + gap), tw_, None, lab, kind, st, small=True, seed=11 + n)


CATS = [("CARTES", 3), ("CAMÉRAS", 7), ("SATELLITE-ISR", 2), ("DONNÉES", 9), ("TEMPS", 3), ("PLANS", 3), ("DIFFUSION", 2), ("TEXTE", 1), ("SPÉCIAL", 3)]


def source_tab(cv, i, x, y, w, cat, rows, cols, sel, current, hover=None, filt=None):
    cv.text(x, y, "Source actuelle", 19, "text2", box=34)
    cv.rect(x + 132, y, w - 132, 34, "bg2", border="line")
    cv.text(x + 144, y, current[0], 20, "text", bold=True, box=34)
    pill(cv, x + w - 8, y + 6, current[1], "accent", right=True)
    y += 48
    cw = 150
    lh = 36
    bot = i[1] + i[3] - 184
    cv.rect(x, y, cw, bot - y, "bg0", border="line")
    for n, (lab, cnt) in enumerate(CATS):
        yy = y + 2 + n * lh
        if n == cat:
            cv.rect(x + 1, yy, cw - 2, lh, "accentDim")
            cv.rect(x + 1, yy, 3, lh, "accent")
        cv.text(x + 12, yy, lab, 17, "accent" if n == cat else "text", bold=True, box=lh)
        cv.text(x + cw - 10, yy, str(cnt), 15, "muted", mono=True, anchor="r", box=lh)
    lx, lw = x + cw + 8, w - cw - 8
    cv.rect(lx, y, lw, bot - y, "bg0", border="line")
    ly = y + 4
    if filt:
        edit(cv, lx + 6, ly + 2, lw - 12, 32, filt[0], placeholder=True, fs=17)
        toolbox(cv, lx + 6, ly + 40, lw - 12, 30, filt[1], filt[2], 15)
        ly += 76
    table(cv, lx + 1, ly, lw - 2, cols, rows, rh=34, sel=sel, hover=hover, fs=18)


# ------------------------------------------------------------------------------------------------------------- maquettes
def vue_generale(W=1920, H=1080):
    cv = Cv(W, H)
    c, i = shell(cv)
    y = centre_head(cv, c, "MUR INCURVÉ", "Grand écran incurvé · 2 dalles · SALLE TOC BRAVO · à 6 m · 2 en direct")
    y = wall_curved(cv, c, y)
    others(cv, c, y)
    x, y, w = insp_head(cv, i, "Dalle 2 · Carte et flux", "MUR INCURVÉ · en direct · luminosité 100 %", 0)
    cols = [("", 26, "l", False), ("Source", w - 166 - 28 - 130, "l", False), ("Aussi montrée sur", 130, "l", False)]
    rows = [[("dot", "accent"), ("Carte et flux", "accent"), ("MUR NORD · 1", "text2")], [("dot", "accent"), "Carte seule", ("", "muted")], [("dot", "accent"), "Situation du poste", ("POSTE 1 · 1", "text2")]]
    source_tab(cv, i, x, y, w, 0, rows, cols, 0, ("Carte et flux", "CARTES"), hover=1)
    insp_foot(cv, i, "Carte seule : la carte de situation en plein écran, sans le flux.", "Exemple : suivre un déplacement sur la dalle centrale. Double-clic : appliquer tout de suite.")
    return cv.img


def source_picker():
    cv = Cv(1920, 1080)
    c, i = shell(cv, sel=(3,), crumb=("SALLE TOC BRAVO", "MUR INCURVÉ", "Dalle 1"), pending="1 changement non appliqué · Dalle 1 : caméra ALPHA 1-3")
    y = centre_head(cv, c, "MUR INCURVÉ", "Grand écran incurvé · 2 dalles · SALLE TOC BRAVO · à 6 m · 1 en direct, 1 sans signal")
    y = wall_curved(cv, c, y, sel=(0,), states=("nosig", "live"), kinds=("nosig", "split"), labels=("CAMÉRA ALPHA 1-3", "CARTE ET FLUX"), armed="Caméra · ALPHA 1-3 (hors ligne)", lab="ALPHA 1-3 · Sdt Petit")
    others(cv, c, y)
    x, y, w = insp_head(cv, i, "Dalle 1 · Caméra ALPHA 1-3", "MUR INCURVÉ · aperçu avant application · sans signal", 0, stok="amber")
    lw = w - 166
    cols = [("", 24, "l", False), ("Caméra", lw - 24 - 118, "l", False), ("État", 118, "l", False)]
    rows = [[("dot", "accent"), "Mosaïque automatique", ("5 en direct", "accent")],
            [("dot", "accent"), "ALPHA 1-1 · Sgt Morel", ("en direct", "accent")],
            [("dot", "accent"), "ALPHA 1-2 · Cpl Diaz", ("en direct", "accent")],
            [("dot", "red"), ("ALPHA 1-3 · Sdt Petit", "accent"), ("hors ligne 4 min", "red")],
            [("dot", "accent"), "DRONE AR-2 · Bravo", ("en direct", "accent")],
            [("dot", "amber"), "VAB 2 · tourelle", ("liaison faible", "amber")],
            [("dot", "accent"), "PORTE NORD · fixe", ("en direct", "accent")],
            [("dot", "lost"), ("TOIT PC · fixe", "muted"), ("hors de portée", "lost")]]
    source_tab(cv, i, x, y, w, 1, rows, cols, 3, ("Escouade ADAM", "DONNÉES"), filt=("Chercher une caméra, un indicatif…", ["Toutes 7", "En direct 5", "Autres 2"], 0))
    insp_foot(cv, i, "Caméra hors ligne depuis 4 min : la dalle montrera PAS DE SIGNAL.", "Elle reprendra seule au retour de la liaison. Sinon : « Mosaïque automatique » (5 en direct).", tok="amber")
    return cv.img


def _view_common(cv, kind):
    sel, crumb = ((4,), ("SALLE TOC BRAVO", "MUR INCURVÉ", "Dalle 2")) if kind == "map" else ((5,), ("SALLE TOC BRAVO", "ÉCRAN GAUCHE"))
    return shell(cv, sel=sel, crumb=crumb, pending="2 changements non appliqués · zoom, suivi" if kind == "map" else None,
                 status=("Appliqué à 1 dalle · 12:36:10 Z", "accent"))


def vue_zoom_suivi():
    cv = Cv(1920, 1080)
    c, i = _view_common(cv, "map")
    y = centre_head(cv, c, "MUR INCURVÉ", "Grand écran incurvé · 2 dalles · SALLE TOC BRAVO · à 6 m · 2 en direct")
    y = wall_curved(cv, c, y, hint="Molette sur la vignette : zoom · clic droit maintenu : déplacer la carte · double-clic : centrer ici")
    others(cv, c, y)
    x, y, w = insp_head(cv, i, "Dalle 2 · Carte et flux", "MUR INCURVÉ · en direct · suit ALPHA 1-1", 1)
    y = section(cv, x, y, w, "Cadrage", "1 : 12 500 · centre 045 071")
    fx, fw = field(cv, x, y, w, "Zoom")
    button(cv, fx, y, 34, 34, "−", "normal", 22)
    slider(cv, fx + 40, y, fw - 80, 34, 0.42, "1 : 12 500", vw=112)
    button(cv, fx + fw - 34, y, 34, 34, "+", "normal", 22)
    y = helpline(cv, fx, y + 38, fw, "Exemple : 1 : 5 000 pour un village, 1 : 50 000 pour la zone.")
    fx, fw = field(cv, x, y, w, "Centre")
    edit(cv, fx, y, 120, 34, "045 071", mono=True)
    button(cv, fx + 126, y, 104, 34, "MA POSITION", "normal", 15)
    button(cv, fx + 236, y, fw - 236, 34, "POINTER…", "normal", 15)
    y = helpline(cv, fx, y + 38, fw, "Grille à 6 ou 8 chiffres. Exemple : 0452 0718.")
    fx, fw = field(cv, x, y, w, "Orientation")
    toolbox(cv, fx, y, fw, 34, ["Nord en haut", "Cap de la cible"], 0)
    y += 48
    y = section(cv, x, y, w, "Suivi", "ALPHA 1-1 · 8 membres")
    fx, fw = field(cv, x, y, w, "Suivre")
    toolbox(cv, fx, y, fw, 34, ["Rien", "Unité", "Groupe", "Véhicule", "Aéronef"], 2, 15)
    y += 40
    fx, fw = field(cv, x, y, w, "Cible")
    combo(cv, fx, y, fw, 34, "ALPHA 1-1 · Sgt Morel · 8")
    y = helpline(cv, fx, y + 38, fw, "Tapez pour chercher. Exemple : « alpha », « VAB 2 », « Griffon ».")
    checkbox(cv, fx, y, "Recentrer quand la cible sort du cadre", True, 18)
    y += 40
    y = section(cv, x, y, w, "Calques", "7 sur 10")
    layers = [("Amis", 1), ("Ennemis signalés", 1), ("Marqueurs", 1), ("Tracés du plan", 1), ("Zones", 1), ("Relais et réseau", 0), ("Quadrillage", 1), ("Noms des lieux", 1), ("Courbes de niveau", 0), ("Caméras", 0)]
    for n, (lab, on) in enumerate(layers):
        checkbox(cv, x + (n % 2) * (w / 2), y + (n // 2) * 27, lab, bool(on), 18)
    insp_foot(cv, i, "Suivre un groupe : la carte reste centrée sur son chef.", "Exemple : Groupe + ALPHA 1-1 pour suivre l'assaut sans toucher à la carte.")
    return cv.img


def vue_satellite():
    cv = Cv(1920, 1080)
    c, i = _view_common(cv, "sat")
    x, y, w, h = c
    y = centre_head(cv, c, "ÉCRAN GAUCHE", "Écran 98 pouces · 1 dalle · SALLE TOC BRAVO · à 7 m · en direct")
    tw_ = (w - 48) * 0.84
    hh = tile(cv, x + 24 + (w - 48 - tw_) / 2, y, tw_, 1, "SAT 1 · THERMIQUE", "sat", "live", sel=True, seed=9, bright="75 %")
    tw_ = w - 48
    cv.text(x + 24, y + hh + 10, "Clic sur l'image : pointer ici · molette : champ · flèches : déplacer la visée · clic droit : suivre la cible sous le curseur", 16, "muted", box=34, w=tw_)
    others(cv, c, y + hh + 54, skip=("ÉCRAN GAUCHE", "BANDEAU"))
    x, y, w = insp_head(cv, i, "ÉCRAN GAUCHE · SAT 1", "Écran 98 pouces · en direct · thermique, suit VAB 2", 1)
    y = section(cv, x, y, w, "Capteur", "SAT 1 · libre")
    fx, fw = field(cv, x, y, w, "Image")
    toolbox(cv, fx, y, fw, 34, ["Jour", "Therm. blanc", "Therm. noir", "Nuit"], 1, 15)
    y += 40
    fx, fw = field(cv, x, y, w, "Champ")
    slider(cv, fx, y, fw, 34, 0.22, "8°", vw=70)
    y = helpline(cv, fx, y + 38, fw, "De 1° (un véhicule) à 40° (un quartier). Exemple : 8°.")
    fx, fw = field(cv, x, y, w, "Qualité")
    toolbox(cv, fx, y, fw, 34, ["Auto", "512", "1024", "2048"], 0, 15)
    y = helpline(cv, fx, y + 38, fw, "Auto baisse la qualité quand personne n'est à moins de 15 m.")
    y += 6
    y = section(cv, x, y, w, "Visée", "grille 045218 · cap 312°")
    fx, fw = field(cv, x, y, w, "Mode")
    toolbox(cv, fx, y, fw, 34, ["Point fixe", "Suivi", "Balayage"], 1, 16)
    y += 40
    fx, fw = field(cv, x, y, w, "Cible")
    combo(cv, fx, y, fw, 34, "Véhicule · VAB 2 (Bravo)")
    y += 40
    fx, fw = field(cv, x, y, w, "Grille")
    edit(cv, fx, y, 120, 34, "045 218", mono=True)
    button(cv, fx + 126, y, fw - 126, 34, "POINTER SUR LA CARTE…", "normal", 15)
    y += 48
    fx, fw = field(cv, x, y, w, "Déplacer")
    bs = 34
    px0 = fx
    for dx, dy, kd in ((1, 0, "up"), (0, 1, "left"), (2, 1, "right"), (1, 2, "down")):
        cv.rect(px0 + dx * (bs + 4), y + dy * (bs + 4), bs, bs, "bg2", border="line")
        cv.tri(px0 + dx * (bs + 4) + bs / 2, y + dy * (bs + 4) + bs / 2, "text", kd, 6)
    cv.rect(px0 + bs + 4, y + bs + 4, bs, bs, "bg0", border="lineStrong")
    cv.dot(px0 + bs + 4 + bs / 2, y + bs + 4 + bs / 2, "accent", 4)
    tx = px0 + 3 * (bs + 4) + 14
    cv.text(tx, y + 2, "Flèches du clavier : même effet.", 16, "muted", box=22, w=fw - 130)
    cv.text(tx, y + 26, "Centre : revenir sur la cible.", 16, "muted", box=22, w=fw - 130)
    cv.text(tx, y + 50, "Une caméra fixe orientable se", 16, "muted", box=22, w=fw - 130)
    cv.text(tx, y + 74, "règle ici de la même façon.", 16, "muted", box=22, w=fw - 130)
    insp_foot(cv, i, "Suivi : le satellite garde la cible au centre tant qu'il la voit.", "Exemple : Suivi + VAB 2, champ 8°, thermique blanc de nuit.", scopes=("Cet écran", "Sélection", "SAT 1 partout", "La salle"))
    return cv.img


def texte():
    cv = Cv(1920, 1080)
    c, i = shell(cv, sel=(8,), crumb=("SALLE TOC BRAVO", "MESSAGE"), pending="1 changement non appliqué · message 2 modifié")
    x, y, w, h = c
    y = centre_head(cv, c, "MESSAGE", "Bandeau LED 4,8 m · 16:1 · SALLE TOC BRAVO · à 6 m · en direct")
    bw_, bh_ = w - 48, (w - 48) / 16
    cv.rect(x + 24, y, bw_, 34, "bg2")
    cv.text(x + 34, y, "À l'écran maintenant · message 1 sur 3", 18, "text", bold=True, box=34)
    pill(cv, x + 24 + bw_ - 6, y + 6, "EN DIRECT", "accent", right=True)
    cv.paste(led(cv.P(bw_), cv.P(bh_), "OPÉRATION HORIZON · H-00:42"), x + 24, y + 34)
    cv.rect(x + 24, y, bw_, 34 + bh_, None, border="lineStrong")
    y += 34 + bh_ + 18
    cv.rect(x + 24, y, bw_, 34, "accentDim")
    cv.text(x + 34, y, "Aperçu du message 2 · non appliqué", 18, "accent", bold=True, box=34)
    pill(cv, x + 24 + bw_ - 6, y + 6, "DÉFILE · 7 car./s", "amber", right=True)
    cv.paste(led(cv.P(bw_), cv.P(bh_), "IEF BRAVO 14:00 SALLE 2 · TENUE ALLÉGÉE · RADIO CANAL 3 · BRIEF", "amber"), x + 24, y + 34)
    cv.rect(x + 24, y, bw_, 34 + bh_, None, border="accent", bw=2)
    y += 34 + bh_ + 26
    y = section(cv, x + 24, y, bw_, "File des messages", "3 messages · tourne toutes les 20 s")
    cols = [("N°", 50, "l", True), ("Message", bw_ - 50 - 110 - 150 - 90 - 110, "l", False), ("Couleur", 110, "l", False), ("Effet", 150, "l", False), ("Durée", 90, "r", True), ("État", 110, "l", False)]
    rows = [["1", "OPÉRATION HORIZON · H-00:42", ("vert", "accent"), "fixe", "20 s", ("à l'écran", "accent")],
            ["2", ("BRIEF BRAVO 14:00 SALLE 2 · TENUE ALLÉGÉE · RADIO CANAL 3", "accent"), ("ambre", "amber"), "défile, normal", "30 s", ("modifié", "amber")],
            ["3", "SILENCE RADIO DE 13:50 À 14:10", ("rouge", "red"), "clignote", "15 s", ("en attente", "text2")]]
    y = table(cv, x + 24, y, bw_, cols, rows, rh=36, sel=1) + 12
    bx = x + 24
    for lab, bw, kind in (("NOUVEAU MESSAGE", 170, "normal"), ("MONTER", 100, "normal"), ("DESCENDRE", 120, "normal"), ("RETIRER", 100, "normal"), ("AFFICHER MAINTENANT", 200, "normal"), ("VIDER LA FILE", 140, "ghost")):
        button(cv, bx, y, bw, 34, lab, kind, 15)
        bx += bw + 8
    y += 56
    y = section(cv, x + 24, y, bw_, "Messages enregistrés", "profil du joueur · 6")
    bx = x + 24
    for lab in ("ALERTE BASE", "SILENCE RADIO", "BRIEF DANS 10 MIN", "RELÈVE", "FIN D'EXERCICE", "BIENVENUE"):
        bw = cv.tw(lab, 16, bold=True) + 28
        button(cv, bx, y, bw, 34, lab, "ghost", 16)
        bx += bw + 8
    button(cv, x + 24 + bw_ - 190, y, 190, 34, "ENREGISTRER CELUI-CI", "normal", 15)
    x, y, w = insp_head(cv, i, "MESSAGE · message 2", "Bandeau LED · en direct · file de 3 messages", 3)
    y = section(cv, x, y, w, "Texte", "58 / 240 caractères")
    cv.rect(x, y, w, 92, "bg0", border="accent")
    cv.text(x + 10, y + 6, "BRIEF BRAVO 14:00 SALLE 2 · TENUE ALLÉGÉE ·", 19, "text", mono=True, box=26, w=w - 20)
    tw2 = cv.text(x + 10, y + 32, "RADIO CANAL 3", 19, "text", mono=True, box=26)
    cv.rect(x + 12 + tw2, y + 36, 2, 20, "accent")
    y = helpline(cv, x, y + 98, w, "Majuscules conseillées. Exemple : BRIEF BRAVO 14:00 SALLE 2.") + 6
    y = section(cv, x, y, w, "Apparence", "ambre · défile")
    fx, fw = field(cv, x, y, w, "Couleur", 96)
    toolbox(cv, fx, y, fw, 34, ["Vert", "Ambre", "Rouge", "Blanc"], 1, 16, swatches=["accent", "amber", "red", "text"])
    y += 40
    fx, fw = field(cv, x, y, w, "Effet", 96)
    toolbox(cv, fx, y, fw, 34, ["Fixe", "Défile", "Clignote", "Frappe"], 1, 16)
    y += 40
    fx, fw = field(cv, x, y, w, "Vitesse", 96)
    toolbox(cv, fx, y, fw, 34, ["Lente", "Normale", "Rapide"], 1, 16)
    y = helpline(cv, fx, y + 38, fw, "4, 7 ou 11 caractères par seconde.")
    fx, fw = field(cv, x, y, w, "Lignes", 96)
    toolbox(cv, fx, y, fw, 34, ["Auto", "1 ligne", "2 lignes"], 0, 16)
    y += 40
    fx, fw = field(cv, x, y, w, "Durée", 96)
    slider(cv, fx, y, fw, 34, 0.25, "30 s", vw=80)
    y = helpline(cv, fx, y + 38, fw, "Temps à l'écran avant le message suivant.")
    insp_foot(cv, i, "Défile : le texte trop long passe de droite à gauche.", "Un texte qui tient reste fixe, même avec « Défile ». Entrée : appliquer.", scopes=("Cet écran", "Sélection", "Les bandeaux", "La salle"))
    return cv.img


NORD = [("CARTE ET FLUX", "split", "live"), ("SAT 1", "sat", "live"), ("MOSAÏQUE 2 × 2", "mosaic", "live"), ("RÉSEAU", "net", "standby"), ("HORLOGE", "clock", "live"), ("MIRE", "bars", "off")]
TREE_NORD = [TREE[0], (1, "down", "MUR NORD", "accent", "6 dalles", "on")] + [(2, None, f"{n + 1} · {l.capitalize()}", {"live": "accent", "standby": "amber", "off": "muted"}[s], {"standby": "veille"}.get(s, ""), {"live": "on", "standby": "sb", "off": "off"}[s]) for n, (l, _, s) in enumerate(NORD)] + [(1, "right", "MUR INCURVÉ", "accent", "2 dalles", "on")] + TREE[5:13] + TREE[13:]


def wall_nord(cv, c, y0, sel=(), hint=None):
    x, _, w, _ = c
    gap = 10
    tw_ = (w - 48 - 2 * gap) / 3
    hh = 0
    for n, (lab, kind, st) in enumerate(NORD):
        hh = tile(cv, x + 24 + (n % 3) * (tw_ + gap), y0 + (n // 3) * (hh + gap), tw_, n + 1, lab, kind, st, sel=n in sel, seed=20 + n)
    yy = y0 + 2 * hh + gap + 8
    cv.text(x + 24, yy, hint or "Clic : choisir · Ctrl + clic : ajouter · Maj + clic : de… à · Ctrl + A : tout l'écran · bouton d'une dalle : allumer, veille, éteindre", 16, "muted", box=30, w=w - 48)
    return yy + 40


LAYOUTS = [("Situation + réseau", "CF RÉS HOR"), ("Carte large", "CAR FLX HOR"), ("2 cartes", "CAR SAT RÉS"), ("Carte + caméras", "CF MOS MOS"), ("Carte + main cour.", "CAR MC FLX"), ("Quad", "CAR SAT MOS RÉS"),
           ("Surveillance", "MOS MOS"), ("Ordres et trans.", "FLX DEM CAR"), ("EXCHECK", "EXE HOR CAR"), ("Santé / pertes", "PER DEM CAR"), ("Réseau / relais", "RÉS CAR FLX"), ("Horloges + diff.", "HOR DIF")]


def alimentation_dispositions():
    cv = Cv(1920, 1080)
    c, i = shell(cv, sel=(1,), tree=TREE_NORD, crumb=("SALLE TOC BRAVO", "MUR NORD"), pending="1 changement non appliqué · disposition « Quad »", apply="APPLIQUER")
    y = centre_head(cv, c, "MUR NORD", "Mur d'images 3 × 2 · 6 dalles · SALLE TOC BRAVO · à 6 m · 4 en direct, 1 en veille, 1 éteinte")
    others(cv, c, wall_nord(cv, c, y), skip=("MUR NORD", "BANDEAU"))
    x, y, w = insp_head(cv, i, "MUR NORD · Situation + réseau", "Mur d'images 3 × 2 · 6 dalles · 4 en direct", 2)
    y = section(cv, x, y, w, "Disposition", "12 · actuelle : Situation + réseau")
    gap = 8
    cw, ch = (w - 2 * gap) / 3, 92
    for n, (lab, cells) in enumerate(LAYOUTS):
        cx, cy = x + (n % 3) * (cw + gap), y + (n // 3) * (ch + gap)
        cur, pick = n == 0, n == 5
        cv.rect(cx, cy, cw, ch, "accentDim" if pick else "bg2", border="accent" if pick else ("lineStrong" if cur else "line"), bw=2 if pick else 1)
        cs = cells.split()
        nc = 3
        mw, mh = (cw - 20 - (nc - 1) * 3) / nc, 20
        for k in range(6):
            lab2 = cs[k % len(cs)]
            mx, my = cx + 10 + (k % nc) * (mw + 3), cy + 9 + (k // nc) * (mh + 3)
            cv.rect(mx, my, mw, mh, "bg0", border="lineStrong")
            cv.text(mx + mw / 2, my, lab2, 13, "accent" if pick else "text2", bold=True, anchor="m", box=mh)
        cv.text(cx + 10, cy + 60, lab, 16, "accent" if pick else "text", bold=cur or pick, box=24, w=cw - (38 if cur else 16))
        if cur:
            cv.check(cx + cw - 16, cy + 72, "accent")
    y += 4 * (ch + gap) + 8
    y = section(cv, x, y, w, "Image sur plusieurs dalles", "aucune")
    toolbox(cv, x, y, w, 34, ["Une image par dalle", "2 × 2 au centre", "Tout le mur"], 0, 16)
    y = helpline(cv, x, y + 38, w, "Exemple : « Tout le mur » étire la carte de situation sur les 6 dalles.")
    insp_foot(cv, i, "Quad : carte, satellite, caméras et réseau.", "Exemple : mur 2 × 2 ou 3 × 2. Les dalles en trop reprennent le contenu dans l'ordre.", scope=2, scopes=("Cette dalle", "Sélection", "Tout l'écran", "La salle"))
    return cv.img


def alimentation():
    cv = Cv(1920, 1080)
    c, i = shell(cv, sel=(2, 3, 4), tree=TREE_NORD, crumb=("SALLE TOC BRAVO", "MUR NORD", "3 dalles choisies"), status=("Appliqué à 3 dalles · 12:37:02 Z", "accent"))
    y = centre_head(cv, c, "MUR NORD", "Mur d'images 3 × 2 · 6 dalles · SALLE TOC BRAVO · à 6 m · 3 dalles choisies")
    others(cv, c, wall_nord(cv, c, y, sel=(0, 1, 2)), skip=("MUR NORD", "BANDEAU"))
    x, y, w = insp_head(cv, i, "3 dalles choisies", "MUR NORD · dalles 1, 2 et 3 · valeurs communes affichées", 4)
    y = section(cv, x, y, w, "Alimentation", "3 allumées")
    fx, fw = field(cv, x, y, w, "État")
    toolbox(cv, fx, y, fw, 34, ["Allumée", "Veille", "Éteinte"], 0, 16)
    y = helpline(cv, fx, y + 38, fw, "Veille : écran noir, image gardée, rallumage immédiat.")
    fx, fw = field(cv, x, y, w, "Luminosité")
    slider(cv, fx, y, fw, 34, 0.75, "75 %", vw=80)
    y += 40
    bw = (fw - 3 * 6) / 4
    for n, lab in enumerate(("25 %", "50 %", "75 %", "100 %")):
        button(cv, fx + n * (bw + 6), y, bw, 30, lab, "armed" if n == 2 else "normal", 15)
    y = helpline(cv, fx, y + 34, fw, "Exemple : 50 % de nuit pour ne pas éblouir la salle.") + 6
    y = section(cv, x, y, w, "Veille automatique", "valeurs différentes", "amber")
    fx, fw = field(cv, x, y, w, "Mise en veille")
    combo(cv, fx, y, fw, 34, "— valeurs différentes —", tok="amber")
    y = helpline(cv, fx, y + 38, fw, "Exemple : après 10 min sans personne à moins de 30 m.")
    checkbox(cv, fx, y, "Rallumer quand quelqu'un approche", True, 18)
    y += 44
    y = section(cv, x, y, w, "Rendu", "2 en direct · 1 allégée")
    fx, fw = field(cv, x, y, w, "Qualité")
    toolbox(cv, fx, y, fw, 34, ["Auto", "Pleine", "Allégée"], 0, 16)
    y += 44
    cols = [("", 24, "l", False), ("Dalle", 150, "l", False), ("Rendu", 120, "l", False), ("Image", w - 294, "l", True)]
    rows = [[("dot", "accent"), "1 · Carte et flux", ("en direct", "accent"), ("2048 × 1024 · 2 s", "text2")], [("dot", "accent"), "2 · SAT 1", ("en direct", "accent"), ("1024 × 512 · 4 Hz", "text2")],
            [("dot", "accent"), "3 · Mosaïque 2 × 2", ("en direct", "accent"), ("4 × 256 · 4 Hz", "text2")]]
    table(cv, x, y, w, cols, rows, rh=30, fs=17)
    insp_foot(cv, i, "Plusieurs dalles : seul ce que vous changez est appliqué.", "Un champ « valeurs différentes » reste tel quel tant qu'on n'y touche pas.", scope=1, scopes=("Cette dalle", "Sélection (3)", "Tout l'écran", "La salle"))
    return cv.img


def routage():
    cv = Cv(1920, 1080)
    c, i = shell(cv, sel=(0,), crumb=("SALLE TOC BRAVO", "Routage"), status=("12 dalles alimentées · 7 sources", "accent"))
    x, y, w, h = c
    y = centre_head(cv, c, "Routage de la salle", "Une ligne par source, une colonne par sortie · clic sur une case : envoyer la source vers la dalle", mode=2)
    outs = ["NORD 1", "NORD 2", "NORD 3", "NORD 4", "NORD 5", "NORD 6", "INC. 1", "INC. 2", "GAUCHE", "DROIT", "P1 · 1", "P1 · 2", "SUSP.", "ENTRÉE"]
    srcs = [("Carte et flux", "CARTES", [0, 7]), ("Situation du poste", "CARTES", [10]), ("SAT 1 · thermique", "SATELLITE-ISR", [1, 8]), ("Mosaïque 2 × 2", "CAMÉRAS", [2, 9]), ("Caméra ALPHA 1-1", "CAMÉRAS", []),
            ("Escouade ADAM", "DONNÉES", [6]), ("Réseau", "DONNÉES", [3, 11, 12]), ("Main courante", "DONNÉES", []), ("Exécution", "DONNÉES", []), ("Horloge et chronos", "TEMPS", [4]),
            ("Plan · phase 2", "PLANS", []), ("Mire", "DIFFUSION", [5]), ("BROADCAST · canal 1", "DIFFUSION", [])]
    lw = 290
    gx = x + 24 + lw
    cw_ = (w - 48 - lw) / len(outs)
    rh = 40
    hy = y + 4
    for n, o in enumerate(outs):
        off = n in (13,)
        cv.text(gx + n * cw_ + cw_ / 2, hy, o, 14, "muted" if off else ("accent" if n == 7 else "text2"), bold=True, anchor="m", box=30)
    y = hy + 34
    cv.rect(x + 24, y - 1, w - 48, 1, "lineStrong")
    cv.rect(gx + 7 * cw_, y, cw_, rh * len(srcs), "bg1")
    for r, (lab, cat, on) in enumerate(srcs):
        yy = y + r * rh
        if r == 0:
            cv.rect(x + 24, yy, w - 48, rh, "bg2", 0.7)
        cv.text(x + 34, yy, lab, 18, "text" if on else "text2", bold=bool(on), box=rh, w=180)
        cv.text(x + 24 + lw - 12, yy, cat, 13, "muted", anchor="r", box=rh)
        for n in range(len(outs)):
            bx, by = gx + n * cw_ + cw_ / 2, yy + rh / 2
            if n in on:
                cv.rect(bx - 13, by - 13, 26, 26, "amber" if n in (3, 12) else ("lineStrong" if n in (5, 13) else "accent"))
            else:
                cv.rect(bx - 13, by - 13, 26, 26, "bg0", border="line")
        cv.rect(x + 24, yy + rh - 1, w - 48, 1, "line", 0.6)
    y += rh * len(srcs) + 14
    lx = x + 24
    for tok, lab in (("accent", "envoyée, en direct"), ("amber", "envoyée, dalle en veille"), ("lineStrong", "envoyée, dalle éteinte")):
        cv.rect(lx, y + 5, 14, 14, tok)
        lx += 22 + cv.text(lx + 22, y, lab, 16, "text2", box=24) + 26
    cv.text(x + w - 24, y, "Une dalle ne reçoit qu'une source : cocher une case décoche l'autre de la colonne.", 16, "muted", anchor="r", box=24)
    x, y, w = insp_head(cv, i, "Carte et flux", "CARTES · envoyée vers 2 dalles", 0)
    y = section(cv, x, y, w, "Sorties de cette source", "2 dalles")
    cols = [("", 24, "l", False), ("Sortie", w - 24 - 120 - 80, "l", False), ("État", 120, "l", False), ("Lum.", 80, "r", True)]
    rows = [[("dot", "accent"), "MUR NORD · dalle 1", ("en direct", "accent"), "100 %"], [("dot", "accent"), "MUR INCURVÉ · dalle 2", ("en direct", "accent"), "100 %"]]
    y = table(cv, x, y, w, cols, rows, rh=34) + 14
    button(cv, x, y, (w - 8) / 2, 34, "ENVOYER PARTOUT DANS LA SALLE", "normal", 14)
    button(cv, x + (w - 8) / 2 + 8, y, (w - 8) / 2, 34, "RETIRER DE TOUTES LES DALLES", "ghost", 14)
    y += 54
    y = section(cv, x, y, w, "Diffusion", "non diffusée")
    fx, fw = field(cv, x, y, w, "Titre")
    edit(cv, fx, y, fw, 34, "TOC BRAVO · CANAL 1")
    y = helpline(cv, fx, y + 38, fw, "Montré sur la mire avant la diffusion. Exemple : BRIEF 14:00.")
    button(cv, fx, y, fw, 34, "DIFFUSER CETTE SOURCE DANS LA SALLE", "normal", 15)
    insp_foot(cv, i, "Une source, plusieurs sorties : les dalles recopient la même image.", "Aucun rendu de plus : la texture de la source est partagée.", scope=3)
    return cv.img


def salle_suppression():
    cv = Cv(1920, 1080)
    c, i = shell(cv, sel=(13,), crumb=("Salles", "SALLE BRIEFING"), status=("3 salles · 15 écrans · 23 dalles", "text2"), room="Toutes les salles · 3")
    x, y, w, h = c
    y = centre_head(cv, c, "Salles", "3 salles sur cette carte · 2 écrans sans salle · rayon de liaison par défaut 20 m", mode=None, modes=("ÉCRAN", "SALLE", "ROUTAGE"))
    bx = x + 24
    for lab, bw, kind in (("CRÉER UNE SALLE", 150, "normal"), ("GÉNÉRER : PETITE", 150, "normal"), ("MOYENNE", 90, "normal"), ("GRANDE", 90, "normal"), ("GÉNÉRER TOUS LES FLUX", 190, "normal"), ("RENOMMER", 100, "ghost"), ("SUPPRIMER", 110, "danger")):
        button(cv, bx, y, bw, 36, lab, kind, 15)
        bx += bw + 8
    y += 52
    cols = [("", 34, "l", False), ("Salle", 250, "l", False), ("Écrans", 80, "r", True), ("Dalles", 80, "r", True), ("Postes", 80, "r", True), ("Distance", 100, "r", True), ("Accès", 180, "l", False), ("État", w - 48 - 804, "l", False)]
    rows = [[("dot", "accent"), "SALLE TOC BRAVO", "9", "17", "1", "6 m", "officiers du TOC", ("12 en direct · 2 figées", "accent")],
            [("dot", "amber"), ("SALLE BRIEFING", "accent"), "4", "4", "0", "48 m", "tout le camp", ("lointaine · 4 figées", "amber")],
            [("dot", "muted"), "PC AVANCÉ", "2", "2", "1", "1 240 m", "Zeus seulement", ("lointaine · éteinte", "muted")],
            [("dot", "lost"), ("Sans salle", "text2"), "2", "2", "0", "—", "—", ("2 écrans non liés", "text2")]]
    y = table(cv, x + 24, y, w - 48, cols, rows, rh=40, sel=1) + 28
    y = section(cv, x + 24, y, w - 48, "Écrans de SALLE BRIEFING", "4 écrans · 4 dalles")
    cols = [("", 34, "l", False), ("Écran", 260, "l", False), ("Type", 280, "l", False), ("Source", 250, "l", False), ("État", w - 48 - 824, "l", False)]
    rows = [[("dot", "amber"), "PROJECTION", "Écran 98 pouces sur pied", "Plan · diaporama", ("figée · à 48 m", "amber")], [("dot", "amber"), "TABLEAU GAUCHE", "Écran 65 pouces", "ORBAT", ("figée · à 49 m", "amber")],
            [("dot", "amber"), "TABLEAU DROIT", "Écran 65 pouces", "Effectif", ("figée · à 49 m", "amber")], [("dot", "amber"), "BANDEAU ENTRÉE", "Bandeau LED 2,4 m", "Texte · BRIEF 14:00", ("figée · à 52 m", "amber")]]
    table(cv, x + 24, y, w - 48, cols, rows, rh=36)
    x, y, w = insp_head(cv, i, "SALLE BRIEFING", "4 écrans · 4 dalles · à 48 m · lointaine", 5)
    y = section(cv, x, y, w, "Salle", "créée par Adj Mercier")
    fx, fw = field(cv, x, y, w, "Nom")
    edit(cv, fx, y, fw, 34, "SALLE BRIEFING")
    y += 46
    fx, fw = field(cv, x, y, w, "Rayon")
    slider(cv, fx, y, fw, 34, 0.3, "20 m", vw=80)
    y = helpline(cv, fx, y + 38, fw, "Les écrans posés à moins de 20 m se lient seuls à la salle.") + 6
    y = section(cv, x, y, w, "Accès", "tout le camp")
    fx, fw = field(cv, x, y, w, "Qui règle")
    combo(cv, fx, y, fw, 34, "Tout le camp")
    y = helpline(cv, fx, y + 38, fw, "Exemple : « officiers du TOC » verrouille la salle pour les autres.")
    button(cv, fx, y, fw, 34, "OUVRIR LE JOURNAL DE LA SALLE", "normal", 15)
    insp_foot(cv, i, "Le journal garde qui a changé quoi, et quand.", "Exemple : 12:31 Z · Adj Mercier · MUR NORD dalle 2 : SAT 1.", scope=3)
    # confirmation dans la fenêtre (jamais une alerte du jeu)
    cv.dim(D["x"], D["y"], D["w"], D["h"], 0.72)
    mw, mh = 720, 362
    mx, my = D["x"] + (D["w"] - mw) / 2, D["y"] + (D["h"] - mh) / 2
    cv.rect(mx, my, mw, mh, "bg1", border="red", bw=1)
    cv.rect(mx, my, mw, 3, "red")
    cv.text(mx + 28, my + 24, "Supprimer SALLE BRIEFING ?", 30, "text", bold=True, box=36)
    cv.text(mx + 28, my + 70, "4 écrans et 4 dalles sont liés à cette salle. Les autres salles ne changent pas.", 19, "text2", box=26)
    oy = my + 116
    for n, (lab, sub, on) in enumerate((("Garder les écrans", "Ils restent posés et passent dans « Sans salle ».", True), ("Supprimer aussi les 4 écrans", "Les objets sont retirés de la mission. Ils reviennent avec « Charger » si la scène est enregistrée.", False))):
        yy = oy + n * 76
        cv.rect(mx + 28, yy, mw - 56, 66, "accentDim" if on else "bg0", border="accent" if on else "lineStrong")
        cv.dot(mx + 52, yy + 33, "bg0", 10)
        cv.d.ellipse([cv.P(mx + 42), cv.P(yy + 23), cv.P(mx + 62), cv.P(yy + 43)], outline=col("accent" if on else "lineStrong"), width=2)
        if on:
            cv.dot(mx + 52, yy + 33, "accent", 5)
        cv.text(mx + 76, yy + 8, lab, 20, "text", bold=True, box=26)
        cv.text(mx + 76, yy + 34, sub, 16, "text2", box=24, w=mw - 150)
    by = my + mh - 68
    cv.text(mx + 28, by, "Échap : annuler · Entrée ne supprime pas", 16, "muted", box=40)
    button(cv, mx + mw - 28 - 170, by, 170, 40, "SUPPRIMER", "danger", 17)
    button(cv, mx + mw - 28 - 170 - 8 - 150, by, 150, 40, "ANNULER", "hover", 17)
    return cv.img


def compact_eden():
    """Variante Eden : fenêtre plus petite (62 % x 72 %), deux colonnes, schéma sans image (pas de texture dans l'éditeur)."""
    cv = Cv(1920, 1080)
    # rappel du cadre d'Eden (schématique) pour montrer la place laissée
    cv.rect(0, 0, 1920, 34, "bg1", 0.9)
    cv.text(16, 0, "Éditeur Eden (schéma)", 16, "muted", box=34)
    cv.rect(0, 34, 300, 1046, "bg1", 0.55)
    cv.rect(1620, 34, 300, 1046, "bg1", 0.55)
    X, Y, W, H = 364, 150, 1192, 780
    cv.rect(X - 1, Y - 1, W + 2, H + 2, "bg0", border="lineStrong")
    cv.rect(X, Y, W, 56, "bg1")
    cv.rect(X, Y + 56, W, 2, "accent")
    cv.text(X + 18, Y, "ÉCRANS DU TOC", 26, "text", bold=True, box=56)
    combo(cv, X + 200, Y + 11, 270, 34, "SALLE TOC BRAVO · 9 écrans", 18)
    edit(cv, X + 480, Y + 11, 300, 34, "Chercher un écran, une source…", placeholder=True, fs=17)
    button(cv, X + W - 14 - 100, Y + 11, 100, 34, "FERMER", "normal", 15)
    button(cv, X + W - 14 - 100 - 8 - 96, Y + 11, 96, 34, "SALLES", "normal", 15)
    by0 = Y + 58
    bh = H - 58 - 56
    rw = 300
    cv.rect(X, by0, rw, bh, "bg1")
    cv.rect(X + rw, by0, 1, bh, "line")
    rh = 30
    for n, (ind, arrow, lab, tok, right, pw) in enumerate(TREE[:15]):
        yy = by0 + 10 + n * rh
        on = n == 4
        if on:
            cv.rect(X + 6, yy, rw - 12, rh - 1, "accentDim")
            cv.rect(X + 6, yy, 3, rh - 1, "accent")
        ix = X + 16 + ind * 16
        if arrow:
            cv.tri(ix + 4, yy + rh / 2, "text2", arrow, 4.5)
        ix += 16
        if tok:
            cv.rect(ix + 1, yy + rh / 2 - 4, 8, 8, "lineStrong" if pw != "off" else "line")
            ix += 18
        if right:
            cv.text(X + rw - 14, yy, right if right not in ("figée", "loin", "veille") else "", 15, "muted", anchor="r", box=rh)
        cv.text(ix, yy, lab, 17, "accent" if on else "text", bold=(ind == 0 or on), box=rh, w=X + rw - ix - 80)
    fy = by0 + bh - 50
    cv.rect(X + 12, fy, rw - 24, 1, "line")
    b3 = (rw - 24 - 12) / 3
    for n, lab in enumerate(("AJOUTER", "DUPLIQUER", "SUPPRIMER")):
        button(cv, X + 12 + n * (b3 + 6), fy + 9, b3, 32, lab, "normal", 14)
    # colonne de droite : schéma compact puis inspecteur
    cx, cw = X + rw + 1, W - rw - 1
    cv.rect(cx, by0, cw, bh, "bg1")
    px = cx + 20
    pw_ = cw - 40
    cv.text(px, by0 + 12, "MUR INCURVÉ · dalle 2 · Carte et flux", 24, "text", bold=True, box=30)
    cv.text(px + pw_, by0 + 12, "enregistré dans la mission", 16, "muted", anchor="r", box=30)
    ty = by0 + 52
    tw_ = (pw_ - 10) / 2
    for n, lab in enumerate(("1 · ESCOUADE ADAM", "2 · CARTE ET FLUX")):
        on = n == 1
        cv.rect(px + n * (tw_ + 10), ty, tw_, 58, "accentDim" if on else "bg2", border="accent" if on else "lineStrong", bw=2 if on else 1)
        cv.text(px + n * (tw_ + 10) + tw_ / 2, ty, lab, 19, "accent" if on else "text", bold=True, anchor="m", box=58)
    ty += 72
    ws = [cv.tw(t, 16, bold=True) + 20 for t in TABS]
    k = pw_ / sum(ws)
    tx = px
    cv.rect(px, ty + 34, pw_, 1, "line")
    for n, (t, tw2) in enumerate(zip(TABS, ws)):
        tw2 *= k
        if n == 0:
            cv.rect(tx, ty, tw2, 34, "bg2")
            cv.rect(tx, ty + 32, tw2, 3, "accent")
        cv.text(tx + tw2 / 2, ty, t, 16, "accent" if n == 0 else "text2", bold=True, anchor="m", box=34)
        tx += tw2
    ty += 48
    lh = 33
    cwc = 180
    lst_h = by0 + bh - 96 - ty
    cv.rect(px, ty, cwc, lst_h, "bg0", border="line")
    for n, (lab, cnt) in enumerate(CATS):
        yy = ty + 2 + n * lh
        if n == 0:
            cv.rect(px + 1, yy, cwc - 2, lh, "accentDim")
            cv.rect(px + 1, yy, 3, lh, "accent")
        cv.text(px + 12, yy, lab, 17, "accent" if n == 0 else "text", bold=True, box=lh)
        cv.text(px + cwc - 10, yy, str(cnt), 15, "muted", mono=True, anchor="r", box=lh)
    lx, lw = px + cwc + 8, pw_ - cwc - 8
    cv.rect(lx, ty, lw, lst_h, "bg0", border="line")
    cols = [("", 26, "l", False), ("Source", lw - 2 - 26 - 220, "l", False), ("Aussi montrée sur", 220, "l", False)]
    rows = [[("dot", "accent"), ("Carte et flux", "accent"), ("MUR NORD · 1", "text2")], [("dot", "accent"), "Carte seule", ""], [("dot", "accent"), "Situation du poste", ("POSTE 1 · 1", "text2")]]
    table(cv, lx + 1, ty + 4, lw - 2, cols, rows, rh=33, sel=0)
    hy = by0 + bh - 84
    cv.text(px, hy, "Appliquer à", 18, "text2", box=32)
    toolbox(cv, px + 110, hy, 470, 32, ["Cette dalle", "Sélection", "Tout l'écran", "La salle"], 0, 15)
    cv.text(px, hy + 42, "Carte et flux : carte de situation et derniers comptes rendus. Exemple : dalle centrale du mur.", 16, "muted", box=24, w=pw_)
    ay = Y + H - 56
    cv.rect(X, ay, W, 56, "bg1")
    cv.rect(X, ay, W, 1, "lineStrong")
    cv.dot(X + 26, ay + 28, "accent", 5)
    cv.text(X + 40, ay, "Appliqué à 1 dalle · écrit dans les attributs de l'objet", 18, "accent", box=56)
    bx = X + W - 14
    for lab, bw, kind in (("APPLIQUER", 160, "off"), ("ANNULER", 110, "off"), ("COPIER VERS…", 140, "normal"), ("RÉINITIALISER", 140, "ghost")):
        bx -= bw
        button(cv, bx, ay + 11, bw, 34, lab, kind, 16)
        bx -= 8
    return cv.img


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    shots = {"vue_generale": vue_generale, "source_picker": source_picker, "vue_zoom_suivi": vue_zoom_suivi, "vue_satellite": vue_satellite, "texte": texte,
             "alimentation_dispositions": alimentation_dispositions, "alimentation": alimentation, "routage": routage, "salle_suppression": salle_suppression,
             "compact_eden": compact_eden, "1366": lambda: vue_generale(1366, 768)}
    only = [a for a in sys.argv[2:]]
    for name, fn in shots.items():
        if only and name not in only:
            continue
        fn().save(OUT / f"{name}.png")
        print("écrit", OUT / f"{name}.png")


if __name__ == "__main__":
    main()
