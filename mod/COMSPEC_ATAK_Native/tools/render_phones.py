"""
Rendus 3D (Blender / Cycles) des trois cadres alternatifs du terminal ATAK : smartphone en coque tactique
(« tactique », identifiant rugged), smartphone premium fin (« iphone ») et tablette véhicule durcie (« tablet »).
Appareils génériques : aucune marque ni logo réel.
Chaque appareil donne une face (paysage, trou d'écran transparent où le jeu incruste l'interface) et un dos (miroir
horizontal de la face : la silhouette se superpose une fois retournée). Toiles 2048 x 2048 RGBA.
Depuis ATAK natif 1.93.0, les faces et dos iphone, rugged, keys et tablet viennent des photos de frame_photos.py :
ne pas relancer ces quatre-là (leurs anciens rendus sont remplacés ; celui de keys reste pour le JPC sous
render/phone_keys_jpc_*.png).
Prérequis : pip install bpy (Blender comme module Python, 5.x) ; réutilise les aides de render_3d.py.

    python3 render_phones.py [sortie]      # par défaut tools/render/
    RENDER_ONLY=rugged|iphone|tablet|keys|plate|flip|ribbed|ru|es|all   RENDER_SAMPLES=64

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
BOUNDS = {"rugged": (200, 560, 1900, 1560), "iphone": (200, 560, 1900, 1500), "tablet": (150, 480, 1950, 1580), "keys": (150, 480, 1900, 1520),
          "plate": (130, 480, 1920, 1700), "flip": (150, 480, 1900, 1520), "ribbed": (200, 560, 1850, 1480),
          "ru": (200, 560, 1850, 1480), "es": (200, 560, 1850, 1480)}
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


def label_png(path, back, title="COMSPEC VT-10", sub="RUGGED TERMINAL", sn="S/N VT10-2291-0473", power="12-28 V DC  3.5 A",
              ip="IP65  MIL-STD-810H", nsn="NSN 7010-99-COM-0010"):
    """Étiquette signalétique de la tablette (inversée pour la vue de dos, UV liées à X)."""
    from PIL import Image, ImageDraw, ImageFont, ImageOps
    w, h = 520, 180
    im = Image.new("RGB", (w, h), (206, 204, 192))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 0, w - 1, 44), fill=(28, 30, 26))
    d.text((16, 22), title, font=ImageFont.truetype(FONT_B, 30), fill=(222, 220, 205), anchor="lm")
    d.text((w - 16, 22), sub, font=ImageFont.truetype(FONT_R, 18), fill=(170, 170, 160), anchor="rm")
    fm = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 18)
    d.text((16, 66), sn, font=fm, fill=(40, 40, 40), anchor="lm")
    d.text((16, 92), power, font=fm, fill=(40, 40, 40), anchor="lm")
    d.text((16, 118), ip, font=fm, fill=(40, 40, 40), anchor="lm")
    x, i = 300, 0
    while x < w - 20:
        bw = 2 + (i * 7) % 4
        d.rectangle((x, 58, x + bw, 140), fill=(20, 20, 20))
        x += bw + 2 + (i * 3) % 4
        i += 1
    d.text((w / 2, 162), nsn, font=ImageFont.truetype(FONT_R, 16), fill=(70, 70, 70), anchor="mm")
    if back:
        im = ImageOps.mirror(im)
    im.save(path)


def regulatory_png(path, back):
    """Étiquette des normes (dos de la tablette à touches) : modèle, CE, IP68, MIL-STD-810H, ISO 9001 / 14001,
    poubelle barrée DEEE. Inversée pour la vue de dos (UV liées à X)."""
    from PIL import Image, ImageDraw, ImageFont, ImageOps
    w, h = 680, 248
    im = Image.new("RGB", (w, h), (214, 210, 198))
    d = ImageDraw.Draw(im)
    ink = (34, 34, 32)
    fb, fr = ImageFont.truetype(FONT_B, 26), ImageFont.truetype(FONT_R, 18)
    fm = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 17)
    d.text((18, 14), "COMSPEC TX-8  RUGGED EUD", font=fb, fill=ink)
    for i, t in enumerate(("Model: TX8-JPC   S/N TX8-0412-7731", "7.4 V DC  6800 mAh  Li-ion", "IP68   MIL-STD-810H   ISO 9001:2015   ISO 14001",
                           "Conforme EN 62368-1 / EN 301 489 / EN 300 328")):
        d.text((18, 56 + i * 26), t, font=fm if i < 2 else fr, fill=ink)
    # Marquage CE (deux arcs ouverts et le trait du E).
    x, y, r = 22, 170, 28
    d.arc((x, y, x + 2 * r, y + 2 * r), 45, 315, fill=ink, width=7)
    x2 = x + 2 * r + 8
    d.arc((x2, y, x2 + 2 * r, y + 2 * r), 45, 315, fill=ink, width=7)
    d.line((x2 + 14, y + r, x2 + 2 * r - 6, y + r), fill=ink, width=7)
    # Poubelle barrée (DEEE).
    bx = 180
    d.rectangle((bx + 8, y + 10, bx + 40, y + 52), outline=ink, width=4)
    d.line((bx + 2, y + 8, bx + 46, y + 8), fill=ink, width=4)
    d.line((bx, y, bx + 48, y + 58), fill=ink, width=4)
    d.line((bx + 48, y, bx, y + 58), fill=ink, width=4)
    d.text((250, 178), "IP68", font=fb, fill=ink)
    d.text((330, 182), "Made in EU  ·  COMSPEC Defence", font=fr, fill=ink)
    x = 330
    i = 0
    while x < w - 20:
        bw = 2 + (i * 7) % 4
        d.rectangle((x, 212, x + bw, 238), fill=(20, 20, 20))
        x += bw + 2 + (i * 3) % 4
        i += 1
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
        # Dos simple : coque lisse, module photo du S7 (verre noir, objectif, flash et capteur), panneau velcro coyote
        # au centre (patchs), mention MIL-STD-810H moulée et étiquette des normes, charnière discrète du rabat JPC.
        V.ring((190, 600, 1860, 1440), (300, 700, 1750, 1340), 70, 40, -0.05, 0.10, frame, bevel=0.03)
        V.box(301, 701, 1749, 1339, 40, -0.05, 0.03, frame, bevel=0.015)
        # Module photo façon S7 : fenêtre de la coque, îlot en verre noir légèrement bombé, gros objectif à bague,
        # pilule flash + capteur cardio à côté.
        V.box(1425, 676, 1665, 806, 46, 0.0, 0.06, black, bevel=0.015, name="cam_window")
        V.box(1445, 692, 1605, 790, 44, 0.04, 0.11, mat_glass("camglass", (0.01, 0.01, 0.012), 0.05), bevel=0.02)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.36, 0.37, 0.38), 0.25, 1.0)
        V.lens(1525, 741, 38, 0.1, gun, dark, coat, ring_h=0.035)
        V.box(1618, 700, 1650, 782, 15, 0.04, 0.1, mat_glass("pill", (0.02, 0.02, 0.022), 0.08), bevel=0.01)
        V.cyl(1634, 722, 10, 0.1, 0.112, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8), verts=32)
        for v in (752, 766):
            V.cyl(1634, v, 4, 0.1, 0.108, r3.mat_simple("hrm", (0.25, 0.05, 0.05), 0.2, coat=1.0), verts=16)
        # Panneau velcro (boucles) coyote cousu au centre.
        loop = mat_stipple("velcro", (0.42, 0.33, 0.21), scale=150.0, strength=0.7)
        seam = r3.mat_paint("seam", (0.30, 0.24, 0.15), rough=0.9, grain=2.0)
        V.box(752, 846, 1298, 1214, 18, 0.02, 0.07, seam, bevel=0.01, name="velcro_edge")
        V.box(768, 862, 1282, 1198, 12, 0.05, 0.085, loop, bevel=0.006, name="velcro")
        # Mentions : MIL-STD moulé, étiquette réglementaire (CE, IP68, ISO, DEEE).
        V.text("MIL-STD-810H", 1025, 1262, 0.03, 34, mat_polymer("emb", tuple(c * 0.8 for c in KEY_SAND), rough=0.8), extrude=0.006)
        lab = os.path.join(OUT, "_vt10_label.png")
        regulatory_png(lab, True)
        V.box(1360, 1190, 1700, 1314, 4, 0.03, 0.034, r3.mat_image("label", lab, 0.5), bevel=0)
        # Charnière du rabat JPC : deux charnons aux extrémités du bas.
        for u0 in (430, 1500):
            V.box(u0, 1370, u0 + 110, 1430, 14, 0.0, 0.2, dsand_hinge := mat_polymer("hinge", tuple(c * 0.86 for c in KEY_SAND)), bevel=0.03, name="hinge_leaf")
            hcyl(V, u0 + 6, u0 + 104, 1410, 18, 0.19, steel, bevel=0.01)
    return {"power": 3300, "size": 14, "fill": 560}


# --- Coques de référence (photos de TTRD), géométrie modélisée par nous, sans logos de marque ---
CASE_TAN = (0.40, 0.37, 0.29)
CASE_GREEN = (0.43, 0.44, 0.39)


def mirror_u(view, u):
    """Abscisse donnée dans l'image de dos -> coordonnée de la vue courante (identique de dos, miroir de face)."""
    return u if view.back else SIZE - u


