"""
Mobilier de base SOF posable dans Eden / Zeus (mod COMSPEC Base SOF) : modèles MLOD + vignettes d'éditeur.

  sof_cage_panel        panneau de cage grillagée 1 m x 2,6 m (cadre tube carré, grillage soudé 50 mm)
  sof_cage_door         panneau de cage avec porte battante (animée : source « door »)
  sof_cage_4x3          cage complète 4 x 3 x 2,6 m, toit grillagé, porte animée
  sof_mesh_locker       casier grillagé double porte 1 x 0,6 x 2 m (portes animées : source « door »)
  sof_team_bay          alvéole individuelle en contreplaqué 1 x 0,6 x 2,4 m (dépôt d'équipement)
  sof_team_bay_x5       rangée de 5 alvéoles
  sof_gun_rack          râtelier d'armes en bois 8 places (vide)
  sof_workbench         établi acier 2,2 m avec panneau perforé et tablette
  sof_tool_chest        servante d'atelier 7 tiroirs à roulettes
  sof_shelf             étagère acier 4 niveaux 1,2 x 0,5 x 2 m
  sof_sign_large        pancarte murale 1,6 x 0,8 m à 1,2 m du sol (texture personnalisable)
  sof_sign_small        plaque 0,6 x 0,3 m à 1,5 m du sol (texture personnalisable)
  sof_sign_stand        pancarte sur pieds 1,6 x 0,8 m (texture personnalisable)
  sof_wall_flag         drapeau mural 1,5 x 0,75 m sur barre à 2,4 m (texture personnalisable)

Aucune arme ni équipement modélisé (décision TTRD 13/10/2026) : le dépôt d'équipement viendra plus tard via ACE.
Sélections retexturables (model.cfg) : frame, mesh (cages, casiers), sign, flag.
Chaque modèle : LOD visuelle 1, ombre 0 (volumes fermés, sans grillage), géométrie et géométrie de tir (boîtes convexes,
masse, autocenter=0), mémoire (axes et points d'action des portes).

    python3 gen_sof_textures.py && python3 model_sof_base.py      (ONLY=nom ; NO_PREVIEW=1 ; RENDER_SAMPLES=n)
Extension Blender « Arma 3 Object Builder » requise (comme mod/COMSPEC_ATAK_Native/tools/model_relay_towers.py).
"""
import math
import os
import sys

import bpy  # noqa: I001
import addon_utils
import bmesh
from mathutils import Matrix, Vector

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")
TEX = "z\\comspec_sof\\addons\\main\\data\\"
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "48"))

BLUE = (0.06, 0.16, 0.62)
BLACK = (0.04, 0.045, 0.045)

# matière : (texture du p3d, rvmat, mètres par tuile UV, sélection retexturable, aperçu : png ou couleur, rugosité, métal)
MATS = {
    "frame": ("#(argb,8,8,3)color(0.06,0.16,0.62,1,co)", "sof_frame", 1.0, "frame", BLUE, 0.4, 0.4),
    "mesh": (TEX + "sof_mesh_blue_ca.paa", "sof_mesh", 0.5, "mesh", "sof_mesh_blue_ca", 0.4, 0.5),
    "steel": (TEX + "sof_steel_co.paa", "sof_steel", 1.0, None, "sof_steel_co", 0.5, 0.5),
    "galva": (TEX + "sof_galva_co.paa", "sof_galva", 1.0, None, "sof_galva_co", 0.35, 0.8),
    "wood": (TEX + "sof_plywood_co.paa", "sof_wood", 1.2, None, "sof_plywood_co", 0.7, 0.0),
    "peg": (TEX + "sof_pegboard_co.paa", "sof_paint", 0.8, None, "sof_pegboard_co", 0.5, 0.2),
    "red": (TEX + "sof_red_co.paa", "sof_paint", 1.0, None, "sof_red_co", 0.3, 0.2),
    "chrome": ("#(argb,8,8,3)color(0.7,0.7,0.72,1,co)", "sof_galva", 1.0, None, (0.7, 0.7, 0.72), 0.15, 1.0),
    "rubber": ("#(argb,8,8,3)color(0.03,0.03,0.03,1,co)", "sof_matte", 1.0, None, (0.03, 0.03, 0.03), 0.8, 0.0),
    "latch": ("#(argb,8,8,3)color(0.75,0.55,0.05,1,co)", "sof_paint", 1.0, None, (0.75, 0.55, 0.05), 0.4, 0.5),
    "sign": (TEX + "sof_sign_co.paa", "sof_matte", 0.0, "sign", "sof_sign_co", 0.5, 0.0),
    "flag": (TEX + "sof_flag_co.paa", "sof_matte", 0.0, "flag", "sof_flag_co", 0.85, 0.0),
}


