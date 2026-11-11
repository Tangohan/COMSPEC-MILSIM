"""
Sons du mod COMSPEC Flashbang : découpe des enregistrements de TTRD + pièces synthétisées, en OGG Vorbis.

Sources : les 9 captures son_01..son_09 (extraites des vidéos H:\\StreamLabs\\2026-11-11 11-0*.mp4, même ordre).
Analyse (spectrogrammes, détection d'attaques, corrélation croisée) :

  son_01  bang « grave » (son F) + acouphène sinus pur 9956 Hz (ré#9), 1,74-11,32 s
  son_02  bang (son G) + acouphène 4186 Hz (do8) et harmoniques + grondement grave long, 1,08-11,49 s
  son_03  bang seul (son A), 0,92-3,55 s                                  -> bang_unique_a
  son_04  bang seul (son B), 1,05-3,56 s                                  -> bang_unique_b
  son_05  prise ratée : acouphène 1760 Hz très faible (-60 dBFS), 0,66-1,80 s
  son_06  bang (son E) + acouphène 1760 Hz à harmoniques impaires, 0,46-4,96 s
  son_07  manipulation 0,78-2,75 s ; bang A' + acouphène 3520 Hz 3,0-8,0 s ; rafale de 3 bangs (C, A, G) 9,0-12,15 s
          (bang C seul 9,03-10,21 s -> bang_unique_c) ; bang F + acouphène 9956 Hz 12,19-13,41 s
  son_08  manipulation 0,85-3,35 s ; bang A' + acouphène 3520 Hz (la7), 3,80-8,57 s  -> boucle d'acouphène
  son_09  manipulation 0,0-1,45 s ; bang D + acouphène 3520 Hz, 1,67-6,27 s

Fichiers produits (rôle, effet, origine, doutes) : voir LISEZ_MOI plus bas. Suffixe _enreg = enregistrement de TTRD (éventuellement filtré,
découpé, réverbéré) ; _synth = synthétisé ici (numpy), original.

    python gen_fb_sounds.py [dossier_sources] [--drill] [--ecoute H:\\StreamLabs\\ogg]
      --drill   n'écrit que les sons au choix de la DRILL (pop sourd, bang léger, sifflet, cloche)
      --ecoute  copie les fichiers écrits dans le dossier d'écoute et refait son LISEZ-MOI ; les morceaux de
                sources_decoupees\\ déjà présents ne sont jamais réécrits

Sans argument : sources dans le scratchpad (variable SRC). Python 3 + numpy ; ffmpeg (variable FFMPEG ou PATH).
"""
import os
import shutil
import subprocess
import sys

import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.normpath(os.path.join(HERE, "..", "Sources", "addons", "main", "sounds"))
SRC = r"C:\Users\tetar\AppData\Local\Temp\claude\15b436256604086c\26c3cb4c-e895-4719-a463-4064f9ad0b4c\scratchpad\flash_src"
FFMPEG = os.environ.get("FFMPEG") or shutil.which("ffmpeg") or (
    r"C:\Users\tetar\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe"
    r"\ffmpeg-9.0-full_build\bin\ffmpeg.exe")
SR = 44100
PEAK_BANG = -3.0       # crête des bangs (dBFS) : fort mais pas perçant, le volume final est réglé dans la config
TINNITUS_RMS = -26.3   # RMS par canal au départ de l'acouphène : environ -20 LUFS pour un sinus à 3,5 kHz


# ----------------------------------------------------------------------------------------------------- outils
def load(i):
    """son_0i.ogg décodé en float32 stéréo 44,1 kHz."""
    p = os.path.join(SRC, f"son_{i:02d}.ogg")
    raw = subprocess.run([FFMPEG, "-v", "error", "-i", p, "-f", "f32le", "-ac", "2", "-ar", str(SR), "-"],
                         capture_output=True, check=True).stdout
    return np.frombuffer(raw, np.float32).reshape(-1, 2).astype(np.float64)