def camo_png(path, seed=7):
    """Camouflage multi-environnement générique (taches douces beige, vert, brun, fines branches sombres)."""
    import random
    from PIL import Image, ImageDraw, ImageFilter
    rnd = random.Random(seed)
    W = 1024
    im = Image.new("RGB", (W, W), (150, 136, 102))
    for col, n, rmin, rmax, blur in (((172, 158, 120), 70, 30, 90, 10), ((112, 118, 80), 80, 25, 80, 8),
                                     ((128, 104, 72), 60, 20, 70, 6), ((84, 92, 62), 50, 15, 50, 5)):
        layer = Image.new("L", (W, W), 0)
        d = ImageDraw.Draw(layer)
        for _ in range(n):
            x, y, r = rnd.uniform(0, W), rnd.uniform(0, W), rnd.uniform(rmin, rmax)
            pts = [(x + r * rnd.uniform(0.5, 1.1) * __import__("math").cos(a / 9 * 6.283), y + r * rnd.uniform(0.4, 1.0) * __import__("math").sin(a / 9 * 6.283)) for a in range(9)]
            d.polygon(pts, fill=255)
        layer = layer.filter(ImageFilter.GaussianBlur(blur)).point(lambda v: 255 if v > 110 else 0).filter(ImageFilter.GaussianBlur(2))
        im.paste(Image.new("RGB", (W, W), col), (0, 0), layer)
    # Grain de tissu enduit : léger bruit, pas de motif net.
    import numpy as np
    a = np.asarray(im).astype(np.float32)
    a += np.random.default_rng(seed).normal(0, 5, a.shape[:2])[..., None]
    Image.fromarray(np.clip(a, 0, 255).astype("uint8")).save(path)