# ---------------------------------------------------------------------------------------------------------- builder
class Part:
    def __init__(self, verts, faces, mat, groups=(), uvs=None, shadow=True):
        self.verts, self.faces, self.mat = verts, faces, mat
        self.groups = set(groups)
        self.uvs = uvs  # liste par face de listes (u, v), sinon projection planaire
        self.shadow = shadow


class B:
    """Pièces d'un modèle, boîtes de géométrie et points mémoire, dans un repère local empilable (push/pop)."""

    def __init__(self):
        self.parts, self.geo, self.mem = [], [], []
        self.stack = [Matrix.Identity(4)]
        self.groups = []

    def push(self, loc=(0, 0, 0), rz=0.0, groups=()):
        self.stack.append(self.stack[-1] @ Matrix.Translation(loc) @ Matrix.Rotation(rz, 4, "Z"))
        self.groups.append(set(groups))

    def pop(self):
        self.stack.pop()
        self.groups.pop()

    def _g(self, groups):
        out = set(groups)
        for g in self.groups:
            out |= g
        return out

    def _t(self, v):
        return (self.stack[-1] @ Vector((*v, 1.0))).to_3d()

    def box(self, c, s, mat, groups=(), rot=None, shadow=True):
        """Pavé : c = centre de la base, s = dimensions ; rot : matrice 3x3 locale autour de la base."""
        sx, sy, sz = s
        vs = []
        for z in (0, sz):
            for x, y in ((-sx / 2, -sy / 2), (sx / 2, -sy / 2), (sx / 2, sy / 2), (-sx / 2, sy / 2)):
                p = Vector((x, y, z))
                if rot is not None:
                    p = rot @ p
                vs.append(self._t(Vector(c) + p))
        faces = [(0, 3, 2, 1), (4, 5, 6, 7), (0, 1, 5, 4), (1, 2, 6, 5), (2, 3, 7, 6), (3, 0, 4, 7)]
        self.parts.append(Part(vs, faces, mat, self._g(groups), shadow=shadow and mat not in ("mesh", "flag")))

    def beam(self, p0, p1, w, mat, groups=()):
        p0, p1 = Vector(p0), Vector(p1)
        d = p1 - p0
        L = d.length
        z = d.normalized()
        x = Vector((0, 0, 1)).cross(z)
        if x.length < 1e-6:
            x = Vector((1, 0, 0))
        x.normalize()
        y = z.cross(x)
        rot = Matrix((x, y, z)).transposed()
        self.box(tuple(p0), (w, w, L), mat, groups, rot=rot)

    def cyl(self, c, r, h, mat, groups=(), seg=12, axis="Z"):
        vs, fs = [], []
        for k in range(seg):
            a = 2 * math.pi * k / seg
            for z in (0, h):
                p = Vector((r * math.cos(a), r * math.sin(a), z))
                if axis == "X":
                    p = Vector((p.z, p.x, p.y))
                elif axis == "Y":
                    p = Vector((p.x, p.z, p.y))
                vs.append(self._t(Vector(c) + p))
        for k in range(seg):
            a, b = 2 * k, 2 * ((k + 1) % seg)
            fs.append((a, b, b + 1, a + 1))
        fs.append(tuple(2 * k for k in reversed(range(seg))))
        fs.append(tuple(2 * k + 1 for k in range(seg)))
        self.parts.append(Part(vs, fs, mat, self._g(groups)))

    def quad(self, pts, uvs, mat, groups=(), both=True):
        """Quadrilatère texturé (UV explicites) ; both : face arrière aussi (UV miroir pour une lecture à l'endroit)."""
        vs = [self._t(Vector(p)) for p in pts]
        self.parts.append(Part(vs, [(0, 1, 2, 3)], mat, self._g(groups), uvs=[uvs], shadow=False))
        if both:
            back = [uvs[1], uvs[0], uvs[3], uvs[2]]
            self.parts.append(Part([vs[1], vs[0], vs[3], vs[2]], [(0, 1, 2, 3)], mat, self._g(groups), uvs=[back], shadow=False))

    def geobox(self, c, s, groups=()):
        self.geo.append((self._t(Vector(c)), s, self.stack[-1].to_3x3(), self._g(groups)))

    def point(self, name, p):
        self.mem.append((name, self._t(Vector(p))))


