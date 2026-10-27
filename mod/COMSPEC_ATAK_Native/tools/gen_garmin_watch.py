#!/usr/bin/env python3
"""Montre Garmin ouverte avec la touche « Montre » du jeu (O) : textures de fn_watch.

  gw_frame.paa, gw_frame_coyote.paa, gw_frame_freecandy.paa, gw_frame_usa.paa
                  la montre vue de face, tirée des photos fournies par TTRD (tools/src/garmin_noire.png, _coyote,
                  _freecandy, _usa ; Forerunner 965 noire, coyote, arc-en-ciel « FreeCandy », drapeau américain) :
                  fond noir de la photo détouré (remplissage depuis les bords), écran rond repéré par ajustement d'un
                  cercle puis rendu transparent, photo mise à l'échelle et centrée pour que l'écran fasse 270 px de
                  rayon au centre d'une texture 1024 (attendu par fn_watch), bracelet adouci aux bords de la texture,
                  ombre portée douce, bord du verre très léger.
  item_garminwatch_*.paa
                  icônes d'inventaire des trois coloris ajoutés (la montre détourée, écran éteint).
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
from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from paa_dxt import save_paa  # noqa: E402

DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")

N = 1024
C = N / 2
RS = 270  # rayon de l'écran (px) attendu par fn_watch


# Coloris : texture -> photo source (tools/src).
SKINS = {
    "gw_frame": "garmin_noire.png",
    "gw_frame_coyote": "garmin_coyote.png",
    "gw_frame_freecandy": "garmin_freecandy.png",
    "gw_frame_usa": "garmin_usa.png",
}


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


def _screen(m, cx, cy):
    """Cercle de l'écran (noir presque pur) : premier pixel plus clair sur 72 rayons depuis le centre supposé, puis
    ajustement algébrique d'un cercle (trois passes, le centre se recale). Renvoie (cx, cy, rayon)."""
    for _ in range(3):
        pts = []
        for t in np.radians(np.arange(0, 360, 5)):
            for r in range(120, 340):
                x, y = int(round(cx + r * math.cos(t))), int(round(cy - r * math.sin(t)))
                if m[y, x] > 9:
                    pts.append((x, y))
                    break
        p = np.array(pts, float)
        A = np.c_[2 * p[:, 0], 2 * p[:, 1], np.ones(len(p))]
        sol = np.linalg.lstsq(A, p[:, 0] ** 2 + p[:, 1] ** 2, rcond=None)[0]
        cx, cy = sol[0], sol[1]
        R = math.sqrt(sol[2] + cx ** 2 + cy ** 2)
    return cx, cy, R


def photo_frame(src):
    """Montre détourée d'une photo sur fond noir, écran transparent de rayon RS au centre d'une texture N x N."""
    im = Image.open(os.path.join(HERE, "src", src)).convert("RGB")
    a = np.asarray(im).astype(float)
    m = a.max(2)
    h, w = m.shape
    cx, cy, R = _screen(m, w / 2, h * 0.45)
    # Fond : pixels presque noirs reliés aux bords de la photo.
    bg = Image.fromarray(((m <= 7) * 255).astype(np.uint8)).copy()
    for seed in ((0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1), (w // 2, 0), (w // 2, h - 1), (0, h // 2), (w - 1, h // 2)):
        if bg.getpixel(seed) == 255:
            ImageDraw.floodfill(bg, seed, 128)
    watch = (np.asarray(bg) != 128).astype(float)
    alpha = np.clip(_blur(watch, 1.2) * 1.15, 0, 1)
    # Écran : transparent, bord adouci sur 2 px.
    y, x = np.mgrid[0:h, 0:w].astype(float)
    d = np.hypot(x - cx, y - cy)
    alpha = np.where(d < R + 1, np.clip(d - (R - 1), 0, 2) / 2 * alpha, alpha)
    rgba = Image.fromarray(np.dstack([a, alpha * 255]).astype(np.uint8), "RGBA")
    # Mise à l'échelle : écran de rayon RS, centré.
    s = RS / R
    rgba = rgba.resize((int(round(w * s)), int(round(h * s))), Image.LANCZOS)
    ox, oy = cx * s - C, cy * s - C
    out = rgba.crop((int(round(ox)), int(round(oy)), int(round(ox)) + N, int(round(oy)) + N))
    arr = np.asarray(out).astype(float)
    # Bracelet adouci aux bords haut et bas de la texture.
    yy = np.arange(N)[:, None]
    arr[..., 3] *= np.clip(np.minimum(yy, N - 1 - yy) / 70, 0, 1)
    # Ombre portée douce sous la montre, et bord du verre (léger dégradé sombre vers l'intérieur de l'écran).
    wa = arr[..., 3] / 255
    sh = _blur(np.roll(np.roll(wa, 10, axis=0), 6, axis=1), 14) * 0.5
    oa = np.clip(wa + sh * (1 - wa), 0, 1)
    rgb = arr[..., :3] * (wa / np.maximum(oa, 1e-6))[..., None]
    y2, x2 = np.mgrid[0:N, 0:N].astype(float)
    d2 = np.hypot(x2 - C, y2 - C)
    scr = d2 < RS
    edge = np.clip((d2 - (RS - 14)) / 14, 0, 1) ** 1.6 * 0.55
    rgb[scr] = 0
    oa[scr] = edge[scr]
    return Image.fromarray(np.dstack([np.clip(rgb, 0, 255), oa * 255]).astype(np.uint8), "RGBA")


def item_icon(frame):
    """Icône d'inventaire 256 x 256 : la montre (écran éteint) recadrée sur le boîtier et le haut du bracelet."""
    f = frame.copy()
    bg = Image.new("RGBA", f.size, (0, 0, 0, 0))
    ImageDraw.Draw(bg).ellipse((C - RS, C - RS, C + RS, C + RS), fill=(4, 4, 5, 255))
    bg.alpha_composite(f)
    return bg.crop((C - 400, C - 400, C + 400, C + 400)).resize((256, 256), Image.LANCZOS)


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
        **{k: photo_frame(v) for k, v in SKINS.items()},
        "gw_disc": disc(),
        "gw_alt": icon(i_alt),
        "gw_bt": icon(i_bt),
        "gw_phone": icon(i_phone),
        "gw_bolt": icon(i_bolt),
        "gw_arrow": icon(i_arrow),
        "gw_pin": icon(i_pin),
    }
    for k in ("coyote", "freecandy", "usa"):
        out["item_garminwatch_" + k] = item_icon(out["gw_frame_" + k])
    for name, im in out.items():
        save_paa(im, os.path.join(DATA, name + ".paa"))
        if png:
            os.makedirs(RENDER, exist_ok=True)
            im.save(os.path.join(RENDER, name + ".png"))
        print(name)


if __name__ == "__main__":
    main()