def decode(path):
    """OGG du mod décodé (canaux d'origine) : sert aux durées du LISEZ-MOI."""
    probe = subprocess.run([FFMPEG, "-v", "error", "-i", path, "-f", "f32le", "-ar", str(SR), "-"],
                           capture_output=True, check=True).stdout
    mono_raw = subprocess.run([FFMPEG, "-v", "error", "-i", path, "-f", "f32le", "-ac", "1", "-ar", str(SR), "-"],
                              capture_output=True, check=True).stdout
    ch = max(1, round(len(probe) / max(1, len(mono_raw))))
    return np.frombuffer(probe, np.float32).reshape(-1, ch)


def cut(x, t0, t1):
    return x[int(round(t0 * SR)):int(round(t1 * SR))].copy()


def mono(x):
    """Canal le plus énergique (le mélange L+R des captures stéréo décorrélées creuse le spectre)."""
    if x.ndim == 1:
        return x
    return x[:, 0] if (x[:, 0] ** 2).sum() >= (x[:, 1] ** 2).sum() else x[:, 1]


def fade(x, fin=0.003, fout=0.2):
    x = x.copy()
    n_in, n_out = int(fin * SR), int(fout * SR)
    if n_in:
        x[:n_in] *= (0.5 - 0.5 * np.cos(np.linspace(0, np.pi, n_in)))[:, None] if x.ndim == 2 else \
            0.5 - 0.5 * np.cos(np.linspace(0, np.pi, n_in))
    if n_out:
        r = 0.5 + 0.5 * np.cos(np.linspace(0, np.pi, n_out))
        x[-n_out:] *= r[:, None] if x.ndim == 2 else r
    return x


def filt(x, lp=None, hp=None, order=4, notch=()):
    """Filtre à phase nulle dans le domaine fréquentiel (réponse de type Butterworth), encoches étroites."""
    n = len(x)
    pad = 1 << int(np.ceil(np.log2(n + SR)))
    f = np.fft.rfftfreq(pad, 1 / SR)
    h = np.ones_like(f)
    if lp:
        h /= np.sqrt(1 + (f / lp) ** (2 * order))
    if hp:
        h *= 1 / np.sqrt(1 + (hp / np.maximum(f, 1e-3)) ** (2 * order))
    for fc in notch:
        h *= 1 - np.exp(-0.5 * ((f - fc) / 6.0) ** 2)
    X = np.fft.rfft(x, pad, axis=0)
    y = np.fft.irfft(X * (h[:, None] if x.ndim == 2 else h), pad, axis=0)
    return y[:n]


def norm_peak(x, db):
    return x * (10 ** (db / 20) / (np.abs(x).max() + 1e-12))


def convolve(x, ir):
    n = len(x) + len(ir) - 1
    pad = 1 << int(np.ceil(np.log2(n)))
    return np.fft.irfft(np.fft.rfft(x, pad) * np.fft.rfft(ir, pad), pad)[:n]


def room_ir(rt60, length, seed, early=(), lp=None):
    """Réponse impulsionnelle synthétique : bruit à décroissance exponentielle + réflexions discrètes."""
    rng = np.random.default_rng(seed)
    t = np.arange(int(length * SR)) / SR
    ir = rng.standard_normal(len(t)) * np.exp(-6.91 * t / rt60) * 0.25
    ir[: int(0.004 * SR)] = 0
    for dt, g in early:
        ir[int(dt * SR)] += g
    ir[0] = 1.0
    if lp:
        ir = filt(ir, lp=lp, order=2)
    return ir


def write_ogg(path, x):
    """Écrit un OGG Vorbis (qualité 5) ; x mono (N) ou stéréo (N, 2)."""
    ch = 1 if x.ndim == 1 else 2
    data = np.clip(x, -1, 1).astype(np.float32).tobytes()
    subprocess.run([FFMPEG, "-v", "error", "-y", "-f", "f32le", "-ar", str(SR), "-ac", str(ch), "-i", "-",
                    "-c:a", "libvorbis", "-q:a", "5", path], input=data, check=True)