# ---------------------------------------------------------------------------------------------------------- modèles
T = 0.04  # section des tubes de cage


def cage_panel(b, w=1.0, h=2.6, door=False, axis_name="door"):
    """Panneau dans le plan XZ, centré en x, posé au sol ; door : porte battante (charnière à gauche)."""
    for x in (-w / 2 + T / 2, w / 2 - T / 2):
        b.box((x, 0, 0), (T, T, h), "frame")
    b.box((0, 0, h - T), (w, T, T), "frame")
    b.geobox((0, 0, h - T), (w, 0.06, T))
    if not door:
        b.box((0, 0, 0.03), (w, T, T), "frame")
        b.box((0, 0, h * 0.55), (w - 2 * T, T, T), "frame")
        b.box((0, 0, 0.07), (w - 2 * T, 0.004, h - 0.11), "mesh")
        b.geobox((0, 0, 0), (w, 0.06, h))
        return
    dw, dh = w - 2 * T - 0.02, h - 0.32
    for x in (-w / 2 + T / 2, w / 2 - T / 2):
        b.geobox((x, 0, 0), (T, 0.06, h))
    x0 = -w / 2 + T + 0.01
    b.box((0, 0, dh + 0.06), (w - 2 * T, T, T), "frame")
    b.box((0, 0, dh + 0.1), (w - 2 * T, 0.004, h - dh - 0.14), "mesh")
    b.push((x0, 0, 0.05), 0.0, groups=(axis_name,))
    for x in (T / 2, dw - T / 2):
        b.box((x, 0, 0), (T, T, dh), "frame")
    for z in (0, dh * 0.5, dh - T):
        b.box((dw / 2, 0, z), (dw, T, T), "frame")
    b.beam((T / 2, 0, T), (dw - T / 2, 0, dh * 0.5), T * 0.8, "frame")
    b.box((dw / 2, 0, 0.02), (dw - 2 * T, 0.004, dh - 0.04), "mesh")
    b.box((dw - 0.07, -0.035, 1.0), (0.05, 0.03, 0.12), "latch")
    b.box((dw - 0.07, 0.035, 1.0), (0.05, 0.03, 0.12), "latch")
    b.geobox((dw / 2, 0, 0), (dw, 0.06, dh))
    b.pop()
    b.point(axis_name + "_axis", (x0, 0, 0.1))
    b.point(axis_name + "_axis", (x0, 0, dh))
    b.point(axis_name + "_action", (x0 + dw - 0.1, 0, 1.0))


def m_cage_panel(b):
    cage_panel(b)


def m_cage_door(b):
    cage_panel(b, door=True)


def m_cage_4x3(b, w=4, d=3, h=2.6):
    for i in range(w):
        x = -w / 2 + 0.5 + i
        b.push((x, d / 2, 0)); cage_panel(b, h=h); b.pop()
        b.push((x, -d / 2, 0)); cage_panel(b, h=h, door=(i == w - 2)); b.pop()
    for j in range(d):
        y = -d / 2 + 0.5 + j
        for s in (-1, 1):
            b.push((s * w / 2, y, 0), math.pi / 2); cage_panel(b, h=h); b.pop()
    # toit : grillage sur lisses
    for j in range(d + 1):
        b.box((0, -d / 2 + j, h), (w, T, T), "frame")
    b.box((0, 0, h + T - 0.004), (w, d, 0.004), "mesh")
    b.geobox((0, 0, h), (w, d, 0.06))