def phone_front(V, u0, v0, u1, v1, r, top_left=True):
    """Face de smartphone dans sa coque : verre noir laqué, dalle, caméra frontale et écouteur côté haut (gauche)."""
    V.box(u0, v0, u1, v1, r, -0.06, 0.0, mat_glass("glass", (0.006, 0.007, 0.008), 0.03), bevel=0.008)
    V.box(447, 708, 1554, 1341, 4, 0.0, 0.002, mat_glass("screen", (0.003, 0.004, 0.006), 0.02))
    cu = 425 if top_left else 1576
    V.cyl(cu, 1025, 8, 0.0, 0.004, r3.mat_simple("fcam", (0.0, 0.0, 0.0), 0.1, coat=1.0))
    V.dome(cu, 1025, 4.5, 0.004, 0.02, r3.mat_simple("flens", (0.06, 0.08, 0.16), 0.05, coat=1.0))
    V.box(cu - 6, 1060, cu + 6, 1140, 4, 0.0, 0.003, r3.mat_simple("spk", (0.03, 0.03, 0.03), 0.6), bevel=0)


def plate(back):
    """Coque porte-plaques (support MOLLE / JPC) couleur tan : ailes à pads suédés, équerres caoutchouc noires aux
    bords, plaque centrale surélevée avec velcro, îlot photo triple objectif, pied de fixation à crans et levier."""
    D = 0.55
    F, V = View(False, D), View(back, D)
    tan = mat_polymer("tan", CASE_TAN, rough=0.72, edge=0.28)
    tan2 = mat_polymer("tan2", tuple(c * 1.06 for c in CASE_TAN), rough=0.7, edge=0.32)
    suede = r3.mat_paint("suede", tuple(c * 0.92 for c in CASE_TAN), rough=0.95, grain=4.0)
    rubber = mat_polymer("rubber", (0.045, 0.045, 0.047), rough=0.75, grain=1.2, edge=0.25)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    M = lambda u: SIZE - u  # noqa: E731  (cotes de l'image de dos -> repère de la face, pour les pièces communes F)
    # Coque : corps commun aux deux vues, équerres caoutchouc sur les bords courts (traversantes).
    F.box(170, 610, 1878, 1430, 60, -2 * D - 0.04, 0.10, tan, bevel=0.05, name="body")
    for u0, u1 in ((150, 236), (1812, 1898)):
        F.box(u0, 630, u1, 1410, 26, -2 * D - 0.12, 0.22, rubber, bevel=0.04, name="bracket")
    for u0, u1 in ((154, 320), (1728, 1894)):
        for v0, v1 in ((612, 676), (1364, 1428)):
            F.box(u0, v0, u1, v1, 30, -2 * D - 0.12, 0.22, rubber, bevel=0.04, name="corner")
    # Pied de fixation (sous la coque) : bloc à crans et levier de verrouillage, poignée côté gauche de dos.
    F.box(M(1330), 1400, M(718), 1530, 22, -2 * D - 0.3, -0.10, tan, bevel=0.04, name="foot")
    for k in range(10):
        u = 745 + k * 56
        a, b = sorted((M(u), M(u + 30)))
        F.box(a, 1500, b, 1604, 8, -2 * D - 0.34, -0.14, tan2, bevel=0.03, name="crenel")
    a, b = sorted((M(330), M(1390)))
    F.box(a, 1540, b, 1610, 28, -2 * D - 0.26, -0.40, tan, bevel=0.04, name="lever")
    a, b = sorted((M(240), M(360)))
    F.box(a, 1508, b, 1640, 40, -2 * D - 0.30, -0.36, tan2, bevel=0.05, name="lever_grip")
    if not back:
        # Face : lèvre de coque tan autour du verre, équerres et coins noirs visibles.
        V.ring((236, 640, 1812, 1400), (392, 676, 1656, 1372), 40, 40, -0.05, 0.16, tan2, bevel=0.04)
        phone_front(V, 392, 676, 1656, 1372, 40)
    else:
        # Dos : ailes en retrait à pads suédés, plaque centrale surélevée, velcro, emblème, îlot photo.
        V.box(270, 690, 560, 1360, 26, 0.04, 0.12, suede, bevel=0.02, name="pad_l")
        V.box(1470, 1080, 1760, 1360, 26, 0.04, 0.12, suede, bevel=0.02, name="pad_r")
        V.box(560, 600, 1470, 1200, 30, 0.08, 0.30, tan2, bevel=0.05, name="plate")
        V.box(700, 1180, 1330, 1420, 40, 0.08, 0.30, tan2, bevel=0.05, name="plate_low")
        loop = mat_stipple("velcro", (0.34, 0.29, 0.21), scale=150.0, strength=0.7)
        V.box(733, 745, 1278, 1255, 10, 0.28, 0.335, loop, bevel=0.006, name="velcro")
        V.text("COMSPEC", 1015, 680, 0.30, 34, mat_polymer("emb", tuple(c * 0.85 for c in CASE_TAN), rough=0.8), extrude=0.006)
        ring = mat_polymer("emblem", tuple(c * 0.85 for c in CASE_TAN), rough=0.8)
        V.cyl(1005, 1330, 46, 0.29, 0.33, ring, verts=64, bevel=0.01)
        V.cyl(1005, 1330, 34, 0.32, 0.335, tan2, verts=64)
        V.box(1001, 1300, 1009, 1360, 2, 0.33, 0.345, ring, bevel=0)
        V.box(975, 1326, 1035, 1334, 2, 0.33, 0.345, ring, bevel=0)
        # Îlot photo (coin haut droit de dos) : cadre noir, verre, trois objectifs et flash.
        V.box(1334, 677, 1797, 1051, 70, 0.04, 0.24, rubber, bevel=0.03, name="cam_frame")
        V.box(1360, 700, 1772, 1028, 60, 0.20, 0.27, mat_glass("camglass", (0.012, 0.012, 0.014), 0.06), bevel=0.02)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.10, 0.10, 0.11), 0.3, 1.0)
        for u, v in ((1478, 800), (1650, 800), (1564, 935)):
            V.lens(u, v, 60, 0.27, gun, dark, coat, ring_h=0.08)
        V.cyl(1690, 950, 18, 0.27, 0.29, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8), verts=32)
    return {"power": 3300, "size": 14, "fill": 560}


