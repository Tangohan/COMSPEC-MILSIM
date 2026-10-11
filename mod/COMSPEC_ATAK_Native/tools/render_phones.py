"""
Rendus 3D (Blender / Cycles) des trois cadres alternatifs du terminal ATAK : smartphone durci (« rugged »), smartphone
premium fin (« iphone ») et tablette véhicule durcie (« tablet »). Appareils génériques : aucune marque ni logo réel.
Chaque appareil donne une face (paysage, trou d'écran transparent où le jeu incruste l'interface) et un dos (miroir
horizontal de la face : la silhouette se superpose une fois retournée). Toiles 2048 x 2048 RGBA.
Prérequis : pip install bpy (Blender comme module Python, 5.x) ; réutilise les aides de render_3d.py.

    python3 render_phones.py [sortie]      # par défaut tools/render/
    RENDER_ONLY=rugged|iphone|tablet|all   RENDER_SAMPLES=64

Sorties : phone_<appareil>_front.png / phone_<appareil>_back.png.
Contrat de la face : alpha 0 exactement sur x 455..1546, y 716..1333 (inclus, y vers le bas), alpha 255 sur un anneau
d'au moins 6 px autour ; hors de l'appareil, alpha 0. Repères : 1 unité Blender = 100 px, X à droite, Y en haut ; la
face regarde +Z (caméra au-dessus), le dos regarde -Z (caméra en dessous, d'où le miroir horizontal).
"""
import math
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "render")
os.environ.setdefault("RENDER_SAMPLES", "64")
sys.argv = [sys.argv[0], OUT]  # render_3d lit sys.argv[1] à l'import
sys.path.insert(0, HERE)
sys.dont_write_bytecode = True

import bpy  # noqa: E402,I001 (bmesh et mathutils ne se chargent qu'après bpy)
import bmesh  # noqa: E402
from mathutils import Vector  # noqa: E402

import render_3d as r3  # noqa: E402

FONT_B, FONT_R = r3.FONT_B, r3.FONT_R
SIZE = 2048
HOLE = (455, 716, 1546, 1333)  # rectangle transparent de l'écran (inclus)
RING = 6  # marge opaque minimale autour du trou
BOUNDS = {"rugged": (200, 560, 1900, 1500), "iphone": (200, 560, 1900, 1500), "tablet": (150, 480, 1950, 1580)}
ACCENT = (0.80, 0.43, 0.13)  # orange mat des touches et rondelles du modèle durci