def m_mesh_locker(b, W=1.0, D=0.6, H=2.0):
    st = "steel"
    for sx in (-1, 1):
        for sy in (-1, 1):
            b.box((sx * (W / 2 - 0.025), sy * (D / 2 - 0.025), 0), (0.05, 0.05, H), "frame")
            b.box((sx * (W / 2 - 0.025), sy * (D / 2 - 0.025), -0.0), (0.06, 0.06, 0.05), "rubber")
    for z in (0.05, H - 0.04):
        b.box((0, 0, z), (W, D, 0.04), "frame")
    for z in (0.72, 1.36):
        b.box((0, 0.02, z), (W - 0.1, D - 0.1, 0.02), "galva")
    b.box((0, D / 2 - 0.005, 0.09), (W - 0.1, 0.006, H - 0.13), "mesh")
    for sx in (-1, 1):
        b.box((sx * (W / 2 - 0.005), 0, 0.09), (0.006, D - 0.1, H - 0.13), "mesh")
    b.geobox((0, 0.03, 0), (W, D - 0.06, H))
    dw = W / 2 - 0.03
    for s, name in ((-1, "door_l"), (1, "door_r")):
        hx = s * (W / 2 - 0.02)
        b.push((hx, -D / 2, 0.09), 0.0, groups=(name,))
        ox = -s * dw / 2
        for xx in (ox - dw / 2 + 0.02, ox + dw / 2 - 0.02):
            b.box((xx, 0, 0), (0.04, 0.03, H - 0.14), "frame")
        for zz in (0, (H - 0.14) / 2, H - 0.18):
            b.box((ox, 0, zz), (dw, 0.03, 0.04), "frame")
        b.box((ox, 0, 0.02), (dw - 0.04, 0.006, H - 0.18), "mesh")
        b.box((-s * (dw - 0.05), -0.03, 0.85), (0.02, 0.03, 0.2), "chrome")
        b.geobox((ox, 0, 0), (dw, 0.04, H - 0.14))
        b.pop()
        b.point(name + "_axis", (hx, -D / 2, 0.2))
        b.point(name + "_axis", (hx, -D / 2, H - 0.2))
    b.point("door_action", (0, -D / 2 - 0.1, 1.0))


def team_bay(b, W=1.0, D=0.6, H=2.4, side=True):
    if side:
        b.box((-W / 2 + 0.01, 0, 0), (0.02, D, H), "wood")
    b.box((W / 2 - 0.01, 0, 0), (0.02, D, H), "wood")
    b.box((0, D / 2 - 0.01, 0), (W, 0.02, H), "wood")
    for z in (0.0, 0.5, 1.9, H - 0.02):
        b.box((0, 0, z), (W, D, 0.02), "wood")
    b.box((0, -D / 2 + 0.01, 0.42), (W - 0.04, 0.02, 0.08), "wood")
    b.cyl((-W / 2 + 0.02, 0.08, 1.78), 0.016, W - 0.04, "galva", axis="X")
    for x in (-0.25, 0.0, 0.25):
        b.cyl((x, D / 2 - 0.02, 1.6), 0.008, 0.08, "galva", axis="Y")
        b.box((x, D / 2 - 0.1, 1.55), (0.016, 0.016, 0.06), "galva")
    b.geobox((0, D / 2 - 0.01, 0), (W, 0.02, H))
    b.geobox((W / 2 - 0.01, 0, 0), (0.02, D, H))
    if side:
        b.geobox((-W / 2 + 0.01, 0, 0), (0.02, D, H))
    for z in (0.0, 0.5, 1.9, H - 0.02):
        b.geobox((0, 0, z), (W, D, 0.02))


def m_team_bay(b):
    team_bay(b)


def m_team_bay_x5(b):
    for k in range(5):
        b.push((-2.0 + k, 0, 0)); team_bay(b, side=(k == 0)); b.pop()


def m_gun_rack(b, n=8):
    L = n * 0.14 + 0.2
    b.box((0, 0, 0), (L, 0.4, 0.06), "wood")
    b.box((0, 0.18, 0.06), (L, 0.04, 1.1), "wood")
    for x in (-L / 2 + 0.02, L / 2 - 0.02):
        b.box((x, 0.0, 0.06), (0.04, 0.36, 1.1), "wood")
    # barre haute à encoches et sabots de crosse
    b.box((0, 0.11, 0.86), (L, 0.04, 0.05), "wood")
    for i in range(n + 1):
        x = -L / 2 + 0.1 + i * 0.14 - 0.07
        b.box((x, 0.05, 0.86), (0.04, 0.1, 0.05), "wood")
        if i < n:
            b.box((x + 0.07, -0.02, 0.06), (0.1, 0.16, 0.035), "rubber")
    b.box((0, 0.12, 0.6), (L - 0.06, 0.02, 0.02), "steel")
    b.geobox((0, 0, 0), (L, 0.4, 0.06))
    b.geobox((0, 0.18, 0.06), (L, 0.04, 1.1))
    for x in (-L / 2 + 0.02, L / 2 - 0.02):
        b.geobox((x, 0.0, 0.06), (0.04, 0.36, 1.1))


