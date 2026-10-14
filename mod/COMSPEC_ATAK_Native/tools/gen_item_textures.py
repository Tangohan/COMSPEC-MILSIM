"""
Atlas des objets d'inventaire 3D (model_items.py) : une seule texture 1024 x 1024 pour les onze objets.

  moitié haute   32 pastilles de matière (128 px) : polymère olive / noir / tan, caoutchouc, cordura, laiton de SMA,
                 acier, alu anodisé, verre d'objectif, filtre vert, voyants, radôme, gaine, sangle...
                 Chaque face d'une matière unie prend ses UV dans sa pastille (grain, salissure, usure).
  moitié basse   étiquettes et sérigraphies (batterie, batterie externe, caméra, patch, DL-7, kit de réparation,
                 kit véhicule, valise du socle, filtre JVN).

Pour le shader Super (rvmat items.rvmat) : items_co (couleur), items_nohq (grain, trame du cordura, gaufrage du
caoutchouc) et items_smdi (G = intensité, B = brillance des reflets : laiton et verre brillants, cordura mat).

    python3 gen_item_textures.py      # écrit render/items_{co,nohq,smdi}.png
    puis  hemtt utils paa convert render/items_co.png ../Sources/addons/items/data/items_co.paa  (idem _nohq, _smdi)
"""
import os

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
RENDER = os.path.join(HERE, "render")
N = 1024
TILE = 128

# Pastilles : nom -> (couleur sRGB, force du grain, motif de relief, reflet (intensité, brillance), métal (aperçu))
SWATCHES = {
    "olive": ((74, 80, 54), 0.10, "grain", (0.25, 0.35), 0.0),
    "black": ((30, 31, 33), 0.10, "grain", (0.30, 0.40), 0.0),
    "tan": ((152, 132, 98), 0.10, "grain", (0.22, 0.30), 0.0),
    "rubber": ((24, 24, 25), 0.06, "knurl", (0.10, 0.15), 0.0),
    "coyote": ((118, 98, 70), 0.16, "weave", (0.04, 0.08), 0.0),
    "ranger": ((70, 74, 56), 0.16, "weave", (0.04, 0.08), 0.0),
    "webbing": ((26, 27, 26), 0.14, "weave", (0.04, 0.08), 0.0),
    "brass": ((196, 162, 92), 0.05, "smooth", (0.90, 0.80), 1.0),
    "steel": ((172, 174, 178), 0.06, "brushed", (0.80, 0.65), 1.0),
    "anodized": ((52, 54, 58), 0.06, "brushed", (0.55, 0.55), 0.6),
    "lens": ((8, 10, 16), 0.02, "smooth", (1.00, 0.95), 0.0),
    "filter": ((34, 70, 50), 0.03, "smooth", (0.90, 0.90), 0.0),
    "led_g": ((80, 235, 110), 0.02, "smooth", (0.80, 0.90), 0.0),
    "led_r": ((235, 50, 40), 0.02, "smooth", (0.80, 0.90), 0.0),
    "led_b": ((60, 140, 255), 0.02, "smooth", (0.80, 0.90), 0.0),
    "radome": ((206, 206, 196), 0.08, "grain", (0.20, 0.30), 0.0),
    "grey": ((104, 107, 108), 0.08, "grain", (0.30, 0.40), 0.0),
    "cable": ((20, 20, 21), 0.05, "smooth", (0.25, 0.45), 0.0),
    "orange": ((206, 112, 38), 0.06, "grain", (0.30, 0.40), 0.0),
    "red": ((168, 34, 30), 0.06, "grain", (0.30, 0.40), 0.0),
    "yellow": ((206, 172, 40), 0.06, "grain", (0.30, 0.40), 0.0),
    "foam": ((36, 36, 38), 0.20, "foam", (0.02, 0.05), 0.0),
    "pcb": ((24, 70, 40), 0.10, "grain", (0.40, 0.50), 0.0),
    "glass": ((24, 28, 32), 0.02, "smooth", (1.00, 0.95), 0.0),
    "screen": ((10, 12, 14), 0.02, "smooth", (1.00, 0.95), 0.0),
    "white": ((222, 222, 216), 0.05, "grain", (0.25, 0.35), 0.0),
    "olive_dark": ((52, 56, 40), 0.10, "grain", (0.22, 0.30), 0.0),
    "velcro": ((60, 64, 46), 0.25, "foam", (0.02, 0.05), 0.0),
    "khaki": ((108, 100, 78), 0.10, "grain", (0.22, 0.32), 0.0),
    "ir": ((58, 50, 52), 0.04, "smooth", (0.85, 0.85), 0.0),
}
NAMES = list(SWATCHES)

