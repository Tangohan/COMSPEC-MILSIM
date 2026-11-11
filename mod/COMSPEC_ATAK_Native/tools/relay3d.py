"""
Boîte à outils des modèles 3D « relais » (pylônes, antennes, caméras IP, baies réseau) : maillage, primitives, LOD,
écriture MLOD (.p3d) en pur Python, atlas de textures procéduraux (numpy / Pillow), export OBJ pour les rendus Blender.
Même famille que model_toc_screens.py (aucun Blender requis pour produire les modèles et les textures).

Repère : X à droite, Y vers l'avant, Z vers le haut (écrit X, Z, Y dans le P3D). Faces en sens direct vues de
l'extérieur ; normales écrites opposées (convention MLOD, comme model_projsurface.py).

Idées directrices (voir docs/audit/2026-11-relais.md) :
  - peu de sections : une « planche de profilés » (bandes raccordables en U : acier, peinture, câbles) et un atlas
    d'équipements (rectangles : armoires, radômes, panneaux, béton) par famille ; voyants en matières unies ;
  - arêtes vives = sommets propres à chaque face, surfaces lisses = sommets partagés + normales radiales : le moteur
    obtient le même éclairage qu'il relise les normales ou qu'il les recalcule ;
  - chaque primitive peut déposer un « volume simple » (boîte ou prisme fermé) pour les LOD d'ombre, de géométrie, de tir
    et de vue : S="sSgfv" (s : ombre proche, S : ombre lointaine, g : collision, f : tir, v : vue) ;
  - détail d : 0 (de près) à 4 (silhouette) ; les constructeurs de modèles testent m.d.
"""
import json
import math
import os
import random
import shutil
import struct
import subprocess

import numpy as np
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))


# --------------------------------------------------------------------------------------------------------------------
# Vecteurs (tuples) et repères
# --------------------------------------------------------------------------------------------------------------------
def vadd(a, b):
    return (a[0] + b[0], a[1] + b[1], a[2] + b[2])


def vsub(a, b):
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def vmul(a, k):
    return (a[0] * k, a[1] * k, a[2] * k)


def vdot(a, b):
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


def vcross(a, b):
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def vlen(a):
    return math.sqrt(vdot(a, a))


def vnorm(a):
    n = vlen(a)
    return (a[0] / n, a[1] / n, a[2] / n) if n > 1e-12 else (0.0, 0.0, 0.0)


def vlerp(a, b, t):
    return (a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t)


def vortho(d):
    """Un vecteur unitaire perpendiculaire à d (stable)."""
    a = (0.0, 0.0, 1.0) if abs(d[2]) < 0.9 else (1.0, 0.0, 0.0)
    return vnorm(vcross(d, a))


def newell(pts):
    n = [0.0, 0.0, 0.0]
    for i, p in enumerate(pts):
        q = pts[(i + 1) % len(pts)]
        n[0] += (p[1] - q[1]) * (p[2] + q[2])
        n[1] += (p[2] - q[2]) * (p[0] + q[0])
        n[2] += (p[0] - q[0]) * (p[1] + q[1])
    return tuple(n)


def mat_mul(A, B):
    return tuple(tuple(sum(A[i][k] * B[k][j] for k in range(3)) for j in range(3)) for i in range(3))


def mat_vec(A, v):
    return (A[0][0] * v[0] + A[0][1] * v[1] + A[0][2] * v[2], A[1][0] * v[0] + A[1][1] * v[1] + A[1][2] * v[2],
            A[2][0] * v[0] + A[2][1] * v[1] + A[2][2] * v[2])


IDENT = ((1.0, 0.0, 0.0), (0.0, 1.0, 0.0), (0.0, 0.0, 1.0))


def rot(axis, deg):
    """Matrice de rotation de deg degrés autour de l'axe 'x', 'y', 'z' ou d'un vecteur."""
    a = math.radians(deg)
    c, s = math.cos(a), math.sin(a)
    if axis == "x":
        return ((1, 0, 0), (0, c, -s), (0, s, c))
    if axis == "y":
        return ((c, 0, s), (0, 1, 0), (-s, 0, c))
    if axis == "z":
        return ((c, -s, 0), (s, c, 0), (0, 0, 1))
    x, y, z = vnorm(axis)
    t = 1 - c
    return ((t * x * x + c, t * x * y - s * z, t * x * z + s * y), (t * x * y + s * z, t * y * y + c, t * y * z - s * x),
            (t * x * z - s * y, t * y * z + s * x, t * z * z + c))


def basis(x=None, y=None, z=None):
    """Matrice dont les colonnes sont les axes locaux donnés (deux suffisent, le troisième est déduit)."""
    if x is None:
        y, z = vnorm(y), vnorm(z)
        x = vcross(y, z)
    elif y is None:
        x, z = vnorm(x), vnorm(z)
        y = vcross(z, x)
    else:
        x, y = vnorm(x), vnorm(y)
        z = vcross(x, y)
    return ((x[0], y[0], z[0]), (x[1], y[1], z[1]), (x[2], y[2], z[2]))


# --------------------------------------------------------------------------------------------------------------------
# Matières : clé -> texture, rvmat, type de région (bande raccordable en U ou rectangle), rectangle UV
# --------------------------------------------------------------------------------------------------------------------
class Mat:
    __slots__ = ("key", "tex", "rvmat", "kind", "rect", "tile", "atlas")

    def __init__(self, key, tex, rvmat, kind="rect", rect=(0, 0, 1, 1), tile=1.0, atlas=None):
        self.key, self.tex, self.rvmat, self.kind, self.rect, self.tile, self.atlas = key, tex, rvmat, kind, rect, tile, atlas


MATS = {}


def flat_mat(key, rgb, rvmat, alpha=1.0):
    """Matière unie (texture procédurale du moteur) : voyants, verre, caoutchouc."""
    MATS[key] = Mat(key, "#(argb,8,8,3)color(%g,%g,%g,%g,co)" % (rgb[0], rgb[1], rgb[2], alpha), rvmat, "flat")
    MATS[key].atlas = tuple(rgb)


# --------------------------------------------------------------------------------------------------------------------
# Maillage
# --------------------------------------------------------------------------------------------------------------------
class Solid:
    """Volume simple fermé (convexe) : points partagés, triangles ; usages : lettres de S ; sels : sélections (os)."""
    __slots__ = ("pts", "tris", "use", "sels", "tag")

    def __init__(self, pts, tris, use, sels, tag):
        self.pts, self.tris, self.use, self.sels, self.tag = pts, tris, use, sels, tag


