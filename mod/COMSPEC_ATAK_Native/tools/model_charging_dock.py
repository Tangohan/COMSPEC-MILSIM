"""
Socle de recharge CS-10 : dix téléphones COMSPEC (Samsung S7 en coque tactique) debout sur deux gradins.

Modèle (data/charging_dock.p3d) :
- socle en aluminium anodisé gris très foncé, 57 x 24 cm, gradin arrière surélevé, berceaux et dosserets en
  caoutchouc, ailettes de séparation, poignées, patins, prise secteur, ouïes et plaque signalétique au dos ;
  matières PBR (Cycles) cuites avec leur occlusion dans une texture 2048 (data/charging_dock_co.paa) ;
- dix téléphones : le maillage du téléphone du gilet (model_vest_phone.build, texture photo de la coque), un par
  emplacement, sélection phone1..phone10 (coque choisie par le propriétaire, "" quand l'emplacement est vide) ;
- écran de chaque téléphone : sélection screen1..screen10 (data/dock_screen_0..5_co.paa, niveau de charge) ;
- voyant de chaque emplacement : sélection led1..led10 (couleur procédurale) ; écrans et voyants en
  data/dock_glow.rvmat (lumineux la nuit).
LOD : résolution 1, ombre (boîtes), géométrie (boîtes convexes, 9 kg), géométrie de tir.
Emplacements 1 à 5 : rangée basse, de gauche à droite vu de face ; 6 à 10 : gradin arrière.

Rendu d'aperçu (tools/render/charging_dock_preview.png) et icônes d'inventaire avant la cuisson.

    PHONE_PNG=/dossier/des/vest_phone*_co.png python3 model_charging_dock.py
    (PNG obtenus par « hemtt utils paa convert vest_phone_co.paa vest_phone_co.png », idem _od et _black)
    puis  hemtt utils paa convert render/charging_dock_co.png ../Sources/addons/main/data/charging_dock_co.paa
Extension Blender « Arma 3 Object Builder » requise (voir model_vest_phone.py).
"""
import math
import os
import sys

import bpy  # noqa: I001 (bmesh et mathutils après bpy)
import addon_utils
import bmesh
from mathutils import Matrix, Vector

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import model_vest_phone as vp  # noqa: E402

DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")
PHONE_PNG = os.environ.get("PHONE_PNG", RENDER)
TEX = "z\\comspec_atak_native\\addons\\main\\data\\"
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "96"))
BAKE_SIZE = 2048

# Dimensions (m). Repère Blender : X largeur, +Y avant du socle (direction de l'objet en jeu), Z haut.
W, D = 0.57, 0.24
FEET = 0.006
DECK = 0.05        # dessus de la rangée basse
RISER = 0.125      # dessus du gradin
PITCH = 0.106
LEAN = math.radians(12)
ROW_A_Y, ROW_B_Y = 0.06, -0.065
# Vu de face, la gauche du spectateur est +X : emplacement 1 (et 6) à gauche.
SLOT_X = [(2 - i) * PITCH for i in range(5)]
PH_H = (vp.X1 - vp.X0) * vp.M_PER_PX   # 16,5 cm, axe long
PH_D = vp.DEPTH


def slots():
    """[(x, y, z du berceau)] des emplacements 1..10."""
    return [(x, ROW_A_Y, DECK) for x in SLOT_X] + [(x, ROW_B_Y, RISER) for x in SLOT_X]


def srgb(c):
    return tuple(((v + 0.055) / 1.055) ** 2.4 if v > 0.04045 else v / 12.92 for v in c) + (1.0,)


