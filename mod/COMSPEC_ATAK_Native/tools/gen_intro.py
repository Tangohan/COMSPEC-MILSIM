#!/usr/bin/env python3
"""
Intro au démarrage du jeu (fn_introSplash) : textures et son.
  intro_logo_ca.paa   emblème COMSPEC ATAK + « COMSPEC » en blanc sur fond transparent (1024x512), teinté en jeu
                      (marque des écrans de chargement, fn_loadingBadge) ;
  intro_chime.ogg     son : carillon de démarrage synthétique, court et discret (~1,6 s).
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


def sound(path):
    """Carillon de démarrage synthétique, sobre : deux notes douces (la5 puis mi6, quinte montante) en sinus avec
    quelques partiels de cloche, une nappe grave très légère et une petite réverbération. ~1,6 s, crête à -6 dBFS
    (le jeu le joue en plus à volume 0,35)."""
    n = int(SR * 1.6)
    t = np.arange(n) / SR
    out = np.zeros(n)

    def note(t0, hz, amp, decay):
        i = t >= t0
        tt = t[i] - t0
        env = np.minimum(tt / 0.008, 1) * np.exp(-tt * decay)
        tone = (np.sin(2 * math.pi * hz * tt) + 0.22 * np.sin(2 * math.pi * hz * 2.0 * tt) * np.exp(-tt * 3)
                + 0.06 * np.sin(2 * math.pi * hz * 3.01 * tt) * np.exp(-tt * 6))
        out[i] += tone * env * amp

    note(0.0, 880.0, 0.55, 3.2)
    note(0.13, 1318.5, 0.5, 2.6)
    # Nappe grave (la3 + mi4) qui gonfle puis s'éteint sous les deux notes.
    pad = (np.sin(2 * math.pi * 220 * t) + 0.6 * np.sin(2 * math.pi * 329.6 * t)) * 0.08
    pad *= np.minimum(t / 0.15, 1) * np.exp(-t * 2.2)
    out += pad
    # Réverbération sommaire : échos décroissants.
    rev = out.copy()
    for k, g in ((0.029, 0.3), (0.043, 0.24), (0.067, 0.17), (0.101, 0.11), (0.149, 0.07)):
        dly = int(k * SR)
        rev[dly:] += out[:-dly] * g
    fade = np.ones(n)
    fade[-int(SR * 0.25):] = np.linspace(1, 0, int(SR * 0.25))
    rev *= fade
    rev *= 0.5 / np.max(np.abs(rev))
    pcm = (rev * 32767).astype(np.int16)
    stereo = np.stack([pcm, np.roll(pcm, 14)], axis=1)
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
        logo().save(os.path.join(HERE, "render", "intro_logo.png"))
    sound(os.path.join(OUT, "intro_chime.ogg"))


if __name__ == "__main__":
    main()
