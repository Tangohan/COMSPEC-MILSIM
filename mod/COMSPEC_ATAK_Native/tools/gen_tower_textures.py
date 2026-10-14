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
  tower_olive   acier peint vert olive militaire (armoires, pylône de 30 m) : farinage, coulures, éclats, rouille
  tower_sign    panneaux (512 x 256, UV propres) : danger électrique / RF, identifiant du site, consignes
  tower_fence   grillage simple torsion de l'enclos (_ca, transparence)
  tower_net     filet de camouflage (_ca, transparence) : lanières vert / brun / sable sur maille
Pour chacun (sauf câble et feu) : _nohq (normales) et _smdi (reflets) du shader Super (rvmat tower_<nom>.rvmat).

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


def maps(name, height, strength, spec, gloss):
    """Cartes du shader Super d'Arma : _nohq (normales tirées du relief, raccordables) et _smdi (vert : intensité
    du reflet, bleu : brillance ; rouge à 1)."""
    dx = (np.roll(height, -1, 1) - np.roll(height, 1, 1)) * strength
    dy = (np.roll(height, -1, 0) - np.roll(height, 1, 0)) * strength
    n = np.stack([-dx, dy, np.ones_like(dx)], -1)
    n /= np.linalg.norm(n, axis=-1, keepdims=True)
    Image.fromarray((n * 0.5 + 0.5).clip(0, 1).__mul__(255).astype(np.uint8), "RGB").save(os.path.join(RENDER, name + "_nohq.png"))
    sm = np.stack([np.ones_like(spec), np.clip(spec, 0, 1), np.clip(gloss, 0, 1) * np.ones_like(spec)], -1)
    Image.fromarray((sm * 255).astype(np.uint8), "RGB").save(os.path.join(RENDER, name + "_smdi.png"))


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
    # Zinc terni (oxyde blanchâtre, mat) par plaques.
    dull = np.clip((noise(1.9) - 0.45) * 2.0, 0, 1)
    rgb = rgb * (1 - 0.12 * dull[..., None]) + 0.12 * dull[..., None] * np.array([0.78, 0.79, 0.78])
    save("tower_galva", rgb)
    maps("tower_galva", 0.6 * fine + 0.4 * edge + 2.0 * rust, 6.0, 0.55 - 0.25 * dull - 0.45 * rust + 0.1 * (sp - 0.5), 0.45 - 0.2 * dull)


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
    maps("tower_dark", noise(0.9) + 2.5 * rust, 5.0, 0.32 - 0.18 * chalk - 0.3 * rust, 0.4 - 0.15 * chalk)


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
    maps("tower_radome", fine, 2.0, 0.22 - 0.15 * st - 0.08 * grime, 0.35 * np.ones_like(st))


def box():
    mott = noise(2.2)
    fine = noise(0.6)
    g = 0.70 + 0.03 * (mott - 0.5) + 0.02 * (fine - 0.5)
    rgb = tint(g, (0.98, 1.0, 1.0))
    rgb -= (0.10 * streaks())[..., None]
    dust = np.clip((noise(1.6) - 0.55) * 1.5, 0, 1)
    rgb = rgb * (1 - 0.15 * dust[..., None]) + 0.15 * dust[..., None] * np.array([0.55, 0.50, 0.42])
    save("tower_box", rgb)
    maps("tower_box", fine, 2.5, 0.35 - 0.2 * dust, 0.45 * np.ones_like(dust))


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
    maps("tower_concrete", 0.5 * mid + 0.8 * fine - 1.5 * pores, 8.0, 0.06 * np.ones_like(fine), 0.12 * np.ones_like(fine))


def grating():
    """Caillebotis : 30 x 30 mailles par tuile, lames porteuses claires, vides sombres (sans transparence)."""
    ys, xs = np.mgrid[0:N, 0:N]
    cell = N / 30
    bar_x = (xs % cell) < cell * 0.16
    bar_y = (ys % cell) < cell * 0.10
    base = np.where(bar_x | bar_y, 0.62, 0.10) + 0.05 * (noise(1.5) - 0.5)
    rgb = tint(base, (1.0, 1.01, 1.03))
    save("tower_grating", rgb)
    bars = (bar_x | bar_y).astype(float)
    maps("tower_grating", bars, 3.0, 0.5 * bars, 0.45 * np.ones_like(bars))


def light():
    """Feu de balisage : rouge saturé (64 px, émissif par tower_glow.rvmat)."""
    Image.new("RGB", (64, 64), (235, 22, 12)).save(os.path.join(RENDER, "tower_light_co.png"))
    print("texture tower_light")


def cable():
    g = 0.06 + 0.02 * (noise(1.0) - 0.5)
    save("tower_cable", tint(g, (1.0, 1.0, 1.0)))


