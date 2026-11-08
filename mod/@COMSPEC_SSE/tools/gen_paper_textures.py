#!/usr/bin/env python3
"""Papiers de la visionneuse SSE (addons/ui/data/paper), tout dessiné ici : ni photo ni image tierce.

Usage : python gen_paper_textures.py [--png dossier] [--only code,code]
Sortie : paper_<code>_co.paa (jour) et paper_<code>_n_co.paa (nuit, plus sombre, texte noir toujours lisible),
512 x 512, DXT1 (suffixe _co, sans alpha). Conversion : ImageToPAA des Arma 3 Tools (registre BI), sinon HEMTT.
Affiche aussi la couleur moyenne du bandeau et du pied de chaque papier (table de fn_paperSkin.sqf).

Géométrie de la feuille (resultDialog.hpp, 19 x 20 cases) ramenée à 512 px :
  bandeau rouge 0-28, en-tête 28-90, corps 100-442 (texte de x 16 à 496), pied 448-512.
Le texte du corps est noir : lignes, carreaux et décors restent pâles dans cette zone.
Dépendances : Pillow, numpy.
"""
import os
import shutil
import subprocess
import sys
import tempfile

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

S = 512
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "addons", "ui", "data", "paper")


def rng(code):
    return np.random.default_rng(sum(ord(c) * (i + 7) for i, c in enumerate(code)))


