"""
Mobilier et périphériques de salle d'opérations (TOC / JOC), modèles du mod (demande de TTRD : « les bureaux sont laids
et à l'envers », « souris, clavier, chaise et poste fixe sont super laids ») : consoles d'opérateur, table de briefing,
fauteuil d'opérateur, claviers, souris, unités centrales, accessoires de bureau. Mêmes primitives, format MLOD et
dossier que les écrans (model_toc_screens.py, importé tel quel) : Sources/addons/main/data/toc/.

Repère : X à droite, Y vers l'avant (direction de l'objet dans le jeu), Z vers le haut.
RÈGLE D'ORIENTATION (la même pour tout le poste, tests/Unit/AtakTocDeskAssetTest.php) :
  - un bureau, un poste PC, un clavier, une souris, une unité centrale : l'OPÉRATEUR est du côté de la direction de
    l'objet (+Y). Côté +Y : espace des genoux, façade de la baie PC, clavier, face des moniteurs. Côté -Y : voile de
    fond, passe-câbles, dos des moniteurs. Vu par l'opérateur (qui regarde vers -Y), sa gauche est en +X ;
  - un fauteuil : il REGARDE dans la direction de l'objet (assise et accoudoirs vers +Y, dossier en -Y).
  Dans une salle dont le mur d'images est dans la direction D : écrans, postes PC et bureaux à D + 180, fauteuils à D.

Bureaux (origine au sol, sous l'origine du poste PC posé dessus : plateau à 0,74 m, de y = -0,45 à y = +0,40) :
  toc_furn_desk_single    console 1 poste, 1,60 x 0,85 m
  toc_furn_desk_double    console 2 postes, 3,20 m (postes à x = -0,8 et +0,8)
  toc_furn_desk_triple    console 3 postes, 4,80 m (postes à x = -1,6, 0 et +1,6)
  toc_furn_desk_curved    console en arc 1 poste (corde 2,19 m) pour l'écran incurvé et le poste à trois écrans
  toc_furn_desk_corner    pièce d'angle 90° (origine au centre de l'arc : bouts sur l'axe -Y et sur l'axe +X)
  toc_furn_table_brief    table de briefing 2,40 x 1,10 m
  toc_furn_chair          fauteuil d'opérateur (dossier résille, lombaire, accoudoirs, piètement cinq branches)
Périphériques (origine sur le plateau, opérateur en +Y) :
  toc_furn_kbd / _kbd_tkl / _kbd_qwerty / _kbd_backlit   clavier extra-plat 44 x 13 cm (sans pavé : 36 cm), AZERTY ou QWERTY
  toc_furn_mouse / _mouse_pad     souris ergonomique (sans fil) ; avec son tapis de bureau 80 x 30 cm
  toc_furn_tower / _sff / _minipc / _sff_under    tour, unité compacte, mini-PC, unité compacte en berceau sous plateau
Accessoires : toc_furn_headset, _notepad, _mug, _handset, _cabletray.

Chaque modèle : LOD 0 / 2 / 6, ombre, géométrie (boîtes convexes, autocenter=0), vue, tir, points mémoire.
Textures : toc_top (stratifié gris clair), toc_mesh (résille), toc_kit (atlas : légendes des claviers, façades, pages).

    python model_toc_furniture.py     # modèles, textures (hemtt utils paa convert), rvmat, tools/toc_furniture_manifest.json
    (NO_TEX=1 : sans textures ; OBJ_DIR=dossier : OBJ et PNG pour les rendus Blender, tools/render_toc_furniture.py)
"""
import json
import math
import os
import shutil
import struct
import subprocess
import sys
import tempfile

import numpy as np
from PIL import Image, ImageDraw, ImageFont

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import model_toc_screens as T  # noqa: E402

DATA = T.DATA
HERE = T.HERE
MANIFEST = os.path.join(HERE, "toc_furniture_manifest.json")

# Matières en plus (clé -> texture, rvmat, m par tuile).
T.MATS.update({
    "top": ("toc_top_co.paa", "toc_top.rvmat", 0.6),
    "mesh": ("toc_mesh_co.paa", "toc_mesh.rvmat", 0.06),
    "kit": ("toc_kit_co.paa", "toc_kit.rvmat", 1.0),
    "kitglow": ("toc_kit_co.paa", "toc_kit_glow.rvmat", 1.0),
    "paper": ("#(argb,8,8,3)color(0.78,0.78,0.74,1,co)", "toc_plastic.rvmat", 1.0),
    "ceramic": ("#(argb,8,8,3)color(0.07,0.10,0.14,1,co)", "toc_steel.rvmat", 1.0),
    "coffee": ("#(argb,8,8,3)color(0.03,0.018,0.01,1,co)", "toc_steel.rvmat", 1.0),
})

TOP_Z = 0.74          # dessus du plateau (origine des postes PC)
TOP_T = 0.028         # épaisseur du plateau
Y_BACK, Y_FRONT = -0.45, 0.40
SEAT = 1.6            # largeur d'un poste sur une console droite
CHAIR_Y = 0.80        # fauteuil : distance de l'origine du poste vers l'opérateur
CURVE_C = (0.0, 1.25)  # console en arc : centre de l'arc (côté opérateur)
CURVE_RI, CURVE_RO, CURVE_A = 0.86, 1.70, math.radians(40)

# Atlas toc_kit (2048 x 1024) : zone -> (x, y, largeur, hauteur) en pixels.
ATLAS = (2048, 1024)
ZONES = {
    "kbd_azerty": (0, 0, 1056, 312), "kbd_qwerty": (0, 316, 1056, 312), "kbd_glow": (0, 632, 1056, 312),
    "nameplate": (0, 948, 320, 76), "mini_front": (330, 948, 360, 76),
    "tower_front": (1064, 0, 300, 660), "sff_front": (1372, 0, 150, 465), "mouse_top": (1530, 0, 128, 236),
    "handset": (1666, 0, 120, 300), "notepad": (1372, 470, 300, 424),
}


def zone_uv(zone, s, t):
    """UV de l'atlas pour le point (s, t) de la zone (0..1, t vers le bas)."""
    x, y, w, h = ZONES[zone]
    return ((x + 0.5 + s * (w - 1)) / ATLAS[0], (y + 0.5 + t * (h - 1)) / ATLAS[1])


# --------------------------------------------------------------------------------------------------------------------
# Primitives en plus
# --------------------------------------------------------------------------------------------------------------------
def rrect(x0, x1, y0, y1, r_front, r_back, n):
    """Contour (sens direct vu de dessus) d'un rectangle aux coins arrondis : r_front côté +Y, r_back côté -Y."""
    out = []
    for cx, cy, r, a0 in ((x1, y1, r_front, 0), (x0, y1, r_front, 90), (x0, y0, r_back, 180), (x1, y0, r_back, 270)):
        sx = -1 if cx == x0 else 1
        sy = -1 if cy == y0 else 1
        if r <= 0 or n < 1:
            out.append((cx, cy))
            continue
        ox, oy = cx - sx * r, cy - sy * r
        for i in range(n + 1):
            a = math.radians(a0 + 90.0 * i / n)
            out.append((ox + r * math.cos(a), oy + r * math.sin(a)))
    if len(out) % 2:
        out.append(out[-1])
    return out


def fan(m, loop, z, mat, up=True, uvfn=None):
    c = (sum(p[0] for p in loop) / len(loop), sum(p[1] for p in loop) / len(loop))
    n = len(loop)
    for i in range(0, n, 2):
        pts = [(c[0], c[1], z)] + [(loop[(i + k) % n][0], loop[(i + k) % n][1], z) for k in range(3)]
        m.poly(pts, mat, uvs=[uvfn(p) for p in pts] if uvfn else None, hint=(0, 0, 1 if up else -1))


def prism(m, loop, z0, z1, mat_top, mat_side, bottom=None, uvfn=None):
    """Prisme droit sur un contour convexe : dessus, chant, dessous éventuel."""
    fan(m, loop, z1, mat_top, True, uvfn)
    if bottom:
        fan(m, loop, z0, bottom, False)
    c = (sum(p[0] for p in loop) / len(loop), sum(p[1] for p in loop) / len(loop))
    n = len(loop)
    for i in range(n):
        a, b = loop[i], loop[(i + 1) % n]
        if abs(a[0] - b[0]) + abs(a[1] - b[1]) < 1e-9:
            continue
        hint = ((a[0] + b[0]) / 2 - c[0], (a[1] + b[1]) / 2 - c[1], 0)
        m.poly([(a[0], a[1], z0), (b[0], b[1], z0), (b[0], b[1], z1), (a[0], a[1], z1)], mat_side, hint=hint)


def arc_pt(c, r, a):
    """Point de l'arc de centre c : angle a depuis l'axe -Y (vers +X)."""
    return (c[0] + r * math.sin(a), c[1] - r * math.cos(a))


def ring_sector(m, c, ri, ro, a0, a1, n, z0, z1, mat_top, mat_side, bottom=None, ends=True):
    """Secteur d'anneau (plateau en arc, voile courbe) : dessus, chants, dessous éventuel."""
    for i in range(n):
        b0, b1 = a0 + (a1 - a0) * i / n, a0 + (a1 - a0) * (i + 1) / n
        pi0, pi1, po0, po1 = arc_pt(c, ri, b0), arc_pt(c, ri, b1), arc_pt(c, ro, b0), arc_pt(c, ro, b1)
        m.poly([pi0 + (z1,), po0 + (z1,), po1 + (z1,), pi1 + (z1,)], mat_top, hint=(0, 0, 1))
        if bottom:
            m.poly([pi0 + (z0,), po0 + (z0,), po1 + (z0,), pi1 + (z0,)], bottom, hint=(0, 0, -1))
        if z1 - z0 > 1e-6:
            am = (b0 + b1) / 2
            out = (math.sin(am), -math.cos(am), 0)
            m.poly([po0 + (z0,), po1 + (z0,), po1 + (z1,), po0 + (z1,)], mat_side, hint=out)
            m.poly([pi0 + (z0,), pi1 + (z0,), pi1 + (z1,), pi0 + (z1,)], mat_side, hint=(-out[0], -out[1], 0))
    if ends and z1 - z0 > 1e-6:
        for a, s in ((a0, -1), (a1, 1)):
            pi, po = arc_pt(c, ri, a), arc_pt(c, ro, a)
            m.poly([pi + (z0,), po + (z0,), po + (z1,), pi + (z1,)], mat_side, hint=(s * math.cos(a), s * math.sin(a), 0))


def beam(m, p0, p1, w, h, mat, sels=()):
    """Poutre droite de p0 à p1 : largeur w (horizontale), hauteur h. Rend ses 8 coins (boîte fermée)."""
    d = T.vnorm(T.vsub(p1, p0))
    s = T.vnorm(T.vcross(d, (0, 0, 1))) if abs(d[2]) < 0.99 else (1.0, 0.0, 0.0)
    u = T.vcross(s, d)
    P = []
    for e in (p0, p1):
        for ss in (-1, 1):
            for uu in (-1, 1):
                P.append(tuple(e[k] + ss * s[k] * w / 2 + uu * u[k] * h / 2 for k in range(3)))
    c = tuple(sum(p[k] for p in P) / 8 for k in range(3))
    for q in ((0, 1, 3, 2), (4, 6, 7, 5), (0, 4, 5, 1), (2, 3, 7, 6), (0, 2, 6, 4), (1, 5, 7, 3)):
        pts = [P[i] for i in q]
        fc = tuple(sum(p[k] for p in pts) / 4 for k in range(3))
        m.poly(pts, mat, sels, hint=T.vsub(fc, c))
    return ("pts", P)