def flip(back):
    """Étui à rabat multicam : charnières en haut et en bas, grand velcro coyote en L, îlot photo double objectif."""
    D = 0.55
    F, V = View(False, D), View(back, D)
    camo = os.path.join(OUT, "_camo.png")
    camo_png(camo)
    cam = r3.mat_image("camo", camo, 0.75)
    tanp = mat_polymer("tanp", (0.48, 0.42, 0.31), rough=0.72, edge=0.3)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    steel = r3.mat_simple("steel", (0.40, 0.40, 0.38), 0.35, 1.0)
    F.box(200, 620, 1848, 1420, 46, -2 * D - 0.04, 0.10, cam, bevel=0.05, name="body")
    # Charnière haute : barre ronde à charnons, zones crantées aux extrémités, goupilles aux coins.
    hcyl(F, 330, 1720, 600, 34, -D, tanp, bevel=0.01)
    for u0 in (330, 760, 1190, 1560):
        hcyl(F, u0, u0 + 130, 600, 40, -D, tanp, bevel=0.02)
    for u0 in (470, 1400):
        for k in range(6):
            F.box(u0 + k * 26, 548, u0 + k * 26 + 14, 600, 4, -D - 0.2, -D + 0.2, tanp, bevel=0.01, name="fin")
    for u in (300, 1750):
        F.cyl(u, 612, 16, -2 * D - 0.06, 0.16, steel, verts=32)
    # Charnière basse : charnons et fûts, ailettes aux extrémités.
    for u0, u1 in ((340, 650), (1400, 1710)):
        hcyl(F, u0, u1, 1418, 36, -D, tanp, bevel=0.02)
    hcyl(F, 650, 1400, 1418, 26, -D, dark)
    for u0 in (230, 1720):
        for k in range(4):
            F.box(u0 + k * 26, 1400, u0 + k * 26 + 14, 1448, 4, -D - 0.3, -D + 0.3, tanp, bevel=0.01, name="fin")
    if not back:
        V.ring((200, 620, 1848, 1420), (380, 680, 1668, 1370), 46, 34, -0.05, 0.16, cam, bevel=0.04)
        phone_front(V, 380, 680, 1668, 1370, 34)
    else:
        loop = mat_stipple("velcro", (0.45, 0.36, 0.23), scale=150.0, strength=0.7)
        V.box(274, 733, 1123, 1342, 30, 0.08, 0.15, loop, bevel=0.006, name="velcro_a")
        V.box(1040, 1098, 1690, 1342, 30, 0.078, 0.148, loop, bevel=0.006, name="velcro_b")
        # Îlot photo : bordure camo surélevée, plaque gris foncé, deux objectifs côte à côte, flash.
        V.box(1150, 725, 1740, 1170, 70, 0.04, 0.22, cam, bevel=0.04, name="cam_border")
        V.box(1186, 759, 1703, 1142, 56, 0.18, 0.25, r3.mat_paint("camplate", (0.13, 0.13, 0.14), rough=0.55), bevel=0.02)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.08, 0.08, 0.09), 0.3, 1.0)
        for u in (1405, 1590):
            V.lens(u, 846, 92, 0.25, gun, dark, coat, ring_h=0.09)
        V.cyl(1504, 977, 28, 0.25, 0.27, r3.mat_simple("flash", (0.85, 0.85, 0.82), 0.3, coat=0.8), verts=32)
    return {"power": 3300, "size": 14, "fill": 560}


