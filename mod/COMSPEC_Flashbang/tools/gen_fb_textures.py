"""
Textures du mod COMSPEC Flashbang (Pillow + numpy), sources PNG dans tools/render/ :

  fb_body_<V>_co.png    corps de la grenade, V = 1..9 et drill, et leurs variantes à délai <V>_d3 / <V>_d8 (1024 x 1024)
  fb_body_nohq.png      relief commun du corps (évents, bande, cordon de soudure)
  fb_body_smdi.png      reflets communs du corps (peinture satinée, évents mats)
  fb_metal_co.png       atlas de la tête : fusée, levier, anneau et goupille, boîtier du percuteur (512 x 512)
  fb_metal_nohq.png / fb_metal_smdi.png
  fb_afterimage_ca.png  tache d'image rémanente (blanc sur transparent, teintée en jeu)
  fb_flare_ca.png       halo d'éblouissement doux (optique)
  fb_halo_<1..8>_ca.png halos des détonations (8 modèles : étoiles, anneau, éclats, traînées, couronne) + fb_halo_core_ca.png
                        (python gen_fb_textures.py --halos : seulement ceux-là)
  fb_module_ca.png      icône de module Zeus / Eden (silhouette de grenade + éclair, blanc sur transparent)

Disposition de fb_body_<V>_co (UV posées par model_flashbang.py) :
  lignes 0..639      flanc du cylindre déroulé : colonne = angle autour de l'axe (0 = côté du levier, +Z),
                     ligne 0 = haut du corps (côté fusée), ligne 639 = bas (côté évents)
  disque (192, 832)  fond du corps (rayon 176 px), évents de fond
  carré (704, 832)   couronne supérieure du corps (teinte unie autour de la fusée)

Référence réelle : fiches Rheinmetall « 6 Bang » (Ø40 x 112 mm, acier, évents en partie basse, bande marron)
et Primetake PT4523C (115 mm, Ø40 mm). Le marquage est fictif (lot COMSPEC), le chiffre N et N pastilles
permettent de distinguer les variantes en main et au sol (chiffre répété sur les deux flancs).
Variantes à délai : 2e bande colorée sous la bande du haut (jaune 3 s, rouge 8 s) avec « 3s » / « 8s » tout autour,
cartouche « 3s » / « 8s » de la même couleur à côté du chiffre (deux flancs) et en grand au dos.

BANG spéciaux (japan, women, surprise, german) : flanc multicolore par thème, fb_body_<thème>_co.png
(python gen_fb_textures.py --themes : seulement ceux-là).

    python gen_fb_textures.py         puis  python model_flashbang.py (dans Blender) et la conversion PAA (README)
"""
import math
import os
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
RENDER = os.path.join(HERE, "render")
ORIGINAL = [str(i) for i in range(1, 10)] + ["drill"]
BASES = ORIGINAL + ["50"]          # 50 : GANG BANG BF (corps noir, bande rouge)
DELAYS = (3, 8)
# Variantes : 1..9, drill et 50 (mèche standard), puis <base>_d3 / <base>_d8 (mèche fixe 3 s / 8 s).
VARIANTS = BASES + [f"{b}_d{d}" for b in BASES for d in DELAYS]
BLACK = (0.10, 0.10, 0.11)
RED = (0.72, 0.07, 0.05)
N = 1024
SIDE_H = 640                 # hauteur du flanc déroulé (px)
H_BODY = 0.086               # hauteur réelle du corps (m), pour placer les rangées d'évents
rng = np.random.default_rng(84)

OLIVE = (0.30, 0.31, 0.22)
BLUE = (0.10, 0.30, 0.66)
BROWN = (0.45, 0.27, 0.13)
STEEL_DARK = (0.12, 0.12, 0.12)
WHITE = (0.93, 0.93, 0.90)
# Bande de délai (2e bande sous la bande marron / blanche) : jaune = 3 s, rouge = 8 s.
DELAY_COLOR = {3: (0.95, 0.76, 0.08), 8: (0.82, 0.10, 0.08)}
DELAY_BAND = (0.158, 0.205)       # 2e bande (fraction de la hauteur du flanc, depuis le haut)
DELAY_BAND_Y = sum(DELAY_BAND) / 2

# Évents : (hauteur relative depuis le bas du corps, nombre de trous sur le tour, rayon en px)
VENT_ROWS = [(0.11, 8, 30), (0.25, 8, 30)]
VENT_BOTTOM = [(0.0, 0.0, 34)] + [(0.58 * math.cos(a), 0.58 * math.sin(a), 30) for a in np.linspace(0, 2 * math.pi, 5, endpoint=False)]
BOTTOM_C, BOTTOM_R = (192, 832), 176
TOP_C = (704, 832)


def font(size, bold=True):
    """Police DIN (Bahnschrift) sous Windows, sinon Arial gras, sinon DejaVu (Linux)."""
    for p in (r"C:\Windows\Fonts\bahnschrift.ttf", r"C:\Windows\Fonts\arialbd.ttf",
              "/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf",
              "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"):
        if os.path.exists(p):
            f = ImageFont.truetype(p, size)
            if p.endswith("bahnschrift.ttf"):
                try:
                    f.set_variation_by_name("Bold" if bold else "SemiBold Condensed")
                except (OSError, ValueError):
                    pass
            return f
    return ImageFont.load_default()


