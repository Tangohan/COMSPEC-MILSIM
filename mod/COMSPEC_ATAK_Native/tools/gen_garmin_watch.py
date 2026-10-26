#!/usr/bin/env python3
"""Montre Garmin ouverte avec la touche « Montre » du jeu (O) : textures de fn_watch.

  gw_frame.paa    la montre vue de face (Forerunner 570 noire) : boîtier polymère, lunette métal anthracite gravée
                  (LIGHT, UP, DOWN, START, BACK, GARMIN), 5 poussoirs, cornes et bracelet silicone qui s'efface aux
                  extrémités (trous et passant en bas). Écran rond transparent (rayon 270 px sur 1024 : ce qui est
                  dessiné dessous apparaît), reflet du verre très léger.
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

N = 1024
C = N / 2
RS, RB, RC = 270, 300, 334  # écran, lunette, boîtier (px)


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


def strap(img, top):
    """Bracelet silicone 22 mm (largeur 0,52 du boîtier) : grain, bords arrondis, s'efface vers l'extrémité."""
    w = int(RC * 2 * 0.56)
    x0 = int(C - w / 2)
    h = int(C - RC * 0.55)
    s = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    a = np.zeros((h, w), float)
    col = np.zeros((h, w, 3), float)
    xs = np.linspace(-1, 1, w)
    # Arrondi des bords (épaisseur) : plus sombre et plus étroit vers l'extérieur.
    shade = 0.75 + 0.25 * np.cos(xs * math.pi / 2) ** 0.6
    rng = np.random.default_rng(4 if top else 5)
    grain = rng.normal(0, 2.2, (h, w))
    base = np.array([26, 27, 29], float)
    col[:] = base
    col *= shade[None, :, None]
    col += grain[..., None]
    a[:] = 255
    # Bord : 6 px adoucis.
    edge = np.clip((1 - np.abs(xs)) * w / 2 / 5, 0, 1)
    a *= edge[None, :]
    # Fondu vers l'extrémité.
    ys = np.arange(h)
    far = ys if top else (h - 1 - ys)
    a *= np.clip(far / (h * 0.42), 0, 1)[:, None]
    # Arête centrale légèrement brillante (relief du bracelet Forerunner).
    hl = np.exp(-((xs + 0.25) / 0.18) ** 2) * 10
    col += hl[None, :, None]
    s = Image.fromarray(np.dstack([np.clip(col, 0, 255), a]).astype(np.uint8), "RGBA")
    d = ImageDraw.Draw(s)
    if not top:
        # Trous et passant du côté long.
        for k in range(3):
            yy = int(h * 0.58 + k * 34)
            d.ellipse((w / 2 - 7, yy - 7, w / 2 + 7, yy + 7), fill=(6, 6, 7, 255))
            d.ellipse((w / 2 - 5, yy - 6, w / 2 + 5, yy + 2), fill=(14, 14, 15, 255))
        d.rectangle((0, int(h * 0.36), w, int(h * 0.36) + 26), fill=(20, 21, 23, 235))
        d.line((0, int(h * 0.36) + 2, w, int(h * 0.36) + 2), fill=(44, 45, 48, 210), width=2)
    img.alpha_composite(s, (x0, 0 if top else N - h))


def frame():
    img = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    strap(img, True)
    strap(img, False)
    y, x = np.mgrid[0:N, 0:N]
    d = np.hypot(x - C, y - C)
    sa = np.asarray(img.getchannel("A"), float) * np.clip((d - RC + 20) / 20, 0, 1)
    img.putalpha(Image.fromarray(sa.astype(np.uint8)))
    ang = np.arctan2(y - C, x - C)
    # Cornes : prolongement du boîtier vers le bracelet (trapèzes arrondis).
    lug = Image.new("L", (N, N), 0)
    ld = ImageDraw.Draw(lug)
    for sy in (-1, 1):
        yy0 = C + sy * RC * 0.86
        yy1 = C + sy * (RC + 44)
        ld.rounded_rectangle((C - RC * 0.6, min(yy0, yy1), C + RC * 0.6, max(yy0, yy1)), radius=40, fill=255)
    lug = np.asarray(lug.filter(ImageFilter.GaussianBlur(1.2)), float) / 255
    # Boîtier polymère : noir mat, éclairé du haut à gauche.
    light = 0.5 + 0.5 * np.cos(ang + math.radians(135))
    case = radial((30, 31, 33), (14, 14, 15), RB, RC + 60)
    case += (light * 12)[..., None]
    case_a = np.clip((RC + 0.5 - d), 0, 1)
    case_a = np.maximum(case_a, lug)
    # Lunette métal anthracite brossée (anneau RS+10 -> RB), chanfrein clair à l'extérieur.
    brushed = (np.sin(ang * 900) * 0.5 + 0.5) * 2.5
    bez = np.zeros((N, N, 3)) + np.array([58, 60, 64], float)
    bez += (light * 38 + brushed)[..., None]
    t = np.clip((d - (RB - 12)) / 12, 0, 1)[..., None]
    bez = bez * (1 - t * 0.35) + np.array([120, 122, 126], float) * (t * 0.35) * light[..., None]
    in_bez = ((d >= RS + 8) & (d <= RB)).astype(float)
    rgb = case * (1 - in_bez[..., None]) + bez * in_bez[..., None]
    # Joint noir de l'écran (RS -> RS + 8).
    in_gasket = ((d >= RS) & (d < RS + 8)).astype(float)
    rgb = rgb * (1 - in_gasket[..., None]) + np.array([4, 4, 5], float) * in_gasket[..., None]
    alpha = case_a * 255
    # Écran : transparent, avec le reflet du verre (arc clair en haut à gauche) et un bord interne sombre.
    in_scr = d < RS
    glare = np.clip(1 - np.hypot(x - (C - RS * 0.42), y - (C - RS * 0.55)) / (RS * 0.9), 0, 1) ** 2.2 * 20
    edge = np.clip((d - (RS - 16)) / 16, 0, 1) * 150
    scr_a = np.clip(glare + edge, 0, 255)
    rgb[in_scr] = (np.array([255, 255, 255], float) * (glare[in_scr] / np.maximum(scr_a[in_scr], 1))[:, None]
                   + np.array([0, 0, 0], float) * (edge[in_scr] / np.maximum(scr_a[in_scr], 1))[:, None])
    alpha[in_scr] = scr_a[in_scr]
    body = Image.fromarray(np.dstack([np.clip(rgb, 0, 255), np.clip(alpha, 0, 255)]).astype(np.uint8), "RGBA")
    # Poussoirs : 3 à gauche (LIGHT, UP, DOWN), 2 à droite (START, BACK) ; acier sombre, stries.
    btn = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    for a, big in ((150, False), (180, False), (210, False), (30, True), (-30, False)):
        r = math.radians(a)
        L, W = (62, 50) if big else (54, 44)
        b = Image.new("RGBA", (L + 8, W + 8), (0, 0, 0, 0))
        bd = ImageDraw.Draw(b)
        bd.rounded_rectangle((4, 4, L + 4, W + 4), radius=12, fill=(70, 72, 76, 255))
        bd.rounded_rectangle((4, 4, L + 4, W / 2 + 2), radius=12, fill=(104, 106, 110, 255))
        bd.rounded_rectangle((4, W / 2 + 2, L + 4, W + 4), radius=12, fill=(48, 50, 53, 255))
        for k in range(5):
            xx = 4 + L * 0.45 + k * 6
            bd.line((xx, 8, xx, W), fill=(40, 41, 44, 255), width=2)
        b = b.rotate(a, expand=True, resample=Image.BICUBIC)
        cx, cy = C + (RC + 6) * math.cos(r), C - (RC + 6) * math.sin(r)
        btn.alpha_composite(b, (int(cx - b.width / 2), int(cy - b.height / 2)))
    img.alpha_composite(btn)
    img.alpha_composite(body)
    # Gravures de la lunette.
    dd = ImageDraw.Draw(img)

    def bezel_text(t, a, sz=17):
        f = font(sz)
        w = dd.textlength(t, font=f)
        tmp = Image.new("RGBA", (int(w) + 8, sz + 10), (0, 0, 0, 0))
        ImageDraw.Draw(tmp).text((4, 2), t, font=f, fill=(196, 198, 202, 235))
        rot = a - 90 if math.sin(math.radians(a)) >= 0 else a + 90
        tmp = tmp.rotate(rot, expand=True, resample=Image.BICUBIC)
        rr = (RS + 8 + RB) / 2
        cx, cy = C + rr * math.cos(math.radians(a)), C - rr * math.sin(math.radians(a))
        img.alpha_composite(tmp, (int(cx - tmp.width / 2), int(cy - tmp.height / 2)))

    for t, a in (("LIGHT", 150), ("UP", 180), ("DOWN", 210), ("START", 30), ("BACK", -30)):
        bezel_text(t, a)
    bezel_text("GARMIN", -90, 19)
    # Pointe rouge du START (repère Forerunner).
    r = math.radians(30)
    dd.line((C + (RB - 6) * math.cos(r), C - (RB - 6) * math.sin(r), C + (RB + 8) * math.cos(r), C - (RB + 8) * math.sin(r)),
            fill=(214, 56, 44, 255), width=5)
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
