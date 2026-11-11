"""
Écrans de salle d'opérations (TOC / JOC) : murs d'images, écrans géants, écran suspendu, bandeau horloge, plaque de nom.
Modèles MLOD écrits en pur Python (même format que model_projsurface.py, sans Blender), textures et matières du cadre,
aperçus Eden rendus par un petit moteur logiciel (numpy), dans Sources/addons/main/data/toc/.

Repère : X à droite, Y vers l'avant (côté écran, direction de l'objet dans le jeu), Z vers le haut ; écrit X, Z, Y dans
le P3D. Faces : sens direct vu de l'extérieur dans ce repère (comme model_projsurface.py), normales écrites opposées.

Chaque modèle :
  LOD visuelles 0 / 2 / 6 (détail décroissant : biseaux, ouïes, voyant, câbles, puis boîtes simples),
  ombre 10000 (boîtes fermées, sommets partagés, triangles), géométrie (boîtes convexes ComponentNN, masse, autocenter=0),
  géométrie de tir et de vue (mêmes boîtes).
  Dalle(s) d'écran : sélection(s) nommée(s), quadrilatère(s) à 1 mm derrière le bord du cadre, UV 0..1 exactement sur la
  partie visible, haut de l'image en haut. Matière d'origine : verre éteint (toc_glass_off.rvmat) ; l'image prend
  comspec_screenMat (comspec_screen_on / comspec_screen_night, items) posée par fn_tocScreen « surface ».

Modèles (origine : sol, plan du mur en Y = 0, milieu en X ; écran suspendu : dessus de la platine de plafond) :
  toc_wall_2x2        4 dalles 55" (1,2096 x 0,6804 m visibles, jointure 7 mm), image unique « screen » sur les quatre
                      (UV proportionnelles à la position : l'image passe derrière les jointures, comme un vrai mur)
  toc_wall_2x2_quad   même mur, une sélection par dalle : panel1 panel2 / panel3 panel4 (lecture, index 0 à 3)
  toc_wall_3x2        6 dalles 55" : panel1 panel2 panel3 / panel4 panel5 panel6 (index 0 à 5)
  toc_wall_3x2_mixed  même mur : panel1 = bloc 2x2 de gauche (une image 16:9), panel2 = dalle haut droite,
                      panel3 = dalle bas droite (index 0 à 2). Une image unique sur 3x2 serait en 8:3 (déformée x1,5).
  toc_98_wall         98" (2,1695 x 1,2203 m visibles) sur support mural plat
  toc_98_stand        même dalle sur pied roulant lourd (deux colonnes, pieds à roulettes, tablette)
  toc_65_ceiling      65" (1,4390 x 0,8094 m) sous un tube de plafond, incliné de 10° vers le bas
  toc_clock_strip     bandeau horloge LED, face 1,12 x 0,28 m (4:1), boîtier 1,16 x 0,32 m, milieu à 2,55 m
  toc_name_plate      plaque de nom d'élément, face 0,86 x 0,215 m (4:1), plaque 0,90 x 0,25 m, milieu à 2,55 m

    python model_toc_screens.py           # modèles, textures (hemtt utils paa convert), rvmat, aperçus
    (NO_TEX=1 : sans textures ; NO_PREVIEW=1 : sans aperçus ; RENDER_DIR=dossier : grands rendus de contrôle)
"""
import math
import os
import shutil
import struct
import subprocess
import sys
import tempfile

import numpy as np
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data", "toc")
TEX = "z\\comspec_atak_native\\addons\\main\\data\\toc\\"

# --------------------------------------------------------------------------------------------------------------------
# Dimensions (m)
# --------------------------------------------------------------------------------------------------------------------
T55 = (1.2096, 0.6804)        # dalle 55" 16:9, surface visible
T55_BZ = 0.0035               # bord de dalle de mur d'images (jointure 7 mm entre deux images)
TRIM = 0.012                  # cornière alu autour d'un mur d'images
WALL_Z0 = 0.90                # bas de l'image des murs d'images (sol à 0)
P98 = (2.1695, 1.2203)
P65 = (1.4390, 0.8094)
CLOCK = (1.12, 0.28)
PLATE = (0.86, 0.215)
HIGH_Z = 2.55                 # milieu du bandeau horloge et de la plaque : juste au-dessus d'un mur d'images (haut à 2,31 m)

# Matières : clé -> (texture, rvmat, échelle de projection en m par tuile, couleur de l'aperçu)
MATS = {
    "frame": ("toc_frame_co.paa", "toc_frame.rvmat", 0.5),
    "plastic": ("toc_plastic_co.paa", "toc_plastic.rvmat", 0.5),
    "steel": ("toc_steel_co.paa", "toc_steel.rvmat", 0.5),
    "vent": ("toc_vent_co.paa", "toc_vent.rvmat", 0.08),
    "rubber": ("#(argb,8,8,3)color(0.025,0.025,0.025,1,co)", "toc_plastic.rvmat", 1.0),
    "led": ("#(argb,8,8,3)color(0.1,0.9,0.25,1,co)", "toc_led.rvmat", 1.0),
    "screen": ("#(argb,8,8,3)color(0.012,0.014,0.018,1,co)", "toc_glass_off.rvmat", 1.0),
    "plate": ("#(argb,8,8,3)color(0.06,0.065,0.07,1,co)", "toc_glass_off.rvmat", 1.0),
}


def tex_path(key):
    t = MATS[key][0]
    return t if t.startswith("#") else TEX + t


# --------------------------------------------------------------------------------------------------------------------
# Maillage
# --------------------------------------------------------------------------------------------------------------------
def vsub(a, b):
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def vcross(a, b):
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def vdot(a, b):
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


def vnorm(a):
    n = math.sqrt(vdot(a, a))
    return (a[0] / n, a[1] / n, a[2] / n) if n > 1e-12 else (0.0, 0.0, 0.0)


def newell(pts):
    n = [0.0, 0.0, 0.0]
    for i, p in enumerate(pts):
        q = pts[(i + 1) % len(pts)]
        n[0] += (p[1] - q[1]) * (p[2] + q[2])
        n[1] += (p[2] - q[2]) * (p[0] + q[0])
        n[2] += (p[0] - q[0]) * (p[1] + q[1])
    return tuple(n)


class Mesh:
    """Faces à arêtes vives (sommets propres à chaque face) pour les LOD visuelles ; sommets partagés (weld) pour les
    LOD fermées (ombre, géométrie). Une face : sommets (3 ou 4), UV par coin, matière, sélections."""

    def __init__(self):
        self.pts = []
        self.faces = []      # (indices, uvs, normale, matière, sélections)
        self.sel_pts = {}    # sélection -> indices de points
        self.mass = None

    def point(self, p):
        self.pts.append(tuple(float(c) for c in p))
        return len(self.pts) - 1

    def poly(self, pts, mat, sels=(), uvs=None, hint=None):
        n = newell(pts)
        if vdot(n, n) < 1e-14:
            return  # face dégénérée (biseau nul)
        if hint is not None and vdot(n, hint) < 0:
            pts = list(reversed(pts))
            if uvs is not None:
                uvs = list(reversed(uvs))
            n = (-n[0], -n[1], -n[2])
        n = vnorm(n)
        if uvs is None:
            uvs = project_uv(pts, n, MATS.get(mat, (None, None, 1.0))[2])
        if len(pts) > 4:
            raise ValueError("polygone > 4")
        idx = [self.point(p) for p in pts]
        self.faces.append((idx, list(uvs), n, mat, tuple(sels)))
        for s in sels:
            self.sel_pts.setdefault(s, set()).update(idx)

    def merge(self, other):
        off = len(self.pts)
        self.pts += other.pts
        for idx, uvs, n, mat, sels in other.faces:
            self.faces.append(([i + off for i in idx], uvs, n, mat, sels))
        for s, ids in other.sel_pts.items():
            self.sel_pts.setdefault(s, set()).update(i + off for i in ids)