# --- Matières (Cycles) ---
def mat_anodized(name, color, rough=0.42, grain=1.0):
    """Aluminium anodisé microbillé : grain fin en relief, légère variation de teinte, arêtes un peu plus claires."""
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt, n = m.node_tree, m.node_tree.nodes
    b = n["Principled BSDF"]
    b.inputs["Roughness"].default_value = rough
    b.inputs["Metallic"].default_value = 0.55
    tc = n.new("ShaderNodeTexCoord")
    fine = n.new("ShaderNodeTexNoise")
    fine.inputs["Scale"].default_value = 2600.0
    fine.inputs["Detail"].default_value = 4.0
    nt.links.new(tc.outputs["Object"], fine.inputs["Vector"])
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.18 * grain
    bump.inputs["Distance"].default_value = 0.0004
    nt.links.new(fine.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], b.inputs["Normal"])
    big = n.new("ShaderNodeTexNoise")
    big.inputs["Scale"].default_value = 9.0
    big.inputs["Detail"].default_value = 3.0
    nt.links.new(tc.outputs["Object"], big.inputs["Vector"])
    ramp = n.new("ShaderNodeValToRGB")
    ramp.color_ramp.elements[0].color = tuple(c * 0.85 for c in srgb(color)[:3]) + (1,)
    ramp.color_ramp.elements[1].color = tuple(min(1, c * 1.12) for c in srgb(color)[:3]) + (1,)
    nt.links.new(big.outputs["Fac"], ramp.inputs["Fac"])
    # Usure claire sur les arêtes (pointiness).
    geo = n.new("ShaderNodeNewGeometry")
    wr = n.new("ShaderNodeValToRGB")
    wr.color_ramp.elements[0].position = 0.53
    wr.color_ramp.elements[1].position = 0.62
    mix = n.new("ShaderNodeMix")
    mix.data_type = "RGBA"
    mul = n.new("ShaderNodeMath")
    mul.operation = "MULTIPLY"
    mul.inputs[1].default_value = 0.55
    nt.links.new(geo.outputs["Pointiness"], wr.inputs["Fac"])
    nt.links.new(wr.outputs["Color"], mul.inputs[0])
    nt.links.new(mul.outputs[0], mix.inputs["Factor"])
    nt.links.new(ramp.outputs["Color"], mix.inputs["A"])
    mix.inputs["B"].default_value = srgb((0.50, 0.51, 0.50))
    nt.links.new(mix.outputs["Result"], b.inputs["Base Color"])
    return m


def mat_rubber(name, color, rough=0.82):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt, n = m.node_tree, m.node_tree.nodes
    b = n["Principled BSDF"]
    b.inputs["Base Color"].default_value = srgb(color)
    b.inputs["Roughness"].default_value = rough
    tc = n.new("ShaderNodeTexCoord")
    tex = n.new("ShaderNodeTexNoise")
    tex.inputs["Scale"].default_value = 1800.0
    nt.links.new(tc.outputs["Object"], tex.inputs["Vector"])
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.25
    bump.inputs["Distance"].default_value = 0.0003
    nt.links.new(tex.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], b.inputs["Normal"])
    return m


def mat_simple(name, color, rough=0.5, metal=0.0, emit=0.0):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = m.node_tree.nodes["Principled BSDF"]
    b.inputs["Base Color"].default_value = srgb(color)
    b.inputs["Roughness"].default_value = rough
    b.inputs["Metallic"].default_value = metal
    if emit:
        b.inputs["Emission Color"].default_value = srgb(color)
        b.inputs["Emission Strength"].default_value = emit
    return m


def mat_decal(name, png, under):
    """Sérigraphie ou plaque : image (UV « Src ») posée sur la teinte du support, opaque (cuite dans la texture)."""
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt, n = m.node_tree, m.node_tree.nodes
    b = n["Principled BSDF"]
    b.inputs["Roughness"].default_value = 0.5
    uv = n.new("ShaderNodeUVMap")
    uv.uv_map = "Src"
    img = n.new("ShaderNodeTexImage")
    img.image = bpy.data.images.load(png)
    img.extension = "CLIP"
    nt.links.new(uv.outputs["UV"], img.inputs["Vector"])
    mix = n.new("ShaderNodeMix")
    mix.data_type = "RGBA"
    mix.inputs["A"].default_value = srgb(under)
    nt.links.new(img.outputs["Alpha"], mix.inputs["Factor"])
    nt.links.new(img.outputs["Color"], mix.inputs["B"])
    nt.links.new(mix.outputs["Result"], b.inputs["Base Color"])
    return m


