#!/usr/bin/env python3
"""Maquettes des dégâts d'écran du téléphone : les vraies textures (gen_screen_damage.py, gen_screen_fx.py) posées sur
un faux écran ATAK, avec les mêmes règles de placement que le jeu (fn_screenFaults, fn_screenSurface, fn_screenGlitch).

Usage : python gen_screen_mockups.py <aperçus gen_screen_damage> <aperçus gen_screen_fx>/rgba <dossier de sortie>
Sortie : un PNG par bris, panne, bug et niveau de sang (portrait et paysage), et des planches de contact.
Ne sert qu'à montrer le rendu sans lancer le jeu ; rien n'est lu par le mod.
"""
import math
import os
import random
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

DMG, BLOOD, OUT = sys.argv[1], sys.argv[2], sys.argv[3]
os.makedirs(OUT, exist_ok=True)
W, H = 600, 1280  # écran en portrait (petit côté 600 px : téléphone en main en 1440p)
_cache = {}


def tex(name):
    if name not in _cache:
        for d in (DMG, BLOOD):
            p = os.path.join(d, name + ".png")
            if os.path.exists(p):
                _cache[name] = Image.open(p).convert("RGBA")
                break
        else:
            raise FileNotFoundError(name)
    return _cache[name]


def font(sz, bold=False):
    for f in (("arialbd.ttf" if bold else "arial.ttf"), "DejaVuSans.ttf"):
        try:
            return ImageFont.truetype(f, sz)
        except OSError:
            pass
    return ImageFont.load_default()


def fbm(w, h, seed, octaves=4, base=3):
    rng = np.random.default_rng(seed)
    out = np.zeros((h, w), np.float32)
    amp = 1.0
    for o in range(octaves):
        c = base * 2 ** o
        g = Image.fromarray(np.uint8(rng.random((int(c * h / min(w, h)) + 2, int(c * w / min(w, h)) + 2)) * 255), "L").resize((w, h), Image.BICUBIC)
        out += amp * np.asarray(g, np.float32) / 255
        amp *= 0.5
    return (out - out.min()) / (out.max() - out.min())


def ui(land):
    """Faux écran ATAK : carte en relief, quadrillage, pions, itinéraire, barre d'état et barre d'outils."""
    w, h = (H, W) if land else (W, H)
    n = fbm(w, h, 11, 5, 2)
    m = fbm(w, h, 12, 4, 5)
    r = 58 + 70 * n + 20 * m
    g = 82 + 78 * n + 18 * m
    b = 60 + 46 * n
    water = n < 0.2
    r[water], g[water], b[water] = 44, 86, 120
    img = Image.fromarray(np.dstack([r, g, b]).clip(0, 255).astype(np.uint8), "RGB").convert("RGBA")
    d = ImageDraw.Draw(img)
    lv = (n * 14) % 1
    cont = Image.fromarray(np.uint8((np.abs(lv - 0.5) > 0.46) * 70), "L")
    img.paste(Image.new("RGBA", (w, h), (30, 30, 20, 255)), (0, 0), cont)
    for x in range(0, w, 100):
        d.line([x, 0, x, h], fill=(20, 26, 24, 110))
    for y in range(0, h, 100):
        d.line([0, y, w, y], fill=(20, 26, 24, 110))
    rnd = random.Random(5)
    pts = [(w * 0.15, h * 0.8), (w * 0.3, h * 0.62), (w * 0.52, h * 0.55), (w * 0.7, h * 0.36), (w * 0.82, h * 0.2)]
    d.line(pts, fill=(250, 208, 40, 255), width=4)
    for (x, y) in pts[:3]:
        d.rounded_rectangle([x - 17, y - 12, x + 17, y + 12], 3, fill=(128, 224, 255, 255), outline=(10, 20, 30, 255), width=2)
    for _ in range(3):
        x, y = rnd.uniform(0.5, 0.9) * w, rnd.uniform(0.12, 0.45) * h
        d.polygon([(x, y - 16), (x + 16, y), (x, y + 16), (x - 16, y)], fill=(255, 128, 128, 255), outline=(30, 10, 10, 255))
    d.rectangle([0, 0, w, 34], fill=(18, 22, 20, 235))
    d.text((12, 8), "14:32  COMSPEC ATAK", font=font(16, True), fill=(220, 230, 225, 255))
    d.text((w - 150, 8), "4G  87 %  GPS 3D", font=font(15), fill=(150, 220, 160, 255))
    d.rectangle([0, h - 76, w, h], fill=(18, 22, 20, 240))
    k = 5 if not land else 9
    for i, t in enumerate(["CARTE", "MSG", "PLI", "9L", "OUTILS", "DRONE", "MED", "RAD", "RÉGL"][:k]):
        x = (i + 0.5) * w / k
        d.rounded_rectangle([x - 26, h - 66, x + 26, h - 30], 8, fill=(44, 60, 52, 255))
        d.text((x, h - 16), t, font=font(12), fill=(190, 205, 198, 255), anchor="mm")
    d.rounded_rectangle([12, 48, 230, 110], 8, fill=(18, 22, 20, 215))
    d.text((24, 56), "31U FT 4821 9037", font=font(17, True), fill=(235, 240, 238, 255))
    d.text((24, 82), "ALT 142 m   CAP 047°", font=font(14), fill=(160, 175, 168, 255))
    return img