def noise(size, beta=2.0, h=None):
    """Bruit en 1/f^beta (0..1), carré ou rectangulaire."""
    h = h or size
    fx = np.fft.fftfreq(size)[None, :] * size
    fy = np.fft.fftfreq(h)[:, None] * h
    f = np.sqrt(fx * fx + fy * fy)
    f[0, 0] = 1.0
    spec = (rng.normal(size=(h, size)) + 1j * rng.normal(size=(h, size))) / f ** (beta / 2)
    spec[0, 0] = 0
    out = np.real(np.fft.ifft2(spec))
    out -= out.min()
    return out / out.max()


def to_normal(hm, strength):
    """Carte de hauteur (0..1) -> normal map DirectX attendue par Arma (_nohq, vert inversé)."""
    gy, gx = np.gradient(hm)
    nx, ny, nz = -gx * strength, gy * strength, np.ones_like(hm)
    n = np.sqrt(nx * nx + ny * ny + nz * nz)
    return np.stack([nx / n * 0.5 + 0.5, ny / n * 0.5 + 0.5, nz / n * 0.5 + 0.5], -1)


def vent_centers():
    """Centres des évents sur le flanc déroulé (colonne, ligne, rayon), décalés d'une rangée à l'autre."""
    out = []
    for k, (h, n, r) in enumerate(VENT_ROWS):
        y = (1 - h) * SIDE_H
        for i in range(n):
            x = (i + 0.5 * k + 0.25) / n * N
            for dx in (-N, 0, N):     # recouvrement de la couture
                out.append((x + dx, y, r))
    return out


def disc_mask(shape, cx, cy, r, soft=1.5):
    ys, xs = np.mgrid[0:shape[0], 0:shape[1]]
    d = np.sqrt((xs - cx) ** 2 + (ys - cy) ** 2)
    return np.clip((r - d) / soft + 0.5, 0, 1), d


def base_body(drill, fifty=False):
    """Couleur de fond, hauteur (relief) et masque des évents, communs aux marquages."""
    co = np.zeros((N, N, 3))
    hgt = np.full((N, N), 0.5)
    vent = np.zeros((N, N))
    paint = BLUE if drill else (BLACK if fifty else OLIVE)
    grain = noise(N, 1.4)
    co[:] = paint
    co *= (0.92 + 0.10 * grain)[..., None]
    # Bas du corps : culot acier sombre (évents), sur le vrai modèle multi-bang à évents de fond.
    bot = int((1 - 0.36) * SIDE_H)
    if not drill:
        co[bot:SIDE_H] = np.array(STEEL_DARK) * (0.85 + 0.3 * grain[bot:SIDE_H])[..., None]
    else:
        co[bot:SIDE_H] = np.array(BLUE) * 0.8 * (0.9 + 0.15 * grain[bot:SIDE_H])[..., None]
    hgt[bot - 3:bot + 3] = 0.42                                 # sertissage entre corps et culot
    # Bande de couleur près de la fusée (marron = faible explosif ; blanche pour l'exercice).
    b0, b1 = int(0.07 * SIDE_H), int(0.15 * SIDE_H)
    co[b0:b1] = np.array(WHITE if drill else (RED if fifty else BROWN)) * (0.92 + 0.1 * grain[b0:b1])[..., None]
    hgt[b0:b1] += 0.04
    # Bourrelet roulé en haut et en bas du flanc.
    for r0, r1 in ((0, 10), (SIDE_H - 10, SIDE_H)):
        co[r0:r1] *= 0.8
        hgt[r0:r1] = 0.62
    # Évents latéraux (bouchés et peints en bleu sur la grenade d'exercice).
    for cx, cy, r in vent_centers():
        if cx < -r or cx > N + r:
            continue
        m, d = disc_mask((N, N), cx, cy, r)
        rim = np.clip(1 - np.abs(d - r) / 4.0, 0, 1)
        if drill:
            hgt -= 0.10 * m
            co = co * (1 - 0.25 * rim[..., None])
        else:
            vent = np.maximum(vent, m)
            # Paroi intérieure éclairée en bas du trou (lumière venant du haut), fond noir au centre.
            ys = np.arange(N)[:, None]
            wall = np.clip((d / r) ** 3, 0, 1) * np.clip((ys - cy) / r + 0.3, 0, 1)
            inner = np.array((0.012, 0.012, 0.011)) + np.array((0.16, 0.16, 0.15)) * wall[..., None]
            co = co * (1 - m[..., None]) + inner * m[..., None]
            co = co * (1 - 0.35 * rim[..., None])
            hgt -= 0.45 * m
    # Fond du corps (disque) : acier sombre, évents de fond.
    m, d = disc_mask((N, N), *BOTTOM_C, BOTTOM_R)
    under = np.array(BLUE if drill else STEEL_DARK) * (0.85 + 0.25 * grain[..., None])
    co = co * (1 - m[..., None]) + under * m[..., None]
    for ox, oy, r in VENT_BOTTOM:
        vm, vd = disc_mask((N, N), BOTTOM_C[0] + ox * BOTTOM_R, BOTTOM_C[1] + oy * BOTTOM_R, r)
        if drill:
            hgt -= 0.1 * vm
        else:
            vent = np.maximum(vent, vm)
            co = co * (1 - vm[..., None]) + np.array((0.015, 0.015, 0.015)) * vm[..., None]
            hgt -= 0.45 * vm
    ring = np.clip(1 - np.abs(d - BOTTOM_R * 0.93) / 3, 0, 1)
    hgt += 0.08 * ring
    # Couronne supérieure : peinture du corps.
    tm, _ = disc_mask((N, N), *TOP_C, 170)
    co = co * (1 - tm[..., None]) + np.array(paint)[None, None] * (0.9 + 0.1 * grain[..., None]) * tm[..., None]
    hgt += 0.03 * noise(N, 2.6)
    return co, hgt, vent