def m_workbench(b, w=2.2, d=0.8, h=0.92):
    for sx in (-1, 1):
        for sy in (-1, 1):
            b.box((sx * (w / 2 - 0.05), sy * (d / 2 - 0.05), 0), (0.05, 0.05, h - 0.05), "steel")
    b.box((0, 0, h - 0.05), (w, d, 0.05), "steel")
    b.box((0, 0, 0.18), (w - 0.1, d - 0.1, 0.02), "galva")
    for sx in (-1, 1):
        b.box((sx * (w / 2 - 0.05), d / 2 - 0.03, h), (0.05, 0.05, 1.2), "steel")
    b.box((0, d / 2 - 0.03, h + 0.05), (w - 0.1, 0.02, 1.0), "peg")
    b.box((0, d / 2 - 0.15, h + 1.12), (w, 0.28, 0.03), "galva")
    # étau
    b.box((-w / 2 + 0.3, -d / 2 + 0.15, h), (0.18, 0.14, 0.08), "frame")
    b.box((-w / 2 + 0.25, -d / 2 + 0.15, h + 0.08), (0.05, 0.16, 0.08), "frame")
    b.box((-w / 2 + 0.35, -d / 2 + 0.15, h + 0.08), (0.05, 0.16, 0.08), "frame")
    b.cyl((-w / 2 + 0.3, -d / 2 - 0.05, h + 0.12), 0.01, 0.3, "chrome", axis="Y")
    b.geobox((0, 0, 0), (w, d, h))
    b.geobox((0, d / 2 - 0.03, h), (w, 0.06, 1.2))


def m_tool_chest(b):
    b.box((0, 0, 0.1), (0.68, 0.46, 1.0), "red")
    for i in range(7):
        z = 0.16 + i * 0.13
        b.box((0, -0.235, z), (0.62, 0.012, 0.11), "red")
        b.box((0, -0.25, z + 0.08), (0.5, 0.02, 0.012), "chrome")
    b.box((0, 0, 1.1), (0.7, 0.48, 0.02), "rubber")
    for sx in (-1, 1):
        for sy in (-1, 1):
            b.cyl((sx * 0.29, sy * 0.15 - 0.025, 0.05), 0.05, 0.05, "rubber", axis="Y")
    b.geobox((0, 0, 0), (0.7, 0.48, 1.12))


def m_shelf(b, w=1.2, d=0.5, h=2.0, levels=4):
    for sx in (-1, 1):
        for sy in (-1, 1):
            b.box((sx * (w / 2 - 0.02), sy * (d / 2 - 0.02), 0), (0.04, 0.04, h), "steel")
    for i in range(levels):
        z = 0.1 + i * (h - 0.16) / (levels - 1)
        b.box((0, 0, z), (w, d, 0.025), "galva")
        b.geobox((0, 0, z), (w, d, 0.03))
    for sx in (-1, 1):
        b.geobox((sx * (w / 2 - 0.02), 0, 0), (0.04, d, h))


def sign_face(b, w, h, z0, y=-0.012, frame=True):
    b.quad([(-w / 2, y, z0), (w / 2, y, z0), (w / 2, y, z0 + h), (-w / 2, y, z0 + h)],
           [(0, 0), (1, 0), (1, 1), (0, 1)], "sign", both=False)
    b.box((0, 0, z0), (w, 0.02, h), "steel")
    if frame:
        for z in (z0 - 0.01, z0 + h - 0.01):
            b.box((0, -0.004, z), (w + 0.02, 0.03, 0.02), "galva")
        for x in (-w / 2, w / 2):
            b.box((x, -0.004, z0), (0.02, 0.03, h), "galva")


