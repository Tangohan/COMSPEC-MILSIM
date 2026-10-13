#!/usr/bin/env python3
"""Textures des gouttes de pluie sur l'écran du téléphone (fn_screenRain), rendues comme de l'eau sur du verre.

Usage : python3 gen_rain.py <hemtt>
Sortie : Sources/addons/main/data/rain_*.paa (couleurs réelles, opacité réglée en jeu) :
  rain_bead_0..3     goutte posée, 128 x 128 (diamètre ~72 % du cadre, irrégulière)
  rain_run_0..1      goutte qui glisse, 128 x 256 (tête au centre à 75 % de la hauteur, corps court vers le haut)
  rain_trail_0..1    traînée : chapelet de gouttelettes, 64 x 256 (plus fine vers le haut)
  rain_mist_land / rain_mist_port   bruine fine sur tout l'écran, 1024 x 512 et 512 x 1024
Champ de hauteur = union de sphères au bord raide ; ombrage par la normale : corps un peu assombri, liseré sombre net,
croissant clair dans le bas (lumière concentrée par la lentille), reflet en haut à gauche.
Dépendances : numpy, Pillow, HEMTT (hemtt utils paa convert).
"""
import os
import sys
import tempfile

import math
import random

import numpy as np
from PIL import Image

SS = 4  # suréchantillonnage


def _field(w, h, spheres):
    """spheres : [(cx, cy, r), ...] en pixels de la texture finale. Renvoie la hauteur normalisée (0 dehors)."""
    W, H = w * SS, h * SS
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    xx = (xx + 0.5) / SS
    yy = (yy + 0.5) / SS
    hf = np.zeros((H, W), np.float32)
    for cx, cy, r in spheres:
        d2 = (xx - cx) ** 2 + (yy - cy) ** 2
        v = r * 0.6 * np.clip(1 - d2 / (r * r), 0, None) ** 0.35  # bord raide : angle de contact de l'eau sur le verre
        np.maximum(hf, v, out=hf)
    return hf


def edge_hint(n):
    return np.clip(1 - n[..., 2], 0, 1) ** 0.5


def _shade(hf, w, h, body=0.12, rim=0.85, spec=1.0, glow=0.5):
    """Goutte vue de face : léger assombrissement du corps (la lentille rapetisse et inverse ce qu'il y a derrière),
    liseré sombre net sur le bord, croissant clair à l'intérieur du bas (la lumière concentrée par la lentille),
    reflet net en haut à gauche."""
    gy, gx = np.gradient(hf * SS)
    n = np.stack([-gx, -gy, np.ones_like(hf)], -1)
    n /= np.linalg.norm(n, axis=-1, keepdims=True)
    inside = (hf > 0).astype(np.float32)
    nz = n[..., 2]
    L = np.array([-0.45, -0.6, 0.66]); L /= np.linalg.norm(L)
    Hv = L + np.array([0, 0, 1.0]); Hv /= np.linalg.norm(Hv)
    s = (np.clip((n @ Hv), 0, 1) ** 45 + 0.35 * np.clip((n @ Hv), 0, 1) ** 8 * edge_hint(n)) * inside
    edge = np.clip(1 - nz, 0, 1) * inside
    down = np.clip(n[..., 1] / np.maximum(np.hypot(n[..., 0], n[..., 1]), 1e-4), 0, 1)
    up = np.clip(-n[..., 1] / np.maximum(np.hypot(n[..., 0], n[..., 1]), 1e-4), 0, 1)
    dark = np.clip(edge ** 2.2 * rim * (0.6 + 0.4 * up) + body * inside, 0, 1)
    caustic = np.clip(edge ** 0.9 * (1 - edge ** 3) * down ** 1.5 * glow, 0, 1)
    a_white = np.clip(s * spec + caustic, 0, 1)
    alpha = a_white + dark * (1 - a_white)
    lum = np.where(alpha > 1e-4, a_white / np.maximum(alpha, 1e-4), 0)
    rgb = np.stack([lum * 235 + 8, lum * 242 + 10, lum * 250 + 12], -1)
    img = np.dstack([rgb, alpha * 255]).astype(np.float32)
    im = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8), "RGBA")
    return _down(im, w, h)


