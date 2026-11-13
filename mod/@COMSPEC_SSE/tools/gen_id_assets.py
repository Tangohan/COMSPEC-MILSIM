#!/usr/bin/env python3
"""Visuels du contrôle d'identité (addon identity) : gabarits de documents FICTIFS, icônes, silhouette, filtres, app ATAK.

Tout est dessiné ici (Pillow, numpy) : ni photo, ni image tierce, ni armoiries ni motif officiel. Rendu inspiré des
documents réels (ICAO 9303, cartes ID-1 européennes) : bandeau fin de la couleur du pays, emblème inventé (étoile dans un
anneau + code), fond pâle à guilloches fines, puce à contacts, zone MRZ blanc cassé. Chaque document reste explicitement
un document de jeu : pastille « SIMULATION » dans le bandeau et mention en pied, pour TOUS les pays.

Pays imaginaires (codes hors ISO : ALT, TNA, LVN, MLD) : nom peint dans le bandeau.
Pays réels stylisés : trois gabarits GÉNÉRIQUES par zone de style (EU bordeaux, AM bleu, OT vert), sans nom de pays
(posé en jeu), bandeau « SIMULATION — DOCUMENT DE JEU ».
Les champs (nom, dates, MRZ…) ne sont PAS peints : seules les ÉTIQUETTES le sont, aux coordonnées de LAYOUT (fractions
du document : x sur la largeur, y sur la hauteur) ; la valeur est posée en jeu à y + 0,04 (CfgComspecDocuments.hpp,
fn_idViewerOnLoad). Sous chaque étiquette, ~0,075 de hauteur reste libre de tout trait.

  python tools/gen_id_assets.py            # écrit addons/identity/data/*.paa (HEMTT, sinon paa_dxt.py de l'ATAK)
  python tools/gen_id_assets.py --png DIR  # garde aussi les PNG sources dans DIR
  python tools/gen_id_assets.py --zones    # seulement les gabarits des zones de style (EU, AM, OT)
  python tools/gen_id_assets.py --military # seulement les cartes militaires et leur icône

Gabarits 1024 x 512 (étirés à l'affichage au format réel : ID-1 1,585, TD3 1,42), dessinés au format réel en 2x puis
réduits (LANCZOS) ; icônes 256, filtres 256, app 128. Côtés en puissances de 2, DXT.

API réutilisable par une extension (voir extensions/example_documents/tools/gen_textures.py) :
  Doc(ratio, bg)                      canvas de travail ; doc.X(fx), doc.Y(fy), doc.layer(), doc.blend(), doc.finish()
  guilloche(doc, col, seed, alpha, accent, rosettes)   emblem(doc, cx, cy, r, col, code, star, alpha)
  header(doc, col, name, title, code, band, emblem_at, pill, edoc, stripe)    labels(doc, items, col, size)
  photo_frame(doc, box, col)   chip(doc, box)   signature(doc, x0, x1, y, col)   mrz_zone(doc, y0, y1)
  mention(doc, y, align, col)  footer(doc, y, col)   stamp(doc, cx, cy, r, text, sub)   paper(doc, col)
  render(spec) -> Image 1024 x 512 à partir d'un dict déclaratif (voir SPEC_KEYS)
  icon_card(...), icon_sheet(...) -> Image 256 x 256     to_paa(png, paa)
Dépendances : Pillow, numpy.
"""
import math
import os
import random
import shutil
import subprocess
import sys
import tempfile

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "addons", "identity", "data")
FONTS = os.path.join(os.environ.get("WINDIR", "C:\\Windows"), "Fonts")
SS = 2                 # sur-échantillonnage du dessin (anticrénelage à la réduction)
TEX = (1024, 512)      # taille des gabarits en texture

COUNTRIES = {
    # code : (nom FR, nom EN, couleur principale, couleur d'accent des guilloches)
    "ALT": ("RÉPUBLIQUE D'ALTIS", "REPUBLIC OF ALTIS", (34, 82, 158), (182, 124, 70)),
    "TNA": ("FÉDÉRATION DE TANOA", "FEDERATION OF TANOA", (28, 108, 72), (156, 94, 124)),
    "LVN": ("RÉPUBLIQUE DE LIVONIE", "REPUBLIC OF LIVONIA", (118, 32, 52), (70, 102, 152)),
    "MLD": ("RÉPUBLIQUE DE MALDEN", "REPUBLIC OF MALDEN", (160, 98, 26), (62, 110, 132)),
    # Zones de style des pays réels stylisés : aucun nom peint, gabarit commun à tous les pays de la zone.
    "EU": ("", "", (104, 26, 44), (64, 92, 150)),
    "AM": ("", "", (28, 44, 96), (160, 104, 62)),
    "OT": ("", "", (30, 76, 58), (150, 92, 72)),
}
ZONES = ("EU", "AM", "OT")
MILITARY = ((82, 94, 58), (150, 118, 70))        # kaki : principale, accent
LICENCE = ((176, 72, 104), (96, 112, 160))       # rose UE : principale, accent
GAME_MENTION = "SIMULATION — DOCUMENT DE JEU"
GAME_FOOTER = "SIMULATION — DOCUMENT DE JEU, SANS VALEUR · GAME DOCUMENT, NO VALUE"
MICROTEXT = "SIMULATION · DOCUMENT DE JEU · GAME DOCUMENT · NO VALUE · "

# Étiquettes peintes : (texte, x, y) en fraction du document ; la valeur est posée en jeu à y + 0,04 (hauteur 0,062).
LAYOUT = {
    "card_recto": [
        ("NOM / SURNAME", 0.34, 0.205), ("PRÉNOMS / GIVEN NAMES", 0.34, 0.325),
        ("SEXE / SEX", 0.34, 0.445), ("NATIONALITÉ / NATIONALITY", 0.45, 0.445), ("DATE DE NAISSANCE / DATE OF BIRTH", 0.64, 0.445),
        ("LIEU DE NAISSANCE / PLACE OF BIRTH", 0.34, 0.565),
        ("N° DU DOCUMENT / DOCUMENT No", 0.34, 0.685), ("DATE D'EXPIRATION / DATE OF EXPIRY", 0.64, 0.685),
    ],
    "card_verso": [
        ("ADRESSE / ADDRESS", 0.05, 0.06), ("AUTORITÉ DE DÉLIVRANCE / ISSUING AUTHORITY", 0.05, 0.21),
        ("DATE DE DÉLIVRANCE / DATE OF ISSUE", 0.05, 0.36), ("PROFESSION / OCCUPATION", 0.36, 0.36),
    ],
    "passport": [
        ("TYPE", 0.36, 0.135), ("CODE", 0.45, 0.135), ("N° DE PASSEPORT / PASSPORT No", 0.60, 0.135),
        ("NOM / SURNAME", 0.36, 0.245), ("PRÉNOMS / GIVEN NAMES", 0.36, 0.355),
        ("NATIONALITÉ / NATIONALITY", 0.36, 0.465), ("DATE DE NAISSANCE / DATE OF BIRTH", 0.66, 0.465),
        ("SEXE / SEX", 0.36, 0.575), ("LIEU DE NAISSANCE / PLACE OF BIRTH", 0.47, 0.575),
        ("DATE DE DÉLIVRANCE / DATE OF ISSUE", 0.36, 0.675), ("DATE D'EXPIRATION / DATE OF EXPIRY", 0.55, 0.675),
        ("AUTORITÉ / AUTHORITY", 0.74, 0.675),
    ],
    "military_recto": [
        ("NOM / SURNAME", 0.34, 0.205), ("PRÉNOMS / GIVEN NAMES", 0.34, 0.325),
        ("GRADE / RANK", 0.34, 0.445), ("DATE DE NAISSANCE / DATE OF BIRTH", 0.64, 0.445),
        ("FORCE / SERVICE", 0.34, 0.565),
        ("N° DE CARTE / CARD No", 0.34, 0.685), ("DATE D'EXPIRATION / DATE OF EXPIRY", 0.64, 0.685),
    ],
    "license_recto": [
        ("1. NOM / SURNAME", 0.34, 0.205), ("2. PRÉNOMS / GIVEN NAMES", 0.34, 0.315),
        ("3. DATE ET LIEU DE NAISSANCE / DATE AND PLACE OF BIRTH", 0.34, 0.425),
        ("4a. DÉLIVRÉ LE / ISSUED", 0.34, 0.535), ("4b. EXPIRE LE / EXPIRY", 0.58, 0.535),
        ("4c. AUTORITÉ / AUTHORITY", 0.34, 0.645), ("5. N° DU PERMIS / LICENCE No", 0.34, 0.755), ("9. CATÉGORIES", 0.70, 0.755),
    ],
}
# Proportions réelles (largeur / hauteur) : carte ID-1, page de données TD3.
RATIO = {"card_recto": 1.585, "card_verso": 1.585, "passport": 1.42, "license_recto": 1.585, "license_verso": 1.585, "military_recto": 1.585}
PHOTO_CARD = (0.045, 0.215, 0.265, 0.57)
PHOTO_PASSPORT = (0.04, 0.145, 0.275, 0.52)
CHIP_CARD = (0.835, 0.195, 0.12, 0.165)
LICENCE_CATS = ("AM", "A1", "A", "B", "C1", "C", "D", "BE")

