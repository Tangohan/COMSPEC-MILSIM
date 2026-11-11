#!/usr/bin/env python3
"""Textures des bris d'écran, pannes de dalle, bugs d'affichage, boue et pluie du téléphone (fn_screenFaults,
fn_screenGlitch, fn_screenSurface). Dessins d'origine, entièrement calculés ici : aucune image d'un autre mod ou jeu.

Usage : python gen_screen_damage.py [dossier d'aperçus PNG] [--sans-paa]
Sortie : Sources/addons/main/data/gx_*.paa (verre), pf_*.paa (dalle), gl_*.paa (bugs), dirt_mud_*.paa, dirt_rain_*.paa,
bloodc_seep_* / bloodd_seep_* et bloodc_puddle_* / bloodd_puddle_* (sang resté dans les fêlures, flaque du bas).
Dépendances : Pillow, numpy (et lzallright pour des PAA compressés, tools/paa_dxt.py).

Verre (gx_<genre>_<variante>_<gravité 1 à 3>) : la gravité fait grandir la même cassure depuis le même impact (même
graine, plus de rayons et d'anneaux), jamais un motif répété. Trois gabarits, lus par fn_screenFaults :
  - « point » (carré 1024) : impact au point (0,3 ; 0,3) de la texture, tournée par quarts de tour pour le placer du bon
    côté de l'écran ; les fêlures courent jusqu'au bord du verre ;
  - « coin » (carré 1024) : origine au coin haut gauche de la texture, posée dans un coin de l'écran ;
  - « bande » (512 x 1024) : fracture longue, d'un bord à l'autre du verre.
Chaque fêlure est un trait clair (l'arête du verre accroche la lumière) doublé d'une ombre fine du côté opposé à la
lumière, d'intensité irrégulière le long du trait : le jeu fait varier l'opacité avec le reflet du verre.
"""
import math
import os
import random
import sys

import numpy as np
from PIL import Image, ImageChops, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
NO_PAA = "--sans-paa" in sys.argv
ARGS = [a for a in sys.argv[1:] if not a.startswith("--")]
PREVIEW = ARGS[0] if ARGS else None
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
SS = 2  # suréchantillonnage du tracé


def save(img, name):
    if not NO_PAA:
        from paa_dxt import save_paa
        save_paa(img, os.path.join(OUT, name + ".paa"))
    if PREVIEW:
        os.makedirs(PREVIEW, exist_ok=True)
        img.save(os.path.join(PREVIEW, name + ".png"))


def arr(img):
    return np.asarray(img, np.float32) / 255


