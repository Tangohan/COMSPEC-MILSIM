"""
Rendus 3D (Blender / Cycles) des pièces « réalistes » de l'ATAK natif : module DL-7 (trois teintes), panneau
arrière de la coque (dos du téléphone), batterie externe et balise IR (accessoires vus de dos). Les PNG produits sont repris par gen_assets.py (datalink_*, phone_back*,
item_datacable). Prérequis : pip install bpy (Blender comme module Python, 5.x).

    python3 render_3d.py [sortie]      # par défaut tools/render/

Repères : 1 unité Blender = 100 px de la texture ; X vers la droite, Y vers le haut (image : y vers le bas).
"""
import math
import os
import sys

import bpy  # noqa: I001 (bmesh et mathutils ne se chargent qu'après bpy)
import bmesh
from mathutils import Vector

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "render")
FONT_B = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_R = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "96"))

# Module DL-7 : 700 x 290 px dans les textures (gen_assets.DATALINK), rendu avec une marge pour l'ombre et les prises.
MOD_W, MOD_H, MOD_PAD = 700, 290, 70
MOD_TINTS = {"olive": (0.30, 0.32, 0.22), "black": (0.11, 0.115, 0.11), "tan": (0.58, 0.50, 0.36)}
# Dos du téléphone : panneau dans la texture 2048 (miroir de la face), x 372..1730, y 690..1358.
BACK_COYOTE = (0.55, 0.49, 0.35)


def srgb(c):
    return tuple(((v + 0.055) / 1.055) ** 2.4 if v > 0.04045 else v / 12.92 for v in c) + (1.0,)


def reset():
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.device = "CPU"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    try:
        sc.cycles.denoiser = "OPENIMAGEDENOISE"
    except TypeError:
        pass
    sc.render.film_transparent = True
    sc.render.image_settings.file_format = "PNG"
    sc.render.image_settings.color_mode = "RGBA"
    sc.view_settings.view_transform = "Standard"
    sc.view_settings.look = "None"
    world = bpy.data.worlds.new("w")
    sc.world = world
    world.use_nodes = True
    bg = world.node_tree.nodes["Background"]
    bg.inputs[0].default_value = (0.20, 0.21, 0.20, 1)
    bg.inputs[1].default_value = 0.55
    return sc


def px(u, v, cx, cy):
    """Pixel de texture (u, v) -> coordonnées Blender, (cx, cy) = centre du cadre rendu en pixels."""
    return ((u - cx) / 100.0, (cy - v) / 100.0)


# --- Matériaux ---
def mat_paint(name, color, rough=0.55, metal=0.0, grain=0.4, wear=0.0):
    """Peinture / Cerakote : grain fin en relief, légère variation de teinte, usure claire sur les arêtes."""
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    n = nt.nodes
    bsdf = n["Principled BSDF"]
    bsdf.inputs["Roughness"].default_value = rough
    bsdf.inputs["Metallic"].default_value = metal
    tex = n.new("ShaderNodeTexNoise")
    tex.inputs["Scale"].default_value = 900.0
    tex.inputs["Detail"].default_value = 6.0
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.10 * grain
    bump.inputs["Distance"].default_value = 0.004
    nt.links.new(tex.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], bsdf.inputs["Normal"])
    # Variation de teinte à grande échelle (taches, poussière).
    big = n.new("ShaderNodeTexNoise")
    big.inputs["Scale"].default_value = 6.0
    big.inputs["Detail"].default_value = 3.0
    ramp = n.new("ShaderNodeValToRGB")
    lo = tuple(max(0, c * 0.86) for c in srgb(color)[:3]) + (1,)
    hi = tuple(min(1, c * 1.10) for c in srgb(color)[:3]) + (1,)
    ramp.color_ramp.elements[0].color = lo
    ramp.color_ramp.elements[1].color = hi
    nt.links.new(big.outputs["Fac"], ramp.inputs["Fac"])
    col = ramp.outputs["Color"]
    if wear > 0:
        geo = n.new("ShaderNodeNewGeometry")
        wr = n.new("ShaderNodeValToRGB")
        wr.color_ramp.elements[0].position = 0.52
        wr.color_ramp.elements[1].position = 0.60
        mix = n.new("ShaderNodeMix")
        mix.data_type = "RGBA"
        nt.links.new(geo.outputs["Pointiness"], wr.inputs["Fac"])
        mul = n.new("ShaderNodeMath")
        mul.operation = "MULTIPLY"
        mul.inputs[1].default_value = wear
        nt.links.new(wr.outputs["Color"], mul.inputs[0])
        nt.links.new(mul.outputs[0], mix.inputs["Factor"])
        nt.links.new(col, mix.inputs["A"])
        mix.inputs["B"].default_value = srgb((0.62, 0.62, 0.58))
        col = mix.outputs["Result"]
    nt.links.new(col, bsdf.inputs["Base Color"])
    return m