def project_uv(pts, n, scale):
    """Projection monde (1 tuile = scale m) selon l'axe dominant de la normale ; brossage horizontal (le long de X)."""
    ax = max(range(3), key=lambda i: abs(n[i]))
    out = []
    for p in pts:
        if ax == 1:
            u, v = p[0], -p[2]
        elif ax == 0:
            u, v = p[1], -p[2]
        else:
            u, v = p[0], p[1]
        out.append((u / scale, v / scale))
    return out


# --------------------------------------------------------------------------------------------------------------------
# Primitives
# --------------------------------------------------------------------------------------------------------------------
def box(m, x0, x1, y0, y1, z0, z1, mat, b=0.0, sels=(), skip=()):
    """Boîte, arêtes biseautées de b (0 : boîte nette). skip : faces non créées ('-y', '+z'...) ; sans effet si b > 0
    sur les biseaux."""
    c = ((x0 + x1) / 2, (y0 + y1) / 2, (z0 + z1) / 2)
    h = ((x1 - x0) / 2, (y1 - y0) / 2, (z1 - z0) / 2)
    b = min(b, min(h) * 0.45)

    def P(s, a):
        # Point du coin de signes s, sur la face perpendiculaire à l'axe a.
        return tuple(c[i] + s[i] * (h[i] if i == a else h[i] - b) for i in range(3))

    signs = [(sx, sy, sz) for sx in (-1, 1) for sy in (-1, 1) for sz in (-1, 1)]
    names = ("x", "y", "z")
    for a in range(3):
        for sa in (-1, 1):
            if (("+" if sa > 0 else "-") + names[a]) in skip:
                continue
            o, q = [i for i in range(3) if i != a]
            ring = []
            for so, sq in ((-1, -1), (1, -1), (1, 1), (-1, 1)):
                s = [0, 0, 0]
                s[a], s[o], s[q] = sa, so, sq
                ring.append(P(s, a))
            hint = [0, 0, 0]
            hint[a] = sa
            m.poly(ring, mat, sels, hint=tuple(hint))
    if b <= 0:
        return
    for a in range(3):
        for e in range(a + 1, 3):
            t = [i for i in range(3) if i not in (a, e)][0]
            for sa in (-1, 1):
                for se in (-1, 1):
                    q = []
                    for st in (-1, 1):
                        s = [0, 0, 0]
                        s[a], s[e], s[t] = sa, se, st
                        q.append((P(s, a), P(s, e)))
                    ring = [q[0][0], q[1][0], q[1][1], q[0][1]]
                    hint = [0, 0, 0]
                    hint[a], hint[e] = sa, se
                    m.poly(ring, mat, sels, hint=tuple(hint))
    for s in signs:
        m.poly([P(s, 0), P(s, 1), P(s, 2)], mat, sels, hint=s)


def cylinder(m, p0, p1, r, mat, n=12, caps=True, sels=()):
    """Cylindre à facettes de p0 à p1."""
    d = vnorm(vsub(p1, p0))
    a = (1.0, 0.0, 0.0) if abs(d[0]) < 0.9 else (0.0, 1.0, 0.0)
    u = vnorm(vcross(d, a))
    v = vcross(d, u)
    ring = lambda p: [tuple(p[k] + r * (math.cos(2 * math.pi * i / n) * u[k] + math.sin(2 * math.pi * i / n) * v[k]) for k in range(3)) for i in range(n)]
    r0, r1 = ring(p0), ring(p1)
    for i in range(n):
        j = (i + 1) % n
        mid = tuple((r0[i][k] + r0[j][k]) / 2 - p0[k] for k in range(3))
        m.poly([r0[i], r0[j], r1[j], r1[i]], mat, sels, hint=mid)
    if caps:
        # Bouchons en éventail de quadrilatères (n pair).
        for ring_, hint in ((r0, tuple(-x for x in d)), (r1, d)):
            ctr = tuple(sum(p[k] for p in ring_) / n for k in range(3))
            for i in range(0, n, 2):
                m.poly([ctr, ring_[i], ring_[(i + 1) % n], ring_[(i + 2) % n]], mat, sels, hint=hint)


def lathe_rect(m, loops, mat, sels=(), close_start=None, close_end=None):
    """Surface balayée de rectangles (x0, x1, z0, z1, y) reliés dans l'ordre : sens défini par la construction
    (de l'arrière vers l'avant puis vers l'intérieur : faces vers l'extérieur). close_* : (matière, sélections, uv)."""
    def corners(L):
        x0, x1, z0, z1, y = L
        return [(x0, y, z0), (x1, y, z0), (x1, y, z1), (x0, y, z1)]
    cs = [corners(L) for L in loops]
    for a, b_ in zip(cs, cs[1:]):
        for k in range(4):
            k1 = (k + 1) % 4
            m.poly([a[k], b_[k], b_[k1], a[k1]], mat, sels)
    if close_start:
        cm, csel, cuv = close_start
        m.poly(cs[0], cm, csel, uvs=cuv, hint=(0, -1, 0))
    if close_end:
        cm, csel, cuv = close_end
        m.poly(cs[-1], cm, csel, uvs=cuv, hint=(0, 1, 0))


def screen_quad(m, x0, x1, z0, z1, y, sel, uv=(0.0, 0.0, 1.0, 1.0), mat="screen"):
    """Dalle face à +Y ; uv = (u gauche, v haut, u droite, v bas) de l'image vue de face. Vue de face (depuis +Y),
    la droite du spectateur est en -X : la gauche de l'image est en +X (comme model_projsurface.py)."""
    u0, v0, u1, v1 = uv
    pts = [(x0, y, z0), (x1, y, z0), (x1, y, z1), (x0, y, z1)]
    uvs = [(u1, v1), (u0, v1), (u0, v0), (u1, v0)]
    m.poly(pts, mat, (sel,), uvs=uvs, hint=(0, 1, 0))