def mat_image(name, png, emit=0.0, rough=0.3):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt, n = m.node_tree, m.node_tree.nodes
    b = n["Principled BSDF"]
    b.inputs["Roughness"].default_value = rough
    uv = n.new("ShaderNodeUVMap")
    uv.uv_map = "Src"
    img = n.new("ShaderNodeTexImage")
    img.image = bpy.data.images.load(png)
    nt.links.new(uv.outputs["UV"], img.inputs["Vector"])
    nt.links.new(img.outputs["Color"], b.inputs["Base Color"])
    if emit:
        nt.links.new(img.outputs["Color"], b.inputs["Emission Color"])
        b.inputs["Emission Strength"].default_value = emit
    return m


# --- Géométrie ---
def link(me, name):
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    return ob


def box(x0, y0, z0, x1, y1, z1, mat, bevel=0.0, name="box", matrix=None):
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co = Vector(((x0 + x1) / 2 + v.co.x * (x1 - x0), (y0 + y1) / 2 + v.co.y * (y1 - y0), (z0 + z1) / 2 + v.co.z * (z1 - z0)))
    if bevel:
        bmesh.ops.bevel(bm, geom=list(bm.edges), offset=bevel, segments=3, profile=0.5, affect="EDGES", clamp_overlap=True)
    if matrix is not None:
        bm.transform(matrix)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(mat)
    for p in me.polygons:
        p.use_smooth = False
    return link(me, name)


def cyl(x, y, z, r, h, mat, axis="Z", verts=24, name="cyl"):
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, segments=verts, radius1=r, radius2=r, depth=h)
    rot = {"Z": Matrix.Identity(4), "X": Matrix.Rotation(math.pi / 2, 4, "Y"), "Y": Matrix.Rotation(math.pi / 2, 4, "X")}[axis]
    bm.transform(Matrix.Translation((x, y, z)) @ rot)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(mat)
    return link(me, name)


def quad(corners, uvs, mat, name="quad"):
    """Quadrilatère (coins dans l'ordre anti-horaire vu du côté visible), UV « Src »."""
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("Src")
    vs = [bm.verts.new(c) for c in corners]
    f = bm.faces.new(vs)
    for loop in f.loops:
        loop[uvl].uv = uvs[vs.index(loop.vert)]
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(mat)
    return link(me, name)


def lean_at(x, y, z):
    """Repère d'un emplacement : origine au fond du berceau, penché de LEAN vers l'arrière."""
    return Matrix.Translation((x, y, z)) @ Matrix.Rotation(LEAN, 4, "X")


def phone_matrix(x, y, z):
    # Maillage du gilet : axe long sur X (bouton home côté +X), écran vers -Y. Debout : home en bas, écran vers +Y.
    return lean_at(x, y, z + 0.004) @ Matrix.Translation((0, 0, PH_H / 2)) @ Matrix.Rotation(math.pi, 4, "Z") @ Matrix.Rotation(math.pi / 2, 4, "Y")


