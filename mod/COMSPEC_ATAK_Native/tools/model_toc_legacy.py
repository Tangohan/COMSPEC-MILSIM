"""
Refonte des premiers écrans TOC (demande de TTRD, 11/11 : « des vieux écrans TOC horriblement laids ») : téléviseur,
écrans sur trépied, moniteurs de bureau, toiles de projection, vidéoprojecteur, PC portable du poste. Ils étaient des
objets du jeu de base réhabillés ; ce sont maintenant des modèles du mod, écrits avec les primitives, les matières et le
moteur d'aperçu de model_toc_screens.py (même qualité : bords fins biseautés, coque arrière, pieds, LOD 0 / 2 / 6,
ombre, géométrie, tir, vue, dalles en sélections nommées aux UV 0..1). Les classes gardent leur nom (missions et
sauvegardes) : COMSPEC_ATAK_Screen_Tripod, _Dual, _Desk, _Wall, COMSPEC_ATAK_ProjScreen, _ProjScreen_Wall,
COMSPEC_ATAK_Projector, COMSPEC_ATAK_TocLaptop (toc_screens_legacy.hpp).

Repère de construction : X à droite, Y vers l'avant (face de l'écran), Z vers le haut, sol (ou plateau de table) en Z = 0.
Origine : comme les objets du jeu qu'ils remplacent, ces modèles sont centrés par le moteur (pas de propriété
autocenter=0) : l'origine est le milieu de la boîte englobante, la position d'un objet déjà posé dans une mission reste
juste si la hauteur hors tout est celle de l'ancien objet (hauteurs visées ci-dessous, estimées : le jeu n'a pas été
lancé). Seul le vidéoprojecteur de plafond (classe nouvelle) a son origine sur le dessus de la platine de plafond.

  toc_tv_55 / toc_tv_75         téléviseur mural 55" (1,2096 x 0,6804 m) / 75" (1,6602 x 0,9339 m), support mural plat
  toc_tripod_65                 65" de terrain (1,4390 x 0,8094 m) sur trépied : cadre renforcé, pare-chocs, poignées ; 2,00 m
  toc_tripod_dual_43            deux 43" (0,9520 x 0,5355 m) côte à côte sur une traverse : screen (gauche), screen2 ; 1,90 m
  toc_desk_27 / _27x2 / _27x3   moniteur 27" (0,5977 x 0,3362 m) sur pied ; double et triple sur bras (panel1.. de gauche à
                                droite, latéraux du triple tournés de 20°)
  toc_proj_screen_wall          toile tendue 100" (2,214 x 1,245 m) à cadre fin, murale
  toc_proj_screen_tripod        toile 80" (1,771 x 0,996 m) déroulée de son carter, sur trépied ; 2,12 m
  toc_projector / _ceiling      vidéoprojecteur (0,34 x 0,26 x 0,10 m), objectif centré ; posé, ou suspendu à une potence
  toc_laptop                    portable durci ouvert (0,36 x 0,27 m, écran 13,3"), sélection « screen »
Toiles : l'image n'est pas sur la toile mais sur la surface que fn_projector pose devant elle (mesurée par des rayons sur
la géométrie : la toile est une boîte plane, le mât du trépied est à plus de 10 cm derrière).

    python model_toc_legacy.py        # modèles, matière de toile, aperçus (RENDER_DIR=dossier : grands rendus ; ONLY=préfixe)
"""
import math
import os
import shutil
import struct
import sys
import tempfile

import numpy as np
from PIL import Image

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import model_toc_screens as T  # noqa: E402

DATA = T.DATA
P55 = T.T55
P75 = (1.6602, 0.9339)
P65 = T.P65
P43 = (0.9520, 0.5355)
P27 = (0.5977, 0.3362)
P13 = (0.2944, 0.1656)
C100 = (2.214, 1.245)
C80 = (1.771, 0.996)

# Matières en plus : toile blanche mate, verre d'objectif (verre éteint), clavier.
T.MATS["canvas"] = ("#(argb,8,8,3)color(0.84,0.84,0.82,1,co)", "toc_canvas.rvmat", 1.0)
T.MATS["key"] = ("#(argb,8,8,3)color(0.05,0.052,0.055,1,co)", "toc_plastic.rvmat", 1.0)