def loft(m, rings, mat, key, closed=True, cap0=None, cap1=None, uvfn=None, sels=()):
    """Surface lissée entre des anneaux de même nombre de points (normales par sommet, sommets partagés)."""
    R, N = len(rings), len(rings[0])
    A = np.array(rings, float)
    cen = A.mean(1)
    nrm = np.zeros_like(A)
    for i in range(R):
        for j in range(N):
            jn, jp = (j + 1) % N if closed else min(j + 1, N - 1), (j - 1) % N if closed else max(j - 1, 0)
            t1 = A[i, jn] - A[i, jp]
            t2 = A[min(i + 1, R - 1), j] - A[max(i - 1, 0), j]
            v = np.cross(t1, t2)
            ln = np.linalg.norm(v)
            v = v / ln if ln > 1e-12 else A[i, j] - cen[i]
            if np.dot(v, A[i, j] - cen[i]) < 0:
                v = -v
            nrm[i, j] = v
    for i in range(R - 1):
        for j in range(N if closed else N - 1):
            j1 = (j + 1) % N
            pts = [tuple(A[i, j]), tuple(A[i, j1]), tuple(A[i + 1, j1]), tuple(A[i + 1, j])]
            vn = [tuple(nrm[i, j]), tuple(nrm[i, j1]), tuple(nrm[i + 1, j1]), tuple(nrm[i + 1, j])]
            hint = tuple(nrm[i, j] + nrm[i, j1] + nrm[i + 1, j1] + nrm[i + 1, j])
            m.poly(pts, mat, sels, uvs=[uvfn(p) for p in pts] if uvfn else None, hint=hint, vn=vn, share=key)
    for ring, cmat, sgn in ((A[0], cap0, -1), (A[-1], cap1, 1)):
        if not cmat:
            continue
        c = ring.mean(0)
        hint = tuple(sgn * (A[-1].mean(0) - A[0].mean(0)))
        for j in range(0, N, 2):
            pts = [tuple(c)] + [tuple(ring[(j + k) % N]) for k in range(3)]
            m.poly(pts, cmat, sels, uvs=[uvfn(p) for p in pts] if uvfn else None, hint=hint)


def kit_quad(m, pts, zone, st, hint, mat="kit"):
    """Quadrilatère texturé par une zone de l'atlas : st = (s, t) de chaque coin."""
    m.poly(pts, mat, uvs=[zone_uv(zone, s, t) for s, t in st], hint=hint)


def move(sub, dx=0.0, dy=0.0, dz=0.0, rot=0.0):
    """Maillage tourné autour de Z (degrés) puis déplacé."""
    a = math.radians(rot)
    c, s = math.cos(a), math.sin(a)

    def fp(p):
        return (p[0] * c - p[1] * s + dx, p[0] * s + p[1] * c + dy, p[2] + dz)

    def fv(v):
        return (v[0] * c - v[1] * s, v[0] * s + v[1] * c, v[2])
    sub.pts = [fp(p) for p in sub.pts]
    sub.faces = [(idx, uvs, fv(n), mat, sels) for idx, uvs, n, mat, sels in sub.faces]
    sub.vn = {k: [fv(v) for v in vs] for k, vs in sub.vn.items()}
    return sub


def tilt_x(sub, a, pivot):
    sub.pts = [T.rot_x(p, a, pivot) for p in sub.pts]
    sub.faces = [(idx, uvs, T.rot_x(n, a, (0, 0, 0)), mat, sels) for idx, uvs, n, mat, sels in sub.faces]
    sub.vn = {k: [T.rot_x(v, a, (0, 0, 0)) for v in vs] for k, vs in sub.vn.items()}
    return sub


# --------------------------------------------------------------------------------------------------------------------
# Claviers
# --------------------------------------------------------------------------------------------------------------------
U = 0.01905
KB_MX, KB_MY = 0.005, 0.006         # marges du châssis
KB_GAP = 0.006                      # entre le bloc principal, la navigation et le pavé
KB_D = 6 * U + 0.004 + 2 * KB_MY    # 0,1303 m
KB_W_FULL = 22 * U + 2 * KB_GAP + 2 * KB_MX   # 0,4411 m
KB_W_TKL = 18 * U + KB_GAP + 2 * KB_MX        # 0,3589 m

ROWS = {
    "azerty": [
        ["²", "&", "é", "\"", "'", "(", "-", "è", "_", "ç", "à", ")", "=", ("⌫", 2)],
        [("Tab", 1.5), "A", "Z", "E", "R", "T", "Y", "U", "I", "O", "P", "^", "$", ("↵", 1.5)],
        [("Verr", 1.75), "Q", "S", "D", "F", "G", "H", "J", "K", "L", "M", "ù", ("Entrée", 2.25)],
        [("Maj", 1.25), "<", "W", "X", "C", "V", "B", "N", ",", ";", ":", "!", ("Maj", 2.75)],
        [("Ctrl", 1.25), ("Win", 1.25), ("Alt", 1.25), ("", 6.25), ("AltGr", 1.25), ("Fn", 1.25), ("Menu", 1.25), ("Ctrl", 1.25)],
    ],
    "qwerty": [
        ["`", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "-", "=", ("⌫", 2)],
        [("Tab", 1.5), "Q", "W", "E", "R", "T", "Y", "U", "I", "O", "P", "[", "]", ("\\", 1.5)],
        [("Caps", 1.75), "A", "S", "D", "F", "G", "H", "J", "K", "L", ";", "'", ("Enter", 2.25)],
        [("Shift", 2.25), "Z", "X", "C", "V", "B", "N", "M", ",", ".", "/", ("Shift", 2.75)],
        [("Ctrl", 1.25), ("Win", 1.25), ("Alt", 1.25), ("", 6.25), ("Alt", 1.25), ("Fn", 1.25), ("Menu", 1.25), ("Ctrl", 1.25)],
    ],
}
NAV = [["Impr", "Arrêt", "Pause"], ["Ins", "⇱", "⇞"], ["Suppr", "Fin", "⇟"], [None, None, None], [None, "↑", None], ["←", "↓", "→"]]
PAD = [[None, None, None, None], ["Num", "/", "*", "-"], ["7", "8", "9", "+"], ["4", "5", "6", "+"], ["1", "2", "3", "↵"], [("0", 2), ".", "↵"]]


def kbd_keys(layout, numpad):
    """Touches : (x depuis le bord gauche de l'opérateur, y depuis le fond, largeur, profondeur, légende), en mètres."""
    keys = []

    def row_y(r):
        return KB_MY + r * U + (0.004 if r > 0 else 0)
    # Rangée des fonctions : Échap, trois groupes de quatre.
    keys.append((KB_MX, row_y(0), U, U, "Esc"))
    for g in range(3):
        for k in range(4):
            keys.append((KB_MX + (2 + g * 4.5 + k) * U, row_y(0), U, U, "F%d" % (g * 4 + k + 1)))
    for r, row in enumerate(ROWS[layout]):
        xa = KB_MX
        for kd in row:
            lab, wk = kd if isinstance(kd, tuple) else (kd, 1)
            keys.append((xa, row_y(r + 1), wk * U, U, lab))
            xa += wk * U
    blocks = [(KB_MX + 15 * U + KB_GAP, NAV)] + ([(KB_MX + 18 * U + 2 * KB_GAP, PAD)] if numpad else [])
    for x0, block in blocks:
        for r, row in enumerate(block):
            xa = x0
            for kd in row:
                lab, wk = kd if isinstance(kd, tuple) else (kd, 1)
                if lab is not None:
                    keys.append((xa, row_y(r), wk * U, U, lab))
                xa += wk * U
    return keys


def keyboard(m, cx, cy, detail, numpad=True, layout="azerty", backlit=False, cable=False, base_z=0.0):
    """Clavier extra-plat (châssis alu de 44 x 13 cm, touches basses légendées par l'atlas), incliné de 4°,
    rangée des fonctions au fond (-Y), gauche de l'opérateur en +X. Rend sa boîte."""
    W = KB_W_FULL if numpad else KB_W_TKL
    zone = "kbd_glow" if backlit else "kbd_" + layout
    mat = "kitglow" if backlit else "kit"
    sub = T.Mesh()
    h0 = 0.009
    T.box(sub, -W / 2, W / 2, -KB_D / 2, KB_D / 2, 0.0, h0, "frame", b=0.002 if detail == 0 else 0)

    def st(xa, ya):
        # xa depuis la gauche de l'opérateur (+X), ya depuis le fond : zone dessinée sur le clavier complet.
        return (xa / KB_W_FULL, ya / KB_D)

    def P(xa, ya, z):
        return (W / 2 - xa, -KB_D / 2 + ya, z)
    if detail == 0:
        g, kh = 0.0011, 0.0042
        for xa, ya, w, d, _ in kbd_keys(layout, numpad):
            a0, a1, b0, b1 = xa + g, xa + w - g, ya + g, ya + d - g
            i = 0.0012          # dessus de touche plus étroit que sa base
            top = [P(a0 + i, b0 + i, h0 + kh), P(a1 - i, b0 + i, h0 + kh), P(a1 - i, b1 - i, h0 + kh), P(a0 + i, b1 - i, h0 + kh)]
            kit_quad(sub, top, zone, [st(a0 + i, b0 + i), st(a1 - i, b0 + i), st(a1 - i, b1 - i), st(a0 + i, b1 - i)], (0, 0, 1), mat)
            base = [P(a0, b0, h0), P(a1, b0, h0), P(a1, b1, h0), P(a0, b1, h0)]
            for k in range(4):
                k1 = (k + 1) % 4
                mid = tuple((base[k][q] + base[k1][q]) / 2 - (base[0][q] + base[2][q]) / 2 for q in range(3))
                sub.poly([base[k], base[k1], top[k1], top[k]], "rubber", hint=mid)
    else:
        w_ = W - 2 * KB_MX + 0.002
        top = [P(KB_MX - 0.001, KB_MY - 0.001, h0 + 0.004), P(KB_MX - 0.001 + w_, KB_MY - 0.001, h0 + 0.004),
               P(KB_MX - 0.001 + w_, KB_D - KB_MY + 0.001, h0 + 0.004), P(KB_MX - 0.001, KB_D - KB_MY + 0.001, h0 + 0.004)]
        stv = [st(KB_MX - 0.001, KB_MY - 0.001), st(KB_MX - 0.001 + w_, KB_MY - 0.001), st(KB_MX - 0.001 + w_, KB_D - KB_MY + 0.001),
               st(KB_MX - 0.001, KB_D - KB_MY + 0.001)]
        kit_quad(sub, top, zone, stv, (0, 0, 1), mat)
        if detail == 1:
            T.box(sub, -W / 2 + KB_MX - 0.001, W / 2 - KB_MX + 0.001, -KB_D / 2 + KB_MY - 0.001, KB_D / 2 - KB_MY + 0.001, h0, h0 + 0.004,
                  "rubber", skip=("-z", "+z"))
    if detail == 0:
        for sx in (-1, 1):   # patins arrière repliables
            T.box(sub, sx * (W / 2 - 0.05) - 0.02, sx * (W / 2 - 0.05) + 0.02, -KB_D / 2 + 0.004, -KB_D / 2 + 0.016, -0.006, 0.0, "rubber")
    tilt_x(sub, math.radians(-4), (0.0, KB_D / 2, 0.0))
    move(sub, cx, cy, base_z + 0.0015)
    m.merge(sub)
    if cable and detail == 0:
        cable_run(m, [(cx + 0.06, cy - KB_D / 2 + 0.002, base_z + 0.014), (cx + 0.06, cy - KB_D / 2 - 0.03, base_z + 0.004),
                      (cx + 0.10, cy - KB_D / 2 - 0.16, base_z + 0.004)])
    return (cx - W / 2, cx + W / 2, cy - KB_D / 2, cy + KB_D / 2, base_z, base_z + 0.03)


def cable_run(m, pts, r=0.0028, n=5):
    for a, b in zip(pts, pts[1:]):
        T.cylinder(m, a, b, r, "rubber", n=n, caps=False)


# --------------------------------------------------------------------------------------------------------------------
# Souris, tapis
# --------------------------------------------------------------------------------------------------------------------
MOUSE_L, MOUSE_W = 0.118, 0.064