def build_body(m):
    parts = []
    hx = W / 2
    parts.append(box(-hx, -D / 2, FEET, hx, D / 2, DECK, m["body"], 0.004, "base"))
    parts.append(box(-hx, -D / 2, DECK - 0.004, hx, 0.0, RISER, m["body"], 0.004, "riser"))
    # Patins.
    for sx in (-1, 1):
        for sy in (-1, 1):
            parts.append(cyl(sx * 0.25, sy * 0.095, FEET / 2, 0.013, FEET, m["rubber"], name="foot"))
    # Berceaux, dosserets et ailettes.
    for (x, y, z) in slots():
        parts.append(box(x - 0.049, y - 0.018, z - 0.001, x + 0.049, y + 0.018, z + 0.014, m["rubber"], 0.003, "cradle"))
        parts.append(box(x - 0.042, y - 0.016, z + 0.0135, x + 0.042, y + 0.016, z + 0.0145, m["dark"], 0.0, "pocket"))
        parts.append(box(-0.026, -PH_D / 2 - 0.008, 0.0, 0.026, -PH_D / 2 - 0.001, 0.06, m["rubber"], 0.002, "rest", matrix=lean_at(x, y, z)))
    for (y, z) in ((ROW_A_Y, DECK), (ROW_B_Y, RISER)):
        for i in range(6):
            fx = -hx + 0.012 + i * (W - 0.024) / 5 if i in (0, 5) else (SLOT_X[i - 1] + SLOT_X[i]) / 2
            parts.append(box(-0.003, -0.02, 0.0, 0.003, 0.022, 0.055, m["rubber"], 0.0015, "fin", matrix=lean_at(fx, y, z)))
    # Poignées latérales (zone haute du gradin).
    for sx in (-1, 1):
        x = sx * hx
        for py in (-0.098, -0.022):
            parts.append(cyl(x + sx * 0.012, py, 0.088, 0.0055, 0.024, m["metal"], axis="X", name="post"))
        parts.append(cyl(x + sx * 0.024, -0.06, 0.088, 0.0065, 0.09, m["grip"], axis="Y", name="grip"))
    # Vis aux coins.
    for (x, y, z) in [(sx * 0.272, yy, zz) for sx in (-1, 1) for (yy, zz) in ((-0.11, RISER), (-0.012, RISER), (0.11, DECK))]:
        parts.append(cyl(x, y, z + 0.0006, 0.0042, 0.0016, m["metal"], verts=16, name="screw"))
        parts.append(cyl(x, y, z + 0.0012, 0.0016, 0.0008, m["dark"], verts=6, name="torx"))
    # Dos : prise secteur, interrupteur, ouïes, plaque signalétique.
    yb = -D / 2
    parts.append(box(0.17, yb - 0.006, 0.058, 0.215, yb + 0.001, 0.09, m["dark"], 0.002, "inlet"))
    parts.append(box(0.18, yb - 0.0075, 0.066, 0.205, yb - 0.005, 0.082, m["black"], 0.0, "inlet_pins"))
    parts.append(box(0.232, yb - 0.006, 0.064, 0.25, yb + 0.001, 0.084, m["dark"], 0.0015, "switch"))
    parts.append(box(0.236, yb - 0.010, 0.072, 0.246, yb - 0.005, 0.082, m["red"], 0.001, "switch_rocker"))
    for i in range(9):
        vx = -0.23 + i * 0.016
        parts.append(box(vx, yb - 0.0006, 0.055, vx + 0.007, yb + 0.0005, 0.105, m["black"], 0.0, "vent"))
    pw, ph = 0.090, 0.034
    # Quadrilatères : bas-gauche, bas-droite, haut-droite, haut-gauche vus par le spectateur (de dos, sa gauche est -X).
    parts.append(quad([(0.02, yb - 0.0004, 0.06), (0.02 + pw, yb - 0.0004, 0.06), (0.02 + pw, yb - 0.0004, 0.06 + ph), (0.02, yb - 0.0004, 0.06 + ph)],
                      [(0, 0), (1, 0), (1, 1), (0, 1)], m["plate_rear"], "plate_rear"))
    # Bandeau avant (voyants 01 à 05) et bord du gradin (06 à 10), plaque de marque sur le pont avant.
    yf = D / 2 + 0.0004
    parts.append(quad([(hx, yf, 0.011), (-hx, yf, 0.011), (-hx, yf, 0.045), (hx, yf, 0.045)], [(0, 0), (1, 0), (1, 1), (0, 1)], m["strip_front"], "strip_front"))
    zr = RISER + 0.0004
    parts.append(quad([(hx, 0.0, zr), (-hx, 0.0, zr), (-hx, -0.022, zr), (hx, -0.022, zr)], [(0, 0), (1, 0), (1, 1), (0, 1)], m["strip_riser"], "strip_riser"))
    zd = DECK + 0.0004
    parts.append(quad([(0.06, 0.112, zd), (-0.06, 0.112, zd), (-0.06, 0.096, zd), (0.06, 0.096, zd)], [(0, 0), (1, 0), (1, 1), (0, 1)], m["plate_brand"], "plate_brand"))
    return parts