# --- Repère de vue : coordonnées image (px, y vers le bas) -> scène ---
class View:
    """Pose les pièces en pixels de l'image rendue. h = hauteur au-dessus de la face (BU) ; le corps occupe z -D..D.
    Vue de dos : X inversé (caméra sous l'appareil) et hauteurs comptées vers -Z ; les cotes du dos se donnent dans
    le même cadre que la face (corps aux mêmes u), décalées de du pour que le miroir tombe juste (cx = centre du corps)."""

    def __init__(self, back, D, cx=SIZE / 2):
        self.back = back
        self.s = -1 if back else 1
        self.D = D
        self.du = 2 * (SIZE / 2 - cx) if back else 0.0

    def X(self, u):
        return self.s * (u + self.du - SIZE / 2) / 100.0

    def Y(self, v):
        return (SIZE / 2 - v) / 100.0

    def Z(self, h):
        return self.s * (self.D + h)

    def zz(self, h0, h1):
        a, b = self.Z(h0), self.Z(h1)
        return min(a, b), max(a, b)

    def box(self, u0, v0, u1, v1, r, h0, h1, mat, bevel=0.02, name="box", seg=10):
        x = min(self.X(u0), self.X(u1))
        z0, z1 = self.zz(h0, h1)
        return r3.rrect(x, self.Y(v0), abs(u1 - u0) / 100, (v1 - v0) / 100, r / 100, z0, z1, mat, bevel, name, seg)

    def ring(self, outer, inner, r_o, r_i, h0, h1, mat, bevel=0.02, name="ring"):
        """Cadre : prisme arrondi évidé par un booléen (arêtes intérieures adoucies elles aussi)."""
        ob = self.box(*outer, r_o, h0, h1, mat, bevel=0, name=name)
        cut = self.box(*inner, r_i, h0 - 1, h1 + 1, mat, bevel=0, name=name + "_cut")
        for o in (ob, cut):  # rrect produit des normales rentrantes : à corriger pour le booléen
            bm = bmesh.new()
            bm.from_mesh(o.data)
            bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
            bm.to_mesh(o.data)
            bm.free()
        m = ob.modifiers.new("cut", "BOOLEAN")
        m.object = cut
        m.operation = "DIFFERENCE"
        m.solver = "EXACT"
        # Booléen appliqué tout de suite (un découpeur masqué au rendu n'est pas fiable) puis découpeur supprimé.
        ev = ob.evaluated_get(bpy.context.evaluated_depsgraph_get())
        me = bpy.data.meshes.new_from_object(ev)
        ob.modifiers.clear()
        ob.data = me
        bpy.data.objects.remove(cut)
        for p in me.polygons:
            p.use_smooth = True
        if bevel:
            b = ob.modifiers.new("bev", "BEVEL")
            b.width = bevel
            b.segments = 4
            b.limit_method = "ANGLE"
            b.angle_limit = math.radians(50)
            b.harden_normals = True
        return ob

    def cyl(self, u, v, r, h0, h1, mat, verts=48, bevel=0.0):
        z0, z1 = self.zz(h0, h1)
        return r3.cyl(self.X(u), self.Y(v), r / 100, z0, z1, mat, verts=verts, bevel=bevel)

    def dome(self, u, v, r, h, height, mat):
        return r3.dome(self.X(u), self.Y(v), r / 100, self.Z(h), height, mat)

    def text(self, s, u, v, h, size, mat, font=FONT_B, align="CENTER", rot=0.0, extrude=0.003):
        ob = r3.text(s, self.X(u), self.Y(v), self.Z(h), size / 100, mat, font=font, align=align, extrude=extrude)
        ob.data.align_y = "CENTER"
        # Rotation dans le plan de l'image ; de dos, le texte est retourné pour se lire depuis -Z.
        ob.rotation_euler = (0, math.pi, -rot) if self.back else (0, 0, rot)
        return ob

    def torx(self, u, v, r, h, metal, dark, washer=None):
        """Vis à tête cylindrique et empreinte six lobes (rondelle colorée en option)."""
        if washer is not None:
            self.cyl(u, v, r * 1.5, h - 0.01, h + 0.025, washer, bevel=0.01)
            h += 0.02
        self.cyl(u, v, r, h, h + 0.05, metal, bevel=r * 0.22 / 100)
        self.cyl(u, v, r * 0.30, h + 0.045, h + 0.056, dark, verts=24)
        for k in range(6):
            a = k * math.pi / 3
            self.cyl(u + r * 0.30 * math.cos(a), v + r * 0.30 * math.sin(a), r * 0.13, h + 0.045, h + 0.056, dark, verts=12)

    def lens(self, u, v, r, h, ring, black, coat, ring_h=0.12):
        """Objectif : bague, verre noir, iris et traitement antireflet bleuté bombé."""
        self.cyl(u, v, r, h, h + ring_h, ring, verts=96, bevel=0.015)
        self.cyl(u, v, r * 0.80, h + ring_h - 0.01, h + ring_h + 0.01, black, verts=96)
        self.cyl(u, v, r * 0.55, h + ring_h, h + ring_h + 0.015, r3.mat_simple("iris", (0.05, 0.05, 0.06), 0.3, 0.6), verts=64)
        self.dome(u, v, r * 0.42, h + ring_h + 0.01, r * 0.10 / 100, coat)


# --- Matériaux supplémentaires ---
def mat_glass(name, color=(0.008, 0.009, 0.012), rough=0.04, coat_rough=0.22):
    """Verre noir laqué : vernis légèrement diffusant (reflet doux de la boîte à lumière, pas de tache nette)."""
    m = r3.mat_simple(name, color, rough, coat=1.0)
    m.node_tree.nodes["Principled BSDF"].inputs["Coat Roughness"].default_value = coat_rough
    return m


def mat_frosted(name, color):
    """Verre dépoli (dos premium) : satiné, micro-grain."""
    m = r3.mat_paint(name, color, rough=0.42, grain=0.25)
    m.node_tree.nodes["Principled BSDF"].inputs["Coat Weight"].default_value = 0.6
    m.node_tree.nodes["Principled BSDF"].inputs["Coat Roughness"].default_value = 0.35
    return m