# Clés du dict de render(spec) (toutes optionnelles sauf colors) :
SPEC_KEYS = """
  ratio       largeur / hauteur (1.585 par défaut)
  colors      {"main": (r,g,b), "accent": (r,g,b), "bg": (r,g,b), "label": (r,g,b)} ; bg et label déduits de main
  paper       "plain" (défaut) | "cream" (papier crème texturé)
  guilloche   {"seed": n, "alpha": 0.28, "rosettes": [(cx, cy, r), ...], "lattice": True, "bands": 3} | False
  watermark   (cx, cy, r, code[, alpha])        emblème en filigrane
  header      {"name": "...", "title": "...", "code": "ALT", "band": 0.155, "emblem": (cx, cy, r), "pill": "SIMULATION",
               "edoc": (x, y) | None, "stripe": (r,g,b) | None, "color": (r,g,b)}
  heading     {"text": "...", "y": 0.1, "size": 0.07, "rules": True}   titre centré avec filet double (sans bandeau)
  microtext   y | None            ligne de micro-texte (défaut : sous le bandeau)
  photo       (x, y, w, h)        chip  (x, y, w, h)        signature  (x0, x1, y[, étiquette])
  labels      [(texte, x, y), ...]   label_size  0.022
  mrz         (y0, y1)            mention  (y, "right"|"center"|"left")     footer  y | None
  stamp       (cx, cy, r, texte, sous-texte)
  extra       fonction(doc) appelée avant la réduction
  size        (1024, 512)
"""


# ----------------------------------------------------------------------------------------------------- couleurs, polices
def mix(a, b, t):
    """Mélange de deux couleurs RVB : t = 0 donne a, t = 1 donne b."""
    return tuple(int(round(a[i] + (b[i] - a[i]) * t)) for i in range(3))


def tint(col, t=0.91):
    return mix(col, (255, 255, 255), t)


def shade(col, t=0.3):
    return mix(col, (0, 0, 0), t)


def label_colour(main):
    return mix(main, (72, 76, 84), 0.55)


_FONT_CACHE = {}


def font(name, px, weight=None, width=None):
    """Police TrueType ; bahnschrift.ttf est variable (graisse 300-700, chasse 75-100)."""
    px = max(4, int(round(px)))
    key = (name, px, weight, width)
    f = _FONT_CACHE.get(key)
    if f is None:
        f = None
        for n in (name, "segoeui.ttf", "arial.ttf"):
            p = os.path.join(FONTS, n)
            if os.path.exists(p):
                f = ImageFont.truetype(p, px)
                break
        if f is None:
            f = ImageFont.load_default()
        if weight or width:
            try:
                f.set_variation_by_axes([weight or 400, width or 100])
            except Exception:
                pass
        _FONT_CACHE[key] = f
    return f


def fnt_name(px):       # nom du pays, titres : Bahnschrift gras
    return font("bahnschrift.ttf", px, 700, 92)


def fnt_title(px):      # sous-titres bilingues
    return font("bahnschrift.ttf", px, 400, 92)


def fnt_label_fr(px):   # étiquettes FR : demi-gras semi-condensé
    return font("bahnschrift.ttf", px, 620, 84)


def fnt_label_en(px):   # étiquettes EN : maigre semi-condensé
    return font("bahnschrift.ttf", px, 400, 84)


def fnt_small(px):
    return font("bahnschrift.ttf", px, 400, 88)


def text_w(f, t):
    return f.getlength(t)


# ------------------------------------------------------------------------------------------------------------- canvas
class Doc:
    """Canvas de travail d'un document, au format réel, sur-échantillonné SS fois. Coordonnées en fractions."""

    def __init__(self, ratio=1.585, bg=(245, 246, 248), ss=SS, width=TEX[0]):
        self.ratio = ratio
        self.ss = ss
        self.W = width * ss
        self.H = int(round(self.W / ratio))
        self.bg = tuple(bg)
        self.img = Image.new("RGBA", (self.W, self.H), self.bg + (255,))
        self.d = ImageDraw.Draw(self.img)

    def X(self, fx):
        return fx * self.W

    def Y(self, fy):
        return fy * self.H

    def box(self, fx, fy, fw, fh):
        return (self.X(fx), self.Y(fy), self.X(fx + fw), self.Y(fy + fh))

    def px(self, fh):
        """Taille en pixels d'une fraction de la hauteur."""
        return max(1, int(round(fh * self.H)))

    def layer(self):
        return Image.new("RGBA", (self.W, self.H), (0, 0, 0, 0))

    def blend(self, layer, opacity=1.0):
        """Compose un calque RGBA (opacité globale) sur le document."""
        if opacity < 1.0:
            layer.putalpha(layer.getchannel("A").point(lambda v: int(v * opacity)))
        self.img.alpha_composite(layer)
        self.d = ImageDraw.Draw(self.img)

    def finish(self, size=TEX):
        return self.img.resize(size, Image.LANCZOS)


# ------------------------------------------------------------------------------------------------------- fond, guilloches
def paper(doc, col, fibre=5.0, cloud=4.0, vignette=0.05, seed=7):
    """Papier légèrement texturé (grain, fibres, nuages) de la couleur donnée."""
    r = np.random.default_rng(seed)
    W, H = doc.W, doc.H
    a = np.zeros((H, W, 3), np.float32) + np.array(col, np.float32)

    def noise(scale, amp):
        n = r.normal(0, 1, (H // scale + 2, W // scale + 2)).astype(np.float32)
        im = Image.fromarray(((n - n.min()) / (np.ptp(n) + 1e-6) * 255).astype(np.uint8)).resize((W, H), Image.BICUBIC)
        return (np.asarray(im, np.float32) / 255 - 0.5) * 2 * amp

    g = noise(2, fibre * 0.5) + noise(6, fibre) + noise(96, cloud)
    a += g[..., None]
    # Image RVB : le mode de dessin « RGBA » mélange alors l'encre (sur une image RGBA elle serait écrite telle quelle).
    img = Image.fromarray(np.clip(a, 0, 255).astype(np.uint8))
    d = ImageDraw.Draw(img, "RGBA")
    # Fibres : courts traits à peine plus clairs ou plus sombres que le papier.
    for _ in range(700):
        x, y = r.uniform(0, W), r.uniform(0, H)
        ang = r.uniform(0, math.pi)
        ln = r.uniform(2, 6) * doc.ss
        v = int(r.choice([255, 90]))
        d.line((x, y, x + math.cos(ang) * ln, y + math.sin(ang) * ln), fill=(v, v, v, int(r.uniform(4, 9))), width=doc.ss)
    a = np.asarray(img, np.float32)[..., :3]
    yy, xx = np.mgrid[0:H, 0:W]
    xx = xx / (W - 1) - 0.5
    yy = yy / (H - 1) - 0.5
    a *= (1 - vignette * (xx ** 2 + yy ** 2) * 3)[..., None]
    doc.img = Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)).convert("RGBA")
    doc.d = ImageDraw.Draw(doc.img)


def _hypotrochoid(cx, cy, rad, R, r, dd, rot=0.0, steps=240):
    """Hypotrochoïde fermée (R, r entiers : R lobes, fermée après r / pgcd tours) ramenée au rayon `rad`."""
    turns = r // math.gcd(R, r)
    k = (R - r) / r
    norm = (R - r) + dd
    ca, sa = math.cos(rot), math.sin(rot)
    pts = []
    for i in range(steps * turns + 1):
        t = 2 * math.pi * i / steps
        x = ((R - r) * math.cos(t) + dd * math.cos(k * t)) / norm
        y = ((R - r) * math.sin(t) - dd * math.sin(k * t)) / norm
        pts.append((cx + rad * (x * ca - y * sa), cy + rad * (x * sa + y * ca)))
    return pts


