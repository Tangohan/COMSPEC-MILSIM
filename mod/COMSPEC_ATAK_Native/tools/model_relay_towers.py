"""Pylônes relais du réseau ATAK (relais posés dans Eden / Zeus, fn_relayTower) : cinq modèles et leurs ruines.

  relay_tower_30  mât treillis carré 30 m peint vert olive, haubané à deux niveaux (ancrages béton, ridoirs), deux
                  couronnes de panneaux, RRU, parabole, balisage, paratonnerre
  relay_tower_40  tour autostable triangulaire 40 m galvanisée, plate-forme de tête, tête à bras (6 panneaux), deux
                  faisceaux hertziens, fouets, feux à mi-hauteur, enclos grillagé (2 armoires, climatisation)
  relay_tower_50  pylône élancé carré 50 m galvanisé, trois paliers à caillebotis et garde-corps, deux couronnes,
                  deux paraboles, feux de tête et de mi-hauteur, enclos grillagé (2 armoires, climatisation)
  relay_tower_m20 monopole 20 m galvanisé en tronçons emboîtés, échelons, trois secteurs serrés en tête, parabole,
                  balisage ; armoires et climatisation sous filet de camouflage, dans un enclos
  relay_tower_m12 mât monopole léger 12 m haubané, une couronne de trois panneaux, balisage, petite armoire

Chaque modèle : dalle béton du site, massifs, armoires vert olive (panneau danger RF, voyant, plinthe, ouïes), plaque
d'identification du site, goussets aux nœuds et échelons (LOD 1), LOD visuelles 1 / 4 / 12 / 30 (détail
décroissant), ombres 0 (tirée de la LOD 4 : échelle, chemins de câbles, garde-corps, haubans) et 10 (une enveloppe
convexe fermée par pièce, rentrée de 6 mm : pas de bandes ni d'ombre propre ; panneaux, filet et grillage exclus),
géométrie (enveloppe de chaque pièce du pylône simplifié, grillage épaissi, masse, autocenter=0, map=transmitter),
géométrie de tir (treillis presque complet ; les balles traversent grillage et filet), géométrie de vue (le plein
seulement), chemin praticable (dalle, massifs, paliers) et mémoire (light_top, light_mid1/2 : feux d'obstacle rouges
clignotants la nuit, MarkerLights de config.cpp). Boîtes et cornières à facettes, tubes lissés.
Ruine (relay_tower_XX_ruin.p3d) : même pylône plié au-dessus d'un tronçon resté debout, couché sur le sol, équipements
arrachés et éparpillés, haubans rompus (DestructBuilding, config.cpp).

Textures raccordables : gen_tower_textures.py (projection monde, 1 m = 1 tuile). Aperçus Cycles : tools/render/tower_*.png
(dont tower_family_night.png, vue de nuit avec les feux).

    python3 gen_tower_textures.py && python3 model_relay_towers.py
    (NO_PREVIEW=1 pour sauter les rendus ; NO_EXPORT=1 ; ONLY=m20 ; RENDER_SAMPLES=n)
    puis  hemtt utils paa convert render/tower_X_co.png ../Sources/addons/towers/data/tower_X_co.paa  (galva, dark, radome,
    box, concrete, grating, cable, olive, sign ; net et fence en _ca) — hemtt ne remplace pas un PAA existant : l'effacer avant.
Extension Blender « Arma 3 Object Builder » requise (voir model_vest_phone.py).
"""
import math
import os
import random
import sys

import bpy  # noqa: I001 (bmesh et mathutils après bpy)
import addon_utils
import bmesh
from mathutils import Matrix, Vector

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "towers", "data")
RENDER = os.path.join(HERE, "render")
TEX = "z\\comspec_atak_native\\addons\\towers\\data\\"
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "64"))

# Matières : texture, rvmat, échelle de projection (m par tuile), réglages Cycles de l'aperçu (rugosité, métal).
MATS = {
    "galva": ("tower_galva", "tower_galva", 1.0, 0.38, 0.85),
    "dark": ("tower_dark", "tower_dark", 1.0, 0.55, 0.35),
    "radome": ("tower_radome", "tower_radome", 1.5, 0.5, 0.0),
    "box": ("tower_box", "tower_box", 1.0, 0.45, 0.25),
    "concrete": ("tower_concrete", "tower_concrete", 2.0, 0.9, 0.0),
    "grating": ("tower_grating", "tower_grating", 1.0, 0.45, 0.7),
    "cable": ("tower_cable", "tower_matte", 1.0, 0.6, 0.0),
    "light": ("tower_light", "tower_glow", 1.0, 0.3, 0.0),
    "olive": ("tower_olive", "tower_olive", 1.0, 0.5, 0.3),
    "sign": ("tower_sign", "tower_sign", 1.0, 0.45, 0.0),
    "net": ("tower_net", "tower_net", 2.0, 0.85, 0.0),
    "fence": ("tower_fence", "tower_net", 1.0, 0.5, 0.6),
}
# Matières sans volume propre (panneaux collés, filet) : ni ombre, ni collision, ni tir.
THIN = ("sign", "net", "fence")


# --------------------------------------------------------------------------------------------------------------------
# Accumulateur de géométrie : chaque pièce = sommets, faces (quads / n-gones), UV par coin, matière, nature
# ("struct" : se plie avec le pylône dans la ruine ; "equip" : pièce rigide arrachée ; "ground" : reste en place).
# --------------------------------------------------------------------------------------------------------------------
class Part:
    # smooth : nombre de faces lissées en tête de liste (flancs des tubes et des révolutions), le reste à arêtes vives.
    # uvs : UV imposées par face (panneaux), sinon projection monde.
    __slots__ = ("verts", "faces", "mat", "kind", "gid", "box", "smooth", "uvs")

    def __init__(self, verts, faces, mat, kind, gid, box=False, smooth=0, uvs=None):
        self.verts, self.faces, self.mat, self.kind, self.gid, self.box = verts, faces, mat, kind, gid, box
        self.smooth, self.uvs = smooth, uvs