def mat_simple(name, color, rough=0.5, metal=0.0, coat=0.0, alpha=1.0, emit=None):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = m.node_tree.nodes["Principled BSDF"]
    b.inputs["Base Color"].default_value = srgb(color)
    b.inputs["Roughness"].default_value = rough
    b.inputs["Metallic"].default_value = metal
    if coat:
        b.inputs["Coat Weight"].default_value = coat
    if emit:
        b.inputs["Emission Color"].default_value = srgb(emit)
        b.inputs["Emission Strength"].default_value = 1.0
    return m


def mat_brushed(name, color, rough=0.35):
    """Aluminium anodisé brossé (plaque de fixation)."""
    m = mat_simple(name, color, rough, 0.85)
    nt = m.node_tree
    n = nt.nodes
    b = n["Principled BSDF"]
    tc = n.new("ShaderNodeTexCoord")
    mp = n.new("ShaderNodeMapping")
    mp.inputs["Scale"].default_value = (1.0, 220.0, 1.0)
    tex = n.new("ShaderNodeTexNoise")
    tex.inputs["Scale"].default_value = 8.0
    tex.inputs["Detail"].default_value = 10.0
    bump = n.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.06
    nt.links.new(tc.outputs["Object"], mp.inputs["Vector"])
    nt.links.new(mp.outputs["Vector"], tex.inputs["Vector"])
    nt.links.new(tex.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], b.inputs["Normal"])
    return m


def mat_image(name, path, rough=0.6):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    b = nt.nodes["Principled BSDF"]
    b.inputs["Roughness"].default_value = rough
    t = nt.nodes.new("ShaderNodeTexImage")
    t.image = bpy.data.images.load(path)
    tc = nt.nodes.new("ShaderNodeTexCoord")
    nt.links.new(tc.outputs["Generated"], t.inputs["Vector"])
    nt.links.new(t.outputs["Color"], b.inputs["Base Color"])
    return m


def mat_grip(name, color, scale=5.0, strength=0.35):
    """Caoutchouc à motif losange (damier tourné de 45°) en relief."""
    m = mat_paint(name, color, rough=0.78, grain=1.2)
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
    old = n["Principled BSDF"].inputs["Normal"].links[0].from_node
    nt.links.new(old.outputs["Normal"], bump.inputs["Normal"])
    nt.links.new(bump.outputs["Normal"], n["Principled BSDF"].inputs["Normal"])
    return m


# --- Géométrie ---
def cut(ob, cutter):
    """Perce ob avec cutter (booléen), cutter masqué au rendu."""
    mod = ob.modifiers.new("cut", "BOOLEAN")
    mod.operation = "DIFFERENCE"
    mod.object = cutter
    mod.solver = "EXACT"
    cutter.hide_render = True


