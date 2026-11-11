"""
Sons des BANG spéciaux du mod COMSPEC Flashbang (JAPAN, WOMEN, SUPRISE, GERMAN) : MP3 fournis par COMSPEC -> OGG Vorbis.

    python gen_fb_theme_sounds.py <dossier des MP3> [dossier de copie pour écoute]

Entrées (jamais modifiées) : « JAPAN BANG.mp3 », « WOMEN BANG.mp3 », « SUPRISE BANG.mp3 », « GERMAN BANG.mp3 ».
Sorties dans Sources/addons/main/sounds/ :
  bang_<thème>_enreg.ogg          mono 44,1 kHz : couche 3D (playSound3D) et, pour une source mono, couche proche 2D
  bang_<thème>_stereo_enreg.ogg   stéréo : couche proche 2D, seulement si la source est vraiment stéréo
Traitement : silence de tête retiré (le son part avec le 1er flash), sonie intégrée ramenée à celle des bangs du mod
(cible ci-dessous, jamais plus fort que le bang standard : -11 LUFS, crête momentanée -7 LUFS), limiteur, crête finale
vérifiée après encodage (<= -0,5 dBFS). SUPRISE et GERMAN sont des sources saturées (écrêtées à la source) : elles
sont seulement baissées, la saturation d'origine reste. Affiche aussi les attaques détectées (flux spectral), qui
servent à caler les flashs sur le son (instants[] de CfgSons.hpp).
"""
import os
import re
import shutil
import subprocess
import sys

import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "sounds")
FFMPEG = os.environ.get("FFMPEG") or shutil.which("ffmpeg") or "ffmpeg"
SR = 44100
# thème : (fichier source, sonie intégrée visée en LUFS)
THEMES = {
    "japan": ("JAPAN BANG.mp3", -11.5),
    "women": ("WOMEN BANG.mp3", -11.5),
    "suprise": ("SUPRISE BANG.mp3", -12.5),
    "german": ("GERMAN BANG.mp3", -12.5),
}
CEIL_DB = -1.5          # plafond du limiteur (marge pour le dépassement de l'encodage Vorbis)
PEAK_MAX_DB = -0.5      # crête maximale admise après encodage


def run(args):
    return subprocess.run([FFMPEG, "-hide_banner", "-nostats"] + args, capture_output=True)


def decode(path, channels):
    r = run(["-v", "error", "-i", path, "-ac", str(channels), "-ar", str(SR), "-f", "f32le", "-"])
    if r.returncode:
        sys.exit(r.stderr.decode("utf-8", "replace"))
    return np.frombuffer(r.stdout, dtype="<f4").astype(np.float64).reshape(-1, channels)


def measure(path_or_pcm, channels=1):
    """(sonie intégrée LUFS, crête momentanée LUFS, crête échantillon dBFS)."""
    if isinstance(path_or_pcm, str):
        r = run(["-i", path_or_pcm, "-af", "ebur128", "-f", "null", "-"])
        x = decode(path_or_pcm, channels)
    else:
        x = path_or_pcm
        r = subprocess.run([FFMPEG, "-hide_banner", "-nostats", "-f", "f32le", "-ar", str(SR), "-ac", str(x.shape[1]), "-i", "-",
                            "-af", "ebur128", "-f", "null", "-"], input=x.astype("<f4").tobytes(), capture_output=True)
    log = r.stderr.decode("utf-8", "replace")
    integ = float(re.findall(r"I:\s*(-?[0-9.]+) LUFS", log)[-1])
    mom = max(float(v) for v in re.findall(r" M:\s*(-?[0-9.]+)", log))
    return integ, mom, 20 * np.log10(np.abs(x).max() + 1e-12)


def trim_head(x, thr_db=-40.0):
    """Retire le silence de tête (première fenêtre de 5 ms au-dessus du seuil, moins 5 ms)."""
    w = SR // 200
    env = np.array([np.sqrt((x[i:i + w] ** 2).mean()) for i in range(0, len(x) - w, w)])
    hot = np.nonzero(20 * np.log10(env + 1e-12) > thr_db)[0]
    k = max(0, (int(hot[0]) - 1) * w) if len(hot) else 0
    return x[k:], k / SR


def fade(x, fin=0.002, fout=0.03):
    x = x.copy()
    a, b = int(fin * SR), int(fout * SR)
    x[:a] *= np.linspace(0, 1, a)[:, None]
    x[-b:] *= np.linspace(1, 0, b)[:, None]
    return x


def encode(x, path, gain_db):
    af = "volume=%.2fdB,alimiter=limit=%.4f:level=false:attack=1:release=40" % (gain_db, 10 ** (CEIL_DB / 20))
    r = subprocess.run([FFMPEG, "-hide_banner", "-nostats", "-v", "error", "-y", "-f", "f32le", "-ar", str(SR), "-ac", str(x.shape[1]),
                        "-i", "-", "-af", af, "-ar", str(SR), "-c:a", "libvorbis", "-q:a", "5", "-map_metadata", "-1", path],
                       input=x.astype("<f4").tobytes(), capture_output=True)
    if r.returncode:
        sys.exit(r.stderr.decode("utf-8", "replace"))


def onsets(x):
    """Attaques (flux spectral, maxima locaux sur 0,3 s) : [(instant en s, force 0..1)]."""
    n, h = 2048, 441
    w = np.hanning(n)
    s = np.log1p(100 * np.array([np.abs(np.fft.rfft(w * x[i:i + n, 0])) for i in range(0, len(x) - n, h)]))
    flux = np.maximum(s[1:] - s[:-1], 0).sum(1)
    flux /= flux.max()
    out = []
    for i in range(len(flux)):
        if flux[i] > 0.25 and flux[i] == flux[max(0, i - 15):i + 16].max():
            out.append((round((i + 1) * h / SR + n / 2 / SR, 2), round(float(flux[i]), 2)))
    return out


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    src, copy = sys.argv[1], (sys.argv[2] if len(sys.argv) > 2 else None)
    os.makedirs(OUT, exist_ok=True)
    for theme, (name, target) in THEMES.items():
        path = os.path.join(src, name)
        st = decode(path, 2)
        st, cut = trim_head(st)
        mono = st.mean(1, keepdims=True)
        side = st[:, 0] - st[:, 1]
        stereo = np.sqrt((side ** 2).mean()) > 0.02 * np.sqrt((mono ** 2).mean())
        todo = [(mono, f"bang_{theme}_enreg.ogg")] + ([(st, f"bang_{theme}_stereo_enreg.ogg")] if stereo else [])
        for x, fname in todo:
            x = fade(x)
            integ, _mom, _pk = measure(x)
            gain = target - integ
            out = os.path.join(OUT, fname)
            for _ in range(4):
                encode(x, out, gain)
                i2, m2, p2 = measure(out, x.shape[1])
                # Jamais plus fort que la cible ni que le bang standard (crête momentanée -7 LUFS), crête sous le plafond.
                over = max(i2 - target, m2 + 7.0, p2 - PEAK_MAX_DB)
                if over <= 0.05:
                    break
                gain -= over + 0.1
            print("%-32s %5.2f s  %s  tête retirée %.3f s  gain %+.1f dB -> %.1f LUFS, momentanée max %.1f LUFS, crête %.2f dBFS"
                  % (fname, len(x) / SR, "stéréo" if x.shape[1] == 2 else "mono", cut, gain, i2, m2, p2))
            if copy:
                shutil.copyfile(out, os.path.join(copy, fname))
        print("   attaques :", onsets(mono))


if __name__ == "__main__":
    main()