class Builder:
    def __init__(self, detail, split=0.0, pieces=False):
        self.detail = detail      # 0 = complet ... 3 = silhouette
        self.split = split        # longueur max d'un tronçon (ruine), 0 = pièces d'un seul tenant
        self.pieces = pieces      # ruine (ombres, géométries) : chaque tronçon est une pièce fermée à part
        self.parts = []
        self.gid = 0
        self.kind = "struct"
        self.mat = "galva"
        self.mem = []             # points mémoire (nom, position) : feux de balisage

    @property
    def intact(self):
        """Modèle debout (pas la ruine) : haubans et feux n'existent que sur lui."""
        return not self.split and not self.pieces

    def group(self, kind):
        """Nouvelle pièce rigide (equip) ou retour à la structure."""
        self.gid += 1
        self.kind = kind
        return self.gid

    def add(self, verts, faces, mat=None, box=False, smooth=0, uvs=None):
        self.parts.append(Part(verts, faces, mat or self.mat, self.kind, self.gid if self.kind != "struct" else 0, box, smooth, uvs))

    def quad(self, c, nrm, up, w, h, mat, uv=(0, 0, 1, 1), both=False):
        """Quad (panneau, filet) centré en c, face vers nrm ; uv : rectangle de la texture."""
        c, nrm, up = Vector(c), Vector(nrm).normalized(), Vector(up).normalized()
        rt = up.cross(nrm).normalized()
        vs = [c - rt * w / 2 - up * h / 2, c + rt * w / 2 - up * h / 2, c + rt * w / 2 + up * h / 2, c - rt * w / 2 + up * h / 2]
        u0, v0, u1, v1 = uv
        uvs = [(u0, v0), (u1, v0), (u1, v1), (u0, v1)]
        self.add(vs, [(0, 1, 2, 3)], mat, uvs=[uvs])
        if both:
            self.add(list(reversed(vs)), [(0, 1, 2, 3)], mat, uvs=[list(reversed(uvs))])

    # --- Primitives ---
    def prism(self, p0, p1, prof, a, b=None, mat=None, caps=True, box=False, smooth=False):
        """Extrusion d'un contour 2D (x selon a, y selon b) de p0 à p1."""
        p0, p1 = Vector(p0), Vector(p1)
        d = p1 - p0
        if d.length < 1e-6:
            return
        d.normalize()
        a = Vector(a)
        a = (a - d * d.dot(a))
        if a.length < 1e-6:
            a = d.orthogonal()
        a.normalize()
        if b is None:
            b = d.cross(a)
        else:
            b = Vector(b)
            b = b - d * d.dot(b) - a * a.dot(b)
            if b.length < 1e-6:
                b = d.cross(a)
            b.normalize()
        n = len(prof)
        # Ruine : longues pièces recoupées tous les self.split m pour qu'elles se plient avec le pylône.
        segs = max(1, math.ceil((p1 - p0).length / self.split)) if self.split else 1
        if self.pieces and segs > 1:
            for k in range(segs):
                self.prism(p0.lerp(p1, k / segs), p0.lerp(p1, (k + 1) / segs), prof, a, b, mat, True, box, smooth)
            return
        verts, faces = [], []
        for k in range(segs + 1):
            c = p0.lerp(p1, k / segs)
            verts += [c + a * x + b * y for x, y in prof]
        for k in range(segs):
            o = k * n
            faces += [(o + i, o + (i + 1) % n, o + n + (i + 1) % n, o + n + i) for i in range(n)]
        sides = len(faces) if smooth else 0
        if caps:
            faces.append(tuple(reversed(range(n))))
            faces.append(tuple(range(segs * n, (segs + 1) * n)))
        self.add(verts, faces, mat, box, sides)

    def beam(self, p0, p1, w, h, a=(0, 0, 1), mat=None):
        self.prism(p0, p1, [(-w / 2, -h / 2), (w / 2, -h / 2), (w / 2, h / 2), (-w / 2, h / 2)], a, None, mat, box=True)

    def tube(self, p0, p1, r, n=8, mat=None, a=(0, 0, 1)):
        if self.detail >= 2:
            n = max(4, n // 2)
        prof = [(r * math.cos(2 * math.pi * i / n), r * math.sin(2 * math.pi * i / n)) for i in range(n)]
        if Vector(a).cross(Vector(p1) - Vector(p0)).length < 1e-6:
            a = (1, 0, 0)
        self.prism(p0, p1, prof, a, None, mat, smooth=n >= 6)

    def angle(self, p0, p1, s, t, a, b, mat=None):
        """Cornière à ailes égales s, épaisseur t, coin sur la ligne p0-p1, ailes selon +a et +b."""
        if self.detail >= 2:
            self.prism(p0, p1, [(0, 0), (s * 0.7, 0), (s * 0.7, s * 0.7), (0, s * 0.7)], a, b, mat)
            return
        self.prism(p0, p1, [(0, 0), (s, 0), (s, t), (t, t), (t, s), (0, s)], a, b, mat)

    def cuboid(self, c, size, rot=None, mat=None):
        """Boîte centrée en c, dimensions (x, y, z) dans le repère tourné rot (Matrix 3x3)."""
        sx, sy, sz = (v / 2 for v in size)
        rot = rot or Matrix.Identity(3)
        c = Vector(c)
        pts = [(-sx, -sy, -sz), (sx, -sy, -sz), (sx, sy, -sz), (-sx, sy, -sz), (-sx, -sy, sz), (sx, -sy, sz), (sx, sy, sz), (-sx, sy, sz)]
        verts = [c + rot @ Vector(p) for p in pts]
        faces = [(0, 3, 2, 1), (4, 5, 6, 7), (0, 1, 5, 4), (1, 2, 6, 5), (2, 3, 7, 6), (3, 0, 4, 7)]
        self.add(verts, faces, mat, True)

    def lathe(self, base, axis, prof, n=16, mat=None):
        """Révolution d'un profil [(rayon, hauteur)] autour de l'axe (de base vers axis)."""
        if self.detail >= 2:
            n = max(6, n // 2)
        base, ax = Vector(base), Vector(axis).normalized()
        u = ax.orthogonal().normalized()
        v = ax.cross(u)
        verts, faces = [], []
        m = len(prof)
        for i in range(n):
            t = 2 * math.pi * i / n
            dirv = u * math.cos(t) + v * math.sin(t)
            for r, h in prof:
                verts.append(base + ax * h + dirv * r)
        for i in range(n):
            j = (i + 1) % n
            for k in range(m - 1):
                faces.append((i * m + k, j * m + k, j * m + k + 1, i * m + k + 1))
        sides = len(faces)
        # Fermeture des extrémités de rayon non nul.
        if prof[0][0] > 1e-4:
            faces.append(tuple(i * m for i in range(n)))
        if prof[-1][0] > 1e-4:
            faces.append(tuple(i * m + m - 1 for i in reversed(range(n))))
        self.add(verts, faces, mat, smooth=sides)


def rot_z(deg):
    return Matrix.Rotation(math.radians(deg), 3, "Z")


# --------------------------------------------------------------------------------------------------------------------
# Treillis : jambes, membrures horizontales, croix de Saint-André, entretoises de plan.
# --------------------------------------------------------------------------------------------------------------------
class Lattice:
    def __init__(self, legs, widths, panels, leg_kind, leg_size, brace, mat, bracing="X"):
        self.n = legs                      # 3 ou 4
        self.widths = widths               # [(z, largeur de face)] linéaire par morceaux
        self.panels = panels               # cotes des membrures horizontales
        self.leg_kind, self.leg_size, self.brace, self.mat, self.bracing = leg_kind, leg_size, brace, mat, bracing
        self.phi = [math.radians((90 if legs == 3 else 45) + i * 360 / legs) for i in range(legs)]

    def width(self, z):
        w = self.widths
        if z <= w[0][0]:
            return w[0][1]
        for (z0, w0), (z1, w1) in zip(w, w[1:]):
            if z <= z1:
                return w0 + (w1 - w0) * (z - z0) / (z1 - z0)
        return w[-1][1]

    def radius(self, z):
        w = self.width(z)
        return w / math.sqrt(3) if self.n == 3 else w / math.sqrt(2)

    def corner(self, i, z):
        r = self.radius(z)
        p = self.phi[i % self.n]
        return Vector((r * math.cos(p), r * math.sin(p), z))

    def face_normal(self, i):
        p = (self.phi[i % self.n] + self.phi[(i + 1) % self.n]) / 2
        if self.n == 4 or True:
            p = math.atan2(math.sin(self.phi[i % self.n]) + math.sin(self.phi[(i + 1) % self.n]),
                           math.cos(self.phi[i % self.n]) + math.cos(self.phi[(i + 1) % self.n]))
        return Vector((math.cos(p), math.sin(p), 0))

    def build(self, B):
        B.kind, B.mat = "struct", self.mat
        top = self.panels[-1]
        breaks = [z for z, _ in self.widths if 0 < z < top]
        zs = [0.0] + breaks + [top]
        # Jambes : un tronçon droit entre deux cassures de la conicité.
        for i in range(self.n):
            p = self.phi[i]
            out = Vector((math.cos(p), math.sin(p), 0))
            for z0, z1 in zip(zs, zs[1:]):
                c0, c1 = self.corner(i, z0), self.corner(i, z1)
                if self.leg_kind == "tube":
                    r = self.leg_size * (1.0 - 0.35 * z0 / top)
                    B.tube(c0, c1, r, 10)
                else:
                    s = self.leg_size
                    # Cornière : coin vers l'extérieur, ailes le long des deux faces voisines.
                    na, nb = self.corner(i - 1, z0) - c0, self.corner(i + 1, z0) - c0
                    B.angle(c0 + out * 0.01, c1 + out * 0.01, s, s * 0.1, na, nb)
        # Goussets boulonnés aux nœuds (de près) : une platine par face et par jambe à chaque membrure.
        if B.detail == 0:
            for z in self.panels[1:-1]:
                for i in range(self.n):
                    c = self.corner(i, z)
                    for j in (i - 1, i + 1):
                        al = self.corner(j, z) - c
                        al.z = 0
                        al.normalize()
                        nf = self.face_normal(i if j == i + 1 else i - 1)
                        rot = Matrix((al, nf, Vector((0, 0, 1)))).transposed()
                        B.cuboid(c + al * (0.07 + self.leg_size * 0.5) - nf * 0.012, (0.16, 0.008, 0.2), rot, self.mat)
        s = self.brace
        step = 1 if B.detail <= 1 else (2 if B.detail == 2 else 4)
        for k, (z0, z1) in enumerate(zip(self.panels, self.panels[1:])):
            for i in range(self.n):
                nf = self.face_normal(i)
                a0, b0, a1, b1 = self.corner(i, z0), self.corner(i + 1, z0), self.corner(i, z1), self.corner(i + 1, z1)
                inset = lambda p, q, t=0.04: p + (q - p).normalized() * t
                if k % step == 0 or z0 == 0:
                    if B.detail <= 2 or k % 8 == 0:
                        B.angle(inset(a0, b0), inset(b0, a0), s, s * 0.1, -nf, Vector((0, 0, 1)))
                if B.detail >= 3 and k % 2:
                    continue
                if self.bracing == "X":
                    B.angle(inset(a0, b1, 0.06), inset(b1, a0, 0.06), s * 0.85, s * 0.085, -nf, Vector((0, 0, 1)))
                    if B.detail <= 2:
                        B.angle(inset(b0, a1, 0.06), inset(a1, b0, 0.06), s * 0.85, s * 0.085, -nf, Vector((0, 0, -1)))
                else:
                    (p, q) = (a0, b1) if (k + i) % 2 == 0 else (b0, a1)
                    B.angle(inset(p, q, 0.05), inset(q, p, 0.05), s * 0.85, s * 0.085, -nf, Vector((0, 0, 1)))
            # Entretoises de plan (diaphragmes) aux cassures.
            if B.detail <= 1 and any(abs(z1 - zb) < 0.01 for zb in breaks) and self.n == 4:
                B.angle(self.corner(0, z1), self.corner(2, z1), s * 0.8, s * 0.08, Vector((0, 0, 1)), Vector((1, -1, 0)))
                B.angle(self.corner(1, z1), self.corner(3, z1), s * 0.8, s * 0.08, Vector((0, 0, 1)), Vector((1, 1, 0)))
        # Platines et goussets au pied, massifs en béton sous chaque jambe.
        B.kind = "ground"
        for i in range(self.n):
            c = self.corner(i, 0)
            B.cuboid((c.x, c.y, 0.12 - 0.5), (1.1, 1.1, 1.24), rot_z(math.degrees(self.phi[i])), "concrete")
            B.cuboid((c.x, c.y, 0.135), (0.42, 0.42, 0.03), rot_z(math.degrees(self.phi[i])), self.mat)
            if B.detail == 0:
                for dx, dy in ((-0.15, -0.15), (0.15, -0.15), (0.15, 0.15), (-0.15, 0.15)):
                    q = rot_z(math.degrees(self.phi[i])) @ Vector((dx, dy, 0))
                    B.tube((c.x + q.x, c.y + q.y, 0.12), (c.x + q.x, c.y + q.y, 0.2), 0.016, 6, self.mat)
        B.kind = "struct"


# --------------------------------------------------------------------------------------------------------------------
# Équipements
# --------------------------------------------------------------------------------------------------------------------
def panel_antenna(B, base, az, h=2.0, w=0.30, d=0.12, tilt=3.0, pipe_r=0.038):
    """Panneau d'antenne de secteur (radôme blanc), tube de fixation derrière, brides, connecteurs et jarretières."""
    B.group("equip")
    base = Vector(base)
    rz = rot_z(az)
    fwd = rz @ Vector((1, 0, 0))
    side = rz @ Vector((0, 1, 0))
    tl = Matrix.Rotation(math.radians(-tilt), 3, side)
    # Tube de fixation (mât de secteur).
    B.tube(base + Vector((0, 0, -0.35)), base + Vector((0, 0, h + 0.25)), pipe_r, 8, "galva")
    c = base + fwd * (pipe_r + 0.05 + d / 2) + Vector((0, 0, h / 2))
    ch = min(0.03, w * 0.12)
    prof = [(-w / 2 + ch, -d / 2), (w / 2 - ch, -d / 2), (w / 2, -d / 2 + ch), (w / 2, d / 2 - ch),
            (w / 2 - ch, d / 2), (-w / 2 + ch, d / 2), (-w / 2, d / 2 - ch), (-w / 2, -d / 2 + ch)]
    if B.detail >= 2:
        prof = [(-w / 2, -d / 2), (w / 2, -d / 2), (w / 2, d / 2), (-w / 2, d / 2)]
    up = tl @ Vector((0, 0, 1))
    # prof : x selon side, y selon fwd.
    B.prism(c - up * (h / 2 - 0.03), c + up * (h / 2 - 0.03), prof, side, fwd, "radome")
    if B.detail <= 1:
        for s_ in (-1, 1):
            B.cuboid(c + up * s_ * (h / 2 - 0.015), (d * 1.02, w * 1.02, 0.03), rz, "box")
        # Brides haute et basse.
        for zz in (0.25, h - 0.25):
            p = base + Vector((0, 0, zz))
            B.cuboid(p + fwd * (pipe_r + 0.025), (0.06, 0.10, 0.05), rz, "galva")
    if B.detail == 0:
        # Connecteurs sous le panneau et jarretières noires vers le bas.
        for k in (-1, 1):
            q = c - up * (h / 2) + side * (k * w * 0.22)
            B.tube(q, q - Vector((0, 0, 0.06)), 0.014, 6, "box")
            B.tube(q - Vector((0, 0, 0.06)), q - Vector((0, 0, 0.30)) - fwd * 0.06, 0.008, 4, "cable")
            B.tube(q - Vector((0, 0, 0.30)) - fwd * 0.06, base + Vector((0, 0, -0.25)) + side * k * 0.03, 0.008, 4, "cable")
    B.kind = "struct"


def rru(B, pos, az):
    """Tête radio déportée (RRU) à ailettes, sur le tube de secteur."""
    B.group("equip")
    rz = rot_z(az)
    fwd = rz @ Vector((1, 0, 0))
    p = Vector(pos) + fwd * 0.12
    B.cuboid(p, (0.16, 0.28, 0.42), rz, "box")
    if B.detail == 0:
        for k in range(-3, 4):
            B.cuboid(p + fwd * 0.10 + (rz @ Vector((0, k * 0.04, 0))), (0.05, 0.006, 0.38), rz, "box")
        B.cuboid(p - fwd * 0.09, (0.04, 0.12, 0.20), rz, "galva")
        B.tube(p - Vector((0, 0, 0.21)), p - Vector((0, 0, 0.5)) - fwd * 0.08, 0.009, 4, "cable")
    B.kind = "struct"


def dish(B, pos, az, r=0.45, depth=0.32, elev=0.0):
    """Faisceau hertzien : parabole à capot (radôme plat blanc), jupe, cône arrière, bride de fixation."""
    B.group("equip")
    pos = Vector(pos)
    rz = rot_z(az)
    fwd = rz @ Matrix.Rotation(math.radians(elev), 3, "Y") @ Vector((1, 0, 0))
    # Jupe (cylindre) puis radôme légèrement bombé à l'avant, cône arrière gris.
    B.lathe(pos, fwd, [(r * 0.35, -depth * 0.9), (r * 0.9, -depth * 0.45), (r, -depth * 0.25), (r, 0.0), (r * 1.01, 0.02)], 20, "box")
    B.lathe(pos + fwd * 0.02, fwd, [(r * 1.01, 0.0), (r * 0.8, 0.025), (r * 0.45, 0.04), (0.0, 0.045)], 20, "radome")
    B.lathe(pos - fwd * depth * 0.9, fwd, [(0.0, -0.12), (r * 0.22, -0.06), (r * 0.35, 0.0)], 10, "box")
    # Bride et bras vers le support.
    B.beam(pos - fwd * depth * 0.6, pos - fwd * (depth + 0.45), 0.06, 0.06, (0, 0, 1), "galva")
    if B.detail == 0:
        B.tube(pos - fwd * (depth + 0.1), pos - fwd * (depth + 0.3) - Vector((0, 0, 0.7)), 0.012, 6, "cable")
    B.kind = "struct"
    return pos - fwd * (depth + 0.45)


def whip(B, base, length, r=0.022):
    B.group("equip")
    base = Vector(base)
    B.tube(base, base + Vector((0, 0, 0.12)), r * 1.8, 8, "box")
    B.tube(base + Vector((0, 0, 0.12)), base + Vector((0, 0, length)), r, 8, "radome")
    B.kind = "struct"


def cabinet(B, pos, az, size=(0.8, 1.3, 1.7)):
    """Armoire technique sur dalle (portes, ouïes, toit débordant) et passerelle de câbles vers le pylône."""
    B.kind = "ground"
    pos = Vector(pos)
    rz = rot_z(az)
    sx, sy, sz = size
    B.cuboid(pos + Vector((0, 0, -0.2)), (sx + 0.6, sy + 0.6, 0.5), rz, "concrete")
    B.cuboid(pos + Vector((0, 0, 0.05 + sz / 2)), (sx, sy, sz), rz, "olive")
    B.cuboid(pos + Vector((0, 0, 0.05 + sz + 0.03)), (sx + 0.08, sy + 0.08, 0.06), rz, "olive")
    fwd = rz @ Vector((1, 0, 0))
    side = rz @ Vector((0, 1, 0))
    if B.detail <= 2:
        # Panneau danger sur la porte gauche, voyant d'état au-dessus (lumineux la nuit).
        B.quad(pos + fwd * (sx / 2 + 0.003) + side * (-sy / 4) + Vector((0, 0, 0.05 + sz * 0.72)), fwd, (0, 0, 1), 0.2, 0.2, "sign", (0.0, 0.0, 0.5, 1.0))
    if B.detail <= 1:
        B.cuboid(pos + fwd * (sx / 2 + 0.008) + side * (sy / 4) + Vector((0, 0, 0.05 + sz * 0.88)), (0.016, 0.03, 0.03), rz, "light")
        # Socle (plinthe) sombre et ouïes latérales.
        B.cuboid(pos + Vector((0, 0, 0.1)), (sx + 0.02, sy + 0.02, 0.1), rz, "cable")
        for j in range(5):
            B.cuboid(pos + side * (sy / 2 + 0.004) + Vector((0, 0, 0.35 + j * 0.05)), (sx * 0.6, 0.01, 0.012), rz, "cable")
        # Joints des deux portes, poignées, ouïes basses.
        for k in (-1, 0, 1):
            B.cuboid(pos + fwd * (sx / 2 + 0.004) + side * (k * sy / 2 * 0.98) + Vector((0, 0, 0.05 + sz / 2)), (0.01, 0.012, sz * 0.96), rz, "cable")
        for k in (-1, 1):
            B.cuboid(pos + fwd * (sx / 2 + 0.02) + side * (k * 0.1) + Vector((0, 0, 0.05 + sz * 0.55)), (0.03, 0.025, 0.16), rz, "galva")
        if B.detail == 0:
            for j in range(6):
                B.cuboid(pos + fwd * (sx / 2 + 0.006) + side * (0.25) + Vector((0, 0, 0.25 + j * 0.035)), (0.012, 0.3, 0.012), rz, "cable")
                B.cuboid(pos + fwd * (sx / 2 + 0.006) + side * (-0.25) + Vector((0, 0, 0.25 + j * 0.035)), (0.012, 0.3, 0.012), rz, "cable")
    B.kind = "struct"


def cable_bridge(B, p0, p1, h=2.4, n_cables=4):
    """Passerelle horizontale de câbles entre l'armoire et le pied du pylône, sur deux poteaux."""
    B.kind = "ground"
    p0, p1 = Vector(p0), Vector(p1)
    a, b = p0 + Vector((0, 0, h)), p1 + Vector((0, 0, h))
    d = (b - a).normalized()
    side = d.cross(Vector((0, 0, 1))).normalized()
    for s in (-1, 1):
        B.beam(a + side * s * 0.15, b + side * s * 0.15, 0.04, 0.06, (0, 0, 1), "galva")
    if B.detail <= 1:
        L = (b - a).length
        for k in range(int(L / 0.5) + 1):
            q = a + d * (k * 0.5)
            B.beam(q - side * 0.15, q + side * 0.15, 0.03, 0.01, (0, 0, 1), "galva")
    for q in (a, b):
        B.tube(q - Vector((0, 0, h + 0.2)), q, 0.04, 8, "galva")
    if B.detail <= 1:
        # Pare-glace : tôle au-dessus des câbles.
        B.cuboid((a + b) / 2 + Vector((0, 0, 0.32)), ((b - a).length, 0.5, 0.008), rot_z(math.degrees(math.atan2(d.y, d.x))), "galva")
        for q in (a, b):
            B.tube(q + side * 0.2, q + side * 0.2 + Vector((0, 0, 0.32)), 0.02, 6, "galva")
    if B.detail <= 1:
        for k in range(n_cables):
            off = side * (-0.09 + 0.06 * k) + Vector((0, 0, 0.03))
            B.tube(a + off, b + off, 0.011, 6, "cable")
    B.kind = "struct"


def ladder(B, x, y, z0, z1, along=(1, 0, 0)):
    """Échelle verticale (montants plats, barreaux) et rail antichute central."""
    al = Vector(along).normalized()
    base = Vector((x, y, 0))
    for s in (-1, 1):
        p = base + al * s * 0.21
        B.beam(p + Vector((0, 0, z0)), p + Vector((0, 0, z1)), 0.06, 0.012, al, "galva")
    if B.detail <= 1:
        B.beam(base + Vector((0, 0, z0)), base + Vector((0, 0, z1)), 0.03, 0.03, al, "galva")
    if B.detail == 0:
        z = z0 + 0.15
        while z < z1:
            B.tube(base - al * 0.21 + Vector((0, 0, z)), base + al * 0.21 + Vector((0, 0, z)), 0.012, 4, "galva", (0, 0, 1))
            z += 0.3


def cable_run(B, x, y, z0, z1, n=6, along=(1, 0, 0)):
    """Chemin de câbles vertical (échelle à câbles) et faisceau de coaxiaux noirs."""
    al = Vector(along).normalized()
    base = Vector((x, y, 0))
    for s in (-1, 1):
        B.beam(base + al * s * 0.17 + Vector((0, 0, z0)), base + al * s * 0.17 + Vector((0, 0, z1)), 0.05, 0.012, al, "galva")
    if B.detail <= 1:
        z = z0 + 0.4
        while z < z1:
            B.beam(base - al * 0.17 + Vector((0, 0, z)), base + al * 0.17 + Vector((0, 0, z)), 0.03, 0.008, (0, 0, 1), "galva")
            z += 0.8 if B.detail == 0 else 1.6
    nrm = al.cross(Vector((0, 0, 1)))
    for k in range(n if B.detail == 0 else 2):
        off = al * (-0.12 + 0.24 * k / max(1, n - 1)) + nrm * 0.03
        B.tube(base + off + Vector((0, 0, z0)), base + off + Vector((0, 0, z1)), 0.013 if B.detail == 0 else 0.03, 6, "cable")


def platform(B, lat, z, overhang=0.45, rail=1.1):
    """Palier : caillebotis sur la section du pylône débordant de overhang, garde-corps (lisse, sous-lisse, plinthe)."""
    n = lat.n
    r = lat.radius(z)
    ro = r + overhang * (math.sqrt(2) if n == 4 else 2.0)
    pts = [Vector((ro * math.cos(p), ro * math.sin(p), z)) for p in lat.phi]
    # Plancher : prisme du polygone (épaisseur 4 cm), bord en cornière.
    prof = [(p.x, p.y) for p in pts]
    B.prism(Vector((0, 0, z - 0.04)), Vector((0, 0, z)), prof, (1, 0, 0), (0, 1, 0), "grating")
    for i in range(n):
        p, q = pts[i], pts[(i + 1) % n]
        nf = lat.face_normal(i)
        B.beam(p, q, 0.06, 0.012, nf, "galva")
        if B.detail <= 2:
            for t in (0.0, 0.5):
                s = p + (q - p) * t
                B.beam(s, s + Vector((0, 0, rail)), 0.05, 0.05, nf, "galva")
            B.beam(p + Vector((0, 0, rail)), q + Vector((0, 0, rail)), 0.045, 0.045, (0, 0, 1), "galva")
        if B.detail <= 1:
            B.beam(p + Vector((0, 0, rail * 0.5)), q + Vector((0, 0, rail * 0.5)), 0.035, 0.035, (0, 0, 1), "galva")
            B.beam(p + Vector((0, 0, 0.08)), q + Vector((0, 0, 0.08)), 0.15, 0.005, nf, "galva")
        # Consoles sous le plancher, de la jambe vers le bord.
        if B.detail <= 1:
            c = lat.corner(i, z - 0.6)
            B.beam(c, pts[i] - Vector((0, 0, 0.04)), 0.05, 0.05, (0, 0, 1), "galva")


def top_pole(B, z0, length, r=0.06, rod=True, beacon=False):
    B.tube((0, 0, z0 - 1.0), (0, 0, z0 + length), r, 10, "galva")
    if rod and B.detail <= 2:
        B.tube((0, 0, z0 + length), (0, 0, z0 + length + 1.4), 0.012, 6, "galva")
    if beacon:
        obstruction_light(B, (0, 0, z0 + length), "light_top", 1.3)


def obstruction_light(B, pos, name, size=1.0, bracket=None):
    """Feu d'obstacle rouge (corps sombre, globe rouge émissif) ; point mémoire name pour la lumière du jeu
    (MarkerLights, clignotant la nuit). bracket : point du pylône où souder la console."""
    B.group("equip")
    p = Vector(pos)
    if bracket is not None:
        B.tube(Vector(bracket), p - Vector((0, 0, 0.02)), 0.025, 6, "galva", (0, 0, 1))
    B.tube(p, p + Vector((0, 0, 0.06 * size)), 0.075 * size, 10, "cable")
    B.lathe(p + Vector((0, 0, 0.06 * size)), (0, 0, 1), [(0.065 * size, 0.0), (0.065 * size, 0.08 * size), (0.045 * size, 0.13 * size), (0.0, 0.15 * size)], 12, "light")
    B.kind = "struct"
    if B.intact:
        B.mem.append((name, p + Vector((0, 0, 0.12 * size))))


def guys(B, lat, levels, radius, az0=90.0, n=3):
    """Haubans (câbles acier) de chaque niveau vers trois ancrages béton à radius m, ridoirs au pied ; pylône debout
    seulement (dans la ruine, les câbles ont cédé : seuls restent les ancrages)."""
    for k in range(n):
        az = math.radians(az0 + k * 360 / n)
        d = Vector((math.cos(az), math.sin(az), 0))
        anchor = d * radius
        B.kind = "ground"
        B.cuboid(anchor + Vector((0, 0, -0.25)), (1.2, 0.8, 0.7), rot_z(math.degrees(az)), "concrete")
        B.cuboid(anchor + Vector((0, 0, 0.12)), (0.3, 0.12, 0.06), rot_z(math.degrees(az)), "galva")
        B.kind = "struct"
        if not B.intact:
            continue
        for j, z in enumerate(levels):
            top = d * (lat.radius(z) * 0.9) + Vector((0, 0, z))
            foot = anchor + d * (-0.15 * j) + Vector((0, 0, 0.18))
            ridoir = foot + (top - foot).normalized() * 0.9
            B.tube(foot, ridoir, 0.022, 6, "galva")
            B.tube(ridoir, top, 0.009, 6, "galva")
            if B.detail <= 1:
                B.tube(top - Vector((0, 0, 0.05)), top + Vector((0, 0, 0.05)), 0.05, 6, "galva")


def ac_unit(B, pos, az):
    """Groupe de climatisation des armoires : caisson, ventilateur grillagé, pieds, liaisons frigorifiques."""
    B.kind = "ground"
    rz = rot_z(az)
    fwd = rz @ Vector((1, 0, 0))
    p = Vector(pos)
    B.cuboid(p + Vector((0, 0, 0.35)), (0.32, 0.82, 0.56), rz, "box")
    for s_ in (-1, 1):
        B.cuboid(p + rz @ Vector((0, s_ * 0.3, 0.035)), (0.4, 0.06, 0.07), rz, "cable")
    if B.detail <= 2:
        B.lathe(p + fwd * 0.161 + Vector((0, 0, 0.36)), fwd, [(0.2, 0.0), (0.2, 0.004)], 20, "cable")
    if B.detail <= 1:
        for k in range(-3, 4):
            B.cuboid(p + fwd * 0.168 + rz @ Vector((0, k * 0.055, 0.36)), (0.008, 0.008, 0.4), rz, "galva")
        B.tube(p - fwd * 0.17 + Vector((0, 0.1, 0.5)), p - fwd * 0.6 + Vector((0, 0.1, 0.5)), 0.012, 6, "cable")
    B.kind = "struct"


def camo_net(B, x0, y0, x1, y1, h=2.4, n=6):
    """Filet de camouflage tendu sur quatre perches au-dessus des armoires, affaissé au centre (deux faces)."""
    B.kind = "ground"
    for x, y in ((x0, y0), (x1, y0), (x1, y1), (x0, y1)):
        B.tube((x, y, 0.0), (x, y, h + 0.05), 0.025, 6, "cable")
    pts = {}
    for i in range(n + 1):
        for j in range(n + 1):
            u, v = i / n, j / n
            sag = 0.45 * math.sin(math.pi * u) * math.sin(math.pi * v) + 0.05 * math.sin(7 * u + 3 * v)
            pts[i, j] = Vector((x0 + (x1 - x0) * u, y0 + (y1 - y0) * v, h - sag))
    verts, faces, uvs = [], [], []
    for i in range(n):
        for j in range(n):
            q = [pts[i, j], pts[i + 1, j], pts[i + 1, j + 1], pts[i, j + 1]]
            uv = [((i) / 3, (j) / 3), ((i + 1) / 3, (j) / 3), ((i + 1) / 3, (j + 1) / 3), ((i) / 3, (j + 1) / 3)]
            o = len(verts)
            verts += q
            faces.append((o, o + 1, o + 2, o + 3))
            uvs.append(uv)
            o = len(verts)
            verts += list(reversed(q))
            faces.append((o, o + 1, o + 2, o + 3))
            uvs.append(list(reversed(uv)))
    B.add(verts, faces, "net", uvs=uvs)
    B.kind = "struct"


def site_pad(B, x0, y0, x1, y1):
    """Dalle béton du site (10 cm au-dessus du sol, 40 cm dessous pour les terrains en pente)."""
    B.kind = "ground"
    B.cuboid(((x0 + x1) / 2, (y0 + y1) / 2, -0.15), (x1 - x0, y1 - y0, 0.5), None, "concrete")
    B.kind = "struct"


def site_sign(B, pos, az, h=1.2):
    """Plaque d'identification du site sur poteau (face vers az)."""
    B.kind = "ground"
    fwd = rot_z(az) @ Vector((1, 0, 0))
    p = Vector(pos)
    B.tube(p, p + Vector((0, 0, h + 0.3)), 0.025, 6, "galva")
    B.cuboid(p + fwd * 0.03 + Vector((0, 0, h)), (0.01, 0.5, 0.5), rot_z(az), "galva")
    B.quad(p + fwd * 0.036 + Vector((0, 0, h)), fwd, (0, 0, 1), 0.5, 0.5, "sign", (0.5, 0.0, 1.0, 1.0))
    B.kind = "struct"


def step_bolts(B, lat, z0, z1, az=200.0):
    """Échelons boulonnés du monopole (de près) : deux files décalées tous les 40 cm."""
    if B.detail > 0:
        return
    z = z0
    k = 0
    while z < z1:
        a = math.radians(az + (12 if k % 2 else -12))
        d = Vector((math.cos(a), math.sin(a), 0))
        r = lat.radius(z)
        B.tube(d * (r - 0.01) + Vector((0, 0, z)), d * (r + 0.17) + Vector((0, 0, z)), 0.011, 6, "galva", (0, 0, 1))
        B.tube(d * (r + 0.17) + Vector((0, 0, z - 0.01)), d * (r + 0.17) + Vector((0, 0, z + 0.05)), 0.011, 6, "galva", (1, 0, 0))
        z += 0.4
        k += 1


def sector_ring(B, lat, z, arm, az_list, panels_per, h, w, tilt=3.0, rru_on=True, spread=0.5):
    """Bras de secteurs : un bras horizontal (deux tubes) par azimut, tube de fixation et panneaux au bout."""
    for az in az_list:
        rz = rot_z(az)
        fwd = rz @ Vector((1, 0, 0))
        side = rz @ Vector((0, 1, 0))
        r = lat.radius(z) * (0.75 if lat.n == 4 else 0.55)
        for dz in (0.0, h * 0.75):
            B.tube(Vector((0, 0, z + dz)) + fwd * r, Vector((0, 0, z + dz)) + fwd * (r + arm), 0.04, 8, "galva", (0, 0, 1))
            if B.detail <= 1:
                B.tube(Vector((0, 0, z + dz)) + fwd * (r + arm) - side * spread, Vector((0, 0, z + dz)) + fwd * (r + arm) + side * spread, 0.035, 8, "galva", (0, 0, 1))
        for k in range(panels_per):
            off = 0.0 if panels_per == 1 else (-spread + 2 * spread * k / (panels_per - 1))
            base = Vector((0, 0, z - 0.1)) + fwd * (r + arm + 0.06) + side * off
            panel_antenna(B, base, az, h, w, 0.12, tilt)
            if rru_on:
                rru(B, base + Vector((0, 0, -0.55)) + side * 0.0, az)


# --------------------------------------------------------------------------------------------------------------------
# Les trois pylônes
# --------------------------------------------------------------------------------------------------------------------
def panel_levels(z0, z1, step):
    zs = []
    z = z0
    while z < z1 - 1e-6:
        zs.append(round(z, 3))
        z += step
    zs.append(z1)
    return zs


def tower_30(B):
    """Mât treillis carré 30 m peint vert olive, haubané à deux niveaux (photo de référence 1)."""
    lat = Lattice(4, [(0, 1.5), (6, 0.95), (30, 0.85)], panel_levels(0, 30, 1.0), "angle", 0.09, 0.05, "olive", "Z")
    site_pad(B, -1.6, -1.6, 4.0, 1.6)
    lat.build(B)
    B.mat = "olive"
    cable_run(B, 0.0, 0.0, 0.3, 27.0, 6, (1, 0, 0))
    if B.detail <= 2:
        ladder(B, 0.0, -0.2, 0.3, 29.0)
    # Trois secteurs de deux panneaux hauts (2,6 m), RRU en dessous, montés au plus près du mât.
    sector_ring(B, lat, 26.6, 0.35, (30, 150, 270), 1, 2.6, 0.34, 2.0, True)
    # Deuxième couronne plus basse (opérateur 2) : panneaux 1,6 m, parabole de faisceau hertzien.
    sector_ring(B, lat, 22.4, 0.30, (90, 210, 330), 1, 1.6, 0.28, 4.0, True)
    B.mat = "olive"
    fwd = rot_z(-20) @ Vector((1, 0, 0))
    anchor = dish(B, Vector((0, 0, 19.0)) + fwd * (lat.radius(19.0) + 0.55), -20.0, 0.3, 0.2)
    B.tube(anchor, Vector((0, 0, 19.0)) + fwd * lat.radius(19.0) * 0.8, 0.035, 8, "galva", (0, 0, 1))
    B.mat = "olive"
    top_pole(B, 30.0, 2.2, 0.05, True, True)
    whip(B, (0.3, 0.3, 30.0), 0.8, 0.015)
    guys(B, lat, (14.0, 26.0), 12.0, 60.0)
    cabinet(B, (2.6, 0.0, 0.0), 0.0, (0.75, 1.2, 1.6))
    cable_bridge(B, (2.2, 0.0, 0.0), (0.45, 0.0, 0.0), 2.2, 4)
    site_sign(B, (3.6, -1.3, 0.1), 0.0)
    return lat


def tower_40(B):
    """Tour triangulaire 40 m galvanisée, plate-forme de tête, paraboles, enclos technique (photo de référence 2)."""
    lat = Lattice(3, [(0, 3.4), (34, 1.3), (40, 1.3)], panel_levels(0, 40, 2.0), "tube", 0.075, 0.07, "galva", "X")
    site_pad(B, -3.0, -5.6, 3.0, 2.6)
    lat.build(B)
    B.mat = "galva"
    ladder(B, 0.0, -0.05, 0.3, 39.5)
    cable_run(B, 0.0, 0.33, 0.3, 37.0, 8, (1, 0, 0))
    platform(B, lat, 35.6, 0.5)
    B.mat = "galva"
    # Tête : trois bras longs, deux panneaux par secteur (photo), RRU sous chaque panneau.
    sector_ring(B, lat, 37.0, 1.5, (90, 210, 330), 2, 1.5, 0.30, 3.0, True, 0.65)
    B.mat = "galva"
    # Faisceaux hertziens : grande parabole sur une face, petite plus bas.
    for z, az, r in ((31.0, 30.0, 0.45), (27.0, 150.0, 0.30)):
        fwd = rot_z(az) @ Vector((1, 0, 0))
        pos = Vector((0, 0, z)) + fwd * (lat.radius(z) * 0.5 + 0.75)
        anchor = dish(B, pos, az, r, 0.28 if r > 0.4 else 0.2)
        B.tube(anchor + Vector((0, 0, -0.6)), anchor + Vector((0, 0, 0.6)), 0.045, 8, "galva")
        B.tube(anchor, Vector((0, 0, z)) + fwd * (lat.radius(z) * 0.45), 0.04, 8, "galva", (0, 0, 1))
    top_pole(B, 40.0, 3.0, 0.06, True, True)
    # Feux intermédiaires à mi-hauteur sur deux jambes.
    for i in (0, 1):
        c = lat.corner(i, 20.0)
        out = Vector((c.x, c.y, 0)).normalized()
        obstruction_light(B, c + out * 0.35, f"light_mid{i + 1}", 1.0, c)
    # Fouets omnidirectionnels sur une barre en tête.
    B.tube((-0.6, 0, 41.5), (0.6, 0, 41.5), 0.03, 8, "galva", (0, 0, 1))
    whip(B, (-0.6, 0, 41.5), 2.4)
    whip(B, (0.6, 0, 41.5), 1.8)
    cabinet(B, (0.0, -3.4, 0.0), 90.0, (0.8, 1.4, 1.7))
    cabinet(B, (1.6, -3.4, 0.0), 90.0, (0.7, 1.0, 1.5))
    ac_unit(B, (-1.6, -3.5, 0.1), 90.0)
    cable_bridge(B, (0.0, -2.9, 0.0), (0.0, -0.9, 0.0), 2.4, 5)
    fence(B, (0.0, -4.0, 0.0), 5.4, 2.6, 2.0)
    site_sign(B, (-2.75, -5.4, 0.1), -90.0)
    return lat


def tower_50(B):
    """Pylône élancé carré 50 m galvanisé, paliers, balisage, enclos technique (photo de référence 3)."""
    lat = Lattice(4, [(0, 4.0), (40, 1.15), (50, 1.15)], panel_levels(0, 50, 2.5), "angle", 0.12, 0.07, "galva", "X")
    site_pad(B, -3.2, -3.2, 6.6, 3.2)
    lat.build(B)
    B.mat = "galva"
    ladder(B, 0.0, -0.05, 0.3, 49.5)
    cable_run(B, 0.0, 0.32, 0.3, 46.5, 8, (1, 0, 0))
    for z in (15.0, 30.0, 44.0):
        platform(B, lat, z, 0.45 if z < 40 else 0.6)
    B.mat = "galva"
    sector_ring(B, lat, 46.6, 0.55, (0, 120, 240), 2, 2.0, 0.30, 2.0, True, 0.35)
    sector_ring(B, lat, 41.0, 0.45, (60, 180, 300), 1, 1.4, 0.26, 4.0, True)
    B.mat = "galva"
    for z, az, r in ((38.5, 60.0, 0.32), (33.0, 200.0, 0.45)):
        fwd = rot_z(az) @ Vector((1, 0, 0))
        pos = Vector((0, 0, z)) + fwd * (lat.radius(z) * 0.7 + 0.7)
        anchor = dish(B, pos, az, r, 0.22 if r < 0.4 else 0.28)
        B.tube(anchor + Vector((0, 0, -0.5)), anchor + Vector((0, 0, 0.5)), 0.045, 8, "galva")
        B.tube(anchor, Vector((0, 0, z)) + fwd * (lat.radius(z) * 0.6), 0.04, 8, "galva", (0, 0, 1))
    top_pole(B, 50.0, 2.5, 0.06, True, True)
    for i, z in ((0, 25.0), (2, 25.0)):
        c = lat.corner(i, z)
        out = Vector((c.x, c.y, 0)).normalized()
        obstruction_light(B, c + out * 0.35, f"light_mid{1 if i == 0 else 2}", 1.0, c)
    whip(B, (0.5, 0.5, 50.0), 2.0)
    cabinet(B, (3.8, 0.0, 0.0), 0.0, (0.8, 1.4, 1.8))
    cabinet(B, (3.8, -1.8, 0.0), 0.0, (0.8, 1.2, 1.8))
    ac_unit(B, (3.8, 1.6, 0.1), 0.0)
    cable_bridge(B, (3.35, 0.0, 0.0), (1.6, 0.0, 0.0), 2.6, 5)
    fence(B, (4.6, -0.2, 0.0), 3.4, 5.6, 2.0)
    site_sign(B, (6.4, -2.9, 0.1), 0.0)
    return lat


class Mono:
    """Monopole : fût conique en tronçons emboîtés (pas de treillis). Même interface que Lattice pour les équipements
    (radius, n) et la ruine (Collapse)."""

    def __init__(self, height, r0, r1):
        self.n = 4
        self.height, self.r0, self.r1 = height, r0, r1
        self.panels = [0.0, height]
        self.widths = [(0.0, r0 * 2), (height, r1 * 2)]

    def radius(self, z):
        t = max(0.0, min(1.0, z / self.height))
        return self.r0 + (self.r1 - self.r0) * t

    def corner(self, i, z):
        r = self.radius(z)
        p = math.radians(45 + i * 90)
        return Vector((r * math.cos(p), r * math.sin(p), z))

    def build(self, B, sections, fence=None):
        """Fût en sections emboîtées (collerette au recouvrement), platine sur massif, tiges d'ancrage."""
        B.kind, B.mat = "struct", "galva"
        h = self.height
        n = 16 if B.detail <= 1 else (10 if B.detail == 2 else 8)
        zs = [h * k / sections for k in range(sections + 1)]
        for z0, z1 in zip(zs, zs[1:]):
            # Tronçon légèrement conique : deux anneaux de rayon différent (prisme tronc de cône).
            # Ruine : anneaux tous les B.split m pour que le fût se plie (pièces séparées pour ombres et géométries).
            k = max(1, math.ceil((z1 - z0) / B.split)) if B.split else 1
            cuts = [z0 + (z1 - z0) * j / k for j in range(k + 1)]
            if B.pieces:
                for a, b in zip(cuts, cuts[1:]):
                    B.lathe((0, 0, a), (0, 0, 1), [(self.radius(a), 0.0), (self.radius(b), b - a)], n, "galva")
            else:
                B.lathe((0, 0, z0), (0, 0, 1), [(self.radius(z), z - z0) for z in cuts], n, "galva")
            if z0 > 0 and B.detail <= 2:
                # Recouvrement du tronçon inférieur (manchon).
                B.lathe((0, 0, z0 - 0.35), (0, 0, 1), [(self.radius(z0) * 1.06, 0.0), (self.radius(z0) * 1.06, 0.45)], n, "galva")
        # Pied : platine, goussets, massif.
        B.kind = "ground"
        r = self.r0
        B.cuboid((0, 0, -0.35), (r * 2 + 1.4, r * 2 + 1.4, 1.1), None, "concrete")
        B.lathe((0, 0, 0.2), (0, 0, 1), [(r + 0.25, 0.0), (r + 0.25, 0.05)], n, "galva")
        if B.detail <= 1:
            for k in range(8):
                a = 2 * math.pi * k / 8
                c = Vector((math.cos(a), math.sin(a), 0)) * (r + 0.17)
                B.tube((c.x, c.y, 0.2), (c.x, c.y, 0.36), 0.022, 6, "galva")
                if B.detail == 0:
                    g = Vector((math.cos(a + math.pi / 8), math.sin(a + math.pi / 8), 0))
                    B.prism(Vector((0, 0, 0.25)) + g * r, Vector((0, 0, 0.25)) + g * (r + 0.2), [(0, 0), (0.012, 0), (0.012, 0.35), (0, 0.06)], (0, 0, 1), g.cross(Vector((0, 0, 1))), "galva")
        B.kind = "struct"


def mesh_panel(B, a, b, h):
    """Pan de grillage transparent (deux faces) entre a et b, du sol à h - 0,05 m ; UV : 1 m = 1 tuile."""
    d = b - a
    L = d.length
    nrm = d.normalized().cross(Vector((0, 0, 1)))
    B.quad((a + b) / 2 + Vector((0, 0, h / 2)), nrm, (0, 0, 1), L, h - 0.1, "fence", (0, 0, L, h - 0.1), both=True)


def fence(B, c, sx, sy, h=2.0, gate=True):
    """Enclos grillagé (poteaux, lisse haute, grillage) autour des armoires d'un monopole."""
    B.kind = "ground"
    c = Vector(c)
    pts = [c + Vector(p) for p in ((-sx / 2, -sy / 2, 0), (sx / 2, -sy / 2, 0), (sx / 2, sy / 2, 0), (-sx / 2, sy / 2, 0))]
    for i in range(4):
        p, q = pts[i], pts[(i + 1) % 4]
        L = (q - p).length
        d = (q - p).normalized()
        k = max(1, round(L / 2.5))
        for j in range(k + 1):
            t = p + d * (L * j / k)
            B.tube(t, t + Vector((0, 0, h)), 0.03, 8, "galva")
        B.tube(p + Vector((0, 0, h)), q + Vector((0, 0, h)), 0.022, 6, "galva", (0, 0, 1))
        if gate and i == 0:
            # Portail : le grillage s'arrête sur 1,2 m au milieu du côté.
            m = (p + q) / 2
            for a, b in ((p, m - d * 0.6), (m + d * 0.6, q)):
                mesh_panel(B, a, b, h)
        else:
            mesh_panel(B, p, q, h)
    B.kind = "struct"


def tower_m20(B):
    """Monopole 20 m (photo de référence 4) : fût galvanisé en quatre tronçons, échelons, trois secteurs serrés en
    tête, RRU, petite parabole, balisage ; armoires et climatisation sous filet de camouflage, dans un enclos."""
    lat = Mono(20.0, 0.42, 0.22)
    site_pad(B, -1.0, -2.6, 5.0, 2.2)
    lat.build(B, 4)
    step_bolts(B, lat, 2.6, 18.0)
    B.mat = "galva"
    sector_ring(B, lat, 18.0, 0.28, (20, 140, 260), 1, 1.5, 0.28, 2.0, True)
    sector_ring(B, lat, 16.2, 0.22, (80, 200, 320), 1, 1.0, 0.24, 3.0, False)
    B.mat = "galva"
    fwd = rot_z(-30) @ Vector((1, 0, 0))
    pos = Vector((0, 0, 19.3)) + fwd * (lat.radius(19.3) + 0.45)
    anchor = dish(B, pos, -30.0, 0.30, 0.22)
    B.tube(anchor, Vector((0, 0, 19.3)) + fwd * lat.radius(19.3) * 0.8, 0.035, 8, "galva", (0, 0, 1))
    top_pole(B, 20.0, 1.2, 0.05, True, True)
    whip(B, (0.18, 0.0, 20.3), 1.2, 0.016)
    cabinet(B, (3.2, 0.6, 0.0), 0.0, (0.75, 1.2, 1.6))
    cabinet(B, (3.2, -0.9, 0.0), 0.0, (0.6, 0.8, 1.4))
    ac_unit(B, (3.3, -1.9, 0.1), 0.0)
    cable_bridge(B, (2.75, 0.0, 0.0), (0.55, 0.0, 0.0), 2.2, 4)
    fence(B, (3.1, -0.15, 0.0), 2.6, 4.0, 2.0)
    camo_net(B, 1.7, -2.4, 4.6, 2.1, 2.5)
    site_sign(B, (4.8, -2.4, 0.1), 0.0)
    return lat


def tower_m12(B):
    """Mât monopole léger 12 m haubané (photo de référence 5) : trois tronçons, une couronne de trois panneaux,
    balisage, petite armoire."""
    lat = Mono(12.0, 0.30, 0.17)
    site_pad(B, -0.9, -0.9, 2.6, 0.9)
    lat.build(B, 3)
    step_bolts(B, lat, 2.4, 10.0)
    B.mat = "galva"
    sector_ring(B, lat, 10.6, 0.22, (0, 120, 240), 1, 1.0, 0.24, 2.0, True)
    top_pole(B, 12.0, 1.0, 0.045, True, True)
    whip(B, (0.15, 0.0, 12.2), 1.0, 0.015)
    guys(B, lat, (8.0,), 6.5, 60.0)
    cabinet(B, (1.9, 0.0, 0.0), 0.0, (0.6, 0.9, 1.4))
    cable_bridge(B, (1.55, 0.0, 0.0), (0.4, 0.0, 0.0), 2.0, 3)
    return lat


TOWERS = {
    "relay_tower_30": (tower_30, 30.0, 6000.0),
    "relay_tower_40": (tower_40, 40.0, 9000.0),
    "relay_tower_50": (tower_50, 50.0, 12000.0),
    "relay_tower_m12": (tower_m12, 12.0, 3000.0),
    "relay_tower_m20": (tower_m20, 20.0, 4500.0),
}


# --------------------------------------------------------------------------------------------------------------------
# Ruine : le pylône casse au-dessus d'un tronçon resté debout, la partie haute se couche et repose sur le sol.
# --------------------------------------------------------------------------------------------------------------------
class Collapse:
    def __init__(self, lat, height, seed):
        rnd = random.Random(seed)
        self.rnd = rnd
        self.h1 = height * rnd.uniform(0.12, 0.18)
        self.blend = 0.9
        self.az = rnd.uniform(0, 360)
        rz = rot_z(self.az)
        self.dir = rz @ Vector((1, 0, 0))     # sens de la chute
        self.side = rz @ Vector((0, 1, 0))
        self.hx = lat.radius(self.h1) * 0.95   # charnière sur la face côté chute (repère local : la chute va vers +x)
        # Premier pli : la partie haute plonge (theta1 depuis la verticale) jusqu'au sol, second pli : elle s'y couche.
        self.t1 = math.radians(rnd.uniform(122, 140))
        self.theta = math.radians(rnd.uniform(90.5, 92.0))
        self.s2 = (self.h1 - 0.25) / -math.cos(self.t1)
        self.L = height + 3.0 - self.h1
        self.wob = (rnd.uniform(0.2, 0.5), rnd.uniform(0.15, 0.3), rnd.uniform(0, 6))

    def local(self, p):
        return Vector((-p.dot(self.dir), p.dot(self.side), p.z))

    def world(self, q):
        return -self.dir * q.x + self.side * q.y + Vector((0, 0, q.z))

    @staticmethod
    def smooth(t):
        t = max(0.0, min(1.0, t))
        return t * t * (3 - 2 * t)

    def f(self, p):
        q = self.local(p)
        s = q.z - self.h1
        a = self.t1 * self.smooth((s + self.blend) / (2 * self.blend))
        if a <= 0:
            # Tronçon debout : léger vrillage et flexion vers la chute.
            k = (q.z / max(self.h1, 0.1)) ** 2
            q = Vector((q.x + 0.25 * k, q.y + 0.08 * k, q.z))
            return self.world(q)
        rx, rz = q.x - self.hx, s
        x = self.hx + rx * math.cos(a) + rz * math.sin(a)
        z = self.h1 - rx * math.sin(a) + rz * math.cos(a)
        # Second pli (froissé sur 2 m) : retour à l'horizontale autour du point où l'arête basse touche le sol.
        a2 = (self.t1 - self.theta) * self.smooth((s - self.s2 + 1.0) / 2.0)
        if a2 > 0:
            px = self.hx + self.s2 * math.sin(self.t1)
            pz = self.h1 + self.s2 * math.cos(self.t1)
            rx2, rz2 = x - px, z - pz
            x = px + rx2 * math.cos(a2) - rz2 * math.sin(a2)
            z = pz + rx2 * math.sin(a2) + rz2 * math.cos(a2)
        amp, freq, ph = self.wob
        sp = max(0.0, s)
        y = q.y + amp * math.sin(sp * freq + ph) * min(1, sp / 4)
        if z < 0.03:
            z = 0.03 + (0.03 - z) * 0.05
        return self.world(Vector((x, y, z)))


def collapse_parts(parts, col, seed):
    rnd = random.Random(seed + 7)
    groups = {}
    for p in parts:
        if p.kind == "equip":
            groups.setdefault(p.gid, []).append(p)
    out = []
    for p in parts:
        if p.kind == "struct":
            out.append(Part([col.f(v) for v in p.verts], p.faces, p.mat, p.kind, p.gid, p.box, p.smooth, p.uvs))
        elif p.kind == "ground":
            out.append(p)
    # Équipements : chaque groupe suit la chute puis tombe au sol (couché, retourné), parfois projeté plus loin.
    for gid, ps in groups.items():
        allv = [v for p in ps for v in p.verts]
        c = sum(allv, Vector()) / len(allv)
        c2 = col.f(c)
        if c.z > col.h1 + 1:
            c2 = c2 + col.dir * rnd.uniform(-1.5, 3.0) + col.side * rnd.uniform(-3.0, 3.0)
        rot = (Matrix.Rotation(rnd.uniform(-0.4, 0.4), 3, "Z") @ Matrix.Rotation(-col.theta + rnd.uniform(-0.3, 0.3), 3, col.side)
               @ Matrix.Rotation(rnd.uniform(-0.6, 0.6), 3, col.dir))
        moved = [[c2 + rot @ (v - c) for v in p.verts] for p in ps]
        zmin = min(v.z for vs in moved for v in vs)
        lift = Vector((0, 0, 0.02 - zmin))
        for p, vs in zip(ps, moved):
            out.append(Part([v + lift for v in vs], p.faces, p.mat, "equip", gid, p.box, p.smooth, p.uvs))
    # Débris : cornières tordues éparpillées autour du pied.
    B = Builder(1)
    B.mat = parts[0].mat if parts else "galva"
    for k in range(14):
        a = rnd.uniform(0, 2 * math.pi)
        d = rnd.uniform(2.0, 9.0)
        c = col.world(Vector((-d, 0, 0))) * 0.4 + Vector((math.cos(a) * d, math.sin(a) * d, 0)) * 0.6
        L = rnd.uniform(0.8, 2.6)
        t = rnd.uniform(0, 2 * math.pi)
        e = Vector((math.cos(t), math.sin(t), 0)) * L / 2
        p0, p1 = c - e + Vector((0, 0, 0.04)), c + e + Vector((0, 0, rnd.uniform(0.04, 0.3)))
        B.angle(p0, p1, 0.06, 0.006, Vector((0, 0, 1)), e.cross(Vector((0, 0, 1))))
    for p in B.parts:
        p.mat = out[0].mat if out else "galva"
        out.append(p)
    return out


# --------------------------------------------------------------------------------------------------------------------
# Blender : maillages, matières, LOD
# --------------------------------------------------------------------------------------------------------------------
def project_uv(vs, scale):
    """UV par coin : projection sur le plan dominant de la face (monde), scale m par tuile."""
    n = Vector()
    for i in range(len(vs)):
        a, b = vs[i], vs[(i + 1) % len(vs)]
        n += Vector(((a.y - b.y) * (a.z + b.z), (a.z - b.z) * (a.x + b.x), (a.x - b.x) * (a.y + b.y)))
    ax = max(range(3), key=lambda i: abs(n[i]))
    if ax == 0:
        return [(v.y / scale, v.z / scale) for v in vs]
    if ax == 1:
        return [(v.x / scale, v.z / scale) for v in vs]
    return [(v.x / scale, v.y / scale) for v in vs]


def make_mesh(name, parts, mats, uv_from=None, with_uv=True):
    """Objet Blender des pièces ; uv_from : pièces d'origine (même ordre) pour garder l'UV avant pliage (ruine)."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap") if with_uv else None
    keys = []
    for k in [p.mat for p in parts]:
        if k not in keys:
            keys.append(k)
    for pi, p in enumerate(parts):
        bv = [bm.verts.new(v) for v in p.verts]
        src = uv_from[pi].verts if uv_from is not None else p.verts
        for fi, f in enumerate(p.faces):
            try:
                face = bm.faces.new([bv[i] for i in f])
            except ValueError:
                continue
            face.material_index = keys.index(p.mat)
            # Flancs des tubes lissés ; boîtes, cornières et bouchons à facettes (sinon le jeu moyenne les normales
            # des coins : faces éclairées à contre-jour, reflets en bandes sur les massifs).
            face.smooth = fi < p.smooth
            if uvl is not None:
                uvs = p.uvs[fi] if p.uvs else project_uv([src[i] for i in f], MATS[p.mat][2])
                for loop, uv in zip(face.loops, uvs):
                    loop[uvl].uv = uv
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    # Arête vive dès qu'une face voisine est à facettes (export A3OB : #SharpEdges#).
    for e in bm.edges:
        e.smooth = all(f.smooth for f in e.link_faces)
    bmesh.ops.triangulate(bm, faces=[f for f in bm.faces if len(f.verts) > 4], ngon_method="EAR_CLIP")
    bm.to_mesh(me)
    bm.free()
    for k in keys:
        me.materials.append(mats[k])
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    return ob


def build_parts(fn, detail, split=0.0):
    B = Builder(detail, split)
    lat = fn(B)
    return B.parts, lat


def solid(parts):
    """Pièces ayant un volume (sans panneaux collés ni filet)."""
    return [p for p in parts if p.mat not in THIN]


def fence_walls(parts):
    """Grillage pour la géométrie (collisions) : chaque pan épaissi à 4 cm (les balles, elles, passent)."""
    out = []
    for p in parts:
        if p.mat != "fence" or len(p.verts) != 4:
            continue
        a, b, c = p.verts[0], p.verts[1], p.verts[2]
        n = (b - a).cross(c - a).normalized() * 0.02
        out.append(Part([v + n for v in p.verts] + [v - n for v in p.verts], [], "galva", p.kind, p.gid, True))
    return out


def memory_lod(name, points):
    """LOD mémoire : un sommet nommé par point (feux de balisage : MarkerLights de config.cpp)."""
    me = bpy.data.meshes.new(name)
    me.from_pydata([tuple(p) for _n, p in points], [], [])
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    for i, (n, _p) in enumerate(points):
        g = ob.vertex_groups.new(name=n)
        g.add([i], 1.0, "REPLACE")
    return set_lod(ob, 9, 0)


def a3_materials():
    out = {}
    for k, (tex, rv, *_r) in MATS.items():
        m = bpy.data.materials.new("a3_" + k)
        p = m.a3ob_properties_material
        p.texture_type = "TEX"
        p.texture_path = TEX + tex + ("_ca.paa" if k in ("net", "fence") else "_co.paa")
        p.material_path = TEX + rv + ".rvmat"
        out[k] = m
    return out


def set_lod(ob, lod, res=0, props=()):
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = str(lod)
    p.resolution = res
    for k, v in props:
        it = p.properties.add()
        it.name, it.value = k, v
    return ob


def tri(ob):
    bm = bmesh.new()
    bm.from_mesh(ob.data)
    bmesh.ops.triangulate(bm, faces=bm.faces)
    bm.to_mesh(ob.data)
    bm.free()


def mesh_from_parts(name, parts):
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    for p in parts:
        bv = [bm.verts.new(v) for v in p.verts]
        for f in p.faces:
            try:
                bm.faces.new([bv[i] for i in f])
            except ValueError:
                pass
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bmesh.ops.triangulate(bm, faces=bm.faces)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    return ob


def add_mass(ob, mass):
    layer = ob.data.attributes.new("a3ob_mass", "FLOAT", "POINT")
    n = len(ob.data.vertices)
    for i in range(n):
        layer.data[i].value = mass / n


def clear_scene():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)
    for me in list(bpy.data.meshes):
        bpy.data.meshes.remove(me)


def hull(p, inset=0.0):
    """Enveloppe convexe fermée d'une pièce (sommets, triangles), rentrée de inset m le long des normales :
    une composante par pièce, sans arête ouverte (ombres et géométries du jeu)."""
    bm = bmesh.new()
    for v in p.verts:
        bm.verts.new(v)
    bmesh.ops.convex_hull(bm, input=bm.verts[:], use_existing_faces=False)
    for v in [v for v in bm.verts if not v.link_faces]:
        bm.verts.remove(v)
    if len(bm.faces) < 4:
        bm.free()
        return None
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    if inset > 0:
        lo = Vector([min(v.co[i] for v in bm.verts) for i in range(3)])
        hi = Vector([max(v.co[i] for v in bm.verts) for i in range(3)])
        d = min(inset, 0.2 * min(hi - lo))
        moves = [v.normal.copy() * -d for v in bm.verts]
        for v, m in zip(bm.verts, moves):
            v.co += m
    bmesh.ops.triangulate(bm, faces=bm.faces)
    verts = [v.co.copy() for v in bm.verts]
    idx = {v: i for i, v in enumerate(bm.verts)}
    faces = [tuple(idx[v] for v in f.verts) for f in bm.faces]
    bm.free()
    return Part(verts, faces, p.mat, p.kind, p.gid, True)


def extent(p):
    lo = Vector([min(v[i] for v in p.verts) for i in range(3)])
    hi = Vector([max(v[i] for v in p.verts) for i in range(3)])
    return sorted(hi - lo)


def hulls(parts, inset=0.0, min_len=0.0, min_thick=0.0):
    """Une enveloppe par pièce assez grande (plus grande dimension >= min_len, plus petite >= min_thick)."""
    out = []
    for p in parts:
        e = extent(p)
        if e[2] < min_len or e[0] < min_thick:
            continue
        h = hull(p, inset)
        if h is not None:
            out.append(h)
    return out


def roadway_parts(parts):
    """Surfaces praticables : dessus des massifs, de la dalle de l'armoire et des paliers (normale vers le haut)."""
    out = []
    for p in parts:
        if p.mat not in ("concrete", "grating"):
            continue
        for f in p.faces:
            vs = [p.verts[i] for i in f]
            if len(vs) < 3:
                continue
            n = (vs[1] - vs[0]).cross(vs[2] - vs[0])
            if n.length > 1e-9 and n.normalized().z > 0.9:
                out.append(Part(vs, [tuple(range(len(vs)))], p.mat, p.kind, p.gid, True))
    return out


def lod_parts(fn, detail, ruin, height, seed, split):
    """Pièces d'une LOD ; ruine : tronçons fermés séparés (pieces) pliés avec le pylône."""
    B = Builder(detail, split if ruin else 0.0, pieces=ruin)
    lat = fn(B)
    parts = B.parts
    if ruin:
        parts = collapse_parts(parts, Collapse(lat, height, seed), seed)
    return parts, lat


def export_model(name, fn, height, ruin, mats):
    clear_scene()
    seed = sum(map(ord, name))
    objs = []
    for res, detail in ((1, 0), (4, 1), (12, 2), (30, 3)):
        parts, lat = build_parts(fn, detail, (2.0, 3.0, 4.0, 6.0)[detail] if ruin else 0.0)
        if ruin:
            parts = collapse_parts(parts, Collapse(lat, height, seed), seed)
        ob = make_mesh(f"{name}_lod{res}", parts, mats)
        objs.append(set_lod(ob, 0, res))
    # Ombres : une enveloppe convexe fermée par pièce, rentrée de 6 mm sous la surface visible. Volumes ouverts
    # (paraboles, révolutions à pointe) et volumes collés aux faces visibles donnaient des bandes claires et
    # sombres sur les massifs et le pied du pylône.
    # Ruine (au sol, vue de près) : le pylône simplifié suffit, le fichier reste léger.
    # Debout : ombre proche tirée de la LOD 4 (échelle, chemins de câbles, garde-corps, haubans visibles au sol).
    for res, detail in ((0, 3 if ruin else 1), (10, 3)):
        parts, _lat = lod_parts(fn, detail, ruin, height, seed, (4.0, 4.0, 4.0, 6.0)[detail])
        shadow = mesh_from_parts(f"{name}_shadow{res}", hulls(solid(parts), 0.006, 0.08))
        set_lod(shadow, 4, res)
    # Géométrie (collisions, sélection Eden) : chaque pièce du pylône simplifié, jambes, croisillons, antennes, massifs
    # et armoire à leur vraie taille (plus de grande boîte invisible en tête, plus de croisillons traversables).
    gp_all, _lat = lod_parts(fn, 3, ruin, height, seed, 4.0)
    gp = solid(gp_all)
    props = [("autocenter", "0"), ("map", "transmitter")] if not ruin else [("autocenter", "0")]
    geo = mesh_from_parts(name + "_geo", hulls(gp + fence_walls(gp_all), 0.0, 0.12))
    add_mass(geo, 12000.0 if not ruin else 9000.0)
    set_lod(geo, 6, 0, props)
    # Tir : tout le treillis presque complet (les balles touchent les cornières, passent entre elles).
    fp = gp if ruin else solid(lod_parts(fn, 2, ruin, height, seed, 4.0)[0])
    fire = mesh_from_parts(name + "_fire", hulls(fp, 0.0, 0.05))
    set_lod(fire, 15, 0)
    # Vue (IA) : seulement le plein (massifs, armoire, panneaux, paraboles) ; le treillis ne cache rien.
    view = mesh_from_parts(name + "_view", hulls(gp, 0.0, 0.2, 0.12))
    set_lod(view, 14, 0)
    # Chemin praticable : dessus des massifs, dalle de l'armoire, paliers.
    if not ruin:
        rw = mesh_from_parts(name + "_roadway", roadway_parts(build_parts(fn, 3)[0]))
        set_lod(rw, 11, 0)
    if not ruin:
        B = Builder(0)
        fn(B)
        if B.mem:
            memory_lod(name + "_memory", B.mem)
    out = os.path.join(DATA, name + ("_ruin" if ruin else "") + ".p3d")
    bpy.context.view_layer.objects.active = objs[0]
    res = bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    print("export", res, out, sum(len(o.data.polygons) for o in objs[:1]), "faces LOD1")


# --------------------------------------------------------------------------------------------------------------------
# Aperçus (Cycles) : mêmes textures que le jeu, ciel physique, herbe.
# --------------------------------------------------------------------------------------------------------------------
def preview_materials():
    out = {}
    for k, (tex, rv, sc, rough, metal) in MATS.items():
        m = bpy.data.materials.new("pv_" + k)
        m.use_nodes = True
        nt, n = m.node_tree, m.node_tree.nodes
        b = n["Principled BSDF"]
        b.inputs["Roughness"].default_value = rough
        b.inputs["Metallic"].default_value = metal
        path = os.path.join(RENDER, tex + ("_ca.png" if k in ("net", "fence") else "_co.png"))
        if os.path.exists(path):
            img = n.new("ShaderNodeTexImage")
            img.image = bpy.data.images.load(path)
            nt.links.new(img.outputs["Color"], b.inputs["Base Color"])
            if k in ("net", "fence"):
                nt.links.new(img.outputs["Alpha"], b.inputs["Alpha"])
        # Relief et reflets : mêmes cartes _nohq / _smdi que le shader Super du jeu.
        nq = os.path.join(RENDER, tex + "_nohq.png")
        if os.path.exists(nq):
            ni = n.new("ShaderNodeTexImage")
            ni.image = bpy.data.images.load(nq)
            ni.image.colorspace_settings.name = "Non-Color"
            nm = n.new("ShaderNodeNormalMap")
            nt.links.new(ni.outputs["Color"], nm.inputs["Color"])
            nt.links.new(nm.outputs["Normal"], b.inputs["Normal"])
        sm = os.path.join(RENDER, tex + "_smdi.png")
        if os.path.exists(sm):
            si = n.new("ShaderNodeTexImage")
            si.image = bpy.data.images.load(sm)
            si.image.colorspace_settings.name = "Non-Color"
            sep = n.new("ShaderNodeSeparateColor")
            nt.links.new(si.outputs["Color"], sep.inputs["Color"])
            inv = n.new("ShaderNodeMath")
            inv.operation = "SUBTRACT"
            inv.inputs[0].default_value = 1.0
            nt.links.new(sep.outputs["Blue"], inv.inputs[1])
            nt.links.new(inv.outputs[0], b.inputs["Roughness"])
        if k == "light":
            b.inputs["Base Color"].default_value = (1.0, 0.05, 0.02, 1)
            b.inputs["Emission Color"].default_value = (1.0, 0.05, 0.02, 1)
            b.inputs["Emission Strength"].default_value = 30.0
        out[k] = m
    return out


def scene_setup():
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.look = "AgX - Medium High Contrast"
    sc.view_settings.exposure = float(os.environ.get("EXPOSURE", "-2.2"))
    world = bpy.data.worlds.new("sky")
    sc.world = world
    world.use_nodes = True
    nt = world.node_tree
    bg = nt.nodes["Background"]
    sky = nt.nodes.new("ShaderNodeTexSky")
    for t in ("MULTIPLE_SCATTERING", "SINGLE_SCATTERING", "NISHITA", "HOSEK_WILKIE"):
        try:
            sky.sky_type = t
            break
        except TypeError:
            continue
    try:
        sky.sun_elevation = math.radians(38)
        sky.sun_rotation = math.radians(215)
    except AttributeError:
        pass
    nt.links.new(sky.outputs["Color"], bg.inputs["Color"])
    bg.inputs["Strength"].default_value = 0.35
    sun = bpy.data.lights.new("sun", "SUN")
    sun.energy = 4.0
    sun.angle = math.radians(0.6)
    so = bpy.data.objects.new("sun", sun)
    so.rotation_euler = (math.radians(52), 0, math.radians(215 - 180 + 90))
    sc.collection.objects.link(so)
    return sc


def ground(size=400):
    me = bpy.data.meshes.new("ground")
    s = size / 2
    me.from_pydata([(-s, -s, 0), (s, -s, 0), (s, s, 0), (-s, s, 0)], [], [(0, 1, 2, 3)])
    ob = bpy.data.objects.new("ground", me)
    bpy.context.collection.objects.link(ob)
    m = bpy.data.materials.new("grass")
    m.use_nodes = True
    nt, n = m.node_tree, m.node_tree.nodes
    b = n["Principled BSDF"]
    b.inputs["Roughness"].default_value = 0.95
    tc = n.new("ShaderNodeTexCoord")
    nz = n.new("ShaderNodeTexNoise")
    nz.inputs["Scale"].default_value = 0.6
    nz.inputs["Detail"].default_value = 8.0
    nt.links.new(tc.outputs["Object"], nz.inputs["Vector"])
    ramp = n.new("ShaderNodeValToRGB")
    ramp.color_ramp.elements[0].color = (0.035, 0.05, 0.018, 1)
    ramp.color_ramp.elements[1].color = (0.11, 0.12, 0.05, 1)
    nt.links.new(nz.outputs["Fac"], ramp.inputs["Fac"])
    nt.links.new(ramp.outputs["Color"], b.inputs["Base Color"])
    me.materials.append(m)
    return ob


def camera(sc, loc, target, lens, res):
    cd = bpy.data.cameras.new("cam")
    cd.lens = lens
    cd.clip_end = 2000
    co = bpy.data.objects.new("cam", cd)
    sc.collection.objects.link(co)
    co.location = loc
    d = Vector(target) - Vector(loc)
    co.rotation_euler = d.to_track_quat("-Z", "Y").to_euler()
    sc.camera = co
    sc.render.resolution_x, sc.render.resolution_y = res
    return co


def render(sc, path, fmt="PNG"):
    sc.render.image_settings.file_format = fmt
    if fmt == "JPEG":
        sc.render.image_settings.quality = 88
    sc.render.filepath = path
    bpy.ops.render.render(write_still=True)
    print("render", path)


def previews():
    clear_scene()
    sc = scene_setup()
    pm = preview_materials()
    ground()
    place = {"relay_tower_30": (-22, 0), "relay_tower_40": (0, 6), "relay_tower_50": (24, 0),
             "relay_tower_m12": (-40, 4), "relay_tower_m20": (44, 4)}
    objs, mem = {}, {}
    for name, (fn, h, _r) in TOWERS.items():
        if os.environ.get("RUINS_ONLY") == "1":
            break
        B = Builder(0)
        fn(B)
        ob = make_mesh(name, B.parts, pm)
        ob.location = (*place[name], 0)
        objs[name] = ob
        mem[name] = [p + Vector((*place[name], 0)) for _n, p in B.mem]
    if objs:
        night_preview(sc, pm, mem)
        intact_previews(sc, objs, place)
    ruin_previews(sc, pm)


def night_preview(sc, pm, mem):
    """Vue d'ensemble de nuit : ciel éteint, clair de lune bleuté, feux d'obstacle rouges (comme MarkerLights)."""
    sun = bpy.data.objects["sun"]
    bg = sc.world.node_tree.nodes["Background"]
    old = (sun.data.energy, bg.inputs["Strength"].default_value, sc.view_settings.exposure)
    sun.data.energy = 0.03
    sun.data.color = (0.6, 0.7, 1.0)
    bg.inputs["Strength"].default_value = 0.004
    sc.view_settings.exposure = 0.5
    pm["light"].node_tree.nodes["Principled BSDF"].inputs["Emission Strength"].default_value = 60.0
    lights = []
    for pts in mem.values():
        for p in pts:
            ld = bpy.data.lights.new("red", "POINT")
            ld.color = (1.0, 0.08, 0.04)
            ld.energy = 120.0
            ld.shadow_soft_size = 0.05
            lo = bpy.data.objects.new("red", ld)
            lo.location = p + Vector((0, 0, 0.25))
            sc.collection.objects.link(lo)
            lights.append(lo)
    camera(sc, (2, -95, 9), (2, 0, 22), 32, (1800, 1100))
    render(sc, os.path.join(RENDER, "tower_family_night.png"))
    for lo in lights:
        bpy.data.objects.remove(lo, do_unlink=True)
    sun.data.energy, bg.inputs["Strength"].default_value, sc.view_settings.exposure = old
    sun.data.color = (1, 1, 1)
    pm["light"].node_tree.nodes["Principled BSDF"].inputs["Emission Strength"].default_value = 30.0
    sc.camera = None


def intact_previews(sc, objs, place):
    # Vue d'ensemble, puis gros plans en contre-plongée (comme les photos de référence).
    camera(sc, (2, -95, 9), (2, 0, 22), 32, (1800, 1100))
    render(sc, os.path.join(RENDER, "tower_family.png"))
    for name, cam, tgt, lens in (("relay_tower_30", (-14, -9, 16), (-22, 0, 27), 45),
                                 ("relay_tower_40", (8, -6, 25), (0, 6, 37), 40),
                                 ("relay_tower_50", (34, -12, 34), (24, 0, 46), 42),
                                 ("relay_tower_m12", (-34, -6, 8), (-40, 4, 10), 40),
                                 ("relay_tower_m20", (50, -8, 13), (44, 4, 18), 40)):
        sc.camera = None
        camera(sc, cam, tgt, lens, (1200, 1200))
        render(sc, os.path.join(RENDER, f"tower_{name[-2:]}_top.png"))
    # Aperçus Eden (455 x 256) : un pylône seul.
    for name, ob in objs.items():
        for o in objs.values():
            o.hide_render = o is not ob
        x, y = place[name]
        h = TOWERS[name][1]
        camera(sc, (x + h * 0.7, y - h * 1.8, h * 0.3), (x, y, h * 0.5), 35, (455, 256))
        render(sc, os.path.join(DATA, f"{name}_preview.jpg"), "JPEG")
    for o in objs.values():
        bpy.data.objects.remove(o, do_unlink=True)


def ruin_previews(sc, pm):
    # Ruines : une vue de trois quarts par pylône abattu.
    for name, (fn, h, _r) in TOWERS.items():
        parts, lat = build_parts(fn, 0, 2.0)
        seed = sum(map(ord, name))
        col = Collapse(lat, h, seed)
        ob = make_mesh(name + "_ruin", collapse_parts(parts, col, seed), pm)
        tip = col.world(Vector((h * 0.45, 0, 0)))
        camera(sc, tuple(tip + col.side * (h * 0.85) + Vector((0, 0, h * 0.3))), tuple(tip), 30, (1400, 900))
        render(sc, os.path.join(RENDER, f"tower_{name[-2:]}_ruin.png"))
        bpy.data.objects.remove(ob, do_unlink=True)


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    only = os.environ.get("ONLY")
    if os.environ.get("NO_EXPORT") != "1":
        mats = a3_materials()
        for name, (fn, h, _r) in TOWERS.items():
            if only and only not in name:
                continue
            export_model(name, fn, h, False, mats)
            export_model(name, fn, h, True, mats)
    if os.environ.get("NO_PREVIEW") != "1":
        previews()


if __name__ == "__main__":
    main()