def ribbed(back):
    """Coque fine vert-gris à bandes horizontales en relief (bourrelets), îlot photo vertical triple objectif."""
    D = 0.42
    F, V = View(False, D), View(back, D)
    green = mat_polymer("green", CASE_GREEN, rough=0.7, edge=0.28)
    green2 = mat_polymer("green2", tuple(c * 1.05 for c in CASE_GREEN), rough=0.68, edge=0.34)
    island = r3.mat_paint("island", (0.10, 0.10, 0.105), rough=0.6, grain=0.8)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    F.box(245, 610, 1803, 1430, 90, -2 * D - 0.04, 0.10, green, bevel=0.06, name="body", seg=14)
    if not back:
        V.ring((245, 610, 1803, 1430), (300, 662, 1748, 1378), 90, 60, -0.05, 0.14, green2, bevel=0.04)
        phone_front(V, 300, 662, 1748, 1378, 60)
    else:
        # Haut du téléphone à droite de dos : îlot photo en L, objectifs en colonne (ici en ligne), flash.
        V.box(1295, 684, 1737, 987, 40, 0.04, 0.14, island, bevel=0.03, name="island_a")
        V.box(1541, 900, 1737, 1282, 40, 0.035, 0.138, island, bevel=0.03, name="island_b")
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.30, 0.31, 0.32), 0.3, 1.0)
        for u in (1645, 1515, 1385):
            V.lens(u, 800, 58, 0.13, gun, dark, coat, ring_h=0.06)
        V.cyl(1645, 892, 16, 0.13, 0.15, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8), verts=32)
        V.text("COMSPEC", 1640, 1130, 0.141, 20, r3.mat_simple("ink", (0.30, 0.30, 0.30), 0.5), font=FONT_R, rot=math.pi / 2, extrude=0.003)
        # Bandes en relief, de la caméra vers le bas du téléphone (à gauche de dos).
        edges = (1213, 1057, 918, 770, 622, 475, 300)
        for i in range(len(edges) - 1):
            a, b = edges[i + 1] + 6, edges[i] - 6
            V.box(a, 650, b, 1390, 30, 0.04, 0.16 + 0.02 * (i % 2), green2, bevel=0.07, name="band")
        for v0 in (905, 1167):
            V.box(266, v0, 282, v0 + 98, 6, 0.0, 0.11, dark, bevel=0.01, name="slot")
    return {"power": 3300, "size": 14, "fill": 560}


# --- Téléphones nationaux : durci russe (camouflage EMR « Цифра ») et durci espagnol (« pixelado boscoso ») ---
# Même corps que la coque nervurée (245..1803 x 610..1430) : le JPC réutilise jpc_ribbed.p3d avec leur atlas.
EMR = [(0.0, (128, 136, 98)), (0.50, (96, 110, 72), 3.0), (0.58, (70, 84, 56), 2.2), (0.66, (96, 80, 58), 1.6),
       (0.80, (40, 42, 36), 1.0)]