def build_phones(tints, m):
    """Téléphones, écrans et voyants, un trio par emplacement. tints : teinte par emplacement (None = vide)."""
    out = []
    sx0, sy0, sx1, sy1 = 455, 716, 1546, 1333
    for i, (x, y, z) in enumerate(slots()):
        n = i + 1
        ph, po = vp.build()
        bpy.data.objects.remove(po)
        ph.name = f"phone{n}"
        ph.data.uv_layers[0].name = "Src"
        ph.data.transform(phone_matrix(x, y, z))
        ph.data.materials.clear()
        ph.data.materials.append(m["phone_" + (tints[i] or "coyote")])
        out.append(("phone", n, ph, tints[i] is None))
        # Écran : rectangle de l'écran de la photo (paysage) -> portrait, 0,4 mm devant la vitre.
        cs = []
        for u, v in ((sx0, sy0), (sx1, sy0), (sx1, sy1), (sx0, sy1)):
            mx, mz = vp.to_model(u, v)
            cs.append((mx, -vp.DEPTH / 2 - 0.0004, mz))
        uvs = [(1 - (v - sy0) / (sy1 - sy0), 1 - (u - sx0) / (sx1 - sx0)) for u, v in ((sx0, sy0), (sx1, sy0), (sx1, sy1), (sx0, sy1))]
        sc = quad(cs, uvs, m["screen_%d" % (i % 6)], f"screen{n}")
        sc.data.transform(phone_matrix(x, y, z))
        out.append(("screen", n, sc, tints[i] is None))
        # Voyant : rangée basse sur le bandeau avant, rangée haute sur le bord du gradin (centre du cadre sérigraphié).
        if n <= 5:
            zc, yf = 0.011 + 0.034 * (1 - 0.31), D / 2 + 0.0008
            lc = [(x + 0.0065, yf, zc - 0.0035), (x - 0.0065, yf, zc - 0.0035), (x - 0.0065, yf, zc + 0.0035), (x + 0.0065, yf, zc + 0.0035)]
        else:
            yc, zr = -0.022 + 0.022 * 0.31, RISER + 0.0008
            lc = [(x + 0.0065, yc + 0.0035, zr), (x - 0.0065, yc + 0.0035, zr), (x - 0.0065, yc - 0.0035, zr), (x + 0.0065, yc - 0.0035, zr)]
        led = quad(lc, [(0, 0), (1, 0), (1, 1), (0, 1)], m["led_" + ("off" if tints[i] is None else ("green" if i % 3 else "amber"))], f"led{n}")
        out.append(("led", n, led, False))
    return out


def materials():
    m = {
        "body": mat_anodized("body", (0.105, 0.11, 0.115)),
        "rubber": mat_rubber("rubber", (0.045, 0.047, 0.05)),
        "grip": mat_rubber("grip", (0.03, 0.03, 0.032), 0.9),
        "dark": mat_simple("dark", (0.025, 0.026, 0.028), 0.7),
        "black": mat_simple("black", (0.008, 0.008, 0.009), 0.9),
        "metal": mat_simple("metal", (0.55, 0.56, 0.57), 0.32, 1.0),
        "red": mat_simple("red", (0.55, 0.06, 0.04), 0.4),
        "strip_front": mat_decal("strip_front", os.path.join(RENDER, "dock_strip_front.png"), (0.105, 0.11, 0.115)),
        "strip_riser": mat_decal("strip_riser", os.path.join(RENDER, "dock_strip_riser.png"), (0.105, 0.11, 0.115)),
        "plate_brand": mat_decal("plate_brand", os.path.join(RENDER, "dock_plate_brand.png"), (0.105, 0.11, 0.115)),
        "plate_rear": mat_decal("plate_rear", os.path.join(RENDER, "dock_plate_rear.png"), (0.6, 0.6, 0.6)),
        "led_off": mat_simple("led_off", (0.02, 0.02, 0.02), 0.3),
        "led_green": mat_simple("led_green", (0.1, 1.0, 0.25), 0.3, emit=6.0),
        "led_amber": mat_simple("led_amber", (1.0, 0.55, 0.05), 0.3, emit=6.0),
    }
    for t, f in (("coyote", "vest_phone_co.png"), ("od", "vest_phone_od_co.png"), ("black", "vest_phone_black_co.png")):
        m["phone_" + t] = mat_image("phone_" + t, os.path.join(PHONE_PNG, f), 0.0, 0.55)
    for lv in range(6):
        m["screen_%d" % lv] = mat_image("screen_%d" % lv, os.path.join(RENDER, f"dock_screen_{lv}.png"), 1.6, 0.08)
    return m


def scene_setup():
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.device = "CPU"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    sc.view_settings.view_transform = "AgX"
    world = bpy.data.worlds.new("w")
    sc.world = world
    world.use_nodes = True
    return sc