def rosette(d, cx, cy, rad, col, width=1, seed=0):
    """Rosace de sécurité : anneaux concentriques de cercles ondulés entrelacés ρ(θ) = r0 + a·sin(nθ + φk), couronne
    dentelée, hypotrochoïde fine au cœur."""
    rnd = random.Random(seed)
    rings = [(0.90, 0.07, 14, 12), (0.66, 0.08, 9, 11), (0.44, 0.065, 6, 9), (0.21, 0.05, 4, 7)]
    for r0, a, n, nl in rings:
        ph0 = rnd.uniform(0, 2 * math.pi)
        for k in range(nl):
            pk = ph0 + k * math.pi / nl
            pts = []
            for i in range(361):
                t = math.radians(i)
                rho = rad * (r0 + a * math.sin(n * t + pk) + a * 0.35 * math.sin(2 * n * t - 2 * pk))
                pts.append((cx + rho * math.cos(t), cy + rho * math.sin(t)))
            d.line(pts, fill=col, width=width)
    d.line(_hypotrochoid(cx, cy, rad * 0.3, 7, 3, 1.6, rot=rnd.uniform(0, math.pi)), fill=col, width=width)
    for k in (1.0, 0.985):
        rr = rad * k
        d.ellipse((cx - rr, cy - rr, cx + rr, cy + rr), outline=col, width=width)
    # Couronne de petits arcs (dentelle).
    n = 72
    for i in range(n):
        a0 = 360.0 * i / n
        rr = rad * 1.035
        d.arc((cx - rr, cy - rr, cx + rr, cy + rr), a0, a0 + 360.0 / n * 0.55, fill=col, width=width)


def guilloche(doc, col, seed=1, alpha=0.28, accent=None, rosettes=(), lattice=True, bands=3, y_range=(0.17, 0.95)):
    """Fond de sécurité : treillis fin, rubans de sinusoïdes entrelacées, rosaces. Traits de 1 px au rendu final, à
    `alpha` d'opacité. `accent` colore un ruban sur deux et la couronne des rosaces."""
    rnd = random.Random(seed)
    W, H, lw = doc.W, doc.H, doc.ss
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    c1 = tuple(col) + (255,)
    c2 = (tuple(accent) if accent else tuple(col)) + (255,)
    # 1. Treillis : deux familles de sinusoïdes fines, l'une horizontale, l'autre verticale, plus pâles.
    if lattice:
        step = H * 0.028
        for k in range(int(H / step) + 2):
            y0 = k * step
            pts = [(x, y0 + H * 0.006 * math.sin(x / (W * 0.05) * 2 * math.pi + k * 0.8)) for x in range(0, W + 8, 8)]
            d.line(pts, fill=col[:3] + (92,), width=lw)
        stepx = W * 0.028
        for k in range(int(W / stepx) + 2):
            x0 = k * stepx
            pts = [(x0 + W * 0.004 * math.sin(y / (H * 0.09) * 2 * math.pi + k * 0.6), y) for y in range(0, H + 8, 8)]
            d.line(pts, fill=col[:3] + (66,), width=lw)
    # 2. Rubans : familles de courbes y = yc + A(x)·sin(2πx/λ + φk) + B·sin(2πx/λ2 + ψk) entrelacées.
    y_lo, y_hi = y_range
    for b in range(bands):
        yc = H * (y_lo + (y_hi - y_lo) * (b + 0.5) / bands + rnd.uniform(-0.02, 0.02))
        amp = H * rnd.uniform(0.028, 0.042)
        lam = W * rnd.uniform(0.2, 0.34)
        lam2 = lam / rnd.choice((2, 3))
        lam3 = W * rnd.uniform(0.6, 1.1)
        ph = rnd.uniform(0, 2 * math.pi)
        nl = 22
        colb = c1 if b % 2 == 0 else c2
        for k in range(nl):
            pk = k * math.pi / nl
            pts = []
            for x in range(0, W + 6, 6):
                env = 0.6 + 0.4 * math.sin(2 * math.pi * x / lam3 + pk * 2)
                y = yc + amp * env * math.sin(2 * math.pi * x / lam + ph + pk) + amp * 0.35 * math.sin(2 * math.pi * x / lam2 - pk * 2 + ph)
                pts.append((x, y))
            d.line(pts, fill=colb, width=lw)
    # 3. Rosaces.
    for i, (cx, cy, r) in enumerate(rosettes):
        rosette(d, doc.X(cx), doc.Y(cy), r * H, c2 if i % 2 else c1, width=lw, seed=seed * 7 + i)
    doc.blend(lay, alpha)


def microtext(doc, y, col, alpha=0.4, size=0.011):
    """Ligne de micro-texte répétée sur toute la largeur (filigrane typographique)."""
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    f = fnt_small(doc.px(size))
    t = MICROTEXT
    while text_w(f, t) < doc.W * 1.05:
        t += MICROTEXT
    d.text((doc.X(0.03), doc.Y(y)), t, fill=tuple(col) + (255,), font=f, anchor="lm")
    doc.blend(lay, alpha)