def blur_noise(r, scale, amp):
    """Bruit lissé (fibres, nuages) centré sur 0."""
    n = r.normal(0, 1, (S // scale + 2, S // scale + 2)).astype(np.float32)
    im = Image.fromarray(((n - n.min()) / (np.ptp(n) + 1e-6) * 255).astype(np.uint8)).resize((S, S), Image.BICUBIC)
    a = np.asarray(im, np.float32) / 255 - 0.5
    return a * 2 * amp


def base(code, color, fibre=6.0, cloud=7.0, vignette=0.06):
    r = rng(code)
    a = np.zeros((S, S, 3), np.float32) + np.array(color, np.float32)
    g = blur_noise(r, 1, fibre * 0.5) + blur_noise(r, 3, fibre) + blur_noise(r, 48, cloud)
    a += g[..., None]
    # Fibres : courts traits plus clairs ou plus sombres.
    img = Image.fromarray(np.clip(a, 0, 255).astype(np.uint8))
    d = ImageDraw.Draw(img, "RGBA")
    for _ in range(900):
        x, y = r.uniform(0, S, 2)
        ang = r.uniform(0, np.pi)
        ln = r.uniform(2, 7)
        v = int(r.choice([255, 60]))
        d.line((x, y, x + np.cos(ang) * ln, y + np.sin(ang) * ln), fill=(v, v, v, int(r.uniform(6, 16))), width=1)
    a = np.asarray(img, np.float32)
    yy, xx = np.mgrid[0:S, 0:S] / (S - 1) - 0.5
    a *= (1 - vignette * (xx ** 2 + yy ** 2) * 3)[..., None]
    return a, r


def to_img(a):
    return Image.fromarray(np.clip(a, 0, 255).astype(np.uint8))


def over(a, layer):
    """Compose un calque RGBA Pillow sur le tableau."""
    l = np.asarray(layer, np.float32)
    al = l[..., 3:4] / 255
    return a * (1 - al) + l[..., :3] * al


def layer():
    return Image.new("RGBA", (S, S), (0, 0, 0, 0))


def hlines(a, step, color, alpha, y0=100, y1=444, x0=0, x1=S, width=1):
    l = layer()
    d = ImageDraw.Draw(l)
    y = y0
    while y < y1:
        d.line((x0, y, x1, y), fill=color + (alpha,), width=width)
        y += step
    return over(a, l.filter(ImageFilter.GaussianBlur(0.4)))


def grid(a, step, color, alpha, y0=0, y1=S):
    l = layer()
    d = ImageDraw.Draw(l)
    for x in range(step // 2, S, step):
        d.line((x, y0, x, y1), fill=color + (alpha,))
    for y in range(step // 2, S, step):
        if y0 <= y <= y1:
            d.line((0, y, S, y), fill=color + (alpha,))
    return over(a, l.filter(ImageFilter.GaussianBlur(0.35)))


def margin(a, x, alpha=110):
    l = layer()
    d = ImageDraw.Draw(l)
    d.line((x, 0, x, S), fill=(196, 64, 70, alpha), width=2)
    return over(a, l.filter(ImageFilter.GaussianBlur(0.4)))


def stain(a, r, cx, cy, rad, color=(120, 78, 36), alpha=40, ring=True):
    """Tache de café / d'eau : fond diffus et bord plus marqué."""
    l = layer()
    d = ImageDraw.Draw(l)
    pts = []
    for k in range(48):
        t = k / 48 * 2 * np.pi
        rr = rad * (1 + r.normal(0, 0.06))
        pts.append((cx + np.cos(t) * rr, cy + np.sin(t) * rr * 0.9))
    d.polygon(pts, fill=color + (alpha,))
    if ring:
        d.line(pts + [pts[0]], fill=color + (min(255, alpha * 2),), width=3)
    return over(a, l.filter(ImageFilter.GaussianBlur(2.2)))


def fingerprint(a, r, cx, cy, size=26, alpha=34, color=(60, 55, 50)):
    l = layer()
    d = ImageDraw.Draw(l)
    rot = r.uniform(0, np.pi)
    for k in range(3, int(size), 3):
        pts = []
        for t in np.linspace(0, 2 * np.pi, 60):
            x = np.cos(t) * k * 0.72
            y = np.sin(t) * k + r.normal(0, 0.5)
            pts.append((cx + x * np.cos(rot) - y * np.sin(rot), cy + x * np.sin(rot) + y * np.cos(rot)))
        d.line(pts, fill=color + (alpha,), width=1)
    m = Image.new("L", (S, S), 0)
    ImageDraw.Draw(m).ellipse((cx - size * 0.8, cy - size, cx + size * 0.8, cy + size), fill=255)
    l.putalpha(Image.fromarray(np.minimum(np.asarray(l.split()[3]), np.asarray(m.filter(ImageFilter.GaussianBlur(5))))))
    return over(a, l.filter(ImageFilter.GaussianBlur(0.6)))


def edges(a, r, width, dark, noise=0.5, sides=(1, 1, 1, 1)):
    """Bords assombris (gris, jaunis), irréguliers. dark : facteur au bord (0 = noir)."""
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    dist = np.full((S, S), 1e9, np.float32)
    for on, dd in zip(sides, (xx, S - 1 - xx, yy, S - 1 - yy)):
        if on:
            dist = np.minimum(dist, dd)
    dist = dist + blur_noise(r, 12, width * noise) + blur_noise(r, 3, width * 0.15)
    k = np.clip(1 - dist / width, 0, 1) ** 1.6
    return a * (1 - (1 - dark) * k)[..., None]


def folds(a, r, n, strength=10.0, lines=None):
    """Plis : relief en toit le long de droites, éclairé en biais (ombre d'un côté, reflet de l'autre)."""
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    h = np.zeros((S, S), np.float32)
    lines = lines or []
    for _ in range(n):
        lines.append((r.uniform(0, S), r.uniform(0, S), r.uniform(0, np.pi), r.uniform(18, 60), r.choice([-1, 1])))
    for (px, py, ang, w, sgn) in lines:
        nx, ny = -np.sin(ang), np.cos(ang)
        dd = (xx - px) * nx + (yy - py) * ny
        h += sgn * np.clip(1 - np.abs(dd) / w, 0, 1) * w * 0.35
    h += blur_noise(r, 24, 6) + blur_noise(r, 6, 1.4)
    gy, gx = np.gradient(h)
    shade = (gx * 0.7 + gy * 0.7)
    # Arête vive du pli : fine ligne sombre.
    a = a + (shade * strength)[..., None] * 0.5
    for (px, py, ang, w, sgn) in lines:
        nx, ny = -np.sin(ang), np.cos(ang)
        dd = np.abs((xx - px) * nx + (yy - py) * ny)
        a *= (1 - 0.10 * np.exp(-(dd ** 2) / 1.5))[..., None]
    return a


def watermark_seal(a, cx, cy, rad, color, alpha):
    l = layer()
    d = ImageDraw.Draw(l)
    d.ellipse((cx - rad, cy - rad, cx + rad, cy + rad), outline=color + (alpha,), width=4)
    d.ellipse((cx - rad * 0.82, cy - rad * 0.82, cx + rad * 0.82, cy + rad * 0.82), outline=color + (alpha,), width=2)
    for k in range(24):
        t = k / 24 * 2 * np.pi
        x, y = cx + np.cos(t) * rad * 0.91, cy + np.sin(t) * rad * 0.91
        d.ellipse((x - 3, y - 3, x + 3, y + 3), fill=color + (alpha,))
    # Étoile centrale, laurier stylisé.
    pts = []
    for k in range(10):
        t = -np.pi / 2 + k * np.pi / 5
        rr = rad * (0.42 if k % 2 == 0 else 0.17)
        pts.append((cx + np.cos(t) * rr, cy + np.sin(t) * rr))
    d.polygon(pts, fill=color + (alpha,))
    for side in (-1, 1):
        for k in range(7):
            t = np.pi / 2 + side * (0.35 + k * 0.22)
            x, y = cx + np.cos(t) * rad * 0.62, cy + np.sin(t) * rad * 0.62
            d.ellipse((x - 7, y - 3.5, x + 7, y + 3.5), fill=color + (alpha,))
    return over(a, l.filter(ImageFilter.GaussianBlur(0.8)))


def tear(a, r):
    """Feuille déchirée en deux puis recollée au ruban adhésif."""
    xs = np.linspace(-4, S + 4, 90)
    walk = np.cumsum(r.normal(0, 2.2, xs.size))
    ys = 0.56 * S - (xs / S) * 0.1 * S + walk - np.linspace(0, walk[-1], xs.size)
    path = list(zip(xs, ys))
    l = layer()
    d = ImageDraw.Draw(l)
    d.line([(x, y + 2.5) for x, y in path], fill=(40, 32, 24, 70), width=4)   # ombre du raccord
    d.line(path, fill=(255, 252, 244, 200), width=3)                           # fibres blanches du bord
    d.line([(x, y - 1.5) for x, y in path], fill=(90, 80, 66, 90), width=1)
    a = over(a, l.filter(ImageFilter.GaussianBlur(0.7)))
    # Rubans : trois bandes translucides un peu jaunies, bords plus nets.
    for tx in (70, 255, 430):
        ty = np.interp(tx, xs, ys)
        w, h = r.uniform(46, 60), r.uniform(80, 100)
        ang = r.uniform(-14, 14)
        t = Image.new("RGBA", (int(w) + 8, int(h) + 8), (0, 0, 0, 0))
        td = ImageDraw.Draw(t)
        td.rectangle((4, 4, 4 + w, 4 + h), fill=(246, 234, 180, 92), outline=(190, 174, 120, 150))
        for _ in range(30):
            sx, sy = r.uniform(4, 4 + w), r.uniform(4, 4 + h)
            td.point((sx, sy), fill=(255, 255, 255, 60))
        t = t.rotate(ang, expand=True, resample=Image.BICUBIC)
        l = layer()
        l.alpha_composite(t, (int(tx - t.width / 2), int(ty - t.height / 2)))
        a = over(a, l)
    return a


def burn(a, r, width=24):
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    dist = np.minimum(np.minimum(xx, S - 1 - xx), np.minimum(yy, S - 1 - yy))
    # Bord rongé plus profond par endroits (coins, morsures).
    dist = dist + blur_noise(r, 48, width * 0.9) + blur_noise(r, 12, width * 0.3) + blur_noise(r, 3, 2.5)
    for cx, cy in ((0, 0), (S, S), (S, 0)):
        dist = np.minimum(dist, np.hypot(xx - cx, yy - cy) * 0.6 + blur_noise(r, 32, 12))
    char = np.clip(1 - dist / (width * 0.45), 0, 1)
    scorch = np.clip(1 - dist / width, 0, 1) ** 1.3
    a = a * (1 - 0.55 * scorch)[..., None] + np.array([70, 38, 14], np.float32) * (0.55 * scorch)[..., None] * 0.6
    a = a * (1 - char)[..., None] + np.array([22, 16, 12], np.float32) * char[..., None]
    # Suie : quelques traînées.
    l = layer()
    d = ImageDraw.Draw(l)
    for _ in range(14):
        x, y = r.uniform(0, S, 2)
        d.ellipse((x - 18, y - 6, x + 18, y + 6), fill=(30, 24, 18, 14))
    return over(a, l.filter(ImageFilter.GaussianBlur(6)))


def form(a, color=(70, 82, 66)):
    """Formulaire : cases et lignes de saisie imprimées pâles, petites étiquettes (barres) sans texte."""
    l = layer()
    d = ImageDraw.Draw(l)
    al = 70
    d.rectangle((10, 96, 502, 444), outline=color + (al + 20,), width=2)
    rows = [(96, 150, [0.5]), (150, 204, [0.34, 0.67]), (204, 258, [0.6]), (258, 444, [])]
    for y0, y1, cuts in rows:
        d.line((10, y1, 502, y1), fill=color + (al,), width=1)
        for c in cuts:
            x = 10 + c * 492
            d.line((x, y0, x, y1), fill=color + (al,), width=1)
        for c in [0] + cuts:
            x = 10 + c * 492
            d.rectangle((x + 5, y0 + 5, x + 5 + 46, y0 + 9), fill=color + (al - 20,))
    for y in range(290, 440, 22):
        d.line((18, y, 494, y), fill=color + (40,), width=1)
    # Repères de numérisation aux coins, code de case à gauche.
    for x, y in ((4, 92), (496, 92), (4, 440), (496, 440)):
        d.rectangle((x, y, x + 10, y + 10), fill=color + (110,))
    return over(a, l.filter(ImageFilter.GaussianBlur(0.35)))


def spiral(a, r):
    l = layer()
    d = ImageDraw.Draw(l)
    for y in range(40, S - 10, 22):
        d.ellipse((3, y - 4, 11, y + 4), fill=(46, 42, 38, 230))           # trou
        d.ellipse((4, y - 3, 10, y + 1), fill=(20, 18, 16, 255))
        d.arc((-10, y - 7, 9, y + 7), 300, 60, fill=(150, 154, 160, 255), width=3)  # spire
        d.arc((-10, y - 7, 9, y + 7), 300, 20, fill=(215, 220, 228, 255), width=1)
    d.line((15, 0, 15, S), fill=(120, 112, 100, 40), width=1)               # pli de perforation
    return over(a, l.filter(ImageFilter.GaussianBlur(0.5)))


def ticket(a, r):
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    # Impression thermique passée : bandes horizontales grisées, bords légèrement roulés.
    fade = 1 - 0.05 * (np.sin(yy / 23.0 + r.uniform(0, 6)) * 0.5 + 0.5) * (np.sin(yy / 97.0) * 0.5 + 0.5)
    curl = 1 - 0.10 * (np.clip(1 - xx / 26, 0, 1) ** 2 + np.clip(1 - (S - 1 - xx) / 26, 0, 1) ** 2)
    a = a * (fade * curl)[..., None]
    l = layer()
    d = ImageDraw.Draw(l)
    # Bord arraché en dents de scie, haut et bas.
    for y0, sgn in ((0, 1), (S - 1, -1)):
        pts = [(0, y0)]
        for x in range(0, S + 12, 12):
            pts.append((x, y0 + sgn * (7 if (x // 12) % 2 else 2)))
        pts.append((S, y0))
        d.polygon(pts, fill=(52, 50, 48, 255))
    # Ligne de pointillés de découpe sous l'en-tête, comme sur les tickets.
    for x in range(14, 500, 9):
        d.line((x, 446, x + 4, 446), fill=(90, 90, 90, 70))
    return over(a, l.filter(ImageFilter.GaussianBlur(0.6)))


def postit(a, r):
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    band = np.clip(1 - yy / 70, 0, 1) * 0.06            # bande adhésive, un peu plus sombre
    curl = np.clip((xx + yy - 1.55 * S) / (0.45 * S), 0, 1) ** 2 * 0.22
    a = a * (1 - band - curl)[..., None]
    # Coin bas droit relevé : ombre portée dans le coin.
    l = layer()
    d = ImageDraw.Draw(l)
    d.polygon([(S, S - 70), (S, S), (S - 80, S)], fill=(60, 50, 10, 80))
    return over(a, l.filter(ImageFilter.GaussianBlur(10)))


def carbon(a, r):
    l = layer()
    d = ImageDraw.Draw(l)
    # Bavures bleues du carbone et traits fantômes d'une autre frappe.
    for _ in range(22):
        x, y = r.uniform(0, S), r.uniform(90, S)
        d.ellipse((x - r.uniform(10, 40), y - r.uniform(3, 9), x + r.uniform(10, 40), y + r.uniform(3, 9)), fill=(40, 60, 140, 16))
    for y in range(118, 440, 21):
        x = 20 + r.uniform(0, 30)
        while x < 480:
            w = r.uniform(6, 34)
            if r.random() < 0.55:
                d.line((x, y, x + w, y), fill=(50, 70, 150, 26), width=3)
            x += w + r.uniform(3, 10)
    a = over(a, l.filter(ImageFilter.GaussianBlur(1.6)))
    return a


def make(code):
    """Renvoie l'image du jour (RGB, 512 x 512)."""
    if code == "uni":
        a, r = base(code, (242, 240, 233), fibre=4, cloud=4)
    elif code == "quadrille":
        a, r = base(code, (244, 243, 238), fibre=4, cloud=4)
        a = grid(a, 12, (110, 150, 190), 70)
    elif code == "seyes":
        a, r = base(code, (245, 244, 240), fibre=4, cloud=4)
        l = layer()
        d = ImageDraw.Draw(l)
        for y in range(4, S, 5):
            major = (y - 4) % 20 == 0
            d.line((0, y, S, y), fill=(120, 110, 200, 85 if major else 38), width=1)
        for x in range(30, S, 20):
            d.line((x, 0, x, S), fill=(120, 110, 200, 38), width=1)
        a = over(a, l.filter(ImageFilter.GaussianBlur(0.35)))
        a = margin(a, 12)
    elif code == "ligne":
        a, r = base(code, (246, 245, 239), fibre=4, cloud=4)
        a = hlines(a, 21, (100, 140, 190), 70, y0=104, y1=S)
        a = margin(a, 12, 70)
    elif code == "sale":
        a, r = base(code, (226, 218, 198), fibre=7, cloud=12, vignette=0.12)
        a = edges(a, r, 46, 0.68)
        for _ in range(3):
            a = stain(a, r, r.uniform(60, 450), r.uniform(120, 430), r.uniform(26, 52), alpha=int(r.uniform(22, 36)))
        a = stain(a, r, 440, 470, 40, color=(70, 60, 50), alpha=30, ring=False)
        for _ in range(4):
            a = fingerprint(a, r, r.uniform(30, 480), r.uniform(110, 470))
        l = layer()
        d = ImageDraw.Draw(l)
        for _ in range(40):
            x, y = r.uniform(0, S, 2)
            rad = r.uniform(0.6, 2.2)
            d.ellipse((x - rad, y - rad, x + rad, y + rad), fill=(60, 50, 40, int(r.uniform(40, 110))))
        a = over(a, l)
    elif code == "jauni":
        a, r = base(code, (228, 210, 160), fibre=6, cloud=14, vignette=0.16)
        a = edges(a, r, 60, 0.72)
        l = layer()
        d = ImageDraw.Draw(l)
        for _ in range(70):   # rousseurs (piqûres d'humidité)
            x, y = r.uniform(0, S, 2)
            rad = r.uniform(1, 5)
            d.ellipse((x - rad, y - rad, x + rad, y + rad), fill=(150, 96, 40, int(r.uniform(20, 60))))
        a = over(a, l.filter(ImageFilter.GaussianBlur(1.1)))
        a = folds(a, r, 0, 5, lines=[(S / 2, S / 2, 0.0, 26, 1), (S / 2, S / 2, np.pi / 2, 26, -1)])
    elif code == "froisse":
        a, r = base(code, (238, 233, 220), fibre=5, cloud=6)
        a = folds(a, r, 11, 18)
        a = edges(a, r, 30, 0.85)
    elif code == "dechire":
        a, r = base(code, (240, 236, 226), fibre=5, cloud=6)
        a = hlines(a, 21, (110, 140, 180), 40, y0=104, y1=S)
        a = tear(a, r)
        a = edges(a, r, 24, 0.88)
    elif code == "brule":
        a, r = base(code, (232, 222, 196), fibre=6, cloud=10, vignette=0.1)
        a = burn(a, r)
    elif code == "entete":
        a, r = base(code, (247, 246, 242), fibre=3, cloud=3)
        a = watermark_seal(a, S / 2, 270, 120, (150, 160, 175), 34)
        l = layer()
        d = ImageDraw.Draw(l)
        for k in range(0, 5):                     # filet guilloché en pied
            pts = [(x, 452 + k * 2 + 3 * np.sin(x / 9.0 + k)) for x in range(0, S, 2)]
            d.line(pts, fill=(60, 80, 120, 70), width=1)
        d.line((14, 94, 498, 94), fill=(40, 52, 80, 120), width=2)
        d.line((14, 98, 498, 98), fill=(40, 52, 80, 70), width=1)
        a = over(a, l.filter(ImageFilter.GaussianBlur(0.4)))
    elif code == "formulaire":
        a, r = base(code, (232, 234, 218), fibre=5, cloud=5)
        a = form(a)
    elif code == "carbone":
        a, r = base(code, (224, 228, 236), fibre=4, cloud=9)
        a = carbon(a, r)
        a = edges(a, r, 20, 0.9)
    elif code == "spirale":
        a, r = base(code, (246, 245, 240), fibre=4, cloud=4)
        a = hlines(a, 20, (100, 140, 190), 66, y0=104, y1=S, x0=18)
        a = spiral(a, r)
    elif code == "ticket":
        a, r = base(code, (247, 247, 245), fibre=2, cloud=3, vignette=0.02)
        a = ticket(a, r)
    elif code == "postit":
        a, r = base(code, (250, 232, 120), fibre=4, cloud=6, vignette=0.04)
        a = postit(a, r)
    else:
        raise SystemExit(f"papier inconnu : {code}")
    return to_img(a)


def night(img):
    """Visionneuse de nuit : feuille moins éblouissante, un peu froide, texte noir encore net (contraste > 4,5)."""
    a = np.asarray(img, np.float32)
    lum = a.mean(2, keepdims=True)
    a = (a * 0.8 + lum * 0.2) * np.array([0.60, 0.61, 0.66], np.float32)
    return to_img(a)


PAPERS = ["uni", "quadrille", "seyes", "ligne", "sale", "jauni", "froisse", "dechire", "brule", "entete",
          "formulaire", "carbone", "spirale", "ticket", "postit"]


def image_to_paa():
    try:
        import winreg
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, r"Software\Bohemia Interactive\ImageToPAA") as k:
            tool = winreg.QueryValueEx(k, "tool")[0]
            return tool if os.path.exists(tool) else None
    except OSError:
        return None


def to_paa(png, paa):
    # Ni ImageToPAA ni HEMTT ne signalent toujours un fichier qu'ils n'ont pas écrasé : l'ancien est supprimé d'abord.
    if os.path.exists(paa):
        os.remove(paa)
    tool = image_to_paa()
    if tool:
        subprocess.run([tool, png, paa], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    else:
        hemtt = shutil.which("hemtt") or "hemtt"
        subprocess.run([hemtt, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)
    if not os.path.exists(paa):
        raise SystemExit(f"conversion ratée : {paa}")


def mean_rgb(img, box):
    a = np.asarray(img.crop(box), np.float32).reshape(-1, 3).mean(0) / 255
    return [round(float(v), 3) for v in a]


def main():
    keep = None
    if "--png" in sys.argv:
        keep = sys.argv[sys.argv.index("--png") + 1]
        os.makedirs(keep, exist_ok=True)
    only = PAPERS
    if "--only" in sys.argv:
        only = sys.argv[sys.argv.index("--only") + 1].split(",")
    os.makedirs(OUT, exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="comspec_paper_")
    try:
        for code in only:
            day = make(code)
            for suffix, img in (("", day), ("_n", night(day))):
                name = f"paper_{code}{suffix}_co"
                png = os.path.join(tmp, name + ".png")
                img.save(png)
                if keep:
                    img.save(os.path.join(keep, name + ".png"))
                to_paa(png, os.path.join(OUT, name + ".paa"))
                # En-tête (28-90) et pied (448-512), sans les bords : teinte des bandeaux posés sur la feuille.
                print(f'["{code}{suffix}", {mean_rgb(img, (40, 34, 472, 86))}, {mean_rgb(img, (40, 452, 472, 500))}],')
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
