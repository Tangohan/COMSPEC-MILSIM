"""
Textures des pylônes relais (model_relay_towers.py) : motifs raccordables (tuilés en jeu, projection monde 1 m = 1 tuile
sauf indication), tirés d'un bruit périodique (FFT) pour qu'aucune couture ne se voie.

  tower_galva   acier galvanisé à chaud : fleurs de zinc, marbrure, coulures grises et quelques piqûres de rouille
  tower_dark    acier peint gris anthracite patiné (pylône de 30 m)
  tower_radome  radôme de panneau d'antenne : plastique blanc cassé, coulures verticales de crasse
  tower_box     coffret / RRU : tôle peinte gris clair, légère poussière
  tower_concrete massif en béton : pores, laitance, salissures
  tower_grating caillebotis galvanisé (maille 33 mm : 1 m = 30 mailles)
  tower_cable   gaine de câble noire
  tower_light   feu de balisage rouge

    python3 gen_tower_textures.py      # écrit render/tower_*_co.png
    puis  hemtt utils paa convert render/tower_X_co.png ../Sources/addons/towers/data/tower_X_co.paa
"""
import os

import numpy as np
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
RENDER = os.path.join(HERE, "render")
N = 1024
rng = np.random.default_rng(1977)


def noise(beta=2.0, size=N, aniso=(1.0, 1.0)):
    """Bruit périodique en 1/f^beta, normalisé 0..1. aniso étire les fréquences (x, y)."""
    fx = np.fft.fftfreq(size)[None, :] * size * aniso[0]
    fy = np.fft.fftfreq(size)[:, None] * size * aniso[1]
    f = np.sqrt(fx * fx + fy * fy)
    f[0, 0] = 1.0
    spec = (rng.normal(size=(size, size)) + 1j * rng.normal(size=(size, size))) / f ** (beta / 2)
    spec[0, 0] = 0
    out = np.real(np.fft.ifft2(spec))
    out -= out.min()
    return out / out.max()


def band(lo, hi, size=N):
    """Bruit limité à une bande de fréquences (en cycles par tuile)."""
    fx = np.fft.fftfreq(size)[None, :] * size
    fy = np.fft.fftfreq(size)[:, None] * size
    f = np.sqrt(fx * fx + fy * fy)
    spec = (rng.normal(size=(size, size)) + 1j * rng.normal(size=(size, size))) * ((f >= lo) & (f <= hi))
    out = np.real(np.fft.ifft2(spec))
    out -= out.min()
    return out / out.max()


def spangle(cells=14):
    """Fleurs de zinc : cellules de Voronoï périodiques (cells x cells germes jittés), une teinte par cellule."""
    g = cells
    pts = (np.stack(np.meshgrid(np.arange(g), np.arange(g)), -1).reshape(-1, 2) + rng.random((g * g, 2))) / g
    val = rng.random(g * g)
    ys, xs = np.mgrid[0:N, 0:N] / N
    best = np.full((N, N), 9.0)
    second = np.full((N, N), 9.0)
    idx = np.zeros((N, N), dtype=int)
    for i, (px, py) in enumerate(pts):
        dx = np.abs(xs - px)
        dx = np.minimum(dx, 1 - dx)
        dy = np.abs(ys - py)
        dy = np.minimum(dy, 1 - dy)
        d = dx * dx + dy * dy
        closer = d < best
        second = np.where(closer, best, np.minimum(second, d))
        idx = np.where(closer, i, idx)
        best = np.where(closer, d, best)
    edge = np.sqrt(second) - np.sqrt(best)
    return val[idx], np.clip(edge * g * 6, 0, 1)


def save(name, rgb):
    rgb = np.clip(rgb, 0, 1)
    os.makedirs(RENDER, exist_ok=True)
    Image.fromarray((rgb * 255 + 0.5).astype(np.uint8), "RGB").save(os.path.join(RENDER, name + "_co.png"))
    print("texture", name)


def tint(gray, color):
    return gray[..., None] * np.array(color)[None, None, :]


def streaks(strength=1.0):
    """Coulures verticales (eau de pluie) : bruit très étiré en hauteur."""
    s = noise(1.6, aniso=(0.06, 1.0))
    return np.clip((s - 0.55) * 3.0, 0, 1) * strength