# ----------------------------------------------------------------------------------------- pièces synthétisées
def tinnitus_env(t):
    """Décroissance réaliste de l'acouphène : rapide les premières secondes, puis lente ; silence à 60 s."""
    a = 0.6 * np.exp(-t / 2.5) + 0.4 * np.exp(-t / 20.0)
    taper = np.where(t > 48, 0.5 + 0.5 * np.cos(np.pi * np.clip((t - 48) / 12, 0, 1)), 1.0)
    return a * taper


def synth_tinnitus(dur):
    """Acouphène synthétique : la7 (3520 Hz) + partenaire à 3526 Hz (battement 6 Hz), légère 2e harmonique,
    bande de bruit étroite ; canal droit décalé de 1 Hz (sensation « dans la tête »)."""
    rng = np.random.default_rng(7)
    t = np.arange(int(dur * SR)) / SR
    out = []
    for df in (0.0, 1.0):
        s = (np.sin(2 * np.pi * (3520 + df) * t) + 0.45 * np.sin(2 * np.pi * (3526 + df) * t + 1.3)
             + 0.06 * np.sin(2 * np.pi * (7040 + 2 * df) * t))
        nz = filt(rng.standard_normal(len(t)), lp=3800, hp=3250, order=6) * 0.35
        out.append(s + nz)
    return np.stack(out, 1)


def synth_pop():
    """Pétard d'entraînement : craquement bref, corps grave court, souffle de fumée léger (0,5 s)."""
    rng = np.random.default_rng(11)
    n = int(0.5 * SR)
    t = np.arange(n) / SR
    crack = filt(rng.standard_normal(n), lp=7000, hp=1200, order=2) * np.exp(-t / 0.012)
    body = np.sin(2 * np.pi * (190 - 60 * t) * t) * np.exp(-t / 0.045) * 0.8
    hiss = filt(rng.standard_normal(n), lp=6000, hp=2500, order=2) * 0.05 * np.clip(t / 0.03, 0, 1) * np.exp(-t / 0.18)
    x = crack * 1.0 + body + hiss
    x[:3] = [0.0, 0.9, -0.6]
    return fade(x, 0.0005, 0.05)


def synth_lever():
    """Cuillère qui saute (tintement métallique inharmonique qui tourne) + clic du percuteur (0,4 s)."""
    rng = np.random.default_rng(23)
    n = int(0.4 * SR)
    t = np.arange(n) / SR
    x = np.zeros(n)
    rel = filt(rng.standard_normal(n), hp=2500, order=2) * np.exp(-t / 0.004) * 0.6      # levier libéré
    x += rel
    for f0, d, g in ((2380, 0.11, 0.5), (3910, 0.07, 0.4), (6120, 0.045, 0.3), (8470, 0.03, 0.2)):
        trem = 1 + 0.35 * np.sin(2 * np.pi * 22 * t)                                       # rotation de la cuillère
        x += g * np.sin(2 * np.pi * f0 * t + rng.uniform(0, 6.28)) * np.exp(-t / d) * trem
    k = int(0.035 * SR)                                                                  # clic du percuteur
    m = n - k
    tc = np.arange(m) / SR
    x[k:] += filt(rng.standard_normal(m), hp=1800, lp=9000, order=2) * np.exp(-tc / 0.003) * 0.9
    x[k:] += 0.25 * np.sin(2 * np.pi * 1450 * tc) * np.exp(-tc / 0.02)
    return fade(x, 0.0005, 0.08)


def synth_pop_muffled():
    """« Pop sourd » de la DRILL : même pétard, plus grave et étouffé (passe-bas 900 Hz), petite queue de pièce (0,7 s)."""
    rng = np.random.default_rng(13)
    n = int(0.7 * SR)
    t = np.arange(n) / SR
    crack = filt(rng.standard_normal(n), lp=2500, hp=300, order=2) * np.exp(-t / 0.015) * 0.6
    body = np.sin(2 * np.pi * (120 - 40 * t) * t) * np.exp(-t / 0.07)
    x = filt(crack + body, lp=900, order=3)
    ir = room_ir(0.35, 0.5, 17, early=((0.009, 0.4), (0.016, 0.25)), lp=1800)
    return fade(convolve(x, ir)[:n], 0.001, 0.15)