# --------------------------------------------------------------------------------------------------------------------
# Pièces
# --------------------------------------------------------------------------------------------------------------------
def panel(m, cx, cz, aw, ah, bz, y0, y1, detail, sel, uv=(0.0, 0.0, 1.0, 1.0), bevel=0.003, face_mat="screen",
          body="frame", ring="frame"):
    """Dalle : corps (y0 -> y1), bord (bz = gauche, droite, bas, haut) autour de la partie visible aw x ah centrée en
    (cx, cz), biseau extérieur, chanfrein intérieur, verre en retrait de 1 mm."""
    l, r, b, t = bz
    X0, X1, Z0, Z1 = cx - aw / 2 - l, cx + aw / 2 + r, cz - ah / 2 - b, cz + ah / 2 + t
    ax0, ax1, az0, az1 = cx - aw / 2, cx + aw / 2, cz - ah / 2, cz + ah / 2
    if detail >= 2:
        lathe_rect(m, [(X0, X1, Z0, Z1, y0), (X0, X1, Z0, Z1, y1), (ax0, ax1, az0, az1, y1)], body, close_start=(body, (), None))
        screen_quad(m, ax0, ax1, az0, az1, y1 + 0.0004, sel, uv, face_mat)
        return
    bv = min(bevel, (y1 - y0) * 0.3, min(l, r, b, t) * 0.45)
    ic = min(0.0015, min(l, r, b, t) * 0.3)
    loops_body = [(X0 + bv, X1 - bv, Z0 + bv, Z1 - bv, y0), (X0, X1, Z0, Z1, y0 + bv), (X0, X1, Z0, Z1, y1 - bv),
                  (X0 + bv, X1 - bv, Z0 + bv, Z1 - bv, y1)]
    lathe_rect(m, loops_body, body, close_start=(body, (), None))
    rec = 0.001 if detail == 0 else 0.0
    loops_ring = [loops_body[-1], (ax0 - ic, ax1 + ic, az0 - ic, az1 + ic, y1), (ax0, ax1, az0, az1, y1 - rec)]
    lathe_rect(m, loops_ring, ring)
    screen_quad(m, ax0, ax1, az0, az1, y1 - rec, sel, uv, face_mat)


def back_casing(m, cx, cz, w, h, y0, y1, detail, vents=True):
    """Bosse arrière (électronique) : plastique, ouïes, boîte de raccordement en bas."""
    if detail >= 2:
        box(m, cx - w / 2, cx + w / 2, y0, y1, cz - h / 2, cz + h / 2, "plastic", skip=("+y",))
        return
    box(m, cx - w / 2, cx + w / 2, y0, y1, cz - h / 2, cz + h / 2, "plastic", b=min(0.01, (y1 - y0) * 0.4), skip=("+y",))
    if detail == 0 and vents:
        # Ouïes : deux bandeaux en haut et en bas du dos, 0,5 mm en saillie.
        for zc in (cz + h / 2 - h * 0.14, cz - h / 2 + h * 0.14):
            vw, vh = w * 0.7, h * 0.12
            pts = [(cx + vw / 2, y0 - 0.0005, zc - vh / 2), (cx - vw / 2, y0 - 0.0005, zc - vh / 2),
                   (cx - vw / 2, y0 - 0.0005, zc + vh / 2), (cx + vw / 2, y0 - 0.0005, zc + vh / 2)]
            uvs = [(0, vh / 0.08), (vw / 0.08, vh / 0.08), (vw / 0.08, 0), (0, 0)]
            m.poly(pts, "vent", uvs=uvs, hint=(0, -1, 0))
        # Boîte de raccordement (alimentation, HDMI) sous la bosse, câble qui en sort.
        bw, bd, bh = min(0.22, w * 0.3), (y1 - y0) * 0.8, 0.05
        box(m, cx + w * 0.2 - bw / 2, cx + w * 0.2 + bw / 2, y0 - bd * 0.2, y0 + bd * 0.8, cz - h / 2 + 0.01, cz - h / 2 + 0.01 + bh, "plastic", b=0.004)


def wall_plate(m, cx, cz, w, h, y1, detail):
    """Platine murale acier (plaquée au mur, Y = 0) et deux bras verticaux vers la dalle."""
    box(m, cx - w / 2, cx + w / 2, 0.0, 0.008, cz - h / 2, cz + h / 2, "steel", b=0.002 if detail == 0 else 0)
    for sx in (-1, 1):
        x = cx + sx * w * 0.3
        box(m, x - 0.025, x + 0.025, 0.008, y1, cz - h / 2 - 0.05, cz + h / 2 + 0.05, "steel", b=0.003 if detail == 0 else 0)
    if detail == 0:
        # Vis de fixation de la platine.
        for sx in (-1, 1):
            for sz in (-1, 1):
                x, z = cx + sx * (w / 2 - 0.03), cz + sz * (h / 2 - 0.03)
                cylinder(m, (x, 0.008, z), (x, 0.013, z), 0.007, "frame", n=8)


def video_wall(m, cols, rows, detail, sels):
    """Mur d'images de cols x rows dalles 55". sels : fonction (col, ligne) -> (sélection, uv)."""
    aw, ah = T55
    pw, ph = aw + 2 * T55_BZ, ah + 2 * T55_BZ
    W, H = cols * pw, rows * ph
    yb0, yb1 = 0.085, 0.150   # corps des dalles
    yf = yb1
    z0 = WALL_Z0 - T55_BZ
    for row in range(rows):
        for col in range(cols):
            cx = W / 2 - pw * (col + 0.5)      # colonne 0 à gauche vue de face (+X)
            cz = z0 + H - ph * (row + 0.5)
            sel, uv = sels(col, row)
            panel(m, cx, cz, aw, ah, (T55_BZ,) * 4, yb0, yb1, detail, sel, uv, bevel=0.0012, ring="plastic")
            back_casing(m, cx, cz, aw * 0.62, ah * 0.6, 0.06, yb0, detail)
            # Support escamotable : deux bras par dalle sur les rails.
            if detail < 2:
                for sx in (-1, 1):
                    x = cx + sx * aw * 0.22
                    box(m, x - 0.02, x + 0.02, 0.03, 0.06, cz - 0.12, cz + 0.12, "steel", b=0.003 if detail == 0 else 0)
    # Rails horizontaux au mur (deux par rangée).
    for row in range(rows):
        cz = z0 + H - ph * (row + 0.5)
        for dz in (-0.16, 0.16):
            box(m, -W / 2 + 0.05, W / 2 - 0.05, 0.0, 0.03, cz + dz - 0.03, cz + dz + 0.03, "steel", b=0.004 if detail == 0 else 0)
    # Cornière alu autour du mur, 4 mm en avant des dalles, biseautée.
    t = TRIM
    X0, X1, Z0, Z1 = -W / 2 - t, W / 2 + t, z0 - t, z0 + H + t
    if detail >= 2:
        lathe_rect(m, [(-W / 2, W / 2, z0, z0 + H, 0.05), (X0, X1, Z0, Z1, 0.05), (X0, X1, Z0, Z1, yf + 0.004),
                       (-W / 2, W / 2, z0, z0 + H, yf + 0.004), (-W / 2, W / 2, z0, z0 + H, 0.05)], "frame")
    else:
        bv = 0.004
        lathe_rect(m, [(-W / 2, W / 2, z0, z0 + H, 0.05), (X0 + bv, X1 - bv, Z0 + bv, Z1 - bv, 0.05),
                       (X0, X1, Z0, Z1, 0.05 + bv), (X0, X1, Z0, Z1, yf + 0.004 - bv),
                       (X0 + bv, X1 - bv, Z0 + bv, Z1 - bv, yf + 0.004), (-W / 2 - 0.0015, W / 2 + 0.0015, z0 - 0.0015, z0 + H + 0.0015, yf + 0.004),
                       (-W / 2, W / 2, z0, z0 + H, yf + 0.0025), (-W / 2, W / 2, z0, z0 + H, 0.05)], "frame")
    return W + 2 * t, H + 2 * t, yf + 0.004


