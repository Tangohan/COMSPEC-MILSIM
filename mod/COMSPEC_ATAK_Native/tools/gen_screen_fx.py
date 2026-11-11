#!/usr/bin/env python3
"""Textures en couleur de l'écran du téléphone : sang réaliste et variantes de dégâts / brouillage (fn_screenSurface,
fn_screenFx).

Usage : python3 gen_screen_fx.py [--sang] [dossier d'aperçus PNG]   (--sang : textures de sang seulement)
Sortie : Sources/addons/main/data/bloodc_*.paa, bloodd_*.paa, fx_*.paa (DXT5, tools/paa_dxt.py : ni HEMTT ni TexView).
Dépendances : Pillow, numpy (et lzallright ou python-lzo pour des PAA compressés).

Sang (bloodc_<genre>_<n>) : les masques de gen_dirt.py (éclaboussure, main, coulure, projection, nappe, traînée, doigt)
et ceux d'ici (grande nappe, coulure épaisse « run », jet artériel, main qui glisse « drag », essuyage « wipe »,
écran couvert « cover » et voile « veil » en version paysage et portrait) mis en couleur au lieu d'une teinte unique :
film mince rouge vif et translucide, cœur épais bordeaux presque opaque, bord plus sombre (sang qui sèche en premier),
caillots, reflets humides là où la goutte est bombée, fondu vers les bords de l'image (aucune arête droite). Chaque tache
a sa version séchée (bloodd_<genre>_<n>) : mate, brun-rouge, anneau du bord épais, craquelures et écailles. Le jeu
teinte la tache fraîche vers le sombre puis passe à la version séchée (fn_screenSurface).
Dégâts : fx_bleed_<n> (fuite de cristaux liquides : tache noire, franges violettes et vertes), fx_backlight_<n>_<port|land>
(fuite de rétroéclairage : halos blancs chauds contre les bords), fx_mura_<n> (arc-en-ciel de pression autour d'un choc),
fx_band_<n> (bandes de lignes mortes colorées).
Brouillage : fx_emi_<n> (4 trames de parasites : lignes de balayage, bandes déchirées, neige colorée).
"""
import math
import os
import random
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
SANG_SEUL = "--sang" in sys.argv
sys.argv = [a for a in sys.argv if a != "--sang"]
sys.argv = sys.argv[:1] + ["hemtt"] + sys.argv[1:]
import gen_dirt  # noqa: E402  (masques de sang existants)
from paa_dxt import save_paa  # noqa: E402

OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
PREVIEW = sys.argv[2] if len(sys.argv) > 2 else None


def save(img, name):
    save_paa(img, os.path.join(OUT, name + ".paa"))
    if PREVIEW:
        os.makedirs(PREVIEW, exist_ok=True)
        bg = Image.new("RGBA", img.size, (38, 44, 41, 255))
        bg.alpha_composite(img)
        bg.convert("RGB").save(os.path.join(PREVIEW, name + ".png"))
        os.makedirs(os.path.join(PREVIEW, "rgba"), exist_ok=True)
        img.save(os.path.join(PREVIEW, "rgba", name + ".png"))