# -------------------------------------------------------------------------------------------------------- éléments
def emblem(doc, cx, cy, r, col, code="SIM", star=True, alpha=255, width=None):
    """Emblème inventé : étoile à cinq branches dans un double anneau, code en dessous. r en fraction de la hauteur."""
    X, Y, R = doc.X(cx), doc.Y(cy), r * doc.H
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    c = tuple(col) + (255,)
    w = width or max(doc.ss, int(round(R * 0.085)))
    d.ellipse((X - R, Y - R, X + R, Y + R), outline=c, width=w)
    r2 = R * 0.84
    d.ellipse((X - r2, Y - r2, X + r2, Y + r2), outline=c, width=max(1, w // 2))
    if star:
        pts = []
        sy = Y - R * 0.14
        for i in range(10):
            a = -math.pi / 2 + i * math.pi / 5
            rr = R * (0.50 if i % 2 == 0 else 0.21)
            pts.append((X + rr * math.cos(a), sy + rr * math.sin(a)))
        d.polygon(pts, fill=c)
        f = font("bahnschrift.ttf", R * 0.30, 700, 90)
        d.text((X, Y + R * 0.56), code, fill=c, font=f, anchor="mm")
    else:
        f = font("bahnschrift.ttf", R * 0.42, 700, 85)
        d.text((X, Y), code, fill=c, font=f, anchor="mm")
    doc.blend(lay, alpha / 255.0)


def pill(doc, right, cy, text, col=(255, 255, 255), size=0.028, alpha=0.9):
    """Pastille arrondie à bord fin, texte en capitales, alignée sur son bord droit."""
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    f = fnt_small(doc.px(size))
    tw = text_w(f, text)
    h = doc.px(size * 1.75)
    padx = doc.px(size * 0.9)
    x1 = doc.X(right)
    x0 = x1 - tw - 2 * padx
    y = doc.Y(cy)
    c = tuple(col) + (255,)
    d.rounded_rectangle((x0, y - h / 2, x1, y + h / 2), h / 2, outline=c, width=max(1, doc.ss), fill=tuple(col) + (28,))
    d.text(((x0 + x1) / 2, y + doc.px(0.002)), text, fill=c, font=f, anchor="mm")
    doc.blend(lay, alpha)


def edoc_symbol(doc, cx, cy, s, col=(255, 255, 255)):
    """Symbole « document électronique » (ICAO 9303) : rectangle, cercle et deux traits, dessiné à la main."""
    X, Y, S = doc.X(cx), doc.Y(cy), s * doc.H
    w = max(doc.ss, int(S * 0.11))
    c = tuple(col) + (255,)
    d = doc.d
    d.rounded_rectangle((X - S, Y - S * 0.64, X + S, Y + S * 0.64), S * 0.12, outline=c, width=w)
    d.ellipse((X - S * 0.34, Y - S * 0.34, X + S * 0.34, Y + S * 0.34), outline=c, width=w)
    d.line((X - S, Y, X - S * 0.34, Y), fill=c, width=w)
    d.line((X + S * 0.34, Y, X + S, Y), fill=c, width=w)


def header(doc, col, name, title, code="SIM", band=0.155, emblem_at=(0.065, 0.078, 0.058), pill_text="SIMULATION",
           edoc=None, stripe=None, name_x=0.13, pill_right=0.965):
    """Bandeau fin de la couleur du pays : emblème, nom du pays, titre bilingue, pastille SIMULATION, symbole e-document."""
    W, H = doc.W, doc.H
    d = doc.d
    hb = doc.Y(band)
    d.rectangle((0, 0, W, hb), fill=tuple(col) + (255,))
    # Léger dégradé (plus sombre en bas) pour donner du relief au bandeau.
    grad = Image.new("RGBA", (1, 64))
    for i in range(64):
        grad.putpixel((0, i), (0, 0, 0, int(26 * i / 63)))
    doc.img.alpha_composite(grad.resize((W, int(hb)), Image.BILINEAR))
    doc.d = d = ImageDraw.Draw(doc.img)
    d.line((0, hb, W, hb), fill=shade(col, 0.35) + (255,), width=doc.ss)
    if stripe:
        d.rectangle((0, hb, W, hb + doc.px(0.012)), fill=tuple(stripe) + (255,))
    if emblem_at:
        emblem(doc, emblem_at[0], emblem_at[1], emblem_at[2], (255, 255, 255), code)
    if name:
        d.text((doc.X(name_x), hb * 0.34), name, fill=(255, 255, 255, 255), font=fnt_name(hb * 0.36), anchor="lm")
    if title:
        d.text((doc.X(name_x), hb * 0.73), title, fill=(255, 255, 255, 222), font=fnt_title(hb * 0.21), anchor="lm")
    if pill_text:
        pill(doc, pill_right, band * 0.5, pill_text, size=band * 0.16)
    if edoc:
        edoc_symbol(doc, edoc[0], edoc[1], band * 0.24)


def heading(doc, text, col, y=0.1, size=0.07, rules=True, x0=0.08, x1=0.92):
    """Titre centré sans bandeau (feuille), encadré de filets doubles."""
    d = doc.d
    c = tuple(col) + (255,)
    f = fnt_name(doc.px(size))
    d.text((doc.X(0.5), doc.Y(y)), text, fill=c, font=f, anchor="mm")
    if rules:
        for yy in (doc.Y(y - size * 0.75), doc.Y(y + size * 0.75)):
            d.line((doc.X(x0), yy, doc.X(x1), yy), fill=c, width=doc.ss * 2)
            d.line((doc.X(x0), yy + doc.ss * 4, doc.X(x1), yy + doc.ss * 4), fill=c, width=doc.ss)


def photo_frame(doc, box, col, radius=0.018, fill=(234, 236, 239)):
    """Cadre photo à coins légèrement arrondis sur fond gris très clair, liseré fin."""
    x, y, w, h = box
    b = doc.box(x, y, w, h)
    r = radius * doc.H
    d = doc.d
    d.rounded_rectangle(b, r, fill=tuple(fill) + (255,), outline=mix(col, fill, 0.45) + (255,), width=doc.ss)
    # Liseré intérieur très pâle (effet de pelliculage).
    inset = doc.ss * 3
    d.rounded_rectangle((b[0] + inset, b[1] + inset, b[2] - inset, b[3] - inset), r * 0.8, outline=mix(col, fill, 0.8) + (255,), width=1)


def chip(doc, box):
    """Puce dorée à huit contacts (ISO 7816) : dégradé or/bronze, coins arrondis, sillons bronze."""
    x, y, w, h = box
    X0, Y0, X1, Y1 = doc.box(x, y, w, h)
    cw, ch = X1 - X0, Y1 - Y0
    r = min(cw, ch) * 0.16
    # Dégradé or : vertical clair → bronze, plus une diagonale de reflet.
    g = np.zeros((int(ch), int(cw), 3), np.float32)
    yy, xx = np.mgrid[0:int(ch), 0:int(cw)]
    t = yy / max(1, ch - 1)
    s = ((xx / max(1, cw - 1)) + t) / 2
    top, bot = np.array((240, 210, 128), np.float32), np.array((186, 138, 62), np.float32)
    g[:] = top[None, None, :] * (1 - t[..., None]) + bot[None, None, :] * t[..., None]
    g += (np.sin(s * math.pi * 2.2 + 0.6) * 14)[..., None]
    gold = Image.fromarray(np.clip(g, 0, 255).astype(np.uint8)).convert("RGBA")
    mask = Image.new("L", gold.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, cw - 1, ch - 1), r, fill=255)
    gold.putalpha(mask)
    doc.img.alpha_composite(gold, (int(X0), int(Y0)))
    doc.d = d = ImageDraw.Draw(doc.img)
    bronze = (128, 90, 40, 255)
    lw = max(doc.ss, int(ch * 0.02))
    d.rounded_rectangle((X0, Y0, X1, Y1), r, outline=bronze, width=lw)
    # Contacts : deux colonnes de quatre plages, zone centrale.
    xa, xb = X0 + cw * 0.30, X1 - cw * 0.30
    d.line((xa, Y0, xa, Y1), fill=bronze, width=lw)
    d.line((xb, Y0, xb, Y1), fill=bronze, width=lw)
    for k in (0.25, 0.5, 0.75):
        yy = Y0 + ch * k
        d.line((X0, yy, xa, yy), fill=bronze, width=lw)
        d.line((xb, yy, X1, yy), fill=bronze, width=lw)
    d.rounded_rectangle((xa, Y0 + ch * 0.25, xb, Y1 - ch * 0.25), r * 0.6, outline=bronze, width=lw)
    d.line((X0 + cw * 0.5, Y0, X0 + cw * 0.5, Y0 + ch * 0.25), fill=bronze, width=lw)
    d.line((X0 + cw * 0.5, Y1 - ch * 0.25, X0 + cw * 0.5, Y1), fill=bronze, width=lw)
    # Reflet : ligne claire en haut à gauche.
    d.line((X0 + r, Y0 + lw, X1 - r, Y0 + lw), fill=(255, 245, 200, 110), width=lw)


def labels(doc, items, col, size=0.022, right=0.965, gap=0.012, min_size=0.015):
    """Étiquettes de champs : partie FR en demi-gras, partie EN (après « / ») en maigre plus pâle ; capitales petites.
    Chaque étiquette est réduite si elle déborde sur l'étiquette suivante de la même ligne (ou la marge droite)."""
    d = doc.d
    en_col = mix(col, doc.bg, 0.38)
    for txt, x, y in items:
        avail = right - x
        for t2, x2, y2 in items:
            if x2 > x and abs(y2 - y) < 0.02:
                avail = min(avail, x2 - x - gap)
        fr, sep, en = txt.partition(" / ")
        px = doc.px(size)
        while True:
            ff, fe = fnt_label_fr(px), fnt_label_en(px)
            wfr = text_w(ff, fr)
            wen = text_w(fe, sep + en) if sep else 0
            if wfr + wen <= doc.X(avail) or px <= doc.px(min_size):
                break
            px -= 1
        X, Y = doc.X(x), doc.Y(y)
        d.text((X, Y), fr, fill=tuple(col) + (255,), font=ff, anchor="la")
        if sep:
            d.text((X + wfr, Y), sep + en, fill=tuple(en_col) + (255,), font=fe, anchor="la")


def signature(doc, x0, x1, y, col, label="SIGNATURE"):
    """Ligne de signature fine avec son étiquette en dessous."""
    d = doc.d
    d.line((doc.X(x0), doc.Y(y), doc.X(x1), doc.Y(y)), fill=mix(col, doc.bg, 0.35) + (255,), width=doc.ss)
    d.text((doc.X(x0), doc.Y(y) + doc.px(0.008)), label, fill=tuple(mix(col, doc.bg, 0.2)) + (255,), font=fnt_label_en(doc.px(0.017)), anchor="la")


def mrz_zone(doc, y0, y1, fill=(253, 253, 249), opacity=228):
    """Zone de lecture automatique : voile blanc cassé presque opaque (un soupçon du treillis reste visible) ; les lignes
    sont posées en jeu, police à chasse fixe."""
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    d.rectangle((0, doc.Y(y0), doc.W, doc.Y(y1)), fill=tuple(fill) + (opacity,))
    d.line((0, doc.Y(y0), doc.W, doc.Y(y0)), fill=(205, 205, 198, 255), width=doc.ss)
    doc.blend(lay)


def mention(doc, y, align="right", col=(110, 114, 122), text=GAME_MENTION, size=0.021, margin=0.04):
    f = fnt_small(doc.px(size))
    x, anchor = {"right": (doc.X(1 - margin), "rm"), "left": (doc.X(margin), "lm"), "center": (doc.X(0.5), "mm")}[align]
    doc.d.text((x, doc.Y(y)), text, fill=tuple(col) + (255,), font=f, anchor=anchor)


def footer(doc, y=0.965, col=(112, 116, 124), text=GAME_FOOTER, size=0.019):
    """Pied discret, centré : document de jeu sans valeur (FR · EN)."""
    mention(doc, y, "center", col, text, size)


def _arc_text(lay, cx, cy, r, text, f, fill, a0, a1, inward=False):
    """Texte le long d'un arc (degrés, sens horaire en coordonnées image) ; glyphes tournés un par un."""
    n = len(text)
    for i, ch in enumerate(text):
        a = a0 + (a1 - a0) * (i + 0.5) / n
        rad = math.radians(a)
        cw, chh = int(text_w(f, ch)) + 8, int(f.size * 1.5)
        g = Image.new("RGBA", (max(cw, 4), chh), (0, 0, 0, 0))
        ImageDraw.Draw(g).text((cw / 2, chh / 2), ch, fill=fill, font=f, anchor="mm")
        g = g.rotate(-(a + 90) if not inward else -(a - 90), resample=Image.BICUBIC, expand=True)
        px, py = cx + r * math.cos(rad), cy + r * math.sin(rad)
        lay.alpha_composite(g, (int(px - g.width / 2), int(py - g.height / 2)))


def stamp(doc, cx, cy, r, text="SIMULATION", sub="DOCUMENT DE JEU", col=(176, 40, 44), alpha=0.62, angle=-12, seed=3):
    """Tampon rond à l'encre rouge délavée : double anneau, étoile, texte sur l'arc, encrage inégal, légèrement tourné."""
    R = r * doc.H
    s = int(R * 2.4)
    lay = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    c = tuple(col) + (255,)
    cxx = cyy = s / 2
    w = max(doc.ss, int(R * 0.05))
    d.ellipse((cxx - R, cyy - R, cxx + R, cyy + R), outline=c, width=w)
    r2 = R * 0.9
    d.ellipse((cxx - r2, cyy - r2, cxx + r2, cyy + r2), outline=c, width=max(1, w // 2))
    r3 = R * 0.58
    d.ellipse((cxx - r3, cyy - r3, cxx + r3, cyy + r3), outline=c, width=max(1, w // 2))
    pts = []
    for i in range(10):
        a = -math.pi / 2 + i * math.pi / 5
        rr = R * (0.34 if i % 2 == 0 else 0.14)
        pts.append((cxx + rr * math.cos(a), cyy + rr * math.sin(a)))
    d.polygon(pts, fill=c)
    f = font("bahnschrift.ttf", R * 0.22, 700, 90)
    _arc_text(lay, cxx, cyy, R * 0.74, text, f, c, -180 - 20, -20 + 20 - 40, inward=False)
    if sub:
        f2 = font("bahnschrift.ttf", R * 0.15, 600, 90)
        _arc_text(lay, cxx, cyy, R * 0.74, sub, f2, c, 150, 30, inward=True)
    # Encrage inégal : masque de bruit lissé.
    rnd = np.random.default_rng(seed)
    n = rnd.normal(0, 1, (s // 6 + 2, s // 6 + 2)).astype(np.float32)
    nm = Image.fromarray(((n - n.min()) / (np.ptp(n) + 1e-6) * 255).astype(np.uint8)).resize((s, s), Image.BICUBIC)
    nm = np.asarray(nm, np.float32) / 255
    fine = rnd.uniform(0, 1, (s, s)).astype(np.float32)
    m = np.clip(0.45 + 0.75 * nm - 0.25 * fine, 0, 1)
    a = np.asarray(lay.getchannel("A"), np.float32) * m
    lay.putalpha(Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)))
    lay = lay.rotate(angle, resample=Image.BICUBIC)
    lay = lay.filter(ImageFilter.GaussianBlur(doc.ss * 0.5))
    full = doc.layer()
    full.alpha_composite(lay, (int(doc.X(cx) - s / 2), int(doc.Y(cy) - s / 2)))
    doc.blend(full, alpha)


# ------------------------------------------------------------------------------------------------------- render(spec)
def render(spec):
    """Dessine un document complet à partir d'un dict déclaratif (clés : SPEC_KEYS) ; retourne l'image en taille texture."""
    colors = spec["colors"]
    main = tuple(colors["main"])
    accent = colors.get("accent")
    bg = tuple(colors.get("bg") or tint(main, 0.91))
    lab = tuple(colors.get("label") or label_colour(main))
    doc = Doc(spec.get("ratio", 1.585), bg)
    if spec.get("paper") == "cream":
        paper(doc, bg)
    g = spec.get("guilloche", {})
    if g is not False:
        guilloche(doc, main, seed=g.get("seed", 1), alpha=g.get("alpha", 0.28), accent=accent,
                  rosettes=g.get("rosettes", ()), lattice=g.get("lattice", True), bands=g.get("bands", 3), y_range=g.get("y_range", (0.17, 0.95)))
    wm = spec.get("watermark")
    if wm:
        emblem(doc, wm[0], wm[1], wm[2], main, wm[3], alpha=wm[4] if len(wm) > 4 else 16)
    hd = spec.get("header")
    band = 0.0
    if hd:
        band = hd.get("band", 0.155)
        header(doc, hd.get("color", main), hd.get("name", ""), hd.get("title", ""), hd.get("code", "SIM"), band,
               hd.get("emblem", (0.065, 0.078, 0.058)), hd.get("pill", "SIMULATION"), hd.get("edoc"), hd.get("stripe"),
               hd.get("name_x", 0.13), hd.get("pill_right", 0.965))
    hg = spec.get("heading")
    if hg:
        heading(doc, hg["text"], hg.get("color", main), hg.get("y", 0.1), hg.get("size", 0.07), hg.get("rules", True))
    mt = spec.get("microtext", "auto")
    if mt == "auto":
        mt = band + 0.014 + (0.012 if hd and hd.get("stripe") else 0) if hd else 0.025
    if mt:
        microtext(doc, mt, main)
    if spec.get("photo"):
        photo_frame(doc, spec["photo"], main)
    if spec.get("chip"):
        chip(doc, spec["chip"])
    labels(doc, spec.get("labels", []), lab, size=spec.get("label_size", 0.022))
    if spec.get("signature"):
        signature(doc, *spec["signature"][:3], lab, *spec["signature"][3:])
    if spec.get("mrz"):
        mrz_zone(doc, *spec["mrz"])
    mn = spec.get("mention")
    if mn:
        mention(doc, mn[0], mn[1] if len(mn) > 1 else "right")
    st = spec.get("stamp")
    if st:
        stamp(doc, *st)
    if spec.get("footer") is not None:
        footer(doc, spec["footer"])
    if spec.get("extra"):
        spec["extra"](doc)
    return doc.finish(spec.get("size", TEX))


# ------------------------------------------------------------------------------------------- documents intégrés (specs)
def _country(code):
    name_fr, name_en, main, accent = COUNTRIES[code]
    return name_fr, main, accent, ("SIM" if code in ZONES else code)


def _title(code, fr, en):
    t = f"{fr}  ·  {en}"
    return t + "  ·  " + GAME_MENTION if code in ZONES else t


def spec_card_recto(code):
    name, main, accent, emb = _country(code)
    return {
        "ratio": RATIO["card_recto"], "colors": {"main": main, "accent": accent},
        "guilloche": {"seed": 1, "rosettes": [(0.93, 0.60, 0.26), (0.18, 0.52, 0.30)]},
        "header": {"name": name, "title": _title(code, "CARTE NATIONALE D'IDENTITÉ", "IDENTITY CARD"), "code": emb},
        "photo": PHOTO_CARD, "chip": CHIP_CARD, "labels": LAYOUT["card_recto"],
        "signature": (0.34, 0.60, 0.905), "footer": 0.965,
    }


def spec_card_verso(code):
    name, main, accent, emb = _country(code)
    return {
        "ratio": RATIO["card_verso"], "colors": {"main": main, "accent": accent},
        "guilloche": {"seed": 2, "alpha": 0.22, "y_range": (0.05, 0.55), "bands": 2},
        "watermark": (0.84, 0.27, 0.16, emb, 22),
        "labels": LAYOUT["card_verso"], "mrz": (0.575, 0.96), "mention": (0.545, "right"), "footer": 0.981,
    }


def spec_passport(code):
    name, main, accent, emb = _country(code)
    return {
        "ratio": RATIO["passport"], "colors": {"main": main, "accent": accent},
        "guilloche": {"seed": 3, "rosettes": [(0.72, 0.42, 0.30), (0.18, 0.44, 0.28)], "y_range": (0.12, 0.74)},
        "header": {"name": name, "title": _title(code, "PASSEPORT", "PASSPORT"), "code": emb, "band": 0.105,
                   "emblem": (0.045, 0.052, 0.038), "edoc": (0.95, 0.052), "name_x": 0.10, "pill_right": 0.905},
        "photo": PHOTO_PASSPORT, "labels": LAYOUT["passport"],
        "mrz": (0.785, 0.965), "mention": (0.76, "center"), "footer": None,
    }


def spec_military_recto(code):
    name, _, _, emb = _country(code)
    main, accent = MILITARY
    return {
        "ratio": RATIO["military_recto"], "colors": {"main": main, "accent": accent, "bg": (236, 237, 226)},
        "guilloche": {"seed": 6, "rosettes": [(0.93, 0.60, 0.26), (0.18, 0.52, 0.30)]},
        "header": {"name": name, "title": _title(code, "CARTE MILITAIRE", "MILITARY IDENTITY CARD"), "code": emb},
        "photo": PHOTO_CARD, "chip": CHIP_CARD, "labels": LAYOUT["military_recto"],
        "signature": (0.34, 0.60, 0.905), "footer": 0.965,
    }


def spec_license_recto(code):
    name, cmain, _, emb = _country(code)
    main, accent = LICENCE
    return {
        "ratio": RATIO["license_recto"], "colors": {"main": main, "accent": accent, "bg": (251, 233, 237)},
        "guilloche": {"seed": 4, "rosettes": [(0.92, 0.50, 0.24), (0.18, 0.52, 0.30)]},
        "header": {"name": name, "title": _title(code, "PERMIS DE CONDUIRE", "DRIVING LICENCE"), "code": emb, "stripe": cmain},
        "photo": PHOTO_CARD, "labels": LAYOUT["license_recto"], "footer": 0.965,
    }


def _licence_table(doc):
    """Verso du permis : tableau des catégories (pictogrammes dessinés), lignes fines ; la ligne B reçoit les dates en jeu."""
    main, _ = LICENCE
    d = doc.d
    lab = label_colour(main)
    rule = mix(main, doc.bg, 0.55) + (255,)
    cols = [0.04, 0.22, 0.42, 0.62]
    heads = ["9. CAT.", "10. DÉLIVRÉ / ISSUED", "11. EXPIRE / EXPIRY", "12. MENTIONS / RESTRICTIONS"]
    labels(doc, [(t, x, 0.06) for x, t in zip(cols, heads)], lab, size=0.021)
    f = font("bahnschrift.ttf", doc.px(0.03), 700, 90)
    ico = tuple(mix(main, (60, 60, 70), 0.5)) + (255,)
    for i, cat in enumerate(LICENCE_CATS):
        y = 0.13 + i * 0.058
        yl = doc.Y(y - 0.008)
        d.line((doc.X(0.03), yl, doc.X(0.97), yl), fill=rule, width=doc.ss)
        d.text((doc.X(0.045), doc.Y(y + 0.004)), cat, fill=tuple(lab) + (255,), font=f, anchor="la")
        _vehicle(d, cat, doc.X(0.11), doc.Y(y + 0.024), doc.px(0.03), ico, doc.ss)
    yl = doc.Y(0.13 + 8 * 0.058 - 0.008)
    d.line((doc.X(0.03), yl, doc.X(0.97), yl), fill=rule, width=doc.ss)


def _vehicle(d, cat, x, y, s, col, lw):
    """Pictogramme de catégorie (silhouette simple) : cyclomoteur, moto, voiture, camion, bus, voiture + remorque."""
    def wheel(cx, cy, r):
        d.ellipse((cx - r, cy - r, cx + r, cy + r), outline=col, width=lw)

    def body(x0, y0, x1, y1, r=0.0):
        d.rounded_rectangle((x0, y0, x1, y1), r, fill=col)

    if cat in ("AM", "A1", "A"):
        k = {"AM": 0.7, "A1": 0.85, "A": 1.0}[cat]
        wheel(x + s * 0.45 * k, y + s * 0.3, s * 0.3 * k)
        wheel(x + s * 1.65 * k, y + s * 0.3, s * 0.3 * k)
        d.line((x + s * 0.45 * k, y + s * 0.3, x + s * 0.9 * k, y - s * 0.25, x + s * 1.35 * k, y - s * 0.05, x + s * 1.65 * k, y + s * 0.3), fill=col, width=lw * 2)
        d.line((x + s * 0.9 * k, y - s * 0.25, x + s * 1.2 * k, y - s * 0.5), fill=col, width=lw * 2)
    elif cat in ("B", "BE"):
        body(x, y - s * 0.2, x + s * 1.9, y + s * 0.25, s * 0.08)
        d.polygon((x + s * 0.5, y - s * 0.2, x + s * 0.75, y - s * 0.55, x + s * 1.4, y - s * 0.55, x + s * 1.65, y - s * 0.2), fill=col)
        wheel(x + s * 0.5, y + s * 0.3, s * 0.22)
        wheel(x + s * 1.45, y + s * 0.3, s * 0.22)
        if cat == "BE":
            d.line((x + s * 1.9, y, x + s * 2.25, y), fill=col, width=lw * 2)
            body(x + s * 2.25, y - s * 0.45, x + s * 3.1, y + s * 0.2, s * 0.05)
            wheel(x + s * 2.7, y + s * 0.3, s * 0.2)
    elif cat in ("C1", "C"):
        k = 1.0 if cat == "C" else 0.85
        body(x, y - s * 0.6 * k, x + s * 0.9 * k, y + s * 0.2, s * 0.08)
        body(x + s * 0.95 * k, y - s * 0.8 * k, x + s * 2.6 * k, y + s * 0.2, s * 0.05)
        wheel(x + s * 0.45 * k, y + s * 0.3, s * 0.22)
        wheel(x + s * 2.1 * k, y + s * 0.3, s * 0.22)
    elif cat == "D":
        body(x, y - s * 0.75, x + s * 2.7, y + s * 0.2, s * 0.12)
        for i in range(4):
            d.rectangle((x + s * (0.2 + i * 0.6), y - s * 0.6, x + s * (0.65 + i * 0.6), y - s * 0.25), fill=(255, 255, 255, 255))
        wheel(x + s * 0.55, y + s * 0.3, s * 0.22)
        wheel(x + s * 2.15, y + s * 0.3, s * 0.22)


def spec_license_verso():
    main, accent = LICENCE
    return {
        "ratio": RATIO["license_verso"], "colors": {"main": main, "accent": accent, "bg": (251, 233, 237)},
        "guilloche": {"seed": 5, "rosettes": [(0.86, 0.33, 0.26)], "y_range": (0.05, 0.6), "bands": 2},
        "mrz": (0.62, 0.94), "footer": 0.97, "microtext": 0.022, "extra": _licence_table,
    }


def card_recto(code):
    return render(spec_card_recto(code))


def card_verso(code):
    return render(spec_card_verso(code))


def passport(code):
    return render(spec_passport(code))


def military_recto(code):
    return render(spec_military_recto(code))


def license_recto(code):
    return render(spec_license_recto(code))


def license_verso():
    return render(spec_license_verso())


# ---------------------------------------------------------------------------------------------------- petits visuels
def silhouette():
    """Portrait de repli (rendu en direct coupé ou indisponible) : fond gris-bleu pâle, silhouette tête-épaules douce.
    Étirée à l'affichage en 35 x 45 : dessin pré-élargi (x 1,29) pour rester proportionné."""
    s = 256 * SS
    img = Image.new("RGBA", (s, s), (226, 231, 238, 255))
    # Léger dégradé de fond (plus clair en haut).
    grad = Image.new("RGBA", (1, 64))
    for i in range(64):
        grad.putpixel((0, i), (180, 190, 204, int(40 * i / 63)))
    img.alpha_composite(grad.resize((s, s), Image.BILINEAR))
    lay = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    sil = (152, 160, 172, 255)
    kx = 1.29
    cx = s / 2
    hw, hh = s * 0.155 * kx, s * 0.20
    hy = s * 0.36
    d.ellipse((cx - hw, hy - hh, cx + hw, hy + hh), fill=sil)
    d.rounded_rectangle((cx - s * 0.06 * kx, hy + hh * 0.6, cx + s * 0.06 * kx, s * 0.66), s * 0.02, fill=sil)
    d.rounded_rectangle((cx - s * 0.30 * kx, s * 0.64, cx + s * 0.30 * kx, s * 1.2), s * 0.16, fill=sil)
    lay = lay.filter(ImageFilter.GaussianBlur(SS * 1.2))
    img.alpha_composite(lay)
    return img.resize((256, 256), Image.LANCZOS)


def hologram():
    """Kinégramme discret (alpha ≤ 60) : fines rosaces et rayons irisés, bords fondus ; posé sur le coin de la photo.
    Son absence reste le défaut d'un faux (fn_idEvaluate)."""
    s = 256 * SS
    lay = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    c = s / 2
    rnd = random.Random(11)

    def iris(t, light=215, amp=40):
        return (int(light + amp * math.sin(2 * math.pi * t)), int(light + amp * math.sin(2 * math.pi * (t + 1 / 3))),
                int(light + amp * math.sin(2 * math.pi * (t + 2 / 3))), 255)

    params = [(9, 4, 3.0), (10, 3, 2.2), (7, 3, 2.0)]
    for j, (R, r, dd) in enumerate(params):
        sc = s * 0.40 * (1 - j * 0.14)
        for q in range(3):
            pts = _hypotrochoid(c, c, sc, R, r, dd, rot=rnd.uniform(0, math.pi), steps=360)
            # Couleur variant le long de la courbe : segments.
            for i in range(0, len(pts) - 24, 24):
                d.line(pts[i:i + 25], fill=iris((i / len(pts) + j * 0.3 + q * 0.1) % 1.0), width=SS)
    for i in range(72):
        a = 2 * math.pi * i / 72
        r0, r1 = s * 0.30, s * 0.47
        d.line((c + r0 * math.cos(a), c + r0 * math.sin(a), c + r1 * math.cos(a), c + r1 * math.sin(a)), fill=iris(i / 72), width=SS)
    for rr in (s * 0.47, s * 0.31, s * 0.12):
        d.ellipse((c - rr, c - rr, c + rr, c + rr), outline=(255, 255, 255, 255), width=SS)
    f = font("bahnschrift.ttf", int(s * 0.07), 700, 90)
    d.text((c, c), "SIM", fill=(255, 255, 255, 255), font=f, anchor="mm")
    # Bords fondus et alpha plafonné.
    yy, xx = np.mgrid[0:s, 0:s]
    rr = np.hypot(xx - c, yy - c) / (s / 2)
    fall = np.clip((0.98 - rr) / 0.3, 0, 1) ** 1.5
    a = np.asarray(lay.getchannel("A"), np.float32) / 255 * fall * 58
    lay.putalpha(Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)))
    lay = lay.filter(ImageFilter.GaussianBlur(SS * 0.6))
    return lay.resize((256, 256), Image.LANCZOS)


def photo_paper():
    """Filtre « photo imprimée » posé par-dessus la photo en jeu : léger grain, vignette aux bords, voile blanc 6 %.
    Alpha moyen ≤ 25 pour ne pas assombrir le portrait."""
    s = 256
    rnd = np.random.default_rng(5)
    yy, xx = np.mgrid[0:s, 0:s]
    nx, ny = xx / (s - 1) * 2 - 1, yy / (s - 1) * 2 - 1
    rr = np.sqrt(nx ** 2 + ny ** 2) / math.sqrt(2)
    veil_a = np.full((s, s), 15.0, np.float32)
    grain_v = rnd.uniform(0, 255, (s, s)).astype(np.float32)
    grain_a = rnd.uniform(0, 9, (s, s)).astype(np.float32)
    vig_a = (np.clip((rr - 0.55) / 0.45, 0, 1) ** 2 * 54).astype(np.float32)
    alpha = veil_a + grain_a + vig_a
    colour = (veil_a * 255 + grain_a * grain_v + vig_a * 12) / alpha
    rgb = np.repeat(np.clip(colour, 0, 255)[..., None], 3, axis=2)
    out = np.dstack((rgb, np.clip(alpha, 0, 255))).astype(np.uint8)
    img = Image.fromarray(out, "RGBA")
    print(f"    photo_paper : alpha moyen {alpha.mean():.1f}")
    return img


# ------------------------------------------------------------------------------------------------------------ icônes
def _shadowed(lay, s):
    """Ombre portée douce sous un visuel d'icône (lisibilité sur l'inventaire)."""
    sh = Image.new("RGBA", lay.size, (0, 0, 0, 0))
    sh.putalpha(lay.getchannel("A").point(lambda v: int(v * 0.45)))
    sh = sh.filter(ImageFilter.GaussianBlur(s * 0.02))
    out = Image.new("RGBA", lay.size, (0, 0, 0, 0))
    out.alpha_composite(sh, (int(s * 0.012), int(s * 0.02)))
    out.alpha_composite(lay)
    return out


def icon_card(main, bg, code="SIM", chip_on=True, photo=True, stripe=None, text=None, size=256):
    """Icône d'inventaire : miniature du recto d'une carte ID-1 (bandeau, emblème, photo, lignes, puce)."""
    s = size * SS
    lay = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    cw = s * 0.92
    ch = cw / 1.585
    x0, y0 = (s - cw) / 2, (s - ch) / 2
    x1, y1 = x0 + cw, y0 + ch
    r = s * 0.035
    d.rounded_rectangle((x0, y0, x1, y1), r, fill=tuple(bg) + (255,))
    hb = ch * 0.2
    band = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    ImageDraw.Draw(band).rounded_rectangle((x0, y0, x1, y0 + hb * 2), r, fill=tuple(main) + (255,))
    ImageDraw.Draw(band).rectangle((x0, y0 + hb, x1, y0 + hb * 2), fill=(0, 0, 0, 0))
    lay.alpha_composite(band)
    d = ImageDraw.Draw(lay)
    if stripe:
        d.rectangle((x0, y0 + hb, x1, y0 + hb + ch * 0.03), fill=tuple(stripe) + (255,))
    # Emblème.
    ex, ey, er = x0 + cw * 0.085, y0 + hb / 2, hb * 0.36
    d.ellipse((ex - er, ey - er, ex + er, ey + er), outline=(255, 255, 255, 255), width=max(2, int(er * 0.14)))
    pts = []
    for i in range(10):
        a = -math.pi / 2 + i * math.pi / 5
        rr = er * (0.55 if i % 2 == 0 else 0.23)
        pts.append((ex + rr * math.cos(a), ey + rr * math.sin(a)))
    d.polygon(pts, fill=(255, 255, 255, 255))
    # Nom (barre claire) ou texte.
    if text:
        d.text((x0 + cw * 0.17, ey), text, fill=(255, 255, 255, 255), font=fnt_name(hb * 0.5), anchor="lm")
    else:
        d.rounded_rectangle((x0 + cw * 0.17, ey - hb * 0.14, x0 + cw * 0.62, ey + hb * 0.14), hb * 0.14, fill=(255, 255, 255, 230))
    # Photo avec silhouette.
    if photo:
        px0, py0 = x0 + cw * 0.05, y0 + hb + ch * 0.1
        px1, py1 = px0 + cw * 0.26, py0 + ch * 0.6
        d.rounded_rectangle((px0, py0, px1, py1), r * 0.4, fill=(232, 235, 240, 255), outline=mix(main, bg, 0.4) + (255,), width=max(1, SS))
        sil = Image.new("RGBA", (s, s), (0, 0, 0, 0))
        ds = ImageDraw.Draw(sil)
        pcx, pw, ph = (px0 + px1) / 2, px1 - px0, py1 - py0
        sc = (150, 158, 170, 255)
        ds.ellipse((pcx - pw * 0.2, py0 + ph * 0.14, pcx + pw * 0.2, py0 + ph * 0.52), fill=sc)
        ds.rounded_rectangle((pcx - pw * 0.42, py0 + ph * 0.6, pcx + pw * 0.42, py1 + ph * 0.5), pw * 0.3, fill=sc)
        mask = Image.new("L", (s, s), 0)
        ImageDraw.Draw(mask).rounded_rectangle((px0 + SS, py0 + SS, px1 - SS, py1 - SS), r * 0.4, fill=255)
        sil.putalpha(Image.fromarray(np.minimum(np.asarray(sil.getchannel("A")), np.asarray(mask))))
        lay.alpha_composite(sil)
        d = ImageDraw.Draw(lay)
    # Lignes de texte.
    lc = mix(main, bg, 0.35) + (255,)
    lx = x0 + cw * 0.37
    for i, ww in enumerate((0.40, 0.30, 0.46, 0.26)):
        yy = y0 + hb + ch * (0.14 + i * 0.16)
        d.rounded_rectangle((lx, yy, lx + cw * ww, yy + ch * 0.055), ch * 0.027, fill=lc)
    # Puce.
    if chip_on:
        qx0, qy0 = x1 - cw * 0.17, y0 + hb + ch * 0.07
        qx1, qy1 = qx0 + cw * 0.12, qy0 + ch * 0.16
        d.rounded_rectangle((qx0, qy0, qx1, qy1), r * 0.3, fill=(222, 186, 100, 255), outline=(130, 92, 40, 255), width=max(1, SS))
        d.line((qx0 + (qx1 - qx0) * 0.3, qy0, qx0 + (qx1 - qx0) * 0.3, qy1), fill=(130, 92, 40, 255), width=max(1, SS))
        d.line((qx1 - (qx1 - qx0) * 0.3, qy0, qx1 - (qx1 - qx0) * 0.3, qy1), fill=(130, 92, 40, 255), width=max(1, SS))
        d.line((qx0, (qy0 + qy1) / 2, qx1, (qy0 + qy1) / 2), fill=(130, 92, 40, 255), width=max(1, SS))
    d.rounded_rectangle((x0, y0, x1, y1), r, outline=shade(main, 0.35) + (255,), width=max(2, SS * 2))
    return _shadowed(lay, s).resize((size, size), Image.LANCZOS)


def icon_booklet(main, code="ALT", title="PASSEPORT", size=256):
    """Icône du passeport : couverture de livret, emblème et titre dorés."""
    s = size * SS
    lay = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    bw = s * 0.62
    bh = bw * 1.42
    x0, y0 = (s - bw) / 2, (s - bh) / 2
    x1, y1 = x0 + bw, y0 + bh
    r = s * 0.035
    dark = shade(main, 0.45)
    d.rounded_rectangle((x0, y0, x1, y1), r, fill=tuple(dark) + (255,))
    # Dos arrondi plus clair à gauche, tranche à droite.
    d.rounded_rectangle((x0, y0, x0 + bw * 0.08, y1), r, fill=shade(main, 0.25) + (255,))
    d.rectangle((x0 + bw * 0.06, y0, x0 + bw * 0.08, y1), fill=tuple(dark) + (255,))
    gold = (214, 180, 96, 255)
    ex, ey, er = (x0 + x1) / 2 + bw * 0.03, y0 + bh * 0.42, bw * 0.19
    d.ellipse((ex - er, ey - er, ex + er, ey + er), outline=gold, width=max(2, int(er * 0.1)))
    d.ellipse((ex - er * 0.84, ey - er * 0.84, ex + er * 0.84, ey + er * 0.84), outline=gold, width=max(1, int(er * 0.04)))
    pts = []
    for i in range(10):
        a = -math.pi / 2 + i * math.pi / 5
        rr = er * (0.5 if i % 2 == 0 else 0.21)
        pts.append((ex + rr * math.cos(a), ey - er * 0.12 + rr * math.sin(a)))
    d.polygon(pts, fill=gold)
    d.text((ex, ey + er * 0.56), code, fill=gold, font=font("bahnschrift.ttf", er * 0.3, 700, 90), anchor="mm")
    d.text((ex, y0 + bh * 0.16), title, fill=gold, font=fnt_name(bw * 0.11), anchor="mm")
    d.text((ex, y0 + bh * 0.77), "SIMULATION", fill=gold, font=fnt_small(bw * 0.07), anchor="mm")
    d.rounded_rectangle((ex - bw * 0.07, y1 - bh * 0.1, ex + bw * 0.07, y1 - bh * 0.05), bw * 0.02, outline=gold, width=max(1, SS))
    d.rounded_rectangle((x0, y0, x1, y1), r, outline=shade(main, 0.7) + (255,), width=max(2, SS * 2))
    return _shadowed(lay, s).resize((size, size), Image.LANCZOS)


def icon_sheet(main, bg=(246, 240, 226), title="LAISSEZ-PASSER", stamp_col=(176, 40, 44), size=256):
    """Icône d'une feuille paysage (laissez-passer) : filets, lignes de texte, tampon rouge."""
    s = size * SS
    lay = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(lay)
    cw = s * 0.92
    ch = cw / 1.414
    x0, y0 = (s - cw) / 2, (s - ch) / 2
    x1, y1 = x0 + cw, y0 + ch
    d.rectangle((x0, y0, x1, y1), fill=tuple(bg) + (255,), outline=(190, 180, 160, 255), width=max(1, SS))
    c = tuple(main) + (255,)
    d.text(((x0 + x1) / 2, y0 + ch * 0.17), title, fill=c, font=fnt_name(ch * 0.13), anchor="mm")
    for yy in (y0 + ch * 0.06, y0 + ch * 0.28):
        d.line((x0 + cw * 0.08, yy, x1 - cw * 0.08, yy), fill=c, width=max(2, SS * 2))
        d.line((x0 + cw * 0.08, yy + SS * 4, x1 - cw * 0.08, yy + SS * 4), fill=c, width=max(1, SS))
    lc = mix(main, bg, 0.5) + (255,)
    for i, ww in enumerate((0.5, 0.36, 0.56, 0.3)):
        yy = y0 + ch * (0.38 + i * 0.13)
        d.rounded_rectangle((x0 + cw * 0.08, yy, x0 + cw * (0.08 + ww), yy + ch * 0.055), ch * 0.027, fill=lc)
    # Tampon.
    sx, sy, sr = x1 - cw * 0.2, y1 - ch * 0.26, ch * 0.19
    st = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    ds = ImageDraw.Draw(st)
    sc = tuple(stamp_col) + (255,)
    ds.ellipse((sx - sr, sy - sr, sx + sr, sy + sr), outline=sc, width=max(2, int(sr * 0.09)))
    ds.ellipse((sx - sr * 0.8, sy - sr * 0.8, sx + sr * 0.8, sy + sr * 0.8), outline=sc, width=max(1, int(sr * 0.04)))
    pts = []
    for i in range(10):
        a = -math.pi / 2 + i * math.pi / 5
        rr = sr * (0.45 if i % 2 == 0 else 0.19)
        pts.append((sx + rr * math.cos(a), sy + rr * math.sin(a)))
    ds.polygon(pts, fill=sc)
    st = st.rotate(-12, resample=Image.BICUBIC, center=(sx, sy))
    st.putalpha(st.getchannel("A").point(lambda v: int(v * 0.7)))
    lay.alpha_composite(st)
    return _shadowed(lay, s).resize((size, size), Image.LANCZOS)


def icon(kind):
    if kind == "military":
        return icon_card(MILITARY[0], (236, 237, 226), chip_on=True)
    if kind == "passport":
        return icon_booklet(COUNTRIES["ALT"][2])
    if kind == "license":
        return icon_card(LICENCE[0], (251, 233, 237), chip_on=False, stripe=COUNTRIES["ALT"][2])
    return icon_card(COUNTRIES["ALT"][2], tint(COUNTRIES["ALT"][2], 0.91))


def app_icon():
    """Icône blanche de l'app Contrôle ID de l'ATAK (128, fond transparent)."""
    s = 128 * SS
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    white = (255, 255, 255, 255)
    k = SS
    d.rounded_rectangle((10 * k, 26 * k, 118 * k, 98 * k), 9 * k, outline=white, width=7 * k)
    d.ellipse((24 * k, 40 * k, 50 * k, 66 * k), fill=white)
    d.pieslice((18 * k, 62 * k, 56 * k, 102 * k), 180, 360, fill=white)
    for i, ww in enumerate((40, 30, 36)):
        d.rounded_rectangle((62 * k, (42 + i * 13) * k, (62 + ww) * k, (48 + i * 13) * k), 3 * k, fill=white)
    d.line((80 * k, 104 * k, 92 * k, 116 * k, 120 * k, 84 * k), fill=white, width=9 * k, joint="curve")
    return img.resize((128, 128), Image.LANCZOS)


# ------------------------------------------------------------------------------------------------------------ sortie
def to_paa(png, paa):
    """PNG -> PAA : HEMTT (`hemtt utils paa convert`), sinon paa_dxt.py de l'ATAK."""
    hemtt = shutil.which("hemtt")
    # HEMTT refuse d'écraser un fichier sans le dire (code de sortie 0) : les gabarits restaient ceux d'avant.
    if os.path.exists(paa):
        os.remove(paa)
    if hemtt:
        r = subprocess.run([hemtt, "utils", "paa", "convert", png, paa], capture_output=True, text=True)
        if r.returncode == 0 and os.path.exists(paa):
            return
        print(r.stdout, r.stderr)
    sys.path.insert(0, os.path.join(os.path.dirname(ROOT), "COMSPEC_ATAK_Native", "tools"))
    from paa_dxt import save_paa  # noqa: E402
    save_paa(Image.open(png).convert("RGBA"), paa)


def write_jobs(jobs, out, keep=None):
    """Écrit chaque image (nom -> Image déjà en taille texture) en PAA dans `out`, PNG gardés dans `keep`."""
    os.makedirs(out, exist_ok=True)
    if keep:
        os.makedirs(keep, exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="comspec_id_")
    for name, img in jobs.items():
        if name.endswith("_co"):
            img = img.convert("RGB").convert("RGBA")
        png = os.path.join(tmp, name + ".png")
        img.save(png)
        if keep:
            shutil.copy(png, os.path.join(keep, name + ".png"))
        to_paa(png, os.path.join(out, name + ".paa"))
        print("  +", name + ".paa")
    shutil.rmtree(tmp, ignore_errors=True)


def main():
    keep = None
    if "--png" in sys.argv:
        keep = sys.argv[sys.argv.index("--png") + 1]
    zones_only = "--zones" in sys.argv
    military_only = "--military" in sys.argv
    jobs = {}
    for code in COUNTRIES:
        if zones_only and code not in ZONES:
            continue
        lc = code.lower()
        if not military_only:
            jobs[f"tpl_card_{lc}_recto_co"] = card_recto(code)
            jobs[f"tpl_card_{lc}_verso_co"] = card_verso(code)
            jobs[f"tpl_passport_{lc}_co"] = passport(code)
            jobs[f"tpl_license_{lc}_recto_co"] = license_recto(code)
        jobs[f"tpl_military_{lc}_recto_co"] = military_recto(code)
    if military_only:
        jobs["icon_military_ca"] = icon("military")
    elif not zones_only:
        jobs["tpl_license_verso_co"] = license_verso()
        jobs["silhouette_co"] = silhouette()
        jobs["hologram_ca"] = hologram()
        jobs["photo_paper_ca"] = photo_paper()
        for k in ("card", "passport", "license", "military"):
            jobs[f"icon_{k}_ca"] = icon(k)
        jobs["app_id"] = app_icon()
    write_jobs(jobs, OUT, keep)


if __name__ == "__main__":
    main()