def synth_whistle():
    """Sifflet d'exercice (sifflet à bille) : porteuse ~3,1 kHz trillée par la bille (28 Hz), souffle ; 0,8 s."""
    rng = np.random.default_rng(29)
    n = int(0.8 * SR)
    t = np.arange(n) / SR
    trill = 1 + 0.035 * np.sign(np.sin(2 * np.pi * 28 * t)) * (0.6 + 0.4 * np.sin(2 * np.pi * 3 * t))
    f = 3100 * filt(trill, lp=180, order=2)
    phase = 2 * np.pi * np.cumsum(f) / SR
    tone = np.sin(phase) + 0.18 * np.sin(2 * phase) + 0.05 * np.sin(3 * phase)
    breath = filt(rng.standard_normal(n), lp=7000, hp=2000, order=2) * 0.12
    env = np.clip(t / 0.025, 0, 1) * np.clip((0.8 - t) / 0.09, 0, 1)
    return fade((tone + breath) * env, 0.002, 0.03)


def synth_bell():
    """Cloche d'exercice : partiels inharmoniques de cloche (880 Hz), décroissances différentes ; 1,6 s."""
    n = int(1.6 * SR)
    t = np.arange(n) / SR
    x = np.zeros(n)
    for ratio, g, d in ((0.5, 0.35, 1.2), (1.0, 1.0, 0.9), (1.19, 0.45, 0.6), (1.5, 0.35, 0.5), (2.0, 0.5, 0.45),
                        (2.74, 0.25, 0.3), (3.76, 0.15, 0.2)):
        x += g * np.sin(2 * np.pi * 880 * ratio * t) * np.exp(-t / d)
    strike = np.random.default_rng(31).standard_normal(n) * np.exp(-t / 0.004) * 0.4
    return fade(x + filt(strike, hp=2000, order=2), 0.0005, 0.25)


def drill_sounds(X):
    """Sons au choix de la grenade DRILL (réglage « Son de la grenade d'entraînement »), en plus de pop_drill_synth."""
    out = {}
    out["pop_sourd_synth"] = norm_peak(synth_pop_muffled(), PEAK_BANG)
    # Bang léger : bang C (son_07, 9,02 s), queue raccourcie à 0,8 s, grave allégé (passe-haut 180 Hz) ; le volume
    # plus faible est donné par la table CfgSons.hpp.
    light = filt(mono(cut(X[7], 9.020, 9.820)), hp=180, order=2)
    out["bang_leger_enreg"] = norm_peak(fade(light, 0.002, 0.35), PEAK_BANG)
    out["sifflet_exercice_synth"] = norm_peak(synth_whistle(), -6.0)
    out["cloche_exercice_synth"] = norm_peak(synth_bell(), -6.0)
    return out


# ----------------------------------------------------------------------------------------- découpe des captures
def loop_from_recording(x8):
    """Boucle de 3 s de l'acouphène 3520 Hz de son_08 (4,60-7,60 s) : passe-haut pour ôter la queue du bang,
    enveloppe aplatie, longueur multiple de 2205 échantillons (= 176 périodes exactes de 3520 Hz à 44,1 kHz)."""
    L = 60 * 2205
    seg = cut(x8, 4.60, 4.60 + L / SR)
    seg = filt(seg, hp=2000, order=6)
    w = 2205
    # RMS par fenêtre de 2205 échantillons (périodes entières : pas d'ondulation), lissé sur 3 fenêtres.
    rms = np.sqrt((seg.reshape(-1, w, 2) ** 2).mean(axis=(1, 2)))
    rms = np.convolve(np.pad(rms, 1, mode="edge"), np.ones(3) / 3, mode="valid")
    centers = np.arange(len(rms)) * w + w / 2
    env_s = np.interp(np.arange(L), centers, rms)
    loop = seg / env_s[:, None]
    loop *= 10 ** (TINNITUS_RMS / 20) / np.sqrt((loop ** 2).mean())
    return loop