def smooth(a, r):
    return np.asarray(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L").filter(ImageFilter.GaussianBlur(r)), dtype=np.float32) / 255


def fbm(w, h, seed, octaves=4, base=3):
    """Bruit fractal lisse 0..1, reproductible (graine numpy), cellules carrées quel que soit le rapport w / h."""
    rng = np.random.default_rng(seed)
    out = np.zeros((h, w), np.float32)
    amp, tot = 1.0, 0.0
    short = min(w, h)
    for o in range(octaves):
        cells = base * (2 ** o)
        gw, gh = max(2, int(cells * w / short) + 2), max(2, int(cells * h / short) + 2)
        g = Image.fromarray(np.uint8(rng.random((gh, gw)) * 255), "L").resize((w, h), Image.BICUBIC)
        out += amp * np.asarray(g, np.float32) / 255
        tot += amp
        amp *= 0.5
    out /= tot
    return np.clip((out - out.min()) / max(1e-6, out.max() - out.min()), 0, 1)


def edge_fade(h, w, margin):
    """Fondu vers les bords de la texture : jamais d'arête droite de l'image visible à l'écran."""
    m = max(2.0, margin * min(w, h))
    fy = np.clip(np.minimum(np.arange(h), np.arange(h)[::-1]) / m, 0, 1)
    fx = np.clip(np.minimum(np.arange(w), np.arange(w)[::-1]) / m, 0, 1)
    fy, fx = fy * fy * (3 - 2 * fy), fx * fx * (3 - 2 * fx)
    return np.outer(fy, fx).astype(np.float32)


def erode(a, n):
    m = Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L")
    for _ in range(max(1, int(n))):
        m = m.filter(ImageFilter.MinFilter(3))
    return np.asarray(m, np.float32) / 255


def cracks(w, h, seed, cell):
    """Craquelures du sang séché : bords de cellules de Voronoï (calcul à mi-résolution), et cellules tirées au sort
    qui se sont écaillées. Renvoie (fissures 0..1, écailles 0..1)."""
    rng = np.random.default_rng(seed)
    sw, sh = max(8, w // 2), max(8, h // 2)
    n = max(6, int(sw * sh / (cell / 2) ** 2))
    # Cellules de tailles très inégales (retour : motif de craquelures répété d'une tache à l'autre) : la moitié des
    # germes au hasard, l'autre moitié serrée autour de quelques foyers ; chaque tache a donc son propre dessin.
    pts = rng.random((n, 2)) * [sw, sh]
    foyers = rng.random((rng.integers(3, 7), 2)) * [sw, sh]
    half = n // 2
    pts[:half] = foyers[rng.integers(0, len(foyers), half)] + rng.normal(0, 0.09 * min(sw, sh), (half, 2))
    yy, xx = np.mgrid[0:sh, 0:sw].astype(np.float32)
    d = np.sqrt((xx[..., None] - pts[:, 0]) ** 2 + (yy[..., None] - pts[:, 1]) ** 2)
    idx = np.argsort(d, axis=2)[..., :2]
    d1 = np.take_along_axis(d, idx[..., :1], 2)[..., 0]
    d2 = np.take_along_axis(d, idx[..., 1:2], 2)[..., 0]
    edge = np.clip(1 - (d2 - d1) / 0.8, 0, 1)
    flake_cells = rng.random(n) < 0.12
    flake = flake_cells[idx[..., 0]].astype(np.float32) * np.clip((d2 - d1) / 3, 0, 1)
    up = lambda a: np.asarray(Image.fromarray(np.uint8(a * 255), "L").resize((w, h), Image.BILINEAR), np.float32) / 255
    return up(edge), up(flake)


def colorize_blood(mask_img, seed, film=0.55, dry=False, margin=0.03):
    """Masque blanc (alpha = quantité de sang) -> sang en couleur.
    Bords nets (le sang sur du verre ne fait pas de flou) ; l'épaisseur vient de la distance au bord : film mince
    près du bord, rouge vif et translucide (l'écran éclaire au travers), cœur épais bordeaux presque opaque ; anneau
    plus sombre sur le pourtour (le bord sèche en premier) ; caillots ; petits reflets humides sur le sang épais.
    dry : même tache séchée (bloodd_*) : brun-rouge mat, sans reflet, anneau du bord épais et foncé (le sang migre
    vers le bord en séchant), film mince presque transparent, craquelures et écailles dans les parties épaisses.
    margin : fondu vers les bords de l'image (part du petit côté)."""
    rnd = random.Random(seed)
    t = np.asarray(mask_img.split()[-1], dtype=np.float32) / 255
    h, w = t.shape
    px = max(1.0, min(w, h) / 256)
    # Couverture aux bords nets : seuil doux sur le masque.
    cov = np.clip((t - 0.22) / 0.16, 0, 1)
    cov = smooth(cov, 0.6 * px) * edge_fade(h, w, margin)
    # Épaisseur : érosions successives (distance au bord approchée) mélangées à la quantité du masque, variée par
    # un bruit lent (une grande nappe n'est pas d'un seul aplat).
    m = Image.fromarray(np.uint8(cov * 255), "L")
    acc = np.zeros_like(t)
    steps = int(6 * px)
    for k in range(steps):
        m = m.filter(ImageFilter.MinFilter(3))
        acc += np.asarray(m, dtype=np.float32) / 255
    var = fbm(w, h, seed + 17, 3, 2)
    thick = np.clip((smooth(acc / steps, 1.5 * px) * 0.75 + t * 0.35) * (0.7 + 0.55 * var), 0, 1)
    ring = np.clip(cov - erode(cov, 1), 0, 1)
    if dry:
        return _dry_rgba(t, cov, thick, seed, film, px)
    clot = gen_dirt.blood_noise(w, h, seed + 3, 5, 1.6)
    # Retours sur le rendu 4.4 : le bord rouge clair faisait « dessiné » et les reflets ronds et blancs ressemblaient
    # à des bulles. Le film mince est maintenant un rouge profond qui s'éclaircit seulement par transparence (on voit
    # l'écran au travers), sans liseré ; le cœur est presque noir.
    thin_c = np.array([150, 14, 16], np.float32)
    thick_c = np.array([70, 3, 7], np.float32)
    rim_c = np.array([58, 6, 6], np.float32)
    clot_c = np.array([30, 2, 3], np.float32)
    k = np.clip(thick * 2.2, 0, 1)[..., None] ** 0.8
    col = thin_c * (1 - k) + thick_c * k
    r = np.clip(ring * 0.5, 0, 1)[..., None]
    col = col * (1 - r) + rim_c * r
    cm = smooth(np.clip((clot - 0.68) * 3, 0, 1), 1.2) * np.clip(thick * 1.5, 0, 1)
    col = col * (1 - cm[..., None] * 0.45) + clot_c * (cm[..., None] * 0.45)
    # Reflets humides : pente de l'épaisseur éclairée d'en haut à gauche, à l'intérieur du sang épais seulement.
    core = np.clip((thick - 0.45) * 3, 0, 1)
    gy, gx = np.gradient(smooth(thick, max(2.0, w / 64)))
    light = np.clip(-(gx * 0.7 + gy * 0.7) * (w / 5), 0, 1) ** 2.2 * core
    # Reflet du ménisque : fin liseré rosé (jamais blanc) le long du bord bombé tourné vers la lumière, cassé par un
    # bruit pour ne pas faire un trait continu. Plus aucun reflet rond.
    gloss = np.clip(light * 0.9 * np.clip((fbm(w, h, seed + 41, 3, 9) - 0.3) * 2.2, 0, 1), 0, 0.42)[..., None]
    col = col * (1 - gloss) + np.array([226, 128, 118], np.float32) * gloss
    # Opacité : film mince translucide, cœur presque opaque, anneau du bord bien visible.
    # Bords minces et translucides : l'opacité monte avec l'épaisseur, sans marche au bord.
    alpha = cov * np.clip(film * 0.5 + (1 - film * 0.5) * np.clip(thick * 1.7, 0, 1) ** 0.85 + ring * 0.08, 0, 1)
    rgba = np.dstack([np.clip(col, 0, 255), alpha * 255]).astype(np.uint8)
    return Image.fromarray(rgba, "RGBA")


def _dry_rgba(t, cov, thick, seed, film, px):
    """Sang séché : brun-rouge mat, anneau du bord épais (effet « tache de café »), film mince presque transparent,
    craquelures et écailles dans les parties épaisses."""
    h, w = t.shape
    ring = np.clip(cov - erode(cov, 2 * px), 0, 1)
    edge, flake = cracks(w, h, seed + 29, (8 + (seed * 7) % 9) * px)
    # Craquelures seulement là où le sang était épais, irrégulières (masquées par un bruit).
    deep = np.clip((thick - 0.55) * 3, 0, 1) * np.clip((fbm(w, h, seed + 31, 3, 4) - 0.35) * 3, 0, 1)
    thin_c = np.array([96, 30, 20], np.float32)
    thick_c = np.array([48, 10, 8], np.float32)
    rim_c = np.array([30, 6, 5], np.float32)
    k = np.clip(thick * 2.0, 0, 1)[..., None]
    col = thin_c * (1 - k) + thick_c * k
    r = np.clip(ring * 1.2, 0, 1)[..., None]
    col = col * (1 - r) + rim_c * r
    # Fissures : fines et un peu plus claires (on voit l'écran au travers) ; écailles : il reste un voile.
    cr = (edge * deep)[..., None]
    col = col * (1 - cr * 0.3) + np.array([120, 52, 38], np.float32) * (cr * 0.3)
    alpha = cov * np.clip(film * 0.55 + (1 - film * 0.55) * np.clip(thick * 1.6, 0, 1) * 0.95 + ring * 0.4, 0, 1)
    alpha *= 1 - 0.35 * edge * deep
    alpha *= 1 - 0.45 * flake * deep
    return Image.fromarray(np.dstack([np.clip(col, 0, 255), np.clip(alpha, 0, 1) * 255]).astype(np.uint8), "RGBA")


def film_rgba(a, seed, dry=False, margin=0.02):
    """Film de sang étalé (voile, essuyage) : translucide, l'écran reste lisible au travers. a : quantité 0..1."""
    h, w = a.shape
    a = np.clip(a, 0, 1) * edge_fade(h, w, margin)
    var = fbm(w, h, seed + 5, 4, 3)
    if dry:
        col = np.array([118, 36, 24], np.float32) * (1 - a[..., None] * 0.5) + np.array([60, 14, 10], np.float32) * (a[..., None] * 0.5)
        alpha = a * (0.42 + 0.25 * var)
    else:
        col = np.array([205, 30, 24], np.float32) * (1 - a[..., None] * 0.6) + np.array([110, 8, 10], np.float32) * (a[..., None] * 0.6)
        alpha = a * (0.5 + 0.3 * var)
    return Image.fromarray(np.dstack([np.clip(col, 0, 255), np.clip(alpha, 0, 1) * 255]).astype(np.uint8), "RGBA")


def _draw_run(d, x, y, length, width, rnd, fill=235, wobble=0.35):
    """Coulure tracée sur un ImageDraw (vers le bas) : largeur qui varie, renflements, perle au bout."""
    bulge = rnd.uniform(0, 6.28)
    for k in range(int(length)):
        tt = k / max(1, length)
        x += rnd.uniform(-wobble, wobble)
        ww = width * (1 - 0.45 * tt) * (0.85 + 0.25 * math.sin(k / max(4, width * 2.5) + bulge))
        d.ellipse([x - ww / 2, y + k - ww / 2, x + ww / 2, y + k + ww / 2], fill=fill)
    br = width * 0.8
    d.ellipse([x - br, y + length - br, x + br, y + length + br * 1.4], fill=min(255, fill + 10))
    return x


def to_img(a):
    return gen_dirt.white(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L"))


def blobs(w, h, rnd, n, rmin, rmax, cx=(0.5, 0.2), cy=(0.5, 0.2)):
    """Somme de taches gaussiennes (rayons en part du petit côté)."""
    s = min(w, h)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    f = np.zeros((h, w), np.float32)
    for _ in range(n):
        x = (cx[0] + rnd.uniform(-cx[1], cx[1])) * w
        y = (cy[0] + rnd.uniform(-cy[1], cy[1])) * h
        r = rnd.uniform(rmin, rmax) * s
        f += np.exp(-((xx - x) ** 2 + (yy - y) ** 2) / (2 * r * r)) * rnd.uniform(0.7, 1.1)
    return f


def runs_from(a, rnd, count, max_len, width, fill=235):
    """Coulures qui partent du bas des zones de sang et descendent (sang qui glisse sur le verre)."""
    h, w = a.shape
    img = Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L")
    d = ImageDraw.Draw(img)
    for _ in range(count):
        for _try in range(20):
            x = rnd.randint(int(w * 0.06), int(w * 0.94))
            col = np.nonzero(a[:, x] > 0.6)[0]
            if len(col):
                break
        else:
            continue
        y = int(col.max()) - int(width)
        if y > h * 0.9:
            continue
        length = min(rnd.uniform(0.3, 1) * max_len, h * 0.96 - y - width * 2)
        if length > width * 2:
            _draw_run(d, x, y, length, width * rnd.uniform(0.7, 1.2), rnd, fill)
    return np.asarray(img, np.float32) / 255


def pool_big(seed, s=512):
    """Grande nappe (sang qui a coulé et s'est étalé) : plusieurs lobes, bord irrégulier, coulures, gouttes autour."""
    rnd = random.Random(seed)
    f = blobs(s, s, rnd, rnd.randint(5, 8), 0.09, 0.2, (0.5, 0.17), (0.44, 0.14))
    n = fbm(s, s, seed, 5, 3)
    a = np.clip((f + (n - 0.5) * 0.9 - 0.5) * 7, 0, 1)
    a = runs_from(a, rnd, rnd.randint(2, 4), s * 0.3, s * 0.03)
    img = Image.fromarray(np.uint8(a * 255), "L")
    d = ImageDraw.Draw(img)
    for _ in range(rnd.randint(14, 26)):
        ang, dist = rnd.uniform(0, 2 * math.pi), rnd.uniform(0.3, 0.46) * s
        x, y, r = s / 2 + math.cos(ang) * dist, s * 0.44 + math.sin(ang) * dist * 0.85, rnd.uniform(1.5, 7) * s / 256
        d.ellipse([x - r, y - r, x + r, y + r], fill=230)
    a = np.asarray(img.filter(ImageFilter.GaussianBlur(1.2)), np.float32) / 255
    return to_img(gen_dirt.blood_body(a, seed, 0.5) * (0.8 + 0.25 * fbm(s, s, seed + 9, 4, 5)))


def cover(seed, w, h):
    """Écran presque couvert (extrême) : grandes nappes réunies (70 à 80 % de l'écran), trouées par endroits, deux ou
    trois passages de doigts qui ont dégagé un peu de verre, coulures qui partent du bas des nappes."""
    rnd = random.Random(seed)
    s = min(w, h)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    # Champ déformé (sang qui a coulé, bords fluides et non des taches rondes) : bruit à grande échelle + grosses
    # taches, le tout déplacé par un second bruit.
    wx = (fbm(w, h, seed + 7, 3, 2) - 0.5) * 0.18 * s
    wy = (fbm(w, h, seed + 8, 3, 2) - 0.5) * 0.18 * s
    f = blobs(w, h, rnd, rnd.randint(9, 14), 0.14, 0.34)
    f = f / f.max()
    n = fbm(w, h, seed, 5, 2)
    field = 0.55 * f + 0.75 * n
    ix = np.clip(xx + wx, 0, w - 1).astype(np.int32)
    iy = np.clip(yy + wy, 0, h - 1).astype(np.int32)
    field = field[iy, ix]
    thr = np.quantile(field, 1 - rnd.uniform(0.72, 0.82))
    a = np.clip((field - thr) * 16, 0, 1)
    # Quelques trous seulement (bulles, verre resté propre), de tailles variées.
    holes = fbm(w, h, seed + 1, 3, 5)
    a *= np.clip((holes - 0.07) * 8, 0, 1)
    # Passages de doigts : un ou deux arcs (pouce qui a voulu dégager l'écran), il n'y reste qu'un film strié.
    for _ in range(rnd.randint(1, 2)):
        cxr, cyr = rnd.uniform(0.2, 0.8) * w, rnd.uniform(0.9, 1.6) * h
        rad = rnd.uniform(0.6, 1.0) * h
        wid = rnd.uniform(0.045, 0.07) * s
        dist = np.sqrt((xx - cxr) ** 2 + (yy - cyr) ** 2) - rad
        ang = np.arctan2(yy - cyr, xx - cxr)
        a0 = rnd.uniform(-2.2, -1.6)
        span = rnd.uniform(0.5, 0.9)
        along = np.clip((ang - a0) / span, 0, 1)
        inside = np.clip(1 - np.abs(dist) / wid, 0, 1) * np.clip(along * 8, 0, 1) * np.clip((1 - along) * 8, 0, 1)
        streak = np.clip((np.sin(dist / wid * rnd.uniform(10, 16) + n * 4) - 0.1) * 2, 0, 1)
        a = a * (1 - inside * 0.9) + inside * 0.45 * streak * (a > 0.1)
    a = runs_from(a, rnd, rnd.randint(7, 12), h * 0.4, s * 0.022)
    a = np.asarray(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L").filter(ImageFilter.GaussianBlur(1.0)), np.float32) / 255
    return to_img(gen_dirt.blood_body(a, seed, 0.4) * (0.82 + 0.22 * fbm(w, h, seed + 4, 4, 6)))


def veil(seed, w, h):
    """Voile mince (sous les nappes en extrême) : sang étalé partout, plus épais par plaques, écran lisible au travers."""
    n = fbm(w, h, seed, 5, 3)
    m = fbm(w, h, seed + 2, 3, 12)
    return np.clip(0.25 + 0.75 * n, 0, 1) * np.clip(0.55 + 0.6 * m, 0, 1)


def run(seed):
    """Coulure épaisse (64 x 512) : renflement en haut, traînée qui ondule et s'amincit, perle au bout. Le jeu en met
    plusieurs bout à bout : la coulure avance vers le bas de l'écran tant que le sang est frais."""
    rnd = random.Random(seed)
    w, h = 64, 512
    img = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(img)
    x = w / 2 + rnd.uniform(-4, 4)
    wid = rnd.uniform(15, 20)
    # Pas de goutte en haut : la coulure part d'une nappe ou du segment précédent (haut effilé dans le fondu).
    _draw_run(d, x, 4, h * 0.88, wid, rnd, 232, 0.5)
    a = np.asarray(img.filter(ImageFilter.GaussianBlur(1.1)), np.float32) / 255
    a *= np.clip(np.arange(h) / (h * 0.06), 0, 1)[:, None]
    return to_img(gen_dirt.blood_body(a, seed, 0.35))


def arterial(seed):
    """Jet artériel (512 x 256) : trait épais qui ondule au milieu, gouttes étirées dans le sens du jet de part et
    d'autre, brume fine autour."""
    rnd = random.Random(seed)
    w, h = 512, 256
    img = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(img)
    y0, amp, ph, slope = h * rnd.uniform(0.4, 0.6), h * rnd.uniform(0.05, 0.14), rnd.uniform(0, 6), rnd.uniform(-0.12, 0.12)
    path = lambda x: y0 + amp * math.sin(x / w * 4 + ph) + (x - w / 2) * slope
    # Trait continu (pulsation : plus épais par endroits).
    xa, xb = w * rnd.uniform(0.12, 0.25), w * rnd.uniform(0.6, 0.78)
    for x in np.arange(xa, xb, 1.0):
        ww = (7 + 8 * abs(math.sin(x / w * 11))) * (1 - 0.45 * (x - xa) / (xb - xa))
        y = path(x) + rnd.uniform(-0.4, 0.4)
        d.ellipse([x - ww / 2, y - ww / 2, x + ww / 2, y + ww / 2], fill=235)
    # Gouttes étirées tout le long, plus grosses au début, plus loin du trait vers la fin.
    for _ in range(rnd.randint(220, 300)):
        x = rnd.uniform(0.03, 0.97) * w
        spread = (5 + 26 * (x / w)) * rnd.gauss(0, 1)
        y = path(x) + spread
        r = max(1.0, rnd.expovariate(1 / 3.2)) * (1.4 - 0.7 * x / w)
        r = min(r, 11)
        dy = (path(x + 4) - path(x)) / 4
        tail = r * rnd.uniform(1.5, 4)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(190, 245))
        d.line([x - tail, y - dy * tail, x, y], fill=rnd.randint(140, 210), width=max(1, int(r * 0.8)))
    for _ in range(rnd.randint(250, 350)):
        x = rnd.uniform(0, 1) * w
        y = path(x) + rnd.gauss(0, 30)
        r = rnd.uniform(0.5, 1.3)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(150, 220))
    a = np.asarray(img.filter(ImageFilter.GaussianBlur(0.7)), np.float32) / 255
    return to_img(a)


def drag(seed):
    """Main en sang qui a glissé vers le bas (256 x 512) : bouts de doigts et paume en haut, traînées striées des
    doigts et du talon de la main qui s'éclaircissent et se cassent en descendant."""
    rnd = random.Random(seed)
    w, h = 256, 512
    img = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(img)
    cx = w / 2 + rnd.uniform(-10, 10)
    tips = []
    for f in range(4):
        x = cx - 60 + f * 38 + rnd.uniform(-5, 5)
        y = 40 + [14, 0, 6, 26][f] + rnd.uniform(-5, 5)
        wid = [26, 28, 27, 22][f]
        d.ellipse([x - wid / 2, y - wid * 0.7, x + wid / 2, y + wid * 0.9], fill=235)
        tips.append((x, y, wid))
    # Paume (talon de la main), sous les doigts.
    for _ in range(7):
        r = rnd.uniform(22, 36)
        x, y = cx + rnd.uniform(-45, 45), 150 + rnd.uniform(-25, 25)
        d.ellipse([x - r, y - r * 0.8, x + r, y + r * 0.8], fill=225)
    tips.append((cx - 20, 170, 60))
    tips.append((cx + 30, 175, 50))
    a = np.asarray(img.filter(ImageFilter.GaussianBlur(2.5)), np.float32) / 255
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    streaks = np.zeros_like(a)
    for (x, y, wid) in tips:
        length = rnd.uniform(0.45, 0.75) * h
        drift = rnd.uniform(-0.06, 0.06)
        cxl = x + (yy - y) * drift
        band = np.clip(1 - np.abs(xx - cxl) / (wid / 2), 0, 1) ** 0.4
        along = np.clip((yy - y) / length, 0, 1)
        fade = (1 - along) ** 0.7 * (yy > y)
        streaks = np.maximum(streaks, band * fade)
    ridges = 0.7 + 0.3 * np.sin(xx * rnd.uniform(0.7, 0.95) + fbm(w, h, seed, 3, 4) * 8)
    broken = np.clip((fbm(w, h, seed + 3, 4, 5) - 0.15) * 4, 0, 1)
    a = np.maximum(a, streaks * ridges * broken)
    return to_img(gen_dirt.blood_body(np.clip(a * 1.05, 0, 1), seed, 0.35))


def wipe(seed):
    """Essuyage (512 x 256) : bande de sang étalée par un doigt ou une manche, striée dans le sens du geste, plus
    chargée au bout du geste (bourrelet), le début presque effacé. Film translucide (film_rgba)."""
    rnd = random.Random(seed)
    w, h = 512, 256
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    y0, amp, ph = h * rnd.uniform(0.4, 0.6), h * rnd.uniform(0.04, 0.12), rnd.uniform(0, 6)
    cy = y0 + amp * np.sin(xx / w * 2.5 + ph)
    wid = h * rnd.uniform(0.22, 0.34)
    band = np.clip(1 - np.abs(yy - cy) / wid, 0, 1) ** 0.7
    tt = xx / w
    load = np.clip(0.15 + 0.85 * tt ** 1.5, 0, 1) * np.clip((0.97 - tt) / 0.05, 0, 1) * np.clip(tt / 0.06, 0, 1)
    # Stries : bruit 1D le long de la largeur, étiré dans le sens du geste (traits nets, pas un flou).
    rng = np.random.default_rng(seed)
    prof = np.asarray(Image.fromarray(np.uint8(rng.random((h // 3, 1)) * 255), "L").resize((1, h), Image.BILINEAR), np.float32)[:, 0] / 255
    off = (cy - y0).astype(np.int32)
    streak = prof[np.clip(yy.astype(np.int32) - off, 0, h - 1)]
    streak = 0.25 + 0.75 * np.clip((streak - 0.3) * 2.2, 0, 1)
    ridge = np.exp(-((tt - 0.9) ** 2) / 0.0012) * band * 0.8
    return np.clip(band * load * streak * 0.95 + ridge, 0, 1)


def p2(m):
    sz = m.size
    p = tuple(1 << max(2, (s - 1).bit_length()) for s in sz)
    return m if p == sz else m.resize(p, Image.BICUBIC)


def blood():
    """Chaque tache existe fraîche (bloodc_*, brillante) et séchée (bloodd_*, mate, craquelée) : même masque, deux
    mises en couleur ; le jeu passe de l'une à l'autre quand le sang sèche (fn_screenSurface)."""
    gens = [
        ("splat", lambda i: (gen_dirt.splat(42 + i) if i < 2 else gen_dirt.splat(1042 + i)).resize((256, 256), Image.BICUBIC), 4, 0.55),
        ("hand", lambda i: gen_dirt.hand(61 + i), 4, 0.55),
        ("drip", lambda i: gen_dirt.drip(71 + i), 2, 0.55),
        ("spatter", lambda i: gen_dirt.spatter(81 + i), 3, 0.8),
        ("pool", lambda i: gen_dirt.pool(91 + i) if i < 2 else pool_big(1091 + i), 4, 0.55),
        ("smear", lambda i: gen_dirt.smear(31 + i), 3, 0.4),
        ("print", lambda i: gen_dirt.fingerprint(21 + i), 4, 0.45),
        ("run", lambda i: run(1201 + i), 3, 0.5),
        ("arterial", lambda i: arterial(1301 + i), 2, 0.75),
        ("drag", lambda i: drag(1401 + i), 2, 0.45),
    ]
    for kind, fn, n, film in gens:
        for i in range(n):
            m = p2(fn(i))
            # Grandes taches : masque agrandi deux fois puis remis en couleur à cette taille (le seuil de
            # colorize_blood redonne un bord net) : nettes en 1440p et 4K, où 256 px s'étalaient sur 800.
            if kind in ("splat", "hand", "pool", "drag", "arterial", "smear", "spatter") and max(m.size) <= 512:
                m = m.resize((m.size[0] * 2, m.size[1] * 2), Image.BICUBIC)
            seed = 300 + i * 7 + len(kind)
            save(colorize_blood(m, seed, film), f"bloodc_{kind}_{i}")
            save(colorize_blood(m, seed, film, dry=True), f"bloodd_{kind}_{i}")
    # Plein écran, une version par orientation (pas d'étirement) : nappes réunies et voile mince.
    for i in range(2):
        for land in (True, False):
            w, h = (512, 256) if land else (256, 512)
            o = "land" if land else "port"
            m = cover(1501 + i * 11 + int(land), w, h)
            save(colorize_blood(m, 1600 + i, 0.5, margin=0.012), f"bloodc_cover_{i}_{o}")
            save(colorize_blood(m, 1600 + i, 0.5, dry=True, margin=0.012), f"bloodd_cover_{i}_{o}")
    for land in (True, False):
        w, h = (512, 256) if land else (256, 512)
        o = "land" if land else "port"
        a = veil(1701 + int(land), w, h)
        save(film_rgba(a, 1701, False, 0.012), f"bloodc_veil_0_{o}")
        save(film_rgba(a, 1701, True, 0.012), f"bloodd_veil_0_{o}")
    for i in range(3):
        a = wipe(1801 + i)
        save(film_rgba(a, 1801 + i, False), f"bloodc_wipe_{i}")
        save(film_rgba(a, 1801 + i, True), f"bloodd_wipe_{i}")


def bleed(seed):
    """Fuite de cristaux liquides : tache noire irrégulière qui s'étale, franges violettes / vertes / bleues."""
    rnd = random.Random(seed)
    s = 256
    yy, xx = np.mgrid[0:s, 0:s].astype(np.float32)
    n = gen_dirt.blood_noise(s, s, seed, 9, 5)
    cx, cy = s / 2 + rnd.uniform(-10, 10), s / 2 + rnd.uniform(-10, 10)
    r = np.sqrt((xx - cx) ** 2 + (yy - cy) ** 2) / (s * 0.30)
    # Doigts d'encre (le cristal coule le long des fêlures).
    ang = np.arctan2(yy - cy, xx - cx)
    fingers = sum(np.cos(ang * rnd.randint(3, 7) + rnd.uniform(0, 6)) * rnd.uniform(0.08, 0.18) for _ in range(3))
    f = r - fingers - (n - 0.5) * 0.9
    core = np.clip((1 - f) * 3.5, 0, 1)
    halo = np.clip(1 - np.abs(f - 1.08) * 9, 0, 1)
    halo2 = np.clip(1 - np.abs(f - 1.22) * 10, 0, 1) * 0.5
    col = np.zeros((s, s, 3), np.float32)
    col += np.array([4, 2, 8], np.float32) * core[..., None]
    col += np.array([150, 40, 210], np.float32) * halo[..., None]
    col += np.array([40, 210, 120], np.float32) * halo2[..., None]
    blue = np.clip(1 - np.abs(f - 0.98) * 12, 0, 1) * 0.6
    col += np.array([30, 60, 220], np.float32) * blue[..., None]
    alpha = np.clip(core * 0.97 + halo * 0.6 + halo2 * 0.4 + blue * 0.5, 0, 1)
    tot = np.maximum(core + halo + halo2 + blue, 1e-3)[..., None]
    col = np.where(tot > 1, col / tot, col)
    a = smooth(alpha, 1.2)
    return Image.fromarray(np.dstack([np.clip(col, 0, 255), a * 255]).astype(np.uint8), "RGBA")


def backlight(seed, land):
    """Fuite de rétroéclairage : halos blancs chauds contre les bords et dans un coin (dalle décollée)."""
    rnd = random.Random(seed)
    w, h = (512, 256) if land else (256, 512)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    a = np.zeros((h, w), np.float32)
    for _ in range(rnd.randint(3, 6)):
        side = rnd.randint(0, 3)
        if side == 0:
            x, y = rnd.uniform(0, w), 0
        elif side == 1:
            x, y = rnd.uniform(0, w), h
        elif side == 2:
            x, y = 0, rnd.uniform(0, h)
        else:
            x, y = w, rnd.uniform(0, h)
        rx, ry = rnd.uniform(0.08, 0.25) * w, rnd.uniform(0.04, 0.12) * h
        if side >= 2:
            rx, ry = rnd.uniform(0.04, 0.12) * w, rnd.uniform(0.08, 0.25) * h
        a += np.exp(-(((xx - x) / rx) ** 2 + ((yy - y) / ry) ** 2)) * rnd.uniform(0.5, 1)
    cx, cy = rnd.choice([0, w]), rnd.choice([0, h])
    a += np.exp(-(((xx - cx) / (0.3 * w)) ** 2 + ((yy - cy) / (0.3 * h)) ** 2)) * 0.8
    a = np.clip(a, 0, 1) * 0.85
    col = np.dstack([np.full_like(a, 255), np.full_like(a, 246), np.full_like(a, 222)])
    return Image.fromarray(np.dstack([col, a * 255]).astype(np.uint8), "RGBA")


def mura(seed):
    """Arc-en-ciel de pression : anneaux irisés autour d'un point écrasé (dalle comprimée)."""
    rnd = random.Random(seed)
    s = 256
    yy, xx = np.mgrid[0:s, 0:s].astype(np.float32)
    n = gen_dirt.blood_noise(s, s, seed, 12, 6)
    r = np.sqrt((xx - s / 2) ** 2 + (yy - s / 2) ** 2) / (s / 2) + (n - 0.5) * 0.25
    ph = r * rnd.uniform(5, 8) * 2 * math.pi
    col = np.dstack([0.5 + 0.5 * np.cos(ph), 0.5 + 0.5 * np.cos(ph - 2.1), 0.5 + 0.5 * np.cos(ph - 4.2)]) * 255
    a = np.clip(1 - r, 0, 1) ** 1.3 * 0.4
    a *= np.clip(r * 4, 0, 1)
    return Image.fromarray(np.dstack([col, a * 255]).astype(np.uint8), "RGBA")


def band(seed):
    """Bande de lignes mortes colorées (nappe de la dalle abîmée) : verticale, plusieurs couleurs serrées."""
    rnd = random.Random(seed)
    w, h = 64, 512
    a = np.zeros((h, w, 4), np.float32)
    x = 0
    palette = [(30, 240, 90), (240, 40, 220), (240, 240, 240), (10, 10, 10), (60, 120, 255), (255, 210, 30)]
    while x < w:
        lw = rnd.randint(1, 4)
        c = rnd.choice(palette)
        al = rnd.uniform(0.55, 0.95)
        a[:, x:x + lw, :3] = c
        a[:, x:x + lw, 3] = al * 255
        x += lw + rnd.randint(0, 3)
    # Ligne qui s'interrompt par endroits (contact intermittent).
    for _ in range(rnd.randint(1, 3)):
        y0 = rnd.randint(0, h - 40)
        a[y0:y0 + rnd.randint(10, 60), :, 3] *= rnd.uniform(0, 0.3)
    return Image.fromarray(np.clip(a, 0, 255).astype(np.uint8), "RGBA")


def emi(frame):
    """Parasites du brouillage : lignes de balayage, bandes déchirées décalées, neige colorée par plaques."""
    rnd = random.Random(500 + frame)
    w, h = 256, 512
    a = np.zeros((h, w, 4), np.float32)
    # Lignes de balayage fines.
    for y in range(0, h, 3):
        a[y, :, :3] = 255
        a[y, :, 3] = rnd.uniform(0.04, 0.12)
    # Bandes déchirées : un aplat clair ou coloré, bord dentelé.
    for _ in range(rnd.randint(3, 6)):
        y0 = rnd.randint(0, h - 30)
        bh = rnd.randint(4, 26)
        c = rnd.choice([(255, 255, 255), (120, 255, 160), (255, 90, 230), (90, 200, 255), (20, 20, 20)])
        for y in range(y0, min(h, y0 + bh)):
            x0 = rnd.randint(0, w // 3)
            x1 = rnd.randint(w // 2, w)
            a[y, x0:x1, :3] = c
            a[y, x0:x1, 3] = rnd.uniform(0.25, 0.6)
    # Neige colorée par plaques.
    rng = np.random.default_rng(900 + frame)
    snow = rng.random((h, w))
    patch = gen_dirt.blood_noise(w, h, 700 + frame, 10, 4)
    m = (snow > 0.82) & (patch > 0.55)
    cols = rng.integers(0, 255, (h, w, 3))
    a[m, :3] = cols[m]
    a[m, 3] = 0.55
    return Image.fromarray(np.dstack([a[..., :3], a[..., 3] * 255]).clip(0, 255).astype(np.uint8), "RGBA")


def main():
    blood()
    if SANG_SEUL:
        return
    for i in range(2):
        save(bleed(1100 + i), f"fx_bleed_{i}")
        save(backlight(1200 + i, False), f"fx_backlight_{i}_port")
        save(backlight(1200 + i, True), f"fx_backlight_{i}_land")
        save(mura(1300 + i), f"fx_mura_{i}")
        save(band(1400 + i), f"fx_band_{i}")
    for f in range(4):
        save(emi(f), f"fx_emi_{f}")


if __name__ == "__main__":
    main()