def render_preview(sc, path, cam_loc, target, lens=50, res=(1600, 1000), table=True):
    if table:
        tm = mat_rubber("table", (0.16, 0.13, 0.09), 0.6)
        box(-0.6, -0.4, -0.03, 0.6, 0.5, 0.0, tm, 0.0, "table")
    bg = sc.world.node_tree.nodes["Background"]
    bg.inputs[0].default_value = (0.36, 0.38, 0.40, 1)
    bg.inputs[1].default_value = 0.35
    for nm, loc, en, sz in (("key", (-0.5, 0.6, 0.9), 70, 0.8), ("fill", (0.7, 0.3, 0.4), 18, 0.8), ("rim", (0.2, -0.7, 0.6), 30, 0.6)):
        li = bpy.data.lights.new(nm, "AREA")
        li.energy = en
        li.size = sz
        o = bpy.data.objects.new(nm, li)
        o.location = loc
        o.rotation_euler = (Vector(target) - Vector(loc)).to_track_quat("-Z", "Y").to_euler()
        sc.collection.objects.link(o)
    cd = bpy.data.cameras.new("cam")
    cd.lens = lens
    cam = bpy.data.objects.new("cam", cd)
    cam.location = cam_loc
    cam.rotation_euler = (Vector(target) - Vector(cam_loc)).to_track_quat("-Z", "Y").to_euler()
    sc.collection.objects.link(cam)
    sc.camera = cam
    sc.render.resolution_x, sc.render.resolution_y = res
    sc.render.film_transparent = not table
    sc.render.filepath = path
    bpy.ops.render.render(write_still=True)
    for o in [o for o in bpy.data.objects if o.type in ("LIGHT", "CAMERA") or o.name == "table"]:
        bpy.data.objects.remove(o)


def join(objs, name):
    bpy.ops.object.select_all(action="DESELECT")
    for o in objs:
        o.select_set(True)
    bpy.context.view_layer.objects.active = objs[0]
    bpy.ops.object.join()
    ob = bpy.context.view_layer.objects.active
    ob.name = name
    return ob


def bake_body(sc, body):
    """Cuisson diffuse (couleur + éclairage direct et indirect sous un ciel blanc uniforme) : teinte et occlusion."""
    me = body.data
    if "Src" not in me.uv_layers:
        me.uv_layers.new(name="Src")
    bake_uv = me.uv_layers.new(name="Bake")
    me.uv_layers.active = bake_uv
    bpy.ops.object.select_all(action="DESELECT")
    body.select_set(True)
    bpy.context.view_layer.objects.active = body
    bpy.ops.object.mode_set(mode="EDIT")
    bpy.ops.mesh.select_all(action="SELECT")
    bpy.ops.uv.smart_project(angle_limit=math.radians(60), island_margin=0.004, area_weight=0.0, scale_to_bounds=True)
    bpy.ops.object.mode_set(mode="OBJECT")
    img = bpy.data.images.new("dock_bake", BAKE_SIZE, BAKE_SIZE)
    for mat in me.materials:
        n = mat.node_tree.nodes
        t = n.new("ShaderNodeTexImage")
        t.image = img
        uvn = n.new("ShaderNodeUVMap")
        uvn.uv_map = "Bake"
        mat.node_tree.links.new(uvn.outputs["UV"], t.inputs["Vector"])
        for x in n:
            x.select = False
        t.select = True
        n.active = t
    bg = sc.world.node_tree.nodes["Background"]
    bg.inputs[0].default_value = (1, 1, 1, 1)
    bg.inputs[1].default_value = 1.0
    sc.cycles.samples = 128
    sc.render.bake.use_pass_direct = True
    sc.render.bake.use_pass_indirect = True
    sc.render.bake.use_pass_color = True
    sc.render.bake.margin = 8
    bpy.ops.object.bake(type="DIFFUSE")
    out = os.path.join(RENDER, "charging_dock_co.png")
    img.filepath_raw = out
    img.file_format = "PNG"
    img.save()
    print("bake", out)
    # Le P3D ne garde que l'UV de la cuisson.
    me.uv_layers.remove(me.uv_layers["Src"])


def a3_material(name, tex, rvmat=""):
    mat = bpy.data.materials.new(name)
    p = mat.a3ob_properties_material
    p.texture_type = "TEX"
    p.texture_path = tex
    p.material_path = rvmat
    return mat