PIXELADO = [(0.0, (168, 152, 112)), (0.42, (108, 118, 74), 4.5), (0.55, (118, 92, 62), 3.5), (0.72, (66, 74, 48), 2.5),
            (0.84, (42, 40, 34), 1.4)]


def pixel_camo_png(path, layers, seed, w=1560, h=822, cell=7):
    """Camouflage numérique : couches de taches pixellisées (bruit lissé seuillé sur une grille de cellules carrées),
    toile au rapport du corps pour que les pixels restent carrés une fois plaqués (coordonnées générées)."""
    import numpy as np
    from PIL import Image, ImageFilter
    rng = np.random.default_rng(seed)
    gw, gh = w // cell + 1, h // cell + 1
    out = np.zeros((gh, gw, 3), np.float32)
    out[:] = layers[0][1]
    for q, col, blur in layers[1:]:
        noise = Image.fromarray((rng.random((gh, gw)) * 255).astype("uint8"))
        f = np.asarray(noise.filter(ImageFilter.GaussianBlur(blur))).astype(np.float32)
        out[f > np.quantile(f, q + (1 - q) * 0.45)] = col
    img = Image.fromarray(out.astype("uint8")).resize((gw * cell, gh * cell), Image.NEAREST).crop((0, 0, w, h))
    a = np.asarray(img).astype(np.float32)
    a += rng.normal(0, 4, a.shape[:2])[..., None]  # grain de l'impression sur le polymère
    Image.fromarray(np.clip(a, 0, 255).astype("uint8")).save(path)


def flag_png(path, stripes, w=420, h=260):
    """Écusson brodé à bandes horizontales (proportions des bandes données), bord surjeté sombre, grain de fil."""
    import numpy as np
    from PIL import Image, ImageDraw
    im = Image.new("RGB", (w, h), (46, 48, 40))
    d = ImageDraw.Draw(im)
    b, total, y = 16, sum(k for k, _ in stripes), 16
    for k, col in stripes:
        y1 = y + (h - 2 * b) * k / total
        d.rectangle((b, y, w - b, y1), fill=col)
        y = y1
    a = np.asarray(im).astype(np.float32)
    rows = (np.arange(h) % 4 < 2)[:, None, None] * 6.0  # rangs de broderie
    a = a - rows + np.random.default_rng(3).normal(0, 5, a.shape[:2])[..., None]
    Image.fromarray(np.clip(a, 0, 255).astype("uint8")).save(path)


def bezel(V, u0, v0, u1, v1, r, h):
    """Verre noir affleurant la lèvre de la coque (h) : le liseré noir reste visible autour de l'écran, caméra
    frontale et écouteur côté haut (gauche)."""
    V.box(u0, v0, u1, v1, r, -0.06, h, mat_glass("bezel", (0.006, 0.007, 0.008), 0.03), bevel=0.008)
    V.cyl(425, 1025, 8, h, h + 0.004, r3.mat_simple("fcam2", (0.0, 0.0, 0.0), 0.1, coat=1.0))
    V.dome(425, 1025, 4.5, h + 0.004, 0.02, r3.mat_simple("flens2", (0.06, 0.08, 0.16), 0.05, coat=1.0))
    V.box(419, 1060, 431, 1140, 4, h, h + 0.003, r3.mat_simple("spk2", (0.03, 0.03, 0.03), 0.6), bevel=0)


