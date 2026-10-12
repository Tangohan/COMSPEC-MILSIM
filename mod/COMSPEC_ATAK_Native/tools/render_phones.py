"""
Rendus 3D (Blender / Cycles) des trois cadres alternatifs du terminal ATAK : smartphone en coque tactique
(« tactique », identifiant rugged), smartphone premium fin (« iphone ») et tablette véhicule durcie (« tablet »).
Appareils génériques : aucune marque ni logo réel.
Chaque appareil donne une face (paysage, trou d'écran transparent où le jeu incruste l'interface) et un dos (miroir
horizontal de la face : la silhouette se superpose une fois retournée). Toiles 2048 x 2048 RGBA.
Prérequis : pip install bpy (Blender comme module Python, 5.x) ; réutilise les aides de render_3d.py.

    python3 render_phones.py [sortie]      # par défaut tools/render/
    RENDER_ONLY=rugged|iphone|tablet|keys|all   RENDER_SAMPLES=64

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
BOUNDS = {"rugged": (200, 560, 1900, 1560), "iphone": (200, 560, 1900, 1500), "tablet": (150, 480, 1950, 1580), "keys": (150, 480, 1900, 1520)}
TAC_GREEN = (0.33, 0.34, 0.29)  # polymère vert ranger mat de la coque tactique
TAC_BLACK = (0.055, 0.056, 0.055)  # polymère noir (clip, liseré, patin dorsal)
KEY_SAND = (0.47, 0.44, 0.39)  # polymère gris sable de la tablette à touches


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


# --- 1. Smartphone en coque tactique ---
def mat_polymer(name, color, rough=0.66, grain=1.1, edge=0.35):
    """Polymère injecté mat : grain fin en relief et chanfreins plus clairs (usure, reprise de lumière). Le facteur
    d'usure vient de l'inclinaison de la normale (|N.z| < 1 sur les chanfreins), valable de face comme de dos."""
    m = r3.mat_paint(name, color, rough=rough, grain=grain)
    nt = m.node_tree
    n = nt.nodes
    bsdf = n["Principled BSDF"]
    base = bsdf.inputs["Base Color"].links[0].from_socket
    geo = n.new("ShaderNodeNewGeometry")
    sep = n.new("ShaderNodeSeparateXYZ")
    ab = n.new("ShaderNodeMath")
    ab.operation = "ABSOLUTE"
    ramp = n.new("ShaderNodeMapRange")
    ramp.inputs["From Min"].default_value = 0.97
    ramp.inputs["From Max"].default_value = 0.70
    ramp.inputs["To Max"].default_value = edge
    nt.links.new(geo.outputs["Normal"], sep.inputs["Vector"])
    nt.links.new(sep.outputs["Z"], ab.inputs[0])
    nt.links.new(ab.outputs[0], ramp.inputs["Value"])
    mix = n.new("ShaderNodeMix")
    mix.data_type = "RGBA"
    nt.links.new(ramp.outputs["Result"], mix.inputs["Factor"])
    nt.links.new(base, mix.inputs["A"])
    mix.inputs["B"].default_value = r3.srgb(tuple(min(1.0, c * 1.55 + 0.06) for c in color))
    nt.links.new(mix.outputs["Result"], bsdf.inputs["Base Color"])
    return m


def mat_stipple(name, color, scale=22.0, strength=0.8):
    """Texture pointillée du patin dorsal (grain de préhension moulé, cellules de Voronoï en relief)."""
    m = r3.mat_paint(name, color, rough=0.82, grain=1.4)
    nt = m.node_tree
    n = nt.nodes
    vo = n.new("ShaderNodeTexVoronoi")
    vo.feature = "F1"
    vo.inputs["Scale"].default_value = scale
    tc = n.new("ShaderNodeTexCoord")
    nt.links.new(tc.outputs["Object"], vo.inputs["Vector"])
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = strength
    bump.inputs["Distance"].default_value = 0.01
    nt.links.new(vo.outputs["Distance"], bump.inputs["Height"])
    old = n["Principled BSDF"].inputs["Normal"].links[0].from_node
    nt.links.new(old.outputs["Normal"], bump.inputs["Normal"])
    nt.links.new(bump.outputs["Normal"], n["Principled BSDF"].inputs["Normal"])
    return m


def mat_knurl(name, color, scale=34.0):
    """Métal anodisé sombre moleté (molette de blocage du rail)."""
    m = r3.mat_simple(name, color, 0.36, 0.85)
    nt = m.node_tree
    n = nt.nodes
    tc = n.new("ShaderNodeTexCoord")
    mp = n.new("ShaderNodeMapping")
    mp.inputs["Rotation"].default_value = (math.radians(45), 0, math.radians(45))
    mp.inputs["Scale"].default_value = (scale, scale, scale)
    ck = n.new("ShaderNodeTexChecker")
    ck.inputs["Scale"].default_value = 1.0
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.7
    bump.inputs["Distance"].default_value = 0.01
    nt.links.new(tc.outputs["Object"], mp.inputs["Vector"])
    nt.links.new(mp.outputs["Vector"], ck.inputs["Vector"])
    nt.links.new(ck.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], n["Principled BSDF"].inputs["Normal"])
    return m


def carve(ob, cutters, bevel, segments=2):
    """Évide ob par des découpeurs (booléen exact appliqué, matériau du découpeur transféré aux faces creusées :
    fonds de rainures sombres), puis chanfrein. ob et les découpeurs sont créés sans biseau."""
    for o in [ob] + cutters:
        bm = bmesh.new()
        bm.from_mesh(o.data)
        bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
        bm.to_mesh(o.data)
        bm.free()
    for i, c in enumerate(cutters):
        m = ob.modifiers.new(f"cut{i}", "BOOLEAN")
        m.object = c
        m.operation = "DIFFERENCE"
        m.solver = "EXACT"
        m.material_mode = "TRANSFER"
    ev = ob.evaluated_get(bpy.context.evaluated_depsgraph_get())
    me = bpy.data.meshes.new_from_object(ev)
    ob.modifiers.clear()
    ob.data = me
    for c in cutters:
        bpy.data.objects.remove(c)
    for p in me.polygons:
        p.use_smooth = True
    if bevel:
        b = ob.modifiers.new("bev", "BEVEL")
        b.width = bevel
        b.segments = segments
        b.limit_method = "ANGLE"
        b.angle_limit = math.radians(50)
        b.harden_normals = True
    return ob


def crisp(ob, segments=1):
    """Chanfrein vif (un segment) au lieu de l'arrondi par défaut de rrect."""
    ob.modifiers["bev"].segments = segments
    return ob