def rvmat_canvas():
    with open(os.path.join(DATA, "toc_canvas.rvmat"), "w", encoding="utf-8", newline="\n") as f:
        f.write("// Toiles de projection (tools/model_toc_legacy.py) : toile blanche mate, sans reflet.\n"
                "ambient[]={1,1,1,1};\ndiffuse[]={1,1,1,1};\nforcedDiffuse[]={0,0,0,0};\nemmisive[]={0,0,0,1};\n"
                "specular[]={0.02,0.02,0.02,1};\nspecularPower=8;\nPixelShaderID=\"Normal\";\nVertexShaderID=\"Basic\";\n")


# --------------------------------------------------------------------------------------------------------------------
# Outils
# --------------------------------------------------------------------------------------------------------------------
def transform(sub, fp):
    """Déplacement rigide d'un maillage (normales recalculées)."""
    sub.pts = [fp(p) for p in sub.pts]
    faces = []
    for idx, uvs, n, mat, sels in sub.faces:
        faces.append((idx, uvs, T.vnorm(T.newell([sub.pts[i] for i in idx])), mat, sels))
    sub.faces = faces
    return sub


def rot_z(p, a, pivot):
    x, y = p[0] - pivot[0], p[1] - pivot[1]
    c, s = math.cos(a), math.sin(a)
    return (pivot[0] + x * c - y * s, pivot[1] + x * s + y * c, p[2])


def obox(p0, p1, r):
    """Boîte fermée le long d'un segment (pied incliné), demi-côté r : 8 points dans l'ordre de hull_box."""
    d = T.vnorm(T.vsub(p1, p0))
    a = (1.0, 0.0, 0.0) if abs(d[0]) < 0.9 else (0.0, 1.0, 0.0)
    u = T.vnorm(T.vcross(d, a))
    v = T.vcross(d, u)
    pts = []
    for e in (p0, p1):
        for su in (-1, 1):
            for sv in (-1, 1):
                pts.append(tuple(e[k] + r * (su * u[k] + sv * v[k]) for k in range(3)))
    return ("pts", pts)


def tripod(m, detail, top, spread, collar, mast_y=0.0, r=0.021):
    """Trépied : mât télescopique jusqu'à top, trois jambes du collier (hauteur collar) aux patins (rayon spread), une
    jambe vers l'arrière, entretoises basses. Rend les segments des jambes (boîtes fermées)."""
    n = 12 if detail == 0 else 8 if detail == 1 else 6
    T.cylinder(m, (0, mast_y, 0.16), (0, mast_y, collar + 0.25), 0.03, "steel", n=n)
    T.cylinder(m, (0, mast_y, collar + 0.25), (0, mast_y, top), 0.022, "frame", n=n)
    if detail < 2:
        T.cylinder(m, (0, mast_y, collar - 0.04), (0, mast_y, collar + 0.04), 0.045, "plastic", n=n)      # collier des jambes
        T.cylinder(m, (0, mast_y, collar + 0.22), (0, mast_y, collar + 0.28), 0.038, "plastic", n=n)      # bague de serrage
        T.cylinder(m, (0, mast_y, 0.20), (0, mast_y, 0.26), 0.04, "plastic", n=n)                         # coulisseau des entretoises
    if detail == 0:
        T.cylinder(m, (0.036, mast_y, collar + 0.25), (0.085, mast_y, collar + 0.25), 0.011, "rubber", n=8)  # molette
    legs = []
    for ang in (-90, 30, 150):
        a = math.radians(ang)
        foot = (spread * math.cos(a), mast_y + spread * math.sin(a), 0.02)
        hip = (0.04 * math.cos(a), mast_y + 0.04 * math.sin(a), collar)
        T.cylinder(m, hip, foot, r, "steel", n=n, caps=False)
        mid = tuple(hip[k] + (foot[k] - hip[k]) * 0.55 for k in range(3))
        low = (0.035 * math.cos(a), mast_y + 0.035 * math.sin(a), 0.23)
        if detail < 2:
            T.cylinder(m, low, mid, 0.012, "steel", n=6, caps=False)
            T.cylinder(m, (foot[0], foot[1], 0.0), (foot[0], foot[1], 0.035), 0.034, "rubber", n=n)       # patin
        legs.append((hip, foot))
    return legs


def tripod_boxes(legs, top, collar, mast_y=0.0):
    return [(-0.035, 0.035, mast_y - 0.035, mast_y + 0.035, 0.16, top)] + [obox(h, (f[0], f[1], 0.0), 0.03) for h, f in legs]