def ru(back):
    """Durci russe (type smartphone durci grand public répandu dans les unités russes) : coque en camouflage EMR
    « Цифра », coins caoutchouc noirs vissés, touche SOS orange, gros module photo rond avec objectif thermique, patin
    texturé, écusson tricolore et marquages cyrilliques."""
    D = 0.55
    F, V = View(False, D), View(back, D)
    camo = os.path.join(OUT, "_camo.png")
    pixel_camo_png(camo, EMR, 11)
    cam = r3.mat_image("emr", camo, 0.78)
    rubber = mat_polymer("rubber", (0.040, 0.042, 0.040), rough=0.78, grain=1.3, edge=0.3)
    steel = r3.mat_simple("steel", (0.42, 0.42, 0.40), 0.35, 1.0)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    ink = r3.mat_paint("ink", (0.62, 0.62, 0.58), rough=0.7)
    F.box(245, 610, 1803, 1430, 90, -2 * D - 0.04, 0.10, cam, bevel=0.05, name="body")
    # Coins pare-chocs (traversants), vis à six lobes de face et de dos.
    corners = [(u0, v0, u1, v1) for u0, u1 in ((245, 445), (1603, 1803)) for v0, v1 in ((610, 790), (1250, 1430))]
    for u0, v0, u1, v1 in corners:
        F.box(u0, v0, u1, v1, 70, -2 * D - 0.12, 0.22, rubber, bevel=0.05, name="bumper")
    # Touches de la tranche haute : SOS orange et volume noir, gardes moulées.
    F.box(1180, 606, 1300, 626, 6, -D - 0.16, -D + 0.16, r3.mat_paint("sos", (0.72, 0.26, 0.05), rough=0.6), bevel=0.03, name="key")
    F.box(1350, 606, 1500, 626, 6, -D - 0.16, -D + 0.16, rubber, bevel=0.03, name="key")
    if not back:
        V.ring((245, 610, 1803, 1430), (360, 672, 1688, 1368), 90, 40, -0.05, 0.16, cam, bevel=0.04)
        phone_front(V, 360, 672, 1688, 1368, 40)
        bezel(V, 360, 672, 1688, 1368, 40, 0.17)
        for u0, v0, u1, v1 in corners:
            V.torx((u0 + u1) / 2 + (-28 if u0 < 1000 else 28), (v0 + v1) / 2 + (-22 if v0 < 1000 else 22), 15, 0.22, steel, dark)
        # Bas du téléphone à droite : plaquette caoutchouc gravée « ЗАЩИЩЁННЫЙ » (durci).
        V.box(1708, 860, 1782, 1180, 22, 0.10, 0.19, rubber, bevel=0.02, name="tag")
        V.text("ЗАЩИЩЁННЫЙ", 1745, 1020, 0.19, 30, ink, rot=math.pi / 2, extrude=0.003)
    else:
        V.box(430, 700, 1190, 1340, 40, 0.04, 0.20, mat_stipple("pad", (0.05, 0.052, 0.05)), bevel=0.05, name="pad")
        for u0, v0, u1, v1 in corners:
            V.torx((u0 + u1) / 2, (v0 + v1) / 2, 15, 0.22, steel, dark)
        # Module photo rond (haut du téléphone à droite de dos) : principal, thermique (germanium, bague dorée),
        # grand-angle, macro et flash.
        V.cyl(1440, 1020, 215, 0.04, 0.24, rubber, verts=128, bevel=0.03)
        V.cyl(1440, 1020, 192, 0.22, 0.27, mat_glass("camglass", (0.010, 0.010, 0.012), 0.06), verts=128, bevel=0.01)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.20, 0.20, 0.21), 0.3, 1.0)
        gold = r3.mat_simple("gold", (0.75, 0.55, 0.22), 0.3, 1.0)
        V.lens(1440, 900, 62, 0.27, gun, dark, coat, ring_h=0.07)
        V.lens(1440, 1140, 58, 0.27, gold, dark, r3.mat_simple("germ", (0.14, 0.10, 0.16), 0.08, 1.0, coat=1.0), ring_h=0.07)
        V.lens(1320, 1020, 40, 0.27, gun, dark, coat, ring_h=0.05)
        V.lens(1560, 1020, 34, 0.27, gun, dark, coat, ring_h=0.05)
        V.cyl(1545, 1135, 16, 0.27, 0.29, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8), verts=32)
        # Écusson tricolore (blanc, bleu, rouge) sur velcro, marquages du patin.
        flag = os.path.join(OUT, "_flag.png")
        flag_png(flag, [(1, (226, 226, 220)), (1, (30, 66, 150)), (1, (190, 36, 40))])
        V.box(482, 748, 742, 912, 14, 0.18, 0.235, mat_stipple("loop", (0.10, 0.11, 0.09), scale=150.0, strength=0.6), bevel=0.006, name="velcro")
        V.box(492, 758, 732, 902, 12, 0.22, 0.25, r3.mat_image("flag", flag, 0.85), bevel=0.006, name="flag")
        V.text("ЗАЩИЩЁННЫЙ ТЕРМИНАЛ", 810, 1245, 0.20, 30, ink, extrude=0.003)
        V.text("Т-1   IP69K   MIL-STD-810H", 810, 1295, 0.20, 20, ink, font=FONT_R, extrude=0.003)
        # Passant de dragonne (bas du téléphone, à gauche de dos).
        V.box(262, 960, 300, 1080, 12, 0.0, 0.14, dark, bevel=0.01, name="lanyard")
    return {"power": 3300, "size": 14, "fill": 560}