def blur(a, r):
    return arr(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L").filter(ImageFilter.GaussianBlur(r)))


def fbm(w, h, seed, octaves=4, base=3):
    rng = np.random.default_rng(seed)
    out = np.zeros((h, w), np.float32)
    amp, tot = 1.0, 0.0
    short = min(w, h)
    for o in range(octaves):
        cells = base * (2 ** o)
        gw, gh = max(2, int(cells * w / short) + 2), max(2, int(cells * h / short) + 2)
        g = Image.fromarray(np.uint8(rng.random((gh, gw)) * 255), "L").resize((w, h), Image.BICUBIC)
        out += amp * arr(g)
        tot += amp
        amp *= 0.5
    out /= tot
    return np.clip((out - out.min()) / max(1e-6, out.max() - out.min()), 0, 1)


def edge_fade(w, h, margin):
    m = max(2.0, margin * min(w, h))
    fy = np.clip(np.minimum(np.arange(h), np.arange(h)[::-1]) / m, 0, 1)
    fx = np.clip(np.minimum(np.arange(w), np.arange(w)[::-1]) / m, 0, 1)
    return np.outer(fy * fy * (3 - 2 * fy), fx * fx * (3 - 2 * fx)).astype(np.float32)


def rgba(col, alpha):
    """col : (h, w, 3) 0..255 ou triplet ; alpha : (h, w) 0..1."""
    h, w = alpha.shape
    if not isinstance(col, np.ndarray):
        col = np.ones((h, w, 3), np.float32) * np.array(col, np.float32)
    return Image.fromarray(np.dstack([np.clip(col, 0, 255), np.clip(alpha, 0, 1) * 255]).astype(np.uint8), "RGBA")


class Glass:
    """Tracé de fêlures : couche « arête » (clair), couche « éclat » (verre écrasé, laiteux) et couche « trou » (noir)."""

    def __init__(self, w, h, seed):
        self.w, self.h = w, h
        self.rnd = random.Random(seed)
        self.seed = seed
        self.line = Image.new("L", (w * SS, h * SS), 0)
        self.milk = Image.new("L", (w * SS, h * SS), 0)
        self.hole = Image.new("L", (w * SS, h * SS), 0)
        self.dl = ImageDraw.Draw(self.line)
        self.dm = ImageDraw.Draw(self.milk)
        self.dh = ImageDraw.Draw(self.hole)

    def walk(self, x, y, ang, length, jitter=0.05, kink=0.07, step=None, bend=0.0):
        """Chemin d'une fêlure : direction persistante, petits écarts, parfois un coude franc. Renvoie les points."""
        rnd = self.rnd
        step = step or self.w / 70
        pts = [(x, y)]
        d = 0.0
        while d < length:
            s = step * rnd.uniform(0.6, 1.5)
            ang += rnd.gauss(0, jitter) + bend * s
            if rnd.random() < kink:
                ang += rnd.uniform(-0.45, 0.45)
            x += math.cos(ang) * s
            y += math.sin(ang) * s
            d += s
            pts.append((x, y))
            if x < -8 or y < -8 or x > self.w + 8 or y > self.h + 8:
                break
        return pts

    def stroke(self, pts, w0=1.6, w1=0.8, a0=1.0, a1=0.72):
        """Trait d'intensité irrégulière (une arête n'accroche pas la lumière partout) qui s'affine vers le bout."""
        rnd = self.rnd
        n = len(pts) - 1
        if n < 1:
            return
        lum = rnd.uniform(0.7, 1.0)
        for i in range(n):
            t = i / n
            lum = min(1.0, max(0.62, lum + rnd.gauss(0, 0.1)))
            # Largeur pensée pour l'écran du jeu : la texture de 1024 px y occupe 500 à 1000 px.
            wd = (w0 + (w1 - w0) * t) * rnd.uniform(0.9, 1.15) * 1.7
            a = (a0 + (a1 - a0) * t) * lum
            p, q = pts[i], pts[i + 1]
            self.dl.line([p[0] * SS, p[1] * SS, q[0] * SS, q[1] * SS], fill=int(255 * min(1, a)), width=max(1, int(round(wd * SS))))

    def crack(self, x, y, ang, length, w0=1.6, branch=0.5, depth=0, **kw):
        pts = self.walk(x, y, ang, length, **kw)
        self.stroke(pts, w0, max(0.6, w0 * 0.45))
        rnd = self.rnd
        # Ramifications : partent d'un coude, plus fines, plus courtes, vers l'extérieur.
        if depth < 2 and len(pts) > 4:
            for _ in range(3):
                if rnd.random() < branch:
                    i = rnd.randint(2, len(pts) - 2)
                    a = math.atan2(pts[i][1] - pts[i - 1][1], pts[i][0] - pts[i - 1][0]) + rnd.choice([-1, 1]) * rnd.uniform(0.3, 0.95)
                    self.crack(pts[i][0], pts[i][1], a, length * rnd.uniform(0.15, 0.45), w0 * 0.7, branch * 0.5, depth + 1)
        return pts

    @staticmethod
    def at(pts, r, ox, oy):
        """Point d'un rayon à la distance r de l'origine (None si le rayon est plus court)."""
        for i in range(1, len(pts)):
            d0 = math.hypot(pts[i - 1][0] - ox, pts[i - 1][1] - oy)
            d1 = math.hypot(pts[i][0] - ox, pts[i][1] - oy)
            if d0 <= r <= d1 and d1 > d0:
                k = (r - d0) / (d1 - d0)
                return (pts[i - 1][0] + (pts[i][0] - pts[i - 1][0]) * k, pts[i - 1][1] + (pts[i][1] - pts[i - 1][1]) * k)
        return None

    def rings(self, rays, ox, oy, radii, p=0.75, closed=True, w0=1.2):
        """Anneaux brisés d'une toile : cordes de rayon en rayon, jamais un cercle parfait."""
        rnd = self.rnd
        n = len(rays)
        for r in radii:
            for i in range(n if closed else n - 1):
                if rnd.random() > p:
                    continue
                a = self.at(rays[i], r * rnd.uniform(0.86, 1.14), ox, oy)
                b = self.at(rays[(i + 1) % n], r * rnd.uniform(0.86, 1.14), ox, oy)
                if a and b and math.hypot(a[0] - b[0], a[1] - b[1]) < r * 1.6:
                    mx = (a[0] + b[0]) / 2 + (ox - (a[0] + b[0]) / 2) * rnd.uniform(-0.04, 0.1)
                    my = (a[1] + b[1]) / 2 + (oy - (a[1] + b[1]) / 2) * rnd.uniform(-0.04, 0.1)
                    self.stroke([a, (mx, my), b], w0, w0 * 0.8, rnd.uniform(0.6, 1), rnd.uniform(0.5, 0.9))
                    # Éclat laiteux entre deux rayons, près de l'impact (verre décollé de son film).
                    if r < self.w * 0.14 and rnd.random() < 0.5:
                        self.dm.polygon([(ox * SS, oy * SS), (a[0] * SS, a[1] * SS), (b[0] * SS, b[1] * SS)], fill=rnd.randint(40, 110))

    def crush(self, x, y, r, n=None):
        """Verre pilé à l'impact : nuage de tout petits traits et de grains blancs, opaque au centre."""
        rnd = self.rnd
        for _ in range(n or int(r * r * 0.5)):
            rr = r * (0.25 + 0.75 * rnd.random() ** 0.7)
            a = rnd.uniform(0, 6.283)
            px, py = x + math.cos(a) * rr, y + math.sin(a) * rr
            ln = rnd.uniform(1.5, max(2.5, r * 0.22))
            b = rnd.uniform(0, 6.283)
            self.dl.line([px * SS, py * SS, (px + math.cos(b) * ln) * SS, (py + math.sin(b) * ln) * SS], fill=rnd.randint(90, 255), width=rnd.choice([1, 1, 2]))
        self.dm.ellipse([(x - r * 0.9) * SS, (y - r * 0.9) * SS, (x + r * 0.9) * SS, (y + r * 0.9) * SS], fill=95)

    def shard(self, poly, dark=True):
        """Éclat de verre manquant : la dalle nue apparaît (sombre, mate), bord blanc du verre cassé."""
        p = [(a * SS, b * SS) for a, b in poly]
        (self.dh if dark else self.dm).polygon(p, fill=255 if dark else 120)
        self.stroke(poly + [poly[0]], 2.0, 1.6, 1.0, 0.85)

    def render(self, fade=None, ink=None, tint=(228, 236, 240)):
        """Assemble : encre de dalle (dessous), verre laiteux, ombre et arête claire des fêlures, trous."""
        w, h = self.w, self.h
        dn = lambda im: arr(im.resize((w, h), Image.LANCZOS))
        line = dn(self.line)
        milk = blur(dn(self.milk), 1.2) * (0.55 + 0.45 * fbm(w, h, self.seed + 5, 4, 12))
        hole = blur(dn(self.hole), 0.7)
        # Ombre : décalée vers le bas à droite (lumière en haut à gauche, comme le reflet de la vitre).
        sh = np.roll(np.roll(line, 2, 0), 1, 1)
        sh = np.clip(blur(sh, 0.8) * 1.25 - line * 0.9, 0, 1)
        glow = blur(line, 2.2) * 0.32
        a_line = np.clip(line * 1.5, 0, 1)
        col = np.zeros((h, w, 3), np.float32)
        alpha = np.zeros((h, w), np.float32)

        def over(c, a):
            nonlocal col, alpha
            a = np.clip(a, 0, 1)
            col = col * (1 - a[..., None]) + np.array(c, np.float32) * a[..., None]
            alpha = alpha + a * (1 - alpha)

        if ink is not None:
            icol, ia = ink
            a = np.clip(ia, 0, 1)
            col = col * (1 - a[..., None]) + icol * a[..., None]
            alpha = alpha + a * (1 - alpha)
        # Dalle nue sous un éclat manquant : gris très sombre, un peu de grain.
        grain = fbm(w, h, self.seed + 9, 3, 60)
        over((205, 214, 220), milk * 0.42)
        over((6, 8, 10), sh * 0.6)
        over((205, 222, 232), glow)
        over(tint, a_line)
        over((14, 15, 17), hole * (0.93 + 0.06 * grain))
        if fade is not None:
            alpha = alpha * fade
        return rgba(col, alpha)


def radial_fade(w, h, ox, oy, r0, r1):
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    d = np.hypot(xx - ox, yy - oy)
    return np.clip((r1 - d) / max(1.0, r1 - r0), 0, 1)


# ---------------------------------------------------------------------------------------------------- verre : genres

S = 1024
OX = OY = 0.3 * S  # impact des gabarits « point »


def hair(seed, sev):
    """Fêlure fine : un seul trait, à peine ramifié ; elle s'allonge avec la gravité."""
    g = Glass(S, S, seed)
    a = g.rnd.uniform(0.25, 1.2)
    L = S * (0.26, 0.5, 0.82)[sev - 1]
    x, y = OX - math.cos(a) * L * 0.25, OY - math.sin(a) * L * 0.25
    g.crack(x, y, a, L, 1.6, branch=(0.0, 0.18, 0.35)[sev - 1], jitter=0.035, kink=0.05)
    g.stroke(g.walk(x, y, a + math.pi, L * 0.22, 0.03, 0.03), 1.1, 0.5, 0.9, 0.3)
    return g.render(edge_fade(S, S, 0.02))


def web_point(seed, sev, bull=False):
    """Impact au centre d'une toile (ou « œil de bœuf » : anneaux serrés d'un choc franc, rayons courts)."""
    g = Glass(S, S, seed)
    rnd = g.rnd
    n = (7, 10, 14)[sev - 1] if not bull else (6, 8, 11)[sev - 1]
    a0 = rnd.uniform(0, 6.283)
    rays = []
    for i in range(n):
        a = a0 + 6.283 * (i + rnd.uniform(-0.3, 0.3)) / n
        if bull:
            L = S * rnd.uniform(0.1, 0.22) * (0.8, 1.2, 1.7)[sev - 1]
        else:
            L = S * min(1.3, rnd.lognormvariate(-0.95, 0.5) * (0.55, 1.1, 1.9)[sev - 1] + 0.08)
        rays.append(g.crack(OX, OY, a, L, 2.1, branch=(0.15, 0.35, 0.5)[sev - 1], jitter=0.04))
    if bull:
        radii = [S * 0.03 * k * rnd.uniform(0.8, 1.25) for k in range(1, (3, 4, 6)[sev - 1])]
        g.rings(rays, OX, OY, radii, 0.62, w0=1.2)
        # Cône de choc : anneau presque complet, plus épais.
        g.rings(rays, OX, OY, [S * 0.05], 1.0, w0=2.2)
    else:
        radii = [S * r for r in ((0.05, 0.11), (0.045, 0.1, 0.17, 0.26), (0.04, 0.09, 0.15, 0.22, 0.31, 0.42))[sev - 1]]
        g.rings(rays, OX, OY, radii, (0.6, 0.7, 0.75)[sev - 1])
    g.crush(OX, OY, S * (0.012, 0.018, 0.026)[sev - 1] * (1.5 if bull else 1))
    return g.render(edge_fade(S, S, 0.02))


def corner(seed, sev, chip=False):
    """Coin écrasé (chute) : éventail de fêlures depuis le coin, anneaux en arc. chip : coin éclaté, éclats de verre
    manquants (la dalle nue apparaît), fêlures plus courtes."""
    g = Glass(S, S, seed)
    rnd = g.rnd
    ox, oy = S * 0.012, S * 0.012
    n = (5, 8, 11)[sev - 1]
    rays = []
    for i in range(n):
        a = (math.pi / 2) * (i + 0.5 + rnd.uniform(-0.3, 0.3)) / n
        L = S * rnd.uniform(0.3, 0.7) * (0.6, 1.1, 1.75)[sev - 1] * (0.6 if chip else 1)
        rays.append(g.crack(ox, oy, a, L, 2.1, branch=(0.15, 0.3, 0.45)[sev - 1], jitter=0.045))
    radii = [S * r for r in ((0.08, 0.17), (0.07, 0.15, 0.26, 0.4), (0.06, 0.13, 0.22, 0.33, 0.47, 0.64, 0.82))[sev - 1]]
    g.rings(rays, ox, oy, radii, 0.72, closed=False)
    g.crush(ox + S * 0.012, oy + S * 0.012, S * (0.02, 0.03, 0.04)[sev - 1])
    if chip:
        # Éclats manquants : polygones entre deux rayons voisins, du coin vers l'intérieur.
        r1 = S * (0.11, 0.18, 0.27)[sev - 1]
        k = (2, 4, 6)[sev - 1]
        for i in rnd.sample(range(n - 1), min(n - 1, k)):
            ra, rb = r1 * rnd.uniform(0.35, 1.0), r1 * rnd.uniform(0.35, 1.0)
            pa, pb = g.at(rays[i], ra, ox, oy), g.at(rays[i + 1], rb, ox, oy)
            if pa and pb:
                inner = rnd.uniform(0.0, 0.5)
                qa = (ox + (pa[0] - ox) * inner, oy + (pa[1] - oy) * inner)
                qb = (ox + (pb[0] - ox) * inner, oy + (pb[1] - oy) * inner)
                g.shard([qa, pa, pb, qb])
        # Le coin lui-même est parti.
        c = S * (0.05, 0.08, 0.12)[sev - 1]
        g.shard([(0, 0), (c * rnd.uniform(1.0, 1.6), 0), (c * rnd.uniform(0.7, 1.1), c * rnd.uniform(0.5, 0.9)), (0, c * rnd.uniform(1.0, 1.6))])
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    fade = np.clip((S - xx) / (S * 0.03), 0, 1) * np.clip((S - yy) / (S * 0.03), 0, 1)
    return g.render(fade)


def diag(seed, sev, mirror=False):
    """Longue fracture en biais, d'un bord du verre à l'autre (gabarit « bande » 512 x 1024)."""
    w, h = 512, 1024
    g = Glass(w, h, seed)
    rnd = g.rnd
    x, y = w * rnd.uniform(0.04, 0.2), -4
    tx, ty = w * rnd.uniform(0.78, 0.96), h + 4
    a = math.atan2(ty - y, tx - x)
    pts = [(x, y)]
    while y < h + 4:
        # Rappel vers la cible : la fracture serpente mais traverse.
        a = a * 0.86 + math.atan2(ty - y, tx - x) * 0.14 + rnd.gauss(0, 0.07)
        if rnd.random() < 0.06:
            a += rnd.uniform(-0.5, 0.5)
        s = rnd.uniform(9, 20)
        x += math.cos(a) * s
        y += math.sin(a) * s
        pts.append((x, y))
    g.stroke(pts, 1.35, 1.1, 1.0, 0.85)
    for _ in range((1, 4, 8)[sev - 1]):
        i = rnd.randint(3, len(pts) - 3)
        b = math.atan2(pts[i][1] - pts[i - 1][1], pts[i][0] - pts[i - 1][0]) + rnd.choice([-1, 1]) * rnd.uniform(0.35, 1.1)
        g.crack(pts[i][0], pts[i][1], b, h * rnd.uniform(0.06, 0.3) * (0.7, 1, 1.3)[sev - 1], 1.4, branch=0.3)
    if sev >= 3:
        # Seconde fracture, presque parallèle, qui rejoint la première.
        i = rnd.randint(len(pts) // 4, len(pts) // 2)
        g.crack(pts[i][0], pts[i][1], a + rnd.uniform(0.15, 0.35), h * 0.7, 1.6, branch=0.3, jitter=0.03)
    img = g.render()
    return img.transpose(Image.FLIP_LEFT_RIGHT) if mirror else img


def press(seed, sev):
    """Fêlure de pression (châssis plié) : longues lignes courbes presque parallèles, sans point d'impact."""
    g = Glass(S, S, seed)
    rnd = g.rnd
    base = rnd.uniform(-0.3, 0.3)
    for i in range((1, 2, 4)[sev - 1]):
        y = S * (0.5 + (i - 1.2) * rnd.uniform(0.07, 0.12))
        bend = rnd.uniform(0.0006, 0.0014) * rnd.choice([1, 1, -1])
        L = S * rnd.uniform(0.55, 0.9) * (0.75, 1, 1.15)[sev - 1]
        g.crack(S * 0.5 - L / 2, y, base - bend * L / 2, L, 1.9, branch=(0.05, 0.15, 0.25)[sev - 1], jitter=0.018, kink=0.02, bend=bend)
    return g.render(radial_fade(S, S, S / 2, S / 2, S * 0.4, S * 0.49))


def ink_blot(w, h, seed, ox, oy, r, tendrils=()):
    """Fuite de cristaux liquides : tache noire aux contours irréguliers, franges violettes puis bleu-vert au bord.
    tendrils : chemins de fêlures le long desquels l'encre a filé."""
    rng = random.Random(seed)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    f = np.zeros((h, w), np.float32)
    for _ in range(rng.randint(5, 9)):
        a, d = rng.uniform(0, 6.283), r * rng.uniform(0, 0.75)
        rr = r * rng.uniform(0.35, 0.8)
        f += np.exp(-((xx - ox - math.cos(a) * d) ** 2 + (yy - oy - math.sin(a) * d) ** 2) / (2 * rr * rr))
    t = Image.new("L", (w, h), 0)
    dt = ImageDraw.Draw(t)
    for pts in tendrils:
        for i in range(1, len(pts)):
            k = 1 - i / len(pts)
            dt.line([pts[i - 1], pts[i]], fill=int(255 * k), width=max(2, int(r * 0.22 * k)))
    f = f / max(1e-6, f.max()) + blur(arr(t), r * 0.05) * 0.9
    # Contour déchiqueté : bruit large (lobes) et bruit fin (dentelures), jamais un ovale lisse.
    f = f * (0.55 + 0.9 * fbm(w, h, seed + 1, 5, 6)) + (fbm(w, h, seed + 2, 3, 40) - 0.5) * 0.1
    core = np.clip((f - 0.42) / 0.012, 0, 1)
    # Frange fine (2 à 6 px) : violet sombre puis bleu, irrégulière ; par endroits absente.
    k = fbm(w, h, seed + 6, 3, 9)
    rim1 = np.clip((f - 0.395) / 0.02, 0, 1) * (1 - core)
    rim2 = np.clip((f - 0.36) / 0.03, 0, 1) * (1 - np.clip((f - 0.395) / 0.02, 0, 1)) * np.clip((k - 0.35) * 3, 0, 1)
    col = np.zeros((h, w, 3), np.float32)
    col += np.array([3, 3, 6], np.float32) * core[..., None]
    col += np.array([58, 30, 104], np.float32) * rim1[..., None]
    col += np.array([40, 84, 140], np.float32) * rim2[..., None]
    alpha = np.clip(core * 0.985 + rim1 * 0.85 + rim2 * 0.35, 0, 1)
    # Lignes mortes qui partent de la tache (colonnes et rangées de la dalle coupées par le choc).
    for _ in range(rng.randint(2, 4)):
        vert = rng.random() < 0.6
        c = int((ox if vert else oy) + rng.uniform(-0.7, 0.7) * r)
        lc = np.array(rng.choice([(4, 4, 6), (30, 220, 90), (210, 40, 200), (230, 232, 235)]), np.float32)
        la = rng.uniform(0.55, 0.9)
        lo, hi = (0, h) if rng.random() < 0.5 else ((0, int(oy)) if rng.random() < 0.5 else (int(oy), h))
        if not vert:
            lo, hi = (0, w) if hi - lo == h else ((0, int(ox)) if lo == 0 else (int(ox), w))
        for d in range(rng.choice([2, 2, 3])):
            if vert and 0 <= c + d < w:
                m = (1 - core[lo:hi, c + d])
                col[lo:hi, c + d] = col[lo:hi, c + d] * (1 - m[:, None]) + lc * m[:, None]
                alpha[lo:hi, c + d] = np.maximum(alpha[lo:hi, c + d], la * m)
            if not vert and 0 <= c + d < h:
                m = (1 - core[c + d, lo:hi])
                col[c + d, lo:hi] = col[c + d, lo:hi] * (1 - m[:, None]) + lc * m[:, None]
                alpha[c + d, lo:hi] = np.maximum(alpha[c + d, lo:hi], la * m)
    tot = np.clip(core + rim1 + rim2, 1e-3, None)
    col = np.where((core + rim1 + rim2)[..., None] > 0.02, col / np.clip(tot, 0.02, 1)[..., None] * np.clip(tot, 0, 1)[..., None] / np.clip(tot, 1e-3, 1)[..., None], col)
    return np.clip(col, 0, 255), alpha


def bullet(seed, sev):
    """Balle ou éclat qui a percé : trou noir aux bords déchiquetés, couronne de verre pilé, rayons serrés, dalle morte
    autour (encre)."""
    g = Glass(S, S, seed)
    rnd = g.rnd
    r = S * (0.016, 0.022, 0.03)[sev - 1]
    n = (12, 17, 23)[sev - 1]
    a0 = rnd.uniform(0, 6.283)
    rays = []
    for i in range(n):
        a = a0 + 6.283 * (i + rnd.uniform(-0.35, 0.35)) / n
        L = S * min(1.2, rnd.lognormvariate(-1.2, 0.55) * (0.7, 1.2, 1.9)[sev - 1] + 0.08)
        rays.append(g.crack(OX + math.cos(a) * r, OY + math.sin(a) * r, a, L, 2.0, branch=0.35, jitter=0.04))
    g.rings(rays, OX, OY, [r * k for k in ((1.9, 2.9), (1.8, 2.7, 3.8, 5.2), (1.7, 2.5, 3.5, 4.8, 6.5, 8.5))[sev - 1]], 0.85, w0=1.4)
    g.crush(OX, OY, r * 2.4, int(r * r * 1.6))
    # Trou : polygone déchiqueté.
    k = 14
    poly = [(OX + math.cos(6.283 * i / k) * r * rnd.uniform(0.7, 1.25), OY + math.sin(6.283 * i / k) * r * rnd.uniform(0.7, 1.25)) for i in range(k)]
    g.dh.polygon([(a * SS, b * SS) for a, b in poly], fill=255)
    ink = ink_blot(S, S, seed + 3, OX + rnd.uniform(-r, r), OY + rnd.uniform(-r, r), r * (2.2, 3.2, 4.6)[sev - 1], [p[: max(3, len(p) // 3)] for p in rnd.sample(rays, 3)])
    return g.render(edge_fade(S, S, 0.02), ink)


def crush_panel(seed, sev):
    """Dalle écrasée : grande tache d'encre noire partie du choc, quelques fêlures seulement."""
    g = Glass(S, S, seed)
    rnd = g.rnd
    rays = []
    a0 = rnd.uniform(0, 6.283)
    n = (4, 6, 8)[sev - 1]
    for i in range(n):
        a = a0 + 6.283 * (i + rnd.uniform(-0.3, 0.3)) / n
        rays.append(g.crack(OX, OY, a, S * rnd.uniform(0.12, 0.4) * (0.7, 1, 1.4)[sev - 1], 1.5, branch=0.3))
    g.crush(OX, OY, S * 0.014)
    ink = ink_blot(S, S, seed + 3, OX + rnd.uniform(-20, 20), OY + rnd.uniform(-20, 20), S * (0.1, 0.17, 0.27)[sev - 1], [p[: max(3, len(p) * 2 // 3)] for p in rays[: n // 2]])
    return g.render(edge_fade(S, S, 0.02), ink)


def abrade(seed):
    """Verre rayé et dépoli : faisceau de rayures très fines, presque parallèles, et quelques-unes en travers. Blanc pur :
    le jeu règle l'opacité (surtout visible dans le reflet)."""
    rnd = random.Random(seed)
    im = Image.new("L", (S * SS, S * SS), 0)
    d = ImageDraw.Draw(im)
    base = rnd.uniform(0, math.pi)
    for i in range(150):
        a = base + rnd.gauss(0, 0.12) if i < 115 else rnd.uniform(0, math.pi)
        x, y = rnd.uniform(0.1, 0.9) * S, rnd.uniform(0.1, 0.9) * S
        L = S * min(0.6, rnd.lognormvariate(-2.6, 0.8))
        pts = [(x, y)]
        for k in range(1, 9):
            pts.append((x + math.cos(a) * L * k / 8 + rnd.gauss(0, 0.6), y + math.sin(a) * L * k / 8 + rnd.gauss(0, 0.6)))
        v = rnd.randint(40, 190)
        for k in range(8):
            d.line([pts[k][0] * SS, pts[k][1] * SS, pts[k + 1][0] * SS, pts[k + 1][1] * SS], fill=int(v * rnd.uniform(0.6, 1)), width=rnd.choice([1, 2, 2, 3]))
    a = arr(im.resize((S, S), Image.LANCZOS))
    haze = np.clip(fbm(S, S, seed + 2, 3, 3) - 0.55, 0, 1) * 0.35
    return rgba((235, 240, 244), (a + haze) * radial_fade(S, S, S / 2, S / 2, S * 0.36, S * 0.49))


def peel(seed):
    """Film de protection qui se décolle depuis un coin : poche d'air laiteuse, bord net du décollement, bulles et
    poussières piégées, liseré clair de la tranche du film."""
    rng = random.Random(seed)
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    n = fbm(S, S, seed, 4, 3)
    d = np.hypot(xx * rng.uniform(0.9, 1.25), yy * rng.uniform(0.9, 1.25)) / S + (n - 0.5) * 0.22
    lift = np.clip((0.6 - d) / 0.012, 0, 1)
    edge = np.clip(1 - np.abs(d - 0.6) / 0.006, 0, 1)
    fr = (np.sin(d * 150 + n * 9) * 0.5 + 0.5) * lift * np.clip((d - 0.3) / 0.3, 0, 1)
    alpha = lift * (0.1 + 0.07 * n) + edge * 0.55 + fr * 0.06
    col = np.ones((S, S, 3), np.float32) * np.array([226, 232, 236], np.float32)
    im = Image.new("L", (S, S), 0)
    dr = ImageDraw.Draw(im)
    for _ in range(26):
        a, rr = rng.uniform(0.1, 1.45), rng.uniform(0.5, 0.95) * S
        x, y = math.cos(a) * rr, math.sin(a) * rr
        r = rng.uniform(3, 15)
        dr.ellipse([x - r, y - r * rng.uniform(0.7, 1.3), x + r, y + r], outline=rng.randint(120, 220), width=1)
        dr.point((x + rng.uniform(-1, 1), y + rng.uniform(-1, 1)), fill=255)
    alpha = np.maximum(alpha, blur(arr(im), 0.5) * 0.5)
    fade = np.clip((S - xx) / (S * 0.03), 0, 1) * np.clip((S - yy) / (S * 0.03), 0, 1)
    return rgba(col, alpha * fade)


def water(seed):
    """Eau entrée sous le verre : buée laiteuse en plaques, auréole (ligne de marée) au bord, perles de condensation."""
    rng = random.Random(seed)
    n = fbm(S, S, seed, 5, 3)
    f = n * radial_fade(S, S, S / 2, S / 2, S * 0.12, S * 0.47)
    fog = np.clip((f - 0.25) / 0.3, 0, 1)
    tide = np.clip(1 - np.abs(f - 0.27) / 0.02, 0, 1)
    fine = fbm(S, S, seed + 4, 3, 40)
    alpha = fog * (0.16 + 0.14 * fine) + tide * 0.3
    col = np.ones((S, S, 3), np.float32) * np.array([214, 224, 228], np.float32)
    drops = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    dd = ImageDraw.Draw(drops)
    for _ in range(380):
        x, y = rng.uniform(0, S), rng.uniform(0, S)
        if fog[int(y) % S, int(x) % S] < 0.5:
            continue
        r = min(9.0, rng.lognormvariate(0.6, 0.6))
        dd.ellipse([x - r, y - r, x + r, y + r], fill=(60, 70, 76, 70), outline=(20, 26, 30, 150))
        dd.ellipse([x - r * 0.5, y - r * 0.6, x - r * 0.05, y - r * 0.15], fill=(250, 252, 255, 200))
    out = rgba(col, alpha)
    out.alpha_composite(drops.filter(ImageFilter.GaussianBlur(0.5)))
    return out


def heat(seed):
    """Brûlure de chaleur : plage jaunie puis brunie au centre, bords fondus, dalle marbrée."""
    n = fbm(S, S, seed, 4, 3)
    f = radial_fade(S, S, S / 2, S / 2, 0, S * 0.47) * (0.55 + 0.75 * n)
    a = np.clip((f - 0.2) / 0.5, 0, 1)
    core = np.clip((f - 0.62) / 0.25, 0, 1)
    col = np.array([214, 168, 52], np.float32) * (1 - core[..., None]) + np.array([92, 52, 22], np.float32) * core[..., None]
    mott = fbm(S, S, seed + 3, 3, 24)
    return rgba(col, blur(a, 6) * (0.34 + 0.3 * core) * (0.8 + 0.4 * mott))


# ------------------------------------------------------------------------------------------------------------ dalle

def burn_in(seed):
    """Image fantôme (marquage) en repère portrait : barre d'état, pastilles d'icônes, barre de navigation, clavier."""
    w, h = 512, 1024
    im = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, w, 34], fill=120)
    for x in (22, 60, 98):
        d.rectangle([x, 9, x + 24, 25], fill=230)
    for x in (w - 46, w - 86, w - 120):
        d.rectangle([x, 9, x + 26, 25], fill=230)
    d.rectangle([0, h - 70, w, h], fill=105)
    for x in (w * 0.2, w * 0.5, w * 0.8):
        d.ellipse([x - 17, h - 52, x + 17, h - 18], outline=235, width=4)
    for r in range(3):
        for c in range(10):
            d.rounded_rectangle([8 + c * 50, h - 300 + r * 62, 50 + c * 50, h - 248 + r * 62], 6, outline=130, width=2)
    a = blur(arr(im), 1.0) * (0.75 + 0.25 * fbm(w, h, seed, 3, 8))
    return rgba((236, 232, 220), a * 0.55)


def dim_zone(seed):
    """Zone de rétroéclairage faible : plage sombre aux bords très doux, un peu marbrée."""
    s = 512
    f = radial_fade(s, s, s / 2, s / 2, 0, s * 0.48) * (0.6 + 0.6 * fbm(s, s, seed, 3, 2))
    return rgba((0, 0, 0), blur(np.clip((f - 0.18) / 0.5, 0, 1), 8) * 0.9)


# ------------------------------------------------------------------------------------------------------------- bugs

def tear(seed):
    """Déchirure d'image : bandes horizontales décalées (double trait clair et sombre aux coupures, liserés de couleur)."""
    w, h = 512, 512
    rng = random.Random(seed)
    col = np.zeros((h, w, 3), np.float32)
    al = np.zeros((h, w), np.float32)
    y = rng.randint(10, 80)
    while y < h - 10:
        bh = rng.randint(3, 46)
        off = rng.randint(8, 70) * rng.choice([-1, 1])
        c = rng.choice([(40, 230, 255), (255, 60, 200), (235, 240, 245), (60, 255, 120)])
        al[y:y + 1, :] = 0.8
        col[y:y + 1, :] = c
        al[y + bh:y + bh + 1, :] = 0.55
        col[y + bh:y + bh + 1, :] = (8, 8, 10)
        # Bande décalée : voile léger et morceau manquant (noir) du côté d'où l'image a glissé.
        al[y + 1:y + bh, :] = 0.1
        col[y + 1:y + bh, :] = c
        if off > 0:
            al[y + 1:y + bh, :off] = 0.85
            col[y + 1:y + bh, :off] = (6, 6, 9)
        else:
            al[y + 1:y + bh, w + off:] = 0.85
            col[y + 1:y + bh, w + off:] = (6, 6, 9)
        y += bh + rng.randint(20, 150)
    return rgba(col, al)


def blocks(seed):
    """Macro-blocs (image compressée corrompue) : pavés de couleur unie par grappes, quadrillage 16 px."""
    rng = random.Random(seed)
    n = 32
    im = Image.new("RGBA", (n, n), (0, 0, 0, 0))
    px = im.load()
    for _ in range(rng.randint(5, 8)):
        cx, cy = rng.randint(0, n - 1), rng.randint(0, n - 1)
        base = rng.choice([(20, 200, 90), (220, 40, 190), (30, 30, 34), (120, 126, 130), (40, 90, 230), (232, 226, 60)])
        for _ in range(rng.randint(6, 26)):
            x, y = cx + rng.randint(-4, 4), cy + rng.randint(-1, 1)
            if 0 <= x < n and 0 <= y < n:
                j = rng.randint(-25, 25)
                px[x, y] = (max(0, min(255, base[0] + j)), max(0, min(255, base[1] + j)), max(0, min(255, base[2] + j)), rng.randint(170, 245))
    return im.resize((512, 512), Image.NEAREST)


def rgb_split(seed):
    """Décalage des couleurs : franges rouges et cyan en fines bandes verticales irrégulières (dalle mal synchronisée)."""
    w = h = 512
    rng = random.Random(seed)
    col = np.zeros((h, w, 3), np.float32)
    al = np.zeros((h, w), np.float32)
    for y in range(0, h, 2):
        if rng.random() < 0.55:
            continue
        x = rng.randint(0, w - 40)
        ln = rng.randint(30, 260)
        col[y, x:x + ln] = (255, 30, 40)
        al[y, x:x + ln] = rng.uniform(0.2, 0.5)
        if y + 1 < h:
            col[y + 1, x + 5:x + ln + 5] = (20, 235, 255)
            al[y + 1, x + 5:x + ln + 5] = rng.uniform(0.2, 0.5)
    return rgba(col, al)


def scan_bar():
    """Barre de balayage : bande claire au cœur doux, suivie d'une ombre."""
    w, h = 64, 256
    y = np.arange(h, dtype=np.float32)[:, None] / h
    a = np.exp(-((y - 0.4) / 0.1) ** 2) * 0.5
    dark = np.exp(-((y - 0.7) / 0.12) ** 2) * 0.35
    col = np.ones((h, w, 3), np.float32) * 235 * (a / np.clip(a + dark, 1e-3, None))[..., None]
    return rgba(col, np.repeat(a + dark, w, 1))


def static(seed):
    """Neige : grains clairs et sombres, lignes entières parfois."""
    rng = np.random.default_rng(seed)
    w = h = 256
    v = rng.random((h, w)).astype(np.float32)
    a = np.clip((rng.random((h, w)) - 0.45) * 2, 0, 1).astype(np.float32)
    rows = rng.random(h) < 0.08
    a[rows, :] = 0.9
    col = np.dstack([v * 255, v * 255, np.clip(v * 255 + 12, 0, 255)])
    return rgba(col, a * 0.9)


def ghost_tap():
    """Appui fantôme : anneau fin et point central, comme le témoin d'un appui."""
    s = 128
    yy, xx = np.mgrid[0:s, 0:s].astype(np.float32)
    d = np.hypot(xx - s / 2, yy - s / 2) / (s / 2)
    a = np.clip(1 - np.abs(d - 0.72) / 0.07, 0, 1) * 0.8 + np.clip((0.2 - d) / 0.1, 0, 1) * 0.6
    return rgba((225, 235, 245), a)


# -------------------------------------------------------------------------------------------------- boue et pluie

def mud(seed):
    """Éclaboussure de boue : amas épais aux bords irréguliers, satellites, plus claire et craquelée où elle est mince."""
    s = 512
    rng = random.Random(seed)
    im = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(im)
    cx, cy = s * rng.uniform(0.4, 0.6), s * rng.uniform(0.4, 0.6)
    ang = rng.uniform(0, 6.283)
    for _ in range(46):
        a = ang + rng.gauss(0, 0.9)
        dist = abs(rng.gauss(0, s * 0.14))
        r = max(2.0, rng.uniform(5, 38) * (1 - dist / (s * 0.45)))
        x, y = cx + math.cos(a) * dist, cy + math.sin(a) * dist
        d.ellipse([x - r, y - r * rng.uniform(0.6, 1), x + r, y + r], fill=rng.randint(170, 255))
    for _ in range(140):
        a = ang + rng.gauss(0, 0.6)
        dist = rng.uniform(0.12, 0.47) * s
        r = rng.uniform(0.8, 5) * (1.1 - dist / (s * 0.5))
        x, y = cx + math.cos(a) * dist, cy + math.sin(a) * dist
        d.ellipse([x - r, y - r, x + r * rng.uniform(1, 2.2), y + r], fill=rng.randint(150, 255))
    m = arr(im.filter(ImageFilter.GaussianBlur(1.6)))
    n = fbm(s, s, seed, 5, 6)
    cov = np.clip((m * (0.7 + 0.6 * n) - 0.3) / 0.1, 0, 1)
    thick = blur(cov, 7)
    col = np.array([116, 92, 62], np.float32) * (1 - thick[..., None]) + np.array([58, 44, 30], np.float32) * thick[..., None]
    col = col * (0.82 + 0.36 * fbm(s, s, seed + 8, 3, 50))[..., None]
    alpha = cov * (0.5 + 0.48 * thick) * radial_fade(s, s, s / 2, s / 2, s * 0.44, s * 0.5)
    return rgba(col, alpha)


def rain(seed):
    """Gouttes de pluie sur le verre : lentilles d'eau (bord sombre fin, reflet en croissant en haut à gauche, lueur en
    bas à droite), tailles très variées, quelques gouttes allongées qui ont glissé."""
    s = 512
    rng = random.Random(seed)
    im = Image.new("RGBA", (s * SS, s * SS), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    for _ in range(46):
        x, y = rng.uniform(0.04, 0.96) * s * SS, rng.uniform(0.04, 0.96) * s * SS
        r = min(15.0, rng.lognormvariate(1.3, 0.6)) * SS
        e = rng.uniform(1.0, 1.9) if rng.random() < 0.25 else rng.uniform(0.9, 1.1)
        box = [x - r, y - r * e, x + r, y + r * e]
        d.ellipse(box, fill=(30, 38, 44, 46), outline=(8, 12, 16, 150), width=max(1, int(r * 0.12)))
        d.chord([x - r * 0.72, y - r * e * 0.72, x + r * 0.72, y + r * e * 0.72], 20, 110, fill=(200, 225, 240, 70))
        d.ellipse([x - r * 0.52, y - r * e * 0.6, x - r * 0.12, y - r * e * 0.26], fill=(255, 255, 255, 215))
    return im.resize((s, s), Image.LANCZOS)


# ------------------------------------------------------------------------------------------------------------- sang

def blood_extras():
    """Sang resté dans les fêlures après un coup de chiffon (seep) et flaque qui s'accumule contre le bord du bas."""
    import gen_screen_fx as fx
    for i in range(2):
        g = Glass(512, 512, 4100 + i)
        rnd = g.rnd
        a0 = rnd.uniform(0, 6.283)
        for k in range(7):
            a = a0 + 6.283 * (k + rnd.uniform(-0.3, 0.3)) / 7
            g.crack(256, 256, a, 512 * rnd.uniform(0.12, 0.4), 3.2, branch=0.4, jitter=0.05)
        m = arr(g.line.resize((512, 512), Image.LANCZOS))
        m = np.clip(blur(m, 1.2) * 2.2, 0, 1) * radial_fade(512, 512, 256, 256, 180, 250)
        mask = Image.fromarray(np.dstack([np.full((512, 512, 3), 255, np.uint8), np.uint8(m * 255)]), "RGBA")
        save(fx.colorize_blood(mask, 4200 + i, 0.75), f"bloodc_seep_{i}")
        save(fx.colorize_blood(mask, 4200 + i, 0.75, dry=True), f"bloodd_seep_{i}")
    # Flaque du bas : ménisque contre le bord, dessus ondulé, plus épaisse là où les coulures arrivent.
    w, h = 512, 128
    rng = random.Random(4300)
    xs = np.arange(w, dtype=np.float32)
    top = 84 + 9 * np.sin(xs / 83 + rng.uniform(0, 6)) + 5 * np.sin(xs / 29 + rng.uniform(0, 6))
    top += np.interp(xs, [0, 110, w - 110, w], [44, 0, 0, 44])
    yy = np.mgrid[0:h, 0:w][0].astype(np.float32)
    m = np.clip((yy - top[None, :]) / 10, 0, 1) * np.clip(0.55 + (yy - top[None, :]) / 80, 0, 1)
    mask = Image.fromarray(np.dstack([np.full((h, w, 3), 255, np.uint8), np.uint8(m * 255)]), "RGBA")
    save(fx.colorize_blood(mask, 4301, 0.6, margin=0.0), "bloodc_puddle_0")
    save(fx.colorize_blood(mask, 4301, 0.6, dry=True, margin=0.0), "bloodd_puddle_0")


# Genres de verre : (nom, fonction (graine, gravité, variante), variantes). Lu aussi par gen_screen_mockups.py.
GLASS = [
    ("hair", lambda s, v, i: hair(s, v), 1),
    ("web", lambda s, v, i: web_point(s, v), 2),
    ("bull", lambda s, v, i: web_point(s, v, True), 1),
    ("corner", lambda s, v, i: corner(s, v), 2),
    ("chip", lambda s, v, i: corner(s, v, True), 1),
    ("diag", lambda s, v, i: diag(s, v, i == 1), 2),
    ("press", lambda s, v, i: press(s, v), 1),
    ("bullet", lambda s, v, i: bullet(s, v), 2),
    ("crush", lambda s, v, i: crush_panel(s, v), 1),
]


def main():
    for gi, (name, fn, nvar) in enumerate(GLASS):
        for i in range(nvar):
            for sev in (1, 2, 3):
                # Même graine pour les trois gravités : la cassure grandit depuis le même impact.
                save(fn(7000 + gi * 37 + i * 101, sev, i), f"gx_{name}_{i}_{sev}")
    for i in range(2):
        save(abrade(7600 + i), f"gx_abrade_{i}")
        save(water(7700 + i), f"gx_water_{i}")
        save(mud(7900 + i), f"dirt_mud_{i}")
        save(rain(7950 + i), f"dirt_rain_{i}")
        save(tear(8100 + i), f"gl_tear_{i}")
        save(blocks(8200 + i), f"gl_block_{i}")
        save(static(8400 + i), f"gl_static_{i}")
    save(peel(7650), "gx_peel_0")
    save(heat(7800), "gx_heat_0")
    save(burn_in(8000), "pf_burn_0")
    save(dim_zone(8050), "pf_dim_0")
    save(rgb_split(8300), "gl_rgb_0")
    save(scan_bar(), "gl_scan_0")
    save(ghost_tap(), "gl_tap_0")
    blood_extras()


if __name__ == "__main__":
    main()