def m_sign_large(b):
    sign_face(b, 1.6, 0.8, 1.2)
    b.geobox((0, 0, 1.2), (1.62, 0.03, 0.82))


def m_sign_small(b):
    sign_face(b, 0.6, 0.3, 1.5, frame=False)
    b.geobox((0, 0, 1.5), (0.6, 0.02, 0.3))


def m_sign_stand(b):
    sign_face(b, 1.6, 0.8, 1.1)
    for x in (-0.7, 0.7):
        b.box((x, 0.03, 0), (0.06, 0.06, 1.95), "steel")
        b.box((x, 0.03, 0), (0.12, 0.5, 0.03), "steel")
    b.geobox((0, 0, 1.1), (1.62, 0.03, 0.82))
    for x in (-0.7, 0.7):
        b.geobox((x, 0.03, 0), (0.06, 0.06, 1.95))


def m_wall_flag(b, w=1.5, h=0.75, nx=12, nz=6):
    """Drapeau tendu sur une barre fixée au mur à 2,4 m (posé contre un mur côté +y)."""
    top = 2.4
    b.cyl((-w / 2 - 0.08, -0.06, top + 0.01), 0.012, w + 0.16, "chrome", axis="X")
    for x in (-w / 2 - 0.04, w / 2 + 0.04):
        b.box((x, -0.03, top), (0.02, 0.06, 0.03), "steel")
    for j in range(nz):
        for i in range(nx):
            def p(ii, jj):
                x = -w / 2 + w * ii / nx
                z = top - h * jj / nz
                y = -0.06 + 0.025 * math.sin(ii / nx * 3 * math.pi) * (0.3 + 0.7 * jj / nz)
                return (x, y, z)
            u0, u1 = i / nx, (i + 1) / nx
            v0, v1 = 1 - j / nz, 1 - (j + 1) / nz
            b.quad([p(i, j + 1), p(i + 1, j + 1), p(i + 1, j), p(i, j)], [(u0, v1), (u1, v1), (u1, v0), (u0, v0)], "flag", both=True)
    b.geobox((0, -0.05, top - h), (w, 0.04, h))


MODELS = {
    "sof_cage_panel": (m_cage_panel, 120, ("frame", "mesh")),
    "sof_cage_door": (m_cage_door, 120, ("frame", "mesh")),
    "sof_cage_4x3": (m_cage_4x3, 900, ("frame", "mesh")),
    "sof_mesh_locker": (m_mesh_locker, 150, ("frame", "mesh")),
    "sof_team_bay": (m_team_bay, 90, ()),
    "sof_team_bay_x5": (m_team_bay_x5, 400, ()),
    "sof_gun_rack": (m_gun_rack, 60, ()),
    "sof_workbench": (m_workbench, 140, ()),
    "sof_tool_chest": (m_tool_chest, 120, ()),
    "sof_shelf": (m_shelf, 60, ()),
    "sof_sign_large": (m_sign_large, 15, ("sign",)),
    "sof_sign_small": (m_sign_small, 5, ("sign",)),
    "sof_sign_stand": (m_sign_stand, 40, ("sign",)),
    "sof_wall_flag": (m_wall_flag, 5, ("flag",)),
}


# ---------------------------------------------------------------------------------------------------------- export
def project_uv(vs, scale):
    n = Vector()
    for i in range(len(vs)):
        a, c = vs[i], vs[(i + 1) % len(vs)]
        n += Vector(((a.y - c.y) * (a.z + c.z), (a.z - c.z) * (a.x + c.x), (a.x - c.x) * (a.y + c.y)))
    ax = max(range(3), key=lambda i: abs(n[i]))
    if ax == 0:
        return [(v.y / scale, v.z / scale) for v in vs]
    if ax == 1:
        return [(v.x / scale, v.z / scale) for v in vs]
    return [(v.x / scale, v.y / scale) for v in vs]


