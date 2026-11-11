"""
Postes PC fixes de salle d'opérations (TOC / JOC) : unité centrale, moniteur(s), clavier mécanique, souris et tapis,
câbles, en UN objet posé sur une table. Mêmes primitives, matières, format MLOD et moteur d'aperçu que les écrans
(model_toc_screens.py, importé tel quel), modèles dans Sources/addons/main/data/toc/.

Repère : X à droite, Y vers l'avant (côté écran : l'opérateur est en +Y), Z vers le haut. Origine : dessus du plateau de
la table, milieu du poste en X. Vu par l'opérateur, sa gauche est en +X (comme tous les écrans : panel1 à gauche).

Modèles (dalle 27" : 0,5977 x 0,3362 m visibles ; 49" 32:9 : 1,1960 x 0,3364 m, rayon 1,8 m) :
  toc_pc_1       tour, un 27" sur pied, clavier, souris                      sélection « screen »
  toc_pc_2       tour, deux 27" côte à côte sur un bras double               panel1 (gauche), panel2
  toc_pc_3       mini-PC, trois 27" en arc (latéraux tournés de 22°)         panel1, panel2, panel3
  toc_pc_curved  tour, 49" incurvé 32:9 sur pied                             panel1 (moitié gauche 16:9), panel2
                 (surface continue, UV 0..1 déroulées sur l'arc de chaque moitié)

Chaque modèle : LOD 0 / 2 / 6 (touches du clavier une à une, puis par rangée, puis plus rien), ombre, géométrie, tir,
vue (boîtes convexes). Dalles : verre éteint, image posée par fn_tocScreen (comspec_screenMat).

    python model_toc_pc.py        # modèles et aperçus Eden (les textures du cadre sont celles de model_toc_screens.py)
    (NO_PREVIEW=1 : sans aperçus ; RENDER_DIR=dossier : grands rendus de contrôle, face et dos)
"""
import math
import os
import shutil
import struct
import tempfile

import numpy as np
from PIL import Image

import model_toc_screens as T

M27 = (0.5977, 0.3362)
P49 = (1.1960, 0.3364)
CURVE_R = 1.8
YS = -0.12            # plan des dalles (l'opérateur est en +Y, le clavier devant)
BZ27 = (0.006, 0.006, 0.012, 0.006)


def rot_z(p, a, pivot=(0.0, 0.0)):
    x, y = p[0] - pivot[0], p[1] - pivot[1]
    c, s = math.cos(a), math.sin(a)
    return (pivot[0] + x * c - y * s, pivot[1] + x * s + y * c, p[2])


def turned(sub, a, pivot):
    """Maillage tourné autour d'un axe vertical (points et normales)."""
    sub.pts = [rot_z(p, a, pivot) for p in sub.pts]
    sub.faces = [(idx, uvs, rot_z(n, a), mat, sels) for idx, uvs, n, mat, sels in sub.faces]
    return sub


def tilted(sub, a, pivot):
    """Maillage basculé autour de l'axe X (clavier incliné)."""
    sub.pts = [T.rot_x(p, a, pivot) for p in sub.pts]
    sub.faces = [(idx, uvs, T.rot_x(n, a, (0, 0, 0)), mat, sels) for idx, uvs, n, mat, sels in sub.faces]
    return sub


# --------------------------------------------------------------------------------------------------------------------
# Pièces
# --------------------------------------------------------------------------------------------------------------------
def monitor(m, cx, cz, detail, sel, ys=YS):
    """Moniteur 27" à bords fins : dalle, coque arrière, platine VESA, voyant."""
    aw, ah = M27
    T.panel(m, cx, cz, aw, ah, BZ27, ys - 0.014, ys, detail, sel, bevel=0.002, body="plastic", ring="plastic")
    T.back_casing(m, cx, cz, 0.34, 0.2, ys - 0.038, ys - 0.014, detail)
    if detail < 2:
        T.box(m, cx - 0.06, cx + 0.06, ys - 0.046, ys - 0.038, cz - 0.06, cz + 0.06, "steel", b=0.002 if detail == 0 else 0)
    if detail == 0:
        T.power_led(m, cx - aw / 2 + 0.03, cz - ah / 2 - 0.006, ys)