def over(base, t, cx, cy, hpx, ang=0.0, alpha=1.0, wpx=None, tint=None):
    """Pose une texture centrée en (cx, cy), haute de hpx pixels, tournée de ang degrés (sens horaire, comme
    ctrlSetAngle), rognée à l'écran."""
    t = tex(t) if isinstance(t, str) else t
    wpx = wpx or hpx * t.size[0] / t.size[1]
    t = t.resize((max(1, int(round(wpx))), max(1, int(round(hpx)))), Image.LANCZOS)
    if tint or alpha != 1.0:
        a = np.asarray(t, np.float32)
        if tint:
            a[..., :3] *= np.array(tint, np.float32)
        a[..., 3] *= alpha
        t = Image.fromarray(a.clip(0, 255).astype(np.uint8), "RGBA")
    if ang % 360:
        t = t.rotate(-ang, Image.BICUBIC, expand=True)
    layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
    layer.paste(t, (int(round(cx - t.size[0] / 2)), int(round(cy - t.size[1] / 2))))
    base.alpha_composite(layer)


def box(base, x, y, w, h, col):
    layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
    ImageDraw.Draw(layer).rectangle([x, y, x + max(1, w) - 1, y + max(1, h) - 1], fill=tuple(int(c * 255) for c in col))
    base.alpha_composite(layer)


def to_screen(gx, gy, ang, land):
    """Repère du verre (portrait) -> écran affiché. Paysage : (x, y) -> (y, 1 - x), quart de tour antihoraire."""
    return (gy, 1 - gx, ang - 90) if land else (gx, gy, ang)


TEMPLATE = {"hair": "point", "web": "point", "bull": "point", "bullet": "point", "crush": "point", "corner": "corner", "chip": "corner",
            "peel": "corner", "diag": "strip", "press": "free", "abrade": "free", "water": "free", "heat": "free"}