def rrect(x, y, w, h, r, z0, z1, mat, bevel=0.02, name="rr", seg=10):
    """Prisme à coins arrondis : (x, y) coin haut gauche en BU (Y vers le haut), extrudé de z0 à z1, arêtes adoucies."""
    r = min(r, w / 2 - 1e-4, h / 2 - 1e-4)
    # Ordre : coin haut droit (90->0), bas droit (0->-90), bas gauche (-90->-180), haut gauche (180->90).
    pts = []
    for (cx, cy), start in (((x + w - r, y - r), 90), ((x + w - r, y - h + r), 0), ((x + r, y - h + r), -90), ((x + r, y - r), -180)):
        for i in range(seg + 1):
            a = math.radians(start - i * 90 / seg)
            pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bot = [bm.verts.new((px_, py_, z0)) for px_, py_ in pts]
    top = [bm.verts.new((px_, py_, z1)) for px_, py_ in pts]
    bm.faces.new(list(reversed(bot)))
    bm.faces.new(top)
    n = len(pts)
    for i in range(n):
        bm.faces.new((bot[i], bot[(i + 1) % n], top[(i + 1) % n], top[i]))
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    ob.data.materials.append(mat)
    if bevel > 0:
        mod = ob.modifiers.new("bev", "BEVEL")
        mod.width = bevel
        mod.segments = 4
        mod.limit_method = "ANGLE"
        mod.angle_limit = math.radians(50)
        mod.harden_normals = True
    for p in ob.data.polygons:
        p.use_smooth = True
    return ob


def cyl(x, y, r, z0, z1, mat, name="cyl", verts=48, bevel=0.0):
    bpy.ops.mesh.primitive_cylinder_add(vertices=verts, radius=r, depth=z1 - z0, location=(x, y, (z0 + z1) / 2))
    ob = bpy.context.active_object
    ob.name = name
    ob.data.materials.append(mat)
    bpy.ops.object.shade_smooth()
    if bevel:
        mod = ob.modifiers.new("bev", "BEVEL")
        mod.width = bevel
        mod.segments = 3
        mod.limit_method = "ANGLE"
    return ob


def dome(x, y, r, z, h, mat, name="dome"):
    bpy.ops.mesh.primitive_uv_sphere_add(radius=r, location=(x, y, z), segments=48, ring_count=24)
    ob = bpy.context.active_object
    ob.scale = (1, 1, h / r)
    ob.data.materials.append(mat)
    bpy.ops.object.shade_smooth()
    return ob


def hexagon(x, y, r, z0, z1, mat, rot=0.0):
    bpy.ops.mesh.primitive_cylinder_add(vertices=6, radius=r, depth=z1 - z0, location=(x, y, (z0 + z1) / 2), rotation=(0, 0, rot))
    ob = bpy.context.active_object
    ob.data.materials.append(mat)
    return ob


def text(s, x, y, z, size, mat, font=FONT_B, align="LEFT", extrude=0.004, rot=0.0):
    cu = bpy.data.curves.new("t", "FONT")
    cu.body = s
    cu.font = bpy.data.fonts.load(font, check_existing=True)
    cu.size = size
    cu.extrude = extrude
    cu.align_x = align
    cu.align_y = "BOTTOM_BASELINE"
    ob = bpy.data.objects.new("t", cu)
    ob.location = (x, y, z)
    ob.rotation_euler = (0, 0, rot)
    bpy.context.collection.objects.link(ob)
    ob.data.materials.append(mat)
    return ob


def screw(x, y, r, z, steel, dark):
    """Vis à tête cylindrique et six pans creux."""
    cyl(x, y, r, z, z + r * 0.45, steel, bevel=r * 0.18)
    hexagon(x, y, r * 0.45, z + r * 0.30, z + r * 0.50, dark, rot=0.3)


def phillips(x, y, r, z, steel, dark):
    """Vis cruciforme à tête bombée."""
    dome(x, y, r, z, r * 0.35, steel)
    for a in (math.pi / 4, -math.pi / 4):
        bpy.ops.mesh.primitive_cube_add(size=1, location=(x, y, z + r * 0.3), rotation=(0, 0, a))
        ob = bpy.context.active_object
        ob.scale = (r * 1.2, r * 0.22, r * 0.2)
        ob.data.materials.append(dark)