def stencil(draw, xy, text, size, fill, anchor="mm", bold=True):
    draw.text(xy, text, font=font(size, bold), fill=fill, anchor=anchor)


def split(variant):
    """'5_d8' -> ('5', 8) ; '5' -> ('5', 0) ; 'drill_d3' -> ('drill', 3)."""
    if "_d" in variant:
        b, d = variant.split("_d")
        return b, int(d)
    return variant, 0


def delay_stamp(d, xy, delay, size):
    """Cartouche de délai : rectangle arrondi de la couleur du délai, « 3s » / « 8s » dedans, liseré blanc."""
    col = tuple(int(c * 255) for c in DELAY_COLOR[delay])
    f = font(size)
    l, t, r, b = d.textbbox(xy, f"{delay}s", font=f, anchor="mm")
    pad = size * 0.18
    d.rounded_rectangle([l - pad, t - pad, r + pad, b + pad], radius=int(size * 0.2), fill=col,
                        outline=tuple(int(c * 255) for c in WHITE), width=max(3, size // 22))
    d.text(xy, f"{delay}s", font=f, fill=(15, 15, 15) if delay == 3 else (245, 245, 240), anchor="mm")


def markings(img, variant):
    """Marquages blancs sérigraphiés sur les deux flancs (u = 0,25 et u = 0,75) : chiffre, pastilles, textes ;
    variantes à délai : cartouche « 3s » / « 8s » à côté du chiffre et en grand au dos (u = 0,5)."""
    d = ImageDraw.Draw(img)
    base, delay = split(variant)
    drill = base == "drill"
    white = tuple(int(c * 255) for c in WHITE)
    for cu in (0.25, 0.75):
        cx = int(cu * N)
        if drill:
            # « DRILL » en gros, écrit autour du corps, répété au-dessus du culot.
            stencil(d, (cx, 210), "DRILL", 96, white)
            if delay:
                delay_stamp(d, (cx, 302), delay, 70)
                stencil(d, (cx, 362), "INERTE / TRAINING", 34, white, bold=False)
                stencil(d, (cx, 396), f"COMSPEC FB-D{delay}  LOT 26-11-D", 24, white, bold=False)
            else:
                stencil(d, (cx, 300), "INERTE / TRAINING", 40, white, bold=False)
                stencil(d, (cx, 345), "NO FLASH - NO BANG", 30, white, bold=False)
                stencil(d, (cx, 384), "COMSPEC FB-D  LOT 26-11-D", 24, white, bold=False)
        else:
            n = int(base)
            big = n > 9
            # Délai : chiffre décalé à gauche, cartouche du délai à sa droite. « 50 » : deux chiffres, plus petits.
            if delay:
                stencil(d, (cx - (60 if big else 52), 250), str(n), 140 if big else 200, white)
                delay_stamp(d, (cx + (76 if big else 70), 252), delay, 76 if big else 84)
            else:
                stencil(d, (cx, 236 if big else 230), str(n), 190 if big else 230, white)
            if big:
                # GANG BANG BF : pas de pastilles (50), le nom en clair à la place.
                stencil(d, (cx, 362), "GANG BANG BF", 38, (235, 60, 45))
            else:
                # N pastilles carrées sous le chiffre (lisibles même quand le chiffre est caché).
                w, gap = 22, 12
                x0 = cx - (n * w + (n - 1) * gap) / 2
                for i in range(n):
                    d.rectangle([x0 + i * (w + gap), 352, x0 + i * (w + gap) + w, 374], fill=white)
            stencil(d, (cx - 75, 150), f"{n}-BANG", 34, white, anchor="rm")
            stencil(d, (cx + 75, 150), f"DELAY {delay} s" if delay else "DELAY 1.5 s", 30, white, anchor="lm", bold=bool(delay))
            suffix = f"-D{delay}" if delay else ""
            lot = f"LOT 26-11-{n}" if big else f"LOT 26-11-0{n}"
            stencil(d, (cx, 400), f"COMSPEC {'BF' if big else 'FB'}-{n}{suffix}  {lot}", 22, white, bold=False)
    if delay:
        # Dos (opposé au levier) : grand cartouche, visible quand la grenade est posée de dos.
        delay_stamp(d, (int(0.5 * N), 250), delay, 120)
    # Bande : rappel du chiffre tout autour (lisible sous n'importe quel angle).
    tag = "D" if drill else base
    col = (40, 40, 40) if drill else white
    for k in range(8):
        stencil(d, (int((k + 0.5) / 8 * N), int(0.11 * SIDE_H)), tag, 40, col)
    if delay:
        # 2e bande : « 3s » / « 8s » tout autour (noir sur jaune, blanc sur rouge), entre les rappels du chiffre.
        dcol = (15, 15, 15) if delay == 3 else (245, 245, 240)
        for k in range(8):
            stencil(d, (int((k + 0.5) / 8 * N), int(DELAY_BAND_Y * SIDE_H)), f"{delay}s", 24, dcol)
    # Fond : marquage gravé.
    tagb = ("FB-D" if drill else f"FB-{base}") + (f"-{delay}s" if delay else "")
    stencil(d, (BOTTOM_C[0], BOTTOM_C[1] + 118), tagb, 26, (150, 150, 150))


def body(variant, hgt_out):
    base, delay = split(variant)
    drill = base == "drill"
    co, hgt, vent = base_body(drill, base == "50")
    if delay:
        # 2e bande colorée du délai, juste sous la bande du haut.
        b0, b1 = int(DELAY_BAND[0] * SIDE_H), int(DELAY_BAND[1] * SIDE_H)
        grain = noise(N, 1.4)[b0:b1]
        co[b0:b1] = np.array(DELAY_COLOR[delay]) * (0.92 + 0.1 * grain)[..., None]
    img = Image.fromarray((np.clip(co, 0, 1) * 255).astype(np.uint8))
    markings(img, variant)
    # Usure légère : arêtes et marquages un peu éraflés, poussière vers le bas.
    arr = np.asarray(img).astype(float) / 255
    wear = np.clip(noise(N, 2.2) - 0.72, 0, 1) * 2.5
    arr = arr * (1 - 0.25 * wear[..., None]) + 0.25 * wear[..., None] * np.array((0.45, 0.44, 0.40))
    img = Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8)).filter(ImageFilter.SMOOTH)
    img.save(os.path.join(RENDER, f"fb_body_{variant}_co.png"))
    if hgt_out is not None and variant == "1":
        hgt_out.append((hgt, vent))