class Mesh:
    def __init__(self, d=0, seed=1):
        self.d = d
        self.pts = []          # positions
        self.ptag = []         # (nature, groupe) par point : ruine
        self.faces = []        # (indices, uvs, normales par coin, matière, sélections)
        self.solids = []
        self.roads = []        # quadrilatères praticables
        self.mem = {}          # points mémoire : nom -> position (ou liste de positions)
        self.R, self.t = IDENT, (0.0, 0.0, 0.0)
        self._stack = []
        self.sels = ()
        self.tag = ("struct", 0)
        self._gid = 0
        self.rng = random.Random(seed)
        self.record = True     # dépôt des volumes simples

    # --- contexte ---
    def push(self, R=None, t=(0.0, 0.0, 0.0), sels=None, tag=None):
        self._stack.append((self.R, self.t, self.sels, self.tag))
        if R is not None or t != (0.0, 0.0, 0.0):
            R = R or IDENT
            self.t = vadd(self.t, mat_vec(self.R, t))
            self.R = mat_mul(self.R, R)
        if sels is not None:
            self.sels = tuple(self.sels) + tuple(sels)
        if tag is not None:
            if tag == "equip":
                self._gid += 1
                self.tag = ("equip", self._gid, self.t)
            else:
                self.tag = (tag, 0)
        return self

    def pop(self):
        self.R, self.t, self.sels, self.tag = self._stack.pop()

    def __enter__(self):
        return self

    def __exit__(self, *a):
        self.pop()

    def W(self, p):
        """Point local -> monde."""
        return vadd(mat_vec(self.R, p), self.t)

    def D(self, v):
        """Direction locale -> monde."""
        return mat_vec(self.R, v)

    # --- points et faces (coordonnées locales) ---
    def pt(self, p):
        self.pts.append(self.W(p))
        self.ptag.append(self.tag)
        return len(self.pts) - 1

    def facei(self, idx, uvs, normals, mat):
        self.faces.append((tuple(idx), tuple(uvs), tuple(self.D(n) for n in normals), mat, self.sels))

    def face(self, pts, mat, uvs=None, out=None, st=None):
        """Face plane à arêtes vives (3 ou 4 points locaux). out : direction extérieure (retourne la face au besoin).
        uvs : UV locales à la région (a, b) ; st : (s en m, t 0..1) par coin pour une bande ; sinon automatique."""
        n = newell(pts)
        if vdot(n, n) < 1e-16:
            return
        if out is not None and vdot(n, out) < 0:
            pts = list(reversed(pts))
            if uvs is not None:
                uvs = list(reversed(uvs))
            if st is not None:
                st = list(reversed(st))
            n = (-n[0], -n[1], -n[2])
        n = vnorm(n)
        M = MATS[mat]
        if uvs is not None:
            uv = [rect_uv(M, a, b) for a, b in uvs]
        elif st is not None:
            uv = [strip_uv(M, s, t) for s, t in st]
        else:
            uv = auto_uv(M, pts, n, self.rng)
        idx = [self.pt(p) for p in pts]
        self.facei(idx, uv, [n] * len(idx), mat)

    def solid(self, pts, tris, use):
        if self.record and use:
            self.solids.append(Solid([self.W(p) for p in pts], tris, use, self.sels, self.tag))

    def road(self, quad):
        self.roads.append([self.W(p) for p in quad])

    def memory(self, name, p):
        self.mem.setdefault(name, []).append(self.W(p))


def rect_uv(M, a, b):
    u0, v0, u1, v1 = M.rect
    return (u0 + (u1 - u0) * a, v0 + (v1 - v0) * b)


def strip_uv(M, s, t):
    u0, v0, u1, v1 = M.rect
    if M.kind != "strip":
        return (u0 + (u1 - u0) * (s / M.tile % 1.0), v0 + (v1 - v0) * t)
    return (s / M.tile, v0 + (v1 - v0) * (0.04 + 0.92 * t))


def auto_uv(M, pts, n, rng):
    """UV automatiques d'une face plane : bande -> le long du plus grand côté ; rectangle -> la face remplit la région
    (haut de la région vers +Z, ou vers +Y pour une face horizontale)."""
    if M.kind == "flat":
        return [(0.5, 0.5)] * len(pts)
    if M.kind == "strip":
        best, e = -1.0, None
        for i, p in enumerate(pts):
            q = pts[(i + 1) % len(pts)]
            L = vlen(vsub(q, p))
            if L > best:
                best, e = L, vnorm(vsub(q, p))
        f = vcross(n, e)
        ss = [vdot(p, e) for p in pts]
        tt = [vdot(p, f) for p in pts]
        t0, t1 = min(tt), max(tt)
        s0 = min(ss) - rng.random() * M.tile
        return [strip_uv(M, s - s0, (t - t0) / (t1 - t0) if t1 > t0 else 0.5) for s, t in zip(ss, tt)]
    up = (0.0, 0.0, 1.0) if abs(n[2]) < 0.9 else (0.0, 1.0, 0.0)
    right = vnorm(vcross(up, n))
    upv = vcross(n, right)
    aa = [vdot(p, right) for p in pts]
    bb = [vdot(p, upv) for p in pts]
    a0, a1, b0, b1 = min(aa), max(aa), min(bb), max(bb)
    return [rect_uv(M, (a - a0) / (a1 - a0) if a1 > a0 else 0.5, 1 - ((b - b0) / (b1 - b0) if b1 > b0 else 0.5)) for a, b in zip(aa, bb)]


# --------------------------------------------------------------------------------------------------------------------
# Volumes simples
# --------------------------------------------------------------------------------------------------------------------
def prism_solid(ring0, ring1):
    """Prisme fermé entre deux contours de même taille (sens direct vu depuis ring1 vers l'extérieur)."""
    n = len(ring0)
    pts = list(ring0) + list(ring1)
    tris = []
    for i in range(n):
        j = (i + 1) % n
        tris += [(i, j, n + j), (i, n + j, n + i)]
    for i in range(1, n - 1):
        tris.append((0, i + 1, i))
        tris.append((n, n + i, n + i + 1))
    return pts, tris


def orient_solid(pts, tris):
    """Oriente tous les triangles d'un volume convexe vers l'extérieur."""
    c = tuple(sum(p[k] for p in pts) / len(pts) for k in range(3))
    out = []
    for a, b, c_ in tris:
        n = vcross(vsub(pts[b], pts[a]), vsub(pts[c_], pts[a]))
        fc = vmul(vadd(vadd(pts[a], pts[b]), pts[c_]), 1 / 3)
        out.append((a, b, c_) if vdot(n, vsub(fc, c)) >= 0 else (a, c_, b))
    return out