def make_wall(cols, rows, mode, detail):
    m = Mesh()
    aw, ah = T55
    pw, ph = aw + 2 * T55_BZ, ah + 2 * T55_BZ
    W, H = cols * pw, rows * ph

    def sels(col, row):
        if mode == "one":
            # Image unique : UV proportionnelles à la position sur toute la surface (jointures comprises).
            x0 = col * pw + T55_BZ
            y0 = row * ph + T55_BZ
            return "screen", (x0 / W, y0 / H, (x0 + aw) / W, (y0 + ah) / H)
        if mode == "mixed":
            if col < 2:
                x0 = col * pw + T55_BZ
                y0 = row * ph + T55_BZ
                return "panel1", (x0 / (2 * pw), y0 / H, (x0 + aw) / (2 * pw), (y0 + ah) / H)
            return ("panel2" if row == 0 else "panel3"), (0.0, 0.0, 1.0, 1.0)
        return "panel%d" % (row * cols + col + 1), (0.0, 0.0, 1.0, 1.0)

    video_wall(m, cols, rows, detail, sels)
    return m


def make_98_wall(detail):
    m = Mesh()
    aw, ah = P98
    bz = (0.012, 0.012, 0.020, 0.012)
    cz = 0.95 + ah / 2
    panel(m, 0.0, cz, aw, ah, bz, 0.075, 0.135, detail, "screen")
    back_casing(m, 0.0, cz, 1.3, 0.78, 0.045, 0.075, detail)
    wall_plate(m, 0.0, cz, 0.9, 0.5, 0.045, detail)
    if detail == 0:
        power_led(m, aw / 2 - 0.05, cz - ah / 2 - 0.010, 0.135)
    return m


def power_led(m, x, z, y):
    box(m, x - 0.003, x + 0.003, y - 0.001, y + 0.0008, z - 0.0015, z + 0.0015, "led")


def make_98_stand(detail):
    m = Mesh()
    aw, ah = P98
    bz = (0.012, 0.012, 0.020, 0.012)
    cz = 0.86 + ah / 2
    panel(m, 0.0, cz, aw, ah, bz, 0.07, 0.13, detail, "screen")
    back_casing(m, 0.0, cz, 1.3, 0.78, 0.04, 0.07, detail)
    if detail == 0:
        power_led(m, aw / 2 - 0.05, cz - ah / 2 - 0.010, 0.13)
    cb = 0.003 if detail == 0 else 0
    col_top = cz + 0.42
    for sx in (-1, 1):
        x = sx * 0.40
        # Colonne (tube 80 x 50), pied avant-arrière, roulettes.
        box(m, x - 0.04, x + 0.04, -0.03, 0.02, 0.13, col_top, "steel", b=cb)
        box(m, x - 0.045, x + 0.045, -0.48, 0.52, 0.10, 0.16, "steel", b=cb)
        box(m, x - 0.05, x + 0.05, -0.06, 0.05, 0.13, 0.22, "steel", b=cb)       # gousset
        if detail < 2:
            for y in (-0.42, 0.46):
                box(m, x - 0.035, x + 0.035, y - 0.035, y + 0.035, 0.088, 0.10, "steel")      # platine
                box(m, x - 0.018, x + 0.018, y - 0.03, y + 0.01, 0.045, 0.088, "steel")      # fourche
                cylinder(m, (x - 0.014, y - 0.01, 0.045), (x + 0.014, y - 0.01, 0.045), 0.045, "rubber", n=12 if detail == 0 else 8)
        # Bras de fixation vers le dos de l'écran.
        for zz in (cz - 0.22, cz + 0.22):
            box(m, x - 0.03, x + 0.03, 0.02, 0.04, zz - 0.03, zz + 0.03, "steel", b=cb)
        if detail < 2:
            # Bouchon de colonne.
            box(m, x - 0.042, x + 0.042, -0.032, 0.022, col_top, col_top + 0.01, "plastic", b=0.002 if detail == 0 else 0)
    # Traverses entre les colonnes : basse (avec tablette) et rail VESA.
    box(m, -0.36, 0.36, -0.025, 0.015, 0.30, 0.36, "steel", b=cb)
    for zz in (cz - 0.22, cz + 0.22):
        box(m, -0.44, 0.44, 0.02, 0.04, zz - 0.035, zz + 0.035, "steel", b=cb)
    # Tablette basse entre les colonnes : boîtier d'alimentation, câble vers le dos de l'écran.
    box(m, -0.30, 0.30, -0.20, 0.10, 0.36, 0.375, "steel", b=cb)
    if detail == 0:
        box(m, -0.18, 0.12, -0.15, 0.05, 0.375, 0.445, "plastic", b=0.005)        # onduleur / boîtier
        # Câble du boîtier vers le dos de l'écran, le long de la colonne droite.
        cylinder(m, (0.10, -0.05, 0.445), (0.36, -0.045, 0.6), 0.008, "rubber", n=6, caps=False)
        cylinder(m, (0.36, -0.045, 0.6), (0.36, -0.045, cz - ah / 2), 0.008, "rubber", n=6, caps=False)
    return m


def rot_x(p, a, pivot):
    y, z = p[1] - pivot[1], p[2] - pivot[2]
    c, s = math.cos(a), math.sin(a)
    return (p[0], pivot[1] + y * c - z * s, pivot[2] + y * s + z * c)


def make_65_ceiling(detail):
    m = Mesh()
    aw, ah = P65
    bz = (0.010, 0.010, 0.016, 0.010)
    cb = 0.002 if detail == 0 else 0
    # Platine de plafond, tube, articulation.
    box(m, -0.12, 0.12, -0.12, 0.12, -0.012, 0.0, "steel", b=cb)
    box(m, -0.03, 0.03, -0.03, 0.03, -0.60, -0.012, "steel", b=cb)
    if detail == 0:
        for sx in (-1, 1):
            for sy in (-1, 1):
                cylinder(m, (sx * 0.09, sy * 0.09, -0.012), (sx * 0.09, sy * 0.09, -0.017), 0.008, "frame", n=8)
        cylinder(m, (-0.05, 0.0, -0.62), (0.05, 0.0, -0.62), 0.025, "steel", n=12)   # rotule
    box(m, -0.05, 0.05, -0.03, 0.03, -0.66, -0.60, "steel", b=cb)
    # Écran incliné de 10° vers le bas autour de l'articulation.
    sub = Mesh()
    top = -0.66
    cz = top - 0.06 - ah / 2
    panel(sub, 0.0, cz, aw, ah, bz, 0.05, 0.10, detail, "screen")
    back_casing(sub, 0.0, cz, 0.9, 0.55, 0.025, 0.05, detail)
    box(sub, -0.25, 0.25, 0.0, 0.025, cz - 0.15, cz + 0.15, "steel", b=cb)        # platine VESA
    box(sub, -0.03, 0.03, -0.02, 0.025, cz + 0.12, top, "steel", b=cb)             # bras vers l'articulation
    if detail == 0:
        power_led(sub, aw / 2 - 0.04, cz - ah / 2 - 0.008, 0.10)
    a = math.radians(-10)
    pivot = (0.0, 0.0, -0.63)
    sub.pts = [rot_x(p, a, pivot) for p in sub.pts]
    sub.faces = [(idx, uvs, rot_x(n, a, (0, 0, 0)), mat, sels) for idx, uvs, n, mat, sels in sub.faces]
    m.merge(sub)
    return m