def main():
    global SRC
    args = sys.argv[1:]
    ecoute = None
    if "--ecoute" in args:
        i = args.index("--ecoute")
        ecoute = args[i + 1]
        del args[i:i + 2]
    only_drill = "--drill" in args
    if only_drill:
        args.remove("--drill")
    if args:
        SRC = args[0]
    os.makedirs(OUT, exist_ok=True)
    X = {i: load(i) for i in range(1, 10)}
    files = {}

    # Bangs isolés (rejoués N fois par le code, un par flash).
    files["bang_unique_a_enreg"] = norm_peak(fade(mono(cut(X[3], 0.967, 3.367)), 0.002, 0.45), PEAK_BANG)
    # Bang B : trace d'acouphène 4186 Hz dans la capture, retirée par encoches étroites.
    bang_b = filt(mono(cut(X[4], 1.077, 3.477)), notch=(4186.0, 8372.0, 12558.0))
    files["bang_unique_b_enreg"] = norm_peak(fade(bang_b, 0.002, 0.45), PEAK_BANG)
    files["bang_unique_c_enreg"] = norm_peak(fade(mono(cut(X[7], 9.020, 10.215)), 0.002, 0.30), PEAK_BANG)
    # Couche grave du premier bang : bang F (son_01) en passe-bas 160 Hz (l'acouphène 9956 Hz disparaît).
    thump = filt(mono(cut(X[1], 1.764, 2.764)), lp=160, order=4)
    files["bang_grave_enreg"] = norm_peak(fade(thump, 0.004, 0.35), PEAK_BANG)
    # Rafale enregistrée (3 bangs à ~1,15 s, son_07 9,0-12,15 s), encoches sur l'acouphène 4186 Hz naissant.
    raf = filt(mono(cut(X[7], 9.020, 12.150)), notch=(4186.0, 8372.0, 12558.0))
    files["bang_rafale_enreg"] = norm_peak(fade(raf, 0.002, 0.15), PEAK_BANG)
    # Derrière un mur / pièce voisine : bang A filtré (passe-bas 450 Hz) + réverbération de pièce RT60 0,6 s.
    a = mono(cut(X[3], 0.967, 3.467))
    ir = room_ir(0.6, 0.9, 3, early=((0.011, 0.5), (0.019, 0.35), (0.031, 0.25)), lp=2500)
    wet = convolve(filt(a, lp=450, order=3), ir)[: int(2.8 * SR)]
    files["bang_interieur_etouffe_enreg"] = norm_peak(fade(wet, 0.003, 0.6), PEAK_BANG)
    # Lointain (> 150 m dehors) : bang B, absorption de l'air (passe-bas 1,1 kHz), échos de façades et queue longue.
    b = bang_b
    ir = room_ir(2.2, 3.2, 5, early=((0.38, 0.32), (0.91, 0.18), (1.6, 0.09)), lp=1500)
    far = convolve(filt(b, lp=1100, order=2), ir)[: int(3.8 * SR)]
    files["bang_lointain_enreg"] = norm_peak(fade(far, 0.01, 1.0), PEAK_BANG)

    # Acouphène : boucle enregistrée (son_08, 3520 Hz) et décroissance 60 s (même enveloppe pour les deux versions).
    loop = loop_from_recording(X[8])
    files["acouphene_boucle_enreg"] = loop
    dur = 60.0
    t = np.arange(int(dur * SR)) / SR
    env = tinnitus_env(t)[:, None]
    tiled = np.tile(loop, (int(np.ceil(len(t) / len(loop))), 1))[: len(t)]
    files["acouphene_decroissance_enreg"] = fade(tiled * env, 0.02, 0.0)
    syn = synth_tinnitus(dur)
    syn *= 10 ** (TINNITUS_RMS / 20) / np.sqrt((syn[: SR] ** 2).mean())
    files["acouphene_decroissance_synth"] = fade(syn * env, 0.02, 0.0)

    # Drill et lancer : synthétisés (aucune prise propre dans les captures).
    files["pop_drill_synth"] = norm_peak(synth_pop(), PEAK_BANG)
    files["levier_cuillere_synth"] = norm_peak(synth_lever(), -6.0)
    drill = drill_sounds(X)
    files.update(drill)
    if only_drill:
        # --drill : n'écrit que les sons au choix de la DRILL (les autres fichiers du mod restent tels quels).
        files = drill

    for name, x in files.items():
        p = os.path.join(OUT, name + ".ogg")
        write_ogg(p, x)
        print(f"{name:32s} {len(x) / SR:6.2f} s  {'stéréo' if x.ndim == 2 else 'mono  '}  crête "
              f"{20 * np.log10(np.abs(x).max() + 1e-12):6.1f} dBFS")

    # Courbe de niveau de la décroissance (dB RMS par seconde) : sert au calcul intensité -> offset dans le code.
    if "acouphene_decroissance_enreg" in files:
        d = files["acouphene_decroissance_enreg"]
        print("décroissance (dB RMS, fenêtre 1 s) :")
        for s in (0, 1, 2, 3, 5, 8, 12, 20, 30, 40, 45, 50, 55, 59):
            w = d[s * SR:(s + 1) * SR]
            print(f"  t={s:2d}s  {20 * np.log10(np.sqrt((w ** 2).mean()) + 1e-12):6.1f} dBFS")

    if ecoute:
        os.makedirs(ecoute, exist_ok=True)
        for name in files:
            shutil.copy2(os.path.join(OUT, name + ".ogg"), os.path.join(ecoute, name + ".ogg"))
        # Morceaux découpés : écrits une seule fois, jamais réécrits ensuite (TTRD peut les avoir rangés ou annotés).
        dec = os.path.join(ecoute, "sources_decoupees")
        os.makedirs(dec, exist_ok=True)
        for v, t0, t1, what in SEGMENTS:
            m, s = divmod(int(t0), 60)
            seg = os.path.join(dec, f"video{v:02d}_{m:02d}m{s:02d}s_{what}.ogg")
            if not os.path.exists(seg):
                write_ogg(seg, fade(cut(X[v], t0, t1), 0.005, 0.03))
        lines = [LISEZ_MOI.strip(), "", "Durées des fichiers du mod :"]
        for name in sorted(f[:-4] for f in os.listdir(OUT) if f.endswith(".ogg")):
            x = decode(os.path.join(OUT, name + ".ogg"))
            lines.append(f"  {name}.ogg : {len(x) / SR:.2f} s, {'stéréo' if x.shape[1] == 2 else 'mono'}")
        with open(os.path.join(ecoute, "LISEZ-MOI.txt"), "w", encoding="utf-8-sig", newline="\r\n") as fh:
            fh.write("\n".join(lines) + "\n")
        print("copie d'écoute :", ecoute)