def stand_single(m, cz, detail, ys=YS, wide=0.26):
    """Pied de moniteur : socle plat biseauté, colonne, tête."""
    cb = 0.003 if detail == 0 else 0
    T.box(m, -wide / 2, wide / 2, ys - 0.17, ys + 0.05, 0.0, 0.012, "steel", b=0.004 if detail < 2 else 0)
    T.box(m, -0.03, 0.03, ys - 0.085, ys - 0.06, 0.012, cz + 0.05, "steel", b=cb)
    T.box(m, -0.045, 0.045, ys - 0.06, ys - 0.046, cz - 0.045, cz + 0.045, "steel", b=cb)
    if detail == 0:
        # Passe-câble de la colonne.
        T.box(m, -0.02, 0.02, ys - 0.092, ys - 0.085, 0.09, 0.13, "plastic", b=0.002)


def arm_pole(m, cz, detail, ys=YS):
    """Mât central d'un bras multi-écrans : pince de table, tube, collier."""
    yp = ys - 0.12
    cb = 0.003 if detail == 0 else 0
    T.box(m, -0.06, 0.06, yp - 0.05, yp + 0.05, 0.0, 0.014, "steel", b=cb)
    T.cylinder(m, (0.0, yp, 0.014), (0.0, yp, cz + 0.09), 0.02, "steel", n=12 if detail == 0 else 8)
    T.box(m, -0.035, 0.035, yp - 0.03, ys - 0.07, cz - 0.03, cz + 0.03, "steel", b=cb)
    if detail == 0:
        T.cylinder(m, (0.0, yp, cz + 0.09), (0.0, yp, cz + 0.096), 0.022, "plastic", n=12)


def arm_bar(m, x0, x1, cz, detail, ys=YS):
    """Traverse horizontale derrière les moniteurs et ses têtes VESA."""
    T.box(m, x0, x1, ys - 0.07, ys - 0.052, cz - 0.02, cz + 0.02, "steel", b=0.003 if detail == 0 else 0)


def arm_head(m, cx, cz, detail, ys=YS):
    T.box(m, cx - 0.04, cx + 0.04, ys - 0.052, ys - 0.046, cz - 0.04, cz + 0.04, "steel", b=0.002 if detail == 0 else 0)


