"""
Textures de la famille « relais » (pylônes, mâts, antennes murales, kits déployés) : trois atlas procéduraux.

  relay_steel   2048 x 1024  planche de profilés raccordable en U (2 048 px = 4 m) : acier galvanisé (fleurage du zinc,
                             rouille blanche, piqûres de rouille), platines boulonnées, tubes, toron de hauban, acier
                             rouillé. Variantes de peinture par hiddenSelections (« camo ») : galvanisé, rouge-blanc
                             (bandes A / B alternées en hauteur par le modèle), vert OTAN, sable.
  relay_equip   2048 x 2048  équipements dépliés à plat : radômes, RRU, paraboles, armoires, abri, béton, groupe
                             électrogène, panneau solaire, caisses, sacs de sable, radio MANET, plaques ; gaines de câbles
                             en bandes. Carte d'ombre ambiante (_as) 1024.
  relay_mesh    512 x 512    grillage simple torsion et caillebotis (alpha test), en bandes.

Tout est dessiné ici (numpy / Pillow) : aucun élément repris d'un autre mod, jeu ou banque d'images.
"""
import math
import os

import numpy as np
from PIL import Image, ImageDraw

import relay3d as r3
from relay3d import Atlas, blur, font, noise, smoothstep

TEX = "z\\comspec_atak_native\\addons\\towers\\data\\"
RNG = np.random.default_rng(4117)

# Couleurs linéaires
GALVA = np.array([0.30, 0.325, 0.34])
ZINC_BLOOM = np.array([0.62, 0.64, 0.65])
RUST = np.array([0.20, 0.075, 0.03])
RUST_DARK = np.array([0.07, 0.03, 0.015])
PAINTS = {
    # variante : (bande A, bande B, platines)
    "galva": None,
    "rw": (np.array([0.50, 0.035, 0.02]), np.array([0.72, 0.72, 0.69]), np.array([0.50, 0.035, 0.02])),
    "olive": (np.array([0.085, 0.10, 0.055]),) * 3,
    "sand": (np.array([0.42, 0.34, 0.22]),) * 3,
}


def lin(r, g, b):
    return np.array([(r / 255) ** 2.2, (g / 255) ** 2.2, (b / 255) ** 2.2])


def srgb8(c):
    return tuple(int(255 * max(0.0, min(1.0, float(v))) ** (1 / 2.2) + 0.5) for v in c)


def mix(a, b, t):
    return a * (1 - t[..., None]) + b * t[..., None] if isinstance(t, np.ndarray) else a * (1 - t) + b * t