def lights(key=(-4, 6, 12), power=2600, size=8, fill=420):
    """Grande boîte à lumière en haut à gauche (même sens que la photo de la face), contre-jour doux."""
    l = bpy.data.lights.new("key", "AREA")
    l.energy = power
    l.size = size
    o = bpy.data.objects.new("key", l)
    o.location = key
    o.rotation_euler = Vector((0, 0, 0)).to_track_quat("Z", "Y").to_euler()
    bpy.context.collection.objects.link(o)
    direction = Vector((0, 0, 0)) - Vector(key)
    o.rotation_euler = direction.to_track_quat("-Z", "Y").to_euler()
    f = bpy.data.lights.new("fill", "AREA")
    f.energy = fill
    f.size = 10
    fo = bpy.data.objects.new("fill", f)
    fo.location = (6, -5, 9)
    fo.rotation_euler = (Vector((0, 0, 0)) - Vector(fo.location)).to_track_quat("-Z", "Y").to_euler()
    bpy.context.collection.objects.link(fo)


def camera(cx_bu, cy_bu, ortho, res_x, res_y):
    sc = bpy.context.scene
    cam = bpy.data.cameras.new("cam")
    cam.type = "ORTHO"
    cam.ortho_scale = ortho
    o = bpy.data.objects.new("cam", cam)
    o.location = (cx_bu, cy_bu, 30)
    bpy.context.collection.objects.link(o)
    sc.camera = o
    sc.render.resolution_x = res_x
    sc.render.resolution_y = res_y
    sc.render.resolution_percentage = 100
    cam.clip_end = 100


def shadow_catcher(w, h):
    bpy.ops.mesh.primitive_plane_add(size=1, location=(0, 0, 0))
    p = bpy.context.active_object
    p.scale = (w, h, 1)
    p.is_shadow_catcher = True
    p.data.materials.append(mat_simple("ground", (0.5, 0.5, 0.5), 0.9))


