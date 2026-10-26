#!/usr/bin/env python3
"""Montre Garmin ouverte avec la touche « Montre » du jeu (O) : textures de fn_watch.

  gw_frame.paa    la montre vue de face (Forerunner 965 coloris coyote, d'après la photo de référence de TTRD), rendue par
                  éclairage d'une carte de hauteurs (normales, diffus, spéculaire, occlusion ; lumière en haut à gauche) :
                  boîtier polymère coyote au bord arrondi et épaulements vers le bracelet, lunette noire satinée avec
                  inscriptions (LIGHT, UP, DOWN, START | STOP, BACK, GARMIN) et repères, 5 gros poussoirs noirs striés,
                  bracelet silicone coyote ajouré (deux jours ovales de chaque côté) qui s'efface aux extrémités, ombre
                  portée douce. Écran rond transparent (rayon 270 px sur 1024, attendu par fn_watch : ce qui est dessiné
                  dessous apparaît), bord du verre et reflet très légers.
  gw_disc.paa     disque blanc plein (fond de l'écran, teinté en noir ; points des jauges).
  gw_alt.paa, gw_bt.paa, gw_phone.paa, gw_bolt.paa, gw_arrow.paa, gw_pin.paa
                  icônes blanches (teintées par le code) : montagne, Bluetooth, téléphone, Body Battery, flèche de
                  position, point d'un équipier.

    python3 tools/gen_garmin_watch.py      (écrit dans Sources/addons/main/data ; --png : copies PNG dans tools/render)
Dépendances : Pillow, numpy, paa_dxt.py.
"""
import math
import os
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from paa_dxt import save_paa  # noqa: E402

DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
# Inscriptions de la lunette : Inter (sans géométrique proche de celle de Garmin), sinon DejaVu.
LABEL_FONT = next((f for f in ("/usr/share/fonts/opentype/inter/Inter-SemiBold.otf", "/usr/share/fonts/opentype/inter/Inter-Bold.otf") if os.path.exists(f)), FONT)

N = 1024
C = N / 2
RS, RB, RC = 270, 322, 346  # écran, lunette, boîtier (px) ; RS = 270 est attendu par fn_watch


def font(sz):
    try:
        return ImageFont.truetype(FONT, sz)
    except OSError:
        return ImageFont.load_default()


def radial(rgb_in, rgb_out, r0, r1):
    """Dégradé radial (tableau N x N x 3) de rgb_in au rayon r0 à rgb_out au rayon r1."""
    y, x = np.mgrid[0:N, 0:N]
    d = np.hypot(x - C, y - C)
    t = np.clip((d - r0) / max(1, r1 - r0), 0, 1)[..., None]
    return np.asarray(rgb_in, float) * (1 - t) + np.asarray(rgb_out, float) * t


def _blur(a, r):
    """Flou gaussien séparable d'un tableau float 2D (numpy seul), bords prolongés."""
    if r <= 0:
        return a.copy()
    k = int(math.ceil(r * 3))
    g = np.exp(-0.5 * (np.arange(-k, k + 1) / r) ** 2)
    g /= g.sum()
    out = np.pad(a, ((0, 0), (k, k)), mode="edge")
    out = sum(g[i] * out[:, i:i + a.shape[1]] for i in range(2 * k + 1))
    out = np.pad(out, ((k, k), (0, 0)), mode="edge")
    return sum(g[i] * out[i:i + a.shape[0], :] for i in range(2 * k + 1))


def _mask(draw_fn):
    m = Image.new("L", (N, N), 0)
    draw_fn(ImageDraw.Draw(m))
    return np.asarray(m, float) / 255


def _curved(img, text, a_deg, r, sz, fill, font_path=FONT):
    """Texte imprimé le long de la lunette, centré sur l'angle a_deg (0 à droite, sens trigo), lisible de l'extérieur
    en haut, de l'intérieur en bas (comme sur la montre)."""
    try:
        f = ImageFont.truetype(font_path, sz)
    except OSError:
        f = ImageFont.load_default()
    upper = math.sin(math.radians(a_deg)) >= 0
    ws = [f.getlength(ch) + 1.6 for ch in text]
    total = sum(ws)
    ang = math.radians(a_deg) + (total / 2 / r if upper else -total / 2 / r)
    for ch, cw in zip(text, ws):
        mid = ang - (cw / 2 / r if upper else -cw / 2 / r)
        t = Image.new("RGBA", (int(cw) + 12, sz + 12), (0, 0, 0, 0))
        ImageDraw.Draw(t).text((6, 2), ch, font=f, fill=fill)
        rot = math.degrees(mid) - 90 if upper else math.degrees(mid) + 90
        t = t.rotate(rot, expand=True, resample=Image.BICUBIC)
        cx, cy = C + r * math.cos(mid), C - r * math.sin(mid)
        img.alpha_composite(t, (int(round(cx - t.width / 2)), int(round(cy - t.height / 2))))
        ang = ang - cw / r if upper else ang + cw / r