def lod_box_parts(name, lod, res=0, mass=0.0, tri=False):
    """Deux boîtes convexes (pont avant, gradin) ; masse répartie sur les sommets."""
    objs = [box(-W / 2, 0.0, FEET, W / 2, D / 2, DECK, None, 0.0, name + "_a"), box(-W / 2, -D / 2, FEET, W / 2, 0.0, RISER + 0.03, None, 0.0, name + "_b")]
    for o in objs:
        o.data.materials.clear()
    ob = join(objs, name)
    if tri:
        bm = bmesh.new()
        bm.from_mesh(ob.data)
        bmesh.ops.triangulate(bm, faces=bm.faces)
        bm.to_mesh(ob.data)
        bm.free()
    if mass:
        layer = ob.data.attributes.new("a3ob_mass", "FLOAT", "POINT")
        for i in range(len(ob.data.vertices)):
            layer.data[i].value = mass / len(ob.data.vertices)
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = str(lod)
    p.resolution = res
    return ob


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    sc = scene_setup()
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    m = materials()
    body = build_body(m)
    # Aperçu : socle presque plein, coques mélangées, emplacements 4 et 9 libres.
    tints = ["coyote", "od", "coyote", None, "black", "coyote", "coyote", "od", None, "coyote"]
    items = build_phones(tints, m)
    for kind, n, ob, empty in items:
        ob.hide_render = empty
    if os.environ.get("NO_PREVIEW") != "1":
        render_preview(sc, os.path.join(RENDER, "charging_dock_preview.png"), (0.42, 0.78, 0.55), (0.0, 0.0, 0.11), 42)
        render_preview(sc, os.path.join(RENDER, "charging_dock_preview_side.png"), (-0.62, 0.38, 0.34), (0.0, 0.0, 0.12), 45)
        render_preview(sc, os.path.join(RENDER, "charging_dock_icon.png"), (0.55, 1.0, 0.75), (0.0, 0.0, 0.11), 55, (512, 512), False)
    for kind, n, ob, empty in items:
        ob.hide_render = False
    # Cuisson du socle seul (téléphones absents de la scène pour ne pas cuire leurs ombres).
    for kind, n, ob, empty in items:
        ob.hide_render = True
    dock = join(body, "dock")
    bake_body(sc, dock)
    for kind, n, ob, empty in items:
        ob.hide_render = False
    # Matières Arma : une texture par pièce.
    dock.data.materials.clear()
    dock.data.materials.append(a3_material("a3_dock", TEX + "charging_dock_co.paa"))
    a3 = {"phone": a3_material("a3_phone", TEX + "vest_phone_co.paa"),
          "screen": a3_material("a3_screen", TEX + "dock_screen_5_co.paa", TEX + "dock_glow.rvmat"),
          "led": a3_material("a3_led", TEX + "dock_led_co.paa", TEX + "dock_glow.rvmat")}
    for kind, n, ob, empty in items:
        if "Src" in ob.data.uv_layers:
            ob.data.uv_layers["Src"].name = "Bake"
        else:
            ob.data.uv_layers[0].name = "Bake"
        ob.data.materials.clear()
        ob.data.materials.append(a3[kind])
        g = ob.vertex_groups.new(name=f"{kind}{n}")
        g.add([v.index for v in ob.data.vertices], 1.0, "REPLACE")
    vis = join([dock] + [ob for _, _, ob, _ in items], "lod0")
    # P3D : pas de polygones de plus de 4 côtés (couvercles des cylindres, biseaux).
    bm = bmesh.new()
    bm.from_mesh(vis.data)
    bmesh.ops.triangulate(bm, faces=[f for f in bm.faces if len(f.verts) > 4])
    bm.to_mesh(vis.data)
    bm.free()
    p = vis.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = "0"
    p.resolution = 1
    shadow = lod_box_parts("shadow", 4, 0, tri=True)
    geo = lod_box_parts("geometry", 6, 0, mass=9.0)
    fire = lod_box_parts("fire", 15, 0)
    bpy.ops.object.select_all(action="DESELECT")
    out = os.path.join(DATA, "charging_dock.p3d")
    res = bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    print("export", res, out, [o.name for o in (vis, shadow, geo, fire)])


if __name__ == "__main__":
    main()