# --- Module DL-7 ---
def render_module(tint, path, scale=2):
    reset()
    W, H = MOD_W / 100, MOD_H / 100
    cxp, cyp = MOD_W / 2, MOD_H / 2
    P = lambda u, v: px(u, v, cxp, cyp)  # noqa: E731
    body_c = MOD_TINTS[tint]
    body = mat_paint("body", body_c, rough=0.5, metal=0.15, grain=0.6, wear=0.8 if tint != "black" else 0.5)
    rubber = mat_paint("rubber", (0.06, 0.065, 0.06), rough=0.75, grain=1.0)
    blackm = mat_simple("black", (0.03, 0.03, 0.03), 0.45)
    steel = mat_simple("steel", (0.75, 0.76, 0.74), 0.28, 1.0)
    chrome = mat_simple("chrome", (0.9, 0.9, 0.88), 0.12, 1.0)
    glass = mat_simple("glass", (0.002, 0.006, 0.004), 0.02, 0.0, coat=0.35)
    lens = mat_simple("lens", (0.04, 0.045, 0.04), 0.08, coat=1.0)
    ink = mat_simple("ink", (0.86, 0.87, 0.82), 0.6)
    T = 0.62  # épaisseur du boîtier
    # Boîtier : bloc arrondi, chanfrein généreux.
    x0, y0 = P(0, 0)
    rrect(x0, y0, W, H, 0.24, 0.0, T, body, bevel=0.07, name="body")
    # Bandeau creux autour de la façade (rainure).
    x1, y1 = P(18, 14)
    rrect(x1, y1, W - 0.36, H - 0.28, 0.16, T - 0.01, T + 0.004, mat_paint("groove", tuple(c * 0.8 for c in body_c), 0.6), bevel=0.01)
    # Capuchons caoutchouc d'angle avec vis.
    k = MOD_H * 0.15
    for cu, cv in ((k * 0.35, k * 0.35), (MOD_W - k * 0.35, k * 0.35), (k * 0.35, MOD_H - k * 0.35), (MOD_W - k * 0.35, MOD_H - k * 0.35)):
        bx, by = P(cu - k, cv - k)
        rrect(bx, by, 2 * k / 100, 2 * k / 100, k * 0.35 / 100, -0.02, T + 0.12, rubber, bevel=0.05)
        sx, sy = P(cu, cv)
        cyl(sx, sy, k * 0.30 / 100, T + 0.08, T + 0.125, blackm)
        screw(sx, sy, k * 0.25 / 100, T + 0.10, steel, blackm)
    # Prises latérales (caoutchouc noir).
    for su in (-MOD_W * 0.015, MOD_W * 1.015):
        bx, by = P(su - MOD_W * 0.035, MOD_H * 0.36)
        rrect(bx, by, MOD_W * 0.07 / 100, MOD_H * 0.28 / 100, 0.06, 0.05, T - 0.05, rubber, bevel=0.03)
    # Boutons sur la tranche haute.
    for fu in (0.37, 0.45):
        bx, by = P(MOD_W * fu, -MOD_H * 0.05)
        rrect(bx, by, MOD_W * 0.05 / 100, MOD_H * 0.08 / 100, 0.03, 0.1, T - 0.1, rubber, bevel=0.02)
    # Écran LCD : cadre noir, verre en retrait (le texte est affiché par le jeu).
    lu, lv, lw, lh = MOD_W * 0.1, MOD_H * 0.38, MOD_W * 0.46, MOD_H * 0.42
    bx, by = P(lu - 9, lv - 9)
    rrect(bx, by, (lw + 18) / 100, (lh + 18) / 100, 0.11, T - 0.02, T + 0.05, blackm, bevel=0.02)
    bx, by = P(lu, lv)
    rrect(bx, by, lw / 100, lh / 100, 0.06, T, T + 0.035, glass, bevel=0.005)
    # Voyants : lunette chromée, lentille bombée sombre (allumée par le jeu).
    for fu, lab in ((0.655, "PWR"), (0.77, "AUTH"), (0.885, "DATA")):
        cx, cy = P(MOD_W * fu, MOD_H * 0.52)
        cyl(cx, cy, MOD_W * 0.04 / 100, T, T + 0.06, chrome, bevel=0.012)
        cyl(cx, cy, MOD_W * 0.029 / 100, T + 0.05, T + 0.065, blackm)
        dome(cx, cy, MOD_W * 0.027 / 100, T + 0.06, 0.035, lens)
        tx, ty = P(MOD_W * fu, MOD_H * 0.76)
        text(lab, tx, ty, T + 0.002, MOD_H * 0.07 / 100, ink, align="CENTER")
    # Marquage sérigraphié.
    tx, ty = P(MOD_W * 0.1, MOD_H * 0.29)
    text("COMSPEC", tx, ty, T + 0.002, MOD_H * 0.13 / 100, ink)
    tx, ty = P(MOD_W * 0.88, MOD_H * 0.29)
    text("DL-7 · DATA LINK", tx, ty, T + 0.002, MOD_H * 0.10 / 100, ink, font=FONT_R, align="RIGHT")
    # Vis de façade et aérations.
    for fu, fv in ((0.1, 0.86), (0.9, 0.86)):
        cx, cy = P(MOD_W * fu, MOD_H * fv)
        phillips(cx, cy, MOD_H * 0.024 / 100, T, steel, blackm)
    for i in range(6):
        bx, by = P(MOD_W * (0.635 + i * 0.047), MOD_H * 0.84)
        rrect(bx, by, MOD_W * 0.028 / 100, MOD_H * 0.026 / 100, 0.012, T - 0.03, T + 0.003, blackm, bevel=0.004)
    shadow_catcher(12, 8)
    lights(key=(-3.5, 4.5, 9), power=900, size=6, fill=160)
    sp = MOD_PAD
    camera(0, 0, (MOD_W + 2 * sp) / 100, (MOD_W + 2 * sp) * scale, (MOD_H + 2 * sp) * scale)
    bpy.context.scene.render.filepath = path
    bpy.ops.render.render(write_still=True)