# --------------------------------------------------------------------------------------------------------------------
# Modèles : chacun rend (maillage, boîtes fermées)
# --------------------------------------------------------------------------------------------------------------------
def make_tv(size, detail):
    m = T.Mesh()
    aw, ah = size
    bz = (0.008, 0.008, 0.014, 0.008)
    cz = ah / 2 + 0.014
    T.wall_plate(m, 0.0, cz, aw * 0.42, ah * 0.42, 0.024, detail)
    T.back_casing(m, 0.0, cz, aw * 0.62, ah * 0.62, 0.024, 0.046, detail)
    T.panel(m, 0.0, cz, aw, ah, bz, 0.046, 0.082, detail, "screen")
    if detail == 0:
        T.power_led(m, aw / 2 - 0.04, cz - ah / 2 - 0.007, 0.082)
    return m, [(-aw / 2 - 0.008, aw / 2 + 0.008, 0.0, 0.082, 0.0, ah + 0.022)]


def rugged(m, cx, cz, aw, ah, y0, y1, detail, sel):
    """Dalle de terrain : cadre épais, pare-chocs caoutchouc aux coins, deux poignées au dos."""
    bz = (0.024, 0.024, 0.03, 0.024)
    T.panel(m, cx, cz, aw, ah, bz, y0, y1, detail, sel, bevel=0.005, body="plastic", ring="frame")
    T.back_casing(m, cx, cz, aw * 0.7, ah * 0.66, y0 - 0.03, y0, detail)
    x0, x1, z0, z1 = cx - aw / 2 - 0.024, cx + aw / 2 + 0.024, cz - ah / 2 - 0.03, cz + ah / 2 + 0.024
    if detail < 2:
        for sx in (-1, 1):
            for sz in (-1, 1):
                x = x0 if sx < 0 else x1
                z = z0 if sz < 0 else z1
                xa, xb = sorted((x - sx * 0.07, x + sx * 0.008))
                za, zb = sorted((z - sz * 0.07, z + sz * 0.008))
                T.box(m, xa, xb, y0 - 0.006, y1 + 0.006, za, zb, "rubber", b=0.006 if detail == 0 else 0)
    if detail == 0:
        for sx in (-1, 1):
            x = cx + sx * (aw / 2 - 0.05)
            for zz in (cz - 0.09, cz + 0.09):
                T.box(m, x - 0.012, x + 0.012, y0 - 0.05, y0, zz - 0.012, zz + 0.012, "steel", b=0.003)
            T.box(m, x - 0.014, x + 0.014, y0 - 0.064, y0 - 0.044, cz - 0.105, cz + 0.105, "rubber", b=0.005)
        T.power_led(m, cx + aw / 2 - 0.05, cz - ah / 2 - 0.016, y1)
    return (x0 - 0.008, x1 + 0.008, y0 - 0.03, y1 + 0.006, z0 - 0.008, z1 + 0.008)


def make_tripod_65(detail):
    m = T.Mesh()
    aw, ah = P65
    cz = 1.13 + ah / 2
    top = cz + 0.2
    legs = tripod(m, detail, top, 0.62, 0.74, mast_y=-0.07)
    bb = rugged(m, 0.0, cz, aw, ah, 0.0, 0.05, detail, "screen")
    cb = 0.003 if detail == 0 else 0
    # Platine VESA et étrier sur le mât.
    T.box(m, -0.22, 0.22, -0.045, -0.03, cz - 0.22, cz + 0.22, "steel", b=cb)
    T.box(m, -0.05, 0.05, -0.11, -0.045, cz - 0.12, cz + 0.12, "steel", b=cb)
    return m, [bb] + tripod_boxes(legs, top, 0.74, -0.07)


