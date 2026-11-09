"""
Description commune des lunettes de sport COMSPEC SSE (addon eyewear) : formes, coloris, positionnement.
Lu par gen_p3d.py (modèles MLOD) et gen_textures.py (textures, icônes).

Repère local (mètres, repère Arma) : X droite, Y haut, Z avant, origine au milieu des deux pupilles.

POSITIONNEMENT (à corriger ici après le premier essai en jeu, puis relancer gen_p3d.py) :
  HEAD_OFFSET  : décalage du repère local dans le repère des modèles CfgGlasses. Relevé sur les lunettes vanilla
                 (boîte englobante de a3\\characters_f\\heads\\glasses\\g_sport_red.p3d, LOD 1 : X ±0,082,
                 Y 0,677..0,721, Z -0,124..+0,028) : yeux à Y ≈ 0,699, avant du verre à Z ≈ +0,028. Le repère est celui
                 du personnage A3 (origine ≈ bassin), modèle pondéré sur l'os « head » (squelette OFP2_ManSkeleton).
  NVG_OFFSET   : décalage supplémentaire du modèle « slot JVN ». Les JVN vanilla portées (a3\\weapons_f\\binocular\\
                 nvg_proxy.p3d, LOD 1 : Y 0,666..0,822, Z -0,228..+0,096) sont dans le même repère que les lunettes :
                 le proxy « nvg » du personnage est à la même origine que le proxy « glasses ». D'où (0, 0, 0).
  ATTACH_OFFSET : décalage du modèle du slot virtuel (attachTo) : modèle centré sur le milieu des yeux ; le point
                 d'accroche et son décalage se règlent dans la config (COMSPEC_SSE_EyewearHead_Base).
"""
import math

HEAD_OFFSET = (0.0, 0.699, 0.008)
NVG_OFFSET = (0.0, 0.0, 0.0)
ATTACH_OFFSET = (0.0, 0.0, 0.0)

PREFIX = "z\\comspec_sse\\addons\\eyewear\\data\\"

# Formes. W : demi-largeur du verre mesurée le long de l'enroulement ; Rh : rayon d'enroulement horizontal ;
# Rv : rayon de courbure verticale ; z0 : avant du verre au centre ; tilt : inclinaison pantoscopique (degrés) ;
# th : épaisseur du verre ; xt : écart des branches derrière les tempes.
SHAPES = {
    "radar": dict(W=0.074, Rh=0.080, Rv=0.11, z0=0.020, tilt=8.0, th=0.0018, xt=0.0755, ext=0.010,
                  t_lo=-0.034, t_hi=0.024),
    "hurricane": dict(W=0.080, Rh=0.088, Rv=0.15, z0=0.021, tilt=6.0, th=0.0020, xt=0.0790, ext=0.006,
                      t_lo=-0.034, t_hi=0.027),
    "wind": dict(W=0.079, Rh=0.084, Rv=0.13, z0=0.020, tilt=7.0, th=0.0018, xt=0.0785, ext=0.009,
                 t_lo=-0.036, t_hi=0.024),
}


def _smooth_end(a, top, bot, a_e, e_min=0.10):
    """Bout du verre arrondi (ellipse) au-delà de a_e : haut et bas se rejoignent."""
    if a <= a_e:
        return top, bot
    k = (a - a_e) / (1.0 - a_e)
    e = max(e_min, math.sqrt(max(0.0, 1.0 - k * k)))
    mid = 0.5 * (top + bot)
    return mid + (top - mid) * e, mid + (bot - mid) * e


def _pl(a, pts):
    """Interpolation linéaire par morceaux (formes anguleuses de la Hurricane)."""
    for (a0, t0), (a1, t1) in zip(pts, pts[1:]):
        if a <= a1:
            k = 0.0 if a1 == a0 else (a - a0) / (a1 - a0)
            return t0 + (t1 - t0) * k
    return pts[-1][1]


def outline(shape, a):
    """Haut et bas du verre (t, mètres) à la position a = |s| / W (0 centre, 1 bout)."""
    a = min(1.0, abs(a))
    if shape == "radar":
        top = 0.0215 - 0.004 * a * a - 0.0065 * a ** 6
        lobe = -0.0305 + 0.074 * (a - 0.42) ** 2
        a_n, apex = 0.19, 0.0010
        bot = lobe if a >= a_n else apex - (apex - (-0.0305 + 0.074 * (a_n - 0.42) ** 2)) * (a / a_n) ** 1.6
        return _smooth_end(a, top, bot, 0.84)
    if shape == "hurricane":
        top = _pl(a, [(0.0, 0.0245), (0.80, 0.0225), (1.0, 0.0120)])
        bot = _pl(a, [(0.0, 0.0030), (0.05, 0.0025), (0.17, -0.0150), (0.20, -0.0200), (0.33, -0.0290),
                      (0.62, -0.0315), (0.92, -0.0130), (1.0, -0.0060)])
        return _smooth_end(a, top, bot, 0.97, 0.55)
    if shape == "wind":
        top = 0.0205 - 0.003 * a * a - 0.0045 * a ** 6
        # Bas du verre en lignes droites aux coins adoucis (moyenne glissante), comme le verre écran de la Wind.
        pts = [(0.0, -0.020), (0.17, -0.0262), (0.40, -0.0338), (0.93, -0.0095), (1.0, -0.0050)]

        def lobe_at(x):
            return sum(_pl(min(1.0, max(0.0, x + d)), pts) for d in (-0.06, -0.03, 0.0, 0.03, 0.06)) / 5.0
        a_n, apex = 0.17, 0.0000
        bot = lobe_at(a) if a >= a_n else apex - (apex - lobe_at(a_n)) * (a / a_n) ** 1.35
        return _smooth_end(a, top, bot, 0.90)
    raise KeyError(shape)