BUTTONS = ((150, "LIGHT"), (180, "UP"), (210, "DOWN"), (30, "START"), (-30, "BACK"))


def frame():
    """Montre vue de face, rendue par éclairage d'une carte de hauteurs (normales, diffus, spéculaire, occlusion),
    lumière en haut à gauche ; ombre portée douce autour ; écran transparent avec reflet du verre."""
    y, x = np.mgrid[0:N, 0:N].astype(float)
    d = np.hypot(x - C, y - C)
    ang = np.arctan2(C - y, x - C)
    rng = np.random.default_rng(965)
    H = np.zeros((N, N))
    alb = np.zeros((N, N, 3))
    spec = np.zeros((N, N))
    shin = np.full((N, N), 16.0)
    cover = np.zeros((N, N))

    def put(mask, height, rgb, sp, sh):
        sel = (mask > 0.5) & (height >= H - 0.01)
        H[sel] = height[sel] if np.ndim(height) else height
        alb[sel] = rgb[sel] if np.ndim(rgb) == 3 else rgb
        spec[sel] = sp
        shin[sel] = sh
        np.maximum(cover, mask, out=cover)

    coyote = np.array([112, 101, 82], float)
    # Bracelet silicone coyote (comme la Forerunner 965 « tan »), bombé, grain fin, deux rangées de jours ovales
    # de chaque côté ; il s'efface vers les extrémités de la texture.
    sw = RC * 2 * 0.6
    xs = (x - C) / (sw / 2)
    in_strap = (np.abs(xs) <= 1) & (np.abs(y - C) > RC * 0.55)
    dome = np.sqrt(np.clip(1 - xs ** 2, 0, 1)) ** 0.4
    sh_strap = 10 + 9 * dome + _blur(rng.normal(0, 0.6, (N, N)), 1.0)
    slot_m = _mask(lambda dd: [dd.rounded_rectangle((C - sw * 0.3, yy - 13, C + sw * 0.3, yy + 13), radius=13, fill=255)
                               for sy in (-1, 1) for yy in (C + sy * (RC + 70), C + sy * (RC + 122))])
    slot_s = _blur(slot_m, 2.5)
    sh_strap = sh_strap - slot_s * 14
    strap_rgb = np.zeros((N, N, 3)) + coyote * 0.93
    strap_rgb = strap_rgb * (1 - slot_s * 0.9)[..., None]
    put(in_strap.astype(float), sh_strap, strap_rgb, 0.08, 10)
    # Fondu des extrémités du bracelet (alpha), calculé plus bas.
    fade = np.clip((np.abs(y - C) - (C - 18)) / -110, 0, 1)
    fade = np.where(np.abs(y - C) > RC * 0.55, fade, 1)

    # Épaulements du boîtier vers le bracelet (trapèzes évasés), bords adoucis.
    def _lugs(dd):
        for sy in (-1, 1):
            ya, yb = C + sy * RC * 0.7, C + sy * (RC + 28)
            dd.polygon([(C - RC * 0.74, ya), (C + RC * 0.74, ya), (C + sw / 2 + 6, yb), (C - sw / 2 - 6, yb)], fill=255)
    lug = _blur(_mask(_lugs), 7)
    lug_h = 12 + _blur(lug, 9) * 18
    put(lug, lug_h, coyote, 0.12, 18)

    # Poussoirs : gros boutons noirs striés (grip), sortent du boîtier.
    for a_deg, _lbl in BUTTONS:
        r = math.radians(a_deg)
        L, W = (58, 64) if a_deg == 30 else (54, 58)
        ux, uy = math.cos(r), -math.sin(r)
        px, py = -uy, ux
        cx0, cy0 = C + (RC + 2) * ux, C + (RC + 2) * uy
        along = (x - cx0) * ux + (y - cy0) * uy
        across = (x - cx0) * px + (y - cy0) * py
        m = (((np.maximum(np.abs(along) - L / 2 + 16, 0) ** 2 + np.maximum(np.abs(across) - W / 2 + 16, 0) ** 2) <= 256)).astype(float)
        prof = np.sqrt(np.clip(1 - (across / (W / 2)) ** 2, 0, 1))
        knurl = ((np.sin(across * 1.3) > 0.1) & (along > 4)).astype(float) * 1.6
        bh = 6 + 14 * prof - knurl
        put(m, bh, np.array([30, 30, 32], float), 0.3, 24)

    # Boîtier polymère coyote, bord arrondi.
    case_m = (d <= RC).astype(float)
    e = np.clip((d - (RC - 26)) / 26, 0, 1)
    case_h = 22 + 10 * np.sqrt(np.clip(1 - e ** 2, 0, 1))
    put(case_m, case_h, coyote, 0.12, 18)

    # Lunette noire mate, légèrement satinée, chanfreins dedans et dehors.
    bez_m = ((d >= RS + 8) & (d <= RB)).astype(float)
    t_in = np.clip((d - (RS + 8)) / 8, 0, 1)
    t_out = np.clip((RB - d) / 7, 0, 1)
    bez_h = 34 + 5 * np.minimum(t_in, t_out)
    brushed = _blur(rng.normal(0, 1, (N, N)), 0.6)
    bez_rgb = np.zeros((N, N, 3)) + np.array([24, 24, 26], float)
    bez_rgb += (brushed * 1.2)[..., None]
    put(bez_m, bez_h, bez_rgb, 0.32, 28)
    # Joint noir de l'écran.
    gas_m = ((d >= RS) & (d < RS + 8)).astype(float)
    put(gas_m, np.full((N, N), 36.0), np.array([5, 5, 6], float), 0.05, 8)

    # --- Éclairage ---
    k = 0.9
    gy, gx = np.gradient(_blur(H, 1.1))
    nx, ny, nz = -gx * k, -gy * k, np.ones_like(H)
    nl = np.sqrt(nx ** 2 + ny ** 2 + nz ** 2)
    nx, ny, nz = nx / nl, ny / nl, nz / nl
    L = np.array([-0.46, -0.56, 0.69])
    L = L / np.linalg.norm(L)
    Hv = L + np.array([0, 0, 1.0])
    Hv = Hv / np.linalg.norm(Hv)
    diff = np.clip(nx * L[0] + ny * L[1] + nz * L[2], 0, 1)
    ndh = np.clip(nx * Hv[0] + ny * Hv[1] + nz * Hv[2], 0, 1)
    sp = spec * ndh ** shin
    # Reflet d'environnement : le haut des surfaces tournées vers le ciel est plus clair.
    env = np.clip(-ny, 0, 1) * 0.25
    ao = 1 - np.clip((_blur(H, 10) - H) / 16, 0, 0.55)
    rgb = alb * (0.38 + 0.85 * diff)[..., None] * ao[..., None] + (sp * 255)[..., None] * np.array([1.0, 1.0, 1.04]) \
        + (env * spec * 120)[..., None]

    # --- Alpha : la montre, l'ombre portée autour, l'écran transparent ---
    alpha = cover * fade
    sh = _blur(np.roll(np.roll(cover * fade, 10, axis=0), 6, axis=1), 16) * 0.55
    out_a = np.clip(alpha + sh * (1 - alpha), 0, 1)
    rgb = np.where(alpha[..., None] > 0, rgb * (alpha / np.maximum(out_a, 1e-6))[..., None], 0)
    in_scr = d < RS
    # Verre : bord interne sombre (épaisseur du verre), reflet large et doux en haut à gauche, liseré clair.
    edge = np.clip((d - (RS - 18)) / 18, 0, 1) ** 1.6 * 0.62
    glare = np.clip(1 - np.hypot(x - (C - RS * 0.5), y - (C - RS * 0.62)) / (RS * 0.95), 0, 1) ** 2.4 * 0.085
    crescent = np.clip(1 - np.abs(d - (RS - 6)) / 4, 0, 1) * np.clip(np.cos(ang - math.radians(125)), 0, 1) ** 2 * 0.45
    ga = np.clip(edge + glare + crescent, 0, 1)
    grgb = (np.array([255, 255, 255], float)[None, None] * ((glare + crescent) / np.maximum(ga, 1e-6))[..., None])
    rgb[in_scr] = grgb[in_scr]
    out_a[in_scr] = ga[in_scr]
    img = Image.fromarray(np.dstack([np.clip(rgb, 0, 255), np.clip(out_a * 255, 0, 255)]).astype(np.uint8), "RGBA")

    # Inscriptions imprimées sur la lunette et repères (12, 3, 6, 9 h et à côté des boutons).
    rr = (RS + 8 + RB) / 2
    ink = (206, 202, 192, 240)
    labels = {146: "LIGHT", 180: "UP", 214: "DOWN", 36: "START | STOP", -36: "BACK"}
    for a_deg, t in labels.items():
        _curved(img, t, a_deg, rr - 1, 17, ink, LABEL_FONT)
    _curved(img, "GARMIN", 270, rr - 1, 19, ink, LABEL_FONT)
    dd = ImageDraw.Draw(img)
    for a_deg in (90, 0, 180, 128, 232, -52):
        r = math.radians(a_deg)
        ux, uy = math.cos(r), -math.sin(r)
        dd.line((C + (RS + 10) * ux, C + (RS + 10) * uy, C + (RS + 19) * ux, C + (RS + 19) * uy), fill=ink, width=3)
    dd.line((C, C + RB - 9, C, C + RB - 2), fill=ink, width=3)
    return img