def glass(base, land, kind, var, sev, gx, gy, light=1.0, ang=0.0):
    """Même placement que fn_screenFaults : gabarit point / coin / bande / libre, sans étirement, dans l'écran."""
    w, h = base.size
    short = min(w, h)
    tpl = TEMPLATE[kind]
    name = f"gx_{kind}_{var}_{sev}" if kind not in ("abrade", "water", "peel", "heat") else f"gx_{kind}_{var}"
    if tpl == "point":
        q = (0 if gy < 0.5 else 3) if gx < 0.5 else (1 if gy < 0.5 else 2)
        ox, oy = [(-0.2, -0.2), (0.2, -0.2), (0.2, 0.2), (-0.2, 0.2)][q]
        cgx, cgy = gx - ox, gy - oy * W / H
        a = 90 * q
        sx, sy, a = to_screen(cgx, cgy, a, land)
        size = short
        cx = min(max(sx * w, size / 2), w - size / 2)
        cy = min(max(sy * h, size / 2), h - size / 2)
    elif tpl == "corner":
        c = (0 if gy < 0.5 else 3) if gx < 0.5 else (1 if gy < 0.5 else 2)
        size = short * (0.95 if kind != "peel" else 0.8)
        px, py = [(0, 0), (1, 0), (1, 1), (0, 1)][c]
        sx, sy, a = to_screen(px, py, 90 * c, land)
        cx = size / 2 if sx < 0.5 else w - size / 2
        cy = size / 2 if sy < 0.5 else h - size / 2
    elif tpl == "strip":
        long_ = max(w, h)
        size = min(long_, 2 * short)
        sx, sy, a = to_screen(0.5, 0.5, 0, land)
        cx, cy = w / 2, h / 2
        for off, al in (((1.5, 1.0), 0.2 * light), ((0, 0), 0.66 + 0.3 * light)):
            over(base, name, cx + off[0], cy + off[1], size, a, al)
        return
    else:
        size = short * {"press": 0.98, "abrade": 0.95, "water": 0.9, "heat": 0.6}[kind] * (0.75 + 0.125 * sev if kind in ("water", "heat") else 1)
        sx, sy, a = to_screen(gx, gy, ang, land)
        cx = min(max(sx * w, size / 2), w - size / 2)
        cy = min(max(sy * h, size / 2), h - size / 2)
    if kind == "abrade":
        over(base, name, cx, cy, size, a, (0.22 + 0.12 * sev) * (0.35 + 0.65 * light))
    elif kind in ("water", "heat", "peel"):
        over(base, name, cx, cy, size, a, (0.6, 0.8, 1.0)[sev - 1])
    else:
        # Reflet de l'arête : seconde passe décalée d'un pixel et demi, d'autant plus visible que la lumière est forte.
        over(base, name, cx + 1.5, cy + 1.0, size, a, 0.2 * light)
        over(base, name, cx, cy, size, a, 0.66 + 0.3 * light)


def glare(base, k=1.0):
    w, h = base.size
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    t = (xx / w * 0.9 + yy / h * 0.6)
    a = np.exp(-((t - 0.32) / 0.11) ** 2) * 0.2 * k + np.exp(-((t - 0.52) / 0.03) ** 2) * 0.07 * k
    base.alpha_composite(Image.fromarray(np.dstack([np.full((h, w, 3), 255, np.uint8), np.uint8(a * 255)]), "RGBA"))


def phone(screen, label=""):
    """Écran dans une coque sombre aux coins arrondis, légende dessous."""
    w, h = screen.size
    m = 26
    out = Image.new("RGBA", (w + 2 * m, h + 2 * m + 44), (26, 28, 30, 255))
    d = ImageDraw.Draw(out)
    d.rounded_rectangle([4, 4, w + 2 * m - 4, h + 2 * m - 4], 42, fill=(8, 9, 10, 255), outline=(58, 62, 66, 255), width=3)
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, w, h], 22, fill=255)
    out.paste(screen, (m, m), mask)
    d.text(((w + 2 * m) / 2, h + 2 * m + 20), label, font=font(22, True), fill=(225, 228, 230, 255), anchor="mm")
    return out


def row(imgs, title=""):
    hh = max(i.size[1] for i in imgs)
    ww = sum(i.size[0] for i in imgs) + 16 * (len(imgs) + 1)
    out = Image.new("RGBA", (ww, hh + 70), (26, 28, 30, 255))
    ImageDraw.Draw(out).text((20, 16), title, font=font(30, True), fill=(240, 240, 240, 255))
    x = 16
    for i in imgs:
        out.paste(i, (x, 62))
        x += i.size[0] + 16
    return out