def notch_a(shape):
    return {"radar": 0.19, "hurricane": 0.20, "wind": 0.17}[shape]


# Coloris. Couleurs RGB 0..1. lens : dégradé du verre (positions |u| 0 centre -> 1 bout) ; tint : ppEffect ColorCorrections
# [luminosité, contraste, [mélange r,g,b,a], saturation] ; spec : spéculaire colorée du verre (miroir).
COLOURS = {
    "radar_white": dict(
        shape="radar", cls="COMSPEC_SSE_G_Oakley_RadarEV_White",
        fr="Oakley Radar EV Path (Blanc / Prizm Road)", en="Oakley Radar EV Path (Polished White / Prizm Road)",
        desc_fr="Lunettes de sport Oakley Radar EV Path, monture blanc brillant, branches rouges, verre Prizm Road (miroir rouge-rose-jaune).",
        desc_en="Oakley Radar EV Path sport glasses, polished white frame, red temples, Prizm Road lens (red-pink-yellow mirror).",
        frame=(0.93, 0.93, 0.94), frame_gloss=(0.85, 0.80), temple=(0.76, 0.11, 0.12), temple_gloss=(0.18, 0.25),
        tip=(0.94, 0.94, 0.95), nose=(0.05, 0.05, 0.055), logo_bg=(0.93, 0.93, 0.94), logo_fg=(0.80, 0.10, 0.12),
        logo="O", vents=(0.55, 0.55, 0.58),
        lens=[(0.0, (0.48, 0.30, 0.62)), (0.35, (0.82, 0.22, 0.48)), (0.62, (0.97, 0.16, 0.30)),
              (0.82, (1.00, 0.45, 0.10)), (1.0, (1.00, 0.90, 0.08))],
        lens_alpha=0.78, lens_vgrad=(0.95, 1.05), spec=(1.0, 0.55, 0.45),
        tint=[0.98, 1.10, [1.00, 0.45, 0.55, 0.16], 1.30]),
    "radar_black": dict(
        shape="radar", cls="COMSPEC_SSE_G_Oakley_RadarEV_Black",
        fr="Oakley Radar EV Path (Noir / Prizm Golf)", en="Oakley Radar EV Path (Polished Black / Prizm Golf)",
        desc_fr="Lunettes de sport Oakley Radar EV Path, monture noir brillant, verre Prizm Golf (miroir violet-vert jade).",
        desc_en="Oakley Radar EV Path sport glasses, polished black frame, Prizm Golf lens (violet to jade mirror).",
        frame=(0.035, 0.035, 0.04), frame_gloss=(0.80, 0.85), temple=(0.04, 0.04, 0.045), temple_gloss=(0.15, 0.20),
        tip=(0.05, 0.05, 0.055), nose=(0.04, 0.04, 0.045), logo_bg=(0.035, 0.035, 0.04), logo_fg=(0.78, 0.78, 0.80),
        logo="O", vents=(0.01, 0.01, 0.01),
        lens=[(0.0, (0.42, 0.88, 0.66)), (0.40, (0.40, 0.82, 0.80)), (0.62, (0.38, 0.70, 0.98)),
              (0.85, (0.62, 0.52, 1.00)), (1.0, (0.70, 0.45, 1.00))],
        lens_alpha=0.74, lens_vgrad=(0.95, 1.05), spec=(0.55, 0.90, 0.75),
        tint=[0.95, 1.12, [0.30, 0.85, 0.55, 0.16], 1.10]),
    "hurricane_black": dict(
        shape="hurricane", cls="COMSPEC_SSE_G_DFranklin_Hurricane_Black",
        fr="D.Franklin Hurricane (Noir)", en="D.Franklin Hurricane (Black)",
        desc_fr="Lunettes masque D.Franklin Hurricane, monture noire mate pleine, pont central, verre écran gris foncé.",
        desc_en="D.Franklin Hurricane shield glasses, full matte black frame, centre bridge, dark grey shield lens.",
        frame=(0.075, 0.075, 0.08), frame_gloss=(0.10, 0.18), temple=(0.075, 0.075, 0.08), temple_gloss=(0.10, 0.18),
        tip=(0.06, 0.06, 0.065), nose=(0.07, 0.07, 0.075), logo_bg=(0.075, 0.075, 0.08), logo_fg=(0.16, 0.16, 0.17),
        logo="D.FRANKLIN", vents=None,
        lens=[(0.0, (0.13, 0.12, 0.12)), (1.0, (0.16, 0.15, 0.15))],
        lens_alpha=0.88, lens_vgrad=(1.0, 1.0), spec=(0.45, 0.45, 0.47),
        tint=[0.72, 1.02, [0.50, 0.50, 0.50, 0.0], 0.92]),
    "wind_rainbow": dict(
        shape="wind", cls="COMSPEC_SSE_G_DFranklin_Wind_Rainbow",
        fr="D.Franklin Wind (Rainbow)", en="D.Franklin Wind (Rainbow)",
        desc_fr="Lunettes de sport D.Franklin Wind, demi-monture translucide violet-rose-bleu, verre miroir violet.",
        desc_en="D.Franklin Wind sport glasses, translucent purple-pink-blue half frame, violet mirror lens.",
        frame=(0.55, 0.30, 0.72), frame_gloss=(0.45, 0.55), temple=(0.55, 0.30, 0.72), temple_gloss=(0.45, 0.55),
        tip=(0.05, 0.05, 0.06), nose=(0.10, 0.68, 0.74), logo_bg=(0.06, 0.52, 0.62), logo_fg=(0.95, 0.97, 1.0),
        logo="D.FRANKLIN", vents=None, rainbow=True,
        lens=[(0.0, (0.58, 0.20, 0.98)), (0.55, (0.62, 0.24, 1.00)), (0.80, (0.85, 0.40, 0.85)),
              (0.92, (0.55, 0.85, 0.80)), (1.0, (0.40, 0.90, 0.95))],
        lens_alpha=0.80, lens_vgrad=(1.05, 0.95), spec=(0.75, 0.45, 1.0),
        tint=[0.88, 1.04, [0.60, 0.35, 1.00, 0.18], 1.05]),
    "wind_orange": dict(
        shape="wind", cls="COMSPEC_SSE_G_DFranklin_Wind_Orange",
        fr="D.Franklin Wind (Orange)", en="D.Franklin Wind (Orange)",
        desc_fr="Lunettes de sport D.Franklin Wind, demi-monture translucide orange et noire, verre miroir rouge-orange.",
        desc_en="D.Franklin Wind sport glasses, translucent orange and black half frame, red-orange mirror lens.",
        frame=(0.95, 0.42, 0.08), frame_gloss=(0.45, 0.55), temple=(0.95, 0.42, 0.08), temple_gloss=(0.45, 0.55),
        tip=(0.05, 0.05, 0.05), nose=(0.98, 0.50, 0.16), logo_bg=(0.06, 0.05, 0.05), logo_fg=(0.98, 0.50, 0.12),
        logo="D.FRANKLIN", vents=None, black_ends=True,
        lens=[(0.0, (0.93, 0.18, 0.12)), (0.45, (0.92, 0.14, 0.08)), (0.78, (1.00, 0.42, 0.05)),
              (1.0, (1.00, 0.62, 0.05))],
        lens_alpha=0.80, lens_vgrad=(1.0, 1.05), spec=(1.0, 0.45, 0.20),
        tint=[0.85, 1.06, [1.00, 0.55, 0.10, 0.22], 1.12]),
}