def disc():
    s = 256
    im = Image.new("RGBA", (s * 4, s * 4), (255, 255, 255, 0))
    ImageDraw.Draw(im).ellipse((4, 4, s * 4 - 5, s * 4 - 5), fill=(255, 255, 255, 255))
    return im.resize((s, s), Image.LANCZOS)


def icon(draw_fn, s=128):
    S = 4
    im = Image.new("RGBA", (s * S, s * S), (255, 255, 255, 0))
    draw_fn(ImageDraw.Draw(im), s * S)
    return im.resize((s, s), Image.LANCZOS)


def i_alt(d, z):
    d.polygon([(z * 0.04, z * 0.86), (z * 0.38, z * 0.26), (z * 0.54, z * 0.5), (z * 0.66, z * 0.36), (z * 0.96, z * 0.86)], fill="white")
    d.polygon([(z * 0.30, z * 0.40), (z * 0.38, z * 0.26), (z * 0.46, z * 0.40), (z * 0.41, z * 0.37), (z * 0.38, z * 0.42), (z * 0.34, z * 0.37)], fill=(255, 255, 255, 0))


def i_bt(d, z):
    w = int(z * 0.085)
    p = [(z * 0.28, z * 0.30), (z * 0.70, z * 0.68), (z * 0.50, z * 0.86), (z * 0.50, z * 0.14), (z * 0.70, z * 0.32), (z * 0.28, z * 0.70)]
    d.line(p, fill="white", width=w, joint="curve")