def tower(m, cx, detail, ys=YS):
    """Unité centrale moyenne tour : coque alu, façade ajourée, bouton et voyant, pieds."""
    w, d, h = 0.21, 0.43, 0.45
    y0, y1 = ys - 0.30, ys - 0.30 + d
    z0 = 0.012
    x0, x1 = cx - w / 2, cx + w / 2
    T.box(m, x0, x1, y0, y1, z0, z0 + h, "frame", b=0.006 if detail == 0 else (0.004 if detail == 1 else 0))
    if detail < 2:
        for sx in (-1, 1):
            for yy in (y0 + 0.04, y1 - 0.04):
                T.box(m, cx + sx * (w / 2 - 0.03) - 0.02, cx + sx * (w / 2 - 0.03) + 0.02, yy - 0.02, yy + 0.02, 0.0, z0, "rubber")
    if detail == 0:
        # Façade (vers l'opérateur) : grille d'aération, bandeau de commande au-dessus.
        vw, vh = w - 0.05, h - 0.13
        zc = z0 + 0.03 + vh / 2
        yf = y1 + 0.0006
        pts = [(cx - vw / 2, yf, zc - vh / 2), (cx + vw / 2, yf, zc - vh / 2), (cx + vw / 2, yf, zc + vh / 2), (cx - vw / 2, yf, zc + vh / 2)]
        uvs = [(vw / 0.08, vh / 0.08), (0, vh / 0.08), (0, 0), (vw / 0.08, 0)]
        m.poly(pts, "vent", uvs=uvs, hint=(0, 1, 0))
        # Dessus : grille d'extraction.
        tw, td = w - 0.06, d - 0.16
        zt = z0 + h + 0.0006
        yc = (y0 + y1) / 2 - 0.02
        pts = [(cx - tw / 2, yc - td / 2, zt), (cx + tw / 2, yc - td / 2, zt), (cx + tw / 2, yc + td / 2, zt), (cx - tw / 2, yc + td / 2, zt)]
        uvs = [(0, td / 0.08), (tw / 0.08, td / 0.08), (tw / 0.08, 0), (0, 0)]
        m.poly(pts, "vent", uvs=uvs, hint=(0, 0, 1))
        # Bandeau : bouton de marche, voyant, deux prises.
        zb = z0 + h - 0.045
        T.cylinder(m, (cx - 0.06, y1, zb), (cx - 0.06, y1 + 0.003, zb), 0.011, "steel", n=12)
        T.box(m, cx - 0.036, cx - 0.028, y1 - 0.001, y1 + 0.001, zb - 0.002, zb + 0.002, "led")
        for k in range(2):
            T.box(m, cx + 0.01 + k * 0.03, cx + 0.024 + k * 0.03, y1 - 0.001, y1 + 0.0008, zb - 0.004, zb + 0.004, "rubber")
        # Dos : bloc d'alimentation et plaque des connecteurs.
        yb = y0 - 0.0006
        pts = [(cx + 0.08, yb, z0 + 0.02), (cx - 0.08, yb, z0 + 0.02), (cx - 0.08, yb, z0 + 0.11), (cx + 0.08, yb, z0 + 0.11)]
        m.poly(pts, "vent", uvs=[(0, 0.09 / 0.08), (2, 0.09 / 0.08), (2, 0), (0, 0)], hint=(0, -1, 0))
        T.box(m, cx - 0.085, cx - 0.04, y0 - 0.002, y0, z0 + 0.15, z0 + h - 0.03, "steel")
    return (x0, x1, y0, y1, 0.0, z0 + h)


def mini_pc(m, cx, cy, detail):
    """Mini-PC (poste à trois écrans) : boîtier plat, grille, voyant."""
    w, d, h = 0.19, 0.19, 0.05
    T.box(m, cx - w / 2, cx + w / 2, cy - d / 2, cy + d / 2, 0.004, 0.004 + h, "frame", b=0.006 if detail == 0 else 0)
    if detail == 0:
        for sx in (-1, 1):
            for sy in (-1, 1):
                T.box(m, cx + sx * 0.07 - 0.012, cx + sx * 0.07 + 0.012, cy + sy * 0.07 - 0.012, cy + sy * 0.07 + 0.012, 0.0, 0.004, "rubber")
        tw = w - 0.07
        zt = 0.004 + h + 0.0006
        pts = [(cx - tw / 2, cy - tw / 2, zt), (cx + tw / 2, cy - tw / 2, zt), (cx + tw / 2, cy + tw / 2, zt), (cx - tw / 2, cy + tw / 2, zt)]
        m.poly(pts, "vent", uvs=[(0, tw / 0.08), (tw / 0.08, tw / 0.08), (tw / 0.08, 0), (0, 0)], hint=(0, 0, 1))
        T.box(m, cx - 0.07, cx - 0.062, cy + d / 2 - 0.001, cy + d / 2 + 0.001, 0.03, 0.034, "led")
        T.cylinder(m, (cx + 0.06, cy + d / 2, 0.03), (cx + 0.06, cy + d / 2 + 0.002, 0.03), 0.007, "steel", n=10)
    return (cx - w / 2, cx + w / 2, cy - d / 2, cy + d / 2, 0.0, 0.004 + h)