def _down(im, w, h):
    a = np.asarray(im).astype(np.float32) / 255
    rgb = a[..., :3] * a[..., 3:4]
    pm = np.dstack([rgb, a[..., 3:4]])
    H, W = pm.shape[:2]
    pm = pm.reshape(h, H // h, w, W // w, 4).mean(axis=(1, 3))
    al = pm[..., 3:4]
    out = np.dstack([np.where(al > 1e-4, pm[..., :3] / np.maximum(al, 1e-4), 0), al])
    return Image.fromarray((np.clip(out, 0, 1) * 255).astype(np.uint8), "RGBA")


def _blob(cx, cy, r, rnd, n=7):
    """Goutte irrégulière : quelques sphères autour du centre, plus lourde vers le bas."""
    sp = [(cx, cy, r)]
    for _ in range(n):
        ang = rnd.uniform(0, 2 * math.pi)
        dd = r * rnd.uniform(0.15, 0.35)
        sp.append((cx + math.cos(ang) * dd, cy + math.sin(ang) * dd * 0.8 + r * 0.06, r * rnd.uniform(0.7, 0.85)))
    return sp


def bead(seed, s=128):
    rnd = random.Random(seed)
    return _shade(_field(s, s, _blob(s / 2, s / 2, s * 0.36, rnd)), s, s)


def run(seed, w=128, h=256):
    """Goutte qui glisse : tête large en bas (centre à 75 % de la hauteur), corps qui s'affine vers le haut, court."""
    rnd = random.Random(seed)
    r = w * 0.36
    cy = h * 0.75
    sp = _blob(w / 2, cy, r, rnd, 5)
    # Corps : sphères de plus en plus petites vers le haut (la goutte s'étire en glissant).
    for i in range(1, 12):
        t = i / 11
        sp.append((w / 2 + rnd.uniform(-1, 1), cy - t * r * 1.5, r * (0.92 - 0.6 * t ** 1.3)))
    return _shade(_field(w, h, sp), w, h)


def trail(seed, w=64, h=256):
    """Traînée : chapelet de gouttelettes laissées par la goutte, plus serrées et plus grosses vers le bas."""
    rnd = random.Random(seed)
    sp = []
    for _ in range(46):
        t = rnd.random() ** 0.7
        y = 6 + t * (h - 12)
        half = 4 + 14 * t
        sp.append((w / 2 + rnd.uniform(-half, half), y, rnd.uniform(1.2, 2.2 + 3.3 * t)))
    return _shade(_field(w, h, sp), w, h, rim=0.6, glow=0.25)


def mist(seed, w=1024, h=512):
    """Bruine : gouttelettes fines sur tout l'écran (opacité réglée en jeu selon l'humidité)."""
    rnd = random.Random(seed)
    sp = []
    for _ in range(int(w * h / 2600)):
        r = rnd.choice([1.8, 2.2, 2.6, 3, 3.6, 4.4, 5.5]) * rnd.uniform(0.85, 1.2)
        sp.append((rnd.uniform(0, w), rnd.uniform(0, h), r))
    out = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    # Par tuiles : le champ entier en suréchantillonné serait trop lourd en mémoire.
    T = 256
    for ty in range(0, h, T):
        for tx in range(0, w, T):
            loc = [(x - tx, y - ty, r) for (x, y, r) in sp if tx - 8 < x < tx + T + 8 and ty - 8 < y < ty + T + 8]
            out.paste(_shade(_field(T, T, loc), T, T, rim=0.7, spec=0.55, glow=0.35), (tx, ty))
    return out


HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "data")


def convert(img, name, tmp):
    import subprocess
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    paa = os.path.join(OUT, name + ".paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def main():
    with tempfile.TemporaryDirectory() as tmp:
        for i in range(4):
            convert(bead(61 + i), f"rain_bead_{i}", tmp)
        for i in range(2):
            convert(run(71 + i), f"rain_run_{i}", tmp)
            convert(trail(81 + i), f"rain_trail_{i}", tmp)
        convert(mist(91, 1024, 512), "rain_mist_land", tmp)
        convert(mist(92, 512, 1024), "rain_mist_port", tmp)


if __name__ == "__main__":
    main()