def add_prism_solid(m, p0, p1, a, b, prof, use):
    if not (m.record and use):
        return
    r0 = [vadd(p0, vadd(vmul(a, x), vmul(b, y))) for x, y in prof]
    r1 = [vadd(p1, vadd(vmul(a, x), vmul(b, y))) for x, y in prof]
    pts, tris = prism_solid(r0, r1)
    m.solid(pts, orient_solid(pts, tris), use)


def solid_box(m, x0, x1, y0, y1, z0, z1, use):
    add_prism_solid(m, (0, 0, z0), (0, 0, z1), (1, 0, 0), (0, 1, 0), [(x0, y0), (x1, y0), (x1, y1), (x0, y1)], use)


def solid_hull8(m, bottom4, top4, use):
    """Hexaèdre convexe quelconque (4 points bas, 4 points haut dans le même ordre)."""
    if not (m.record and use):
        return
    pts, tris = prism_solid(bottom4, top4)
    m.solid(pts, orient_solid(pts, tris), use)


# --------------------------------------------------------------------------------------------------------------------
# Primitives
# --------------------------------------------------------------------------------------------------------------------
def _frame(p0, p1, a=None):
    d = vsub(p1, p0)
    L = vlen(d)
    d = vnorm(d)
    if a is not None:
        a = vsub(a, vmul(d, vdot(a, d)))
        if vlen(a) < 1e-6:
            a = None
    a = vnorm(a) if a is not None else vortho(d)
    b = vcross(d, a)
    return d, L, a, b


def tube(m, p0, p1, r, mat, n=8, r1=None, caps=(True, True), S="", a=None, uoff=None, around=1.0):
    """Tube ou tronc de cône lisse de p0 à p1 (rayon r en p0, r1 en p1). S : volumes simples (prisme à 4 pans)."""
    if r1 is None:
        r1 = r
    d, L, a, b = _frame(p0, p1, a)
    if L < 1e-6:
        return
    M = MATS[mat]
    uo = m.rng.random() * M.tile if uoff is None else uoff
    cs = [(math.cos(2 * math.pi * i / n), math.sin(2 * math.pi * i / n)) for i in range(n)]
    slope = (r - r1) / L
    ring0, ring1, nrm = [], [], []
    for c, s in cs:
        rad = vadd(vmul(a, c), vmul(b, s))
        ring0.append(m.pt(vadd(p0, vmul(rad, r))))
        ring1.append(m.pt(vadd(p1, vmul(rad, r1))))
        nrm.append(vnorm(vadd(rad, vmul(d, slope))))
    # Sommets de couture dupliqués pour les UV (le tour complet de la bande / région).
    for i in range(n):
        j = (i + 1) % n
        t0, t1 = i / n * around, (i + 1) / n * around
        if M.kind == "strip":
            uv = [strip_uv(M, uo, t0), strip_uv(M, uo, t1), strip_uv(M, uo + L, t1), strip_uv(M, uo + L, t0)]
        elif M.kind == "rect":
            uv = [rect_uv(M, t0, 1), rect_uv(M, t1, 1), rect_uv(M, t1, 0), rect_uv(M, t0, 0)]
        else:
            uv = [(0.5, 0.5)] * 4
        m.facei((ring0[i], ring0[j], ring1[j], ring1[i]), uv, (nrm[i], nrm[j], nrm[j], nrm[i]), mat)
    if caps[0] and r > 1e-5:
        _cap(m, [vadd(p0, vmul(vadd(vmul(a, c), vmul(b, s)), r)) for c, s in reversed(cs)], mat, vmul(d, -1))
    if caps[1] and r1 > 1e-5:
        _cap(m, [vadd(p1, vmul(vadd(vmul(a, c), vmul(b, s)), r1)) for c, s in cs], mat, d)
    if S:
        k = 0.92 * max(r, r1)
        add_prism_solid(m, p0, p1, a, b, [(k, 0), (0, k), (-k, 0), (0, -k)], S)


def _cap(m, ring, mat, out):
    """Bouchon d'un contour convexe : éventail de quadrilatères / triangles."""
    n = len(ring)
    M = MATS[mat]
    c = tuple(sum(p[k] for p in ring) / n for k in range(3))
    rr = max(vlen(vsub(p, c)) for p in ring) or 1.0
    e = vnorm(vsub(ring[0], c))
    f = vcross(vnorm(out), e)

    def uv(p):
        q = vsub(p, c)
        a_, b_ = 0.5 + 0.5 * vdot(q, e) / rr, 0.5 + 0.5 * vdot(q, f) / rr
        return strip_uv(M, a_ * 2 * rr, b_) if M.kind == "strip" else (rect_uv(M, a_, b_) if M.kind == "rect" else (0.5, 0.5))
    i = 1
    nw = vnorm(out)
    while i < n - 1:
        poly = [ring[0], ring[i], ring[i + 1]] + ([ring[i + 2]] if i + 2 < n else [])
        idx = [m.pt(p) for p in poly]
        m.facei(idx, [uv(p) for p in poly], [nw] * len(poly), mat)
        i += 2 if i + 2 < n else 1