def make_clock(detail):
    m = Mesh()
    aw, ah = CLOCK
    bz = (0.02,) * 4
    cz = HIGH_Z
    panel(m, 0.0, cz, aw, ah, bz, 0.03, 0.085, detail, "screen", bevel=0.006, body="plastic", ring="plastic")
    if detail < 2:
        for sx in (-1, 1):
            x = sx * 0.4
            box(m, x - 0.02, x + 0.02, 0.0, 0.03, cz - 0.09, cz + 0.09, "steel", b=0.002 if detail == 0 else 0)
    if detail == 0:
        power_led(m, aw / 2 + 0.008, cz - ah / 2 - 0.01, 0.085)
    return m


def make_plate(detail):
    m = Mesh()
    aw, ah = PLATE
    bz = (0.02, 0.02, 0.0175, 0.0175)
    cz = HIGH_Z
    panel(m, 0.0, cz, aw, ah, bz, 0.022, 0.042, detail, "screen", bevel=0.004, face_mat="plate")
    if detail < 2:
        for sx in (-1, 1):
            cylinder(m, (sx * 0.36, 0.0, cz), (sx * 0.36, 0.022, cz), 0.012, "frame", n=10 if detail == 0 else 6)
    return m


# Boîtes des LOD fermées : (x0, x1, y0, y1, z0, z1)
def closed_boxes(name):
    aw, ah = T55
    pw, ph = aw + 2 * T55_BZ, ah + 2 * T55_BZ
    if name.startswith("toc_wall_"):
        cols = 3 if "3x2" in name else 2
        W, H = cols * pw + 2 * TRIM, 2 * ph + 2 * TRIM
        z0 = WALL_Z0 - T55_BZ - TRIM
        return [(-W / 2, W / 2, 0.0, 0.154, z0, z0 + H)]
    if name == "toc_98_wall":
        z0 = 0.95 - 0.02
        return [(-P98[0] / 2 - 0.012, P98[0] / 2 + 0.012, 0.0, 0.135, z0, 0.95 + P98[1] + 0.012)]
    if name == "toc_98_stand":
        z0 = 0.86 - 0.02
        boxes = [(-P98[0] / 2 - 0.012, P98[0] / 2 + 0.012, 0.04, 0.13, z0, 0.86 + P98[1] + 0.012)]
        for sx in (-1, 1):
            x = sx * 0.40
            boxes.append((x - 0.045, x + 0.045, -0.48, 0.52, 0.0, 0.16))
            boxes.append((x - 0.04, x + 0.04, -0.03, 0.04, 0.16, z0))
        boxes.append((-0.30, 0.30, -0.20, 0.10, 0.30, 0.445))
        return boxes
    if name == "toc_65_ceiling":
        aw, ah = P65
        a = math.radians(-10)
        top = -0.66
        cz = top - 0.06 - ah / 2
        pts = []
        for x in (-aw / 2 - 0.01, aw / 2 + 0.01):
            for y in (0.025, 0.10):
                for z in (cz - ah / 2 - 0.016, cz + ah / 2 + 0.01):
                    pts.append(rot_x((x, y, z), a, (0.0, 0.0, -0.63)))
        return [(-0.12, 0.12, -0.12, 0.12, -0.60, 0.0), ("pts", pts)]
    if name == "toc_clock_strip":
        return [(-CLOCK[0] / 2 - 0.02, CLOCK[0] / 2 + 0.02, 0.0, 0.085, HIGH_Z - CLOCK[1] / 2 - 0.02, HIGH_Z + CLOCK[1] / 2 + 0.02)]
    if name == "toc_name_plate":
        return [(-0.45, 0.45, 0.0, 0.042, HIGH_Z - 0.125, HIGH_Z + 0.125)]
    raise KeyError(name)


def hull_box(b):
    """Boîte fermée à sommets partagés (8 points, 12 triangles) ; ('pts', 8 points) : boîte quelconque (même ordre)."""
    if b[0] == "pts":
        P = b[1]
    else:
        x0, x1, y0, y1, z0, z1 = b
        P = [(x, y, z) for x in (x0, x1) for y in (y0, y1) for z in (z0, z1)]
    # indices : (ix, iy, iz) -> 4*ix + 2*iy + iz
    quads = [(0, 1, 3, 2), (4, 6, 7, 5), (0, 4, 5, 1), (2, 3, 7, 6), (0, 2, 6, 4), (1, 5, 7, 3)]
    c = tuple(sum(p[k] for p in P) / 8 for k in range(3))
    tris = []
    for q in quads:
        pts = [P[i] for i in q]
        n = newell(pts)
        fc = tuple(sum(p[k] for p in pts) / 4 for k in range(3))
        if vdot(n, vsub(fc, c)) < 0:
            q = tuple(reversed(q))
        tris += [(q[0], q[1], q[2]), (q[0], q[2], q[3])]
    return P, tris


# --------------------------------------------------------------------------------------------------------------------
# Écriture MLOD
# --------------------------------------------------------------------------------------------------------------------
def asciiz(s):
    return s.encode("ascii") + b"\0"


def tagg(name, data):
    return b"\x01" + asciiz(name) + struct.pack("<I", len(data)) + data


def lod_bytes(pts, faces, sels, resolution, props=(), mass=None):
    """faces : (indices, uvs, normale, texture, rvmat, sélections)."""
    normals = []
    out_faces = b""
    uvset = b""
    for idx, uvs, n, tex, mat, _ in faces:
        ni = len(normals)
        normals.append(n)
        rec = struct.pack("<I", len(idx))
        for k in range(4):
            if k < len(idx):
                rec += struct.pack("<IIff", idx[k], ni, uvs[k][0], uvs[k][1])
                uvset += struct.pack("<ff", uvs[k][0], uvs[k][1])
            else:
                rec += struct.pack("<IIff", 0, 0, 0.0, 0.0)
        rec += struct.pack("<I", 0) + asciiz(tex) + asciiz(mat)
        out_faces += rec
    head = b"P3DM" + struct.pack("<IIIIII", 28, 256, len(pts), len(normals), len(faces), 0)
    body = b"".join(struct.pack("<fffI", p[0], p[2], p[1], 0) for p in pts)
    body += b"".join(struct.pack("<fff", -n[0], -n[2], -n[1]) for n in normals)
    body += out_faces
    body += b"TAGG"
    body += tagg("#UVSet#", struct.pack("<I", 0) + uvset)
    for name, (pset, fset) in sels.items():
        body += tagg(name, bytes(1 if i in pset else 0 for i in range(len(pts))) + bytes(1 if i in fset else 0 for i in range(len(faces))))
    if mass is not None:
        body += tagg("#Mass#", b"".join(struct.pack("<f", w) for w in mass))
    for k, v in props:
        body += tagg("#Property#", k.encode("ascii").ljust(64, b"\0") + v.encode("ascii").ljust(64, b"\0"))
    body += tagg("#EndOfFile#", b"")
    body += struct.pack("<f", resolution)
    return head + body