def make_tripod_dual(detail):
    m = T.Mesh()
    aw, ah = P43
    cz = 1.34 + ah / 2
    top = cz + 0.1
    legs = tripod(m, detail, top, 0.62, 0.74, mast_y=-0.07)
    cb = 0.003 if detail == 0 else 0
    bz = (0.008, 0.008, 0.014, 0.008)
    off = aw / 2 + 0.008 + 0.012
    boxes = []
    for sx, sel in ((1, "screen"), (-1, "screen2")):      # gauche vue de face = +X
        cx = sx * off
        T.back_casing(m, cx, cz, aw * 0.6, ah * 0.62, -0.022, 0.0, detail)
        T.panel(m, cx, cz, aw, ah, bz, 0.0, 0.038, detail, sel)
        T.box(m, cx - 0.11, cx + 0.11, -0.036, -0.022, cz - 0.11, cz + 0.11, "steel", b=cb)
        if detail == 0:
            T.power_led(m, cx + aw / 2 - 0.04, cz - ah / 2 - 0.007, 0.038)
        boxes.append((cx - aw / 2 - 0.008, cx + aw / 2 + 0.008, -0.022, 0.038, cz - ah / 2 - 0.014, cz + ah / 2 + 0.008))
    # Traverse et étrier.
    T.box(m, -off - 0.14, off + 0.14, -0.066, -0.036, cz - 0.03, cz + 0.03, "steel", b=cb)
    T.box(m, -0.05, 0.05, -0.11, -0.066, cz - 0.08, cz + 0.08, "steel", b=cb)
    return m, boxes + tripod_boxes(legs, top, 0.74, -0.07)


def make_desk(n, detail):
    m = T.Mesh()
    aw, ah = P27
    bz = (0.006, 0.006, 0.012, 0.006)
    cb = 0.003 if detail == 0 else 0
    cz = 0.115 + 0.012 + ah / 2
    pw = aw + 0.012 + 0.006
    boxes = []
    # Pied : socle acier, colonne, tête ; bras horizontal pour deux ou trois moniteurs.
    bw, bd = (0.25, 0.2) if n == 1 else (0.38, 0.24)
    T.box(m, -bw / 2, bw / 2, -0.14, -0.14 + bd, 0.0, 0.012, "steel", b=0.004 if detail == 0 else 0)
    T.box(m, -0.03, 0.03, -0.075, -0.05, 0.012, cz + 0.06, "frame", b=cb)
    boxes.append((-bw / 2, bw / 2, -0.14, -0.14 + bd, 0.0, 0.012))
    boxes.append((-0.03, 0.03, -0.075, -0.05, 0.012, cz + 0.06))
    if n > 1:
        half = pw / 2 + 0.06 if n == 2 else pw / 2
        T.box(m, -half, half, -0.05, -0.03, cz - 0.02, cz + 0.02, "frame", b=cb)
    ang = math.radians(20)
    for i in range(n):
        k = i - (n - 1) / 2            # -1, 0, 1 : gauche vue de face = +X pour k < 0
        sub = T.Mesh()
        T.back_casing(sub, 0.0, cz, aw * 0.5, ah * 0.55, -0.018, 0.0, detail, vents=False)
        T.panel(sub, 0.0, cz, aw, ah, bz, 0.0, 0.022, detail, "screen" if n == 1 else "panel%d" % (i + 1), bevel=0.002)
        T.box(sub, -0.05, 0.05, -0.03, -0.018, cz - 0.05, cz + 0.05, "steel", b=cb)
        if detail == 0:
            T.power_led(sub, aw / 2 - 0.03, cz - ah / 2 - 0.006, 0.022)
        hb = [(x, y, z) for x in (-aw / 2 - 0.006, aw / 2 + 0.006) for y in (-0.018, 0.022) for z in (cz - ah / 2 - 0.012, cz + ah / 2 + 0.006)]
        cx = -k * pw
        if n == 3 and k != 0:
            # Latéral tourné de 20° vers l'opérateur autour de son bord intérieur.
            sx = 1 if cx > 0 else -1
            piv = (-sx * pw / 2, 0.0, 0.0)
            # Bras du latéral, articulé au bout de la traverse centrale.
            xa, xb = sorted((-sx * pw / 2, sx * 0.06))
            T.box(sub, xa, xb, -0.05, -0.03, cz - 0.02, cz + 0.02, "frame", b=cb)
            f = lambda p, sx=sx, piv=piv, cx=cx: (lambda q: (q[0] + cx, q[1], q[2]))(rot_z(p, sx * ang, piv))
        else:
            f = lambda p, cx=cx: (p[0] + cx, p[1], p[2])
        transform(sub, f)
        m.merge(sub)
        boxes.append(("pts", [f(p) for p in hb]))
    return m, boxes