def mat_grip(name, color, scale=5.0, strength=0.35):
    """Caoutchouc à motif losange (damier tourné de 45°) en relief."""
    m = r3.mat_paint(name, color, rough=0.78, grain=1.2)
    nt = m.node_tree
    n = nt.nodes
    tc = n.new("ShaderNodeTexCoord")
    mp = n.new("ShaderNodeMapping")
    mp.inputs["Rotation"].default_value = (0, 0, math.radians(45))
    mp.inputs["Scale"].default_value = (scale, scale, scale)
    ck = n.new("ShaderNodeTexChecker")
    ck.inputs["Scale"].default_value = 1.0
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = strength
    bump.inputs["Distance"].default_value = 0.03
    nt.links.new(tc.outputs["Object"], mp.inputs["Vector"])
    nt.links.new(mp.outputs["Vector"], ck.inputs["Vector"])
    nt.links.new(ck.outputs["Fac"], bump.inputs["Height"])
    old = n["Principled BSDF"].inputs["Normal"].links[0].from_node  # bump de grain de mat_paint, chaîné
    nt.links.new(old.outputs["Normal"], bump.inputs["Normal"])
    nt.links.new(bump.outputs["Normal"], n["Principled BSDF"].inputs["Normal"])
    return m


def label_png(path, back):
    """Étiquette signalétique de la tablette (inversée pour la vue de dos, UV liées à X)."""
    from PIL import Image, ImageDraw, ImageFont, ImageOps
    w, h = 520, 180
    im = Image.new("RGB", (w, h), (206, 204, 192))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 0, w - 1, 44), fill=(28, 30, 26))
    d.text((16, 22), "COMSPEC VT-10", font=ImageFont.truetype(FONT_B, 30), fill=(222, 220, 205), anchor="lm")
    d.text((w - 16, 22), "RUGGED TERMINAL", font=ImageFont.truetype(FONT_R, 18), fill=(170, 170, 160), anchor="rm")
    fm = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 18)
    d.text((16, 66), "S/N VT10-2291-0473", font=fm, fill=(40, 40, 40), anchor="lm")
    d.text((16, 92), "12-28 V DC  3.5 A", font=fm, fill=(40, 40, 40), anchor="lm")
    d.text((16, 118), "IP65  MIL-STD-810H", font=fm, fill=(40, 40, 40), anchor="lm")
    x, i = 300, 0
    while x < w - 20:
        bw = 2 + (i * 7) % 4
        d.rectangle((x, 58, x + bw, 140), fill=(20, 20, 20))
        x += bw + 2 + (i * 3) % 4
        i += 1
    d.text((w / 2, 162), "NSN 7010-99-COM-0010", font=ImageFont.truetype(FONT_R, 16), fill=(70, 70, 70), anchor="mm")
    if back:
        im = ImageOps.mirror(im)
    im.save(path)


# --- Éclairage et caméra (face : au-dessus ; dos : en dessous, image miroir) ---
def lights(view, power=3300, size=14, fill=560):
    """Boîte à lumière douce en haut à gauche de l'image (comme render_3d), débouchage opposé."""
    s = view.s
    for nm, loc, en, sz in (("key", (-6 * s, 9, 16 * s), power, size), ("fill", (6 * s, -5, 9 * s), fill, 10)):
        l = bpy.data.lights.new(nm, "AREA")
        l.energy = en
        l.size = sz
        o = bpy.data.objects.new(nm, l)
        o.location = loc
        o.rotation_euler = (Vector((0, 0, 0)) - Vector(loc)).to_track_quat("-Z", "Y").to_euler()
        bpy.context.collection.objects.link(o)


def camera(view):
    r3.camera(0, 0, SIZE / 100, SIZE, SIZE)
    cam = bpy.context.scene.camera
    if view.back:
        cam.location = (0, 0, -30)
        cam.rotation_euler = (0, math.pi, 0)  # regarde +Z ; la droite de l'image devient -X