# Étiquettes (moitié basse, pixels de l'atlas) : nom -> (x0, y0, x1, y1)
LABELS = {
    "battery": (8, 520, 328, 720),
    "powerbank": (336, 520, 736, 680),
    "camera": (744, 520, 1016, 600),
    "patch": (744, 608, 944, 808),
    "dl7": (8, 728, 328, 848),
    "repair": (336, 688, 576, 848),
    "vehicle": (584, 816, 904, 936),
    "dock": (8, 856, 328, 1016),
    "nvg": (336, 856, 576, 936),
    "warn": (336, 944, 576, 1016),
    "serial": (584, 944, 904, 1016),
    "dock_side": (944, 608, 1016, 808),
    "camera_back": (912, 820, 1016, 924),
    "eagle": (772, 392, 1012, 504),
}

rng = np.random.default_rng(424242)


def swatch_uv(name):
    """Centre et demi-taille (UV, origine en bas à gauche comme Blender) de la pastille name."""
    i = NAMES.index(name)
    cx = (i % 8) * TILE + TILE / 2
    cy = (i // 8) * TILE + TILE / 2
    return (cx / N, 1 - cy / N), (TILE * 0.42) / N


def label_uv(name, u, v):
    """(u, v) 0..1 dans l'étiquette (v vers le haut) -> UV de l'atlas."""
    x0, y0, x1, y1 = LABELS[name]
    return ((x0 + u * (x1 - x0)) / N, 1 - (y1 - v * (y1 - y0)) / N)


def font(size, bold=True):
    for p in ("/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf" if bold else
              "/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed.ttf",
              "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"):
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def noise(size, beta=2.0):
    fx = np.fft.fftfreq(size)[None, :] * size
    fy = np.fft.fftfreq(size)[:, None] * size
    f = np.sqrt(fx * fx + fy * fy)
    f[0, 0] = 1.0
    spec = (rng.normal(size=(size, size)) + 1j * rng.normal(size=(size, size))) / f ** (beta / 2)
    spec[0, 0] = 0
    out = np.real(np.fft.ifft2(spec))
    out -= out.min()
    return out / out.max()


def pattern(kind, size):
    """Relief (0..1) d'une pastille."""
    ys, xs = np.mgrid[0:size, 0:size]
    if kind == "weave":
        w = 0.5 + 0.25 * np.sin(xs * 2 * np.pi / 4) * np.sign(np.sin(ys * 2 * np.pi / 8)) \
            + 0.25 * np.sin(ys * 2 * np.pi / 4) * np.sign(np.sin(xs * 2 * np.pi / 8 + 1))
        return 0.7 * w + 0.3 * noise(size, 1.2)
    if kind == "knurl":
        return 0.5 + 0.5 * np.sin((xs + ys) * 2 * np.pi / 10) * np.sin((xs - ys) * 2 * np.pi / 10)
    if kind == "brushed":
        n = rng.normal(size=(size, 1)).repeat(size, 1)
        return 0.5 + 0.15 * n + 0.2 * noise(size, 2.5)
    if kind == "foam":
        return noise(size, 0.6)
    if kind == "grain":
        return 0.6 * noise(size, 1.0) + 0.4 * noise(size, 2.8)
    return np.full((size, size), 0.5)


def to_normal(h, strength):
    gy, gx = np.gradient(h)
    nx, ny = -gx * strength, gy * strength
    nz = np.ones_like(h)
    ln = np.sqrt(nx * nx + ny * ny + nz * nz)
    return np.stack([nx / ln, ny / ln, nz / ln], -1) * 0.5 + 0.5


def build_swatches(co, nohq, smdi):
    for i, name in enumerate(NAMES):
        col, grain, kind, (spec, gloss), _m = SWATCHES[name]
        x0, y0 = (i % 8) * TILE, (i // 8) * TILE
        h = pattern(kind, TILE)
        dirt = noise(TILE, 2.6)
        base = np.array(col, float)[None, None, :] / 255.0
        shade = 1.0 + grain * (h - 0.5) * 1.6 - 0.10 * np.clip(dirt - 0.55, 0, 1) * 2
        c = np.clip(base * shade[..., None], 0, 1)
        # Usure : arêtes un peu éclaircies sur les polymères (le bruit simule les éraflures).
        if kind == "grain" and name not in ("lens", "glass"):
            wear = np.clip((noise(TILE, 1.6) - 0.82) * 6, 0, 1)
            c = c * (1 - 0.25 * wear[..., None]) + 0.25 * wear[..., None] * np.clip(base * 1.6, 0, 1)
        co[y0:y0 + TILE, x0:x0 + TILE] = c
        nohq[y0:y0 + TILE, x0:x0 + TILE] = to_normal(h, {"weave": 3.0, "knurl": 2.0, "foam": 4.0}.get(kind, 1.2))
        g = np.clip(spec * (1 - 0.3 * dirt), 0, 1)
        b = np.clip(gloss * (1 - 0.2 * dirt), 0, 1)
        smdi[y0:y0 + TILE, x0:x0 + TILE] = np.stack([np.ones_like(g), g, b], -1)


def draw_labels(img):
    d = ImageDraw.Draw(img)

    def box(name, fill):
        x0, y0, x1, y1 = LABELS[name]
        d.rectangle((x0, y0, x1 - 1, y1 - 1), fill=fill)
        return x0, y0, x1, y1

    # Batterie : étiquette noire mate, bande verte, caractéristiques et pictogrammes.
    x0, y0, x1, y1 = box("battery", (34, 36, 34))
    d.rectangle((x0, y0 + 46, x1, y0 + 92), fill=(70, 150, 80))
    d.text((x0 + 14, y0 + 8), "COMSPEC", font=font(30), fill=(225, 225, 215))
    d.text((x1 - 120, y0 + 14), "BP-385", font=font(22), fill=(160, 165, 155))
    d.text((x0 + 14, y0 + 52), "ATAK · Li-ion · 3,85 V", font=font(25), fill=(18, 22, 18))
    d.text((x0 + 14, y0 + 102), "3000 mAh  11.55 Wh", font=font(22), fill=(210, 210, 200))
    d.text((x0 + 14, y0 + 130), "Ne pas percer · ne pas exposer au feu", font=font(14, False), fill=(150, 150, 145))
    d.text((x0 + 14, y0 + 148), "Do not puncture · keep away from fire", font=font(14, False), fill=(150, 150, 145))
    for k, t in enumerate(("CE", "UN38", "Li")):
        d.rectangle((x0 + 14 + k * 70, y0 + 170, x0 + 74 + k * 70, y0 + 194), outline=(150, 150, 145), width=2)
        d.text((x0 + 20 + k * 70, y0 + 173), t, font=font(15), fill=(170, 170, 165))
    # Batterie externe : face sérigraphiée.
    x0, y0, x1, y1 = box("powerbank", (40, 42, 38))
    d.text((x0 + 18, y0 + 14), "COMSPEC  PB-10", font=font(34), fill=(220, 220, 210))
    d.text((x0 + 18, y0 + 60), "10 000 mAh · 37 Wh", font=font(26), fill=(190, 190, 180))
    d.text((x0 + 18, y0 + 96), "USB-C PD 18W   IN/OUT", font=font(18), fill=(150, 155, 145))
    d.text((x0 + 18, y0 + 124), "MIL-STD-810H · IP67", font=font(18), fill=(150, 155, 145))
    for k in range(4):
        d.ellipse((x1 - 150 + k * 32, y0 + 120, x1 - 134 + k * 32, y0 + 136), outline=(150, 150, 140), width=2)
    # Caméra.
    x0, y0, x1, y1 = box("camera", (34, 35, 37))
    d.text((x0 + 14, y0 + 10), "COMSPEC", font=font(24), fill=(210, 210, 205))
    d.text((x0 + 14, y0 + 42), "IPC-2 · 1080p · IR 30 m", font=font(17), fill=(150, 150, 145))
    # Dos de la caméra : plaque noire, normes, QR.
    x0, y0, x1, y1 = box("camera_back", (22, 23, 24))
    d.text((x0 + 8, y0 + 6), "MIL-STD-810G", font=font(13), fill=(200, 200, 195))
    d.text((x0 + 8, y0 + 22), "IP67", font=font(13), fill=(200, 200, 195))
    d.text((x0 + 8, y0 + 38), "COMSPEC", font=font(15), fill=(220, 220, 215))
    q = np.random.default_rng(3).random((9, 9)) > 0.5
    for i in range(9):
        for j in range(9):
            if q[i, j] or (i < 3 and j < 3) or (i < 3 and j > 5) or (i > 5 and j < 3):
                d.rectangle((x0 + 30 + j * 5, y0 + 58 + i * 5, x0 + 34 + j * 5, y0 + 62 + i * 5), fill=(205, 205, 200))
    # Écusson COMSPEC (aigle stylisé) de la pochette de réparation.
    x0, y0, x1, y1 = box("eagle", (16, 17, 17))
    d.rectangle((x0 + 4, y0 + 4, x1 - 5, y1 - 5), outline=(48, 50, 48), width=3)
    cx, cy = (x0 + x1) // 2, y0 + 40
    wing = [(0, -4), (-14, -14), (-38, -22), (-70, -24), (-52, -14), (-64, -10), (-44, -4), (-54, 2), (-30, 2), (-14, 8), (0, 18)]
    for sgn in (-1, 1):
        d.polygon([(cx + sgn * x, cy + y) for x, y in wing], fill=(210, 212, 214))
    d.ellipse((cx - 7, cy - 18, cx + 7, cy - 4), fill=(210, 212, 214))
    d.polygon([(cx - 6, cy + 14), (cx + 6, cy + 14), (cx, cy + 26)], fill=(210, 212, 214))
    tw = d.textlength("COMSPEC", font=font(26))
    d.text((cx - tw / 2, y0 + 72), "COMSPEC", font=font(26), fill=(225, 226, 228))
    # Patch 9 dBi (dessus du radôme).
    x0, y0, x1, y1 = box("patch", (100, 93, 72))
    d.rectangle((x0 + 10, y0 + 10, x1 - 11, y1 - 11), outline=(84, 78, 60), width=3)
    d.text((x0 + 30, y0 + 70), "PATCH", font=font(36), fill=(58, 54, 42))
    d.text((x0 + 44, y0 + 112), "9 dBi", font=font(30), fill=(40, 38, 30))
    d.text((x0 + 32, y0 + 156), "2.4 / 4.9 GHz", font=font(16), fill=(66, 62, 50))
    d.ellipse((x0 + 92, y0 + 34, x0 + 108, y0 + 50), fill=(58, 54, 42))
    # DL-7 (boîtier de l'adaptateur).
    x0, y0, x1, y1 = box("dl7", (38, 40, 36))
    d.text((x0 + 14, y0 + 10), "COMSPEC  DL-7", font=font(28), fill=(220, 220, 210))
    d.text((x0 + 14, y0 + 48), "DATA LINK · 7-PIN / USB-C", font=font(18), fill=(160, 160, 150))
    d.text((x0 + 14, y0 + 78), "▲ PWR    ● DATA    ● LINK", font=font(16), fill=(140, 140, 130))
    # Kit de réparation : écusson de la pochette (croix + texte).
    x0, y0, x1, y1 = box("repair", (64, 68, 50))
    d.rectangle((x0 + 6, y0 + 6, x1 - 7, y1 - 7), outline=(40, 42, 32), width=4)
    d.rectangle((x0 + 24, y0 + 54, x0 + 84, y0 + 74), fill=(30, 32, 26))
    d.rectangle((x0 + 44, y0 + 34, x0 + 64, y0 + 94), fill=(30, 32, 26))
    d.text((x0 + 100, y0 + 30), "ATAK", font=font(34), fill=(30, 32, 26))
    d.text((x0 + 100, y0 + 72), "REPAIR KIT", font=font(20), fill=(30, 32, 26))
    d.text((x0 + 24, y0 + 116), "écran · tournevis · joints", font=font(14, False), fill=(34, 36, 28))
    # Kit véhicule.
    x0, y0, x1, y1 = box("vehicle", (24, 25, 26))
    d.text((x0 + 14, y0 + 10), "COMSPEC  VMK-1", font=font(28), fill=(220, 220, 210))
    d.text((x0 + 14, y0 + 48), "VEHICLE MOUNT · 12-32 V DC", font=font(18), fill=(160, 160, 150))
    d.text((x0 + 14, y0 + 78), "ATAK TERMINAL CRADLE", font=font(16), fill=(140, 140, 130))
    # Valise du socle CS-10.
    x0, y0, x1, y1 = box("dock", (40, 42, 34))
    d.rectangle((x0 + 8, y0 + 8, x1 - 9, y1 - 9), outline=(200, 200, 190), width=3)
    d.text((x0 + 22, y0 + 18), "COMSPEC", font=font(26), fill=(220, 220, 210))
    d.text((x0 + 22, y0 + 52), "CS-10", font=font(46), fill=(225, 225, 215))
    d.text((x0 + 22, y0 + 108), "CHARGING DOCK · 10 x ATAK", font=font(17), fill=(170, 170, 160))
    d.text((x0 + 22, y0 + 130), "110-240 V AC · 24 V DC", font=font(15), fill=(150, 150, 140))
    # Flanc de la valise (bande jaune « fragile »).
    x0, y0, x1, y1 = box("dock_side", (200, 170, 40))
    for k in range(0, 220, 28):
        d.polygon([(x0, y0 + k), (x1, y0 + k + 20), (x1, y0 + k + 34), (x0, y0 + k + 14)], fill=(30, 30, 28))
    # Languette du filtre JVN.
    x0, y0, x1, y1 = box("nvg", (206, 112, 38))
    d.text((x0 + 14, y0 + 8), "NVG-SAFE", font=font(32), fill=(30, 22, 12))
    d.text((x0 + 14, y0 + 50), "PULL ▸ RETIRER", font=font(16), fill=(50, 30, 12))
    # Pictogramme d'avertissement générique (antennes, kit véhicule).
    x0, y0, x1, y1 = box("warn", (200, 170, 40))
    d.polygon([(x0 + 14, y1 - 12), (x0 + 44, y0 + 10), (x0 + 74, y1 - 12)], outline=(25, 25, 22), width=4)
    d.text((x0 + 39, y0 + 26), "!", font=font(30), fill=(25, 25, 22))
    d.text((x0 + 86, y0 + 12), "RF · 50 Ω", font=font(20), fill=(25, 25, 22))
    d.text((x0 + 86, y0 + 40), "SMA-M", font=font(20), fill=(25, 25, 22))
    # Plaque de numéro de série (code-barres).
    x0, y0, x1, y1 = box("serial", (210, 210, 202))
    d.text((x0 + 10, y0 + 6), "S/N 7741-0326-A", font=font(16), fill=(30, 30, 30))
    x = x0 + 10
    r = np.random.default_rng(7)
    while x < x1 - 14:
        w = int(r.integers(1, 4))
        d.rectangle((x, y0 + 30, x + w - 1, y1 - 10), fill=(25, 25, 25))
        x += w + int(r.integers(1, 4))


def main():
    os.makedirs(RENDER, exist_ok=True)
    co = np.zeros((N, N, 3))
    co[:] = (0.15, 0.15, 0.15)
    nohq = np.zeros((N, N, 3))
    nohq[:] = (0.5, 0.5, 1.0)
    smdi = np.zeros((N, N, 3))
    smdi[:] = (1.0, 0.25, 0.35)
    build_swatches(co, nohq, smdi)
    img = Image.fromarray((co * 255).astype(np.uint8))
    draw_labels(img)
    # Étiquettes : un peu de salissure et de grain, comme les pastilles.
    lab = np.asarray(img).astype(float) / 255
    dirt = np.asarray(Image.fromarray((noise(N, 2.4) * 255).astype(np.uint8))).astype(float) / 255
    lower = np.zeros((N, N, 1))
    lower[512:] = 1
    lab = lab * (1 - lower * 0.12 * np.clip(dirt - 0.5, 0, 1)[..., None] * 2)
    img = Image.fromarray((np.clip(lab, 0, 1) * 255).astype(np.uint8)).filter(ImageFilter.SMOOTH)
    img.save(os.path.join(RENDER, "items_co.png"))
    # Étiquettes : sérigraphie un peu en relief, reflets satinés.
    gray = np.asarray(img.convert("L")).astype(float) / 255
    nl = to_normal(gray, 1.5)
    nohq[512:] = nl[512:]
    smdi[512:] = np.stack([np.ones((512, N)), np.full((512, N), 0.22), np.full((512, N), 0.32)], -1)
    Image.fromarray((nohq * 255).astype(np.uint8)).save(os.path.join(RENDER, "items_nohq.png"))
    Image.fromarray((smdi * 255).astype(np.uint8)).save(os.path.join(RENDER, "items_smdi.png"))
    print("items_co/nohq/smdi écrits")


if __name__ == "__main__":
    main()