def mouse(m, cx, cy, detail, cable=False, base_z=0.0):
    """Souris ergonomique pour droitier : coque sculptée lissée (bosse sous la paume, pente vers la droite), molette,
    coutures des boutons par l'atlas. Nez vers le fond (-Y). Rend sa boîte."""
    prof = [(0.0, 0.040, 0.010), (0.06, 0.052, 0.024), (0.2, 0.062, 0.036), (0.42, 0.064, 0.040), (0.62, 0.062, 0.035),
            (0.8, 0.058, 0.027), (0.93, 0.050, 0.019), (1.0, 0.036, 0.011)]     # t depuis la paume (+Y), largeur, hauteur
    if detail >= 2:
        T.box(m, cx - 0.03, cx + 0.03, cy - MOUSE_L / 2, cy + MOUSE_L / 2, base_z, base_z + 0.034, "plastic", skip=("-z",))
        return (cx - 0.032, cx + 0.032, cy - MOUSE_L / 2, cy + MOUSE_L / 2, base_z, base_z + 0.04)
    if detail == 1:
        prof = prof[::2] + [prof[-1]]
    K = 10 if detail == 0 else 6
    rings = []
    for t, w, h in prof:
        y = cy + MOUSE_L / 2 - t * MOUSE_L
        ring = []
        for k in range(K + 1):
            a = math.pi * k / K
            # Sommet déporté vers la gauche de l'opérateur (+X) : la main repose en pente.
            x = -math.cos(a) * w / 2 + 0.004 * math.sin(a) ** 2
            z = 0.003 + (h - 0.003) * math.sin(a) ** 0.75
            ring.append((cx + x, y, base_z + z))
        rings.append(ring)

    def uv(p):
        return zone_uv("mouse_top", (cx + MOUSE_W / 2 - p[0]) / MOUSE_W, (p[1] - (cy - MOUSE_L / 2)) / MOUSE_L)
    loft(m, rings, "kit", ("mouse", cx, cy), closed=True, cap0="plastic", cap1="plastic", uvfn=uv)
    if detail == 0:
        yw = cy - MOUSE_L / 2 + 0.030
        T.cylinder(m, (cx - 0.0035, yw, base_z + 0.0275), (cx + 0.0035, yw, base_z + 0.0275), 0.0095, "rubber", n=12)
        for sx in (-1, 1):   # patins de glisse
            T.box(m, cx + sx * 0.018 - 0.008, cx + sx * 0.018 + 0.008, cy - 0.045, cy - 0.035, base_z, base_z + 0.003, "paper", skip=("+z",))
        if cable:
            cable_run(m, [(cx, cy - MOUSE_L / 2 + 0.002, base_z + 0.008), (cx, cy - MOUSE_L / 2 - 0.05, base_z + 0.004),
                          (cx + 0.05, cy - MOUSE_L / 2 - 0.2, base_z + 0.004)])
    return (cx - 0.034, cx + 0.034, cy - MOUSE_L / 2, cy + MOUSE_L / 2, base_z, base_z + 0.042)


def deskpad(m, x0, x1, y0, y1, detail, base_z=0.0):
    """Tapis de bureau : feutre noir, bord surpiqué."""
    loop = rrect(x0, x1, y0, y1, 0.012, 0.012, 3 if detail == 0 else 0)
    prism(m, loop, base_z, base_z + 0.003, "plastic", "rubber")
    if detail == 0:
        prism(m, rrect(x0 + 0.004, x1 - 0.004, y0 + 0.004, y1 - 0.004, 0.009, 0.009, 3), base_z + 0.003, base_z + 0.0034, "mesh", "rubber")
    return (x0, x1, y0, y1, base_z, base_z + 0.02)


# --------------------------------------------------------------------------------------------------------------------
# Unités centrales
# --------------------------------------------------------------------------------------------------------------------
def front_panel(m, zone, x0, x1, y, z0, z1):
    """Façade d'une unité centrale (vers l'opérateur, +Y) : image de l'atlas, gauche de l'opérateur en +X."""
    kit_quad(m, [(x0, y, z0), (x1, y, z0), (x1, y, z1), (x0, y, z1)], zone, [(1, 1), (0, 1), (0, 0), (1, 0)], (0, 1, 0))


def vent_quad(m, pts, hint, wu, hv):
    m.poly(pts, "vent", uvs=[(0, hv / 0.08), (wu / 0.08, hv / 0.08), (wu / 0.08, 0), (0, 0)], hint=hint)


def sff(m, cx, y0, detail, base_z=0.0):
    """Unité centrale compacte (10 x 29 x 31 cm) : coque alu brossé, façade noire (bouton, voyant, USB, audio),
    flanc ajouré, pieds. Façade en y0 + 0,29 (vers l'opérateur). Rend sa boîte."""
    w, d, h = 0.10, 0.29, 0.31
    x0, x1, y1, z0 = cx - w / 2, cx + w / 2, y0 + d, base_z + 0.008
    T.box(m, x0, x1, y0, y1, z0, z0 + h, "frame", b=0.005 if detail == 0 else (0.003 if detail == 1 else 0))
    if detail < 2:
        front_panel(m, "sff_front", x0 + 0.004, x1 - 0.004, y1 + 0.0006, z0 + 0.004, z0 + h - 0.004)
        for yy in (y0 + 0.04, y1 - 0.04):
            T.box(m, x0 + 0.006, x1 - 0.006, yy - 0.015, yy + 0.015, base_z, z0, "rubber")
    if detail == 0:
        for sx, xf in ((1, x1 + 0.0006), (-1, x0 - 0.0006)):
            vent_quad(m, [(xf, y0 + 0.04, z0 + 0.05), (xf, y1 - 0.05, z0 + 0.05), (xf, y1 - 0.05, z0 + h - 0.05), (xf, y0 + 0.04, z0 + h - 0.05)],
                      (sx, 0, 0), d - 0.09, h - 0.10)
        yb = y0 - 0.0006
        vent_quad(m, [(x1 - 0.012, yb, z0 + 0.03), (x0 + 0.012, yb, z0 + 0.03), (x0 + 0.012, yb, z0 + 0.11), (x1 - 0.012, yb, z0 + 0.11)],
                  (0, -1, 0), w - 0.024, 0.08)
        T.box(m, x0 + 0.015, x1 - 0.015, y0 - 0.002, y0, z0 + 0.14, z0 + h - 0.03, "steel")
        zb = z0 + h * (1 - 42 / 465)
        T.box(m, cx - 0.004, cx + 0.004, y1, y1 + 0.0012, zb - 0.0015, zb + 0.0015, "led")
    return (x0, x1, y0, y1, base_z, z0 + h)


def tower(m, cx, y0, detail, base_z=0.0):
    """Station de travail moyenne tour (21 x 45 x 46 cm) : coque noire, façade ajourée, bandeau de prises en haut,
    dessus ajouré, pieds. Façade en y0 + 0,45. Rend sa boîte."""
    w, d, h = 0.21, 0.45, 0.46
    x0, x1, y1, z0 = cx - w / 2, cx + w / 2, y0 + d, base_z + 0.014
    T.box(m, x0, x1, y0, y1, z0, z0 + h, "steel", b=0.006 if detail == 0 else (0.004 if detail == 1 else 0))
    if detail < 2:
        front_panel(m, "tower_front", x0 + 0.006, x1 - 0.006, y1 + 0.0006, z0 + 0.006, z0 + h - 0.006)
        for sx in (-1, 1):
            for yy in (y0 + 0.05, y1 - 0.05):
                T.box(m, cx + sx * 0.07 - 0.025, cx + sx * 0.07 + 0.025, yy - 0.025, yy + 0.025, base_z, z0, "rubber")
    if detail == 0:
        zt = z0 + h + 0.0006
        vent_quad(m, [(x0 + 0.03, y0 + 0.06, zt), (x1 - 0.03, y0 + 0.06, zt), (x1 - 0.03, y1 - 0.12, zt), (x0 + 0.03, y1 - 0.12, zt)],
                  (0, 0, 1), w - 0.06, d - 0.18)
        xf = x1 + 0.0006   # flanc gauche de l'opérateur : panneau alu brossé rapporté
        T.box(m, x1, x1 + 0.003, y0 + 0.02, y1 - 0.02, z0 + 0.02, z0 + h - 0.02, "frame", skip=("-x",))
        yb = y0 - 0.0006
        vent_quad(m, [(cx + 0.085, yb, z0 + 0.02), (cx - 0.085, yb, z0 + 0.02), (cx - 0.085, yb, z0 + 0.11), (cx + 0.085, yb, z0 + 0.11)],
                  (0, -1, 0), 0.17, 0.09)
        T.box(m, cx - 0.09, cx - 0.045, y0 - 0.002, y0, z0 + 0.15, z0 + h - 0.03, "frame")
        zb = z0 + h * (1 - 40 / 660)
        T.box(m, cx + 0.045, cx + 0.055, y1, y1 + 0.0012, zb - 0.0015, zb + 0.0015, "led")
        del xf
    return (x0, x1, y0, y1, base_z, z0 + h)


def mini_pc(m, cx, cy, detail, base_z=0.0):
    """Mini-PC (18 x 18 x 4,5 cm) : boîtier alu, façade à prises, dessus ajouré. Rend sa boîte."""
    w, h = 0.18, 0.045
    x0, x1, y0, y1, z0 = cx - w / 2, cx + w / 2, cy - w / 2, cy + w / 2, base_z + 0.004
    T.box(m, x0, x1, y0, y1, z0, z0 + h, "frame", b=0.005 if detail == 0 else 0)
    if detail < 2:
        front_panel(m, "mini_front", x0 + 0.004, x1 - 0.004, y1 + 0.0006, z0 + 0.004, z0 + h - 0.004)
    if detail == 0:
        for sx in (-1, 1):
            for sy in (-1, 1):
                T.box(m, cx + sx * 0.065 - 0.012, cx + sx * 0.065 + 0.012, cy + sy * 0.065 - 0.012, cy + sy * 0.065 + 0.012, base_z, z0, "rubber")
        zt = z0 + h + 0.0006
        tw = w - 0.06
        vent_quad(m, [(cx - tw / 2, cy - tw / 2, zt), (cx + tw / 2, cy - tw / 2, zt), (cx + tw / 2, cy + tw / 2, zt), (cx - tw / 2, cy + tw / 2, zt)],
                  (0, 0, 1), tw, tw)
        T.box(m, x1 - 0.028, x1 - 0.02, y1, y1 + 0.0012, z0 + 0.03, z0 + 0.033, "led")
    return (x0, x1, y0, y1, base_z, z0 + h)


def sff_under(m, detail):
    """Unité compacte couchée dans un berceau acier vissé sous le plateau (origine : dessous du plateau)."""
    sub = T.Mesh()
    sff(sub, 0.0, -0.145, detail)
    # Couchée sur le flanc : rotation de 90° autour de Y (le haut de l'unité part vers -X), puis descendue.
    def fp(p):
        return (-(p[2] - 0.163), p[1], p[0] - 0.075)

    def fv(v):
        return (-v[2], v[1], v[0])
    sub.pts = [fp(p) for p in sub.pts]
    sub.faces = [(idx, uvs, fv(n), mat, sels) for idx, uvs, n, mat, sels in sub.faces]
    m.merge(sub)
    boxes = [(-0.16, 0.16, -0.145, 0.145, -0.13, -0.02)]
    for y in (-0.10, 0.10):
        T.box(m, -0.185, 0.185, y - 0.015, y + 0.015, -0.142, -0.137, "steel")
        for sx in (-1, 1):
            T.box(m, sx * 0.185 - 0.0025, sx * 0.185 + 0.0025, y - 0.015, y + 0.015, -0.142, 0.0, "steel")
            if detail < 2:
                T.box(m, sx * 0.205 - 0.022, sx * 0.205 + 0.022, y - 0.015, y + 0.015, -0.004, 0.0, "steel")
    boxes.append((-0.19, 0.19, -0.115, 0.115, -0.142, 0.0))
    return boxes