# --- 1. Smartphone durci ---
def rugged(back):
    D = 0.55
    F, V = View(False, D), View(back, D, 1010)  # F : coque commune (coordonnées de la face) ; V : détails de la vue
    rubber = r3.mat_paint("rubber", (0.115, 0.12, 0.12), rough=0.74, grain=1.6)
    rim = r3.mat_paint("rim", (0.165, 0.17, 0.17), rough=0.62, grain=1.0)
    bump = mat_grip("bumper", (0.10, 0.104, 0.104), scale=7.0, strength=0.6)
    accent = r3.mat_paint("accent", ACCENT, rough=0.55, grain=0.8)
    gun = r3.mat_simple("gun", (0.36, 0.37, 0.38), 0.3, 1.0)
    dark = r3.mat_simple("dark", (0.02, 0.02, 0.02), 0.5)
    glass = mat_glass("glass")
    # Coque : bloc caoutchouc à flancs arrondis, butoirs d'angle, nervures de préhension, touches latérales.
    F.box(250, 600, 1770, 1450, 125, -2 * D, 0, rubber, bevel=0.14, name="body")
    for u0, v0 in ((240, 590), (1580, 590), (240, 1260), (1580, 1260)):
        F.box(u0, v0, u0 + 200, v0 + 200, 92, -2 * D - 0.2, 0.2, bump, bevel=0.13, name="bumper")
        F.box(u0 - 6, v0 - 6, u0 + 206, v0 + 206, 96, -2 * D - 0.1, 0.1, accent, bevel=0.05, name="bumper_trim")
    for ux in (243, 1771):
        for k in range(16):
            F.box(ux, 812 + k * 28, ux + 6, 826 + k * 28, 3, -2 * D + 0.15, -0.15, bump, bevel=0.02)
    F.box(1160, 588, 1330, 602, 6, -D - 0.22, -D + 0.22, accent, bevel=0.03, name="volume")
    F.box(1380, 588, 1460, 602, 6, -D - 0.22, -D + 0.22, rim, bevel=0.03, name="power")
    F.box(760, 1448, 900, 1463, 7, -D - 0.25, -D + 0.25, accent, bevel=0.035, name="ptt")
    F.box(1610, 1448, 1660, 1458, 5, -D - 0.12, -D + 0.12, rim, bevel=0.02, name="sos")
    if not back:
        # Cadre surélevé autour d'une vitre plate pleine largeur.
        V.ring((250, 600, 1770, 1450), (291, 641, 1729, 1409), 125, 84, -0.05, 0.10, rim, bevel=0.035)
        V.box(292, 642, 1728, 1408, 84, -0.05, 0.05, glass, bevel=0.012, name="glass")
        V.box(443, 704, 1559, 1346, 4, 0.05, 0.052, mat_glass("screen", (0.004, 0.006, 0.01), 0.02))
        # Haut du téléphone à gauche : écouteur, caméra frontale, capteurs.
        grille = r3.mat_paint("grille", (0.05, 0.05, 0.05), rough=0.9, grain=5.0)
        V.box(316, 950, 334, 1100, 9, 0.03, 0.053, grille)
        V.cyl(392, 1175, 16, 0.05, 0.056, dark)
        V.dome(392, 1175, 10, 0.056, 0.03, r3.mat_simple("flens", (0.06, 0.08, 0.16), 0.05, coat=1.0))
        V.cyl(392, 880, 6, 0.05, 0.056, r3.mat_simple("prox", (0.05, 0.03, 0.04), 0.1, coat=1.0))
        # Bas du téléphone à droite : haut-parleur, marquage générique.
        V.box(1690, 930, 1706, 1120, 8, 0.03, 0.053, grille)
        V.text("X-TREM", 1625, 1025, 0.051, 34, r3.mat_simple("ink", (0.30, 0.31, 0.31), 0.5), rot=math.pi / 2)
        for u, v in ((312, 662), (1708, 662), (312, 1388), (1708, 1388)):
            V.torx(u, v, 14, 0.2, gun, dark, washer=accent)
    else:
        # Dos : plaque à motif losange, îlot photo côté haut (à droite en vue de dos), connecteur magnétique.
        V.ring((250, 600, 1770, 1450), (300, 650, 1720, 1400), 125, 80, -0.05, 0.10, rim, bevel=0.035)
        V.box(301, 651, 1719, 1399, 80, -0.05, 0.04, mat_grip("grip", (0.10, 0.105, 0.105), strength=0.8), bevel=0.02)
        V.box(560, 760, 1300, 1290, 60, 0.03, 0.07, r3.mat_paint("panel", (0.13, 0.135, 0.135), rough=0.6), bevel=0.02)
        V.text("X-TREM", 930, 1025, 0.07, 80, r3.mat_paint("emb", (0.17, 0.175, 0.175), rough=0.55), rot=-math.pi / 2,
               extrude=0.012)
        V.text("IP68 · MIL-STD-810H", 760, 1025, 0.07, 24, r3.mat_simple("ink2", (0.36, 0.37, 0.37), 0.5),
               font=FONT_R, rot=-math.pi / 2)
        V.box(1410, 830, 1660, 1220, 70, 0.0, 0.16, gun, bevel=0.04, name="cam_trim")
        V.box(1424, 844, 1646, 1206, 58, 0.1, 0.19, mat_glass("camglass", (0.01, 0.01, 0.012)), bevel=0.02)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        V.lens(1535, 925, 52, 0.19, gun, dark, coat, ring_h=0.06)
        V.lens(1535, 1050, 40, 0.19, gun, dark, coat, ring_h=0.06)
        V.box(1508, 1130, 1562, 1170, 12, 0.19, 0.205, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8))
        V.box(430, 975, 486, 1075, 26, -0.02, 0.06, dark, bevel=0.02, name="xlink")
        gold = r3.mat_simple("gold", (0.85, 0.66, 0.30), 0.25, 1.0)
        for v in (1005, 1045):
            V.cyl(458, v, 9, 0.0, 0.07, gold, bevel=0.01)
        for u, v in ((312, 662), (1708, 662), (312, 1388), (1708, 1388), (1010, 690), (1010, 1360)):
            V.torx(u, v, 14 if u != 1010 else 12, 0.2 if u != 1010 else 0.1, gun, dark, washer=accent if u != 1010 else None)
    return {"power": 3300, "size": 14, "fill": 560}