KEY_ROWS = [
    [1] * 13 + [2],
    [1.5] + [1] * 12 + [1.5],
    [1.75] + [1] * 11 + [2.25],
    [2.25] + [1] * 10 + [2.75],
    [1.25, 1.25, 1.25, 6.25, 1.25, 1.25, 1.25, 1.25],
]


def keyboard(m, cx, cy, detail):
    """Clavier mécanique sans pavé numérique : châssis alu, touches une à une (LOD 0), incliné de 5°."""
    u = 0.019                       # pas d'une touche
    gap = 0.0025
    main_w = 15 * u
    nav_x = main_w + 0.006          # bloc de navigation à droite de l'opérateur
    W = nav_x + 3 * u + 0.012
    D = 6 * u + 0.012 + 0.016
    sub = T.Mesh()
    x_left = W / 2                  # gauche de l'opérateur en +X
    T.box(sub, -W / 2, W / 2, -D / 2, D / 2, 0.0, 0.016, "frame", b=0.003 if detail == 0 else 0)
    if detail < 2:
        kh = 0.016 + 0.0085

        def key(xa, wa, row, hgt=kh):
            # xa : distance depuis le bord gauche de l'opérateur ; row 0 = rangée des fonctions (au fond, -Y).
            xl = x_left - 0.006 - xa
            y_back = -D / 2 + 0.006 + row * u + (0.004 if row > 0 else 0)
            T.box(sub, xl - wa + gap / 2, xl - gap / 2, y_back + gap / 2, y_back + u - gap / 2, 0.016, hgt, "rubber", skip=("-z",))

        if detail == 0:
            # Rangée des fonctions : Échap, puis trois groupes de quatre.
            key(0, u, 0)
            for g in range(3):
                for k in range(4):
                    key(2 * u + g * 4.5 * u + k * u, u, 0)
            for r, row in enumerate(KEY_ROWS):
                xa = 0.0
                for wk in row:
                    key(xa, wk * u, r + 1)
                    xa += wk * u
            for r in (0, 1, 2):
                for k in range(3):
                    key(nav_x + k * u, u, r)
            key(nav_x + u, u, 4)
            for k in range(3):
                key(nav_x + k * u, u, 5)
        else:
            for r in range(6):
                key(0, main_w, r, kh - 0.001)
            for r in (0, 1, 2, 5):
                key(nav_x, 3 * u, r, kh - 0.001)
    tilted(sub, math.radians(-5), (0.0, D / 2, 0.0))
    for i, p in enumerate(sub.pts):
        sub.pts[i] = (p[0] + cx, p[1] + cy, p[2] + 0.002)
    m.merge(sub)
    if detail == 0:
        # Patins arrière et câble tressé qui part vers le fond.
        for sx in (-1, 1):
            T.box(m, cx + sx * (W / 2 - 0.04) - 0.015, cx + sx * (W / 2 - 0.04) + 0.015, cy - D / 2 + 0.004, cy - D / 2 + 0.02, 0.0, 0.012, "rubber")
        cable(m, [(cx + 0.05, cy - D / 2 + 0.002, 0.014), (cx + 0.05, cy - D / 2 - 0.03, 0.004), (cx + 0.02, YS - 0.2, 0.004)])
    return (cx - W / 2, cx + W / 2, cy - D / 2, cy + D / 2, 0.0, 0.04)