# --------------------------------------------------------------------------------------------------------------------
# Bureaux
# --------------------------------------------------------------------------------------------------------------------
def nameplate(m, x, y, detail, rot=0.0):
    """Porte-nom du poste : plaque inclinée sur son socle, lisible côté opérateur."""
    sub = T.Mesh()
    T.box(sub, -0.085, 0.085, -0.016, 0.016, 0.0, 0.006, "frame", b=0.002 if detail == 0 else 0)
    pl = T.Mesh()
    T.box(pl, -0.08, 0.08, -0.002, 0.002, 0.0, 0.05, "plastic")
    kit_quad(pl, [(-0.078, 0.0024, 0.003), (0.078, 0.0024, 0.003), (0.078, 0.0024, 0.047), (-0.078, 0.0024, 0.047)], "nameplate",
             [(1, 1), (0, 1), (0, 0), (1, 0)], (0, 1, 0))
    tilt_x(pl, math.radians(-22), (0.0, 0.0, 0.0))
    move(pl, 0.0, -0.004, 0.005)
    sub.merge(pl)
    m.merge(move(sub, x, y, TOP_Z, rot))


def cpu_bay(m, x0, x1, detail):
    """Baie PC suspendue sous le plateau : caisson acier ajouré, porte vers l'opérateur (voyant, serrure)."""
    y0, y1, z0, z1 = -0.36, 0.16, 0.10, 0.66
    T.box(m, x0, x1, y0, y1, z0, z1, "steel", b=0.004 if detail == 0 else 0)
    if detail >= 2:
        return
    w = x1 - x0
    vent_quad(m, [(x0 + 0.02, y1 + 0.0006, z0 + 0.03), (x1 - 0.02, y1 + 0.0006, z0 + 0.03), (x1 - 0.02, y1 + 0.0006, z1 - 0.12),
                  (x0 + 0.02, y1 + 0.0006, z1 - 0.12)], (0, 1, 0), w - 0.04, z1 - z0 - 0.15)
    for xs in (x0 + 0.03, x1 - 0.05):
        T.box(m, xs, xs + 0.02, -0.30, 0.10, z1, TOP_Z - TOP_T, "steel")
    if detail == 0:
        T.box(m, x0 + 0.03, x0 + 0.04, y1, y1 + 0.0012, z1 - 0.062, z1 - 0.058, "led")
        T.cylinder(m, (x1 - 0.04, y1, z1 - 0.06), (x1 - 0.04, y1 + 0.003, z1 - 0.06), 0.008, "frame", n=10)
        T.box(m, x0 + 0.02, x1 - 0.02, y1, y1 + 0.004, z1 - 0.105, z1 - 0.095, "frame")     # poignée


def seat_fittings(m, sx, detail):
    """Équipement d'un poste sur le plateau : passe-câbles à brosse, platine du bras d'écran, porte-nom."""
    if detail >= 2:
        return
    z = TOP_Z
    T.box(m, sx - 0.31, sx + 0.31, -0.425, -0.375, z - 0.004, z + 0.0015, "frame", skip=("-z",))
    m.poly([(sx - 0.30, -0.415, z + 0.002), (sx + 0.30, -0.415, z + 0.002), (sx + 0.30, -0.385, z + 0.002), (sx - 0.30, -0.385, z + 0.002)],
           "rubber", hint=(0, 0, 1))
    if detail == 0:
        T.box(m, sx - 0.07, sx + 0.07, -0.31, -0.19, z, z + 0.003, "steel", skip=("-z",))
        for dx in (-0.05, 0.05):
            for dy in (-0.29, -0.21):
                T.cylinder(m, (sx + dx, dy, z + 0.003), (sx + dx, dy, z + 0.005), 0.005, "frame", n=6)
    nameplate(m, sx - 0.63, -0.33, detail)


def leveler(m, x, y):
    T.cylinder(m, (x, y, 0.0), (x, y, 0.012), 0.018, "rubber", n=8)


def desk_straight(detail, seats):
    W = seats * SEAT
    m = T.Mesh()
    n = {0: 6, 1: 3, 2: 0}[detail]
    prism(m, rrect(-W / 2, W / 2, Y_BACK, Y_FRONT, 0.07, 0.02, n), TOP_Z - TOP_T, TOP_Z, "top", "plastic", bottom="steel")
    bv = 0.004 if detail == 0 else 0
    # Joues pleines aux deux bouts, patins en traîneau.
    for sx in (-1, 1):
        x = sx * (W / 2 - 0.035)
        T.box(m, x - 0.015, x + 0.015, -0.42, 0.30, 0.03, TOP_Z - TOP_T, "steel", b=bv)
        T.box(m, x - 0.03, x + 0.03, -0.44, 0.36, 0.012, 0.035, "frame", b=bv)
        if detail == 0:
            leveler(m, x, -0.40)
            leveler(m, x, 0.32)
    # Pieds intermédiaires en T entre deux postes (reculés : les genoux passent).
    for k in range(1, seats):
        x = -W / 2 + k * SEAT
        T.box(m, x - 0.015, x + 0.015, -0.42, -0.06, 0.03, TOP_Z - TOP_T, "steel", b=bv)
        T.box(m, x - 0.03, x + 0.03, -0.44, 0.30, 0.012, 0.035, "frame", b=bv)
        if detail == 0:
            leveler(m, x, -0.40)
            leveler(m, x, 0.26)
    # Voile de fond, traverse et goulotte à câbles sous le plateau.
    T.box(m, -W / 2 + 0.05, W / 2 - 0.05, -0.405, -0.39, 0.26, 0.665, "steel")
    if detail < 2:
        T.box(m, -W / 2 + 0.05, W / 2 - 0.05, -0.39, -0.33, 0.665, TOP_Z - TOP_T, "frame")
        T.box(m, -W / 2 + 0.06, W / 2 - 0.06, -0.385, -0.23, 0.585, 0.591, "frame")
        T.box(m, -W / 2 + 0.06, W / 2 - 0.06, -0.236, -0.23, 0.591, 0.64, "frame")
    if detail == 0:
        vent_quad(m, [(W / 2 - 0.08, -0.4056, 0.50), (-W / 2 + 0.08, -0.4056, 0.50), (-W / 2 + 0.08, -0.4056, 0.64), (W / 2 - 0.08, -0.4056, 0.64)],
                  (0, -1, 0), W - 0.16, 0.14)
    for k in range(seats):
        sx = -W / 2 + SEAT / 2 + k * SEAT
        cpu_bay(m, sx + 0.52, sx + 0.74, detail)
        seat_fittings(m, sx, detail)
    return m


def desk_straight_boxes(seats):
    W = seats * SEAT
    boxes = [(-W / 2, W / 2, Y_BACK, Y_FRONT, TOP_Z - TOP_T, TOP_Z), (-W / 2 + 0.05, W / 2 - 0.05, -0.405, -0.39, 0.26, 0.712)]
    for sx in (-1, 1):
        x = sx * (W / 2 - 0.035)
        boxes.append((x - 0.03, x + 0.03, -0.44, 0.36, 0.0, TOP_Z - TOP_T))
    for k in range(1, seats):
        x = -W / 2 + k * SEAT
        boxes.append((x - 0.03, x + 0.03, -0.44, 0.30, 0.0, TOP_Z - TOP_T))
    for k in range(seats):
        sx = -W / 2 + SEAT / 2 + k * SEAT
        boxes.append((sx + 0.52, sx + 0.74, -0.36, 0.16, 0.10, TOP_Z - TOP_T))
    return boxes


def desk_memory(seats):
    W = seats * SEAT
    mem = {}
    for k in range(seats):
        sx = -W / 2 + SEAT / 2 + k * SEAT
        # Numérotés de la gauche de l'opérateur (+X) vers sa droite.
        i = seats - k
        mem["pc_%d" % i] = [(sx, 0.0, TOP_Z)]
        mem["mon_%d" % i] = [(sx, -0.25, TOP_Z)]
        mem["chair_%d" % i] = [(sx, CHAIR_Y, 0.0)]
        mem["bay_%d" % i] = [(sx + 0.63, 0.16, 0.38)]
        mem["name_%d" % i] = [(sx - 0.63, -0.33, TOP_Z)]
    return mem


def curved_pt(r, a_deg):
    return arc_pt(CURVE_C, r, math.radians(a_deg))