def prism(m, p0, p1, prof, mat, a=None, b=None, caps=True, S="", smooth=False):
    """Extrusion à arêtes vives d'un contour 2D convexe (x selon a, y selon b, sens direct) de p0 à p1."""
    d, L, a2, b2 = _frame(p0, p1, a)
    if L < 1e-6:
        return
    if b is not None:
        b2 = vnorm(vsub(vsub(b, vmul(d, vdot(b, d))), vmul(a2, vdot(b, a2))))
        if vdot(vcross(a2, b2), d) < 0:
            prof = [(x, y) for x, y in reversed(prof)]
    a, b = a2, b2
    M = MATS[mat]
    uo = m.rng.random() * M.tile
    n = len(prof)
    P0 = [vadd(p0, vadd(vmul(a, x), vmul(b, y))) for x, y in prof]
    P1 = [vadd(p1, vadd(vmul(a, x), vmul(b, y))) for x, y in prof]
    for i in range(n):
        j = (i + 1) % n
        if M.kind == "strip":
            m.face([P0[i], P0[j], P1[j], P1[i]], mat, st=[(uo, 0), (uo, 1), (uo + L, 1), (uo + L, 0)])
        else:
            m.face([P0[i], P0[j], P1[j], P1[i]], mat)
    if caps:
        _cap(m, list(reversed(P0)), mat, vmul(d, -1))
        _cap(m, P1, mat, d)
    if S:
        hull = prof if n <= 4 else [prof[0], prof[n // 3], prof[2 * n // 3]]
        add_prism_solid(m, p0, p1, a, b, hull, S)


def beam(m, p0, p1, w, h, mat, a=None, caps=True, S=""):
    """Barre rectangulaire (w selon a, h selon l'autre axe)."""
    prism(m, p0, p1, [(-w / 2, -h / 2), (w / 2, -h / 2), (w / 2, h / 2), (-w / 2, h / 2)], mat, a, None, caps, S)


def angle(m, p0, p1, s, mat, a, b, t=None, S=""):
    """Cornière à ailes égales s (épaisseur t), arête extérieure sur p0-p1, ailes vers +a et +b.
    d <= 1 : vrai profil en L ; d >= 2 : prisme triangulaire (3 faces)."""
    t = t or s * 0.11
    if m.d >= 2:
        prism(m, p0, p1, [(0, 0), (s, 0), (0, s)], mat, a, b, caps=False, S=S)
    else:
        prism(m, p0, p1, [(0, 0), (s, 0), (s, t), (t, t), (t, s), (0, s)], mat, a, b, caps=(m.d == 0), S="")
        if S:
            d, L, a2, b2 = _frame(p0, p1, a)
            bb = vnorm(vsub(vsub(b, vmul(d, vdot(b, d))), vmul(a2, vdot(b, a2))))
            add_prism_solid(m, p0, p1, a2, bb, [(0, 0), (s, 0), (0, s)], S)


def box(m, x0, x1, y0, y1, z0, z1, mat, S="", skip="", bevel=0.0):
    """Boîte alignée sur le repère courant. mat : clé ou dict par face ('+x', '-x', '+y', '-y', '+z', '-z', 'side', '*').
    bevel > 0 (d == 0) : arêtes verticales chanfreinées."""
    def mk(f):
        if isinstance(mat, str):
            return mat
        return mat.get(f) or (mat.get("side") if f[1] in "xy" else None) or mat["*"]
    if bevel > 0 and m.d == 0:
        c = bevel
        prof = [(x0 + c, y0), (x1 - c, y0), (x1, y0 + c), (x1, y1 - c), (x1 - c, y1), (x0 + c, y1), (x0, y1 - c), (x0, y0 + c)]
        names = ["-y", None, "+x", None, "+y", None, "-x", None]
        for i in range(8):
            j = (i + 1) % 8
            f = names[i] or (names[(i + 1) % 8])
            (ax, ay), (bx, by) = prof[i], prof[j]
            m.face([(ax, ay, z0), (bx, by, z0), (bx, by, z1), (ax, ay, z1)], mk(f), uvs=None if names[i] else [(0.0, 1), (0.02, 1), (0.02, 0), (0.0, 0)])
        if "+z" not in skip:
            _cap(m, [(x, y, z1) for x, y in prof], mk("+z"), (0, 0, 1))
        if "-z" not in skip:
            _cap(m, [(x, y, z0) for x, y in reversed(prof)], mk("-z"), (0, 0, -1))
    else:
        F = {"-x": ([(x0, y1, z0), (x0, y0, z0), (x0, y0, z1), (x0, y1, z1)], (-1, 0, 0)),
             "+x": ([(x1, y0, z0), (x1, y1, z0), (x1, y1, z1), (x1, y0, z1)], (1, 0, 0)),
             "-y": ([(x0, y0, z0), (x1, y0, z0), (x1, y0, z1), (x0, y0, z1)], (0, -1, 0)),
             "+y": ([(x1, y1, z0), (x0, y1, z0), (x0, y1, z1), (x1, y1, z1)], (0, 1, 0)),
             "-z": ([(x0, y1, z0), (x1, y1, z0), (x1, y0, z0), (x0, y0, z0)], (0, 0, -1)),
             "+z": ([(x0, y0, z1), (x1, y0, z1), (x1, y1, z1), (x0, y1, z1)], (0, 0, 1))}
        for f, (pts, out) in F.items():
            if f in skip:
                continue
            k = mk(f)
            if MATS[k].kind == "rect":
                m.face(pts, k, uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=out)
            else:
                m.face(pts, k, out=out)
    if S:
        solid_box(m, x0, x1, y0, y1, z0, z1, S)


def lathe(m, prof, mat, n=16, polar=False, closed=(False, False), arc=1.0):
    """Révolution lisse du profil [(rayon, z)] autour de l'axe Z local. polar : UV radiales (parabole vue de face)."""
    M = MATS[mat]
    k = len(prof)
    full = arc >= 0.999
    cols = n if full else n + 1
    rmax = max(r for r, _ in prof) or 1.0
    Ls = [0.0]
    for (r0, z0), (r1, z1) in zip(prof, prof[1:]):
        Ls.append(Ls[-1] + math.hypot(r1 - r0, z1 - z0))
    idx = [[m.pt((r * math.cos(2 * math.pi * arc * i / n), r * math.sin(2 * math.pi * arc * i / n), z)) for r, z in prof] for i in range(cols)]

    def nrm(i, j):
        j0, j1 = max(0, j - 1), min(k - 1, j + 1)
        dr, dz = prof[j1][0] - prof[j0][0], prof[j1][1] - prof[j0][1]
        L = math.hypot(dr, dz) or 1.0
        a = 2 * math.pi * arc * i / n
        return (dz / L * math.cos(a), dz / L * math.sin(a), -dr / L)

    def uv(i, j):
        if M.kind == "flat":
            return (0.5, 0.5)
        if polar:
            a = 2 * math.pi * arc * i / n
            r = prof[j][0] / rmax
            return rect_uv(M, 0.5 + 0.5 * r * math.cos(a), 0.5 - 0.5 * r * math.sin(a))
        if M.kind == "strip":
            return strip_uv(M, Ls[j], i / n)
        return rect_uv(M, i / n, 1 - Ls[j] / (Ls[-1] or 1.0))
    for i in range(n):
        i1 = (i + 1) % cols if full else i + 1
        for j in range(k - 1):
            if prof[j][0] < 1e-6 and prof[j + 1][0] < 1e-6:
                continue
            q = [(i, j), (i1, j), (i1, j + 1), (i, j + 1)]
            uvs = [uv(i if a == i else i + 1, b) for a, b in q]
            if prof[j][0] < 1e-6:
                q, uvs = q[1:], uvs[1:]
            elif prof[j + 1][0] < 1e-6:
                q, uvs = q[:3], uvs[:3]
            # Profil montant : rayon vers l'extérieur => sens direct (i, i+1) en bas puis en haut.
            m.facei([idx[a][b] for a, b in q], uvs, [nrm(a, b) for a, b in q], mat)
    if closed[0] and prof[0][0] > 1e-6:
        _cap(m, [(prof[0][0] * math.cos(2 * math.pi * i / n), prof[0][0] * math.sin(2 * math.pi * i / n), prof[0][1]) for i in reversed(range(n))], mat, (0, 0, -1))
    if closed[1] and prof[-1][0] > 1e-6:
        _cap(m, [(prof[-1][0] * math.cos(2 * math.pi * i / n), prof[-1][0] * math.sin(2 * math.pi * i / n), prof[-1][1]) for i in range(n)], mat, (0, 0, 1))


def plate(m, poly, th, mat, out):
    """Plaque mince (gousset, platine) : polygone convexe 3D (3 ou 4 points), épaisseur th du côté de out.
    d == 0 : deux faces et la tranche ; d >= 1 : deux faces."""
    out = vnorm(out)
    top = [vadd(p, vmul(out, th)) for p in poly]
    m.face(top, mat, out=out)
    m.face(poly, mat, out=vmul(out, -1))
    if m.d == 0:
        n = len(poly)
        c = tuple(sum(p[k] for p in poly) / n for k in range(3))
        for i in range(n):
            j = (i + 1) % n
            mid = vmul(vadd(poly[i], poly[j]), 0.5)
            m.face([poly[i], poly[j], top[j], top[i]], mat, out=vsub(mid, c))


def cable(m, pts, r, mat, n=5, S=""):
    """Câble lisse le long d'une polyligne (anneaux partagés aux coudes)."""
    if len(pts) < 2:
        return
    M = MATS[mat]
    rings, nrms = [], []
    a = None
    s = [0.0]
    for i, p in enumerate(pts):
        d0 = vnorm(vsub(p, pts[i - 1])) if i > 0 else None
        d1 = vnorm(vsub(pts[i + 1], p)) if i < len(pts) - 1 else None
        d = vnorm(vadd(d0, d1)) if d0 and d1 else (d0 or d1)
        if vlen(d) < 1e-6:
            d = d1
        a = vortho(d) if a is None else vnorm(vsub(a, vmul(d, vdot(a, d))))
        b = vcross(d, a)
        ring, nr = [], []
        for k in range(n):
            c, sn = math.cos(2 * math.pi * k / n), math.sin(2 * math.pi * k / n)
            rad = vadd(vmul(a, c), vmul(b, sn))
            ring.append(m.pt(vadd(p, vmul(rad, r))))
            nr.append(rad)
        rings.append(ring)
        nrms.append(nr)
        if i > 0:
            s.append(s[-1] + vlen(vsub(p, pts[i - 1])))
    uo = m.rng.random() * M.tile
    for i in range(len(pts) - 1):
        for k in range(n):
            j = (k + 1) % n
            if M.kind == "flat":
                uv = [(0.5, 0.5)] * 4
            else:
                uv = [strip_uv(M, uo + s[i], k / n), strip_uv(M, uo + s[i], (k + 1) / n), strip_uv(M, uo + s[i + 1], (k + 1) / n), strip_uv(M, uo + s[i + 1], k / n)]
            m.facei((rings[i][k], rings[i][j], rings[i + 1][j], rings[i + 1][k]), uv, (nrms[i][k], nrms[i][j], nrms[i + 1][j], nrms[i + 1][k]), mat)
    if S:
        for p, q in zip(pts, pts[1:]):
            d, L, a2, b2 = _frame(p, q)
            if L > 1e-4:
                add_prism_solid(m, p, q, a2, b2, [(r, 0), (-r * 0.5, r * 0.87), (-r * 0.5, -r * 0.87)], S)


def catenary(p0, p1, sag, n=8):
    """Points d'un câble pendant entre p0 et p1 (flèche sag au milieu)."""
    return [vadd(vlerp(p0, p1, i / n), (0, 0, -sag * 4 * (i / n) * (1 - i / n))) for i in range(n + 1)]


def quad(m, pts, mat, uvs=((0, 1), (1, 1), (1, 0), (0, 0)), double=False):
    """Quadrilatère texturé (panneau, grillage) ; double : visible des deux côtés."""
    m.face(list(pts), mat, uvs=list(uvs))
    if double:
        m.face(list(reversed(pts)), mat, uvs=list(reversed(uvs)))


def bolt(m, p, axis, r, h, mat, n=6):
    """Tête de boulon / écrou (prisme hexagonal), de près seulement."""
    if m.d > 0:
        return
    tube(m, p, vadd(p, vmul(vnorm(axis), h)), r, mat, n=n, caps=(False, True))


# --------------------------------------------------------------------------------------------------------------------
# Écriture MLOD
# --------------------------------------------------------------------------------------------------------------------
def _asciiz(s):
    return s.encode("ascii") + b"\0"


def _tagg(name, data):
    return b"\x01" + _asciiz(name) + struct.pack("<I", len(data)) + data


def lod_bytes(pts, faces, sels, resolution, props=(), mass=None, sharp=False, sharp_edges=None):
    """faces : (indices, uvs, normales par coin, texture, rvmat). sels : nom -> (points, faces)."""
    nmap, normals = {}, []
    out = bytearray()
    uvset = bytearray()
    for idx, uvs, nrm, tex, mat in faces:
        out += struct.pack("<I", len(idx))
        for k in range(4):
            if k < len(idx):
                key = (round(nrm[k][0], 4), round(nrm[k][1], 4), round(nrm[k][2], 4))
                ni = nmap.get(key)
                if ni is None:
                    ni = nmap[key] = len(normals)
                    normals.append(key)
                out += struct.pack("<IIff", idx[k], ni, uvs[k][0], uvs[k][1])
                uvset += struct.pack("<ff", uvs[k][0], uvs[k][1])
            else:
                out += struct.pack("<IIff", 0, 0, 0.0, 0.0)
        out += struct.pack("<I", 0) + _asciiz(tex) + _asciiz(mat)
    head = b"P3DM" + struct.pack("<IIIIII", 28, 256, len(pts), len(normals), len(faces), 0)
    body = bytearray()
    for p in pts:
        body += struct.pack("<fffI", p[0], p[2], p[1], 0)
    for n in normals:
        body += struct.pack("<fff", -n[0], -n[2], -n[1])
    body += out
    body += b"TAGG"
    if sharp:
        edges = set()
        for idx, *_ in faces:
            for i in range(len(idx)):
                a, b = idx[i], idx[(i + 1) % len(idx)]
                edges.add((min(a, b), max(a, b)))
        body += _tagg("#SharpEdges#", b"".join(struct.pack("<II", a, b) for a, b in sorted(edges)))
    else:
        if sharp_edges:
            body += _tagg("#SharpEdges#", b"".join(struct.pack("<II", a, b) for a, b in sharp_edges))
        body += _tagg("#UVSet#", struct.pack("<I", 0) + bytes(uvset))
    np_, nf = len(pts), len(faces)
    for name, (pset, fset) in sels.items():
        pb = bytearray(np_)
        for i in pset:
            pb[i] = 1
        fb = bytearray(nf)
        for i in fset:
            fb[i] = 1
        body += _tagg(name, bytes(pb) + bytes(fb))
    if mass is not None:
        body += _tagg("#Mass#", struct.pack("<%df" % len(mass), *mass))
    for k, v in props:
        body += _tagg("#Property#", k.encode("ascii").ljust(64, b"\0") + v.encode("ascii").ljust(64, b"\0"))
    body += _tagg("#EndOfFile#", b"")
    body += struct.pack("<f", resolution)
    return head + bytes(body)


def visual_lod(m, resolution):
    """Points soudés par position ; arêtes vives (#SharpEdges#) là où les normales des faces voisines diffèrent :
    même rendu que des sommets propres à chaque face, fichier et nombre de sommets réduits."""
    faces, sels = [], {}
    remap, pts, key_of = {}, [], []
    for p in m.pts:
        k = (round(p[0], 4), round(p[1], 4), round(p[2], 4))
        i = remap.get(k)
        if i is None:
            i = remap[k] = len(pts)
            pts.append(p)
        key_of.append(i)
    edges = {}
    for fi, (idx, uvs, nrm, mat, fsels) in enumerate(m.faces):
        M = MATS[mat]
        idx = tuple(key_of[i] for i in idx)
        faces.append((idx, uvs, nrm, M.tex, M.rvmat))
        n = len(idx)
        for k in range(n):
            a, b = idx[k], idx[(k + 1) % n]
            na, nb = nrm[k], nrm[(k + 1) % n]
            if a > b:
                a, b, na, nb = b, a, nb, na
            edges.setdefault((a, b), []).append((na, nb))
        for s in fsels:
            e = sels.setdefault(s, (set(), set()))
            e[1].add(fi)
            e[0].update(idx)
    sharp = []
    for e, lst in edges.items():
        if len(lst) > 1 and any(vdot(lst[0][0], q[0]) < 0.999 or vdot(lst[0][1], q[1]) < 0.999 for q in lst[1:]):
            sharp.append(e)
    return lod_bytes(pts, faces, sels, resolution, sharp_edges=sorted(sharp))


def solids_lod(solids, use, resolution, components=False, mass_total=0.0, props=(), sharp=True):
    pts, faces, sels = [], [], {}
    ci = 0
    for s in solids:
        if use not in s.use:
            continue
        off = len(pts)
        pts += s.pts
        f0 = len(faces)
        for a, b, c in s.tris:
            n = vnorm(vcross(vsub(s.pts[b], s.pts[a]), vsub(s.pts[c], s.pts[a])))
            faces.append(((off + a, off + b, off + c), [(0.0, 0.0)] * 3, (n, n, n), "", ""))
        pr, fr = set(range(off, len(pts))), set(range(f0, len(faces)))
        if components:
            ci += 1
            sels["Component%02d" % ci] = (pr, fr)
        for name in s.sels:
            e = sels.setdefault(name, (set(), set()))
            e[0].update(pr)
            e[1].update(fr)
    if not pts:
        return None
    mass = [mass_total / len(pts)] * len(pts) if mass_total > 0 else None
    return lod_bytes(pts, faces, sels, resolution, props, mass, sharp=sharp)


def memory_lod(mem):
    pts, sels = [], {}
    for name, plist in mem.items():
        for p in plist:
            sels.setdefault(name, (set(), set()))[0].add(len(pts))
            pts.append(p)
    return lod_bytes(pts, [], sels, 1e15)


def roadway_lod(roads):
    pts, faces = [], []
    for q in roads:
        n = vnorm(newell(q))
        if n[2] < 0:
            q = list(reversed(q))
            n = (-n[0], -n[1], -n[2])
        off = len(pts)
        pts += q
        faces.append((tuple(range(off, off + len(q))), [(0.0, 0.0)] * len(q), [n] * len(q), "", ""))
    return lod_bytes(pts, faces, {}, 3e15)


LOD_RES = (1.0, 4.0, 12.0, 30.0, 80.0)


def write_p3d(path, build, mass, geo_props=(), lods=5, shadow_from=2, res=LOD_RES, report=None):
    """build(d) -> Mesh. LOD visuelles res[0..lods-1], ombres 0 et 10, géométrie, mémoire, chemin, tir, vue.
    Les volumes simples sont pris sur la construction de détail shadow_from. Renvoie le maillage de détail 0."""
    meshes = [build(d) for d in range(lods)]
    src = meshes[min(shadow_from, lods - 1)]
    out = [visual_lod(m, r) for m, r in zip(meshes, res)]
    stats = {"lods": [(r, len(m.faces), sum(len(f[0]) - 2 for f in m.faces), len({(MATS[f[3]].tex, MATS[f[3]].rvmat) for f in m.faces})) for m, r in zip(meshes, res)]}
    for use, r, kw in (("s", 10000.0, {}), ("S", 10010.0, {}),
                       ("g", 1e13, dict(components=True, mass_total=mass, props=(("autocenter", "0"),) + tuple(geo_props), sharp=False))):
        b = solids_lod(src.solids, use, r, **kw)
        if b:
            out.append(b)
        stats[use] = (sum(1 for s in src.solids if use in s.use), sum(len(s.tris) for s in src.solids if use in s.use))
    if meshes[0].mem:
        out.append(memory_lod(meshes[0].mem))
    if src.roads:
        out.append(roadway_lod(src.roads))
    for use, r in (("f", 6e15), ("v", 7e15)):
        b = solids_lod(src.solids, use, r, components=True, sharp=False)
        if b:
            out.append(b)
        stats[use] = (sum(1 for s in src.solids if use in s.use), sum(len(s.tris) for s in src.solids if use in s.use))
    with open(path, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, len(out)) + b"".join(out))
    stats["size"] = os.path.getsize(path)
    stats["mem"] = sorted(meshes[0].mem)
    stats["sels"] = sorted({s for f in meshes[0].faces for s in f[4]})
    if report is not None:
        report[os.path.basename(path)] = stats
    return meshes[0]


def check_mesh(m, name=""):
    """Contrôles : faces dégénérées, UV hors atlas (rectangles), volumes fermés. Renvoie la liste des défauts."""
    bad = []
    for idx, uvs, nrm, mat, _ in m.faces:
        P = [m.pts[i] for i in idx]
        if vlen(newell(P)) < 1e-10:
            bad.append("face dégénérée (%s)" % mat)
        M = MATS[mat]
        if M.kind == "rect":
            u0, v0, u1, v1 = M.rect
            for u, v in uvs:
                if not (min(u0, u1) - 1e-4 <= u <= max(u0, u1) + 1e-4 and min(v0, v1) - 1e-4 <= v <= max(v0, v1) + 1e-4):
                    bad.append("UV hors région (%s)" % mat)
                    break
    for s in m.solids:
        edges = {}
        for a, b, c in s.tris:
            for e in ((a, b), (b, c), (c, a)):
                edges[e] = edges.get(e, 0) + 1
        if any(edges.get((b, a), 0) != 1 or n != 1 for (a, b), n in edges.items()):
            bad.append("volume non fermé")
    if bad:
        print("  ! %s : %d défauts (%s)" % (name, len(bad), ", ".join(sorted(set(bad)))))
    return bad


# --------------------------------------------------------------------------------------------------------------------
# Export OBJ (rendus Blender) : un groupe par matière de rendu (atlas ou couleur unie)
# --------------------------------------------------------------------------------------------------------------------
def export_obj(m, path, atlas_png):
    """atlas_png : texture PAA (chemin MLOD) -> dict(co=..., nohq=..., smdi=..., rm=...) des PNG ; écrit path + .json."""
    groups = {}
    for idx, uvs, nrm, mat, sels in m.faces:
        M = MATS[mat]
        key = M.tex if M.kind != "flat" else "flat:" + mat
        groups.setdefault(key, []).append((idx, uvs, nrm))
    mats = {}
    with open(path, "w") as f:
        for p in m.pts:
            f.write("v %.5f %.5f %.5f\n" % p)
        vt = vn = 0
        for gi, (key, fl) in enumerate(sorted(groups.items())):
            name = "mat%d" % gi
            if key.startswith("flat:"):
                M = MATS[key[5:]]
                mats[name] = {"flat": list(M.atlas), "rvmat": os.path.basename(M.rvmat), "key": key[5:]}
            else:
                mats[name] = dict(atlas_png.get(key, {}), tex=key)
            f.write("usemtl %s\n" % name)
            for idx, uvs, nrm in fl:
                for (u, v), n in zip(uvs, nrm):
                    f.write("vt %.5f %.5f\nvn %.4f %.4f %.4f\n" % (u, 1 - v, n[0], n[1], n[2]))
                f.write("f " + " ".join("%d/%d/%d" % (i + 1, vt + k + 1, vn + k + 1) for k, i in enumerate(idx)) + "\n")
                vt += len(idx)
                vn += len(idx)
    with open(path + ".json", "w") as f:
        json.dump({"materials": mats, "memory": {k: v for k, v in m.mem.items()}}, f)


# --------------------------------------------------------------------------------------------------------------------
# Textures procédurales
# --------------------------------------------------------------------------------------------------------------------
_rng = np.random.default_rng(1911)


def noise(h, w, beta=2.0, aniso=(1.0, 1.0), rng=None):
    """Bruit périodique en 1/f^beta (raccordable dans les deux sens), 0..1."""
    rng = rng or _rng
    fx = np.fft.fftfreq(w)[None, :] * w * aniso[0]
    fy = np.fft.fftfreq(h)[:, None] * h * aniso[1]
    f = np.sqrt(fx * fx + fy * fy)
    f[0, 0] = 1.0
    spec = (rng.normal(size=(h, w)) + 1j * rng.normal(size=(h, w))) / f ** (beta / 2)
    spec[0, 0] = 0
    out = np.real(np.fft.ifft2(spec))
    out -= out.min()
    return out / max(out.max(), 1e-9)


def smoothstep(a, b, x):
    t = np.clip((x - a) / (b - a), 0, 1)
    return t * t * (3 - 2 * t)


def blur(a, r):
    """Flou boîte séparable répété (approximation gaussienne), bords répétés."""
    if r < 1:
        return a
    k = int(r)
    for _ in range(2):
        for ax in (0, 1):
            c = np.cumsum(np.concatenate([np.repeat(np.take(a, [0], ax), k + 1, ax), a, np.repeat(np.take(a, [-1], ax), k, ax)], ax), ax)
            n = a.shape[ax]
            hi = np.take(c, range(2 * k + 1, 2 * k + 1 + n), ax)
            lo = np.take(c, range(0, n), ax)
            a = (hi - lo) / (2 * k + 1)
    return a


def font(size, bold=True):
    for name in (("arialbd.ttf", "DejaVuSans-Bold.ttf") if bold else ("arial.ttf", "DejaVuSans.ttf")):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


class Atlas:
    """Atlas : couleur, hauteur (-> normales), spéculaire, brillance, métal (rendus), ombre ambiante, alpha.
    Régions nommées ; chaque région devient une matière (MATS) de type bande (toute la largeur) ou rectangle."""

    def __init__(self, name, w, h, tex_prefix, rvmat, base=(0.5, 0.5, 0.5)):
        self.name, self.w, self.h = name, w, h
        self.tex = tex_prefix + name + "_co.paa"
        self.rvmat = tex_prefix + rvmat
        self.rgb = np.ones((h, w, 3)) * np.array(base)[None, None, :]
        self.height = np.full((h, w), 0.5)
        self.spec = np.full((h, w), 0.3)
        self.gloss = np.full((h, w), 0.4)
        self.metal = np.zeros((h, w))
        self.ao = np.ones((h, w))
        self.alpha = np.ones((h, w))
        self.regions = {}

    def region(self, key, x, y, w, h, kind="rect", tile=4.0, pad=2):
        """Déclare la région (pixels) et la matière associée. Renvoie les tranches numpy (ys, xs)."""
        self.regions[key] = (x, y, w, h, kind)
        if kind == "strip":
            rect = (0.0, (y + pad) / self.h, 1.0, (y + h - pad) / self.h)
        else:
            rect = ((x + pad) / self.w, (y + pad) / self.h, (x + w - pad) / self.w, (y + h - pad) / self.h)
        MATS[key] = Mat(key, self.tex, self.rvmat, kind, rect, tile, self.name)
        return (slice(y, y + h), slice(x, x + w))

    def sl(self, key):
        x, y, w, h, _ = self.regions[key]
        return (slice(y, y + h), slice(x, x + w))

    def fill(self, key, rgb=None, height=None, spec=None, gloss=None, metal=None, ao=None, alpha=None):
        s = self.sl(key)
        for arr, val in ((self.rgb, rgb), (self.height, height), (self.spec, spec), (self.gloss, gloss), (self.metal, metal), (self.ao, ao), (self.alpha, alpha)):
            if val is not None:
                arr[s] = val

    def draw(self, key):
        """Calque Pillow RGBA de la taille de la région (dessin de détails), à poser avec paste()."""
        x, y, w, h, _ = self.regions[key]
        img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        return img, ImageDraw.Draw(img)

    def paste(self, key, img, height=None, spec=None, gloss=None, metal=None):
        """Pose un calque RGBA sur la région ; height / spec / gloss / metal : valeur appliquée là où le calque est opaque."""
        s = self.sl(key)
        a = np.asarray(img, dtype=np.float64) / 255.0
        al = a[..., 3:4]
        self.rgb[s] = self.rgb[s] * (1 - al) + (a[..., :3] ** 2.2) * al
        for arr, val in ((self.height, height), (self.spec, spec), (self.gloss, gloss), (self.metal, metal)):
            if val is not None:
                arr[s] = arr[s] * (1 - al[..., 0]) + val * al[..., 0]

    def save(self, out_dir, suffixes=("co", "nohq", "smdi"), nstrength=3.0, name=None, has_alpha=False):
        """Écrit les PNG (couleur en sRGB). Renvoie le dict des chemins (co, nohq, smdi, as, rm)."""
        name = name or self.name
        os.makedirs(out_dir, exist_ok=True)
        paths = {}
        srgb = np.clip(self.rgb, 0, 1) ** (1 / 2.2)
        if "co" in suffixes or "ca" in suffixes:
            suf = "ca" if has_alpha else "co"
            p = paths["co"] = os.path.join(out_dir, "%s_%s.png" % (name, suf))
            if has_alpha:
                Image.fromarray((np.dstack([srgb, np.clip(self.alpha, 0, 1)]) * 255 + 0.5).astype(np.uint8), "RGBA").save(p)
            else:
                Image.fromarray((srgb * 255 + 0.5).astype(np.uint8), "RGB").save(p)
        if "nohq" in suffixes:
            hgt = self.height
            dx = (np.roll(hgt, -1, 1) - np.roll(hgt, 1, 1)) * nstrength
            dy = (np.roll(hgt, -1, 0) - np.roll(hgt, 1, 0)) * nstrength
            n = np.stack([-dx, dy, np.ones_like(dx)], -1)
            n /= np.linalg.norm(n, axis=-1, keepdims=True)
            p = paths["nohq"] = os.path.join(out_dir, "%s_nohq.png" % name)
            Image.fromarray(((n * 0.5 + 0.5) * 255 + 0.5).astype(np.uint8), "RGB").save(p)
        if "smdi" in suffixes:
            p = paths["smdi"] = os.path.join(out_dir, "%s_smdi.png" % name)
            sm = np.stack([np.ones_like(self.spec), np.clip(self.spec, 0, 1), np.clip(self.gloss, 0, 1)], -1)
            Image.fromarray((sm * 255 + 0.5).astype(np.uint8), "RGB").save(p)
        if "as" in suffixes:
            p = paths["as"] = os.path.join(out_dir, "%s_as.png" % name)
            ao = np.clip(self.ao, 0, 1)
            Image.fromarray((np.stack([np.ones_like(ao), ao, np.ones_like(ao)], -1) * 255 + 0.5).astype(np.uint8), "RGB").save(p)
        # Carte du moteur de rendu (Blender) : rugosité, métal, ombre ambiante.
        p = paths["rm"] = os.path.join(out_dir, "%s_rm.png" % name)
        rough = np.clip(1.0 - self.gloss * 0.9 - self.spec * 0.15, 0.08, 1)
        Image.fromarray((np.stack([rough, np.clip(self.metal, 0, 1), np.clip(self.ao, 0, 1)], -1) * 255 + 0.5).astype(np.uint8), "RGB").save(p)
        return paths


def to_paa(src_png, dst_paa):
    hemtt = shutil.which("hemtt")
    if not hemtt:
        raise RuntimeError("hemtt introuvable (hemtt utils paa convert)")
    if os.path.exists(dst_paa):
        os.remove(dst_paa)  # hemtt ne remplace pas un PAA existant
    subprocess.run([hemtt, "utils", "paa", "convert", src_png, dst_paa], check=True, capture_output=True)


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
class Stage4 {{ texture="{as_}"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage5 {{ texture="{smdi}"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
class Stage6 {{ texture="#(ai,64,64,1)fresnel({fresnel})"; uvSource="none"; }};
class Stage7 {{ texture="a3\\data_f\\env_land_co.paa"; useWorldEnvMap="true"; uvSource="tex"; class uvTransform {{ aside[]={{1,0,0}}; up[]={{0,1,0}}; dir[]={{0,0,0}}; pos[]={{0,0,0}}; }}; }};
"""


def write_rvmat_super(path, comment, nohq, smdi, spec=0.4, power=40, fresnel="1.5,0.5", as_=None):
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write(RVMAT_SUPER.format(comment=comment, spec=spec, power=power, fresnel=fresnel, nohq=nohq, smdi=smdi,
                                   as_=as_ or "#(argb,8,8,3)color(1,1,1,1,AS)"))


def write_rvmat_basic(path, comment, emissive=(0, 0, 0), spec=0.1, power=30, extra=""):
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write("// %s\nambient[]={1,1,1,1};\ndiffuse[]={1,1,1,1};\nforcedDiffuse[]={0,0,0,0};\nemmisive[]={%g,%g,%g,1};\n"
                "specular[]={%g,%g,%g,1};\nspecularPower=%g;\nPixelShaderID=\"Normal\";\nVertexShaderID=\"Basic\";\n%s"
                % (comment, emissive[0], emissive[1], emissive[2], spec, spec, spec, power, extra))