def make_proj_wall(detail):
    m = T.Mesh()
    aw, ah = C100
    fr = 0.045
    cb = 0.004 if detail == 0 else 0
    W, H = aw + 2 * fr, ah + 2 * fr
    # Dos, cadre noir fin (quatre profilés), toile tendue en retrait de 6 mm.
    T.box(m, -aw / 2 - 0.005, aw / 2 + 0.005, 0.012, 0.024, fr - 0.005, fr + ah + 0.005, "plastic", skip=("+y",))
    for (x0, x1, z0, z1) in ((-W / 2, W / 2, 0.0, fr), (-W / 2, W / 2, fr + ah, H), (-W / 2, -aw / 2, fr, fr + ah), (aw / 2, W / 2, fr, fr + ah)):
        T.box(m, x0, x1, 0.008, 0.04, z0, z1, "frame", b=cb)
    m.poly([(-aw / 2, 0.034, fr), (aw / 2, 0.034, fr), (aw / 2, 0.034, fr + ah), (-aw / 2, 0.034, fr + ah)], "canvas", ("canvas",),
           uvs=[(1, 1), (0, 1), (0, 0), (1, 0)], hint=(0, 1, 0))
    if detail < 2:
        for sx in (-1, 1):
            for z in (H * 0.2, H * 0.8):
                T.box(m, sx * aw * 0.3 - 0.04, sx * aw * 0.3 + 0.04, 0.0, 0.012, z - 0.03, z + 0.03, "steel", b=0.002 if detail == 0 else 0)
    return m, [(-W / 2, W / 2, 0.0, 0.04, 0.0, H)]


def make_proj_tripod(detail):
    m = T.Mesh()
    aw, ah = C80
    my = -0.13
    z0 = 1.02
    top = z0 + 0.075 + ah + 0.04
    legs = tripod(m, detail, top + 0.02, 0.56, 0.6, mast_y=my, r=0.017)
    cb = 0.006 if detail == 0 else 0
    W = aw + 0.07
    # Carter (tube où la toile s'enroule), embouts, toile à bords noirs, barre haute et crochet au mât.
    T.box(m, -W / 2, W / 2, -0.04, 0.04, z0, z0 + 0.075, "frame", b=cb * 1.5)
    if detail < 2:
        for sx in (-1, 1):
            T.box(m, sx * W / 2 - 0.012, sx * W / 2 + 0.012, -0.045, 0.045, z0 - 0.005, z0 + 0.08, "plastic", b=cb)
        T.box(m, -0.04, 0.04, my, -0.04, z0 + 0.015, z0 + 0.06, "steel", b=0.003 if detail == 0 else 0)      # patte du carter au mât
    zc0 = z0 + 0.075
    T.box(m, -aw / 2 - 0.03, aw / 2 + 0.03, 0.0, 0.004, zc0, zc0 + ah + 0.03, "plastic", skip=("+y",))
    yb = 0.0045
    for (x0, x1, a0, a1) in ((-aw / 2 - 0.03, -aw / 2, zc0, zc0 + ah + 0.03), (aw / 2, aw / 2 + 0.03, zc0, zc0 + ah + 0.03), (-aw / 2, aw / 2, zc0 + ah, zc0 + ah + 0.03)):
        m.poly([(x0, yb, a0), (x1, yb, a0), (x1, yb, a1), (x0, yb, a1)], "plastic", hint=(0, 1, 0))
    m.poly([(-aw / 2, yb, zc0), (aw / 2, yb, zc0), (aw / 2, yb, zc0 + ah), (-aw / 2, yb, zc0 + ah)], "canvas", ("canvas",),
           uvs=[(1, 1), (0, 1), (0, 0), (1, 0)], hint=(0, 1, 0))
    T.box(m, -aw / 2 - 0.035, aw / 2 + 0.035, -0.012, 0.012, zc0 + ah + 0.03, zc0 + ah + 0.052, "frame", b=cb * 0.6)
    T.box(m, -0.012, 0.012, my, -0.012, zc0 + ah + 0.034, zc0 + ah + 0.048, "steel")                         # potence du crochet
    boxes = [(-aw / 2 - 0.035, aw / 2 + 0.035, -0.012, 0.012, zc0, zc0 + ah + 0.052), (-W / 2, W / 2, -0.04, 0.04, z0, z0 + 0.075)]
    return m, boxes + tripod_boxes(legs, top + 0.02, 0.6, my)