def body_maps(hgt, vent):
    nohq = to_normal(hgt, 6.0)
    Image.fromarray((nohq * 255).astype(np.uint8)).save(os.path.join(RENDER, "fb_body_nohq.png"))
    smdi = np.zeros((N, N, 3))
    smdi[..., 0] = 1.0
    smdi[..., 1] = 0.32 - 0.25 * vent             # peinture satinée, évents mats
    smdi[..., 2] = 0.42 - 0.3 * vent
    Image.fromarray((np.clip(smdi, 0, 1) * 255).astype(np.uint8)).save(os.path.join(RENDER, "fb_body_smdi.png"))


# ------------------------------------------------------------------------------------------------- atlas de la tête
M = 512
METAL_SWATCHES = {           # nom : (colonne, ligne) de la pastille 256 x 256, couleur, spéculaire, brillance, relief
    "fuze": ((0, 0), (0.22, 0.23, 0.17), 0.45, 0.45, "knurl"),
    "lever": ((1, 0), (0.33, 0.33, 0.24), 0.35, 0.40, "brushed"),
    "steel": ((0, 1), (0.62, 0.62, 0.60), 0.85, 0.75, "brushed"),
    "black": ((1, 1), (0.06, 0.06, 0.06), 0.30, 0.35, "grain"),
}


def metal_uv(name):
    """Centre (u, v) et demi-largeur de la pastille dans l'atlas (v Blender : 0 en bas de l'image)."""
    (c, r), *_x = METAL_SWATCHES[name]
    return ((c + 0.5) / 2, 1 - (r + 0.5) / 2), 0.25 * 0.9


def metal():
    co = np.zeros((M, M, 3))
    hgt = np.full((M, M), 0.5)
    smdi = np.zeros((M, M, 3))
    smdi[..., 0] = 1
    ys, xs = np.mgrid[0:256, 0:256]
    for _name, ((c, r), col, spec, gloss, kind) in METAL_SWATCHES.items():
        sl = (slice(r * 256, r * 256 + 256), slice(c * 256, c * 256 + 256))
        g = noise(256, 1.6)
        if kind == "knurl":
            h = 0.5 + 0.5 * np.sin((xs + ys) * 2 * np.pi / 9) * np.sin((xs - ys) * 2 * np.pi / 9)
        elif kind == "brushed":
            h = 0.5 + 0.12 * rng.normal(size=(256, 1)).repeat(256, 1) + 0.15 * noise(256, 2.5)
        else:
            h = 0.6 * noise(256, 1.0) + 0.4 * noise(256, 2.8)
        co[sl] = np.array(col) * (0.85 + 0.25 * g)[..., None]
        hgt[sl] = h
        smdi[sl][..., 1] = spec * (0.85 + 0.2 * g)
        smdi[sl][..., 2] = gloss
    img = Image.fromarray((np.clip(co, 0, 1) * 255).astype(np.uint8))
    d = ImageDraw.Draw(img)
    # Levier : marquage embouti « M201 / COMSPEC » (lisible de près).
    stencil(d, (384, 100), "FZ-201", 26, (190, 190, 170))
    stencil(d, (384, 140), "COMSPEC", 22, (190, 190, 170), bold=False)
    img.save(os.path.join(RENDER, "fb_metal_co.png"))
    Image.fromarray((to_normal(hgt, 3.0) * 255).astype(np.uint8)).save(os.path.join(RENDER, "fb_metal_nohq.png"))
    Image.fromarray((np.clip(smdi, 0, 1) * 255).astype(np.uint8)).save(os.path.join(RENDER, "fb_metal_smdi.png"))