ORDER = ["radar_white", "radar_black", "hurricane_black", "wind_rainbow", "wind_orange"]


def lerp3(a, b, k):
    return tuple(a[i] + (b[i] - a[i]) * k for i in range(3))


def ramp(stops, x):
    x = min(1.0, max(0.0, x))
    for (x0, c0), (x1, c1) in zip(stops, stops[1:]):
        if x <= x1:
            k = 0.0 if x1 == x0 else (x - x0) / (x1 - x0)
            k = k * k * (3 - 2 * k)
            return lerp3(c0, c1, k)
    return stops[-1][1]


def lens_colour(cid, u, v):
    """Couleur RGB du verre au point de texture (u 0..1 gauche-droite, v 0..1 haut-bas)."""
    c = COLOURS[cid]
    a = abs(2.0 * u - 1.0)
    rgb = ramp(c["lens"], a)
    g0, g1 = c["lens_vgrad"]
    k = g0 + (g1 - g0) * v
    if cid == "wind_rainbow":
        # Reflet irisé en biais sur le bout droit (photo produit).
        w = max(0.0, 1.0 - abs((u - 0.80) - 0.25 * (v - 0.5)) / 0.12)
        rgb = lerp3(rgb, (1.0, 0.80, 0.55), 0.35 * w)
    return tuple(min(1.0, x * k) for x in rgb)


def rainbow_frame(u):
    """Monture « tie-dye » translucide de la Wind Rainbow, le long de la barre (u 0..1)."""
    stops = [(0.00, (0.10, 0.62, 0.70)), (0.10, (0.55, 0.28, 0.72)), (0.22, (0.90, 0.35, 0.70)),
             (0.33, (0.45, 0.75, 0.95)), (0.45, (0.55, 0.28, 0.72)), (0.55, (0.55, 0.28, 0.72)),
             (0.67, (0.45, 0.75, 0.95)), (0.78, (0.90, 0.35, 0.70)), (0.90, (0.55, 0.28, 0.72)),
             (1.00, (0.10, 0.62, 0.70))]
    return ramp(stops, u)