def projector_body(m, detail, z0):
    """Boîtier (bas en z0), objectif centré vers +Y, grilles, commandes."""
    w, d, h = 0.34, 0.26, 0.095
    T.box(m, -w / 2, w / 2, -d / 2, d / 2, z0, z0 + h, "plastic", b=0.012 if detail < 2 else 0)
    n = 16 if detail == 0 else 10 if detail == 1 else 6
    zl = z0 + h / 2
    T.cylinder(m, (0, d / 2 - 0.004, zl), (0, d / 2 + 0.03, zl), 0.043, "frame", n=n)
    if detail < 2:
        T.cylinder(m, (0, d / 2 + 0.03, zl), (0, d / 2 + 0.042, zl), 0.049, "rubber", n=n)               # bague de mise au point
        T.cylinder(m, (0, d / 2 + 0.03, zl), (0, d / 2 + 0.0435, zl), 0.036, "screen", n=n)              # lentille
    if detail == 0:
        # Grilles : flancs et arrière, 0,5 mm en saillie.
        vh = h * 0.5
        for sx in (-1, 1):
            x = sx * (w / 2 + 0.0005)
            pts = [(x, -d * 0.32, zl - vh / 2), (x, d * 0.32, zl - vh / 2), (x, d * 0.32, zl + vh / 2), (x, -d * 0.32, zl + vh / 2)]
            m.poly(pts, "vent", uvs=[(0, vh / 0.08), (d * 0.64 / 0.08, vh / 0.08), (d * 0.64 / 0.08, 0), (0, 0)], hint=(sx, 0, 0))
        y = -d / 2 - 0.0005
        pts = [(-w * 0.3, y, zl - vh / 2), (w * 0.05, y, zl - vh / 2), (w * 0.05, y, zl + vh / 2), (-w * 0.3, y, zl + vh / 2)]
        m.poly(pts, "vent", uvs=[(0, vh / 0.08), (w * 0.35 / 0.08, vh / 0.08), (w * 0.35 / 0.08, 0), (0, 0)], hint=(0, -1, 0))
        # Connectique arrière (bandeau), capteur avant.
        T.box(m, w * 0.12, w * 0.4, -d / 2 - 0.002, -d / 2 + 0.002, zl - 0.02, zl + 0.02, "key")
        T.box(m, -w * 0.33, -w * 0.27, d / 2 - 0.002, d / 2 + 0.0015, zl - 0.008, zl + 0.008, "screen")
    return w, d, h


def make_projector(detail):
    m = T.Mesh()
    w, d, h = projector_body(m, detail, 0.018)
    if detail < 2:
        for (x, y) in ((0.0, d / 2 - 0.035), (-w / 2 + 0.04, -d / 2 + 0.04), (w / 2 - 0.04, -d / 2 + 0.04)):
            T.cylinder(m, (x, y, 0.0), (x, y, 0.02), 0.014, "rubber", n=8)
    if detail == 0:
        zt = 0.018 + h
        for i in range(4):
            T.box(m, 0.07 + i * 0.022, 0.085 + i * 0.022, -0.07, -0.055, zt - 0.001, zt + 0.0025, "key", b=0.001)
        T.box(m, 0.07, 0.151, -0.04, -0.036, zt - 0.001, zt + 0.0012, "led")
    return m, [(-w / 2, w / 2, -d / 2, d / 2 + 0.042, 0.0, 0.018 + h)]


def make_projector_ceiling(detail):
    m = T.Mesh()
    cb = 0.002 if detail == 0 else 0
    T.box(m, -0.07, 0.07, -0.07, 0.07, -0.01, 0.0, "steel", b=cb)
    T.cylinder(m, (0, 0, -0.14), (0, 0, -0.01), 0.018, "steel", n=10 if detail == 0 else 6)
    T.box(m, -0.11, 0.11, -0.08, 0.08, -0.152, -0.14, "steel", b=cb)
    w, d, h = projector_body(m, detail, -0.152 - 0.095)
    if detail == 0:
        for sx in (-1, 1):
            for sy in (-1, 1):
                T.cylinder(m, (sx * 0.05, sy * 0.05, -0.01), (sx * 0.05, sy * 0.05, -0.014), 0.006, "frame", n=6)
    return m, [(-w / 2, w / 2, -d / 2, d / 2 + 0.042, -0.247, -0.14), (-0.07, 0.07, -0.07, 0.07, -0.14, 0.0)]