LISEZ_MOI = """
SONS DU MOD COMSPEC FLASHBANG - fichiers à écouter

Ce dossier contient les sons finaux du mod (mêmes noms que dans le mod) et, dans sources_decoupees\\, chaque
morceau extrait de tes 9 vidéos du 11/11/2026, nommé d'après ce que l'analyse y a trouvé.
Suffixe _enreg = ton enregistrement (découpé, filtré ou réverbéré) ; _synth = son synthétisé par le script.
Les vidéos et les mp3 de H:\\StreamLabs n'ont pas été modifiés.

Comment le jeu les utilise :
- bang_unique_a / b / c : UN bang chacun. Il est rejoué N fois pour une grenade à N flashs (un par flash), tiré au
  sort sans répéter deux fois de suite le même, avec une petite variation de hauteur et de volume. Les lectures se
  chevauchent (la queue d'un bang continue pendant le suivant).
- bang_grave : joué en plus, en même temps que le PREMIER bang seulement.
- acouphene_decroissance : joué UNE SEULE fois par joueur (pas N fois). Chaque flash suivant le relance plus tôt dans
  le fichier (plus fort, plus long), avec un plafond.

FICHIER | CE QUE C'EST | EFFET QUI L'UTILISE | ORIGINE | DOUTE
bang_unique_a_enreg.ogg | bang seul, sa queue naturelle | chaque flash (rejoué N fois) | vidéo 03 (11-05-21) à 0,97 s | aucun : bang net sans acouphène
bang_unique_b_enreg.ogg | bang seul (autre son que a) | chaque flash (rejoué N fois) | vidéo 04 (11-05-40) à 1,08 s, trace de bip 4186 Hz retirée | faible
bang_unique_c_enreg.ogg | bang seul (3e son), queue courte | chaque flash (rejoué N fois) | vidéo 07 (11-06-29) à 9,03 s, coupé avant le bang suivant | queue coupée à 1,2 s
bang_grave_enreg.ogg | grave du bang (thump) | 1er flash seulement, en couche | vidéo 01 (11-04-28) à 1,77 s, passe-bas 160 Hz | à écouter : grave peut-être faible
bang_rafale_enreg.ogg | 3 bangs enchaînés (~1,15 s d'écart) | DRILL, son au choix « Rafale courte » | vidéo 07 à 9,0-12,15 s | ce sont 3 sons différents mis bout à bout dans ta capture
bang_interieur_etouffe_enreg.ogg | bang entendu derrière un mur / pièce voisine | flash entendu à travers un mur | bang a + passe-bas 450 Hz + réverbération de pièce (traitement) | rendu à valider à l'oreille
bang_lointain_enreg.ogg | bang lointain avec échos | extérieur au-delà de ~150 m | bang b + passe-bas 1,1 kHz + échos (traitement) | rendu à valider à l'oreille
acouphene_boucle_enreg.ogg | sifflement 3520 Hz (note la7) en boucle de 3 s, sans clic | réserve / acouphène continu | vidéo 08 (11-06-48) 4,6-7,6 s, niveau aplani | c'est un bip pur électronique
acouphene_decroissance_enreg.ogg | sifflement 3520 Hz de 60 s qui décroît (vite puis lentement) | acouphène (1 fois par joueur) | ta boucle 3520 Hz + enveloppe de décroissance calculée | aucun sur l'identification
acouphene_decroissance_synth.ogg | même chose, synthétisé : 3520 + 3526 Hz (battement), canal droit +1 Hz | variante plus « naturelle » | synthèse | au choix de TTRD
pop_drill_synth.ogg | petit pétard + souffle | grenade DRILL, son « Pop » (défaut) | synthèse (pas de prise propre) | -
pop_sourd_synth.ogg | pétard plus grave, étouffé, petite queue de pièce | grenade DRILL, son « Pop sourd » | synthèse | -
bang_leger_enreg.ogg | bang C raccourci (0,8 s), grave allégé | grenade DRILL, son « Bang léger » | vidéo 07 à 9,02 s + passe-haut 180 Hz | à écouter
sifflet_exercice_synth.ogg | sifflet à bille (~3,1 kHz trillé), 0,8 s | grenade DRILL, son « Sifflet d'exercice » | synthèse | -
cloche_exercice_synth.ogg | coup de cloche (880 Hz, partiels de cloche), 1,6 s | grenade DRILL, son « Cloche d'exercice » | synthèse | -
levier_cuillere_synth.ogg | cuillère qui saute + clic du percuteur | au lancer | synthèse (les bruits de manipulation des vidéos ne sont pas exploitables) | -

SON DE LA GRENADE DRILL (réglage d'addon « Son de la grenade d'entraînement », ou choix dans le module Eden / Zeus) :
  Pop (défaut) = pop_drill_synth ; Pop sourd = pop_sourd_synth ; Bang léger = bang_leger_enreg ;
  Bang réel = bang_unique_a / b / c (le bang d'une vraie grenade, sans aucun effet) ; Rafale courte = bang_rafale_enreg ;
  Sifflet d'exercice = sifflet_exercice_synth ; Cloche d'exercice = cloche_exercice_synth ; Aucun son = fumée seule.
  Le son est joué une fois par pop (1 à 9), chaque lecture indépendante, avec la même petite variation que les bangs.

SOURCES DÉCOUPÉES (sources_decoupees\\) - ce que l'analyse a trouvé dans chaque vidéo :
video01 (11-04-28) : bang grave + acouphène bip pur 9956 Hz (ré#9) qui décroît en ~9 s.
video02 (11-05-02) : bang + acouphène 4186 Hz (do8) avec harmoniques + grondement grave qui dure ~10 s.
video03 (11-05-21) : bang seul, sans acouphène.
video04 (11-05-40) : bang seul (autre son), sans acouphène (à peine une trace de bip).
video05 (11-06-03) : prise ratée : seulement un acouphène 1760 Hz très faible (-60 dBFS).
video06 (11-06-07) : bang + acouphène 1760 Hz à harmoniques impaires (son « carré »), ~4,5 s.
video07 (11-06-29) : bruits de manipulation, bang + acouphène 3520 Hz, puis rafale de 3 bangs, puis bang + 9956 Hz.
video08 (11-06-48) : bruits de manipulation, puis bang + acouphène 3520 Hz (~5 s).
video09 (11-06-59) : bruits de manipulation, puis bang + acouphène 3520 Hz (~4,5 s).

À ÉCOUTER EN PRIORITÉ (identifiés par analyse seulement, sans écoute) :
- les « manipulation_avant_lancer » (vidéos 07, 08, 09) : cliquetis et froissements avant le bang ; dégoupillage,
  équipement ou autre bruit du jeu ? Non utilisés dans le mod.
- bang_grave_enreg : vérifier qu'il apporte bien un « boum » grave.
- bang_interieur_etouffe_enreg et bang_lointain_enreg : rendus obtenus par traitement, à valider.
- choix de l'acouphène : _enreg (bip pur 3520 Hz de ta vidéo) ou _synth (battement plus organique).

PROVENANCE À CONFIRMER : les acouphènes enregistrés sont des bips électroniques purs sur des notes de musique exactes
(1760, 3520, 4186, 9956 Hz) et le fond est un silence numérique parfait : ce sont des sons d'un jeu ou d'un mod
capturés à l'écran, pas une prise au micro. Si ces sons viennent d'un autre mod, leur réutilisation est ta décision.
"""