def olive():
    mott = noise(2.0)
    fine = noise(0.7)
    g = 0.5 + 0.06 * (mott - 0.5) + 0.03 * (fine - 0.5)
    rgb = tint(g, (0.62, 0.66, 0.44))
    chalk = np.clip((noise(1.8) - 0.5) * 1.5, 0, 1)
    rgb += (0.05 * chalk)[..., None] * np.array([0.9, 0.9, 0.8])
    rgb -= 0.06 * streaks()[..., None]
    # Éclats de peinture (apprêt gris) et rouille aux défauts.
    chip = np.clip((noise(0.9) - 0.84) * 8, 0, 1)
    rgb = rgb * (1 - 0.5 * chip[..., None]) + 0.5 * chip[..., None] * np.array([0.42, 0.42, 0.40])
    rust = np.clip((noise(1.1) - 0.8) * 5, 0, 1) * np.clip((fine - 0.5) * 4, 0, 1)
    rgb = rgb * (1 - 0.6 * rust[..., None]) + 0.6 * rust[..., None] * np.array([0.32, 0.18, 0.09])
    save("tower_olive", rgb)
    maps("tower_olive", noise(0.9) + 1.5 * chip + 2.0 * rust, 5.0, 0.30 - 0.15 * chalk - 0.25 * rust, 0.38 - 0.12 * chalk)


def sign():
    """Panneaux : moitié gauche danger (triangle jaune, éclair, ondes RF), moitié droite plaque d'identification."""
    from PIL import ImageDraw, ImageFont

    def font(sz):
        for f in ("/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"):
            if os.path.exists(f):
                return ImageFont.truetype(f, sz)
        return ImageFont.load_default()

    im = Image.new("RGB", (512, 256), (232, 232, 226))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 0, 255, 255), fill=(236, 196, 30))
    d.rectangle((6, 6, 249, 249), outline=(20, 20, 18), width=6)
    d.polygon([(128, 22), (230, 196), (26, 196)], outline=(20, 20, 18), width=12)
    d.polygon([(136, 70), (106, 132), (130, 132), (114, 180), (156, 112), (132, 112), (150, 70)], fill=(20, 20, 18))
    d.text((40, 206), "DANGER RF", font=font(28), fill=(20, 20, 18))
    d.rectangle((262, 6, 505, 249), outline=(30, 34, 30), width=6)
    d.rectangle((262, 6, 505, 66), fill=(46, 58, 40))
    d.text((276, 18), "COMSPEC RELAIS", font=font(21), fill=(235, 235, 225))
    d.text((276, 80), "SITE ATAK-07", font=font(27), fill=(28, 30, 28))
    d.text((276, 124), "ACCÈS RÉGLEMENTÉ", font=font(18), fill=(28, 30, 28))
    d.text((276, 152), "Champ RF : ne pas", font=font(18), fill=(70, 70, 66))
    d.text((276, 174), "stationner devant", font=font(18), fill=(70, 70, 66))
    d.text((276, 196), "les antennes.", font=font(18), fill=(70, 70, 66))
    d.text((276, 222), "Urgence : 112", font=font(18), fill=(150, 30, 26))
    a = np.asarray(im).astype(float) / 255
    a = a * (1 - 0.12 * np.clip(noise(2.2, 256)[:, :256].repeat(2, 1)[:, :512] - 0.4, 0, 1)[..., None])
    Image.fromarray((np.clip(a, 0, 1) * 255).astype(np.uint8)).save(os.path.join(RENDER, "tower_sign_co.png"))
    print("texture tower_sign")


def net():
    """Filet de camouflage (transparence) : lanières découpées aux trois teintes sur une maille sombre, trous."""
    n1, n2, n3 = noise(1.4), noise(2.4), noise(0.5)
    col = np.where((n1 > 0.55)[..., None], np.array([0.17, 0.20, 0.11]), np.array([0.27, 0.25, 0.15]))
    col = np.where((n2 > 0.62)[..., None], np.array([0.46, 0.41, 0.30]), col)
    col = col * (0.85 + 0.3 * n3)[..., None]
    ys, xs = np.mgrid[0:N, 0:N]
    mesh = ((xs % 32) < 3) | ((ys % 32) < 3)
    garnish = (noise(0.8) + 0.3 * n1) > 0.5
    alpha = np.where(garnish, 1.0, np.where(mesh, 1.0, 0.0))
    col = np.where((~garnish & mesh)[..., None], np.array([0.12, 0.12, 0.10]), col)
    rgba = np.concatenate([np.clip(col, 0, 1), alpha[..., None]], -1)
    Image.fromarray((rgba * 255).astype(np.uint8), "RGBA").resize((512, 512), Image.LANCZOS).save(os.path.join(RENDER, "tower_net_ca.png"))
    print("texture tower_net")


def fence_mesh():
    """Grillage simple torsion (transparence) : 16 mailles losanges de 6 cm par mètre, fil galvanisé."""
    M = 512
    ys, xs = np.mgrid[0:M, 0:M] / M * 16
    u, v = (xs + ys) % 1.0, (xs - ys) % 1.0
    wire = (np.minimum(u, 1 - u) < 0.055) | (np.minimum(v, 1 - v) < 0.055)
    g = 0.55 + 0.1 * (noise(1.5)[:M, :M] - 0.5)
    rgba = np.stack([g * 0.98, g, g * 1.03, wire.astype(float)], -1)
    Image.fromarray((np.clip(rgba, 0, 1) * 255).astype(np.uint8), "RGBA").save(os.path.join(RENDER, "tower_fence_ca.png"))
    print("texture tower_fence")


if __name__ == "__main__":
    galva()
    dark()
    radome()
    box()
    concrete()
    grating()
    cable()
    light()
    olive()
    sign()
    net()
    fence_mesh()