def sheet(paths, name, cols=4, cell=520):
    """Planche de contact : chaque image réduite dans une case."""
    ims = [Image.open(p).convert("RGB") for p in paths]
    rows_ = math.ceil(len(ims) / cols)
    chh = int(cell * 0.62)
    out = Image.new("RGB", (cols * cell, rows_ * chh), (20, 22, 24))
    for i, im in enumerate(ims):
        im.thumbnail((cell - 12, chh - 12), Image.LANCZOS)
        out.paste(im, ((i % cols) * cell + (cell - im.size[0]) // 2, (i // cols) * chh + (chh - im.size[1]) // 2))
    out.save(os.path.join(OUT, name))


made = []


def emit(img, name):
    p = os.path.join(OUT, name + ".png")
    img.convert("RGB").save(p)
    made.append(p)
    return p


FR_GLASS = {"hair": "Fêlure fine", "web": "Impact en toile", "bull": "Impact « œil de bœuf »", "corner": "Coin écrasé", "chip": "Coin éclaté, verre manquant",
            "diag": "Longue fracture en biais", "press": "Fêlure de pression", "bullet": "Impact de balle", "crush": "Dalle écrasée (encre)",
            "abrade": "Verre rayé et dépoli", "peel": "Film de protection décollé", "water": "Eau sous le verre", "heat": "Brûlure de chaleur"}
POS = {"hair": (0.4, 0.35), "web": (0.3, 0.3), "bull": (0.7, 0.62), "corner": (0.9, 0.9), "chip": (0.1, 0.1), "diag": (0.5, 0.5), "press": (0.5, 0.45),
       "bullet": (0.7, 0.3), "crush": (0.3, 0.7), "abrade": (0.5, 0.5), "peel": (0.9, 0.1), "water": (0.5, 0.6), "heat": (0.55, 0.3)}


def mock_glass():
    for kind, label in FR_GLASS.items():
        ims = []
        for sev in (1, 2, 3):
            s = ui(False)
            glass(s, False, kind, 0, sev, *POS[kind], light=1.0, ang=20)
            glare(s)
            ims.append(phone(s, f"gravité {sev}"))
        s = ui(True)
        glass(s, True, kind, 0, 2, *POS[kind], light=1.0, ang=20)
        glare(s)
        ims.append(phone(s, "paysage, gravité 2 (même verre tourné)"))
        emit(row(ims, f"Verre · {label}"), f"verre_{kind}")
    # Nuit : mêmes fêlures, lumière faible (lisibles sans briller).
    ims = []
    for light, lab in ((1.0, "plein jour"), (0.15, "nuit")):
        s = ui(False)
        glass(s, False, "web", 1, 3, 0.7, 0.3, light)
        glass(s, False, "corner", 1, 2, 0.1, 0.9, light)
        if light < 0.5:
            s = Image.eval(s, lambda v: int(v * 0.55))
            s.putalpha(255)
        glare(s, light)
        ims.append(phone(s, lab))
    s = ui(False)
    for k, v, sv, x, y in (("bullet", 1, 2, 0.3, 0.3), ("diag", 1, 2, 0.5, 0.5), ("chip", 0, 2, 0.9, 0.9), ("abrade", 1, 3, 0.5, 0.6)):
        glass(s, False, k, v, sv, x, y)
    glare(s)
    ims.append(phone(s, "bris cumulés"))
    emit(row(ims, "Verre · jour, nuit et bris cumulés"), "verre_lumiere_cumul")


def panel(base, kind, rnd, land=False, t=1.0):
    """Pannes de dalle sans verre cassé, mêmes dessins que fn_screenFaults (cases de couleur et quelques textures)."""
    w, h = base.size
    p = max(2, round(min(w, h) / 300))
    cols = [(0, 0, 0, 1), (1, 0.05, 0.05, 1), (0.05, 1, 0.1, 1), (0.1, 0.2, 1, 1), (1, 1, 1, 1)]
    if kind == "deadpx":
        for _ in range(5):
            box(base, rnd.randint(20, w - 20), rnd.randint(50, h - 90), p, p, cols[0])
    elif kind == "stuckpx":
        for i in range(5):
            box(base, rnd.randint(20, w - 20), rnd.randint(50, h - 90), p, p, cols[1 + i % 4])
    elif kind == "cluster":
        cx, cy = w * 0.62, h * 0.44
        for _ in range(38):
            dx, dy = rnd.gauss(0, 9), rnd.gauss(0, 9)
            box(base, int(cx + dx), int(cy + dy), p * rnd.choice([1, 1, 2]), p * rnd.choice([1, 1, 2]), cols[rnd.choice([0, 0, 0, 2, 4])])
    elif kind == "deadline":
        box(base, int(w * 0.71), 0, p, h, (0.1, 1, 0.3, 0.95))
    elif kind == "lines":
        for x, c in ((0.22, (1, 0.1, 0.9, 0.95)), (0.24, (0, 0, 0, 1)), (0.58, (0.1, 1, 0.3, 0.9)), (0.83, (1, 1, 1, 0.8))):
            box(base, int(w * x), 0, p, h, c)
        box(base, 0, int(h * 0.37), w, p, (0.2, 0.6, 1, 0.9))
    elif kind == "flickline":
        box(base, 0, int(h * 0.52), w, p, (1, 1, 1, 0.85 * t))
        box(base, 0, int(h * 0.52) + p, w, p, (0, 0, 0, 0.6 * t))
    elif kind == "band":
        over(base, "fx_band_0", w * 0.3, h / 2, h, 0, 0.9, wpx=w * 0.05)
    elif kind == "blot":
        over(base, "fx_bleed_0", w * 0.68, h * 0.7, min(w, h) * 0.34 * t, 35, 0.97)
    elif kind == "burnin":
        over(base, "pf_burn_0", w / 2, h / 2, h if not land else w, 0 if not land else -90, 0.22, wpx=w if not land else h)
    elif kind == "edgeleak":
        over(base, "fx_backlight_0_port", w / 2, h / 2, h, 0, 0.42, wpx=w)
    elif kind == "dimzone":
        over(base, "pf_dim_0", w * 0.4, h * 0.72, min(w, h) * 0.95, 0, 0.6)
    elif kind == "tint":
        box(base, 0, 0, w, h, (0.1, 0.85, 0.3, 0.1))
    elif kind == "touchdead":
        box(base, int(w * 0.1), int(h * 0.55), int(w * 0.5), int(h * 0.2), (0.75, 0.85, 1, 0.045))


FR_PANEL = [("deadpx", "Pixels morts"), ("stuckpx", "Pixels bloqués"), ("cluster", "Amas de pixels"), ("deadline", "Ligne morte"),
            ("lines", "Plusieurs lignes"), ("flickline", "Ligne qui clignote"), ("band", "Bande de colonnes"), ("blot", "Tache d'encre qui grandit"),
            ("burnin", "Image fantôme"), ("edgeleak", "Fuite de rétroéclairage"), ("dimzone", "Zone sombre"), ("tint", "Dominante de couleur"),
            ("touchdead", "Zone tactile morte")]


def mock_panel():
    group = []
    for kind, label in FR_PANEL:
        s = ui(False)
        if kind == "blot":
            ims = []
            for t, lab in ((0.6, "au début"), (1.0, "après 5 min"), (1.5, "après 15 min")):
                s = ui(False)
                panel(s, kind, random.Random(3), t=t)
                glare(s)
                ims.append(phone(s, lab))
            emit(row(ims, f"Dalle · {label} (verre intact)"), f"dalle_{kind}")
            continue
        panel(s, kind, random.Random(3))
        glare(s)
        group.append(phone(s, label))
        if len(group) == 4:
            emit(row(group, "Dalle · pannes sans verre cassé"), f"dalle_{kind}_groupe")
            group = []
    if group:
        emit(row(group, "Dalle · pannes sans verre cassé"), "dalle_fin_groupe")


def glitch(base, kind, rnd):
    w, h = base.size
    if kind == "tear":
        over(base, "gl_tear_0", w / 2, h / 2, h, 0, 0.95, wpx=w)
    elif kind == "roll":
        over(base, "gl_scan_0", w / 2, h * 0.4, h * 0.3, 0, 0.9, wpx=w)
    elif kind == "rgb":
        over(base, "gl_rgb_0", w / 2, h / 2, h, 0, 0.9, wpx=w)
    elif kind == "blocks":
        over(base, "gl_block_0", w / 2, h * 0.45, min(w, h), 0, 0.95)
    elif kind == "freeze":
        box(base, 0, 0, w, h, (0.6, 0.7, 0.8, 0.05))
    elif kind == "flicker":
        box(base, 0, 0, w, h, (0, 0, 0, 0.4))
    elif kind == "noise":
        over(base, "gl_static_0", w / 2, h / 2, h, 0, 0.5, wpx=w)
    elif kind == "pump":
        box(base, 0, 0, w, h, (0, 0, 0, 0.3))
    elif kind == "partial":
        box(base, 0, int(h * 0.5), w, int(h * 0.5), (0, 0, 0, 0.3))
        box(base, 0, int(h * 0.5), w, 2, (1, 1, 1, 0.5))
    elif kind == "reboot":
        box(base, 0, 0, w, h, (0, 0, 0, 1))
        d = ImageDraw.Draw(base)
        d.text((w / 2, h / 2), "COMSPEC ATAK", font=font(30, True), fill=(120, 200, 140, 255), anchor="mm")
    elif kind == "ghosttap":
        for _ in range(3):
            over(base, "gl_tap_0", rnd.uniform(0.2, 0.8) * w, rnd.uniform(0.3, 0.8) * h, 54, 0, 0.8)


FR_GLITCH = [("tear", "Image déchirée"), ("roll", "Barre de balayage"), ("rgb", "Couleurs décalées"), ("blocks", "Pavés de compression"),
             ("freeze", "Image figée"), ("flicker", "Scintillement"), ("noise", "Neige"), ("pump", "Luminosité qui pompe"),
             ("partial", "Demi-écran en retard"), ("reboot", "Écran noir puis logo"), ("ghosttap", "Appuis fantômes")]


def mock_glitch():
    group = []
    for kind, label in FR_GLITCH:
        s = ui(False)
        glitch(s, kind, random.Random(8))
        glare(s)
        group.append(phone(s, label))
        if len(group) == 4:
            emit(row(group, "Bugs d'affichage (une image, moins d'une seconde)"), f"bug_{kind}_groupe")
            group = []
    if group:
        emit(row(group, "Bugs d'affichage (une image, moins d'une seconde)"), "bug_fin_groupe")


ASPECT = {"print": 0.5, "drip": 0.5, "drag": 0.5, "run": 0.125, "smear": 2, "arterial": 2, "wipe": 2, "puddle": 4}


def stain(base, kind, var, x, y, size, ang=0.0, alpha=1.0, dry=False, wet=False):
    w, h = base.size
    hpx = min(w, h) * size
    wpx = hpx * ASPECT.get(kind, 1)
    cx, cy = x * w, y * h
    if kind not in ("run", "puddle"):
        cx = min(max(cx, min(wpx, w) / 2), w - min(wpx, w) / 2)
        cy = min(max(cy, min(hpx, h) / 2), h - min(hpx, h) / 2)
    tint = (1.0, 0.82, 0.86) if wet else None
    over(base, f"blood{'d' if dry else 'c'}_{kind}_{var}", cx, cy, hpx, ang, alpha * (0.6 if wet else 1), wpx=wpx, tint=tint)


def blood_level(base, lvl, rnd, dry=False, wet=False):
    k = dict(dry=dry, wet=wet)
    if lvl >= 1:
        for _ in range(3):
            stain(base, rnd.choice(["splat", "print"]), rnd.randint(0, 3), rnd.uniform(0.1, 0.9), rnd.uniform(0.2, 0.9), rnd.uniform(0.1, 0.18), rnd.uniform(0, 360), 0.8, **k)
    if lvl >= 2:
        stain(base, "spatter", rnd.randint(0, 2), rnd.uniform(0.3, 0.7), rnd.uniform(0.3, 0.7), 0.55, rnd.uniform(0, 360), 0.9, **k)
        stain(base, "smear", rnd.randint(0, 2), 0.5, rnd.uniform(0.5, 0.8), 0.3, rnd.uniform(-30, 30), 0.8, **k)
        stain(base, "drip", rnd.randint(0, 1), rnd.uniform(0.2, 0.8), 0.25, 0.4, 0, 0.9, **k)
    if lvl >= 3:
        for _ in range(2):
            x, y, s = rnd.uniform(0.2, 0.8), rnd.uniform(0.2, 0.6), rnd.uniform(0.55, 0.8)
            stain(base, "pool", rnd.randint(0, 3), x, y, s, rnd.uniform(0, 360), 0.95, **k)
            yy = y + s * 0.3 * min(base.size) / base.size[1]
            for seg in range(3):
                stain(base, "run", rnd.randint(0, 2), x + rnd.uniform(-0.02, 0.02), yy + (seg + 0.5) * 0.4 * min(base.size) / base.size[1], 0.42, 0, 0.92, **k)
        stain(base, "arterial", rnd.randint(0, 1), 0.5, rnd.uniform(0.3, 0.6), 0.5, rnd.uniform(0, 360), 0.9, **k)
        stain(base, "hand", rnd.randint(0, 3), rnd.uniform(0.3, 0.7), rnd.uniform(0.4, 0.7), 0.7, rnd.uniform(-30, 30), 0.85, **k)
        stain(base, "puddle", 0, 0.5, 1 - 0.125 * min(base.size) / base.size[1], 0.25, 0, 0.95, **k)
    if lvl >= 4:
        w, h = base.size
        o = "land" if w > h else "port"
        over(base, f"blood{'d' if dry else 'c'}_veil_0_{o}", w / 2, h / 2, h, 0, 0.85, wpx=w)
        over(base, f"blood{'d' if dry else 'c'}_cover_0_{o}", w / 2, h / 2, h, 0, 0.95, wpx=w)
        for _ in range(3):
            stain(base, "run", rnd.randint(0, 2), rnd.uniform(0.1, 0.9), rnd.uniform(0.3, 0.6), 0.5, 0, 0.92, **k)


def mock_blood():
    names = ["léger", "moyen", "lourd", "extrême"]
    ims = []
    for lvl in (1, 2, 3, 4):
        s = ui(False)
        blood_level(s, lvl, random.Random(40 + lvl))
        glare(s)
        ims.append(phone(s, f"niveau {lvl} · {names[lvl - 1]} (frais)"))
    emit(row(ims, "Sang · quatre niveaux, sang frais"), "sang_niveaux_frais")
    ims = []
    for lvl in (2, 3, 4):
        s = ui(False)
        blood_level(s, lvl, random.Random(40 + lvl), dry=True)
        glare(s)
        ims.append(phone(s, f"niveau {lvl} · séché"))
    s = ui(True)
    blood_level(s, 3, random.Random(43))
    glare(s)
    ims.append(phone(s, "paysage · lourd, les coulures suivent la gravité"))
    emit(row(ims, "Sang · séché, et paysage"), "sang_seche_paysage")
    ims = []
    # Pluie : le sang se dilue en voile rosé ; essuyage : traînées et arc dégagé par le pouce ; fêlures : le sang y reste.
    s = ui(False)
    blood_level(s, 3, random.Random(43), wet=True)
    over(s, "dirt_rain_0", W * 0.5, H * 0.3, W * 0.95, 0, 0.9)
    over(s, "dirt_rain_1", W * 0.5, H * 0.72, W * 0.95, 90, 0.9)
    glare(s)
    ims.append(phone(s, "sous la pluie : dilué, rosé"))
    s = ui(False)
    for i, (x, y) in enumerate(((0.5, 0.35), (0.45, 0.62))):
        stain(s, "wipe", i, x, y, 0.5, -20 + 12 * i, 0.75)
    stain(s, "splat", 1, 0.2, 0.85, 0.16, 0, 0.5)
    stain(s, "print", 2, 0.7, 0.8, 0.2, 20, 0.7)
    glare(s)
    ims.append(phone(s, "après un coup de chiffon"))
    s = ui(False)
    glass(s, False, "web", 0, 3, 0.3, 0.3)
    stain(s, "seep", 0, 0.3, 0.33, 0.5, 40, 0.95, dry=True)
    stain(s, "wipe", 2, 0.5, 0.3, 0.5, 10, 0.4)
    glare(s)
    ims.append(phone(s, "essuyé : le sang reste dans les fêlures"))
    s = ui(False)
    for i in range(5):
        stain(s, "print", i % 4, 0.2 + 0.15 * i, 0.9 - 0.12 * i, 0.19, -30 + 15 * i, 0.75)
    over(s, "dirt_mud_0", W * 0.3, H * 0.3, W * 0.5, 30, 0.95)
    over(s, "dirt_mud_1", W * 0.75, H * 0.55, W * 0.34, 200, 0.95)
    glare(s)
    ims.append(phone(s, "empreintes sous le doigt, boue"))
    emit(row(ims, "Sang · pluie, essuyage, fêlures, empreintes et boue"), "sang_pluie_essuyage_felures")


if __name__ == "__main__":
    mock_glass()
    mock_panel()
    mock_glitch()
    mock_blood()
    sheet([p for p in made if os.path.basename(p).startswith("verre_")], "planche_verre.png", 3, 900)
    sheet([p for p in made if not os.path.basename(p).startswith("verre_")], "planche_dalle_bugs_sang.png", 3, 900)
    print(len(made), "maquettes")