# --- 2. Smartphone premium fin ---
def iphone(back):
    D = 0.42
    F, V = View(False, D), View(back, D, 1001)
    ti = r3.mat_brushed("titanium", (0.50, 0.49, 0.47), 0.30)
    dark = r3.mat_simple("dark", (0.01, 0.01, 0.01), 0.3)
    # Cadre titane aux flancs arrondis, boutons affleurants.
    F.box(393, 654, 1609, 1396, 130, -2 * D, 0, ti, bevel=0.16, name="frame", seg=16)
    F.box(700, 648, 900, 660, 6, -D - 0.16, -D + 0.16, ti, bevel=0.04, name="power")
    F.box(520, 1390, 580, 1402, 6, -D - 0.14, -D + 0.14, ti, bevel=0.04, name="action")
    F.box(650, 1390, 760, 1402, 6, -D - 0.14, -D + 0.14, ti, bevel=0.04, name="vol_up")
    F.box(790, 1390, 900, 1402, 6, -D - 0.14, -D + 0.14, ti, bevel=0.04, name="vol_dn")
    # Coupures d'antenne (bandes polymère) sur la tranche.
    plastic = r3.mat_simple("antenna", (0.10, 0.10, 0.10), 0.5)
    for u, v0, v1 in ((560, 652, 662), (1440, 652, 662), (1440, 1388, 1398)):
        F.box(u, v0, u + 5, v1, 1, -2 * D + 0.1, -0.1, plastic, bevel=0)
    if not back:
        V.box(409, 670, 1593, 1380, 114, -0.06, 0.04, mat_glass("bezel", (0.006, 0.006, 0.008), 0.03), bevel=0.012, seg=16)
        V.box(443, 704, 1559, 1346, 4, 0.04, 0.042, mat_glass("screen", (0.003, 0.004, 0.008), 0.02))
        # Découpe pilule (caméra frontale) près du bord court, haut du téléphone à gauche.
        V.box(421, 972, 445, 1078, 12, 0.04, 0.046, r3.mat_simple("pill", (0.0, 0.0, 0.0), 0.08, coat=1.0))
        V.cyl(433, 1056, 7, 0.046, 0.05, r3.mat_simple("flens", (0.05, 0.07, 0.16), 0.03, coat=1.0))
        V.box(400, 990, 405, 1060, 2, 0.0, 0.045, dark, bevel=0)  # fente d'écouteur entre cadre et verre
    else:
        V.box(409, 670, 1593, 1380, 114, -0.06, 0.03, mat_frosted("backglass", (0.20, 0.205, 0.21)), bevel=0.01, seg=16)
        # Bloc photo carré dans le coin haut (droite de la vue de dos), verre poli légèrement teinté.
        V.box(1262, 696, 1566, 1000, 86, 0.0, 0.10, mat_glass("plateau", (0.16, 0.165, 0.17), 0.12), bevel=0.04, seg=16)
        coat = r3.mat_simple("coat", (0.12, 0.10, 0.28), 0.03, coat=1.0)
        S0u, S0v, S = 1262, 696, 304
        for fu, fv in ((0.72, 0.28), (0.28, 0.28), (0.50, 0.72)):
            V.lens(S0u + fu * S, S0v + fv * S, 58, 0.10, ti, dark, coat, ring_h=0.16)
        V.cyl(S0u + 0.80 * S, S0v + 0.76 * S, 20, 0.10, 0.12, r3.mat_simple("flash", (0.92, 0.88, 0.72), 0.3, coat=0.8))
        V.cyl(S0u + 0.17 * S, S0v + 0.76 * S, 20, 0.10, 0.12, mat_glass("lidar", (0.02, 0.02, 0.03)))
        V.cyl(S0u + 0.50 * S, S0v + 0.40 * S, 5, 0.10, 0.11, dark)
    return {"power": 3000, "size": 14, "fill": 520}