def mouse(m, cx, cy, detail):
    """Tapis de souris et souris (nez vers le fond)."""
    T.box(m, cx - 0.15, cx + 0.15, cy - 0.12, cy + 0.12, 0.0, 0.003, "plastic", b=0.001 if detail == 0 else 0, skip=("-z",))
    if detail >= 2:
        return
    y0 = cy - 0.055
    loops = [(cx - 0.02, cx + 0.02, 0.004, 0.013, y0), (cx - 0.029, cx + 0.029, 0.003, 0.03, y0 + 0.022),
             (cx - 0.032, cx + 0.032, 0.003, 0.039, y0 + 0.062), (cx - 0.03, cx + 0.03, 0.003, 0.031, y0 + 0.095),
             (cx - 0.022, cx + 0.022, 0.004, 0.015, y0 + 0.112)]
    T.lathe_rect(m, loops, "plastic", close_start=("plastic", (), None), close_end=("plastic", (), None))
    if detail == 0:
        T.cylinder(m, (cx - 0.004, y0 + 0.02, 0.026), (cx + 0.004, y0 + 0.02, 0.026), 0.0085, "rubber", n=10)
        cable(m, [(cx, y0, 0.008), (cx, y0 - 0.05, 0.004), (cx + 0.03, YS - 0.2, 0.004)])


def cable(m, pts, r=0.003):
    for a, b in zip(pts, pts[1:]):
        T.cylinder(m, a, b, r, "rubber", n=5, caps=False)


def curved_monitor(m, cz, detail, sels, ys=YS):
    """49" 32:9 incurvé (rayon 1,8 m, centre de courbure côté opérateur) : dalle continue en deux moitiés 16:9
    (sels[0] à gauche de l'opérateur, +X), bords fins, dos qui suit la courbe, coque centrale."""
    aw, ah = P49
    R = CURVE_R
    bl, bb, bt = 0.008, 0.014, 0.008
    A = (aw / 2) / R
    At = A + bl / R
    n = {0: 24, 1: 12, 2: 6}[detail]
    th = 0.02
    az0, az1 = cz - ah / 2, cz + ah / 2
    Z0, Z1 = az0 - bb, az1 + bt

    def P(a, r, z):
        return (r * math.sin(a), ys + R - r * math.cos(a), z)

    angs = [-At] + [-A + 2 * A * i / n for i in range(n + 1)] + [At]
    for i, (a0, a1) in enumerate(zip(angs, angs[1:])):
        am = (a0 + a1) / 2
        inw = (-math.sin(am), math.cos(am), 0.0)
        out = (math.sin(am), -math.cos(am), 0.0)
        bezel = i == 0 or i == len(angs) - 2
        if bezel:
            m.poly([P(a0, R, Z0), P(a1, R, Z0), P(a1, R, Z1), P(a0, R, Z1)], "plastic", hint=inw)
        else:
            m.poly([P(a0, R, Z0), P(a1, R, Z0), P(a1, R, az0), P(a0, R, az0)], "plastic", hint=inw)
            m.poly([P(a0, R, az1), P(a1, R, az1), P(a1, R, Z1), P(a0, R, Z1)], "plastic", hint=inw)
            # s : part de la largeur depuis la gauche de l'opérateur (+X, angle +A).
            s0, s1 = (A - a1) / (2 * A), (A - a0) / (2 * A)
            k = 0 if (s0 + s1) / 2 < 0.5 else 1
            u0, u1 = 2 * s0 - k, 2 * s1 - k
            # a0 < a1 : x croît ; le point en a1 est plus à gauche de l'opérateur (u0).
            m.poly([P(a0, R, az0), P(a1, R, az0), P(a1, R, az1), P(a0, R, az1)], "screen", (sels[k],),
                   uvs=[(u1, 1.0), (u0, 1.0), (u0, 0.0), (u1, 0.0)], hint=inw)
        m.poly([P(a0, R + th, Z0), P(a1, R + th, Z0), P(a1, R + th, Z1), P(a0, R + th, Z1)], "plastic", hint=out)
        m.poly([P(a0, R, Z1), P(a1, R, Z1), P(a1, R + th, Z1), P(a0, R + th, Z1)], "plastic", hint=(0, 0, 1))
        m.poly([P(a0, R, Z0), P(a1, R, Z0), P(a1, R + th, Z0), P(a0, R + th, Z0)], "plastic", hint=(0, 0, -1))
    for a, sx in ((-At, -1), (At, 1)):
        m.poly([P(a, R, Z0), P(a, R + th, Z0), P(a, R + th, Z1), P(a, R, Z1)], "plastic", hint=(sx * math.cos(a), sx * math.sin(a), 0))
    T.back_casing(m, 0.0, cz, 0.36, 0.2, ys - 0.05, ys - 0.011, detail)
    if detail == 0:
        px, py, _ = P(A - 0.03 / R, R, 0)
        T.box(m, px - 0.003, px + 0.003, py - 0.0025, py + 0.0006, az0 - 0.008, az0 - 0.005, "led")
    return A, At, th, Z0, Z1