def make_laptop(detail):
    m = T.Mesh()
    w, d, hb = 0.36, 0.27, 0.034
    cb = 0.006 if detail == 0 else 0.004 if detail == 1 else 0
    # Base durcie, pare-chocs, poignée de transport côté opérateur (+Y), clavier, pavé tactile.
    T.box(m, -w / 2, w / 2, -d / 2, d / 2, 0.0, hb, "plastic", b=cb)
    if detail < 2:
        for sx in (-1, 1):
            for sy in (-1, 1):
                xa, xb = sorted((sx * w / 2 - sx * 0.022, sx * w / 2 + sx * 0.005))
                ya, yb = sorted((sy * d / 2 - sy * 0.022, sy * d / 2 + sy * 0.005))
                T.box(m, xa, xb, ya, yb, -0.002, hb + 0.002, "rubber", b=0.004 if detail == 0 else 0)
        T.box(m, -0.07, 0.07, d / 2, d / 2 + 0.022, 0.006, 0.026, "rubber", b=0.006 if detail == 0 else 0)
    zk = hb + 0.0005
    kx0, kx1, ky0, ky1 = -w / 2 + 0.03, w / 2 - 0.03, -d / 2 + 0.03, 0.03
    m.poly([(kx0, ky0, zk), (kx1, ky0, zk), (kx1, ky1, zk), (kx0, ky1, zk)], "key", hint=(0, 0, 1))
    m.poly([(-0.05, 0.05, zk), (0.05, 0.05, zk), (0.05, 0.11, zk), (-0.05, 0.11, zk)], "key", hint=(0, 0, 1))
    if detail == 0:
        rows, cols = 5, 13
        kw, kd = (kx1 - kx0) / cols, (ky1 - ky0) / rows
        for r in range(rows):
            for c in range(cols):
                x0, y0 = kx0 + c * kw, ky0 + r * kd
                T.box(m, x0 + 0.0025, x0 + kw - 0.0025, y0 + 0.0025, y0 + kd - 0.0025, zk, zk + 0.0022, "rubber", skip=("-z",))
        T.box(m, w / 2 - 0.06, w / 2 - 0.045, 0.06, 0.064, zk, zk + 0.001, "led")
    # Couvercle ouvert à 110°, charnières à l'arrière.
    aw, ah = P13
    lid = T.Mesh()
    lh = d - 0.02
    T.panel(lid, 0.0, hb + 0.012 + lh / 2, aw, ah, ((w - aw) / 2, (w - aw) / 2, (lh - ah) / 2 + 0.008, (lh - ah) / 2 - 0.008), -0.02, 0.0, detail, "screen",
            bevel=0.004, body="plastic", ring="plastic")
    if detail < 2:
        for sx in (-1, 1):
            xa, xb = sorted((sx * w / 2 - sx * 0.018, sx * w / 2 + sx * 0.004))
            T.box(lid, xa, xb, -0.023, 0.003, hb + 0.012 + lh - 0.03, hb + 0.012 + lh + 0.003, "rubber", b=0.003 if detail == 0 else 0)
    a = math.radians(-20)
    piv = (0.0, -0.01, hb + 0.012)
    hb8 = [(x, y, z) for x in (-w / 2, w / 2) for y in (-0.02, 0.0) for z in (hb + 0.012, hb + 0.012 + lh)]
    shift = lambda p: (p[0], p[1] - d / 2 + 0.02, p[2])
    f = lambda p: shift(T.rot_x(p, a, piv))
    transform(lid, f)
    m.merge(lid)
    if detail < 2:
        for sx in (-1, 1):
            T.cylinder(m, (sx * 0.1 - 0.025, -d / 2 + 0.01, hb + 0.008), (sx * 0.1 + 0.025, -d / 2 + 0.01, hb + 0.008), 0.009, "steel", n=8)
    return m, [(-w / 2, w / 2, -d / 2, d / 2 + 0.022, 0.0, hb), ("pts", [f(p) for p in hb8])]


