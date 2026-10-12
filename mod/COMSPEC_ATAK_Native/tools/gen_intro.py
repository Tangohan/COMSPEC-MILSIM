#!/usr/bin/env python3
"""
Intro au démarrage du jeu (fn_introSplash) : textures et son.
  intro_logo_ca.paa   emblème COMSPEC ATAK + « COMSPEC » en blanc sur fond transparent (1024x512), teinté en jeu
                      (copies rouge / cyan décalées pour l'aberration chromatique) ;
  intro_scan_ca.paa   lignes de balayage (64x1024, une ligne sombre sur deux) étirées sur l'écran ;
  intro_glitch.ogg    son : grésillements numériques, montée, impact grave (façon intro de jeu de guerre), ~5 s.
Dessin et son originaux, générés ici.

Usage : python3 gen_intro.py <hemtt>     (Pillow, numpy, ffmpeg avec libvorbis)
"""
import math
import os
import subprocess
import sys
import tempfile
import wave

import numpy as np
from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from gen_assets import OUT, _emblem, _font  # noqa: E402

HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
SR = 44100


def paa(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    dst = os.path.join(OUT, name + ".paa")
    if os.path.exists(dst):
        os.remove(dst)  # « hemtt utils paa convert » n'écrase pas
    subprocess.run([HEMTT, "utils", "paa", "convert", png, dst], check=True)


def logo(w=1024, h=512):
    """Emblème à gauche, mot « COMSPEC » espacé à droite, tout en blanc (la couleur vient du jeu)."""
    k = 2
    W, H = w * k, h * k
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    r = H * 0.3
    _emblem(d, H * 0.42, H / 2, r, k * 1.6)
    # L'emblème est vert : on le passe en blanc en gardant son alpha.
    a = img.split()[3]
    img = Image.new("RGBA", (W, H), (255, 255, 255, 0))
    img.putalpha(a)
    d = ImageDraw.Draw(img)
    txt = "COMSPEC"
    x = H * 0.86
    # Taille du mot ajustée à la place restante (marge à droite).
    size = int(H * 0.3)
    while True:
        f = _font(size)
        sp = size * 0.14
        widths = [d.textlength(c, font=f) for c in txt]
        if x + sum(widths) + sp * (len(txt) - 1) <= W - H * 0.08 or size < 20:
            break
        size -= 4
    y = H / 2 - f.size * 0.62
    for c, cw in zip(txt, widths):
        d.text((x, y), c, font=f, fill=(255, 255, 255, 255))
        x += cw + sp
    return img.resize((w, h), Image.LANCZOS)


def scanlines(w=64, h=1024):
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    for y in range(0, h, 2):
        d.line([(0, y), (w, y)], fill=(0, 0, 0, 110))
    return img


def sound(path):
    """Grésillements (bruit haché), balayage montant, puis impact grave avec queue de réverbération."""
    rnd = np.random.default_rng(7)
    n = int(SR * 5.2)
    t = np.arange(n) / SR
    out = np.zeros(n)

    def burst(t0, dur, amp, hz):
        i0, i1 = int(t0 * SR), int((t0 + dur) * SR)
        seg = rnd.uniform(-1, 1, i1 - i0)
        # Bruit échantillonné-bloqué : son « numérique » cassé.
        hold = max(1, int(SR / hz))
        seg = np.repeat(seg[::hold], hold)[: i1 - i0]
        env = np.ones(i1 - i0)
        env[: 80] = np.linspace(0, 1, 80)
        env[-200:] = np.linspace(1, 0, 200)
        out[i0:i1] += seg * env * amp

    for t0, dur, amp, hz in ((0.05, 0.07, 0.35, 3000), (0.22, 0.05, 0.3, 6000), (0.31, 0.12, 0.4, 1800),
                             (0.62, 0.04, 0.25, 9000), (0.7, 0.09, 0.35, 2500), (1.05, 0.05, 0.3, 4000),
                             (3.9, 0.08, 0.35, 2200), (4.05, 0.05, 0.3, 7000), (4.2, 0.12, 0.4, 1500)):
        burst(t0, dur, amp, hz)
    # Montée : balayage 60 -> 900 Hz sous du bruit filtré, de 0,8 à 1,9 s.
    m = (t >= 0.8) & (t < 1.9)
    tm = t[m] - 0.8
    f = 60 + (900 - 60) * (tm / 1.1) ** 2
    ph = 2 * math.pi * np.cumsum(f) / SR
    out[m] += np.sin(ph) * 0.18 * (tm / 1.1) ** 1.5
    # Impact à 1,9 s : grave 42 Hz qui descend, sous-harmonique, claquement de bruit, longue queue.
    i = t >= 1.9
    ti = t[i] - 1.9
    f = 42 * np.exp(-ti * 0.25) + 18
    ph = 2 * math.pi * np.cumsum(f) / SR
    boom = np.sin(ph) * np.exp(-ti * 1.1) * 0.95 + np.sin(ph * 0.5) * np.exp(-ti * 0.8) * 0.4
    click = rnd.uniform(-1, 1, i.sum()) * np.exp(-ti * 28) * 0.6
    # Nappe métallique (accord dissonant) qui s'éteint.
    pad = sum(np.sin(2 * math.pi * hz * ti) for hz in (110, 116.5, 164.8)) * 0.05 * np.exp(-ti * 0.9)
    out[i] += boom + click + pad
    # Réverbération sommaire : échos décroissants.
    rev = out.copy()
    for k, g in ((0.031, 0.35), (0.047, 0.28), (0.071, 0.2), (0.113, 0.14), (0.167, 0.09)):
        dly = int(k * SR)
        rev[dly:] += out[:-dly] * g
    rev /= np.max(np.abs(rev)) * 1.05
    pcm = (rev * 32767).astype(np.int16)
    stereo = np.stack([pcm, np.roll(pcm, 9)], axis=1)
    wav = path[:-4] + ".wav"
    with wave.open(wav, "wb") as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(stereo.tobytes())
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", wav, "-c:a", "libvorbis", "-q:a", "5", path], check=True)
    os.remove(wav)


def main():
    with tempfile.TemporaryDirectory() as tmp:
        paa(logo(), "intro_logo_ca", tmp)
        paa(scanlines(), "intro_scan_ca", tmp)
        logo().save(os.path.join(HERE, "render", "intro_logo.png"))
    sound(os.path.join(OUT, "intro_glitch.ogg"))


if __name__ == "__main__":
    main()