# --- Dos du téléphone (panneau arrière, plaque, appareil photo, étiquette) ---
def label_png(path):
    from PIL import Image, ImageDraw, ImageFont
    w, h = 380, 340
    im = Image.new("RGB", (w, h), (226, 222, 206))
    d = ImageDraw.Draw(im)
    fb = ImageFont.truetype(FONT_B, 46)
    fr = ImageFont.truetype(FONT_R, 30)
    fm = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", 26)
    d.text((w / 2, 52), "COMSPEC", font=fb, fill=(30, 30, 30), anchor="mm")
    d.text((w / 2, 100), "ATAK · TAC-S7", font=fr, fill=(60, 60, 60), anchor="mm")
    x = 36
    i = 0
    while x < w - 36:
        bw = 2 + (i * 7) % 5
        d.rectangle((x, 130, x + bw * 1.6, 240), fill=(20, 20, 20))
        x += bw * 1.6 + 3 + (i * 3) % 4
        i += 1
    d.text((w / 2, 280), "S/N CS-0417-ATK", font=fm, fill=(50, 50, 50), anchor="mm")
    d.text((w / 2, 316), "IP68 · MIL-STD-810H", font=ImageFont.truetype(FONT_R, 20), fill=(90, 90, 90), anchor="mm")
    im.save(path)


def render_back(path, size=2048):
    reset()
    C = 1024
    P = lambda u, v: px(u, v, C, C)  # noqa: E731
    tpu = mat_paint("tpu", BACK_COYOTE, rough=0.72, grain=1.4)
    tpu_dark = mat_paint("tpu2", tuple(c * 0.78 for c in BACK_COYOTE), rough=0.8, grain=1.4)
    alu = mat_brushed("alu", (0.07, 0.072, 0.07), 0.38)
    blackm = mat_simple("black", (0.025, 0.025, 0.025), 0.4)
    rubber = mat_paint("rubber", (0.05, 0.05, 0.05), rough=0.8, grain=1.0)
    steel = mat_simple("steel", (0.72, 0.73, 0.71), 0.3, 1.0)
    glass = mat_simple("glass", (0.02, 0.03, 0.05), 0.03, coat=1.0)
    lensblue = mat_simple("lensb", (0.08, 0.12, 0.22), 0.05, coat=1.0)
    flash = mat_simple("flash", (0.93, 0.90, 0.78), 0.25)
    velcro = mat_paint("velcro", (0.13, 0.13, 0.12), rough=0.95, grain=6.0)
    tmp = os.path.join(OUT, "_label.png")
    label_png(tmp)
    label = mat_image("label", tmp, 0.55)
    # Panneau arrière : légèrement bombé au centre (deux couches), rebord en retrait.
    bx, by = P(372, 690)
    rrect(bx, by, 13.58, 6.68, 0.84, 0.0, 0.10, tpu_dark, bevel=0.03)
    bx, by = P(384, 700)
    rrect(bx, by, 13.34, 6.48, 0.78, 0.08, 0.22, tpu, bevel=0.06)
    # Panneau velcro (boucles) pour un patch : 300 x 200 px de patch posé par le jeu au centre (x 450-750, y 850-1050).
    bx, by = P(432, 832)
    rrect(bx, by, 3.36, 2.36, 0.16, 0.2, 0.25, tpu_dark, bevel=0.02)
    bx, by = P(440, 840)
    rrect(bx, by, 3.20, 2.20, 0.12, 0.22, 0.27, velcro, bevel=0.015)
    # Bloc appareil photo (verre noir, objectif, flash, capteur).
    bx, by = P(1600, 890)
    rrect(bx, by, 0.96, 2.68, 0.44, 0.18, 0.36, blackm, bevel=0.05)
    bx, by = P(1612, 905)
    rrect(bx, by, 0.72, 1.40, 0.36, 0.34, 0.38, glass, bevel=0.01)
    cx, cy = P(1648, 975)
    cyl(cx, cy, 0.42, 0.36, 0.42, steel, bevel=0.02)
    cyl(cx, cy, 0.34, 0.40, 0.44, blackm)
    dome(cx, cy, 0.28, 0.43, 0.06, lensblue)
    bx, by = P(1626, 1062)
    rrect(bx, by, 0.44, 0.30, 0.08, 0.34, 0.39, flash, bevel=0.01)
    bx, by = P(1630, 1104)
    rrect(bx, by, 0.36, 0.36, 0.08, 0.34, 0.38, glass, bevel=0.01)
    # Dos simple de S7 dans sa coque : TPU lisse, pas de plaque de fixation (retour de jeu).
    # La batterie externe, quand elle est fixée, se pose au centre (gen_assets.PLATE_C).
    # Étiquette du fabricant (papier sérigraphié, un peu plus haut que le TPU).
    bx, by = P(470, 1110)
    rrect(bx, by, 1.70, 1.52, 0.08, 0.22, 0.226, label, bevel=0)
    # Exposition calée sur la photo de la coque (le panneau doit avoir la même teinte que le rebord).
    lights(key=(-6, 9, 16), power=3300, size=14, fill=560)
    camera(0, 0, 20.48, size, size)
    bpy.context.scene.render.filepath = path
    bpy.ops.render.render(write_still=True)
    os.remove(tmp)