def make_mesh(name, parts, mats, sections=(), with_uv=True, shadow=False):
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap") if with_uv else None
    dl = bm.verts.layers.deform.verify()
    keys = []
    gnames = []
    sel = [p for p in parts if (p.shadow or not shadow)]
    for p in sel:
        if p.mat not in keys:
            keys.append(p.mat)
        for g in sorted(p.groups | ({MATS[p.mat][3]} if MATS[p.mat][3] and MATS[p.mat][3] in sections else set())):
            if g not in gnames:
                gnames.append(g)
    for p in sel:
        groups = p.groups | ({MATS[p.mat][3]} if MATS[p.mat][3] and MATS[p.mat][3] in sections else set())
        bv = [bm.verts.new(v) for v in p.verts]
        for v in bv:
            for g in groups:
                v[dl][gnames.index(g)] = 1.0
        for fi, f in enumerate(p.faces):
            try:
                face = bm.faces.new([bv[i] for i in f])
            except ValueError:
                continue
            face.material_index = keys.index(p.mat)
            if uvl is not None:
                uvs = p.uvs[fi] if p.uvs else project_uv([p.verts[i] for i in f], MATS[p.mat][2] or 1.0)
                for loop, uv in zip(face.loops, uvs):
                    loop[uvl].uv = uv
    bmesh.ops.triangulate(bm, faces=[f for f in bm.faces if len(f.verts) > 4], ngon_method="EAR_CLIP")
    bm.to_mesh(me)
    bm.free()
    for k in keys:
        me.materials.append(mats[k])
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    for g in gnames:
        ob.vertex_groups.new(name=g)
    return ob


def geo_mesh(name, boxes, mass):
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    dl = bm.verts.layers.deform.verify()
    gnames = sorted({g for *_x, gs in boxes for g in gs})
    for c, s, rot, groups in boxes:
        sx, sy, sz = s
        vs = []
        for z in (0, sz):
            for x, y in ((-sx / 2, -sy / 2), (sx / 2, -sy / 2), (sx / 2, sy / 2), (-sx / 2, sy / 2)):
                vs.append(bm.verts.new(c + rot @ Vector((x, y, z))))
        for v in vs:
            for g in groups:
                v[dl][gnames.index(g)] = 1.0
        for f in [(0, 3, 2, 1), (4, 5, 6, 7), (0, 1, 5, 4), (1, 2, 6, 5), (2, 3, 7, 6), (3, 0, 4, 7)]:
            bm.faces.new([vs[i] for i in f])
    bmesh.ops.triangulate(bm, faces=bm.faces)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    for g in gnames:
        ob.vertex_groups.new(name=g)
    layer = me.attributes.new("a3ob_mass", "FLOAT", "POINT")
    for i in range(len(me.vertices)):
        layer.data[i].value = mass / len(me.vertices)
    return ob


def mem_mesh(name, points):
    me = bpy.data.meshes.new(name)
    me.from_pydata([tuple(p) for _n, p in points], [], [])
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    names = []
    for n, _p in points:
        if n not in names:
            names.append(n)
    for n in names:
        g = ob.vertex_groups.new(name=n)
        g.add([i for i, (m, _p) in enumerate(points) if m == n], 1.0, "REPLACE")
    return ob


def set_lod(ob, lod, res=0, props=()):
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = str(lod)
    p.resolution = res
    for k, v in props:
        it = p.properties.add()
        it.name, it.value = k, v
    return ob


def a3_materials():
    out = {}
    for k, (tex, rv, *_r) in MATS.items():
        m = bpy.data.materials.new("a3_" + k)
        p = m.a3ob_properties_material
        if tex.startswith("#"):
            p.texture_type = "CUSTOM"
            p.color_raw = tex
        else:
            p.texture_type = "TEX"
            p.texture_path = tex
        p.material_path = TEX + rv + ".rvmat"
        out[k] = m
    return out


def clear_scene():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)
    for me in list(bpy.data.meshes):
        bpy.data.meshes.remove(me)


def export_model(name, mats):
    fn, mass, sections = MODELS[name]
    clear_scene()
    b = B()
    fn(b)
    vis = set_lod(make_mesh(name + "_lod1", b.parts, mats, sections), 0, 1)
    shadow = make_mesh(name + "_shadow", b.parts, mats, sections, with_uv=False, shadow=True)
    shadow.data.materials.clear()
    set_lod(shadow, 4, 0)
    geo = geo_mesh(name + "_geo", b.geo, mass)
    set_lod(geo, 6, 0, [("autocenter", "0"), ("class", "house"), ("canocclude", "0")])
    fire = geo_mesh(name + "_fire", b.geo, mass)
    set_lod(fire, 15, 0)
    if b.mem:
        set_lod(mem_mesh(name + "_mem", b.mem), 9, 0)
    out = os.path.join(DATA, name + ".p3d")
    bpy.context.view_layer.objects.active = vis
    res = bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    print("export", res, out, len(vis.data.polygons), "faces")
    return b