def visual_lod(m, resolution):
    faces = []
    sels = {}
    for fi, (idx, uvs, n, mat, fsels) in enumerate(m.faces):
        tex, rv = tex_path(mat), TEX + MATS[mat][1]
        faces.append((idx, uvs, n, tex, rv, fsels))
        for s in fsels:
            sels.setdefault(s, (set(), set()))[1].add(fi)
    for s, pset in m.sel_pts.items():
        sels.setdefault(s, (set(), set()))[0].update(pset)
    return lod_bytes(m.pts, faces, sels, resolution)


def closed_lod(boxes, resolution, components=False, mass_total=0.0, props=()):
    pts, faces, sels = [], [], {}
    for bi, b in enumerate(boxes):
        P, tris = hull_box(b)
        off = len(pts)
        pts += P
        f0 = len(faces)
        for t in tris:
            a, b_, c = (P[t[0]], P[t[1]], P[t[2]])
            n = vnorm(vcross(vsub(b_, a), vsub(c, b_)))
            faces.append(([off + t[0], off + t[1], off + t[2]], [(0, 0)] * 3, n, "", "", ()))
        if components:
            sels["Component%02d" % (bi + 1)] = (set(range(off, off + 8)), set(range(f0, len(faces))))
    mass = [mass_total / len(pts)] * len(pts) if mass_total > 0 else None
    return lod_bytes(pts, faces, sels, resolution, props, mass)


MODELS = {
    "toc_wall_2x2": (lambda d: make_wall(2, 2, "one", d), 150.0),
    "toc_wall_2x2_quad": (lambda d: make_wall(2, 2, "quad", d), 150.0),
    "toc_wall_3x2": (lambda d: make_wall(3, 2, "quad", d), 220.0),
    "toc_wall_3x2_mixed": (lambda d: make_wall(3, 2, "mixed", d), 220.0),
    "toc_98_wall": (make_98_wall, 95.0),
    "toc_98_stand": (make_98_stand, 160.0),
    "toc_65_ceiling": (make_65_ceiling, 45.0),
    "toc_clock_strip": (make_clock, 9.0),
    "toc_name_plate": (make_plate, 3.0),
}


def write_model(name):
    build, mass = MODELS[name]
    lods = [visual_lod(build(d), res) for d, res in ((0, 0.0), (1, 2.0), (2, 6.0))]
    boxes = closed_boxes(name)
    lods.append(closed_lod(boxes, 10000.0))
    lods.append(closed_lod(boxes, 1e13, components=True, mass_total=mass, props=(("autocenter", "0"),)))
    lods.append(closed_lod(boxes, 6e15, components=True))
    lods.append(closed_lod(boxes, 7e15, components=True))
    out = os.path.join(DATA, name + ".p3d")
    with open(out, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, len(lods)) + b"".join(lods))
    m0 = build(0)
    print("écrit %s : %d faces (LOD 0), %d dalles" % (out, len(m0.faces), len([s for s in m0.sel_pts])))
    return m0


# --------------------------------------------------------------------------------------------------------------------
# Textures et matières
# --------------------------------------------------------------------------------------------------------------------
rng = np.random.default_rng(2611)


def noise(size, beta=2.0, aniso=(1.0, 1.0)):
    """Bruit périodique en 1/f^beta (raccordable), 0..1 ; aniso étire les fréquences (x, y)."""
    fx = np.fft.fftfreq(size)[None, :] * size * aniso[0]
    fy = np.fft.fftfreq(size)[:, None] * size * aniso[1]
    f = np.sqrt(fx * fx + fy * fy)
    f[0, 0] = 1.0
    spec = (rng.normal(size=(size, size)) + 1j * rng.normal(size=(size, size))) / f ** (beta / 2)
    spec[0, 0] = 0
    out = np.real(np.fft.ifft2(spec))
    out -= out.min()
    return out / out.max()


def save_png(path, arr):
    Image.fromarray((np.clip(arr, 0, 1) * 255 + 0.5).astype(np.uint8), "RGB").save(path)


def maps(tmp, name, rgb, height, strength, spec, gloss):
    save_png(os.path.join(tmp, name + "_co.png"), rgb)
    dx = (np.roll(height, -1, 1) - np.roll(height, 1, 1)) * strength
    dy = (np.roll(height, -1, 0) - np.roll(height, 1, 0)) * strength
    n = np.stack([-dx, dy, np.ones_like(dx)], -1)
    n /= np.linalg.norm(n, axis=-1, keepdims=True)
    save_png(os.path.join(tmp, name + "_nohq.png"), n * 0.5 + 0.5)
    ones = np.ones_like(height)
    save_png(os.path.join(tmp, name + "_smdi.png"), np.stack([ones, np.clip(spec, 0, 1) * ones, np.clip(gloss, 0, 1) * ones], -1))


def textures(tmp):
    N = 512
    # Alu anodisé anthracite brossé (cadres, cornières).
    brush = noise(N, 1.2, aniso=(1.0, 0.03))
    mott = noise(N, 2.2)
    g = 0.135 + 0.035 * (brush - 0.5) + 0.02 * (mott - 0.5)
    rgb = g[..., None] * np.array([1.0, 1.03, 1.08])[None, None, :]
    maps(tmp, "toc_frame", rgb, brush, 2.0, 0.42 + 0.12 * (brush - 0.5), 0.58)
    # Plastique noir mat grainé (coques arrière).
    grain = noise(N, 0.4)
    mott = noise(N, 2.0)
    g = 0.075 + 0.012 * (grain - 0.5) + 0.012 * (mott - 0.5)
    maps(tmp, "toc_plastic", np.repeat(g[..., None], 3, -1), grain, 3.0, 0.14 + 0.04 * (grain - 0.5), 0.26)
    # Acier thermolaqué noir satiné, peau d'orange (supports, pied).
    peel = noise(N, 2.6)
    fine = noise(N, 0.8)
    g = 0.085 + 0.015 * (peel - 0.5) + 0.006 * (fine - 0.5)
    maps(tmp, "toc_steel", g[..., None] * np.array([1.0, 1.0, 1.02])[None, None, :], peel * 0.7 + fine * 0.3, 3.5, 0.28, 0.42)
    # Ouïes : fentes horizontales (tuile de 8 cm, 5 fentes).
    V = 256
    yy, xx = np.mgrid[0:V, 0:V] / V
    slot = ((np.mod(yy * 5, 1.0) > 0.3) & (np.mod(yy * 5, 1.0) < 0.7) & (np.mod(xx * 2, 1.0) > 0.06) & (np.mod(xx * 2, 1.0) < 0.94))
    g = np.where(slot, 0.012, 0.08) + 0.01 * (noise(V, 0.5) - 0.5)
    h = np.where(slot, 0.0, 1.0).astype(float)
    maps(tmp, "toc_vent", np.repeat(g[..., None], 3, -1), h, 6.0, np.where(slot, 0.0, 0.14), np.where(slot, 0.0, 0.25))
    return ["toc_frame", "toc_plastic", "toc_steel", "toc_vent"]


RVMAT_SUPER = """// {comment}
ambient[]={{1,1,1,1}};
diffuse[]={{1,1,1,1}};
forcedDiffuse[]={{0,0,0,0}};
emmisive[]={{0,0,0,1}};
specular[]={{{spec},{spec},{spec},1}};
specularPower={power};
PixelShaderID="Super";
VertexShaderID="Super";
class Stage1 {{ texture="{nohq}"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage2 {{ texture="#(argb,8,8,3)color(0.5,0.5,0.5,1,DT)"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage3 {{ texture="#(argb,8,8,3)color(0,0,0,0,MC)"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage4 {{ texture="#(argb,8,8,3)color(1,1,1,1,AS)"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage5 {{ texture="{smdi}"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage6 {{ texture="#(ai,64,64,1)fresnel({fresnel})"; uvSource="none"; }};
class Stage7 {{ texture="a3\\data_f\\env_land_co.paa"; useWorldEnvMap="true"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
"""