def desk_set(m, detail, kb_x=0.0, pad_x=-0.42):
    keyboard(m, kb_x, YS + 0.37, detail)
    mouse(m, pad_x, YS + 0.37, detail)


# --------------------------------------------------------------------------------------------------------------------
# Modèles
# --------------------------------------------------------------------------------------------------------------------
CZ1 = 0.10 + BZ27[2] + M27[1] / 2          # milieu d'un 27" sur pied : bas du cadre à 10 cm du plateau
CZA = 0.12 + BZ27[2] + M27[1] / 2          # sur bras
PW = M27[0] + BZ27[0] + BZ27[1]            # largeur hors tout d'un 27"
ARC = math.radians(22)


def monitor_cables(m, cx, cz, to_x):
    """Câbles d'un moniteur : de la coque au plateau, puis vers l'unité centrale."""
    y = YS - 0.03
    cable(m, [(cx + 0.06, y, cz - 0.09), (cx + 0.06, YS - 0.1, 0.02), (cx + 0.04, YS - 0.2, 0.004), (to_x, YS - 0.26, 0.004)])


def make_pc_1(detail):
    m = T.Mesh()
    monitor(m, 0.0, CZ1, detail, "screen")
    stand_single(m, CZ1, detail)
    tower(m, -0.62, detail)
    desk_set(m, detail, kb_x=0.02, pad_x=-0.40)
    if detail == 0:
        monitor_cables(m, 0.0, CZ1, -0.52)
    return m


def make_pc_2(detail):
    m = T.Mesh()
    for k, sx in enumerate((1, -1)):
        cx = sx * (PW / 2 + 0.002)
        monitor(m, cx, CZA, detail, "panel%d" % (k + 1))
        if detail < 2:
            arm_head(m, cx, CZA, detail)
    arm_pole(m, CZA, detail)
    if detail < 2:
        arm_bar(m, -PW / 2 - 0.05, PW / 2 + 0.05, CZA, detail)
    tower(m, -0.86, detail)
    desk_set(m, detail, kb_x=0.02, pad_x=-0.40)
    if detail == 0:
        monitor_cables(m, PW / 2, CZA, -0.76)
        monitor_cables(m, -PW / 2, CZA, -0.76)
    return m


def side_pivot(sx):
    return (sx * (PW / 2 + 0.002), YS)


def make_pc_3(detail):
    m = T.Mesh()
    monitor(m, 0.0, CZA, detail, "panel2")
    if detail < 2:
        arm_head(m, 0.0, CZA, detail)
        arm_bar(m, -PW / 2 - 0.004, PW / 2 + 0.004, CZA, detail)
    for sx, sel in ((1, "panel1"), (-1, "panel3")):
        sub = T.Mesh()
        cx = sx * (PW + 0.004)
        monitor(sub, cx, CZA, detail, sel)
        if detail < 2:
            arm_head(sub, cx, CZA, detail)
            arm_bar(sub, min(sx * (PW / 2 + 0.004), cx + sx * 0.05), max(sx * (PW / 2 + 0.004), cx + sx * 0.05), CZA, detail)
        m.merge(turned(sub, sx * ARC, side_pivot(sx)))
    arm_pole(m, CZA, detail)
    mini_pc(m, -0.52, YS + 0.12, detail)
    desk_set(m, detail, kb_x=0.02, pad_x=-0.40)
    if detail == 0:
        monitor_cables(m, 0.0, CZA, -0.45)
    return m