# nom -> (constructeur(detail) -> (maillage, boîtes), masse, centré par le moteur)
MODELS = {
    "toc_tv_55": (lambda d: make_tv(P55, d), 18.0, True),
    "toc_tv_75": (lambda d: make_tv(P75, d), 34.0, True),
    "toc_tripod_65": (make_tripod_65, 38.0, True),
    "toc_tripod_dual_43": (make_tripod_dual, 34.0, True),
    "toc_desk_27": (lambda d: make_desk(1, d), 6.0, True),
    "toc_desk_27x2": (lambda d: make_desk(2, d), 12.0, True),
    "toc_desk_27x3": (lambda d: make_desk(3, d), 17.0, True),
    "toc_proj_screen_wall": (make_proj_wall, 14.0, True),
    "toc_proj_screen_tripod": (make_proj_tripod, 11.0, True),
    "toc_projector": (make_projector, 3.5, True),
    "toc_projector_ceiling": (make_projector_ceiling, 5.0, False),
    "toc_laptop": (make_laptop, 3.0, True),
}


def write_model(name):
    build, mass, centered = MODELS[name]
    lods = [T.visual_lod(build(d)[0], res) for d, res in ((0, 0.0), (1, 2.0), (2, 6.0))]
    m0, boxes = build(0)
    props = () if centered else (("autocenter", "0"),)
    lods.append(T.closed_lod(boxes, 10000.0))
    lods.append(T.closed_lod(boxes, 1e13, components=True, mass_total=mass, props=props))
    lods.append(T.closed_lod(boxes, 6e15, components=True))
    lods.append(T.closed_lod(boxes, 7e15, components=True))
    out = os.path.join(DATA, name + ".p3d")
    with open(out, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, len(lods)) + b"".join(lods))
    P = np.array(m0.pts)
    lo, hi = P.min(0), P.max(0)
    print("écrit %s : %d faces (LOD 0), hors tout %.3f x %.3f x %.3f m (l x p x h)" % (name, len(m0.faces), hi[0] - lo[0], hi[1] - lo[1], hi[2] - lo[2]))
    return m0


def for_preview(m):
    """Toile : rendue comme une dalle au contenu blanc (le moteur d'aperçu ne connaît que les matières du cadre)."""
    out = T.Mesh()
    out.pts = list(m.pts)
    out.faces = [(idx, uvs, n, "plate" if mat == "canvas" else "rubber" if mat == "key" else mat, sels) for idx, uvs, n, mat, sels in m.faces]
    return out


def previews(models, render_dir=""):
    yy = np.linspace(0.93, 0.8, 90)[:, None, None]
    white = np.ones((90, 160, 3)) * yy
    for name, m in models.items():
        pm = for_preview(m)
        eye, tgt = T.frame_view(pm, below="ceiling" in name)
        if name == "toc_laptop" or name.startswith("toc_projector") and "ceiling" not in name or name.startswith("toc_desk"):
            # Petits objets de table : vus d'un peu plus haut.
            e = np.array(eye) - np.array(tgt)
            eye = tuple(np.array(tgt) + np.array([e[0], e[1], abs(e[2]) * 2.2]))
        sels = sorted({s for f in pm.faces for s in f[4]})
        cont = {s: (white if s == "canvas" else T.demo_content(seed=i)) for i, s in enumerate(sels)}
        img = T.downsample(T.render(pm, eye, tgt, content=cont))
        Image.fromarray((img * 255 + 0.5).astype(np.uint8)).save(os.path.join(DATA, name + "_preview.jpg"), quality=88)
        if render_dir:
            big = T.downsample(T.render(pm, eye, tgt, size=(2000, 1124), content=cont))
            Image.fromarray((big * 255 + 0.5).astype(np.uint8)).save(os.path.join(render_dir, name + ".png"))
            back = (2 * tgt[0] - eye[0], 2 * tgt[1] - eye[1], eye[2])
            big = T.downsample(T.render(pm, back, tgt, size=(1600, 900), content=cont))
            Image.fromarray((big * 255 + 0.5).astype(np.uint8)).save(os.path.join(render_dir, name + "_dos.png"))
    print("aperçus Eden :", len(models))


def main():
    tmp = tempfile.mkdtemp(prefix="toc_tex_")
    T.TMP_TEX = tmp
    try:
        T.textures(tmp)          # PNG du cadre pour les aperçus seulement (les PAA du mod ne sont pas réécrits)
        rvmat_canvas()
        only = [x for x in os.environ.get("ONLY", "").split(",") if x]
        models = {name: write_model(name) for name in MODELS if not only or any(name.startswith(x) for x in only)}
        if not os.environ.get("NO_PREVIEW"):
            previews(models, os.environ.get("RENDER_DIR", ""))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
