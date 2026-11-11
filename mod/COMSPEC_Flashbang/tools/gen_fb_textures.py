"""
Textures du mod COMSPEC Flashbang (Pillow + numpy), sources PNG dans tools/render/ :

  fb_body_<V>_co.png    corps de la grenade, V = 1..9 et drill (1024 x 1024)
  fb_body_nohq.png      relief commun du corps (évents, bande, cordon de soudure)
  fb_body_smdi.png      reflets communs du corps (peinture satinée, évents mats)
  fb_metal_co.png       atlas de la tête : fusée, levier, anneau et goupille, boîtier du percuteur (512 x 512)
  fb_metal_nohq.png / fb_metal_smdi.png
  fb_afterimage_ca.png  tache d'image rémanente (blanc sur transparent, teintée en jeu)
  fb_flare_ca.png       halo d'éblouissement doux (optique)
  fb_module_ca.png      icône de module Zeus / Eden (silhouette de grenade + éclair, blanc sur transparent)

Disposition de fb_body_<V>_co (UV posées par model_flashbang.py) :
  lignes 0..639      flanc du cylindre déroulé : colonne = angle autour de l'axe (0 = côté du levier, +Z),
                     ligne 0 = haut du corps (côté fusée), ligne 639 = bas (côté évents)
  disque (192, 832)  fond du corps (rayon 176 px), évents de fond
  carré (704, 832)   couronne supérieure du corps (teinte unie autour de la fusée)

Référence réelle : fiches Rheinmetall « 6 Bang » (Ø40 x 112 mm, acier, évents en partie basse, bande marron)
et Primetake PT4523C (115 mm, Ø40 mm). Le marquage est fictif (lot COMSPEC), le chiffre N et N pastilles
permettent de distinguer les variantes en main et au sol (chiffre répété sur les deux flancs).

    python gen_fb_textures.py         puis  python model_flashbang.py (dans Blender) et la conversion PAA (README)
"""
import math
import os

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
RENDER = os.path.join(HERE, "render")
VARIANTS = [str(i) for i in range(1, 10)] + ["drill"]
N = 1024
SIDE_H = 640                 # hauteur du flanc déroulé (px)
H_BODY = 0.086               # hauteur réelle du corps (m), pour placer les rangées d'évents
rng = np.random.default_rng(84)

OLIVE = (0.30, 0.31, 0.22)
BLUE = (0.10, 0.30, 0.66)
BROWN = (0.45, 0.27, 0.13)
STEEL_DARK = (0.12, 0.12, 0.12)
WHITE = (0.93, 0.93, 0.90)

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


def base_body(drill):
    """Couleur de fond, hauteur (relief) et masque des évents, communs aux marquages."""
    co = np.zeros((N, N, 3))
    hgt = np.full((N, N), 0.5)
    vent = np.zeros((N, N))
    paint = BLUE if drill else OLIVE
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
    co[b0:b1] = np.array(WHITE if drill else BROWN) * (0.92 + 0.1 * grain[b0:b1])[..., None]
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


def markings(img, variant):
    """Marquages blancs sérigraphiés sur les deux flancs (u = 0,25 et u = 0,75) : chiffre, pastilles, textes."""
    d = ImageDraw.Draw(img)
    drill = variant == "drill"
    white = tuple(int(c * 255) for c in WHITE)
    for cu in (0.25, 0.75):
        cx = int(cu * N)
        if drill:
            # « DRILL » en gros, écrit autour du corps, répété au-dessus du culot.
            stencil(d, (cx, 210), "DRILL", 96, white)
            stencil(d, (cx, 300), "INERTE / TRAINING", 40, white, bold=False)
            stencil(d, (cx, 345), "NO FLASH - NO BANG", 30, white, bold=False)
            stencil(d, (cx, 384), "COMSPEC FB-D  LOT 26-11-D", 24, white, bold=False)
        else:
            n = int(variant)
            stencil(d, (cx, 230), str(n), 230, white)
            # N pastilles carrées sous le chiffre (lisibles même quand le chiffre est caché).
            w, gap = 22, 12
            x0 = cx - (n * w + (n - 1) * gap) / 2
            for i in range(n):
                d.rectangle([x0 + i * (w + gap), 352, x0 + i * (w + gap) + w, 374], fill=white)
            stencil(d, (cx - 75, 150), f"{n}-BANG", 34, white, anchor="rm")
            stencil(d, (cx + 75, 150), "DELAY 1.5 s", 30, white, anchor="lm", bold=False)
            stencil(d, (cx, 400), f"COMSPEC FB-{n}  LOT 26-11-0{n}", 22, white, bold=False)
    # Bande : rappel du chiffre tout autour (lisible sous n'importe quel angle).
    tag = "D" if drill else variant
    col = (40, 40, 40) if drill else white
    for k in range(8):
        stencil(d, (int((k + 0.5) / 8 * N), int(0.11 * SIDE_H)), tag, 40, col)
    # Fond : marquage gravé.
    stencil(d, (BOTTOM_C[0], BOTTOM_C[1] + 118), "FB-D" if drill else f"FB-{variant}", 26, (150, 150, 150))


def body(variant, hgt_out):
    drill = variant == "drill"
    co, hgt, vent = base_body(drill)
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


def main():
    os.makedirs(RENDER, exist_ok=True)
    keep = []
    for v in VARIANTS:
        body(v, keep)
    body_maps(*keep[0])
    metal()
    afterimage()
    flare()
    module_icon()
    print("textures écrites dans", RENDER)


if __name__ == "__main__":
    main()