def make_pc_curved(detail):
    m = T.Mesh()
    cz = 0.11 + 0.014 + P49[1] / 2
    curved_monitor(m, cz, detail, ("panel1", "panel2"))
    stand_single(m, cz, detail, wide=0.34)
    tower(m, -0.80, detail)
    desk_set(m, detail, kb_x=0.02, pad_x=-0.40)
    if detail == 0:
        monitor_cables(m, 0.0, cz, -0.70)
    return m


def mon_box(cx, cz, a=0.0, pivot=(0.0, 0.0)):
    """Boîte fermée d'un moniteur (éventuellement tourné)."""
    x0, x1 = cx - PW / 2, cx + PW / 2
    z0, z1 = cz - M27[1] / 2 - BZ27[2], cz + M27[1] / 2 + BZ27[3]
    pts = [rot_z((x, y, z), a, pivot) for x in (x0, x1) for y in (YS - 0.04, YS) for z in (z0, z1)]
    return ("pts", pts)


KB_BOX = (0.02 - 0.19, 0.02 + 0.19, YS + 0.37 - 0.075, YS + 0.37 + 0.075, 0.0, 0.035)
PAD_BOX = (-0.40 - 0.15, -0.40 + 0.15, YS + 0.37 - 0.12, YS + 0.37 + 0.12, 0.0, 0.035)


def tower_box(cx):
    return (cx - 0.105, cx + 0.105, YS - 0.30, YS + 0.13, 0.0, 0.462)


def closed_boxes(name):
    if name == "toc_pc_1":
        return [mon_box(0.0, CZ1), (-0.13, 0.13, YS - 0.17, YS + 0.05, 0.0, CZ1 - 0.1), tower_box(-0.62), KB_BOX, PAD_BOX]
    if name == "toc_pc_2":
        return [mon_box(PW / 2 + 0.002, CZA), mon_box(-PW / 2 - 0.002, CZA), (-0.06, 0.06, YS - 0.17, YS - 0.05, 0.0, CZA + 0.09),
                tower_box(-0.86), KB_BOX, PAD_BOX]
    if name == "toc_pc_3":
        return [mon_box(0.0, CZA), mon_box(PW + 0.004, CZA, ARC, side_pivot(1)), mon_box(-PW - 0.004, CZA, -ARC, side_pivot(-1)),
                (-0.06, 0.06, YS - 0.17, YS - 0.05, 0.0, CZA + 0.09), (-0.615, -0.425, YS + 0.025, YS + 0.215, 0.0, 0.054), KB_BOX, PAD_BOX]
    if name == "toc_pc_curved":
        cz = 0.11 + 0.014 + P49[1] / 2
        R = CURVE_R
        At = (P49[0] / 2 + 0.008) / R
        z0, z1 = cz - P49[1] / 2 - 0.014, cz + P49[1] / 2 + 0.008
        boxes = []
        for k in range(4):
            a0, a1 = -At + 2 * At * k / 4, -At + 2 * At * (k + 1) / 4
            am = (a0 + a1) / 2
            # Boîte convexe tangente à l'arc : corde avant, épaisseur vers l'extérieur.
            pts = []
            for a in (a0, a1):
                for r in (R * math.cos((a1 - a0) / 2) - 0.001, R + 0.02):
                    for z in (z0, z1):
                        rr = r / math.cos(a - am) if r < R else r
                        pts.append((rr * math.sin(a), YS + R - rr * math.cos(a), z))
            boxes.append(("pts", pts))
        return boxes + [(-0.17, 0.17, YS - 0.17, YS + 0.05, 0.0, cz - 0.1), tower_box(-0.80), KB_BOX, PAD_BOX]
    raise KeyError(name)