# --- 3. Tablette véhicule durcie ---
def tablet(back):
    D = 0.9
    F, V = View(False, D), View(back, D, 1035)
    shell = r3.mat_paint("shell", (0.088, 0.096, 0.068), rough=0.7, grain=1.6)
    bump = r3.mat_paint("bumper", (0.068, 0.074, 0.052), rough=0.82, grain=2.4)
    keym = r3.mat_paint("key", (0.10, 0.105, 0.095), rough=0.7, grain=1.0)
    steel = r3.mat_simple("steel", (0.55, 0.56, 0.55), 0.32, 1.0)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    ink = r3.mat_simple("ink", (0.78, 0.78, 0.72), 0.55)
    F.box(200, 540, 1870, 1520, 80, -2 * D, 0, shell, bevel=0.12, name="body")
    for u0, v0 in ((175, 515), (1675, 515), (175, 1325), (1675, 1325)):
        F.box(u0, v0, u0 + 220, v0 + 220, 80, -2 * D - 0.25, 0.25, bump, bevel=0.09, name="bumper")
    # Caches de prises (caoutchouc) sur la tranche basse, boucle de stylet sur la tranche haute.
    for u0, u1 in ((640, 860), (920, 1060), (1120, 1250)):
        F.box(u0, 1512, u1, 1528, 8, -D - 0.35, -D + 0.35, bump, bevel=0.04, name="port")
    F.box(1280, 528, 1420, 548, 10, -D - 0.25, -D + 0.25, bump, bevel=0.05, name="tether")
    if not back:
        V.ring((200, 540, 1870, 1520), (415, 676, 1588, 1374), 80, 18, -0.05, 0.16, shell, bevel=0.05)
        V.box(416, 677, 1587, 1373, 18, -0.05, 0.0, mat_glass("glass", (0.008, 0.01, 0.01), 0.05), bevel=0.006)
        V.box(443, 704, 1559, 1346, 4, 0.0, 0.002, mat_glass("screen", (0.004, 0.006, 0.008), 0.03))
        # Colonne de touches physiques (bord droit).
        for i, lab in enumerate(("P1", "P2", "P3")):
            v = 760 + i * 86
            V.box(1670, v, 1790, v + 64, 18, 0.12, 0.25, keym, bevel=0.035)
            V.text(lab, 1730, v + 32, 0.252, 30, ink)
        for i, lab in enumerate(("☀−", "☀+")):
            v = 1040 + i * 86
            V.box(1670, v, 1790, v + 64, 18, 0.12, 0.25, keym, bevel=0.035)
            V.text(lab, 1730, v + 32, 0.252, 30, ink, font=FONT_R)
        V.cyl(1730, 1262, 40, 0.12, 0.25, keym, verts=64, bevel=0.03)
        # Symbole marche/arrêt : anneau ouvert et trait.
        V.cyl(1730, 1262, 17, 0.25, 0.256, ink, verts=48)
        V.cyl(1730, 1262, 12, 0.25, 0.258, keym, verts=48)
        V.box(1720, 1240, 1740, 1256, 3, 0.25, 0.259, keym, bevel=0)
        V.box(1727, 1238, 1733, 1262, 2, 0.25, 0.26, ink, bevel=0)
        V.text("PROG", 1620, 846, 0.161, 18, ink, font=FONT_R, rot=math.pi / 2)
        # Bord gauche : caméra frontale, capteur, grille de haut-parleur, voyants.
        V.cyl(305, 790, 18, 0.14, 0.17, dark)
        V.dome(305, 790, 11, 0.17, 0.03, r3.mat_simple("flens", (0.06, 0.08, 0.16), 0.05, coat=1.0))
        V.cyl(305, 735 + 120, 6, 0.15, 0.165, r3.mat_simple("als", (0.08, 0.06, 0.05), 0.1, coat=1.0))
        for i in range(5):
            for j in range(11):
                V.cyl(255 + i * 25 + (j % 2) * 12, 910 + j * 26, 6, 0.10, 0.165, dark, verts=16)
        for i, c in enumerate(((0.05, 0.25, 0.08), (0.30, 0.20, 0.03), (0.05, 0.10, 0.25))):
            V.dome(265 + i * 40, 1268, 7, 0.16, 0.025, r3.mat_simple(f"led{i}", c, 0.1, coat=1.0))
        # Plaque signalétique sur le bord haut.
        plate = r3.mat_brushed("plate", (0.62, 0.62, 0.60), 0.32)
        V.box(870, 586, 1130, 632, 6, 0.15, 0.175, plate, bevel=0.006)
        V.text("COMSPEC VT-10", 1000, 609, 0.176, 26, r3.mat_simple("ink_d", (0.04, 0.04, 0.04), 0.5))
        for u, v in ((262, 602), (1808, 602), (262, 1458), (1808, 1458)):
            V.torx(u, v, 17, 0.25, steel, dark)
        for u, v in ((480, 608), (1520, 608), (480, 1452), (1520, 1452)):
            V.torx(u, v, 11, 0.16, steel, dark)
    else:
        V.ring((200, 540, 1870, 1520), (300, 640, 1770, 1420), 80, 50, -0.05, 0.12, shell, bevel=0.04)
        V.box(301, 641, 1769, 1419, 50, -0.05, 0.06, mat_grip("grip", (0.07, 0.074, 0.062), scale=3.0, strength=0.2), bevel=0.02)
        # Trappe de batterie avec loquets (à gauche en vue de dos = côté touches de la face).
        V.box(1170, 690, 1700, 1370, 30, 0.04, 0.14, r3.mat_paint("batt", (0.09, 0.095, 0.08), rough=0.66, grain=1.2), bevel=0.03)
        for v in (760, 1300):
            V.box(1380, v - 22, 1520, v + 22, 10, 0.12, 0.2, keym, bevel=0.025)
            for k in range(5):
                V.box(1404 + k * 22, v - 14, 1410 + k * 22, v + 14, 2, 0.19, 0.215, bump, bevel=0.005)
        lab = os.path.join(OUT, "_vt10_label.png")
        label_png(lab, True)
        V.box(1230, 1080, 1670, 1232, 4, 0.14, 0.144, r3.mat_image("label", lab, 0.5), bevel=0)
        V.text("BATTERY", 1450, 1000, 0.141, 30, r3.mat_simple("ink3", (0.40, 0.40, 0.36), 0.5), font=FONT_R)
        # Grilles d'aération.
        for i in range(10):
            V.box(380 + i * 26, 760, 392 + i * 26, 1290, 6, 0.0, 0.07, dark, bevel=0.01)
        # Embase RAM-mount : plaque aluminium noir vissée, boule caoutchouc.
        alu = r3.mat_brushed("alu", (0.06, 0.062, 0.06), 0.36)
        V.box(690, 860, 1050, 1190, 44, 0.06, 0.2, alu, bevel=0.04)
        for u, v in ((730, 900), (1010, 900), (730, 1150), (1010, 1150)):
            V.cyl(u, v, 15, 0.19, 0.22, dark)
            r3.hexagon(V.X(u), V.Y(v), 0.09, *V.zz(0.2, 0.25), steel, rot=0.4)
        V.cyl(870, 1025, 96, 0.18, 0.34, alu, verts=96, bevel=0.03)
        ball = r3.mat_paint("ball", (0.035, 0.035, 0.035), rough=0.55, grain=0.6)
        V.cyl(870, 1025, 52, 0.30, 0.55, ball, verts=64)
        V.dome(870, 1025, 92, 0.85, 0.60, ball)
        for u, v in ((262, 602), (1808, 602), (262, 1458), (1808, 1458), (1050, 600), (1050, 1460), (330, 1025)):
            V.torx(u, v, 15 if v in (602, 1458) else 12, 0.25 if v in (602, 1458) else 0.12, steel, dark)
    return {"power": 3600, "size": 14, "fill": 600}