def i_phone(d, z):
    d.rounded_rectangle((z * 0.28, z * 0.08, z * 0.72, z * 0.92), radius=z * 0.08, fill="white")
    d.rounded_rectangle((z * 0.33, z * 0.17, z * 0.67, z * 0.78), radius=z * 0.02, fill=(255, 255, 255, 0))
    d.ellipse((z * 0.46, z * 0.82, z * 0.54, z * 0.88), fill=(255, 255, 255, 0))


def i_bolt(d, z):
    d.polygon([(z * 0.58, z * 0.04), (z * 0.20, z * 0.56), (z * 0.46, z * 0.56), (z * 0.38, z * 0.96), (z * 0.80, z * 0.40), (z * 0.54, z * 0.40)], fill="white")


def i_arrow(d, z):
    d.polygon([(z * 0.5, z * 0.04), (z * 0.88, z * 0.92), (z * 0.5, z * 0.72), (z * 0.12, z * 0.92)], fill="white")


def i_pin(d, z):
    d.ellipse((z * 0.14, z * 0.14, z * 0.86, z * 0.86), fill="white")
    d.ellipse((z * 0.30, z * 0.30, z * 0.70, z * 0.70), fill=(255, 255, 255, 0))


def main():
    png = "--png" in sys.argv
    out = {
        "gw_frame": frame(),
        "gw_disc": disc(),
        "gw_alt": icon(i_alt),
        "gw_bt": icon(i_bt),
        "gw_phone": icon(i_phone),
        "gw_bolt": icon(i_bolt),
        "gw_arrow": icon(i_arrow),
        "gw_pin": icon(i_pin),
    }
    for name, im in out.items():
        save_paa(im, os.path.join(DATA, name + ".paa"))
        if png:
            os.makedirs(RENDER, exist_ok=True)
            im.save(os.path.join(RENDER, name + ".png"))
        print(name)


if __name__ == "__main__":
    main()