def es(back):
    """Durci espagnol (type smartphone durci professionnel à batterie amovible) : coque en camouflage « pixelado
    boscoso » de l'armée de terre espagnole, embouts caoutchouc rainurés, touche programmable orange, barre photo
    double objectif, trappe de batterie nervurée, écusson rouge et jaune, broches de charge."""
    D = 0.46
    F, V = View(False, D), View(back, D)
    camo = os.path.join(OUT, "_camo.png")
    pixel_camo_png(camo, PIXELADO, 23, cell=9)
    cam = r3.mat_image("pixelado", camo, 0.78)
    rubber = mat_polymer("rubber", (0.045, 0.045, 0.042), rough=0.78, grain=1.3, edge=0.3)
    black = mat_polymer("black", (0.06, 0.062, 0.058), rough=0.62, grain=1.0, edge=0.45)
    dark = r3.mat_simple("dark", (0.015, 0.015, 0.015), 0.5)
    ink = r3.mat_paint("ink", (0.60, 0.60, 0.56), rough=0.7)
    F.box(245, 610, 1803, 1430, 80, -2 * D - 0.04, 0.10, cam, bevel=0.05, name="body", seg=14)
    # Embouts caoutchouc (bords courts), rainures de préhension.
    for u0, u1 in ((245, 345), (1703, 1803)):
        F.box(u0, 610, u1, 1430, 80, -2 * D - 0.10, 0.18, rubber, bevel=0.05, name="cap", seg=14)
    # Touche programmable orange (tranche haute) et volume.
    F.box(880, 606, 1060, 626, 8, -D - 0.15, -D + 0.15, r3.mat_paint("xkey", (0.78, 0.36, 0.06), rough=0.55), bevel=0.03, name="key")
    F.box(1240, 606, 1420, 626, 8, -D - 0.15, -D + 0.15, rubber, bevel=0.03, name="key")
    if not back:
        V.ring((245, 610, 1803, 1430), (345, 662, 1703, 1378), 80, 46, -0.05, 0.14, cam, bevel=0.04)
        phone_front(V, 345, 662, 1703, 1378, 46)
        bezel(V, 375, 672, 1673, 1368, 40, 0.15)
        for u in (268, 294, 320, 1728, 1754, 1780):
            V.box(u - 5, 760, u + 5, 1280, 5, 0.12, 0.185, dark, bevel=0.004, name="groove")
    else:
        # Trappe de batterie noire nervurée, loquet.
        V.box(420, 680, 1300, 1360, 40, 0.04, 0.16, black, bevel=0.04, name="door")
        for v in range(740, 1220, 64):
            V.box(470, v, 1250, v + 26, 12, 0.14, 0.20, black, bevel=0.012, name="rib")
        V.box(760, 1260, 960, 1320, 18, 0.14, 0.22, rubber, bevel=0.02, name="latch")
        V.text("TERMINAL TÁCTICO", 1100, 1290, 0.16, 22, ink, font=FONT_R, extrude=0.003)
        # Barre photo (haut du téléphone à droite de dos) : deux objectifs et flash.
        V.box(1360, 690, 1680, 960, 50, 0.04, 0.22, black, bevel=0.03, name="cam_bar")
        V.box(1380, 710, 1660, 940, 40, 0.20, 0.25, mat_glass("camglass", (0.010, 0.010, 0.012), 0.06), bevel=0.01)
        coat = r3.mat_simple("coat", (0.10, 0.12, 0.30), 0.04, coat=1.0)
        gun = r3.mat_simple("gun", (0.26, 0.26, 0.27), 0.3, 1.0)
        V.lens(1450, 790, 52, 0.25, gun, dark, coat, ring_h=0.06)
        V.lens(1580, 790, 52, 0.25, gun, dark, coat, ring_h=0.06)
        V.cyl(1515, 890, 16, 0.25, 0.27, r3.mat_simple("flash", (0.93, 0.89, 0.72), 0.3, coat=0.8), verts=32)
        # Écusson rouge, jaune, rouge (1 : 2 : 1) sur velcro, sous la barre photo.
        flag = os.path.join(OUT, "_flag.png")
        flag_png(flag, [(1, (178, 22, 32)), (2, (240, 188, 24)), (1, (178, 22, 32))])
        V.box(1384, 1030, 1656, 1206, 14, 0.04, 0.10, mat_stipple("loop", (0.30, 0.27, 0.19), scale=150.0, strength=0.6), bevel=0.006, name="velcro")
        V.box(1394, 1040, 1646, 1196, 12, 0.08, 0.12, r3.mat_image("flag", flag, 0.85), bevel=0.006, name="flag")
        V.text("ESPAÑA", 1520, 1260, 0.10, 22, r3.mat_paint("ink2", (0.20, 0.18, 0.14), rough=0.7), extrude=0.003)
        # Broches de charge dorées (bas du téléphone, à gauche de dos).
        gold = r3.mat_simple("gold", (0.80, 0.62, 0.26), 0.25, 1.0)
        for v in (990, 1050):
            V.cyl(372, v, 10, 0.0, 0.03, gold, verts=24)
    return {"power": 3300, "size": 14, "fill": 560}


DEVICES = {"rugged": rugged, "iphone": iphone, "tablet": tablet, "keys": keys, "plate": plate, "flip": flip, "ribbed": ribbed,
           "ru": ru, "es": es}


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
    for t in ("_vt10_label.png", "_camo.png", "_flag.png"):
        tmp = os.path.join(OUT, t)
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