def rvmats():
    def super_(name, comment, spec, power, fresnel, base=None):
        base = base or name
        with open(os.path.join(DATA, name + ".rvmat"), "w", encoding="utf-8", newline="\n") as f:
            f.write(RVMAT_SUPER.format(comment=comment, spec=spec, power=power, fresnel=fresnel,
                                       nohq=TEX + base + "_nohq.paa", smdi=TEX + base + "_smdi.paa"))
    super_("toc_frame", "Écrans de TOC (tools/model_toc_screens.py) : alu anodisé anthracite brossé, reflets doux.", 0.5, 40, "1.6,0.6")
    super_("toc_plastic", "Écrans de TOC : coque plastique noire mate grainée.", 0.18, 18, "1.4,0.3")
    super_("toc_steel", "Écrans de TOC : acier thermolaqué noir satiné (supports, pied roulant).", 0.3, 30, "1.5,0.45")
    super_("toc_vent", "Écrans de TOC : ouïes d'aération des coques.", 0.15, 18, "1.4,0.3")
    # Verre éteint : dalle noire brillante, reflets de l'environnement (remplacée par comspec_screenMat sous une image).
    glass = RVMAT_SUPER.format(comment="Écrans de TOC : dalle éteinte, verre noir brillant (reflets). Sous une image : comspec_screen_on / _night.",
                               spec=0.9, power=120, fresnel="1.5,0.9", nohq="#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)",
                               smdi="#(argb,8,8,3)color(1,0.85,0.95,1,SMDI)")
    with open(os.path.join(DATA, "toc_glass_off.rvmat"), "w", encoding="utf-8", newline="\n") as f:
        f.write(glass)
    with open(os.path.join(DATA, "toc_led.rvmat"), "w", encoding="utf-8", newline="\n") as f:
        f.write("// Écrans de TOC : voyant de veille (lumineux).\nambient[]={1,1,1,1};\ndiffuse[]={1,1,1,1};\n"
                "forcedDiffuse[]={0,0,0,0};\nemmisive[]={0.2,1.6,0.4,1};\nspecular[]={0.1,0.1,0.1,1};\nspecularPower=30;\n"
                "PixelShaderID=\"Normal\";\nVertexShaderID=\"Basic\";\n")


def to_paa(tmp, names):
    hemtt = shutil.which("hemtt")
    if not hemtt:
        print("hemtt introuvable : PNG laissés dans", tmp)
        return
    for n in names:
        for suf in ("co", "nohq", "smdi"):
            src = os.path.join(tmp, "%s_%s.png" % (n, suf))
            dst = os.path.join(DATA, "%s_%s.paa" % (n, suf))
            if os.path.exists(dst):
                os.remove(dst)  # hemtt ne remplace pas un PAA existant
            subprocess.run([hemtt, "utils", "paa", "convert", src, dst], check=True, capture_output=True)
    print("textures PAA :", ", ".join(names))


# --------------------------------------------------------------------------------------------------------------------
# Aperçus Eden : petit moteur logiciel (z-buffer, Lambert, textures, écrans allumés)
# --------------------------------------------------------------------------------------------------------------------
def demo_content(w=640, h=360, seed=0):
    """Image de démonstration de l'aperçu (carte tactique factice, bandeau, colonne) : aucun logo."""
    r = np.random.default_rng(seed)
    yy, xx = np.mgrid[0:h, 0:w]
    n = noise(512, 2.4)[:h, :w] if h <= 512 and w <= 512 else np.kron(noise(256, 2.4), np.ones((4, 4)))[:h, :w]
    img = np.zeros((h, w, 3))
    land = np.stack([0.30 + 0.25 * n, 0.33 + 0.2 * n, 0.22 + 0.1 * n], -1)
    sea = np.array([0.12, 0.22, 0.32])
    img[:] = np.where((n > 0.38)[..., None], land, sea)
    grid = ((xx % 48) < 1) | ((yy % 48) < 1)
    img[grid] *= 0.6
    for _ in range(14):
        cx, cy = r.integers(40, int(w * 0.66)), r.integers(50, h - 20)
        img[cy - 4:cy + 4, cx - 4:cx + 4] = [0.3, 0.6, 1.0]
    img[: int(h * 0.075)] = [0.06, 0.08, 0.07]
    img[int(h * 0.072): int(h * 0.078)] = [0.28, 0.62, 1.0]
    img[int(h * 0.08):, int(w * 0.66):] = [0.05, 0.065, 0.058]
    for k in range(10):
        y = int(h * 0.12) + k * int(h * 0.075)
        img[y: y + 5, int(w * 0.68): int(w * 0.68) + r.integers(60, int(w * 0.3))] = [0.75, 0.8, 0.78]
    return img


def look_at(eye, target):
    f = np.array(target, float) - np.array(eye, float)
    f /= np.linalg.norm(f)
    r = np.cross(f, [0, 0, 1.0])
    r /= np.linalg.norm(r)
    u = np.cross(r, f)
    return np.array(eye, float), r, u, f