# Segments extraits des 9 vidéos (stéréo, tels quels, fondus de 5 ms) pour l'écoute de TTRD.
SEGMENTS = [
    (1, 1.70, 11.35, "bang_puis_acouphene_9956Hz"),
    (2, 1.05, 11.50, "bang_puis_acouphene_4186Hz_et_grondement"),
    (3, 0.90, 3.56, "bang_seul"),
    (4, 1.00, 3.56, "bang_seul"),
    (5, 0.60, 1.82, "acouphene_tres_faible_prise_ratee"),
    (6, 0.44, 4.98, "bang_puis_acouphene_1760Hz"),
    (7, 0.75, 2.78, "manipulation_avant_lancer"),
    (7, 3.10, 7.95, "bang_puis_acouphene_3520Hz"),
    (7, 9.00, 12.15, "rafale_3_bangs"),
    (7, 12.15, 13.42, "bang_puis_acouphene_9956Hz_coupe"),
    (8, 0.82, 3.36, "manipulation_avant_lancer"),
    (8, 3.76, 8.58, "bang_puis_acouphene_3520Hz"),
    (9, 0.00, 1.46, "manipulation_avant_lancer"),
    (9, 1.62, 6.28, "bang_puis_acouphene_3520Hz"),
]

if __name__ == "__main__":
    main()