def hcyl(view, u0, u1, v, r, h, mat, verts=48, bevel=0.0):
    """Cylindre couché dans le plan de l'image (axe le long de u), centré à la hauteur h."""
    x0, x1 = view.X(u0), view.X(u1)
    zc, half = view.Z(h), abs(x1 - x0) / 2
    ob = r3.cyl((x0 + x1) / 2, view.Y(v), r / 100, zc - half, zc + half, mat, verts=verts, bevel=bevel)
    ob.rotation_euler = (0, math.pi / 2, 0)
    return ob


def rugged(back):
    """Coque tactique d'EUD : cadre polymère vert ranger chanfreiné, liseré noir fin autour de l'écran, embouts plus
    épais (rainures de préhension côté bas du téléphone), clip en haut, rail de fixation à ailettes et molette en bas."""
    D = 0.5
    F, V = View(False, D), View(back, D, 1001)  # F : coque commune (coordonnées de la face) ; V : détails de la vue
    frame = mat_polymer("frame", TAC_GREEN)
    cap = mat_polymer("cap", tuple(c * 1.06 for c in TAC_GREEN), rough=0.7, edge=0.4)
    black = mat_polymer("black", TAC_BLACK, rough=0.6, grain=1.0, edge=0.5)
    groove = r3.mat_simple("groove", (0.02, 0.021, 0.018), 0.85)
    anod = r3.mat_simple("anod", (0.07, 0.072, 0.075), 0.34, 0.85)
    glass = mat_glass("glass", (0.006, 0.007, 0.008), 0.03)
    # Cadre : prisme à coins modérément arrondis, fenêtre de l'écran (face) évidée, lèvre chanfreinée.
    # (cadre limité aux bords longs : les embouts le recouvrent, rien ne déborde dans les rainures)
    body = F.box(398, 625, 1604, 1425, 8, -2 * D - 0.06, 0.08, frame, bevel=0, name="frame")
    win = F.box(422, 684, 1579, 1365, 30, -0.02, 1.0, black, bevel=0, name="win")
    carve(body, [win], 0.07)
    # Embouts (bords courts) plus épais, débordant un peu du cadre : coins extérieurs arrondis, arête intérieure
    # droite ; rainures de préhension verticales sur l'embout droit (côté bas du téléphone), face et dos.
    lcap = F.box(310, 619, 560, 1431, 76, -2 * D - 0.12, 0.14, cap, bevel=0, name="cap_l")
    carve(lcap, [F.box(406, 500, 700, 1600, 1, -3, 3, cap, bevel=0, name="c")], 0.06)
    rcap = F.box(1442, 619, 1692, 1431, 76, -2 * D - 0.12, 0.14, cap, bevel=0, name="cap_r")
    cuts = [F.box(1300, 500, 1596, 1600, 1, -3, 3, cap, bevel=0, name="c")]
    for v0, v1 in ((730, 960), (1090, 1320)):
        cuts.append(F.box(1634, v0, 1654, v1, 10, 0.02, 1.0, groove, bevel=0, name="slot"))
        cuts.append(F.box(1634, v0, 1654, v1, 10, -2 * D - 1.0, -2 * D - 0.06, groove, bevel=0, name="slot"))
    carve(rcap, cuts, 0.05)
    # Touches latérales sur la tranche haute : boutons noirs encadrés de gardes moulées.
    for u0, u1 in ((1170, 1310), (1360, 1430)):
        F.box(u0, 613, u1, 630, 6, -D - 0.16, -D + 0.16, black, bevel=0.03, name="key")
        for g in (u0 - 22, u1 + 4):
            F.box(g, 607, g + 18, 630, 7, -D - 0.24, -D + 0.24, cap, bevel=0.04, name="guard")
    # Clip supérieur (noir), posé sur le dos.
    F.box(888, 590, 1112, 640, 14, -2 * D - 0.30, -0.30, black, bevel=0.035, name="clip")
    F.box(942, 563, 1058, 600, 16, -2 * D - 0.40, -0.36, black, bevel=0.035, name="clip_tab")
    for k in range(3):
        F.box(958, 570 + k * 9, 1042, 574 + k * 9, 2, -2 * D - 0.40, -0.345, black, bevel=0.008, name="clip_rib")
    # Rail de fixation inférieur : platine anodisée, bloc à ailettes (créneaux), molette moletée à droite.
    F.box(770, 1395, 1232, 1458, 14, -2 * D - 0.16, -0.62, anod, bevel=0.03, name="rail_plate")
    F.box(792, 1440, 1210, 1522, 16, -2 * D - 0.26, -0.46, groove, bevel=0.04, name="rail_block")
    for k in range(11):
        u = 802 + k * 37.6
        crisp(F.box(u, 1436, u + 22, 1550, 7, -2 * D - 0.40, -0.22, frame, bevel=0.045, name="fin"))
    knurl = mat_knurl("knurl", (0.05, 0.05, 0.055))
    hcyl(F, 1205, 1238, 1494, 33, -D - 0.2, anod, bevel=0.012)
    hcyl(F, 1234, 1306, 1494, 18, -D - 0.2, anod, bevel=0.005)
    hcyl(F, 1302, 1318, 1494, 32, -D - 0.2, anod, bevel=0.012)
    hcyl(F, 1314, 1440, 1494, 33, -D - 0.2, knurl, verts=64)
    for k in range(7):
        hcyl(F, 1320 + k * 16, 1330 + k * 16, 1494, 36, -D - 0.2, knurl, verts=64, bevel=0.02)
    hcyl(F, 1432, 1458, 1494, 36, -D - 0.2, anod, verts=64, bevel=0.07)
    if not back:
        # Verre noir (liseré fin et régulier autour de l'écran), dalle légèrement plus sombre.
        V.box(422, 684, 1579, 1365, 30, -0.06, 0.0, glass, bevel=0.008, name="glass")
        V.box(447, 708, 1554, 1341, 4, 0.0, 0.002, mat_glass("screen", (0.003, 0.004, 0.006), 0.02))
        # Haut du téléphone à gauche : caméra frontale dans le liseré.
        V.cyl(438, 1025, 8, 0.0, 0.004, r3.mat_simple("fcam", (0.0, 0.0, 0.0), 0.1, coat=1.0))
        V.dome(438, 1025, 4.5, 0.004, 0.02, r3.mat_simple("flens", (0.06, 0.08, 0.16), 0.05, coat=1.0))
        # Marquage moulé discret sur l'embout gauche.
        V.text("COMSPEC", 358, 1025, 0.142, 26, r3.mat_paint("emb", tuple(c * 0.82 for c in TAC_GREEN), rough=0.75),
               rot=math.pi / 2, extrude=0.004)
    else:
        # Dos : plaque d'adaptation chanfreinée, patin texturé noir, insert photo noir côté haut (à droite de dos).
        V.box(392, 660, 1300, 1390, 46, 0.0, 0.16, cap, bevel=0.035, name="plate")
        V.box(432, 694, 1262, 1356, 34, 0.1, 0.27, mat_stipple("pad", TAC_BLACK), bevel=0.05, name="pad")
        ink = r3.mat_paint("ink", (0.26, 0.265, 0.25), rough=0.8)
        V.text("COMSPEC", 470, 1322, 0.27, 24, ink, align="LEFT", extrude=0.004)
        V.text("COMSPEC", 846, 677, 0.16, 14, r3.mat_paint("emb", tuple(c * 0.8 for c in TAC_GREEN), rough=0.8),
               font=FONT_R, extrude=0.003)
        V.box(1336, 680, 1616, 1370, 38, 0.0, 0.14, black, bevel=0.03, name="cam_insert")
        V.box(1364, 724, 1588, 930, 30, 0.1, 0.17, mat_glass("camglass", (0.008, 0.008, 0.01), 0.06), bevel=0.012)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.30, 0.31, 0.32), 0.3, 1.0)
        dark = r3.mat_simple("dark", (0.02, 0.02, 0.02), 0.5)
        V.lens(1424, 800, 46, 0.15, gun, dark, coat, ring_h=0.05)
        V.lens(1528, 800, 46, 0.15, gun, dark, coat, ring_h=0.05)
        for u in (1450, 1502):
            V.cyl(u, 885, 11, 0.15, 0.175, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8), verts=32)
        V.text("TAC-EUD", 1476, 1320, 0.14, 20, ink, font=FONT_R, extrude=0.003)
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