def render(m, eye, target, size=(910, 512), fov=40.0, content=None, bg=(0.16, 0.17, 0.18)):
    W, H = size
    img = np.zeros((H, W, 3))
    gy = np.linspace(1.0, 0.55, H)[:, None, None]
    img[:] = np.array(bg)[None, None, :] * gy
    zb = np.full((H, W), np.inf)
    e, r, u, f = look_at(eye, target)
    foc = (W / 2) / math.tan(math.radians(fov) / 2)
    light = np.array([-0.45, 0.55, 0.7])
    light /= np.linalg.norm(light)
    texs = {}
    for k in ("frame", "plastic", "steel", "vent"):
        p = os.path.join(TMP_TEX, k.join(("toc_", "_co.png"))) if TMP_TEX else None
        texs[k] = np.asarray(Image.open(p), float) / 255 if p and os.path.exists(p) else None
    flat = {"rubber": np.array([0.03, 0.03, 0.03]), "led": np.array([0.2, 1.0, 0.3]), "plate": np.array([0.06, 0.065, 0.07])}
    contents = content or {}
    P = np.array(m.pts)
    rel = P - e
    cam = np.stack([rel @ r, rel @ u, rel @ f], -1)
    for idx, uvs, n, mat, sels in m.faces:
        nn = np.array(n)
        ctr = P[idx].mean(0)
        if np.dot(nn, ctr - e) >= 0:
            continue  # face de dos : un modèle mal orienté se voit troué
        tris = [(0, 1, 2)] if len(idx) == 3 else [(0, 1, 2), (0, 2, 3)]
        for t in tris:
            ii = [idx[k] for k in t]
            cz = cam[ii, 2]
            if (cz < 0.05).any():
                continue
            sx = W / 2 + foc * cam[ii, 0] / cz
            sy = H / 2 - foc * cam[ii, 1] / cz
            x0, x1 = int(max(0, math.floor(sx.min()))), int(min(W - 1, math.ceil(sx.max())))
            y0, y1 = int(max(0, math.floor(sy.min()))), int(min(H - 1, math.ceil(sy.max())))
            if x1 < x0 or y1 < y0:
                continue
            gx, gy2 = np.meshgrid(np.arange(x0, x1 + 1) + 0.5, np.arange(y0, y1 + 1) + 0.5)
            d = (sx[1] - sx[0]) * (sy[2] - sy[0]) - (sx[2] - sx[0]) * (sy[1] - sy[0])
            if abs(d) < 1e-9:
                continue
            w1 = ((gx - sx[0]) * (sy[2] - sy[0]) - (sx[2] - sx[0]) * (gy2 - sy[0])) / d
            w2 = ((sx[1] - sx[0]) * (gy2 - sy[0]) - (gx - sx[0]) * (sy[1] - sy[0])) / d
            w0 = 1 - w1 - w2
            inside = (w0 >= -1e-6) & (w1 >= -1e-6) & (w2 >= -1e-6)
            if not inside.any():
                continue
            iz = w0 / cz[0] + w1 / cz[1] + w2 / cz[2]
            z = 1 / np.where(iz > 0, iz, 1e-9)
            sub = zb[y0:y1 + 1, x0:x1 + 1]
            ok = inside & (z < sub)
            if not ok.any():
                continue
            uv = np.array([uvs[k] for k in t])
            uu = (w0 * uv[0, 0] / cz[0] + w1 * uv[1, 0] / cz[1] + w2 * uv[2, 0] / cz[2]) * z
            vv = (w0 * uv[0, 1] / cz[0] + w1 * uv[1, 1] / cz[1] + w2 * uv[2, 1] / cz[2]) * z
            sel = sels[0] if sels else None
            if mat in ("screen", "plate") and sel in contents:
                c = contents[sel]
                ch, cw = c.shape[:2]
                tx = np.clip((uu * cw).astype(int), 0, cw - 1)
                ty = np.clip((vv * ch).astype(int), 0, ch - 1)
                col = c[ty, tx]
            else:
                tex = texs.get(mat)
                if tex is not None:
                    th, tw = tex.shape[:2]
                    col = tex[(np.floor(vv * th).astype(int)) % th, (np.floor(uu * tw).astype(int)) % tw]
                elif mat in ("screen",):
                    col = np.broadcast_to(np.array([0.015, 0.017, 0.02]), uu.shape + (3,))
                else:
                    col = np.broadcast_to(flat.get(mat, np.array([0.1, 0.1, 0.1])), uu.shape + (3,))
                lam = max(0.0, float(np.dot(nn, light)))
                hv = light - (ctr - e) / np.linalg.norm(ctr - e)
                hv /= np.linalg.norm(hv)
                spec = (max(0.0, float(np.dot(nn, hv))) ** 40) * (0.9 if mat == "screen" else 0.35)
                emis = mat == "led"
                col = col if emis else col * (0.55 + 1.6 * lam) + spec
                if mat == "screen":
                    # Reflet du ciel sur le verre éteint (dégradé) : on voit la dalle noire brillante.
                    col = col + 0.05 * (1 - vv)[..., None]
            sub[ok] = z[ok]
            region = img[y0:y1 + 1, x0:x1 + 1]
            region[ok] = col[ok] if col.shape[:2] == ok.shape else col[ok]
    return np.clip(img, 0, 1)


def downsample(img):
    h, w = img.shape[0] // 2 * 2, img.shape[1] // 2 * 2
    return img[:h, :w].reshape(h // 2, 2, w // 2, 2, 3).mean((1, 3))


def frame_view(m, below=False, fov=40.0, aspect=910 / 512):
    """Cadrage de l'aperçu : trois quarts face, depuis la gauche du spectateur (+X), un peu au-dessus (au-dessous pour
    l'écran suspendu), à la distance où la boîte englobante tient dans l'image."""
    P = np.array(m.pts)
    lo, hi = P.min(0), P.max(0)
    c = (lo + hi) / 2
    d = np.array([0.55, 1.0, -0.22 if below else 0.22])
    d /= np.linalg.norm(d)
    # fov horizontal (render) : la hauteur compte pour aspect fois sa demi-mesure.
    half = max((hi[0] - lo[0]) / 2, (hi[2] - lo[2]) / 2 * aspect, (hi[1] - lo[1]) / 2)
    dist = half / math.tan(math.radians(fov) / 2) * 1.3 + (hi[1] - lo[1]) / 2
    return tuple(c + d * dist), tuple(c)


def clock_content(w=640, h=160):
    img = np.zeros((h, w, 3))
    img[:] = [0.02, 0.02, 0.02]
    # Chiffres à segments factices (barres rouges).
    for k in range(8):
        x = 30 + k * 72 + (24 if k > 3 else 0)
        for (dx, dy, ww, hh) in ((0, 30, 40, 8), (0, 76, 40, 8), (0, 122, 40, 8), (0, 30, 8, 100), (32, 30, 8, 100)):
            img[dy:dy + hh, x + dx:x + dx + ww] = [1.0, 0.12, 0.05]
    return img


def plate_content(w=640, h=160):
    img = np.zeros((h, w, 3))
    img[:] = [0.07, 0.07, 0.075]
    img[8:12, 8:-8] = img[-12:-8, 8:-8] = [0.85, 0.85, 0.8]
    img[8:-8, 8:12] = img[8:-8, -12:-8] = [0.85, 0.85, 0.8]
    img[60:100, 150:490] = [0.85, 0.85, 0.8]
    return img


def previews(models, render_dir=""):
    for name, m in models.items():
        eye, tgt = frame_view(m, below=name == "toc_65_ceiling")
        sels = sorted({s for f in m.faces for s in f[4]})
        cont = {}
        for i, s in enumerate(sels):
            cont[s] = clock_content() if name == "toc_clock_strip" else plate_content() if name == "toc_name_plate" else demo_content(seed=i)
        img = downsample(render(m, eye, tgt, content=cont))
        Image.fromarray((img * 255 + 0.5).astype(np.uint8)).save(os.path.join(DATA, name + "_preview.jpg"), quality=88)
        if render_dir:
            big = downsample(render(m, eye, tgt, size=(2400, 1350), content=cont))
            Image.fromarray((big * 255 + 0.5).astype(np.uint8)).save(os.path.join(render_dir, name + ".png"))
            # Vue de dos (supports, coques, ouïes).
            back = (2 * tgt[0] - eye[0], 2 * tgt[1] - eye[1], eye[2])
            big = downsample(render(m, back, tgt, size=(1600, 900), content=cont))
            Image.fromarray((big * 255 + 0.5).astype(np.uint8)).save(os.path.join(render_dir, name + "_dos.png"))
    print("aperçus Eden :", len(models))


TMP_TEX = ""


def main():
    global TMP_TEX
    os.makedirs(DATA, exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="toc_tex_")
    TMP_TEX = tmp
    try:
        if not os.environ.get("NO_TEX"):
            to_paa(tmp, textures(tmp))
        else:
            textures(tmp)
        rvmats()
        models = {name: write_model(name) for name in MODELS}
        if not os.environ.get("NO_PREVIEW"):
            previews(models, os.environ.get("RENDER_DIR", ""))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