def cells(h, w, size, rng):
    """Fleurage : mosaïque de cristaux (valeur par cellule), raccordable en U."""
    gh, gw = max(2, h // size), max(2, w // size)
    cw, ch = w / gw, h / gh
    px = (rng.random((gh, gw)) + np.arange(gw)[None, :]) * cw
    py = (rng.random((gh, gw)) + np.arange(gh)[:, None]) * ch
    val = rng.random((gh, gw))
    best = np.full((h, w), 1e9)
    out = np.zeros((h, w))
    rx, ry = int(cw * 2) + 1, int(ch * 2) + 1
    for j in range(gh):
        for i in range(gw):
            cx, cy = px[j, i], py[j, i]
            y0, y1 = max(0, int(cy) - ry), min(h, int(cy) + ry + 1)
            xs = np.arange(int(cx) - rx, int(cx) + rx + 1)
            ys = np.arange(y0, y1)
            d = (xs[None, :] - cx) ** 2 + (ys[:, None] - cy) ** 2
            xi = np.mod(xs, w)
            sub = best[y0:y1][:, xi]
            m = d < sub
            sub[m] = d[m]
            best[np.ix_(ys, xi)] = sub
            o = out[y0:y1][:, xi]
            o[m] = val[j, i]
            out[np.ix_(ys, xi)] = o
    return out


def galva_layer(h, w, rng, rust_amount=0.25):
    """Acier galvanisé à chaud : fleurage, voile de rouille blanche, quelques piqûres de rouille. -> rgb, hauteur, spec,
    brillance, métal."""
    sp = cells(h, w, 14, rng)
    fine = noise(h, w, 0.6, rng=rng)
    cloud = noise(h, w, 2.4, rng=rng)
    bloom = smoothstep(0.55, 0.85, noise(h, w, 2.0, aniso=(0.5, 1.0), rng=rng)) * 0.55
    sp = blur(sp, 1)
    g = 0.84 + 0.13 * (sp - 0.5) + 0.08 * (fine - 0.5) + 0.20 * (cloud - 0.5)
    rgb = GALVA[None, None, :] * g[..., None]
    rgb = mix(rgb, ZINC_BLOOM[None, None, :] * (0.8 + 0.3 * fine[..., None]), bloom)
    rmask = smoothstep(1 - 0.16 * rust_amount - 0.05, 1 - 0.10 * rust_amount, noise(h, w, 2.2, rng=rng) * 0.75 + 0.25 * noise(h, w, 0.8, rng=rng))
    rcol = mix(np.broadcast_to(RUST, (h, w, 3)), np.broadcast_to(RUST_DARK, (h, w, 3)), noise(h, w, 1.2, rng=rng))
    halo = np.clip(blur(rmask, 5) * 1.6, 0, 1) * 0.35
    rgb = mix(rgb, rgb * np.array([1.0, 0.78, 0.6]), halo)
    rgb = mix(rgb, rcol, rmask)
    height = 0.5 + 0.06 * (sp - 0.5) + 0.05 * (fine - 0.5) - 0.12 * rmask
    spec = 0.55 + 0.25 * (sp - 0.5) - 0.3 * bloom - 0.45 * rmask
    gloss = 0.52 + 0.2 * (sp - 0.5) - 0.25 * bloom - 0.4 * rmask
    metal = 1.0 - 0.9 * rmask - 0.5 * bloom
    return rgb, height, spec, gloss, metal


def paint_layer(h, w, color, rng, under, wear=1.0):
    """Peinture industrielle vieillie sur l'acier : peau d'orange, éclats aux arêtes (haut et bas de la bande),
    coulures de saleté, farinage. under : couche galva (rgb, hauteur, spec, brillance, métal) visible aux éclats."""
    peel = noise(h, w, 2.8, rng=rng)
    fine = noise(h, w, 0.7, rng=rng)
    fade = noise(h, w, 2.2, aniso=(0.4, 1.0), rng=rng)
    yy = np.abs(np.linspace(-1, 1, h))[:, None] * np.ones((1, w))
    chipn = noise(h, w, 1.6, rng=rng) * 0.6 + noise(h, w, 0.7, rng=rng) * 0.4
    edge = smoothstep(0.80, 1.0, yy)
    chips = smoothstep(0.76 - 0.05 * wear, 0.81 - 0.05 * wear, chipn + 0.32 * edge * wear)
    rgb = color[None, None, :] * (0.88 + 0.22 * (fade - 0.5) + 0.08 * (fine - 0.5))[..., None]
    chalk = smoothstep(0.5, 0.9, fade) * 0.18
    rgb = mix(rgb, np.ones(3)[None, None, :] * rgb.mean(axis=-1, keepdims=True) * 1.5, chalk)
    urgb, uh, us, ug, um = under
    primer = np.clip(blur(chips, 2) * 1.5 - chips, 0, 1)
    rgb = mix(rgb, rgb * 0.55, primer)
    rgb = mix(rgb, urgb, chips)
    height = 0.56 + 0.03 * (peel - 0.5) + 0.02 * (fine - 0.5)
    height = height * (1 - chips) + (uh - 0.10) * chips
    spec = (0.30 + 0.05 * (peel - 0.5)) * (1 - chips) + us * chips
    gloss = (0.42 - 0.12 * chalk / 0.18 * 0.5) * (1 - chips) + ug * chips
    metal = um * chips
    return rgb, height, spec, gloss, metal


def bolts(at, key, pitch_px, rows, r_px, rng, rust=0.5):
    """Rangées de boulons en relief sur une bande (platines) : tête hexagonale, rondelle, auréole de rouille."""
    s = at.sl(key)
    h, w = at.rgb[s].shape[:2]
    lay = Image.new("L", (w, h), 0)
    hl = Image.new("L", (w, h), 0)
    d, dh = ImageDraw.Draw(lay), ImageDraw.Draw(hl)
    for ry in rows:
        cy = int(ry * h)
        for cx in range(pitch_px // 2, w, pitch_px):
            d.ellipse([cx - r_px * 1.7, cy - r_px * 1.7, cx + r_px * 1.7, cy + r_px * 1.7], fill=150)
            a0 = rng.random() * math.pi
            hexa = [(cx + r_px * math.cos(a0 + k * math.pi / 3), cy + r_px * math.sin(a0 + k * math.pi / 3)) for k in range(6)]
            d.polygon(hexa, fill=200)
            dh.polygon(hexa, fill=255)
            d.ellipse([cx - r_px * 0.45, cy - r_px * 0.45, cx + r_px * 0.45, cy + r_px * 0.45], fill=255)
    m = np.asarray(lay, dtype=np.float64) / 255
    hm = np.asarray(hl, dtype=np.float64) / 255
    halo = np.clip(blur(hm, r_px * 1.5) * 3.0, 0, 1) * (0.4 + 0.6 * noise(h, w, 1.5, rng=rng)) * rust
    at.rgb[s] = mix(at.rgb[s], at.rgb[s] * np.array([0.9, 0.62, 0.42]), np.clip(halo - hm, 0, 1))
    at.rgb[s] = at.rgb[s] * (1 - 0.8 * np.clip(blur(m, 3) * 1.3 - m, 0, 1))[..., None]
    at.rgb[s] = mix(at.rgb[s], np.broadcast_to(GALVA * 0.9, (h, w, 3)) * (0.8 + 0.4 * noise(h, w, 0.8, rng=rng))[..., None], hm * 0.85)
    at.height[s] = at.height[s] + 0.35 * blur(m, 1)
    at.spec[s] = at.spec[s] * (1 - hm) + 0.6 * hm
    at.metal[s] = np.maximum(at.metal[s], hm)


STEEL_ROWS = (("st_a", 192, 4.0), ("st_b", 192, 4.0), ("st_pipe", 160, 4.0), ("st_plate", 224, 2.0), ("st_guy", 64, 1.0), ("st_rust", 192, 4.0))


def steel_atlas(variant="galva", declare=True):
    """Planche de profilés. Les matières sont déclarées une fois (variante galva) ; les autres variantes ne changent
    que la couleur (même nohq / smdi : hiddenSelectionsTextures)."""
    rng = np.random.default_rng(77)   # même graine : mêmes éclats et mêmes boulons d'une variante à l'autre
    W, H = 2048, 1024
    at = Atlas("relay_steel" if variant == "galva" else "relay_steel_" + variant, W, H, TEX, "relay_steel.rvmat")
    if not declare:
        saved = dict(r3.MATS)
    y = 0
    for key, h, tile in STEEL_ROWS:
        at.region(key, 0, y, W, h, "strip", tile)
        y += h
    if not declare:
        r3.MATS.clear()
        r3.MATS.update(saved)
    pal = PAINTS[variant]
    for i, (key, h, tile) in enumerate(STEEL_ROWS):
        s = at.sl(key)
        if key in ("st_a", "st_b", "st_pipe", "st_plate"):
            g = galva_layer(h, W, rng, rust_amount={"st_a": 0.25, "st_b": 0.4, "st_pipe": 0.15, "st_plate": 0.7}[key])
            if pal is not None and key != "st_pipe":
                col = pal[{"st_a": 0, "st_b": 1, "st_plate": 2}[key]]
                g = paint_layer(h, W, col, rng, g, wear=1.0 if key != "st_plate" else 1.4)
            else:
                rng.random((h, W))  # consomme l'aléa comme paint_layer pour garder les mêmes boulons
            at.rgb[s], at.height[s], at.spec[s], at.gloss[s], at.metal[s] = g
        elif key == "st_guy":
            xx = np.arange(W)[None, :] + np.arange(h)[:, None] * 1.6
            strand = 0.5 + 0.5 * np.sin(xx * 2 * math.pi / 14.0)
            g = 0.75 + 0.35 * (strand - 0.5) + 0.15 * (noise(h, W, 1.0, rng=rng) - 0.5)
            at.rgb[s] = (GALVA * 0.85)[None, None, :] * g[..., None]
            at.height[s] = 0.3 + 0.4 * strand
            at.spec[s], at.gloss[s], at.metal[s] = 0.5, 0.45, 1.0
        else:
            a, b = noise(h, W, 1.3, rng=rng), noise(h, W, 0.4, rng=rng)
            col = mix(np.broadcast_to(RUST, (h, W, 3)), np.broadcast_to(RUST_DARK, (h, W, 3)), a)
            col = mix(col, np.broadcast_to(np.array([0.33, 0.15, 0.05]), (h, W, 3)), smoothstep(0.6, 0.9, b) * 0.6)
            at.rgb[s] = col * (0.8 + 0.4 * b[..., None])
            at.height[s] = 0.5 + 0.35 * (b - 0.5) + 0.2 * (a - 0.5)
            at.spec[s], at.gloss[s], at.metal[s] = 0.08, 0.12, 0.2
    bolts(at, "st_plate", 164, (0.28, 0.72), 15, rng, rust=0.9 if pal is None else 1.2)
    return at


# --------------------------------------------------------------------------------------------------------------------
# Atlas des équipements
# --------------------------------------------------------------------------------------------------------------------
EQUIP_STRIPS = (("cb_black", 1.0), ("cb_grey", 1.0), ("cb_earth", 0.5), ("cb_copper", 0.5), ("cb_fibre", 1.0), ("cb_hazard", 0.5), ("cb_web", 0.5), ("cb_rubber", 1.0))
EQUIP_RECTS = {
    # clé : (x, y, w, h)
    "radome_front": (0, 256, 192, 768), "radome_back": (192, 256, 192, 768), "radome_side": (384, 256, 96, 768),
    "cab_front": (480, 256, 512, 768), "cab_side": (992, 256, 384, 768), "shelter_front": (1376, 256, 672, 768),
    "dish_front": (0, 1024, 512, 512), "dish_back": (512, 1024, 512, 512), "concrete": (1024, 1024, 512, 512),
    "shelter_side": (1536, 1024, 512, 512),
    "rru_front": (0, 1536, 256, 256), "rru_side": (256, 1536, 128, 256), "genset_side": (384, 1536, 512, 256),
    "genset_end": (896, 1536, 256, 256), "solar": (1152, 1536, 384, 256), "case_top": (1536, 1536, 256, 256),
    "sandbag": (1792, 1536, 256, 256),
    "sign_rf": (0, 1792, 256, 256), "sign_id": (256, 1792, 256, 256), "mpu5": (512, 1792, 128, 256),
    "mpu5_side": (640, 1792, 64, 256), "jerrycan": (704, 1792, 192, 256), "tyre": (896, 1792, 256, 256),
    "plastic": (1152, 1792, 128, 256), "olive": (1280, 1792, 128, 256), "cab_top": (1408, 1792, 192, 256),
    "lamp": (1600, 1792, 64, 256), "deck": (1664, 1792, 256, 256), "grille": (1920, 1792, 128, 256),
}
CAB = lin(188, 190, 182)     # gris clair armoire télécom
RADOME = lin(226, 227, 222)
OLIVE = lin(74, 80, 54)
BLACK = lin(28, 29, 30)


def weather(at, key, rng, dirt=0.5, streaks=0.6, edge=0.5, rust=0.0, grime_col=(0.10, 0.085, 0.065)):
    """Vieillissement d'une région rectangulaire (haut de l'image = haut de la pièce) : coulures verticales, salissure
    en bas, arêtes assombries (ombre ambiante peinte), poussière. Écrit aussi la carte d'ombre ambiante."""
    s = at.sl(key)
    h, w = at.rgb[s].shape[:2]
    yy = np.linspace(0, 1, h)[:, None] * np.ones((1, w))
    xx = np.ones((h, 1)) * np.linspace(0, 1, w)[None, :]
    n1 = noise(256, 256, 2.0, rng=rng)
    n1 = np.asarray(Image.fromarray((n1 * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC), dtype=np.float64) / 255
    col = rng.random((1, w))
    col = blur(np.repeat(col, 4, 0), 1)[0:1, :]
    start = rng.random((1, w)) * 0.5
    st = smoothstep(0.62, 0.9, col) * np.clip((yy - start) * 3, 0, 1) * np.exp(-np.clip(yy - start, 0, 1) * 2.2)
    st = blur(st, 1) * streaks
    low = smoothstep(0.72, 1.0, yy) * (0.5 + 0.5 * n1) * dirt
    g = np.array(grime_col)
    at.rgb[s] = mix(at.rgb[s], np.broadcast_to(g, (h, w, 3)), np.clip(st * 0.55 + low * 0.6 + 0.10 * dirt * (n1 - 0.4), 0, 0.85))
    if rust > 0:
        rm = smoothstep(0.80, 0.95, n1 * 0.6 + 0.4 * noise(h, w, 1.0, rng=rng) if max(h, w) <= 1024 else n1) * rust
        rm = np.maximum(rm, np.clip(blur(rm, 2) * 2, 0, 1) * st * 2)
        at.rgb[s] = mix(at.rgb[s], np.broadcast_to(RUST, (h, w, 3)), np.clip(rm, 0, 1))
        at.spec[s] = at.spec[s] * (1 - np.clip(rm, 0, 1) * 0.8)
    d = np.minimum(np.minimum(xx, 1 - xx) * w, np.minimum(yy, 1 - yy) * h)
    ao = 1 - edge * 0.5 * np.exp(-d / 10.0)
    at.rgb[s] = at.rgb[s] * (0.75 + 0.25 * ao)[..., None]
    at.ao[s] = np.minimum(at.ao[s], ao * (1 - 0.25 * smoothstep(0.8, 1.0, yy)))
    at.gloss[s] = at.gloss[s] * (1 - 0.5 * np.clip(st + low, 0, 1))


def base(at, key, color, rng, var=0.08, grain=0.03, spec=0.25, gloss=0.4, metal=0.0, hbeta=2.6, hamp=0.04):
    s = at.sl(key)
    h, w = at.rgb[s].shape[:2]
    a = noise(min(h, 512), min(w, 512), 2.2, rng=rng)
    b = noise(min(h, 512), min(w, 512), hbeta, rng=rng)
    if a.shape != (h, w):
        a = np.asarray(Image.fromarray((a * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC), dtype=np.float64) / 255
        b = np.asarray(Image.fromarray((b * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC), dtype=np.float64) / 255
    f = rng.random((h, w))
    at.rgb[s] = np.asarray(color)[None, None, :] * (1 + var * 2 * (a - 0.5) + grain * 2 * (f - 0.5))[..., None]
    at.height[s] = 0.5 + hamp * 2 * (b - 0.5)
    at.spec[s], at.gloss[s], at.metal[s] = spec, gloss, metal
    at.ao[s] = 1.0


def stamp(at, key, draw_fn, height=None, spec=None, gloss=None, metal=None, shadow=0.0):
    """Dessin Pillow posé sur la région. shadow > 0 : ombre portée douce sous le dessin (relief peint)."""
    img, d = at.draw(key)
    draw_fn(d, img.size[0], img.size[1])
    s = at.sl(key)
    if shadow > 0:
        a = np.asarray(img, dtype=np.float64)[..., 3] / 255
        sh = np.clip(blur(np.roll(a, 3, 0), 3) - a, 0, 1) * shadow
        at.rgb[s] = at.rgb[s] * (1 - sh)[..., None]
        at.ao[s] = at.ao[s] * (1 - sh)
    at.paste(key, img, height, spec, gloss, metal)


def louvres(d, x0, y0, x1, y1, pitch, col_dark, col_light):
    y = y0
    while y + pitch <= y1:
        d.rectangle([x0, y, x1, y + pitch * 0.55], fill=col_dark)
        d.line([x0, y + pitch * 0.55, x1, y + pitch * 0.55], fill=col_light, width=2)
        y += pitch


def warning_triangle(d, cx, cy, r, text=None):
    """Triangle d'avertissement jaune à bord noir avec un symbole d'onde (dessin original)."""
    pts = [(cx, cy - r), (cx + r * 0.92, cy + r * 0.62), (cx - r * 0.92, cy + r * 0.62)]
    d.polygon(pts, fill=(18, 18, 18, 255))
    k = 0.80
    pts2 = [(cx, cy - r * k + r * 0.06), (cx + r * 0.92 * k, cy + r * 0.62 * k + r * 0.02), (cx - r * 0.92 * k, cy + r * 0.62 * k + r * 0.02)]
    d.polygon(pts2, fill=(244, 196, 22, 255))
    bx, by = cx, cy + r * 0.32
    d.ellipse([bx - r * 0.07, by - r * 0.07, bx + r * 0.07, by + r * 0.07], fill=(18, 18, 18, 255))
    d.line([bx, by, bx, by + r * 0.2], fill=(18, 18, 18, 255), width=max(2, int(r * 0.06)))
    for rr in (0.2, 0.33):
        d.arc([bx - r * rr, by - r * rr, bx + r * rr, by + r * rr], 215, 325, fill=(18, 18, 18, 255), width=max(2, int(r * 0.055)))


def equip_atlas():
    rng = np.random.default_rng(4117)
    W = H = 2048
    at = Atlas("relay_equip", W, H, TEX, "relay_equip.rvmat", base=(0.2, 0.2, 0.2))
    # --- Bandes de câbles (32 px, raccordables en U) ---
    for i, (key, tile) in enumerate(EQUIP_STRIPS):
        s = at.region(key, 0, i * 32, W, 32, "strip", tile, pad=3)
        n = noise(32, W, 1.4, rng=rng)
        xx = np.arange(W)[None, :] * np.ones((32, 1))
        yy = np.arange(32)[:, None] * np.ones((1, W))
        if key == "cb_black":
            at.rgb[s] = (BLACK * 0.8)[None, None, :] * (0.8 + 0.5 * n[..., None])
            lay = Image.new("RGBA", (W, 32), (0, 0, 0, 0))
            dd = ImageDraw.Draw(lay)
            f = font(11, False)
            for x in range(60, W, 512):
                dd.text((x, 9), "RF 7/8  50 OHM  CU/PE   -|- 1 m -|-", fill=(150, 150, 140, 140), font=f)
            for x in range(300, W, 683):
                dd.rectangle([x, 0, x + 9, 32], fill=(12, 12, 12, 255))
            at.paste(key, lay)
            at.spec[s], at.gloss[s] = 0.25, 0.45
            at.height[s] = 0.5 + 0.08 * np.sin(xx * 2 * math.pi / 6.0)
        elif key == "cb_grey":
            at.rgb[s] = lin(95, 98, 100)[None, None, :] * (0.85 + 0.3 * n[..., None])
            at.spec[s], at.gloss[s] = 0.2, 0.35
        elif key == "cb_earth":
            stripe = (np.mod(xx + yy * 2.0, 96) < 48)
            at.rgb[s] = np.where(stripe[..., None], lin(214, 190, 30)[None, None, :], lin(40, 120, 50)[None, None, :]) * (0.85 + 0.3 * n[..., None])
            at.spec[s], at.gloss[s] = 0.25, 0.4
        elif key == "cb_copper":
            strand = 0.5 + 0.5 * np.sin((xx + yy * 1.5) * 2 * math.pi / 10.0)
            patina = smoothstep(0.55, 0.8, noise(32, W, 2.0, rng=rng))
            col = lin(184, 110, 60)[None, None, :] * (0.6 + 0.6 * strand[..., None])
            at.rgb[s] = mix(col, np.broadcast_to(lin(70, 120, 100), (32, W, 3)), patina * 0.6)
            at.height[s] = 0.3 + 0.4 * strand
            at.spec[s], at.gloss[s], at.metal[s] = 0.55, 0.5, 1.0
        elif key == "cb_fibre":
            at.rgb[s] = lin(226, 150, 20)[None, None, :] * (0.85 + 0.3 * n[..., None])
            at.spec[s], at.gloss[s] = 0.3, 0.5
        elif key == "cb_hazard":
            stripe = (np.mod(xx + yy, 128) < 64)
            at.rgb[s] = np.where(stripe[..., None], lin(236, 190, 20)[None, None, :], lin(20, 20, 20)[None, None, :]) * (0.8 + 0.4 * n[..., None])
            at.spec[s], at.gloss[s] = 0.2, 0.35
        elif key == "cb_web":
            weave = 0.5 + 0.25 * np.sin(xx * 2 * math.pi / 5.0) * np.sin(yy * 2 * math.pi / 5.0)
            at.rgb[s] = OLIVE[None, None, :] * (0.7 + 0.6 * weave[..., None]) * (0.85 + 0.3 * n[..., None])
            at.height[s] = weave
            at.spec[s], at.gloss[s] = 0.05, 0.1
        else:
            at.rgb[s] = (BLACK * 0.6)[None, None, :] * (0.8 + 0.5 * n[..., None])
            at.spec[s], at.gloss[s] = 0.12, 0.2
            at.height[s] = 0.5 + 0.2 * (noise(32, W, 0.5, rng=rng) - 0.5)
    for key, (x, y, w, h) in EQUIP_RECTS.items():
        at.region(key, x, y, w, h, "rect", pad=3)

    DARK = srgb8(lin(60, 62, 62)) + (255,)
    LINE = (52, 54, 54, 255)

    # --- Antenne panneau : radôme fibre blanc cassé, capots gris, étiquette, connecteurs en bas ---
    for key in ("radome_front", "radome_back", "radome_side"):
        base(at, key, RADOME, rng, var=0.04, grain=0.012, spec=0.3, gloss=0.5, hamp=0.015)

    def rad(d, w, h, back=False, side=False):
        cap = srgb8(lin(120, 124, 126)) + (255,)
        d.rectangle([0, 0, w, 22], fill=cap)
        d.rectangle([0, h - 30, w, h], fill=cap)
        d.line([0, 22, w, 22], fill=LINE, width=2)
        d.line([0, h - 30, w, h - 30], fill=LINE, width=2)
        if back:
            # rail de fixation alu au dos, deux étriers
            d.rectangle([w * 0.38, 26, w * 0.62, h - 34], fill=srgb8(GALVA * 1.3) + (255,))
            d.line([w * 0.38, 26, w * 0.38, h - 34], fill=LINE, width=2)
            d.line([w * 0.62, 26, w * 0.62, h - 34], fill=LINE, width=2)
            for yy_ in (0.14, 0.84):
                d.rectangle([w * 0.2, h * yy_ - 16, w * 0.8, h * yy_ + 16], fill=srgb8(GALVA * 1.1) + (255,), outline=LINE)
                for xx_ in (0.28, 0.72):
                    d.ellipse([w * xx_ - 6, h * yy_ - 6, w * xx_ + 6, h * yy_ + 6], fill=(70, 72, 74, 255))
            d.rectangle([w * 0.1, h * 0.60, w * 0.9, h * 0.70], fill=(236, 236, 230, 255), outline=LINE)
            f = font(11, False)
            for i, t in enumerate(("X-POL 65° 17.5 dBi", "1695-2690 MHz", "S/N CS-%05d" % 40117)):
                d.text((w * 0.13, h * 0.605 + i * 22), t, fill=(30, 30, 30, 255), font=f)
        elif not side:
            d.line([w * 0.5, 30, w * 0.5, h - 38], fill=(200, 200, 196, 60), width=1)
    stamp(at, "radome_front", lambda d, w, h: rad(d, w, h))
    stamp(at, "radome_back", lambda d, w, h: rad(d, w, h, back=True), height=0.62, shadow=0.35)
    stamp(at, "radome_side", lambda d, w, h: rad(d, w, h, side=True))
    for key in ("radome_front", "radome_back", "radome_side"):
        weather(at, key, rng, dirt=0.35, streaks=0.8, edge=0.5, grime_col=(0.16, 0.14, 0.11))

    # --- Armoire extérieure : portes, poignée, ouïes, plaque danger RF, plinthe ---
    for key in ("cab_front", "cab_side", "cab_top"):
        base(at, key, CAB, rng, var=0.05, grain=0.015, spec=0.3, gloss=0.42, hamp=0.03)

    def cab_front(d, w, h):
        d.rectangle([0, 0, w, 26], fill=srgb8(CAB * 0.7) + (255,))            # larmier
        d.rectangle([0, h - 40, w, h], fill=srgb8(lin(70, 72, 70)) + (255,))  # plinthe
        d.rectangle([10, 34, w - 10, h - 48], outline=LINE, width=3)           # porte
        d.line([w * 0.5, 34, w * 0.5, h - 48], fill=LINE, width=3)
        louvres(d, w * 0.08, 70, w * 0.44, 190, 14, (40, 42, 42, 255), (215, 216, 210, 255))
        louvres(d, w * 0.56, 70, w * 0.92, 190, 14, (40, 42, 42, 255), (215, 216, 210, 255))
        louvres(d, w * 0.08, h - 190, w * 0.44, h - 70, 14, (40, 42, 42, 255), (215, 216, 210, 255))
        louvres(d, w * 0.56, h - 190, w * 0.92, h - 70, 14, (40, 42, 42, 255), (215, 216, 210, 255))
        d.rounded_rectangle([w * 0.515, h * 0.44, w * 0.555, h * 0.60], 6, fill=(34, 36, 38, 255))     # poignée
        d.ellipse([w * 0.524, h * 0.565, w * 0.546, h * 0.585], fill=(150, 150, 140, 255))            # barillet
        for yy_ in (0.12, 0.5, 0.86):                                                                   # charnières
            d.rectangle([w - 14, h * yy_ - 22, w - 4, h * yy_ + 22], fill=(120, 122, 120, 255), outline=LINE)
            d.rectangle([4, h * yy_ - 22, 14, h * yy_ + 22], fill=(120, 122, 120, 255), outline=LINE)
        warning_triangle(d, w * 0.27, h * 0.40, 52)
        f = font(15)
        d.rectangle([w * 0.10, h * 0.47, w * 0.44, h * 0.535], fill=(244, 196, 22, 255), outline=(18, 18, 18, 255))
        d.text((w * 0.115, h * 0.478), "DANGER RF", fill=(18, 18, 18, 255), font=f)
        d.text((w * 0.115, h * 0.505), "NE PAS OUVRIR", fill=(18, 18, 18, 255), font=font(11))
        d.rectangle([w * 0.60, h * 0.34, w * 0.90, h * 0.40], fill=(225, 226, 220, 255), outline=LINE)
        d.text((w * 0.615, h * 0.348), "SITE RELAIS", fill=(30, 30, 30, 255), font=font(13))
        d.text((w * 0.615, h * 0.372), "CS-R  48 V DC  IP55", fill=(60, 60, 60, 255), font=font(10, False))
    stamp(at, "cab_front", cab_front, height=0.42, shadow=0.3)

    def cab_side(d, w, h):
        d.rectangle([0, 0, w, 26], fill=srgb8(CAB * 0.7) + (255,))
        d.rectangle([0, h - 40, w, h], fill=srgb8(lin(70, 72, 70)) + (255,))
        d.rectangle([12, 36, w - 12, h - 50], outline=(150, 152, 146, 255), width=2)
        louvres(d, w * 0.15, h * 0.12, w * 0.85, h * 0.30, 14, (40, 42, 42, 255), (215, 216, 210, 255))
        for xx_ in (0.2, 0.4, 0.6, 0.8):                                         # presse-étoupes en bas
            d.ellipse([w * xx_ - 13, h - 86, w * xx_ + 13, h - 60], fill=(30, 30, 32, 255), outline=(90, 90, 90, 255))
    stamp(at, "cab_side", cab_side, height=0.42, shadow=0.3)

    def cab_top(d, w, h):
        d.rectangle([6, 6, w - 6, h - 6], outline=(150, 152, 146, 255), width=3)
        d.line([w * 0.5, 6, w * 0.5, h - 6], fill=(160, 162, 156, 255), width=2)
    stamp(at, "cab_top", cab_top)
    weather(at, "cab_front", rng, dirt=0.6, streaks=0.9, edge=0.6, rust=0.25)
    weather(at, "cab_side", rng, dirt=0.6, streaks=1.0, edge=0.6, rust=0.3)
    weather(at, "cab_top", rng, dirt=0.3, streaks=0.0, edge=0.4, rust=0.15)

    # --- Abri préfabriqué : tôle nervurée beige, porte acier, traversée de câbles, bloc clim ---
    SH = lin(196, 192, 176)
    for key in ("shelter_front", "shelter_side"):
        base(at, key, SH, rng, var=0.05, grain=0.012, spec=0.22, gloss=0.35)
        s = at.sl(key)
        h, w = at.rgb[s].shape[:2]
        rib = 0.5 + 0.5 * np.cos(np.arange(w)[None, :] * 2 * math.pi / 42.0) * np.ones((h, 1))
        rib = smoothstep(0.55, 0.95, rib)
        at.height[s] = 0.45 + 0.3 * rib
        at.rgb[s] = at.rgb[s] * (0.86 + 0.14 * rib)[..., None]

    def sh_front(d, w, h):
        d.rectangle([0, 0, w, 20], fill=srgb8(SH * 0.6) + (255,))
        d.rectangle([0, h - 30, w, h], fill=srgb8(lin(96, 96, 92)) + (255,))
        x0, x1 = w * 0.30, w * 0.62
        d.rectangle([x0 - 8, 80, x1 + 8, h - 30], fill=srgb8(SH * 0.5) + (255,))
        d.rectangle([x0, 88, x1, h - 34], fill=srgb8(lin(112, 118, 116)) + (255,), outline=LINE, width=2)
        d.rounded_rectangle([x1 - 44, h * 0.52, x1 - 16, h * 0.56], 5, fill=(30, 30, 32, 255))
        d.rectangle([x1 - 40, h * 0.565, x1 - 20, h * 0.60], fill=(150, 140, 60, 255))                 # cadenas
        for yy_ in (0.2, 0.5, 0.8):
            d.rectangle([x0 - 6, h * yy_, x0 + 6, h * yy_ + 40], fill=(90, 92, 90, 255))
        warning_triangle(d, w * 0.46, h * 0.30, 44)
        d.rectangle([w * 0.37, h * 0.38, w * 0.55, h * 0.43], fill=(244, 196, 22, 255), outline=(18, 18, 18, 255))
        d.text((w * 0.378, h * 0.388), "DANGER RF", fill=(18, 18, 18, 255), font=font(15))
        d.rectangle([w * 0.72, 150, w * 0.95, 330], fill=(206, 206, 198, 255), outline=LINE, width=2)   # clim murale
        louvres(d, w * 0.735, 165, w * 0.935, 315, 13, (44, 46, 46, 255), (220, 220, 214, 255))
        d.rectangle([w * 0.05, 70, w * 0.22, 150], fill=(150, 152, 150, 255), outline=LINE, width=2)    # traversée de câbles
        for i in range(6):
            cx, cy = w * 0.078 + (i % 3) * w * 0.057, 92 + (i // 3) * 38
            d.ellipse([cx - 13, cy - 13, cx + 13, cy + 13], fill=(22, 22, 24, 255), outline=(80, 80, 80, 255))
        d.rectangle([w * 0.80, h * 0.62, w * 0.93, h * 0.68], fill=(228, 228, 222, 255), outline=LINE)
        d.text((w * 0.808, h * 0.632), "CS-R 01", fill=(30, 30, 30, 255), font=font(13))
    stamp(at, "shelter_front", sh_front, height=0.42, shadow=0.35)

    def sh_side(d, w, h):
        d.rectangle([0, 0, w, 14], fill=srgb8(SH * 0.6) + (255,))
        d.rectangle([0, h - 20, w, h], fill=srgb8(lin(96, 96, 92)) + (255,))
        d.rectangle([w * 0.62, h * 0.18, w * 0.9, h * 0.52], fill=(206, 206, 198, 255), outline=LINE, width=2)
        louvres(d, w * 0.64, h * 0.21, w * 0.88, h * 0.49, 12, (44, 46, 46, 255), (220, 220, 214, 255))
    stamp(at, "shelter_side", sh_side, height=0.42, shadow=0.35)
    weather(at, "shelter_front", rng, dirt=0.7, streaks=1.0, edge=0.5, rust=0.3)
    weather(at, "shelter_side", rng, dirt=0.7, streaks=1.0, edge=0.5, rust=0.3)

    # --- Parabole : radôme souple gris clair (face), cuvette alu peinte (dos, UV polaires) ---
    base(at, "dish_front", lin(214, 216, 214), rng, var=0.04, grain=0.01, spec=0.2, gloss=0.3)
    base(at, "dish_back", lin(168, 172, 172), rng, var=0.05, grain=0.015, spec=0.35, gloss=0.45)

    def dish_f(d, w, h):
        d.ellipse([6, 6, w - 6, h - 6], outline=(120, 122, 122, 255), width=10)       # cerclage
        d.ellipse([18, 18, w - 18, h - 18], outline=(190, 192, 190, 255), width=3)
        for k in range(24):                                                           # crochets de tension
            a = k * math.pi / 12
            d.ellipse([w / 2 + (w / 2 - 12) * math.cos(a) - 4, h / 2 + (h / 2 - 12) * math.sin(a) - 4,
                       w / 2 + (w / 2 - 12) * math.cos(a) + 4, h / 2 + (h / 2 - 12) * math.sin(a) + 4], fill=(60, 60, 62, 255))
    stamp(at, "dish_front", dish_f)

    def dish_b(d, w, h):
        for k in range(12):
            a = k * math.pi / 6
            d.line([w / 2, h / 2, w / 2 + w * 0.49 * math.cos(a), h / 2 + h * 0.49 * math.sin(a)], fill=(120, 124, 124, 255), width=4)
        d.ellipse([w * 0.34, h * 0.34, w * 0.66, h * 0.66], fill=(110, 114, 114, 255), outline=LINE, width=3)
        for k in range(8):
            a = k * math.pi / 4 + 0.2
            d.ellipse([w / 2 + w * 0.13 * math.cos(a) - 6, h / 2 + h * 0.13 * math.sin(a) - 6, w / 2 + w * 0.13 * math.cos(a) + 6, h / 2 + h * 0.13 * math.sin(a) + 6], fill=(60, 62, 62, 255))
        d.ellipse([4, 4, w - 4, h - 4], outline=(96, 100, 100, 255), width=8)
    stamp(at, "dish_back", dish_b, height=0.6, shadow=0.3)
    weather(at, "dish_front", rng, dirt=0.3, streaks=0.9, edge=0.0, grime_col=(0.15, 0.13, 0.10))
    weather(at, "dish_back", rng, dirt=0.4, streaks=0.8, edge=0.0, rust=0.2)

    # --- Béton : banché, bullage, fissures, coulures de rouille, mousse en pied ---
    s = at.sl("concrete")
    h, w = 512, 512
    a, b, f = noise(h, w, 2.4, rng=rng), noise(h, w, 1.2, rng=rng), rng.random((h, w))
    g = 0.30 + 0.10 * (a - 0.5) + 0.05 * (b - 0.5) + 0.03 * (f - 0.5)
    rgb = g[..., None] * np.array([1.0, 0.98, 0.93])[None, None, :]
    pores = smoothstep(0.985, 0.995, blur(rng.random((h, w)), 1) * 0.3 + rng.random((h, w)) * 0.7)
    pores = np.clip(blur(pores, 1) * 4, 0, 1)
    lay = Image.new("L", (w, h), 0)
    dd = ImageDraw.Draw(lay)
    for _ in range(5):
        x, y = rng.random() * w, rng.random() * h
        ang = rng.random() * 6.28
        for _ in range(40):
            nx, ny = x + math.cos(ang) * 9, y + math.sin(ang) * 9
            dd.line([x, y, nx, ny], fill=255, width=1)
            x, y, ang = nx, ny, ang + (rng.random() - 0.5) * 0.9
    crack = np.asarray(lay, dtype=np.float64) / 255
    board = (np.mod(np.arange(h), 128) < 2).astype(np.float64)[:, None] * np.ones((1, w))
    rgb = rgb * (1 - 0.5 * pores[..., None]) * (1 - 0.6 * crack[..., None]) * (1 - 0.25 * board[..., None])
    stain = smoothstep(0.6, 0.85, noise(h, w, 2.6, aniso=(1.0, 0.25), rng=rng))
    rgb = mix(rgb, rgb * np.array([0.85, 0.6, 0.42]), stain * 0.5)
    moss = smoothstep(0.55, 0.8, noise(h, w, 1.8, rng=rng)) * smoothstep(0.7, 1.0, np.linspace(0, 1, h)[:, None] * np.ones((1, w)))
    rgb = mix(rgb, np.broadcast_to(np.array([0.06, 0.09, 0.03]), (h, w, 3)), moss * 0.7)
    at.rgb[s] = rgb
    at.height[s] = 0.5 + 0.25 * (b - 0.5) - 0.35 * pores - 0.4 * crack - 0.2 * board
    at.spec[s], at.gloss[s] = 0.06, 0.1

    # --- RRU : carter alu gris à ailettes, capot, connecteurs ---
    base(at, "rru_front", lin(170, 174, 176), rng, var=0.04, spec=0.4, gloss=0.5, metal=0.6)
    base(at, "rru_side", lin(150, 154, 156), rng, var=0.04, spec=0.4, gloss=0.5, metal=0.6)

    def rru_f(d, w, h):
        for x in range(14, w - 10, 12):
            d.rectangle([x, 16, x + 5, h - 56], fill=(84, 88, 90, 255))
        d.rectangle([0, h - 50, w, h], fill=(120, 124, 126, 255), outline=LINE)
        for i in range(4):
            cx = w * (0.16 + i * 0.22)
            d.ellipse([cx - 13, h - 38, cx + 13, h - 12], fill=(190, 170, 90, 255), outline=(60, 60, 60, 255), width=2)
            d.ellipse([cx - 5, h - 30, cx + 5, h - 20], fill=(30, 30, 30, 255))
        d.rectangle([w * 0.3, 2, w * 0.7, 14], fill=(228, 228, 222, 255))
    stamp(at, "rru_front", rru_f, height=0.3)

    def rru_s(d, w, h):
        for y in range(18, h - 60, 12):
            d.rectangle([8, y, w - 8, y + 5], fill=(84, 88, 90, 255))
        d.rectangle([0, h - 50, w, h], fill=(120, 124, 126, 255), outline=LINE)
    stamp(at, "rru_side", rru_s, height=0.3)
    weather(at, "rru_front", rng, dirt=0.4, streaks=0.6, edge=0.6)
    weather(at, "rru_side", rng, dirt=0.4, streaks=0.6, edge=0.6)

    # --- Groupe électrogène capoté (jaune engin), côté et bout ---
    GEN = lin(214, 160, 26)
    for key in ("genset_side", "genset_end"):
        base(at, key, GEN, rng, var=0.06, spec=0.3, gloss=0.42)

    def gen_s(d, w, h):
        d.rectangle([0, h - 34, w, h], fill=(36, 36, 38, 255))                              # châssis traîneau
        d.rectangle([w * 0.04, 20, w * 0.48, h - 46], outline=LINE, width=3)                # trappes
        d.rectangle([w * 0.52, 20, w * 0.96, h - 46], outline=LINE, width=3)
        louvres(d, w * 0.07, 40, w * 0.45, 120, 12, (36, 36, 38, 255), (236, 190, 60, 255))
        louvres(d, w * 0.55, 40, w * 0.80, 120, 12, (36, 36, 38, 255), (236, 190, 60, 255))
        d.rounded_rectangle([w * 0.84, 60, w * 0.93, 76], 4, fill=(30, 30, 32, 255))
        d.rectangle([w * 0.55, 140, w * 0.75, 180], fill=(228, 228, 222, 255), outline=LINE)
        d.text((w * 0.56, 146), "GE 8 kVA", fill=(20, 20, 20, 255), font=font(15))
        d.text((w * 0.56, 164), "DIESEL - 230 V", fill=(50, 50, 50, 255), font=font(9, False))
        warning_triangle(d, w * 0.14, 168, 26)
    stamp(at, "genset_side", gen_s, height=0.42, shadow=0.3)

    def gen_e(d, w, h):
        d.rectangle([0, h - 34, w, h], fill=(36, 36, 38, 255))
        d.rectangle([w * 0.12, 26, w * 0.88, h * 0.55], fill=(46, 48, 50, 255), outline=LINE, width=3)   # pupitre
        for i in range(3):
            cx = w * (0.26 + i * 0.24)
            d.ellipse([cx - 17, 40, cx + 17, 74], fill=(226, 226, 220, 255), outline=(20, 20, 20, 255), width=2)
            d.line([cx, 57, cx + 9, 47], fill=(200, 30, 20, 255), width=2)
        d.ellipse([w * 0.22, 92, w * 0.34, 92 + w * 0.12], fill=(190, 30, 24, 255), outline=(20, 20, 20, 255), width=2)  # arrêt d'urgence
        for i in range(3):
            d.rectangle([w * (0.46 + i * 0.13), 96, w * (0.46 + i * 0.13) + 20, 124], fill=(20, 60, 140, 255), outline=(10, 10, 10, 255))
    stamp(at, "genset_end", gen_e, height=0.42, shadow=0.3)
    weather(at, "genset_side", rng, dirt=0.8, streaks=0.9, edge=0.6, rust=0.35, grime_col=(0.05, 0.04, 0.03))
    weather(at, "genset_end", rng, dirt=0.8, streaks=0.7, edge=0.6, rust=0.3, grime_col=(0.05, 0.04, 0.03))

    # --- Panneau solaire : cellules bleu nuit, barres collectrices, cadre alu ---
    base(at, "solar", lin(18, 26, 52), rng, var=0.10, grain=0.02, spec=0.85, gloss=0.9)

    def solar(d, w, h):
        for i in range(1, 12):
            d.line([w * i / 12, 0, w * i / 12, h], fill=(170, 176, 186, 255), width=2)
        for j in range(1, 6):
            d.line([0, h * j / 6, w, h * j / 6], fill=(170, 176, 186, 255), width=2)
        for i in range(12):
            for k in (0.33, 0.67):
                x = w * (i + k) / 12
                d.line([x, 0, x, h], fill=(120, 130, 150, 120), width=1)
        d.rectangle([0, 0, w - 1, h - 1], outline=(176, 180, 184, 255), width=9)
    stamp(at, "solar", solar)
    weather(at, "solar", rng, dirt=0.5, streaks=0.3, edge=0.0, grime_col=(0.22, 0.19, 0.14))

    # --- Caisse de transport (polymère noir nervuré), loquets, étiquette ---
    base(at, "case_top", lin(40, 42, 44), rng, var=0.08, grain=0.05, spec=0.2, gloss=0.3, hamp=0.08, hbeta=0.8)

    def case(d, w, h):
        for i in range(5):
            y = h * (0.12 + i * 0.19)
            d.rounded_rectangle([w * 0.08, y, w * 0.92, y + h * 0.09], 8, outline=(70, 72, 74, 255), width=4)
        for xx_ in (0.25, 0.75):
            d.rectangle([w * xx_ - 20, h - 34, w * xx_ + 20, h - 4], fill=(22, 22, 24, 255), outline=(90, 90, 92, 255), width=2)
        d.rectangle([w * 0.3, h * 0.42, w * 0.7, h * 0.58], fill=(214, 214, 206, 255))
        d.text((w * 0.33, h * 0.445), "RELAIS TERRAIN", fill=(20, 20, 20, 255), font=font(12))
        d.text((w * 0.33, h * 0.51), "RADIO / BATT 24 V", fill=(60, 60, 60, 255), font=font(9, False))
    stamp(at, "case_top", case, height=0.62, shadow=0.3)
    weather(at, "case_top", rng, dirt=0.7, streaks=0.2, edge=0.7, grime_col=(0.20, 0.16, 0.11))

    # --- Sac de sable : toile de jute tissée ---
    s = at.sl("sandbag")
    yy, xx = np.mgrid[0:256, 0:256]
    weave = 0.5 + 0.25 * (np.sin(xx * 2 * math.pi / 4.0) + np.sin(yy * 2 * math.pi / 4.0)) * 0.5
    n = noise(256, 256, 2.2, rng=rng)
    at.rgb[s] = lin(150, 128, 92)[None, None, :] * (0.65 + 0.5 * weave + 0.35 * (n - 0.5))[..., None]
    at.height[s] = 0.3 + 0.4 * weave + 0.3 * (noise(256, 256, 2.8, rng=rng) - 0.5)
    at.spec[s], at.gloss[s] = 0.03, 0.05
    weather(at, "sandbag", rng, dirt=0.8, streaks=0.0, edge=0.9, grime_col=(0.10, 0.075, 0.05))

    # --- Plaque d'avertissement RF et plaque d'identification du site ---
    base(at, "sign_rf", lin(240, 240, 234), rng, var=0.03, spec=0.3, gloss=0.5)

    def sign_rf(d, w, h):
        d.rectangle([4, 4, w - 4, h - 4], outline=(18, 18, 18, 255), width=5)
        d.rectangle([9, 9, w - 9, 66], fill=(200, 28, 22, 255))
        d.text((38, 18), "DANGER", fill=(250, 250, 246, 255), font=font(38))
        warning_triangle(d, w * 0.5, 128, 54)
        f = font(15)
        for i, t in enumerate(("RAYONNEMENT", "RADIOFRÉQUENCE", "ACCÈS RÉGLEMENTÉ")):
            tw = d.textlength(t, font=f)
            d.text(((w - tw) / 2, 178 + i * 21), t, fill=(18, 18, 18, 255), font=f)
        for cx, cy in ((16, 16), (w - 16, 16), (16, h - 16), (w - 16, h - 16)):
            d.ellipse([cx - 5, cy - 5, cx + 5, cy + 5], fill=(120, 120, 116, 255))
    stamp(at, "sign_rf", sign_rf)
    weather(at, "sign_rf", rng, dirt=0.4, streaks=0.7, edge=0.3, rust=0.25)
    base(at, "sign_id", lin(30, 62, 120), rng, var=0.04, spec=0.3, gloss=0.5)

    def sign_id(d, w, h):
        d.rectangle([4, 4, w - 4, h - 4], outline=(236, 236, 230, 255), width=3)
        d.text((16, 18), "SITE RELAIS", fill=(240, 240, 234, 255), font=font(30))
        d.text((16, 62), "CS-R  N° 0417", fill=(240, 240, 234, 255), font=font(22))
        d.line([16, 100, w - 16, 100], fill=(236, 236, 230, 255), width=2)
        f = font(13, False)
        for i, t in enumerate(("Défense d'entrer - site surveillé", "Hauteur : voir plan de site", "Balisage nocturne : feux rouges", "En cas d'urgence : couper le", "disjoncteur général (armoire A)")):
            d.text((16, 112 + i * 22), t, fill=(226, 226, 220, 255), font=f)
        d.rectangle([w - 70, h - 60, w - 16, h - 18], fill=(244, 196, 22, 255))
        d.text((w - 62, h - 54), "48V", fill=(18, 18, 18, 255), font=font(20))
    stamp(at, "sign_id", sign_id)
    weather(at, "sign_id", rng, dirt=0.4, streaks=0.6, edge=0.3, rust=0.15)

    # --- Radio MANET de type MPU5 : boîtier anodisé sombre, molette, connecteurs, voyant ---
    for key in ("mpu5", "mpu5_side"):
        base(at, key, lin(52, 56, 50), rng, var=0.06, grain=0.03, spec=0.35, gloss=0.45, metal=0.5)

    def mpu(d, w, h):
        d.rounded_rectangle([6, 8, w - 6, h - 8], 10, outline=(26, 28, 26, 255), width=4)
        for y in range(70, h - 60, 12):
            d.line([16, y, w - 16, y], fill=(34, 36, 32, 255), width=5)
        d.rectangle([w * 0.2, 22, w * 0.8, 54], fill=(16, 18, 16, 255), outline=(90, 92, 86, 255))
        d.text((w * 0.25, 28), "MANET", fill=(170, 176, 160, 255), font=font(16))
        d.ellipse([w * 0.42, h - 46, w * 0.58, h - 46 + w * 0.16], fill=(20, 22, 20, 255), outline=(110, 112, 104, 255), width=2)
    stamp(at, "mpu5", mpu, height=0.6)

    def mpu_s(d, w, h):
        for y in range(30, h - 30, 12):
            d.line([10, y, w - 10, y], fill=(34, 36, 32, 255), width=5)
    stamp(at, "mpu5_side", mpu_s, height=0.4)
    weather(at, "mpu5", rng, dirt=0.5, streaks=0.2, edge=0.8, grime_col=(0.18, 0.15, 0.10))
    weather(at, "mpu5_side", rng, dirt=0.5, streaks=0.2, edge=0.8, grime_col=(0.18, 0.15, 0.10))

    # --- Jerrycan vert, pneu (flanc, UV polaires), plastique noir, peinture olive, lanterne, tôle larmée, grille ---
    base(at, "jerrycan", lin(62, 76, 44), rng, var=0.07, spec=0.3, gloss=0.4)

    def jerry(d, w, h):
        d.line([w * 0.12, h * 0.25, w * 0.88, h * 0.75], fill=(40, 50, 30, 255), width=7)
        d.line([w * 0.88, h * 0.25, w * 0.12, h * 0.75], fill=(40, 50, 30, 255), width=7)
        d.rectangle([w * 0.12, h * 0.25, w * 0.88, h * 0.75], outline=(40, 50, 30, 255), width=7)
        d.text((w * 0.3, h * 0.82), "GAZOLE 20 L", fill=(220, 210, 60, 255), font=font(12))
    stamp(at, "jerrycan", jerry, height=0.35, shadow=0.3)
    weather(at, "jerrycan", rng, dirt=0.7, streaks=0.5, edge=0.8, rust=0.4)
    base(at, "tyre", lin(26, 26, 27), rng, var=0.10, grain=0.05, spec=0.12, gloss=0.2, hamp=0.08, hbeta=1.0)

    def tyre(d, w, h):
        d.ellipse([w * 0.26, h * 0.26, w * 0.74, h * 0.74], fill=(150, 152, 150, 255), outline=(60, 60, 60, 255), width=4)
        d.ellipse([w * 0.42, h * 0.42, w * 0.58, h * 0.58], fill=(70, 72, 72, 255))
        for k in range(5):
            a = k * 2 * math.pi / 5
            cx, cy = w / 2 + w * 0.15 * math.cos(a), h / 2 + h * 0.15 * math.sin(a)
            d.ellipse([cx - 7, cy - 7, cx + 7, cy + 7], fill=(40, 40, 42, 255))
        for k in range(36):
            a = k * math.pi / 18
            d.line([w / 2 + w * 0.44 * math.cos(a), h / 2 + h * 0.44 * math.sin(a), w / 2 + w * 0.495 * math.cos(a + 0.05), h / 2 + h * 0.495 * math.sin(a + 0.05)], fill=(12, 12, 12, 255), width=5)
        d.ellipse([w * 0.14, h * 0.14, w * 0.86, h * 0.86], outline=(44, 44, 46, 255), width=3)
    stamp(at, "tyre", tyre, height=0.6)
    weather(at, "tyre", rng, dirt=0.9, streaks=0.0, edge=0.0, grime_col=(0.20, 0.16, 0.11))
    base(at, "plastic", lin(30, 31, 32), rng, var=0.08, grain=0.06, spec=0.18, gloss=0.3, hamp=0.06, hbeta=0.6)
    base(at, "olive", OLIVE, rng, var=0.08, grain=0.03, spec=0.2, gloss=0.3)
    weather(at, "olive", rng, dirt=0.6, streaks=0.5, edge=0.7, rust=0.3, grime_col=(0.20, 0.16, 0.11))
    base(at, "lamp", lin(150, 152, 150), rng, var=0.05, spec=0.5, gloss=0.5, metal=0.8)

    def lamp(d, w, h):
        for y in range(8, h, 22):
            d.line([0, y, w, y], fill=(96, 98, 98, 255), width=4)
    stamp(at, "lamp", lamp, height=0.3)
    s = at.sl("deck")
    yy, xx = np.mgrid[0:256, 0:256]
    tread = ((np.mod(xx + yy, 32) < 5) & (np.mod(xx - yy, 64) < 22)) | ((np.mod(xx - yy + 16, 32) < 5) & (np.mod(xx + yy + 32, 64) < 22))
    n = noise(256, 256, 2.0, rng=rng)
    at.rgb[s] = (GALVA * 0.85)[None, None, :] * (0.8 + 0.4 * n[..., None]) * np.where(tread, 1.25, 1.0)[..., None]
    at.height[s] = 0.4 + 0.4 * blur(tread.astype(np.float64), 1)
    at.spec[s], at.gloss[s], at.metal[s] = 0.5, 0.45, 1.0
    weather(at, "deck", rng, dirt=0.8, streaks=0.0, edge=0.5, rust=0.5, grime_col=(0.12, 0.09, 0.06))
    base(at, "grille", lin(196, 198, 192), rng, var=0.04, spec=0.3, gloss=0.4)

    def grille(d, w, h):
        d.rectangle([4, 4, w - 4, h - 4], outline=LINE, width=3)
        louvres(d, 12, 14, w - 12, h * 0.6, 11, (40, 42, 42, 255), (222, 222, 216, 255))
        d.ellipse([w * 0.2, h * 0.64, w * 0.8, h * 0.64 + w * 0.6], fill=(34, 36, 36, 255), outline=(120, 122, 120, 255), width=3)
        for k in range(6):
            a = k * math.pi / 3
            cx, cy = w * 0.5, h * 0.64 + w * 0.3
            d.line([cx, cy, cx + w * 0.28 * math.cos(a), cy + w * 0.28 * math.sin(a)], fill=(120, 122, 120, 255), width=2)
    stamp(at, "grille", grille, height=0.4, shadow=0.3)
    weather(at, "grille", rng, dirt=0.6, streaks=0.9, edge=0.5, rust=0.3)
    return at


def mesh_atlas():
    """Grillage simple torsion (bande haute, 1 m par tuile, 0,5 m de haut) et caillebotis pressé (bande basse)."""
    rng = np.random.default_rng(99)
    W = H = 512
    at = Atlas("relay_mesh", W, H, TEX, "relay_mesh.rvmat", base=tuple(GALVA))
    at.region("fence", 0, 0, W, 256, "strip", 1.0, pad=0)
    at.region("grating", 0, 256, W, 256, "strip", 0.5, pad=0)
    at.alpha[:] = 0
    yy, xx = np.mgrid[0:256, 0:W].astype(np.float64)
    p = 512 / 16.0       # maille de 62,5 mm
    d1 = np.abs(np.mod(xx + yy, p) - p / 2)
    d2 = np.abs(np.mod(xx - yy, p) - p / 2)
    wire = np.minimum(d1, d2) < 2.3
    n = noise(256, W, 1.5, rng=rng)
    at.alpha[0:256] = wire
    at.rgb[0:256] = (GALVA * 1.15)[None, None, :] * (0.7 + 0.6 * n[..., None])
    rust = smoothstep(0.7, 0.9, noise(256, W, 2.2, rng=rng))
    at.rgb[0:256] = mix(at.rgb[0:256], np.broadcast_to(RUST, (256, W, 3)), rust * 0.6)
    # Caillebotis : barres porteuses (le long de U) tous les 34 mm, entretoises tous les 100 mm (tuile de 0,5 m).
    bar = (np.mod(yy, 256 / 8.0) < 6.5)
    cross = (np.mod(xx, W / 10.0) < 5)
    at.alpha[256:512] = bar | cross
    at.rgb[256:512] = (GALVA * 0.95)[None, None, :] * (0.7 + 0.6 * noise(256, W, 1.5, rng=rng)[..., None]) * np.where(bar, 1.0, 0.8)[..., None]
    at.spec[:], at.gloss[:], at.metal[:] = 0.4, 0.4, 1.0
    return at


def build_textures(tmp, data_dir, convert=True, variants=("rw", "olive", "sand")):
    """Peint les atlas, écrit les PNG dans tmp et (convert) les PAA + rvmat dans data_dir.
    Renvoie {chemin de texture MLOD: {co, nohq, smdi, rm}} pour l'export OBJ / Blender."""
    out = {}
    st = steel_atlas()
    p = st.save(tmp, nstrength=4.0)
    out[st.tex] = p
    eq = equip_atlas()
    pe = eq.save(tmp, suffixes=("co", "nohq", "smdi", "as"), nstrength=4.0)
    out[eq.tex] = pe
    ms = mesh_atlas()
    pm = ms.save(tmp, suffixes=("ca",), has_alpha=True)
    ms.tex = TEX + "relay_mesh_ca.paa"
    for k in ("fence", "grating"):
        r3.MATS[k].tex = ms.tex
    out[ms.tex] = dict(pm, alpha=True)
    var_png = {}
    for v in variants:
        a = steel_atlas(v, declare=False)
        var_png[v] = a.save(tmp, suffixes=("co",))
        out[TEX + "relay_steel_%s_co.paa" % v] = dict(p, co=var_png[v]["co"])
    if convert:
        # Tailles : acier 2048 x 1024 (couleur), normales et spéculaire 1024 x 512 ; équipements 2048 (couleur),
        # 1024 (normales, spéculaire), 512 (ombre ambiante).
        def small(src, size):
            dst = src.replace(".png", "_s.png")
            Image.open(src).resize(size, Image.LANCZOS).save(dst)
            return dst
        r3.to_paa(p["co"], os.path.join(data_dir, "relay_steel_co.paa"))
        r3.to_paa(small(p["nohq"], (1024, 512)), os.path.join(data_dir, "relay_steel_nohq.paa"))
        r3.to_paa(small(p["smdi"], (1024, 512)), os.path.join(data_dir, "relay_steel_smdi.paa"))
        for v in variants:
            r3.to_paa(small(var_png[v]["co"], (1024, 512)), os.path.join(data_dir, "relay_steel_%s_co.paa" % v))
        r3.to_paa(pe["co"], os.path.join(data_dir, "relay_equip_co.paa"))
        r3.to_paa(small(pe["nohq"], (1024, 1024)), os.path.join(data_dir, "relay_equip_nohq.paa"))
        r3.to_paa(small(pe["smdi"], (1024, 1024)), os.path.join(data_dir, "relay_equip_smdi.paa"))
        r3.to_paa(small(pe["as"], (512, 512)), os.path.join(data_dir, "relay_equip_as.paa"))
        r3.to_paa(pm["co"], os.path.join(data_dir, "relay_mesh_ca.paa"))
    if data_dir:
        write_rvmats(data_dir)
    return out


def declare_flat():
    """Matières unies : feux d'obstacle, voyants d'état, verre."""
    r3.flat_mat("light_red", (0.9, 0.05, 0.03), TEX + "relay_glow.rvmat")
    r3.flat_mat("led", (0.1, 0.9, 0.2), TEX + "relay_glow.rvmat")
    r3.flat_mat("glass", (0.02, 0.025, 0.03), TEX + "relay_glass.rvmat")


def write_rvmats(data_dir):
    r3.write_rvmat_super(os.path.join(data_dir, "relay_steel.rvmat"),
                         "Relais (tools/relay_tex.py) : planche de profilés, acier galvanisé ou peint.",
                         TEX + "relay_steel_nohq.paa", TEX + "relay_steel_smdi.paa", spec=0.45, power=45, fresnel="1.8,0.6")
    r3.write_rvmat_super(os.path.join(data_dir, "relay_equip.rvmat"),
                         "Relais : atlas des équipements (radômes, armoires, béton, kits).",
                         TEX + "relay_equip_nohq.paa", TEX + "relay_equip_smdi.paa", spec=0.3, power=35, fresnel="1.5,0.4",
                         as_=TEX + "relay_equip_as.paa")
    r3.write_rvmat_basic(os.path.join(data_dir, "relay_mesh.rvmat"), "Relais : grillage et caillebotis (alpha test).",
                         spec=0.2, power=30, extra="renderFlags[]={\"AlphaTest64\"};\n")
    r3.write_rvmat_basic(os.path.join(data_dir, "relay_glow.rvmat"),
                         "Relais : feux d'obstacle et voyants (lumineux, la couleur vient de la texture).", emissive=(6, 6, 6))
    r3.write_rvmat_super(os.path.join(data_dir, "relay_glass.rvmat"), "Relais : verre sombre brillant.",
                         "#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)", "#(argb,8,8,3)color(1,0.8,0.9,1,SMDI)", spec=0.9, power=120, fresnel="1.5,0.9")