def desk_curved(detail):
    m = T.Mesh()
    n = {0: 16, 1: 8, 2: 4}[detail]
    A = CURVE_A
    ring_sector(m, CURVE_C, CURVE_RI, CURVE_RO, -A, A, n, TOP_Z - TOP_T, TOP_Z, "top", "plastic", bottom="steel")
    ring_sector(m, CURVE_C, 1.635, 1.65, -math.radians(36), math.radians(36), max(4, n * 3 // 4), 0.26, TOP_Z - TOP_T, "steel", "steel", bottom="steel")
    if detail < 2:
        ring_sector(m, CURVE_C, 1.47, 1.62, -math.radians(34), math.radians(34), max(4, n // 2), 0.585, 0.591, "frame", "frame", bottom="frame")
        ring_sector(m, CURVE_C, 1.47, 1.476, -math.radians(34), math.radians(34), max(4, n // 2), 0.591, 0.64, "frame", "frame", ends=False)
        ring_sector(m, CURVE_C, 1.632, 1.668, -math.radians(11), math.radians(11), 4, TOP_Z + 0.002, TOP_Z + 0.002, "rubber", "rubber")
        ring_sector(m, CURVE_C, 1.625, 1.675, -math.radians(11.5), math.radians(11.5), 4, TOP_Z - 0.002, TOP_Z + 0.0015, "frame", "frame")
    for s in (-1, 1):
        p0, p1 = curved_pt(0.99, s * 38), curved_pt(1.66, s * 38)
        beam(m, p0 + (0.371,), p1 + (0.371,), 0.03, 0.682, "steel")
        f0, f1 = curved_pt(0.90, s * 38), curved_pt(1.69, s * 38)
        beam(m, f0 + (0.0235,), f1 + (0.0235,), 0.06, 0.023, "frame")
        if detail == 0:
            for r in (0.94, 1.65):
                leveler(m, *curved_pt(r, s * 38))
    # Baie PC rayonnante à la gauche de l'opérateur (+X), porte vers lui.
    b0, b1 = curved_pt(1.08, 29), curved_pt(1.58, 29)
    beam(m, b0 + (0.38,), b1 + (0.38,), 0.22, 0.56, "steel")
    if detail < 2:
        d0 = curved_pt(1.0765, 29)
        beam(m, d0 + (0.36,), b0 + (0.36,), 0.18, 0.40, "vent")
        bs0, bs1 = curved_pt(1.2, 29), curved_pt(1.5, 29)
        beam(m, bs0 + (0.686,), bs1 + (0.686,), 0.16, 0.052, "steel")
    if detail == 0:
        l0, l1 = curved_pt(1.075, 25.5), curved_pt(1.079, 25.5)
        beam(m, l0 + (0.60,), l1 + (0.60,), 0.01, 0.004, "led")
        T.box(m, -0.07, 0.07, -0.31, -0.19, TOP_Z, TOP_Z + 0.003, "steel", skip=("-z",))
    if detail < 2:
        nameplate(m, -0.80, -0.13, detail, rot=-24)
    return m


def curved_boxes():
    boxes = []
    k = 4
    for i in range(k):
        a0, a1 = -CURVE_A + 2 * CURVE_A * i / k, -CURVE_A + 2 * CURVE_A * (i + 1) / k
        am = (a0 + a1) / 2
        pts = []
        for a in (a0, a1):
            for r in (CURVE_RI * math.cos((a1 - a0) / 2), CURVE_RO):
                rr = r / math.cos(a - am) if r < CURVE_RO else r
                for z in (TOP_Z - TOP_T, TOP_Z):
                    pts.append(arc_pt(CURVE_C, rr, a) + (z,))
        boxes.append(("pts", pts))
        pts = []
        for a in (a0 * 0.9, a1 * 0.9):
            for r in (1.62, 1.65):
                for z in (0.26, TOP_Z - TOP_T):
                    pts.append(arc_pt(CURVE_C, r, a) + (z,))
        boxes.append(("pts", pts))
    tmp = T.Mesh()
    for s in (-1, 1):
        boxes.append(beam(tmp, curved_pt(0.90, s * 38) + (0.356,), curved_pt(1.69, s * 38) + (0.356,), 0.06, 0.712, "steel"))
    boxes.append(beam(tmp, curved_pt(1.08, 29) + (0.40,), curved_pt(1.58, 29) + (0.40,), 0.22, 0.60, "steel"))
    return boxes


def desk_corner(detail):
    """Pièce d'angle : quart d'anneau (rayons 0,30 et 1,15 m) du demi-axe -Y au demi-axe +X, opérateur vers le centre."""
    m = T.Mesh()
    n = {0: 12, 1: 6, 2: 3}[detail]
    c = (0.0, 0.0)
    ring_sector(m, c, 0.30, 1.15, 0.0, math.pi / 2, n, TOP_Z - TOP_T, TOP_Z, "top", "plastic", bottom="steel")
    ring_sector(m, c, 1.085, 1.10, math.radians(6), math.radians(84), max(3, n * 2 // 3), 0.26, TOP_Z - TOP_T, "steel", "steel", bottom="steel")
    px, py = arc_pt(c, 0.98, math.pi / 4)
    T.cylinder(m, (px, py, 0.02), (px, py, TOP_Z - TOP_T), 0.035, "steel", n=12 if detail == 0 else 6)
    T.cylinder(m, (px, py, 0.0), (px, py, 0.02), 0.16, "frame", n=16 if detail == 0 else 8)
    qx, qy = arc_pt(c, 0.42, math.pi / 4)
    T.cylinder(m, (qx, qy, 0.02), (qx, qy, TOP_Z - TOP_T), 0.025, "steel", n=10 if detail == 0 else 6)
    if detail < 2:
        T.cylinder(m, (qx, qy, 0.0), (qx, qy, 0.02), 0.09, "frame", n=12 if detail == 0 else 8)
        ring_sector(m, c, 1.05, 1.086, math.radians(34), math.radians(56), 3, TOP_Z + 0.002, TOP_Z + 0.002, "rubber", "rubber")
        ring_sector(m, c, 1.043, 1.093, math.radians(33.5), math.radians(56.5), 3, TOP_Z - 0.002, TOP_Z + 0.0015, "frame", "frame")
    return m


def corner_boxes():
    boxes = []
    c = (0.0, 0.0)
    for i in range(3):
        a0, a1 = math.pi / 6 * i, math.pi / 6 * (i + 1)
        am = (a0 + a1) / 2
        pts = []
        for a in (a0, a1):
            for r in (0.30 * math.cos((a1 - a0) / 2), 1.15):
                rr = r / math.cos(a - am) if r < 1.15 else r
                for z in (TOP_Z - TOP_T, TOP_Z):
                    pts.append(arc_pt(c, rr, a) + (z,))
        boxes.append(("pts", pts))
    px, py = arc_pt(c, 0.98, math.pi / 4)
    boxes.append((px - 0.11, px + 0.11, py - 0.11, py + 0.11, 0.0, TOP_Z - TOP_T))
    return boxes


def table_brief(detail):
    m = T.Mesh()
    n = {0: 8, 1: 4, 2: 1}[detail]
    prism(m, rrect(-1.2, 1.2, -0.55, 0.55, 0.22, 0.22, n), TOP_Z - TOP_T, TOP_Z, "top", "plastic", bottom="steel")
    bv = 0.005 if detail == 0 else 0
    for sx in (-1, 1):
        x = sx * 0.78
        T.box(m, x - 0.06, x + 0.06, -0.26, 0.26, 0.03, TOP_Z - TOP_T, "steel", b=bv)
        prism(m, rrect(x - 0.22, x + 0.22, -0.40, 0.40, 0.08, 0.08, 3 if detail < 2 else 0), 0.0, 0.03, "frame", "frame")
    if detail < 2:
        T.box(m, -0.72, 0.72, -0.04, 0.04, 0.60, TOP_Z - TOP_T, "frame")
        T.box(m, -0.31, 0.31, -0.065, 0.065, TOP_Z - 0.004, TOP_Z + 0.0015, "frame", skip=("-z",))
        m.poly([(-0.30, -0.055, TOP_Z + 0.002), (0.30, -0.055, TOP_Z + 0.002), (0.30, 0.055, TOP_Z + 0.002), (-0.30, 0.055, TOP_Z + 0.002)],
               "rubber", hint=(0, 0, 1))
    return m


# --------------------------------------------------------------------------------------------------------------------
# Fauteuil d'opérateur
# --------------------------------------------------------------------------------------------------------------------
def chair(detail):
    """Fauteuil d'opérateur : il regarde vers +Y. Piètement cinq branches à roulettes, vérin, assise rembourrée,
    dossier résille cintré sur son cadre, renfort lombaire, accoudoirs réglables."""
    m = T.Mesh()
    nc = 12 if detail == 0 else 8 if detail == 1 else 6
    # Piètement.
    T.cylinder(m, (0, 0, 0.055), (0, 0, 0.125), 0.045, "frame", n=nc)
    for k in range(5):
        a = math.radians(90 + 72 * k)
        ca, sa = math.cos(a), math.sin(a)
        beam(m, (0.03 * ca, 0.03 * sa, 0.095), (0.30 * ca, 0.30 * sa, 0.07), 0.045, 0.028, "frame")
        ex, ey = 0.30 * ca, 0.30 * sa
        if detail < 2:
            T.cylinder(m, (ex, ey, 0.045), (ex, ey, 0.075), 0.012, "steel", n=6)
            T.cylinder(m, (ex - 0.022 * sa, ey + 0.022 * ca, 0.028), (ex + 0.022 * sa, ey - 0.022 * ca, 0.028), 0.028, "rubber", n=nc)
        else:
            T.box(m, ex - 0.025, ex + 0.025, ey - 0.025, ey + 0.025, 0.0, 0.056, "rubber")
    # Vérin et mécanisme.
    T.cylinder(m, (0, 0, 0.125), (0, 0, 0.30), 0.03, "steel", n=nc)
    T.cylinder(m, (0, 0, 0.30), (0, 0, 0.41), 0.019, "frame", n=nc)
    T.box(m, -0.10, 0.10, -0.13, 0.11, 0.405, 0.445, "steel", b=0.006 if detail == 0 else 0)
    if detail == 0:
        T.cylinder(m, (-0.10, 0.03, 0.42), (-0.21, 0.03, 0.415), 0.007, "steel", n=6)
        T.box(m, -0.245, -0.205, 0.015, 0.045, 0.408, 0.422, "plastic", b=0.002)
    # Assise : coque et coussin lissé, bord avant en cascade.
    sz = 0.445
    if detail < 2:
        ns = 5 if detail == 0 else 2
        rings = []
        for inset, z, yf in ((0.035, sz, 0.0), (0.004, sz + 0.02, 0.0), (0.0, sz + 0.045, 0.0), (0.012, sz + 0.066, -0.004), (0.05, sz + 0.075, -0.012)):
            loop = rrect(-0.25 + inset, 0.25 - inset, -0.22 + inset, 0.27 - inset + yf, 0.09, 0.06, ns)
            rings.append([(p[0], p[1], z) for p in loop])
        loft(m, rings[:2], "plastic", "seatshell", cap0="plastic")
        loft(m, rings[1:], "mesh", "seatpad", cap1="mesh")
    else:
        T.box(m, -0.25, 0.25, -0.22, 0.27, sz, sz + 0.075, "mesh")
    # Dossier : cadre cintré (rayon 0,55 m), résille tendue, incliné de 8° vers l'arrière.
    back = T.Mesh()
    Rb, half, yb = 0.55, 0.235, -0.235
    z0, z1 = 0.60, 1.10
    nb = 6 if detail == 0 else 3 if detail == 1 else 1

    def bp(x, z, off=0.0):
        a = math.asin(x / Rb)
        return (x, yb - off + (Rb - Rb * math.cos(a)), z)
    xs = [-half + 2 * half * i / nb for i in range(nb + 1)]
    for xa, xb in zip(xs, xs[1:]):
        for off, hint in ((0.0, (0, 1, 0)), (0.004, (0, -1, 0))):
            back.poly([bp(xa, z0 + 0.02, off), bp(xb, z0 + 0.02, off), bp(xb, z1 - 0.02, off), bp(xa, z1 - 0.02, off)], "mesh", hint=hint)
        if detail < 2:
            for zz in (z0, z1):
                beam(back, bp(xa, zz, 0.002), bp(xb, zz, 0.002), 0.022, 0.03, "plastic")
            # Renfort lombaire : bandeau réglable devant le bas du dossier.
            if abs((xa + xb) / 2) < half * 0.72:
                beam(back, bp(xa, 0.72, -0.012), bp(xb, 0.72, -0.012), 0.012, 0.075, "plastic")
    for sx in (-1, 1):
        if detail < 2:
            beam(back, bp(sx * half, z0 - 0.012, 0.002), bp(sx * half, z1 + 0.012, 0.002), 0.03, 0.022, "plastic")
        else:
            beam(back, bp(sx * half, z0, 0.002), bp(sx * half, z1, 0.002), 0.03, 0.022, "plastic")
    # Colonne du dossier : du mécanisme au cadre.
    beam(back, (0, yb - 0.035, 0.52), (0, yb - 0.035, 0.90), 0.07, 0.022, "plastic")
    if detail < 2:
        for zz in (0.62, 0.88):
            beam(back, (0, yb - 0.035, zz), (0, yb - 0.004, zz), 0.05, 0.03, "plastic")
    tilt_x(back, math.radians(8), (0.0, yb, 0.52))
    m.merge(back)
    beam(m, (0, -0.12, 0.425), (0, yb - 0.04, 0.425), 0.07, 0.025, "steel")
    beam(m, (0, yb - 0.04, 0.412), (0, yb - 0.04, 0.54), 0.07, 0.025, "steel")
    # Accoudoirs : montant réglable, manchette rembourrée.
    for sx in (-1, 1):
        x = sx * 0.285
        beam(m, (sx * 0.10, 0.0, 0.43), (x, 0.0, 0.43), 0.05, 0.02, "steel")
        beam(m, (x, 0.0, 0.42), (x, 0.0, 0.655), 0.045, 0.03, "frame")
        if detail == 0:
            T.box(m, x - 0.026, x + 0.026, -0.02, 0.02, 0.54, 0.585, "plastic", b=0.003)
            prism(m, rrect(x - 0.045, x + 0.045, -0.12, 0.15, 0.03, 0.03, 3), 0.655, 0.682, "plastic", "plastic", bottom="plastic")
        else:
            T.box(m, x - 0.045, x + 0.045, -0.12, 0.15, 0.655, 0.682, "plastic")
    return m


def chair_boxes():
    return [(-0.25, 0.25, -0.22, 0.27, 0.40, 0.52), (-0.24, 0.24, -0.33, -0.22, 0.52, 1.10), (-0.30, 0.30, -0.30, 0.30, 0.0, 0.10),
            (-0.04, 0.04, -0.04, 0.04, 0.10, 0.40), (0.24, 0.33, -0.12, 0.15, 0.42, 0.682), (-0.33, -0.24, -0.12, 0.15, 0.42, 0.682)]


# --------------------------------------------------------------------------------------------------------------------
# Accessoires
# --------------------------------------------------------------------------------------------------------------------
def headset(detail):
    """Casque micro posé debout sur ses coques : arceau, coques, coussinets, perche micro."""
    m = T.Mesh()
    R, zc = 0.083, 0.095
    na = 16 if detail == 0 else 6 if detail == 1 else 3
    pts = [(R * math.sin(a), 0.0, zc + R * math.cos(a)) for a in [math.radians(-105 + 210 * i / na) for i in range(na + 1)]]
    for a, b in zip(pts, pts[1:]):
        beam(m, a, b, 0.028, 0.006, "plastic")
    if detail < 2:
        for a, b in zip(pts[na // 3:], pts[na // 3 + 1: na - na // 3 + 1]):
            beam(m, (a[0] * 0.93, 0, zc + (a[2] - zc) * 0.93), (b[0] * 0.93, 0, zc + (b[2] - zc) * 0.93), 0.028, 0.008, "mesh")
    n = 20 if detail == 0 else 10 if detail == 1 else 6
    for sx in (-1, 1):
        T.cylinder(m, (sx * 0.062, 0, 0.048), (sx * 0.098, 0, 0.048), 0.046, "plastic", n=n)
        if detail < 2:
            T.cylinder(m, (sx * 0.044, 0, 0.048), (sx * 0.062, 0, 0.048), 0.043, "rubber", n=n)
            T.cylinder(m, (sx * 0.098, 0, 0.048), (sx * 0.101, 0, 0.048), 0.03, "frame", n=n)
    if detail < 2:
        cable_run(m, [(0.09, 0.02, 0.03), (0.085, 0.08, 0.035), (0.04, 0.125, 0.045)], r=0.0035, n=6)
        T.cylinder(m, (0.04, 0.125, 0.045), (0.015, 0.135, 0.047), 0.009, "rubber", n=8)
    return m


def notepad(detail):
    """Bloc-notes A5 à spirale et son stylo."""
    m = T.Mesh()
    T.box(m, -0.075, 0.075, -0.105, 0.105, 0.0, 0.009, "paper", skip=("-z", "+z"))
    T.box(m, -0.077, 0.077, -0.107, 0.107, 0.0, 0.0015, "plastic", skip=("-z",))
    kit_quad(m, [(-0.075, -0.105, 0.0092), (0.075, -0.105, 0.0092), (0.075, 0.105, 0.0092), (-0.075, 0.105, 0.0092)], "notepad",
             [(1, 0), (0, 0), (0, 1), (1, 1)], (0, 0, 1))
    if detail < 2:
        T.box(m, -0.07, 0.07, -0.109, -0.099, 0.004, 0.013, "steel")
        sub = T.Mesh()
        T.cylinder(sub, (0, -0.07, 0.0045), (0, 0.06, 0.0045), 0.0045, "ceramic", n=8 if detail == 0 else 6)
        T.cylinder(sub, (0, 0.06, 0.0045), (0, 0.072, 0.0045), 0.003, "frame", n=6)
        m.merge(move(sub, -0.105, 0.01, 0.0, 14))
    return m


def mug(detail):
    """Tasse à café : paroi lissée, anse, café."""
    m = T.Mesh()
    n = 20 if detail == 0 else 10 if detail == 1 else 6
    r, h = 0.041, 0.096
    prof = [(r * 0.86, 0.0), (r * 0.97, 0.006), (r, 0.03), (r, h - 0.003), (r * 0.985, h)] if detail < 2 else [(r, 0.0), (r, h)]
    rings = [[(rr * math.cos(2 * math.pi * k / n), rr * math.sin(2 * math.pi * k / n), z) for k in range(n)] for rr, z in prof]
    loft(m, rings, "ceramic", "mug", cap0="ceramic")
    if detail < 2:
        inner = [[(rr * math.cos(2 * math.pi * k / n), rr * math.sin(2 * math.pi * k / n), z) for k in range(n)] for rr, z in ((r * 0.985, h), (r * 0.9, h - 0.001))]
        loft(m, inner, "ceramic", "mugrim")
        fan(m, [(r * 0.9 * math.cos(2 * math.pi * k / n), r * 0.9 * math.sin(2 * math.pi * k / n)) for k in range(n)], h - 0.014, "coffee")
        wall = [[(rr * math.cos(2 * math.pi * k / n), rr * math.sin(2 * math.pi * k / n), z) for k in range(n)] for rr, z in ((r * 0.9, h - 0.001), (r * 0.9, h - 0.014))]
        for a, b in zip(wall[0], wall[0][1:] + wall[0][:1]):
            m.poly([a, b, (b[0], b[1], h - 0.014), (a[0], a[1], h - 0.014)], "ceramic", hint=(-(a[0] + b[0]), -(a[1] + b[1]), 0))
        hp = [(-r + 0.002, 0, 0.078), (-r - 0.026, 0, 0.074), (-r - 0.03, 0, 0.04), (-r + 0.002, 0, 0.022)]
        for a, b in zip(hp, hp[1:]):
            beam(m, a, b, 0.014, 0.009, "ceramic")
    else:
        fan(m, [(p[0], p[1]) for p in rings[-1]], h, "coffee")
    return m


def handset(detail):
    """Combiné radio de table sur son berceau : corps lissé, écouteur, micro, alternat, cordon spiralé."""
    m = T.Mesh()
    T.box(m, -0.045, 0.045, -0.11, 0.11, 0.0, 0.022, "steel", b=0.004 if detail == 0 else 0)
    if detail >= 2:
        T.box(m, -0.028, 0.028, -0.10, 0.10, 0.022, 0.05, "plastic")
        return m
    prof = [(-0.105, 0.046, 0.030), (-0.085, 0.056, 0.034), (-0.055, 0.050, 0.030), (-0.03, 0.040, 0.024), (0.03, 0.040, 0.024),
            (0.055, 0.050, 0.030), (0.085, 0.056, 0.034), (0.105, 0.046, 0.030)]
    if detail == 1:
        prof = prof[::2] + [prof[-1]]
    K = 8 if detail == 0 else 4
    rings = []
    for y, w, h in prof:
        zc = 0.026 + 0.5 * h + (0.006 if abs(y) < 0.04 else 0.0)
        rings.append([(w / 2 * math.cos(2 * math.pi * k / K + math.pi / K), y, zc + h / 2 * math.sin(2 * math.pi * k / K + math.pi / K)) for k in range(K)])
    loft(m, rings, "plastic", "handset", cap0="plastic", cap1="plastic")
    kit_quad(m, [(-0.036, -0.10, 0.0224), (0.036, -0.10, 0.0224), (0.036, -0.02, 0.0224), (-0.036, -0.02, 0.0224)], "handset",
             [(1, 0), (0, 0), (0, 0.27), (1, 0.27)], (0, 0, 1))
    if detail == 0:
        T.box(m, 0.02, 0.026, -0.022, 0.022, 0.036, 0.05, "rubber", b=0.001)      # alternat
        T.box(m, -0.02, 0.02, 0.085, 0.105, 0.018, 0.026, "led")
        pts = [(0.0, -0.105, 0.035)]
        for i in range(1, 6):
            pts.append((0.012 * math.sin(i * 2.1) - 0.004 * i, -0.105 - 0.012 * i, 0.012 + 0.008 * math.cos(i * 2.1)))
        cable_run(m, pts, r=0.0025, n=4)
    return m


def cabletray(detail):
    """Panier à câbles sous plateau (0,80 m) : fils d'acier, deux pattes de fixation (origine : dessous du plateau)."""
    m = T.Mesh()
    L, w, h = 0.80, 0.12, 0.08
    if detail >= 2:
        T.box(m, -L / 2, L / 2, -w / 2, w / 2, -h - 0.02, -0.02, "steel")
        return m
    for y, z in ((-w / 2, -0.02), (w / 2, -0.02), (-w / 2, -0.02 - h), (w / 2, -0.02 - h), (0.0, -0.02 - h), (-w / 2, -0.02 - h / 2), (w / 2, -0.02 - h / 2)):
        beam(m, (-L / 2, y, z), (L / 2, y, z), 0.005, 0.005, "steel")
    step = 0.1 if detail == 0 else 0.2
    k = 0
    while -L / 2 + k * step <= L / 2 + 1e-6:
        x = -L / 2 + k * step
        beam(m, (x, -w / 2, -0.02), (x, -w / 2, -0.02 - h), 0.004, 0.004, "steel")
        beam(m, (x, w / 2, -0.02), (x, w / 2, -0.02 - h), 0.004, 0.004, "steel")
        beam(m, (x, -w / 2, -0.02 - h), (x, w / 2, -0.02 - h), 0.004, 0.004, "steel")
        k += 1
    for sx in (-1, 1):
        T.box(m, sx * 0.3 - 0.015, sx * 0.3 + 0.015, -w / 2 - 0.003, -w / 2, -0.02, 0.0, "steel")
        T.box(m, sx * 0.3 - 0.015, sx * 0.3 + 0.015, -w / 2 - 0.03, -w / 2, -0.003, 0.0, "steel")
    return m


# --------------------------------------------------------------------------------------------------------------------
# Modèles
# --------------------------------------------------------------------------------------------------------------------
def solo(fn, **kw):
    """Modèle d'une seule pièce : (maillage, boîtes) à partir d'une fonction de pièce qui rend sa boîte."""
    def build(detail):
        m = T.Mesh()
        b = fn(m, detail, **kw)
        build.box = b
        return m
    return build


def _kbd(numpad=True, layout="azerty", backlit=False):
    return solo(lambda m, d: keyboard(m, 0.0, 0.0, d, numpad=numpad, layout=layout, backlit=backlit))


def _mouse_pad(m, d):
    deskpad(m, -0.50, 0.30, -0.15, 0.15, d)
    mouse(m, -0.36, 0.0, d, base_z=0.0034)
    return (-0.50, 0.30, -0.15, 0.15, 0.0, 0.045)


# nom -> (construction(détail), boîtes fermées, masse kg, points mémoire, classe, nom FR, nom EN, sous-catégorie)
MODELS = {
    "toc_furn_desk_single": (lambda d: desk_straight(d, 1), lambda: desk_straight_boxes(1), 55.0, lambda: desk_memory(1),
                             "COMSPEC_ATAK_TocDesk_Single", "Console TOC 1 poste (COMSPEC)", "TOC console, 1 seat (COMSPEC)", "Furn"),
    "toc_furn_desk_double": (lambda d: desk_straight(d, 2), lambda: desk_straight_boxes(2), 95.0, lambda: desk_memory(2),
                             "COMSPEC_ATAK_TocDesk_Double", "Console TOC 2 postes (COMSPEC)", "TOC console, 2 seats (COMSPEC)", "Furn"),
    "toc_furn_desk_triple": (lambda d: desk_straight(d, 3), lambda: desk_straight_boxes(3), 135.0, lambda: desk_memory(3),
                             "COMSPEC_ATAK_TocDesk_Triple", "Console TOC 3 postes (COMSPEC)", "TOC console, 3 seats (COMSPEC)", "Furn"),
    "toc_furn_desk_curved": (desk_curved, curved_boxes, 70.0,
                             lambda: {"pc_1": [(0, 0, TOP_Z)], "mon_1": [(0, -0.25, TOP_Z)], "chair_1": [(0, CHAIR_Y, 0)]},
                             "COMSPEC_ATAK_TocDesk_Curved", "Console TOC en arc (COMSPEC)", "TOC console, curved (COMSPEC)", "Furn"),
    "toc_furn_desk_corner": (desk_corner, corner_boxes, 40.0, lambda: {"end_a": [(0, -0.725, TOP_Z)], "end_b": [(0.725, 0, TOP_Z)]},
                             "COMSPEC_ATAK_TocDesk_Corner", "Console TOC, angle (COMSPEC)", "TOC console, corner (COMSPEC)", "Furn"),
    "toc_furn_table_brief": (table_brief, lambda: [(-1.2, 1.2, -0.55, 0.55, TOP_Z - TOP_T, TOP_Z), (-1.0, -0.56, -0.40, 0.40, 0.0, TOP_Z - TOP_T),
                                                   (0.56, 1.0, -0.40, 0.40, 0.0, TOP_Z - TOP_T)], 80.0, lambda: {"top": [(0, 0, TOP_Z)]},
                             "COMSPEC_ATAK_TocDesk_Briefing", "Table de briefing TOC (COMSPEC)", "TOC briefing table (COMSPEC)", "Furn"),
    "toc_furn_chair": (chair, chair_boxes, 16.0, lambda: {"seat": [(0, 0.02, 0.52)], "look": [(0, 1.0, 1.2)]},
                       "COMSPEC_ATAK_TocChair", "Fauteuil d'opérateur TOC (COMSPEC)", "TOC operator chair (COMSPEC)", "Furn"),
    "toc_furn_kbd": (_kbd(), None, 0.7, None, "COMSPEC_ATAK_TocKeyboard", "Clavier AZERTY (COMSPEC)", "Keyboard, AZERTY (COMSPEC)", "Props"),
    "toc_furn_kbd_tkl": (_kbd(numpad=False), None, 0.55, None, "COMSPEC_ATAK_TocKeyboard_TKL", "Clavier compact AZERTY (COMSPEC)",
                         "Compact keyboard, AZERTY (COMSPEC)", "Props"),
    "toc_furn_kbd_qwerty": (_kbd(layout="qwerty"), None, 0.7, None, "COMSPEC_ATAK_TocKeyboard_Qwerty", "Clavier QWERTY (COMSPEC)",
                            "Keyboard, QWERTY (COMSPEC)", "Props"),
    "toc_furn_kbd_backlit": (_kbd(backlit=True), None, 0.7, None, "COMSPEC_ATAK_TocKeyboard_Backlit", "Clavier rétroéclairé (COMSPEC)",
                             "Backlit keyboard (COMSPEC)", "Props"),
    "toc_furn_mouse": (solo(lambda m, d: mouse(m, 0.0, 0.0, d)), None, 0.1, None, "COMSPEC_ATAK_TocMouse", "Souris sans fil (COMSPEC)",
                       "Wireless mouse (COMSPEC)", "Props"),
    "toc_furn_mouse_pad": (solo(_mouse_pad), None, 0.4, None, "COMSPEC_ATAK_TocMouse_Pad", "Souris et tapis de bureau (COMSPEC)",
                           "Mouse and desk mat (COMSPEC)", "Props"),
    "toc_furn_tower": (solo(lambda m, d: tower(m, 0.0, -0.225, d)), None, 12.0, None, "COMSPEC_ATAK_TocTower", "Station de travail, tour (COMSPEC)",
                       "Workstation tower (COMSPEC)", "Props"),
    "toc_furn_sff": (solo(lambda m, d: sff(m, 0.0, -0.145, d)), None, 5.0, None, "COMSPEC_ATAK_TocTower_SFF", "Unité centrale compacte (COMSPEC)",
                     "Compact workstation (COMSPEC)", "Props"),
    "toc_furn_minipc": (solo(lambda m, d: mini_pc(m, 0.0, 0.0, d)), None, 1.3, None, "COMSPEC_ATAK_TocMiniPC", "Mini-PC (COMSPEC)",
                        "Mini PC (COMSPEC)", "Props"),
    "toc_furn_sff_under": (None, None, 6.0, None, "COMSPEC_ATAK_TocTower_Under", "Unité centrale sous plateau (COMSPEC)",
                           "Under-desk workstation (COMSPEC)", "Props"),
    "toc_furn_headset": (headset, lambda: [(-0.105, 0.105, -0.05, 0.05, 0.0, 0.185)], 0.35, None, "COMSPEC_ATAK_TocProp_Headset",
                         "Casque micro (COMSPEC)", "Headset (COMSPEC)", "Props"),
    "toc_furn_notepad": (notepad, lambda: [(-0.115, 0.08, -0.11, 0.11, 0.0, 0.015)], 0.3, None, "COMSPEC_ATAK_TocProp_Notepad",
                         "Bloc-notes et stylo (COMSPEC)", "Notepad and pen (COMSPEC)", "Props"),
    "toc_furn_mug": (mug, lambda: [(-0.072, 0.042, -0.042, 0.042, 0.0, 0.096)], 0.35, None, "COMSPEC_ATAK_TocProp_Mug", "Tasse à café (COMSPEC)",
                     "Coffee mug (COMSPEC)", "Props"),
    "toc_furn_handset": (handset, lambda: [(-0.045, 0.045, -0.11, 0.11, 0.0, 0.065)], 0.6, None, "COMSPEC_ATAK_TocProp_Handset",
                         "Combiné radio de table (COMSPEC)", "Desk radio handset (COMSPEC)", "Props"),
    "toc_furn_cabletray": (cabletray, lambda: [(-0.40, 0.40, -0.065, 0.065, -0.10, 0.0)], 1.5, None, "COMSPEC_ATAK_TocProp_CableTray",
                           "Panier à câbles sous plateau (COMSPEC)", "Under-desk cable tray (COMSPEC)", "Props"),
}


def build_model(name, detail):
    """(maillage, boîtes fermées) d'un modèle."""
    build, boxes = MODELS[name][0], MODELS[name][1]
    if name == "toc_furn_sff_under":
        m = T.Mesh()
        return m, sff_under(m, detail)
    m = build(detail)
    return m, (boxes() if boxes else [build.box])


def memory_lod(mem):
    pts, sels = [], {}
    for name, plist in sorted(mem.items()):
        sels[name] = (set(range(len(pts), len(pts) + len(plist))), set())
        pts += plist
    return T.lod_bytes(pts, [], sels, 1e15)


def write_model(name):
    mass, memf = MODELS[name][2], MODELS[name][3]
    built = [build_model(name, d) for d in (0, 1, 2)]
    boxes = built[0][1]
    lods = [T.visual_lod(m, res) for (m, _), res in zip(built, (0.0, 2.0, 6.0))]
    lods.append(T.closed_lod(boxes, 10000.0))
    lods.append(T.closed_lod(boxes, 1e13, components=True, mass_total=mass, props=(("autocenter", "0"),)))
    lods.append(T.closed_lod(boxes, 6e15, components=True))
    lods.append(T.closed_lod(boxes, 7e15, components=True))
    m0 = built[0][0]
    P = np.array(m0.pts)
    lo, hi = P.min(0), P.max(0)
    mem = memf() if memf else {}
    mem.setdefault("center", [tuple(float(v) for v in (lo + hi) / 2)])
    lods.append(memory_lod(mem))
    out = os.path.join(DATA, name + ".p3d")
    with open(out, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, len(lods)) + b"".join(lods))
    info = {"class": MODELS[name][4], "fr": MODELS[name][5], "en": MODELS[name][6], "subcat": MODELS[name][7],
            "faces": [len(m.faces) for m, _ in built], "boxes": len(boxes), "mass": mass, "bytes": os.path.getsize(out),
            "min": [round(float(v), 4) for v in lo], "max": [round(float(v), 4) for v in hi],
            "memory": {k: [[round(c, 4) for c in p] for p in v] for k, v in mem.items()}}
    print("écrit %s : %s faces (LOD 0 / 1 / 2), %d boîtes, %d octets" % (name, " / ".join(str(n) for n in info["faces"]), len(boxes), info["bytes"]))
    return m0, info


# --------------------------------------------------------------------------------------------------------------------
# Textures
# --------------------------------------------------------------------------------------------------------------------
def font(size, bold=True):
    for name in (("segoeuib.ttf", "arialbd.ttf") if bold else ("segoeui.ttf", "arial.ttf")) + ("DejaVuSans-Bold.ttf", "DejaVuSans.ttf"):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


def sym_font(size):
    for name in ("seguisym.ttf", "segoeui.ttf", "DejaVuSans.ttf"):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


def draw_keyboard(img, zone, layout, glow):
    x0, y0, w, h = ZONES[zone]
    d = ImageDraw.Draw(img)
    sx, sy = w / KB_W_FULL, h / KB_D
    d.rectangle([x0, y0, x0 + w - 1, y0 + h - 1], fill=(6, 7, 8) if glow else (20, 21, 23))
    cap = (9, 10, 12) if glow else (30, 31, 34)
    edge = (20, 60, 70) if glow else (44, 45, 49)
    ink = (150, 245, 255) if glow else (214, 216, 220)
    for xa, ya, kw, kd, lab in kbd_keys(layout, True):
        a0, b0, a1, b1 = x0 + (xa + 0.0011) * sx, y0 + (ya + 0.0011) * sy, x0 + (xa + kw - 0.0011) * sx, y0 + (ya + kd - 0.0011) * sy
        d.rounded_rectangle([a0, b0, a1, b1], radius=5, fill=cap, outline=edge, width=2 if glow else 1)
        if not lab:
            continue
        big = len(lab) == 1 and lab.isascii() and lab.isalnum()
        f = font(22 if big else 13 if len(lab) > 1 else 19) if lab.isascii() or lab in "²éèçàù" else sym_font(20 if len(lab) == 1 else 13)
        bb = d.textbbox((0, 0), lab, font=f)
        tw, th = bb[2] - bb[0], bb[3] - bb[1]
        if big or len(lab) > 1 and kw > 1.2 * U:
            px, py = a0 + 8, b0 + 7
        else:
            px, py = (a0 + a1 - tw) / 2, (b0 + b1 - th) / 2
        d.text((px - bb[0], py - bb[1]), lab, fill=ink, font=f)


def draw_atlas():
    img = Image.new("RGB", ATLAS, (18, 19, 20))
    d = ImageDraw.Draw(img)
    draw_keyboard(img, "kbd_azerty", "azerty", False)
    draw_keyboard(img, "kbd_qwerty", "qwerty", False)
    draw_keyboard(img, "kbd_glow", "azerty", True)
    # Porte-nom : plaque claire gravée.
    x, y, w, h = ZONES["nameplate"]
    d.rectangle([x, y, x + w - 1, y + h - 1], fill=(196, 198, 196))
    d.rectangle([x + 5, y + 5, x + w - 6, y + h - 6], outline=(60, 62, 64), width=2)
    f = font(40)
    bb = d.textbbox((0, 0), "OPS", font=f)
    d.text((x + (w - bb[2] + bb[0]) / 2 - bb[0], y + (h - bb[3] + bb[1]) / 2 - bb[1]), "OPS", fill=(28, 30, 32), font=f)

    def ports(x, y, w, h, vertical):
        """Façade noire : grille en nid d'abeilles, bouton de marche, prises USB, USB-C, audio."""
        d.rectangle([x, y, x + w - 1, y + h - 1], fill=(13, 14, 15))
        gx0, gy0, gx1, gy1 = (x + 14, y + 110, x + w - 14, y + h - 40) if vertical else (x + 150, y + 10, x + w - 70, y + h - 10)
        d.rounded_rectangle([gx0 - 4, gy0 - 4, gx1 + 4, gy1 + 4], radius=6, fill=(8, 8, 9), outline=(34, 35, 38))
        step = 11
        for j, yy in enumerate(range(int(gy0) + 4, int(gy1) - 4, step)):
            for xx in range(int(gx0) + 4 + (step // 2 if j % 2 else 0), int(gx1) - 6, step):
                d.ellipse([xx, yy, xx + 6, yy + 6], fill=(2, 2, 2), outline=(26, 27, 29))
        if vertical:
            cx = x + w // 2
            d.ellipse([cx - 15, y + 27, cx + 15, y + 57], fill=(40, 42, 46), outline=(120, 215, 255), width=2)
            d.line([cx, y + 34, cx, y + 44], fill=(190, 230, 255), width=2)
            px = x + 16
            for k in range(2):
                d.rectangle([px + k * 34, y + 72, px + k * 34 + 26, y + 84], fill=(4, 4, 4), outline=(90, 92, 96))
                d.rectangle([px + k * 34 + 4, y + 77, px + k * 34 + 22, y + 81], fill=(30, 80, 190))
            d.rounded_rectangle([px + 70, y + 74, px + 90, y + 82], radius=4, fill=(4, 4, 4), outline=(90, 92, 96))
            d.ellipse([x + w - 30, y + 70, x + w - 16, y + 84], fill=(3, 3, 3), outline=(70, 110, 70))
        else:
            cy = y + h // 2
            d.ellipse([x + 20, cy - 14, x + 48, cy + 14], fill=(40, 42, 46), outline=(120, 215, 255), width=2)
            for k in range(2):
                d.rectangle([x + 64 + k * 36, cy - 7, x + 90 + k * 36, cy + 7], fill=(4, 4, 4), outline=(90, 92, 96))
                d.rectangle([x + 68 + k * 36, cy - 2, x + 86 + k * 36, cy + 2], fill=(30, 80, 190))
            d.rounded_rectangle([x + w - 56, cy - 5, x + w - 34, cy + 5], radius=4, fill=(4, 4, 4), outline=(90, 92, 96))
            d.ellipse([x + w - 24, cy - 7, x + w - 10, cy + 7], fill=(3, 3, 3), outline=(70, 110, 70))
    ports(*ZONES["tower_front"], True)
    ports(*ZONES["sff_front"], True)
    ports(*ZONES["mini_front"], False)
    # Souris vue de dessus : nez en haut de la zone (-Y), coutures des boutons, molette, flanc à picots.
    x, y, w, h = ZONES["mouse_top"]
    d.rectangle([x, y, x + w - 1, y + h - 1], fill=(24, 25, 27))
    d.line([x + w // 2, y, x + w // 2, y + 96], fill=(4, 4, 4), width=3)
    d.arc([x - 30, y + 60, x + w + 30, y + 150], 200, 340, fill=(4, 4, 4), width=3)
    d.rounded_rectangle([x + w // 2 - 9, y + 28, x + w // 2 + 9, y + 82], radius=8, fill=(5, 5, 5))
    d.rounded_rectangle([x + w // 2 - 5, y + 96, x + w // 2 + 5, y + 110], radius=3, fill=(45, 47, 50))
    for j in range(8):
        for i in range(3):
            d.ellipse([x + w - 16 + i * 5, y + 100 + j * 9, x + w - 13 + i * 5, y + 103 + j * 9], fill=(8, 8, 8))
    d.ellipse([x + w // 2 - 3, y + 196, x + w // 2 + 3, y + 202], fill=(120, 215, 255))
    # Combiné : grille de l'écouteur.
    x, y, w, h = ZONES["handset"]
    d.rectangle([x, y, x + w - 1, y + h - 1], fill=(15, 16, 17))
    for j in range(7):
        for i in range(8):
            d.ellipse([x + 14 + i * 12, y + 8 + j * 10, x + 20 + i * 12, y + 14 + j * 10], fill=(2, 2, 2), outline=(40, 41, 44))
    # Bloc-notes : page lignée, quelques lignes manuscrites illisibles (traits).
    x, y, w, h = ZONES["notepad"]
    d.rectangle([x, y, x + w - 1, y + h - 1], fill=(226, 224, 210))
    for j in range(1, 16):
        d.line([x + 14, y + 20 + j * 25, x + w - 14, y + 20 + j * 25], fill=(150, 170, 200), width=1)
    d.line([x + 40, y, x + 40, y + h], fill=(210, 120, 120), width=1)
    rr = np.random.default_rng(7)
    for j in range(1, 9):
        xx = x + 48
        while xx < x + w - 30 - (j * 37) % 90:
            ln = int(rr.integers(10, 34))
            d.line([xx, y + 14 + j * 25, xx + ln, y + 14 + j * 25 + int(rr.integers(-2, 3))], fill=(34, 40, 90), width=2)
            xx += ln + 7
    return img


def textures(tmp):
    """PNG des textures de ce module (pour le PAA et pour les rendus). Rend les noms des jeux co / nohq / smdi."""
    N = 512
    mott, fine = T.noise(N, 2.4), T.noise(N, 0.6)
    g = 0.50 + 0.03 * (mott - 0.5) + 0.02 * (fine - 0.5)
    T.maps(tmp, "toc_top", g[..., None] * np.array([1.0, 1.005, 1.02])[None, None, :], fine, 1.2, 0.22 + 0.04 * (fine - 0.5), 0.45)
    # Résille : trame tissée en losange sur fond noir (tuile de 6 cm, 16 mailles).
    yy, xx = np.mgrid[0:N, 0:N] / N
    wa = np.abs(np.sin((xx + yy) * 16 * math.pi))
    wb = np.abs(np.sin((xx - yy) * 16 * math.pi))
    weave = np.maximum(wa, wb) ** 3
    g = 0.035 + 0.075 * weave + 0.01 * (T.noise(N, 1.0) - 0.5)
    T.maps(tmp, "toc_mesh", g[..., None] * np.array([1.0, 1.02, 1.06])[None, None, :], weave, 4.0, 0.08 + 0.1 * weave, 0.2)
    draw_atlas().save(os.path.join(tmp, "toc_kit_co.png"))
    return ["toc_top", "toc_mesh"]


def to_paa(tmp, names):
    T.to_paa(tmp, names)
    hemtt = shutil.which("hemtt")
    if hemtt:
        dst = os.path.join(DATA, "toc_kit_co.paa")
        if os.path.exists(dst):
            os.remove(dst)
        subprocess.run([hemtt, "utils", "paa", "convert", os.path.join(tmp, "toc_kit_co.png"), dst], check=True, capture_output=True)


def rvmats():
    def super_(name, comment, spec, power, fresnel, nohq=None, smdi=None):
        with open(os.path.join(DATA, name + ".rvmat"), "w", encoding="utf-8", newline="\n") as f:
            f.write(T.RVMAT_SUPER.format(comment=comment, spec=spec, power=power, fresnel=fresnel,
                                         nohq=nohq or T.TEX + name + "_nohq.paa", smdi=smdi or T.TEX + name + "_smdi.paa"))
    super_("toc_top", "Mobilier de TOC (tools/model_toc_furniture.py) : plateau stratifié gris clair satiné.", 0.22, 35, "1.5,0.35")
    super_("toc_mesh", "Mobilier de TOC : résille et tissu du fauteuil, feutre du tapis.", 0.08, 12, "1.3,0.2")
    super_("toc_kit", "Mobilier de TOC : atlas des légendes de clavier, façades d'unité centrale, pages.", 0.16, 22, "1.4,0.3",
           nohq="#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)", smdi="#(argb,8,8,3)color(1,0.35,0.4,1,SMDI)")
    with open(os.path.join(DATA, "toc_kit_glow.rvmat"), "w", encoding="utf-8", newline="\n") as f:
        f.write("// Mobilier de TOC : clavier rétroéclairé (légendes lumineuses de nuit, discrètes de jour).\nambient[]={1,1,1,1};\n"
                "diffuse[]={1,1,1,1};\nforcedDiffuse[]={0,0,0,0};\nemmisive[]={1.4,1.4,1.4,1};\nspecular[]={0.05,0.05,0.05,1};\n"
                "specularPower=30;\nPixelShaderID=\"Normal\";\nVertexShaderID=\"Basic\";\n")


# --------------------------------------------------------------------------------------------------------------------
# Export pour les rendus Blender (tools/render_toc_furniture.py)
# --------------------------------------------------------------------------------------------------------------------
LOOK = {"frame": (0.45, 0.85), "steel": (0.5, 0.3), "plastic": (0.7, 0.0), "vent": (0.7, 0.2), "top": (0.45, 0.0), "mesh": (0.9, 0.0),
        "kit": (0.6, 0.0), "kitglow": (0.6, 0.0), "rubber": (0.8, 0.0), "paper": (0.9, 0.0), "ceramic": (0.25, 0.0), "coffee": (0.1, 0.0),
        "led": (0.5, 0.0), "screen": (0.08, 0.0), "plate": (0.2, 0.0), "canvas": (0.9, 0.0), "key": (0.6, 0.0)}


def export_obj(m, path, tex_dir, contents=None):
    """OBJ (Z vers le haut) et path + .json : matière -> PNG de couleur ou couleur unie, rugosité, métal, lumière."""
    contents = contents or {}
    groups = {}
    for fi, (idx, uvs, n, mat, sels) in enumerate(m.faces):
        key = mat + ("@" + sels[0] if mat == "screen" and sels and sels[0] in contents else "")
        groups.setdefault(key, []).append((idx, uvs, m.vn.get(fi) or [n] * len(idx)))
    spec = {}
    with open(path, "w") as f:
        for p in m.pts:
            f.write("v %.5f %.5f %.5f\n" % p)
        vt = 0
        for gi, (key, fl) in enumerate(sorted(groups.items())):
            mat = key.split("@")[0]
            name = "m%d_%s" % (gi, key.replace("@", "_"))
            tex = T.MATS[mat][0]
            rough, metal = LOOK.get(mat, (0.6, 0.0))
            s = {"rough": rough, "metal": metal, "key": mat}
            if "@" in key:
                s["emit_png"] = contents[key.split("@")[1]]
            elif tex.startswith("#"):
                s["flat"] = [float(v) for v in tex.split("color(")[1].split(")")[0].split(",")[:3]]
                if mat == "led":
                    s["glow"] = 4.0
            else:
                s["co"] = os.path.join(tex_dir, tex.replace(".paa", ".png")).replace("\\", "/")
                nq = os.path.join(tex_dir, tex.replace("_co.paa", "_nohq.png"))
                if os.path.exists(nq):
                    s["nohq"] = nq.replace("\\", "/")
                if mat == "kitglow":
                    s["glow"] = 1.5
            spec[name] = s
            f.write("usemtl %s\n" % name)
            for idx, uvs, vns in fl:
                for (u, v), n in zip(uvs, vns):
                    f.write("vt %.5f %.5f\nvn %.4f %.4f %.4f\n" % (u, 1 - v, n[0], n[1], n[2]))
                f.write("f " + " ".join("%d/%d/%d" % (i + 1, vt + k + 1, vt + k + 1) for k, i in enumerate(idx)) + "\n")
                vt += len(idx)
    with open(path + ".json", "w") as f:
        json.dump(spec, f)


def main():
    os.makedirs(DATA, exist_ok=True)
    obj_dir = os.environ.get("OBJ_DIR", "")
    tmp = obj_dir or tempfile.mkdtemp(prefix="toc_furn_tex_")
    os.makedirs(tmp, exist_ok=True)
    try:
        names = textures(tmp)
        if not os.environ.get("NO_TEX"):
            to_paa(tmp, names)
        rvmats()
        manifest = {"top_z": TOP_Z, "y_back": Y_BACK, "y_front": Y_FRONT, "seat": SEAT, "chair_y": CHAIR_Y,
                    "curved": {"center": list(CURVE_C), "ri": CURVE_RI, "ro": CURVE_RO, "half_angle_deg": round(math.degrees(CURVE_A), 3)},
                    "kbd": {"full": [round(KB_W_FULL, 4), round(KB_D, 4)], "tkl": [round(KB_W_TKL, 4), round(KB_D, 4)]}, "models": {}}
        for name in MODELS:
            m0, info = write_model(name)
            manifest["models"][name] = info
            if obj_dir:
                T.textures(obj_dir)
                export_obj(m0, os.path.join(obj_dir, name + ".obj"), obj_dir)
        # Postes PC (model_toc_pc.py, mêmes pièces) : encombrement, sens des moniteurs, empreinte sur le plateau.
        import model_toc_pc as PC
        manifest["kits"] = {}
        for name, (build, _) in PC.MODELS.items():
            m0, m1 = build(0), build(1)
            P = np.array(m0.pts)
            ny = [n[1] for _, _, n, mat, _ in m0.faces if mat == "screen"]
            foot = sorted({(round(p[0], 2), round(p[1], 2)) for p in m1.pts if p[2] < 0.08})
            manifest["kits"][name] = {"min": [round(float(v), 4) for v in P.min(0)], "max": [round(float(v), 4) for v in P.max(0)],
                                      "screen_normal_y": round(sum(ny) / len(ny), 4), "footprint": [list(p) for p in foot],
                                      "pad": list(PC.PAD), "unit": list(PC.UNITS[name]), "faces": [len(build(d).faces) for d in (0, 1, 2)]}
        with open(MANIFEST, "w", encoding="utf-8", newline="\n") as f:
            json.dump(manifest, f, ensure_ascii=False, indent=1, sort_keys=True)
            f.write("\n")
    finally:
        if not obj_dir:
            shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