# --- 4. Tablette durcie à touches (portée sur le JPC) ---
def keys(back):
    """Tablette d'EUD durcie : cadre polymère gris sable, ailes latérales avec un mini-joystick chacune, touches
    retour / accueil / apps récentes / fn à gauche, A / B / X / Y à droite, épaulements et patte de fixation en haut,
    molettes de blocage aux coins hauts. Le dos porte la charnière du rabat du JPC (bas) et la plaque d'adaptation."""
    D = 0.55
    F, V = View(False, D), View(back, D, 1025)
    frame = mat_polymer("frame", KEY_SAND, rough=0.7, edge=0.3)
    shoulder = mat_polymer("shoulder", tuple(c * 0.94 for c in KEY_SAND), rough=0.72, edge=0.35)
    well = mat_polymer("well", tuple(c * 0.62 for c in KEY_SAND), rough=0.8, edge=0.2)
    black = mat_polymer("black", TAC_BLACK, rough=0.6, grain=1.0, edge=0.5)
    rubber = r3.mat_paint("rubber", (0.032, 0.032, 0.034), rough=0.6, grain=0.8)
    fnkey = r3.mat_paint("fnkey", (0.20, 0.055, 0.045), rough=0.6, grain=0.8)
    ink = r3.mat_simple("ink", (0.90, 0.90, 0.88), 0.5)
    steel = r3.mat_simple("steel", (0.55, 0.56, 0.55), 0.32, 1.0)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    knurl = mat_knurl("knurl", (0.045, 0.045, 0.05))
    # Corps : prisme aux coins arrondis, fenêtre de l'écran évidée côté face.
    body = F.box(190, 600, 1860, 1440, 70, -2 * D, 0.12, frame, bevel=0, name="body")
    carve(body, [F.box(425, 690, 1576, 1360, 22, -0.02, 1.0, black, bevel=0, name="win")], 0.06)
    # Tranche haute : deux épaulements et la patte de fixation centrale (fente noire), comme sur la photo.
    for u0, u1 in ((372, 870), (1180, 1678)):
        F.box(u0, 572, u1, 618, 14, -2 * D + 0.12, 0.04, shoulder, bevel=0.04, name="shoulder")
    F.box(900, 512, 1150, 610, 18, -2 * D + 0.22, -0.04, shoulder, bevel=0.045, name="tab")
    F.box(950, 540, 1100, 566, 8, -2 * D + 0.18, 0.0, dark, bevel=0.01, name="tab_slot")
    # Lèvre basse et caches de prises.
    F.box(390, 1424, 1660, 1462, 14, -2 * D + 0.06, -0.08, shoulder, bevel=0.035, name="lip")
    for u0, u1 in ((560, 720), (1330, 1490)):
        F.box(u0, 1450, u1, 1470, 8, -D - 0.3, -D + 0.3, black, bevel=0.03, name="port")
    if not back:
        # Molettes de blocage aux coins hauts (de dos : simple pastille caoutchouc, voir plus bas).
        for u in (250, 1800):
            V.cyl(u, 648, 44, 0.0, 0.30, knurl, verts=64, bevel=0.02)
            V.cyl(u, 648, 30, 0.28, 0.36, rubber, verts=48, bevel=0.02)
        V.box(425, 690, 1576, 1360, 22, -0.05, 0.0, mat_glass("glass", (0.006, 0.007, 0.008), 0.03), bevel=0.006)
        V.box(447, 708, 1554, 1341, 4, 0.0, 0.002, mat_glass("screen", (0.003, 0.004, 0.006), 0.02))
        # Caméra frontale et capteur de luminosité dans le liseré haut.
        V.cyl(1000, 664, 9, 0.10, 0.13, dark)
        V.dome(1000, 664, 5.5, 0.13, 0.02, r3.mat_simple("flens", (0.06, 0.08, 0.16), 0.05, coat=1.0))
        V.cyl(1060, 664, 5, 0.10, 0.125, r3.mat_simple("als", (0.08, 0.06, 0.05), 0.1, coat=1.0))

        def stick(u, v):
            # Mini-joystick : puits sombre, bague moletée, champignon caoutchouc légèrement creusé.
            V.cyl(u, v, 74, 0.06, 0.14, well, verts=96, bevel=0.02)
            V.cyl(u, v, 60, 0.12, 0.24, knurl, verts=96, bevel=0.015)
            V.cyl(u, v, 48, 0.22, 0.46, rubber, verts=96, bevel=0.05)
            V.cyl(u, v, 36, 0.455, 0.47, r3.mat_paint("stick_top", (0.045, 0.045, 0.047), rough=0.75, grain=2.0), verts=64)

        def key(u, v, label, mat, font=FONT_B, size=44):
            V.cyl(u, v, 50, 0.06, 0.15, well, verts=64, bevel=0.015)
            V.cyl(u, v, 41, 0.12, 0.30, mat, verts=64, bevel=0.035)
            V.text(label, u, v, 0.302, size, ink, font=font)

        rows = (948, 1062, 1176, 1290)
        stick(318, 800)
        for v, lab, mat, fs in zip(rows, ("↺", "⌂", "❐", "fn"), (rubber, rubber, rubber, fnkey), (72, 74, 62, 44)):
            key(318, v, lab, mat, FONT_R if lab in ("↺", "⌂", "❐") else FONT_B, fs)
        stick(1732, 800)
        for v, lab in zip(rows, ("A", "B", "X", "Y")):
            key(1732, v, lab, rubber)
        V.text("COMSPEC", 1430, 595, 0.045, 22, r3.mat_paint("emb", tuple(c * 0.78 for c in KEY_SAND), rough=0.8), extrude=0.004)
        for u, v in ((262, 1388), (1788, 1388)):
            V.torx(u, v, 15, 0.12, steel, dark)
    else:
        # Dos : plaque à motif losange, trappe de batterie à loquets, plaque d'adaptation vissée, charnière du rabat.
        V.ring((190, 600, 1860, 1440), (300, 700, 1750, 1340), 70, 40, -0.05, 0.12, frame, bevel=0.04)
        V.box(301, 701, 1749, 1339, 40, -0.05, 0.06, mat_grip("grip", tuple(c * 0.8 for c in KEY_SAND), scale=3.0, strength=0.2), bevel=0.02)
        V.box(1180, 740, 1700, 1300, 30, 0.04, 0.15, mat_polymer("batt", tuple(c * 0.9 for c in KEY_SAND)), bevel=0.03)
        for v in (800, 1240):
            V.box(1380, v - 22, 1520, v + 22, 10, 0.12, 0.21, black, bevel=0.025)
        V.text("BATTERY", 1440, 1020, 0.151, 30, r3.mat_simple("ink3", (0.30, 0.28, 0.25), 0.5), font=FONT_R)
        alu = r3.mat_brushed("alu", (0.06, 0.062, 0.06), 0.36)
        V.box(430, 780, 1000, 1180, 30, 0.06, 0.2, alu, bevel=0.03)
        for u, v in ((480, 830), (950, 830), (480, 1130), (950, 1130)):
            V.torx(u, v, 13, 0.2, steel, dark)
        V.text("COMSPEC TX-8", 715, 980, 0.201, 34, r3.mat_simple("ink_l", (0.62, 0.62, 0.6), 0.5))
        # Charnière du rabat (bas de la tablette) : charnons alternés et axe.
        for k in range(6):
            u = 420 + k * 210
            V.box(u, 1360, u + 150, 1430, 18, 0.0, 0.28, black, bevel=0.03, name="knuckle")
        hcyl(V, 400, 1650, 1395, 14, 0.14, steel)
        for u in (250, 1800):
            V.cyl(u, 648, 40, 0.0, 0.08, rubber, verts=48, bevel=0.015)
        for u, v in ((262, 1388), (1788, 1388)):
            V.torx(u, v, 15, 0.12, steel, dark)
    return {"power": 3300, "size": 14, "fill": 560}


DEVICES = {"rugged": rugged, "iphone": iphone, "tablet": tablet, "keys": keys}


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