def galva():
    sp, edge = spangle(34)
    mott = noise(2.2)
    fine = noise(0.6)
    g = 0.60 + 0.06 * (sp - 0.5) + 0.10 * (mott - 0.5) + 0.04 * (fine - 0.5) - 0.03 * (1 - edge)
    g -= 0.10 * streaks()
    rgb = tint(g, (1.0, 1.01, 1.03))
    # Piqûres et voiles de rouille clairsemés.
    rust = np.clip((noise(1.2) - 0.80) * 6, 0, 1) * np.clip((fine - 0.45) * 4, 0, 1)
    rgb = rgb * (1 - 0.6 * rust[..., None]) + 0.6 * rust[..., None] * np.array([0.42, 0.25, 0.14])
    save("tower_galva", rgb)


def dark():
    mott = noise(2.0)
    fine = noise(0.7)
    g = 0.22 + 0.05 * (mott - 0.5) + 0.03 * (fine - 0.5)
    rgb = tint(g, (1.0, 1.02, 1.05))
    # Peinture farinée (voile clair) et coulures, rouille aux défauts.
    chalk = np.clip((noise(1.8) - 0.5) * 1.5, 0, 1)
    rgb += 0.06 * chalk[..., None]
    rgb -= 0.05 * streaks()[..., None]
    rust = np.clip((noise(1.1) - 0.78) * 5, 0, 1) * np.clip((fine - 0.5) * 4, 0, 1)
    rgb = rgb * (1 - 0.7 * rust[..., None]) + 0.7 * rust[..., None] * np.array([0.30, 0.17, 0.09])
    save("tower_dark", rgb)


def radome():
    mott = noise(2.4)
    fine = noise(0.5)
    g = 0.86 + 0.03 * (mott - 0.5) + 0.015 * (fine - 0.5)
    rgb = tint(g, (1.0, 0.995, 0.975))
    # Crasse : coulures verticales grises et voile de pollution plus marqué par endroits.
    st = streaks(1.0)
    rgb -= (0.16 * st)[..., None] * np.array([1.0, 1.0, 0.95])
    grime = np.clip((noise(2.0, aniso=(0.3, 1.0)) - 0.5) * 1.2, 0, 1)
    rgb -= (0.08 * grime)[..., None]
    save("tower_radome", rgb)


def box():
    mott = noise(2.2)
    fine = noise(0.6)
    g = 0.70 + 0.03 * (mott - 0.5) + 0.02 * (fine - 0.5)
    rgb = tint(g, (0.98, 1.0, 1.0))
    rgb -= (0.10 * streaks())[..., None]
    dust = np.clip((noise(1.6) - 0.55) * 1.5, 0, 1)
    rgb = rgb * (1 - 0.15 * dust[..., None]) + 0.15 * dust[..., None] * np.array([0.55, 0.50, 0.42])
    save("tower_box", rgb)


def concrete():
    mott = noise(2.0)
    mid = band(40, 160)
    fine = noise(0.3)
    g = 0.56 + 0.07 * (mott - 0.5) + 0.05 * (mid - 0.5) + 0.06 * (fine - 0.5)
    pores = (fine < 0.12) & (noise(0.4) < 0.35)
    g = np.where(pores, g * 0.55, g)
    rgb = tint(g, (1.0, 0.98, 0.94))
    dirt = np.clip((noise(1.8) - 0.5) * 1.6, 0, 1)
    rgb = rgb * (1 - 0.25 * dirt[..., None]) + 0.25 * dirt[..., None] * np.array([0.33, 0.30, 0.24])
    save("tower_concrete", rgb)


def grating():
    """Caillebotis : 30 x 30 mailles par tuile, lames porteuses claires, vides sombres (sans transparence)."""
    ys, xs = np.mgrid[0:N, 0:N]
    cell = N / 30
    bar_x = (xs % cell) < cell * 0.16
    bar_y = (ys % cell) < cell * 0.10
    base = np.where(bar_x | bar_y, 0.62, 0.10) + 0.05 * (noise(1.5) - 0.5)
    rgb = tint(base, (1.0, 1.01, 1.03))
    save("tower_grating", rgb)


def light():
    """Feu de balisage : rouge saturé (64 px, émissif par tower_glow.rvmat)."""
    Image.new("RGB", (64, 64), (235, 22, 12)).save(os.path.join(RENDER, "tower_light_co.png"))
    print("texture tower_light")


def cable():
    g = 0.06 + 0.02 * (noise(1.0) - 0.5)
    save("tower_cable", tint(g, (1.0, 1.0, 1.0)))


if __name__ == "__main__":
    galva()
    dark()
    radome()
    box()
    concrete()
    grating()
    cable()
    light()