DEVICES = {"rugged": rugged, "iphone": iphone, "tablet": tablet}


# --- Post-traitement et contrôle ---
def punch(path):
    """Trou d'écran : alpha 0 dans le rectangle, assombrissement progressif du bord intérieur du cadre (14 px)."""
    import numpy as np
    from PIL import Image
    a = np.asarray(Image.open(path).convert("RGBA")).astype(np.float32)
    x0, y0, x1, y1 = HOLE
    yy, xx = np.mgrid[0:SIZE, 0:SIZE]
    dx = np.maximum(np.maximum(x0 - xx, xx - x1), 0)
    dy = np.maximum(np.maximum(y0 - yy, yy - y1), 0)
    d = np.sqrt(dx * dx + dy * dy)
    band = (d > 0) & (d <= 14)
    f = np.where(band, 0.45 + 0.55 * (d / 14.0) ** 1.4, 1.0)
    a[..., :3] *= f[..., None]
    a[y0:y1 + 1, x0:x1 + 1] = 0
    Image.fromarray(np.clip(a + 0.5, 0, 255).astype(np.uint8)).save(path)


def check(path, name, front):
    import numpy as np
    from PIL import Image
    al = np.asarray(Image.open(path))[..., 3]
    ys, xs = np.nonzero(al)
    bb = (int(xs.min()), int(ys.min()), int(xs.max()), int(ys.max()))
    bx0, by0, bx1, by1 = BOUNDS[name]
    ok = bx0 <= bb[0] and by0 <= bb[1] and bb[2] <= bx1 and bb[3] <= by1
    msg = f"{os.path.basename(path)} bbox={bb} bornes={'ok' if ok else 'HORS'}"
    if front:
        x0, y0, x1, y1 = HOLE
        hole_ok = al[y0:y1 + 1, x0:x1 + 1].max() == 0
        ringa = al[y0 - RING:y1 + 1 + RING, x0 - RING:x1 + 1 + RING].copy()
        ringa[RING:-RING, RING:-RING] = 255
        ring_ok = ringa.min() == 255
        ok = ok and hole_ok and ring_ok
        msg += f" trou={'ok' if hole_ok else 'NON'} anneau={'ok' if ring_ok else 'min %d' % ringa.min()}"
    print(msg)
    return ok


def render(name, back):
    r3.reset()
    params = DEVICES[name](back)
    view = View(back, 0)
    lights(view, **params)
    camera(view)
    path = os.path.join(OUT, f"phone_{name}_{'back' if back else 'front'}.png")
    bpy.context.scene.render.filepath = path
    bpy.ops.render.render(write_still=True)
    if not back:
        punch(path)
    tmp = os.path.join(OUT, "_vt10_label.png")
    if os.path.exists(tmp):
        os.remove(tmp)
    return check(path, name, not back)


if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    what = os.environ.get("RENDER_ONLY", "all")
    views = os.environ.get("RENDER_VIEWS", "front,back").split(",")
    good = True
    for nm in DEVICES:
        if what in ("all", nm):
            for v in views:
                good &= render(nm, v == "back")
    print("ok" if good else "ECHEC du contrôle")
    sys.exit(0 if good else 1)