MODELS = {
    "toc_pc_1": (make_pc_1, 16.0),
    "toc_pc_2": (make_pc_2, 22.0),
    "toc_pc_3": (make_pc_3, 21.0),
    "toc_pc_curved": (make_pc_curved, 23.0),
}


def write_model(name):
    build, mass = MODELS[name]
    lods = [T.visual_lod(build(d), res) for d, res in ((0, 0.0), (1, 2.0), (2, 6.0))]
    boxes = closed_boxes(name)
    lods.append(T.closed_lod(boxes, 10000.0))
    lods.append(T.closed_lod(boxes, 1e13, components=True, mass_total=mass, props=(("autocenter", "0"),)))
    lods.append(T.closed_lod(boxes, 6e15, components=True))
    lods.append(T.closed_lod(boxes, 7e15, components=True))
    out = os.path.join(T.DATA, name + ".p3d")
    with open(out, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, len(lods)) + b"".join(lods))
    m0 = build(0)
    print("écrit %s : %d faces (LOD 0), %d / %d faces (LOD 1 / 2), sélections %s" % (
        out, len(m0.faces), len(build(1).faces), len(build(2).faces), sorted(m0.sel_pts)))
    return m0


def view(m, back=False, fov=36.0, aspect=910 / 512):
    """Trois quarts face depuis la gauche de l'opérateur, en plongée (clavier et souris visibles)."""
    P = np.array(m.pts)
    lo, hi = P.min(0), P.max(0)
    c = (lo + hi) / 2
    d = np.array([0.5, -1.0 if back else 1.0, 0.55])
    d /= np.linalg.norm(d)
    # La profondeur (clavier devant, tour derrière) compte en hauteur d'image sous cette plongée.
    half = max((hi[0] - lo[0]) / 2 + (hi[1] - lo[1]) * 0.25, ((hi[2] - lo[2]) / 2 + (hi[1] - lo[1]) * 0.3) * aspect)
    dist = half / math.tan(math.radians(fov) / 2) * 0.95
    return tuple(c + d * dist), tuple(c)


def previews(models, render_dir=""):
    for name, m in models.items():
        sels = sorted({s for f in m.faces for s in f[4]})
        if name == "toc_pc_curved":
            # Une seule image 32:9 coupée en deux : la dalle doit se lire d'un seul tenant.
            wide = np.concatenate([T.demo_content(seed=3), T.demo_content(seed=4)], axis=1)
            half = wide.shape[1] // 2
            cont = {"panel1": wide[:, :half], "panel2": wide[:, half:]}
        else:
            cont = {s: T.demo_content(seed=i) for i, s in enumerate(sels)}
        eye, tgt = view(m)
        img = T.downsample(T.render(m, eye, tgt, fov=36.0, content=cont))
        Image.fromarray((img * 255 + 0.5).astype(np.uint8)).save(os.path.join(T.DATA, name + "_preview.jpg"), quality=88)
        if render_dir:
            big = T.downsample(T.render(m, eye, tgt, size=(2400, 1350), fov=36.0, content=cont))
            Image.fromarray((big * 255 + 0.5).astype(np.uint8)).save(os.path.join(render_dir, name + ".png"))
            eye, tgt = view(m, back=True)
            big = T.downsample(T.render(m, eye, tgt, size=(1600, 900), fov=36.0, content=cont))
            Image.fromarray((big * 255 + 0.5).astype(np.uint8)).save(os.path.join(render_dir, name + "_dos.png"))
    print("aperçus Eden :", len(models))


def main():
    tmp = tempfile.mkdtemp(prefix="toc_pc_tex_")
    T.TMP_TEX = tmp
    try:
        T.textures(tmp)     # PNG du cadre pour l'aperçu seulement (les PAA du mod ne sont pas réécrits)
        models = {name: write_model(name) for name in MODELS}
        if not os.environ.get("NO_PREVIEW"):
            rd = os.environ.get("RENDER_DIR", "")
            if rd:
                os.makedirs(rd, exist_ok=True)
            previews(models, rd)
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