# ---------------------------------------------------------------------------------------------------------- vignettes
def preview_material(k):
    tex, _rv, _s, _sel, look, rough, metal = MATS[k]
    m = bpy.data.materials.new("pv_" + k)
    m.use_nodes = True
    nt, n = m.node_tree, m.node_tree.nodes
    bsdf = n["Principled BSDF"]
    bsdf.inputs["Roughness"].default_value = rough
    bsdf.inputs["Metallic"].default_value = metal
    if isinstance(look, tuple):
        bsdf.inputs["Base Color"].default_value = (*look, 1)
    else:
        img = n.new("ShaderNodeTexImage")
        img.image = bpy.data.images.load(os.path.join(RENDER, look + ".png"))
        nt.links.new(img.outputs["Color"], bsdf.inputs["Base Color"])
        if look.endswith("_ca"):
            nt.links.new(img.outputs["Alpha"], bsdf.inputs["Alpha"])
    return m


def previews(models):
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    sc.cycles.transparent_max_bounces = 48
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.exposure = -0.4
    sc.render.resolution_x, sc.render.resolution_y = 455, 256
    sc.render.image_settings.file_format = "JPEG"
    sc.render.image_settings.quality = 90
    world = bpy.data.worlds.new("w")
    sc.world = world
    world.use_nodes = True
    world.node_tree.nodes["Background"].inputs["Color"].default_value = (0.42, 0.44, 0.46, 1)
    world.node_tree.nodes["Background"].inputs["Strength"].default_value = 0.9
    sun = bpy.data.lights.new("sun", "SUN")
    sun.energy = 3.0
    so = bpy.data.objects.new("sun", sun)
    so.rotation_euler = (math.radians(40), 0, math.radians(-35))
    sc.collection.objects.link(so)
    pm = {k: preview_material(k) for k in MATS}
    me = bpy.data.meshes.new("floor")
    me.from_pydata([(-30, -30, 0), (30, -30, 0), (30, 30, 0), (-30, 30, 0)], [], [(0, 1, 2, 3)])
    fl = bpy.data.objects.new("floor", me)
    sc.collection.objects.link(fl)
    fm = bpy.data.materials.new("floor")
    fm.use_nodes = True
    fm.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value = (0.2, 0.2, 0.19, 1)
    me.materials.append(fm)
    cd = bpy.data.cameras.new("cam")
    cam = bpy.data.objects.new("cam", cd)
    sc.collection.objects.link(cam)
    sc.camera = cam
    for name in models:
        for ob in [o for o in sc.objects if o.name.startswith("pv_obj")]:
            bpy.data.objects.remove(ob, do_unlink=True)
        b = B()
        MODELS[name][0](b)
        ob = make_mesh("pv_obj", b.parts, pm, ())
        bb = [ob.matrix_world @ Vector(c) for c in ob.bound_box]
        lo = Vector((min(v.x for v in bb), min(v.y for v in bb), min(v.z for v in bb)))
        hi = Vector((max(v.x for v in bb), max(v.y for v in bb), max(v.z for v in bb)))
        ctr = (lo + hi) / 2
        r = (hi - lo).length / 2
        cd.lens = 35
        dist = r / math.tan(math.atan(36 / 2 / 35 * 256 / 455)) * 1.05
        dirv = Vector((0.55, -1.0, 0.42)).normalized()
        cam.location = ctr + dirv * dist
        cam.rotation_euler = (ctr - cam.location).to_track_quat("-Z", "Y").to_euler()
        sc.render.filepath = os.path.join(DATA, name + "_preview.jpg")
        bpy.ops.render.render(write_still=True)
        print("preview", sc.render.filepath)


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    only = os.environ.get("ONLY")
    names = [n for n in MODELS if not only or only in n]
    if os.environ.get("NO_EXPORT") != "1":
        mats = a3_materials()
        for name in names:
            export_model(name, mats)
    if os.environ.get("NO_PREVIEW") != "1":
        previews(names)


if __name__ == "__main__":
    main()