# --- Accessoires : batterie externe sur la plaque, balise IR de la coque (vus de dos) ---
PB_W, PB_H, ACC_PAD = 440, 300, 60
IR_W = 110


def render_powerbank(path):
    """Batterie externe 10 000 mAh tenue sur la plaque par deux sangles velcro ; prise USB-C et câble court."""
    reset()
    P = lambda u, v: px(u, v, PB_W / 2, PB_H / 2)  # noqa: E731
    body = mat_paint("pb", (0.075, 0.08, 0.072), rough=0.7, grain=1.6)
    edge = mat_paint("pb2", (0.05, 0.052, 0.05), rough=0.8, grain=1.2)
    strap = mat_paint("strap", (0.20, 0.22, 0.15), rough=0.95, grain=5.0)
    blackm = mat_simple("black", (0.02, 0.02, 0.02), 0.4)
    led = mat_simple("led", (0.1, 0.9, 0.3), 0.3, emit=(0.2, 1.0, 0.35))
    ledoff = mat_simple("ledoff", (0.05, 0.08, 0.05), 0.3)
    ink = mat_simple("ink", (0.80, 0.80, 0.76), 0.6)
    steel = mat_simple("steel", (0.7, 0.7, 0.68), 0.3, 1.0)
    T = 0.42
    x, y = P(0, 0)
    rrect(x, y, PB_W / 100, PB_H / 100, 0.36, 0.0, T, edge, bevel=0.08, name="pb")
    x, y = P(14, 14)
    rrect(x, y, (PB_W - 28) / 100, (PB_H - 28) / 100, 0.28, T - 0.02, T + 0.03, body, bevel=0.05)
    # Nervures de préhension moulées (bas).
    for i in range(6):
        x, y = P(140 + i * 28, 236)
        rrect(x, y, 0.14, 0.34, 0.05, T + 0.02, T + 0.045, edge, bevel=0.01)
    tx, ty = P(PB_W / 2, 92)
    text("COMSPEC", tx, ty, T + 0.031, 0.32, ink, align="CENTER")
    tx, ty = P(PB_W / 2, 126)
    text("PWR-10K · 10 000 mAh", tx, ty, T + 0.031, 0.15, ink, font=FONT_R, align="CENTER")
    # Témoin de charge : 4 diodes (3 allumées).
    for i in range(4):
        cx, cy = P(PB_W / 2 - 51 + i * 34, 176)
        cyl(cx, cy, 0.07, T + 0.02, T + 0.05, blackm)
        dome(cx, cy, 0.055, T + 0.05, 0.025, led if i < 3 else ledoff)
    # Sangles velcro autour de la plaque (bords), coutures plus sombres.
    seam = mat_paint("seam", (0.12, 0.13, 0.09), rough=0.95, grain=3.0)
    for su in (62, PB_W - 62):
        x, y = P(su - 22, -16)
        rrect(x, y, 0.44, (PB_H + 32) / 100, 0.04, T - 0.1, T + 0.07, strap, bevel=0.015)
        for dv in (8, PB_H + 2):
            x, y = P(su - 20, dv)
            rrect(x, y, 0.40, 0.06, 0.02, T + 0.065, T + 0.075, seam, bevel=0.005)
    # Prise USB-C sur la tranche droite et câble court vers le téléphone.
    x, y = P(PB_W - 8, 200)
    rrect(x, y, 0.32, 0.5, 0.08, 0.08, T - 0.08, blackm, bevel=0.02)
    x, y = P(PB_W + 16, 214)
    rrect(x, y, 0.26, 0.22, 0.1, 0.12, 0.3, steel, bevel=0.02)
    shadow_catcher(14, 10)
    lights(key=(-3.5, 4.5, 9), power=800, size=6, fill=150)
    camera(0, 0, (PB_W + 2 * ACC_PAD) / 100, PB_W + 2 * ACC_PAD, PB_H + 2 * ACC_PAD)
    bpy.context.scene.render.filepath = path
    bpy.ops.render.render(write_still=True)