# ------------------------------------------------------------------------------------------------- textures d'interface
def afterimage():
    """Tache rémanente : noyau dense irrégulier, halo flou, bords effilochés (blanc, alpha = densité)."""
    S = 256
    ys, xs = np.mgrid[0:S, 0:S].astype(float)
    a = np.zeros((S, S))
    for _ in range(11):
        cx, cy = S / 2 + rng.normal(0, 26), S / 2 + rng.normal(0, 22)
        sx, sy = rng.uniform(34, 70), rng.uniform(30, 62)
        a += rng.uniform(0.5, 1.0) * np.exp(-(((xs - cx) / sx) ** 2 + ((ys - cy) / sy) ** 2))
    a = a / a.max()
    a = a * (0.8 + 0.3 * noise(S, 2.0))
    r = np.sqrt((xs - S / 2) ** 2 + (ys - S / 2) ** 2) / (S / 2)
    a *= np.clip((1.0 - r) / 0.35, 0, 1)            # bord du carré toujours transparent
    a = np.clip(a / a.max() * 1.15, 0, 1) ** 1.1
    rgba = np.zeros((S, S, 4))
    rgba[..., :3] = 1
    rgba[..., 3] = a
    Image.fromarray((rgba * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(2)).save(os.path.join(RENDER, "fb_afterimage_ca.png"))


def flare():
    """Halo d'éblouissement : cœur blanc, halo large, 6 rayons fins (diffraction)."""
    S = 256
    ys, xs = np.mgrid[0:S, 0:S].astype(float) - S / 2 + 0.5
    r = np.sqrt(xs * xs + ys * ys) / (S / 2)
    ang = np.arctan2(ys, xs)
    a = np.exp(-(r / 0.10) ** 2) + 0.55 * np.exp(-(r / 0.38) ** 2)
    rays = np.zeros_like(r)
    for k in range(6):
        t = k * np.pi / 3
        da = np.abs(np.angle(np.exp(1j * (ang - t))))
        rays += np.exp(-(da / 0.025) ** 2) * np.clip(1 - r, 0, 1) ** 1.5
    a += 0.6 * rays
    a *= np.clip((1 - r) / 0.15, 0, 1)
    rgba = np.zeros((S, S, 4))
    rgba[..., :3] = 1
    rgba[..., 3] = np.clip(a, 0, 1)
    Image.fromarray((rgba * 255).astype(np.uint8)).save(os.path.join(RENDER, "fb_flare_ca.png"))


def halos():
    """Halos des détonations (fn_halo) : 8 couronnes originales + 1 cœur, blanc sur transparent (alpha = lumière),
    teintées, tournées et étirées en jeu. 256 x 256, bord toujours transparent.

      1 étoile à 4 branches (+ 4 courtes en diagonale)    5 éclat asymétrique (lobes inégaux)
      2 étoile à 6 branches                               6 halo doux + rayons fins nombreux
      3 étoile à 8 branches inégales                      7 éclat à traînées anamorphiques (horizontales)
      4 anneau diffus                                     8 couronne déchirée (anneau troué, mèches)
    """
    S = 256
    hr = np.random.default_rng(2026)          # générateur propre : les autres textures restent identiques
    ys, xs = np.mgrid[0:S, 0:S].astype(float) - S / 2 + 0.5
    r = np.sqrt(xs * xs + ys * ys) / (S / 2)
    ang = np.arctan2(ys, xs)
    edge = np.clip((1 - r) / 0.14, 0, 1)

    def ray(t, width, length, power=1.6):
        da = np.abs(np.angle(np.exp(1j * (ang - t))))
        return np.exp(-(da * np.maximum(r, 0.02) / width) ** 2) * np.clip(1 - r / length, 0, 1) ** power * (da < 1.2)

    def ang_noise(n_harm, seed_amp=1.0):
        out = np.zeros_like(r)
        for k in range(1, n_harm + 1):
            out += seed_amp * hr.normal(0, 1.0 / k ** 0.7) * np.cos(k * ang + hr.uniform(0, 2 * np.pi))
        return out

    glow = lambda w: np.exp(-(r / w) ** 2)
    out = {}
    # 1 : étoile à 4 branches
    a = 0.55 * glow(0.30) + 0.25 * glow(0.55)
    for k in range(4):
        a += 0.9 * ray(k * np.pi / 2, 0.012, 1.0)
        a += 0.45 * ray(k * np.pi / 2 + np.pi / 4, 0.010, 0.55)
    out[1] = a
    # 2 : étoile à 6 branches
    a = 0.5 * glow(0.28) + 0.25 * glow(0.6)
    for k in range(6):
        a += 0.8 * ray(k * np.pi / 3, 0.011, 0.95)
    out[2] = a
    # 3 : étoile à 8 branches inégales
    a = 0.5 * glow(0.26) + 0.2 * glow(0.5)
    for k in range(8):
        a += hr.uniform(0.5, 0.95) * ray(k * np.pi / 4 + hr.normal(0, 0.03), hr.uniform(0.008, 0.016), hr.uniform(0.55, 1.0))
    out[3] = a
    # 4 : anneau diffus
    a = 0.6 * glow(0.24) + 0.3 * glow(0.5) + 0.5 * np.exp(-((r - 0.6) / 0.05) ** 2) + 0.28 * np.exp(-((r - 0.6) / 0.2) ** 2)
    out[4] = a
    # 5 : éclat asymétrique (lobes inégaux)
    lobe = np.clip(0.75 + 0.3 * ang_noise(5), 0.3, 1.5)
    a = 0.8 * np.exp(-(r / (0.5 * lobe)) ** 2) + 0.45 * glow(0.22) + 0.15 * glow(0.6)
    for _ in range(7):
        a += hr.uniform(0.3, 0.7) * ray(hr.uniform(0, 2 * np.pi), hr.uniform(0.008, 0.02), hr.uniform(0.6, 1.0))
    out[5] = a
    # 6 : halo doux + rayons fins nombreux
    a = 0.55 * glow(0.34) + 0.2 * glow(0.62)
    for _ in range(44):
        a += hr.uniform(0.12, 0.42) * ray(hr.uniform(0, 2 * np.pi), hr.uniform(0.004, 0.008), hr.uniform(0.45, 1.0), 1.2)
    out[6] = a
    # 7 : traînées anamorphiques horizontales
    a = 0.5 * glow(0.24) + 0.18 * glow(0.5)
    xr, yr = xs / (S / 2), ys / (S / 2)
    a += 0.95 * np.exp(-(yr / 0.022) ** 2) * np.clip(1 - np.abs(xr), 0, 1) ** 1.3
    a += 0.35 * np.exp(-((yr - 0.07) / 0.012) ** 2) * np.clip(1 - np.abs(xr) / 0.6, 0, 1) ** 1.5
    a += 0.3 * np.exp(-((yr + 0.05) / 0.010) ** 2) * np.clip(1 - np.abs(xr) / 0.75, 0, 1) ** 1.5
    a += 0.4 * ray(np.pi / 2, 0.01, 0.4) + 0.4 * ray(-np.pi / 2, 0.01, 0.4)
    out[7] = a
    # 8 : couronne déchirée
    tear = np.clip(0.5 + 0.28 * ang_noise(22), 0.05, 1.0)
    rad = 0.55 + 0.05 * ang_noise(6)
    a = 0.55 * glow(0.24) + 0.2 * glow(0.5) + 0.6 * tear * np.exp(-((r - rad) / 0.045) ** 2) + 0.3 * tear * np.exp(-((r - rad) / 0.2) ** 2)
    for _ in range(16):
        a += hr.uniform(0.15, 0.4) * ray(hr.uniform(0, 2 * np.pi), hr.uniform(0.004, 0.009), hr.uniform(0.7, 1.0), 0.9) * np.clip((r - 0.35) / 0.15, 0, 1)
    out[8] = a
    for k, a in out.items():
        a = np.clip(a * edge, 0, 1) ** 0.9
        rgba = np.zeros((S, S, 4))
        rgba[..., :3] = 1
        rgba[..., 3] = a
        Image.fromarray((rgba * 255).astype(np.uint8)).save(os.path.join(RENDER, f"fb_halo_{k}_ca.png"))
    # Cœur : disque blanc saturé à bord doux.
    core = np.clip(1.6 * np.exp(-(r / 0.42) ** 2) * edge, 0, 1)
    rgba = np.zeros((S, S, 4))
    rgba[..., :3] = 1
    rgba[..., 3] = core
    Image.fromarray((rgba * 255).astype(np.uint8)).save(os.path.join(RENDER, "fb_halo_core_ca.png"))


def module_icon():
    """Icône de module 128 x 128 : grenade à levier + éclair, blanc sur transparent (dessinée en 512 puis réduite)."""
    S = 512
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    w = (255, 255, 255, 255)
    # Corps, culot à évents, fusée, levier, anneau.
    d.rounded_rectangle([150, 200, 300, 470], 18, fill=w)
    for y in (400, 440):
        for x in (180, 225, 270):
            d.ellipse([x - 12, y - 12, x + 12, y + 12], fill=(0, 0, 0, 0))
    d.rectangle([150, 222, 300, 240], fill=(0, 0, 0, 0))
    d.rectangle([180, 140, 270, 200], fill=w)
    d.polygon([(270, 140), (330, 160), (330, 420), (312, 420), (312, 178), (270, 168)], fill=w)
    d.ellipse([110, 110, 190, 190], outline=w, width=14)
    # Éclair.
    d.polygon([(395, 60), (330, 230), (385, 230), (345, 380), (470, 190), (410, 190), (450, 60)], fill=w)
    img.resize((128, 128), Image.LANCZOS).save(os.path.join(RENDER, "fb_module_ca.png"))


# ------------------------------------------------------------------------------------------------- BANG spéciaux
# Grenades à thème (son propre, corps multicolore) : même géométrie, même culot à évents, flanc peint par thème.
# Formes simples (aplats, bandes, dégradé, confettis) : aucun drapeau détaillé, aucun emblème.
THEMES = {"japan": "JAPAN BANG", "women": "WOMEN BANG", "surprise": "SUPRISE BANG", "german": "GERMAN BANG"}
THEME_FLASHES = {"japan": 3, "women": 3, "surprise": 5, "german": 9}
PAINT_TOP, PAINT_BOT = 10, int((1 - 0.36) * SIDE_H) - 3      # zone peinte du flanc (sous le bourrelet, au-dessus du culot)
BAND = (int(0.07 * SIDE_H), int(0.15 * SIDE_H))              # bande de couleur près de la fusée


def outlined(d, xy, text, size, fill, edge, w=None):
    d.text(xy, text, font=font(size), fill=fill, anchor="mm", stroke_width=w or max(3, size // 14), stroke_fill=edge)


def theme_paint(theme):
    """Peinture du flanc (lignes PAINT_TOP..PAINT_BOT, RVB 0..1) et couleur de la couronne supérieure."""
    h = PAINT_BOT - PAINT_TOP
    a = np.zeros((h, N, 3))
    ys = (np.arange(h)[:, None] + PAINT_TOP).astype(float)
    xs = np.arange(N)[None, :].astype(float)
    if theme == "japan":
        a[:] = (0.94, 0.94, 0.92)
        top = (0.94, 0.94, 0.92)
    elif theme == "german":
        # Trois bandes horizontales noir / rouge / or sous la bande du haut.
        y0 = BAND[1]
        step = (PAINT_BOT - y0) / 3
        a[:] = (0.07, 0.07, 0.07)
        a[(ys[:, 0] >= y0 + step) & (ys[:, 0] < y0 + 2 * step)] = (0.80, 0.07, 0.06)
        a[ys[:, 0] >= y0 + 2 * step] = (0.96, 0.76, 0.10)
        top = (0.07, 0.07, 0.07)
    elif theme == "women":
        # Dégradé vertical rose -> violet -> turquoise.
        stops = np.array([(0.98, 0.36, 0.62), (0.58, 0.30, 0.82), (0.16, 0.78, 0.76)])
        f = np.clip((ys[:, 0] - PAINT_TOP) / (h - 1), 0, 1) * 2
        i = np.minimum(f.astype(int), 1)
        k = (f - i)[:, None]
        a[:] = (stops[i] * (1 - k) + stops[i + 1] * k)[:, None, :]
        top = tuple(stops[0])
    else:
        # Arc-en-ciel en bandes obliques qui se raccordent à la couture, confettis par-dessus.
        cols = np.array([(0.90, 0.12, 0.14), (0.98, 0.52, 0.10), (0.98, 0.84, 0.12), (0.20, 0.72, 0.28),
                         (0.14, 0.52, 0.92), (0.50, 0.28, 0.82)])
        idx = (np.floor((xs + ys * 0.8) / (N / 12)) % 6).astype(int)
        a[:] = cols[idx]
        top = (0.50, 0.28, 0.82)
    return a, top


def theme_art(img, theme):
    """Formes et marquages du thème, dessinés sur les deux flancs (u = 0,25 et u = 0,75) et sur la bande."""
    d = ImageDraw.Draw(img)
    name = THEMES[theme]
    n = THEME_FLASHES[theme]
    white, black = (245, 245, 240), (18, 18, 18)
    y_band = (BAND[0] + BAND[1]) // 2
    if theme == "japan":
        red = (200, 22, 30)
        d.rectangle([0, BAND[0], N, BAND[1]], fill=red)
        for y in (BAND[1] + 12, PAINT_BOT - 22):                       # liserés rouges
            d.rectangle([0, y, N, y + 7], fill=red)
        for cu in (0.25, 0.75):
            cx = int(cu * N)
            d.ellipse([cx - 68, 186 - 68, cx + 68, 186 + 68], fill=red)
            d.text((cx, 296), "JAPAN", font=font(62), fill=black, anchor="mm")
            d.text((cx, 352), "BANG", font=font(62), fill=black, anchor="mm")
        band_ink, tag = white, "JAPAN"
    elif theme == "german":
        d.rectangle([0, BAND[0], N, BAND[1]], fill=(240, 194, 26))
        step = (PAINT_BOT - BAND[1]) / 3
        for cu in (0.25, 0.75):
            cx = int(cu * N)
            d.text((cx, BAND[1] + step * 0.5), "GERMAN", font=font(62), fill=white, anchor="mm")
            d.text((cx, BAND[1] + step * 1.5), "BANG", font=font(76), fill=white, anchor="mm")
            d.text((cx, BAND[1] + step * 2.5), f"{n} FLASH", font=font(46), fill=black, anchor="mm")
        band_ink, tag = black, "GERMAN"
    elif theme == "women":
        d.rectangle([0, BAND[0], N, BAND[1]], fill=white)
        for cu in (0.25, 0.75):
            cx = int(cu * N)
            outlined(d, (cx, 206), "WOMEN", 76, white, (70, 24, 110))
            outlined(d, (cx, 292), "BANG", 84, white, (70, 24, 110))
            for k in range(n):                                          # n pastilles
                x = cx + (k - (n - 1) / 2) * 44
                d.ellipse([x - 13, 372 - 13, x + 13, 372 + 13], fill=white, outline=(70, 24, 110), width=3)
        band_ink, tag = (150, 40, 130), "WOMEN"
    else:
        r = np.random.default_rng(7)
        conf = [(245, 245, 240), (255, 220, 40), (255, 90, 170), (60, 220, 220), (20, 20, 20)]
        for _ in range(170):
            x, y = int(r.uniform(0, N)), int(r.uniform(BAND[1] + 6, PAINT_BOT - 6))
            s = int(r.uniform(5, 12))
            c = conf[int(r.integers(0, len(conf)))]
            round_one = r.random() < 0.5
            for dx in (-N, 0, N):
                if round_one:
                    d.ellipse([x + dx - s, y - s, x + dx + s, y + s], fill=c)
                else:
                    d.rectangle([x + dx - s, y - s // 2, x + dx + s, y + s // 2], fill=c)
        d.rectangle([0, BAND[0], N, BAND[1]], fill=black)
        for cu in (0.25, 0.75):
            cx = int(cu * N)
            d.rounded_rectangle([cx - 138, 108, cx + 138, 398], radius=26, fill=black)
            outlined(d, (cx, 196), "?", 170, (255, 220, 40), white, 5)
            d.text((cx, 312), "SUPRISE", font=font(52), fill=white, anchor="mm")
            d.text((cx, 362), "BANG", font=font(52), fill=white, anchor="mm")
        band_ink, tag = (255, 220, 40), "SUPRISE"
    for x in (0, N // 4, N // 2, 3 * N // 4, N):                        # rappel du nom sur la bande (flancs, dos, couture)
        d.text((x, y_band), tag, font=font(38), fill=band_ink, anchor="mm")
    d.text((BOTTOM_C[0], BOTTOM_C[1] + 118), "FB-" + tag, font=font(26), fill=(150, 150, 150), anchor="mm")


def theme_body(theme):
    """Corps d'un BANG spécial : culot, évents et relief communs (base_body), flanc et couronne repeints."""
    co, _hgt, _vent = base_body(False)
    paint, top = theme_paint(theme)
    grain = noise(N, 1.4)
    co[PAINT_TOP:PAINT_BOT] = paint * (0.94 + 0.08 * grain[PAINT_TOP:PAINT_BOT])[..., None]
    tm, _ = disc_mask((N, N), *TOP_C, 170)
    co = co * (1 - tm[..., None]) + np.array(top)[None, None] * (0.9 + 0.1 * grain[..., None]) * tm[..., None]
    img = Image.fromarray((np.clip(co, 0, 1) * 255).astype(np.uint8))
    theme_art(img, theme)
    arr = np.asarray(img).astype(float) / 255
    wear = np.clip(noise(N, 2.2) - 0.76, 0, 1) * 2.5                    # usure plus légère : couleurs franches
    arr = arr * (1 - 0.18 * wear[..., None]) + 0.18 * wear[..., None] * np.array((0.45, 0.44, 0.40))
    img = Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8)).filter(ImageFilter.SMOOTH)
    img.save(os.path.join(RENDER, f"fb_body_{theme}_co.png"))


def themes():
    """BANG spéciaux : générateur de bruit propre (les textures déjà livrées ne changent pas)."""
    global rng
    rng = np.random.default_rng(1200)
    for t in THEMES:
        theme_body(t)


def main():
    os.makedirs(RENDER, exist_ok=True)
    if "--halos" in sys.argv:
        halos()
        print("halos écrits dans", RENDER)
        return
    if "--themes" in sys.argv:
        themes()
        print("textures des BANG spéciaux écrites dans", RENDER)
        return
    keep = []
    # Ordre fixe (le bruit est tiré d'un générateur commun) : variantes standard et textures communes d'abord, à
    # l'identique des versions précédentes, puis les variantes à délai.
    for v in ORIGINAL:
        body(v, keep)
    body_maps(*keep[0])
    metal()
    afterimage()
    flare()
    module_icon()
    halos()
    # Puis les variantes à délai des modèles d'origine, et enfin le 50 (GANG BANG BF) : les textures déjà livrées
    # restent identiques.
    for v in [f"{b}_d{d}" for b in ORIGINAL for d in DELAYS] + ["50", "50_d3", "50_d8"]:
        body(v, None)
    themes()
    print("textures écrites dans", RENDER)


if __name__ == "__main__":
    main()