def render_irbeacon(path):
    """Balise IR vissée sur la coque : boîtier noir, lentille bombée sur l'émetteur, marquage IR."""
    reset()
    P = lambda u, v: px(u, v, IR_W / 2, IR_W / 2)  # noqa: E731
    body = mat_paint("ir", (0.05, 0.05, 0.048), rough=0.6, grain=1.2)
    blackm = mat_simple("black", (0.015, 0.015, 0.015), 0.3)
    lens = mat_simple("lens", (0.25, 0.08, 0.1), 0.05, coat=1.0)
    ink = mat_simple("ink", (0.8, 0.8, 0.76), 0.6)
    steel = mat_simple("steel", (0.7, 0.7, 0.68), 0.3, 1.0)
    x, y = P(0, 0)
    rrect(x, y, IR_W / 100, IR_W / 100, 0.22, 0.0, 0.3, body, bevel=0.06)
    cx, cy = P(IR_W / 2, IR_W / 2 - 6)
    cyl(cx, cy, 0.3, 0.28, 0.34, blackm, bevel=0.02)
    dome(cx, cy, 0.26, 0.33, 0.16, lens)
    tx, ty = P(IR_W / 2, IR_W - 10)
    text("IR", tx, ty, 0.301, 0.14, ink, align="CENTER")
    for u, v in ((14, 14), (IR_W - 14, 14)):
        sx, sy = P(u, v)
        phillips(sx, sy, 0.05, 0.3, steel, blackm)
    shadow_catcher(6, 6)
    lights(key=(-3.5, 4.5, 9), power=500, size=5, fill=100)
    camera(0, 0, (IR_W + 2 * 30) / 100, (IR_W + 60) * 2, (IR_W + 60) * 2)
    bpy.context.scene.render.filepath = path
    bpy.ops.render.render(write_still=True)


if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    what = os.environ.get("RENDER_ONLY", "all")
    if what in ("all", "module"):
        for t in MOD_TINTS:
            render_module(t, os.path.join(OUT, f"dl7_{t}.png"))
    if what in ("all", "back"):
        render_back(os.path.join(OUT, "back_panel.png"))
    if what in ("all", "acc"):
        render_powerbank(os.path.join(OUT, "powerbank.png"))
        render_irbeacon(os.path.join(OUT, "irbeacon.png"))
    print("ok")
