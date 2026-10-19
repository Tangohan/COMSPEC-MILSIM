"""
Previews photo-réelles des items SSE du lot 1 (Cycles, bpy 5.2). Maquettes de preview, pas encore les modèles du jeu :
formes, proportions, matières et ambiance « table d'exploitation » (tapis gris, réglet cm, chevalet numéroté).

    python3 gen_textures.py && python3 render_items.py [nom ...]
    RENDER_SAMPLES=n (défaut 96)  ->  ../render/NN-nom.jpg
"""
import math
import os
import sys

import bpy  # noqa: I001
import bmesh
from mathutils import Vector

HERE = os.path.dirname(os.path.abspath(__file__))
TEX = os.path.join(HERE, "tex")
OUT = os.path.join(HERE, "..", "render")
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "96"))
_mats = {}


# ------------------------------------------------------------------ utilitaires
def reset():
    bpy.ops.wm.read_factory_settings(use_empty=True)
    _mats.clear()
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    sc.render.resolution_x, sc.render.resolution_y = 1600, 1100
    sc.render.image_settings.file_format = "JPEG"
    sc.render.image_settings.quality = 90
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.look = "AgX - Medium High Contrast"
    sc.view_settings.exposure = -1.1
    w = bpy.data.worlds.new("w")
    sc.world = w
    w.use_nodes = True
    w.node_tree.nodes["Background"].inputs[0].default_value = (0.06, 0.065, 0.07, 1)
    w.node_tree.nodes["Background"].inputs[1].default_value = 0.6


def mat(name, color=(0.5, 0.5, 0.5), rough=0.5, metal=0.0, tex=None, emit=0.0, transmission=0.0, coat=0.0,
        bump=0.0, alpha=1.0):
    key = (name,)
    if key in _mats:
        return _mats[key]
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    p = nt.nodes["Principled BSDF"]
    p.inputs["Base Color"].default_value = (*color, 1)
    p.inputs["Roughness"].default_value = rough
    p.inputs["Metallic"].default_value = metal
    p.inputs["Transmission Weight"].default_value = transmission
    p.inputs["Coat Weight"].default_value = coat
    p.inputs["Alpha"].default_value = alpha
    if transmission:
        p.inputs["IOR"].default_value = 1.45
    if tex:
        t = nt.nodes.new("ShaderNodeTexImage")
        t.image = bpy.data.images.load(os.path.join(TEX, tex))
        t.interpolation = "Cubic"
        nt.links.new(t.outputs[0], p.inputs["Base Color"])
        if emit:
            p.inputs["Specular IOR Level"].default_value = 0.12
            nt.links.remove(p.inputs["Base Color"].links[0])
            p.inputs["Base Color"].default_value = (0.0, 0.0, 0.0, 1)
            nt.links.new(t.outputs[0], p.inputs["Emission Color"])
            p.inputs["Emission Strength"].default_value = emit
    elif emit:
        p.inputs["Emission Color"].default_value = (*color, 1)
        p.inputs["Emission Strength"].default_value = emit
    if bump:
        n = nt.nodes.new("ShaderNodeTexNoise")
        n.inputs["Scale"].default_value = 900
        n.inputs["Detail"].default_value = 8
        b = nt.nodes.new("ShaderNodeBump")
        b.inputs["Strength"].default_value = bump
        b.inputs["Distance"].default_value = 0.0004
        nt.links.new(n.outputs[0], b.inputs["Height"])
        nt.links.new(b.outputs[0], p.inputs["Normal"])
        # micro-variation de rugosité (traces de doigts, usure)
        n2 = nt.nodes.new("ShaderNodeTexNoise")
        n2.inputs["Scale"].default_value = 60
        mr = nt.nodes.new("ShaderNodeMapRange")
        mr.inputs["To Min"].default_value = max(0.0, rough - 0.12)
        mr.inputs["To Max"].default_value = min(1.0, rough + 0.12)
        nt.links.new(n2.outputs[0], mr.inputs[0])
        nt.links.new(mr.outputs[0], p.inputs["Roughness"])
    _mats[key] = m
    return m


def box(name, size, loc=(0, 0, 0), m=None, bevel=0.0, rot=(0, 0, 0), segs=4):
    sx, sy, sz = size
    bpy.ops.mesh.primitive_cube_add(size=1, location=(loc[0], loc[1], loc[2] + sz / 2))
    o = bpy.context.object
    o.name = name
    o.scale = (sx, sy, sz)
    bpy.ops.object.transform_apply(scale=True)
    o.rotation_euler = rot
    if bevel:
        md = o.modifiers.new("bv", "BEVEL")
        md.width = bevel
        md.segments = segs
        md.limit_method = "ANGLE"
        bpy.ops.object.shade_smooth()
        o.data.shade_smooth()
        md2 = o.modifiers.new("wn", "WEIGHTED_NORMAL")
        md2.keep_sharp = True
    if m:
        o.data.materials.append(m)
    return o


def tbox(name, size, loc, m, bevel=0.0, rot=(0, 0, 0), edge=(0.85, 0.85, 0.83)):
    """Boîte fine texturée sur le dessus seulement (cartes, photos) : tranche unie + plan texturé."""
    b = box(name, size, (0, 0, 0), mat(name + "_edge", edge, 0.5), bevel)
    t = plane(name + "_top", size[0] - bevel * 0.6, size[1] - bevel * 0.6, (0, 0, size[2] + 0.00003), m)
    g = set_parent([b, t], loc, rot[2])
    return g


def cyl(name, r, depth, loc, m=None, rot=(0, 0, 0), verts=48, bevel=0.0):
    bpy.ops.mesh.primitive_cylinder_add(radius=r, depth=depth, location=loc, rotation=rot, vertices=verts)
    o = bpy.context.object
    o.name = name
    bpy.ops.object.shade_smooth()
    if bevel:
        md = o.modifiers.new("bv", "BEVEL")
        md.width = bevel
        md.segments = 3
        md.limit_method = "ANGLE"
    if m:
        o.data.materials.append(m)
    return o


def plane(name, w, h, loc, m, rot=(0, 0, 0)):
    bpy.ops.mesh.primitive_plane_add(size=1, location=loc, rotation=rot)
    o = bpy.context.object
    o.name = name
    o.scale = (w, h, 1)
    bpy.ops.object.transform_apply(scale=True)
    o.data.materials.append(m)
    return o


def sheet(name, w, h, loc, m, curl=0.0, rot_z=0.0, sub=24, wave=0.0):
    """Feuille souple : grille légèrement bombée / ondulée."""
    bpy.ops.mesh.primitive_grid_add(x_subdivisions=sub, y_subdivisions=sub, size=1, location=loc)
    o = bpy.context.object
    o.name = name
    o.scale = (w, h, 1)
    bpy.ops.object.transform_apply(scale=True)
    for v in o.data.vertices:
        u = v.co.x / w + 0.5
        t = v.co.y / h + 0.5
        v.co.z += curl * (u - 0.5) ** 2 * 4 + wave * math.sin(t * 6.3 + u * 2) * 0.5
    o.rotation_euler = (0, 0, rot_z)
    bpy.ops.object.shade_smooth()
    o.data.materials.append(m)
    return o


def curve_cable(name, pts, r, m):
    cu = bpy.data.curves.new(name, "CURVE")
    cu.dimensions = "3D"
    cu.bevel_depth = r
    cu.bevel_resolution = 4
    sp = cu.splines.new("BEZIER")
    sp.bezier_points.add(len(pts) - 1)
    for bp, p in zip(sp.bezier_points, pts):
        bp.co = p
        bp.handle_left_type = bp.handle_right_type = "AUTO"
    o = bpy.data.objects.new(name, cu)
    bpy.context.collection.objects.link(o)
    o.data.materials.append(m)
    return o


def set_parent(children, parent_loc=(0, 0, 0), rot_z=0.0):
    e = bpy.data.objects.new("grp", None)
    bpy.context.collection.objects.link(e)
    e.location = parent_loc
    for c in children:
        c.parent = e
    e.rotation_euler = (0, 0, rot_z)
    return e


def stage(span, number, look=(0, 0, 0.01), elev=38, azim=-28):
    """Tapis, réglet, chevalet, éclairage studio doux et caméra cadrée sur `span` mètres."""
    mt = mat("mat", tex="mat.png", rough=0.85, bump=0.15)
    p = plane("table", 3, 3, (0, 0, 0), mt)
    # répétition de la texture du tapis
    nt = mt.node_tree
    tc = nt.nodes.new("ShaderNodeTexCoord")
    mp = nt.nodes.new("ShaderNodeMapping")
    mp.inputs["Scale"].default_value = (8, 8, 1)
    nt.links.new(tc.outputs["UV"], mp.inputs[0])
    for n in nt.nodes:
        if n.type == "TEX_IMAGE":
            nt.links.new(mp.outputs[0], n.inputs[0])
    s = span
    fz = max(1.0, s / 0.5)
    rl = box("ruler", (0.2 * fz, 0.02 * fz, 0.0012), (s * 0.12, -s * 0.44, 0), mat("ruler", tex="ruler.png", rough=0.6))
    plane("ruler_face", 0.2 * fz, 0.02 * fz, (s * 0.12, -s * 0.44, 0.00125), mat("ruler_f", tex="ruler.png", rough=0.55))
    rl.hide_render = False
    # chevalet jaune en tente, chiffre face caméra
    pm = mat(f"plac{number}", tex=f"placard_{number:02d}.png", rough=0.45)
    back = mat("placback", (0.85, 0.62, 0.05), 0.5)
    pw = 0.045 * fz
    tilt = math.radians(68)
    cx, cy = -s * 0.34, -s * 0.27
    d, h = math.cos(tilt) * pw, math.sin(tilt) * pw
    for name, m_, y0, flip in (("placf", pm, cy - d, False), ("placb", back, cy + d, True)):
        me = bpy.data.meshes.new(name)
        verts = [(cx - pw / 2, y0, 0), (cx + pw / 2, y0, 0), (cx + pw / 2, cy, h), (cx - pw / 2, cy, h)]
        faces = [(0, 1, 2, 3)] if not flip else [(3, 2, 1, 0)]
        me.from_pydata(verts, [], faces)
        uv = me.uv_layers.new()
        for li, vi in enumerate(me.polygons[0].vertices):
            uv.data[li].uv = [(0, 0), (1, 0), (1, 1), (0, 1)][vi]
        o = bpy.data.objects.new(name, me)
        bpy.context.collection.objects.link(o)
        me.materials.append(m_)
        sol = o.modifiers.new("s", "SOLIDIFY")
        sol.thickness = 0.0012
    # lumières
    def area(loc, energy, size, color=(1, 1, 1)):
        bpy.ops.object.light_add(type="AREA", location=loc)
        L = bpy.context.object
        L.data.energy = energy
        L.data.size = size
        L.data.color = color
        d = Vector(look) - L.location
        L.rotation_euler = d.to_track_quat("-Z", "Y").to_euler()
    k = max(0.6, s * 2.2)
    area((-0.8 * k, -0.5 * k, 1.3 * k), 55 * k * k, 1.2 * k, (1, 0.97, 0.92))
    area((1.0 * k, -0.2 * k, 0.6 * k), 12 * k * k, 1.5 * k, (0.85, 0.92, 1))
    area((0.2 * k, 1.1 * k, 0.9 * k), 30 * k * k, 0.8 * k)
    # caméra
    cam = bpy.data.cameras.new("cam")
    cam.lens = 70
    cam.dof.use_dof = True
    cam.dof.aperture_fstop = 11 if s > 0.25 else 8
    co = bpy.data.objects.new("cam", cam)
    bpy.context.collection.objects.link(co)
    dist = s * 2.25
    el, az = math.radians(elev), math.radians(azim)
    co.location = Vector(look) + Vector((math.sin(az) * math.cos(el), -math.cos(az) * math.cos(el), math.sin(el))) * dist
    co.rotation_euler = (Vector(look) - co.location).to_track_quat("-Z", "Y").to_euler()
    focus = bpy.data.objects.new("focus", None)
    bpy.context.collection.objects.link(focus)
    focus.location = look
    cam.dof.focus_object = focus
    bpy.context.scene.camera = co


def render(name):
    os.makedirs(OUT, exist_ok=True)
    bpy.context.scene.render.filepath = os.path.join(OUT, name + ".jpg")
    bpy.ops.render.render(write_still=True)
    print("rendu", name)


# ------------------------------------------------------------------ matières communes
def M():
    return {
        "glass": mat("glass", (0.01, 0.01, 0.012), 0.05, coat=1.0),
        "blackplast": mat("blackplast", (0.025, 0.025, 0.027), 0.45, bump=0.3),
        "rubber": mat("rubber", (0.02, 0.02, 0.021), 0.8, bump=0.6),
        "alu": mat("alu", (0.75, 0.75, 0.77), 0.28, 1.0, bump=0.1),
        "darkalu": mat("darkalu", (0.12, 0.12, 0.13), 0.35, 1.0, bump=0.1),
        "greyplast": mat("greyplast", (0.16, 0.165, 0.17), 0.5, bump=0.4),
        "chrome": mat("chrome", (0.9, 0.9, 0.9), 0.12, 1.0),
        "gold": mat("gold", (0.9, 0.7, 0.35), 0.25, 1.0),
        "paper": mat("paper", (0.82, 0.8, 0.74), 0.85),
    }


# ------------------------------------------------------------------ items
def smartphone():
    m = M()
    b = box("body", (0.073, 0.152, 0.0082), (0, 0, 0), m["darkalu"], 0.0045, segs=6)
    g = box("glass", (0.0715, 0.1505, 0.0006), (0, 0, 0.0082), m["glass"], 0.004, segs=6)
    s = plane("screen", 0.0675, 0.1425, (0, 0, 0.00885), mat("scr", tex="smartphone_screen.png", rough=0.1, emit=1.0))
    cam = cyl("lens", 0.0012, 0.001, (0, 0.071, 0.0088), m["glass"])
    # fissure du verre : fine bande claire
    crack = mat("crack", (0.8, 0.85, 0.9), 0.2, emit=0.05)
    pts = [(-0.03, -0.05), (-0.01, -0.03), (0.005, -0.035), (0.02, -0.01), (0.034, 0.0)]
    for i in range(len(pts) - 1):
        a, c = Vector((*pts[i], 0.00892)), Vector((*pts[i + 1], 0.00892))
        mid = (a + c) / 2
        o = plane("crk", (c - a).length, 0.0003, mid, crack)
        o.rotation_euler = (0, 0, math.atan2(c.y - a.y, c.x - a.x))
    # second téléphone retourné (dos et module photo) en arrière-plan
    b2 = box("body2", (0.073, 0.152, 0.0082), (0, 0, 0), mat("back", (0.05, 0.09, 0.12), 0.25, coat=1, bump=0.1), 0.0045, segs=6)
    mod = box("cammod", (0.03, 0.03, 0.0016), (-0.02, 0.055, 0.0082), m["glass"], 0.006, segs=6)
    l1 = cyl("l1", 0.006, 0.0012, (-0.027, 0.062, 0.0101), m["chrome"])
    l2 = cyl("l2", 0.006, 0.0012, (-0.027, 0.048, 0.0101), m["chrome"])
    l1b = cyl("l1b", 0.0045, 0.0014, (-0.027, 0.062, 0.0103), m["glass"])
    l2b = cyl("l2b", 0.0045, 0.0014, (-0.027, 0.048, 0.0103), m["glass"])
    set_parent([b, g, s, cam] + [o for o in bpy.data.objects if o.name.startswith("crk")], (-0.03, -0.01, 0), math.radians(-12))
    set_parent([b2, mod, l1, l2, l1b, l2b], (0.065, 0.03, 0), math.radians(16))
    stage(0.26, 1)


def basic_phone():
    m = M()
    parts = [box("body", (0.05, 0.113, 0.0145), (0, 0, 0), m["greyplast"], 0.006, segs=6),
             box("front", (0.046, 0.108, 0.001), (0, 0, 0.0145), mat("front", (0.06, 0.065, 0.07), 0.35), 0.004),
             plane("lcd", 0.033, 0.026, (0, 0.03, 0.0157), mat("lcd", tex="basic_screen.png", rough=0.2, emit=0.6)),
             plane("keys", 0.04, 0.042, (0, -0.026, 0.0157), mat("keys", tex="basic_keys.png", rough=0.4)),
             box("nav", (0.026, 0.012, 0.0014), (0, 0.006, 0.0153), mat("navk", (0.05, 0.05, 0.055), 0.3, 0.5), 0.004),
             box("ear", (0.012, 0.0015, 0.0003), (0, 0.048, 0.0156), m["blackplast"])]
    set_parent(parts, (-0.015, 0.0, 0), math.radians(-18))
    # batterie et coque arrière démontées à côté (pratique SSE : retrait batterie / SIM)
    bat = box("bat", (0.034, 0.048, 0.005), (0.055, 0.025, 0), mat("bat", (0.6, 0.62, 0.65), 0.35, 0.6), 0.001)
    lab = plane("batl", 0.03, 0.04, (0.055, 0.025, 0.0051), mat("batl", tex="hdd_label.png", rough=0.4))
    tbox("sim", (0.025, 0.015, 0.0008), (0.075, -0.035, 0), mat("sim", tex="simcard.png", rough=0.4), 0.001, rot=(0, 0, math.radians(25)))
    stage(0.2, 2, look=(0.01, 0, 0.006))


def keycap(name, idx, w, h, loc, cap, atlas, height=0.0018, bevel=0.0012, rot_z=0.0):
    """Touche physique arrondie + étiquette tirée de l'atlas 4 x 4 (gen_textures.KEYS)."""
    k = box(name, (w, h, height), loc, cap, bevel, segs=4)
    t = plane(name + "_l", w * 0.72, h * 0.72, (loc[0], loc[1], loc[2] + height + 0.00002), atlas)
    c, r = idx % 4, idx // 4
    for loop in t.data.loops:
        uv = t.data.uv_layers.active.data[loop.index].uv
        uv.x = (c + uv.x) / 4
        uv.y = (3 - r + uv.y) / 4
    return [k, t]


def satphone():
    """Téléphone satellite type GMR/Iridium : coque gris anthracite caoutchoutée, gros stub d'antenne articulé."""
    m = M()
    shell = mat("satshell", (0.055, 0.058, 0.06), 0.55, bump=0.6)
    grip = mat("satgrip", (0.018, 0.018, 0.019), 0.85, bump=1.0)
    face = mat("satface", (0.03, 0.031, 0.033), 0.3, coat=0.4)
    cap = mat("satcap", (0.07, 0.072, 0.075), 0.45)
    atlas = mat("satatlas", tex="keys_sat.png", rough=0.45)
    L, W, D = 0.143, 0.056, 0.028
    parts = [box("body", (W, L, D), (0, 0, 0), shell, 0.009, segs=6),
             box("gripL", (0.006, L * 0.62, D * 0.8), (-W / 2 + 0.001, -0.02, 0.002), grip, 0.003),
             box("gripR", (0.006, L * 0.62, D * 0.8), (W / 2 - 0.001, -0.02, 0.002), grip, 0.003),
             box("face", (W - 0.008, L - 0.012, 0.0012), (0, 0, D - 0.0006), face, 0.006, segs=5),
             box("bezel", (0.044, 0.038, 0.0008), (0, 0.035, D + 0.0005), mat("satbz", (0.005, 0.005, 0.006), 0.08, coat=1), 0.003),
             plane("lcd", 0.037, 0.028, (0, 0.034, D + 0.00135), mat("slcd", tex="satphone_lcd.png", rough=0.1, emit=1.0)),
             plane("brand", 0.03, 0.0056, (0, 0.0585, D + 0.00135), mat("satbr", tex="sat_brand.png", rough=0.4)),
             box("ear", (0.014, 0.0022, 0.0004), (0, 0.064, D + 0.0006), m["blackplast"], 0.0008)]
    # touches de navigation : 2 softkeys, décrocher (vert), raccrocher (rouge), pavé directionnel rond
    y0 = 0.007
    parts += [box("sk1", (0.011, 0.006, 0.0016), (-0.016, y0, D + 0.0006), cap, 0.0015),
              box("sk2", (0.011, 0.006, 0.0016), (0.016, y0, D + 0.0006), cap, 0.0015),
              box("call", (0.011, 0.006, 0.0016), (-0.016, y0 - 0.009, D + 0.0006), mat("callg", (0.05, 0.35, 0.1), 0.4), 0.0015),
              box("end", (0.011, 0.006, 0.0016), (0.016, y0 - 0.009, D + 0.0006), mat("endr", (0.5, 0.05, 0.04), 0.4), 0.0015),
              cyl("dpad", 0.0075, 0.0016, (0, y0 - 0.0045, D + 0.0014), cap, bevel=0.0007),
              cyl("ok", 0.0032, 0.0018, (0, y0 - 0.0045, D + 0.0022), m["chrome"])]
    for i in range(12):
        c, r = i % 3, i // 3
        parts += keycap(f"k{i}", i, 0.0125, 0.0072, (-0.0145 + c * 0.0145, -0.012 - r * 0.0098, D + 0.0006), cap, atlas)
    parts += [box("mic", (0.004, 0.0012, 0.0004), (0, -0.066, D + 0.0006), m["blackplast"]),
              box("side1", (0.0016, 0.012, 0.004), (-W / 2 - 0.0006, 0.03, D * 0.5), m["rubber"], 0.0007),
              box("side2", (0.0016, 0.007, 0.004), (-W / 2 - 0.0006, 0.012, D * 0.5), m["rubber"], 0.0007)]
    # antenne : charnière en haut à droite, stub épais déployé en avant et légèrement relevé
    hinge = cyl("hinge", 0.0075, 0.012, (0.015, L / 2 - 0.004, D * 0.55), m["rubber"], rot=(0, math.radians(90), 0))
    ant = box("ant", (0.016, 0.105, 0.012), (0, 0.0525, -0.006), grip, 0.0058, segs=6)
    tip = cyl("anttip", 0.0058, 0.002, (0, 0.105, 0), grip, rot=(math.radians(90), 0, 0))
    ag = set_parent([ant, tip], (0.015, L / 2 - 0.004, D * 0.55))
    ag.rotation_euler = (math.radians(14), 0, math.radians(-8))
    parts += [hinge, ag]
    set_parent(parts, (-0.005, -0.045, 0), math.radians(-24))
    stage(0.3, 3, look=(0.0, 0.0, 0.015), elev=42)


def radio():
    """Radio portative bibande type talkie chinois : face avant à grille, LCD, clavier 4 x 4, deux boutons, antenne souple."""
    m = M()
    shell = mat("radio", (0.028, 0.029, 0.03), 0.6, bump=0.7)
    cap = mat("rcap", (0.02, 0.02, 0.021), 0.55)
    atlas = mat("ratlas", tex="keys_radio.png", rough=0.5)
    W, L, D = 0.058, 0.11, 0.024
    parts = [box("body", (W, L, D), (0, 0, 0.012), shell, 0.006, segs=6),
             box("battery", (W - 0.004, L * 0.78, 0.013), (0, -0.012, 0.0), mat("rbat", (0.02, 0.02, 0.021), 0.4), 0.004),
             box("clip", (0.032, 0.065, 0.0025), (0, 0.005, -0.0024), m["blackplast"], 0.001),
             box("grille", (0.048, 0.034, 0.0006), (0, 0.032, 0.012 + D), mat("grbg", (0.012, 0.012, 0.012), 0.8), 0.002)]
    for i in range(9):
        parts.append(box(f"slot{i}", (0.044, 0.0014, 0.0008), (0, 0.0175 + i * 0.0034, 0.012 + D + 0.0002), shell, 0.0004))
    parts += [box("lcdbz", (0.04, 0.02, 0.0006), (0, 0.0, 0.012 + D), mat("rbz", (0.005, 0.005, 0.006), 0.1, coat=1), 0.0015),
              plane("lcd", 0.034, 0.0155, (0, 0.0, 0.012 + D + 0.00065), mat("rlcd", tex="radio_lcd.png", rough=0.2, emit=0.7)),
              plane("brand", 0.04, 0.0075, (0, 0.0145, 0.012 + D + 0.00002), mat("rbr", tex="radio_brand.png", rough=0.5))]
    order = [12, 0, 1, 2, 13, 3, 4, 5, 14, 6, 7, 8, 15, 9, 10, 11]
    for i, idx in enumerate(order):
        c, r = i % 4, i // 4
        parts += keycap(f"rk{i}", idx, 0.0105, 0.0062, (-0.0174 + c * 0.0116, -0.016 - r * 0.0085, 0.012 + D), cap, atlas,
                        height=0.0014)
    top = L / 2
    parts += [cyl("knobA", 0.0065, 0.014, (-0.015, top + 0.007, 0.024), mat("knob", (0.02, 0.02, 0.02), 0.7, bump=1.2),
                  rot=(math.radians(90), 0, 0)),
              cyl("knobB", 0.0065, 0.011, (0.002, top + 0.0055, 0.024), mat("knob", (0.02, 0.02, 0.02), 0.7, bump=1.2),
                  rot=(math.radians(90), 0, 0)),
              cyl("led", 0.0022, 0.0015, (0.012, top + 0.0005, 0.03), mat("redled", (0.8, 0.05, 0.03), 0.3, emit=2),
                  rot=(math.radians(90), 0, 0)),
              cyl("sma", 0.0045, 0.008, (0.018, top + 0.004, 0.024), m["chrome"], rot=(math.radians(90), 0, 0))]
    # antenne souple : tronc conique caoutchouc
    bpy.ops.mesh.primitive_cone_add(radius1=0.0068, radius2=0.004, depth=0.15, vertices=32,
                                    location=(0.018, top + 0.008 + 0.075, 0.024), rotation=(math.radians(-90), 0, 0))
    ant = bpy.context.object
    bpy.ops.object.shade_smooth()
    ant.data.materials.append(m["rubber"])
    parts += [ant, cyl("anttip", 0.004, 0.003, (0.018, top + 0.008 + 0.151, 0.024), m["rubber"], rot=(math.radians(90), 0, 0)),
              box("ptt", (0.003, 0.026, 0.009), (-W / 2 - 0.0012, 0.012, 0.018), m["rubber"], 0.0013),
              box("mon", (0.003, 0.008, 0.006), (-W / 2 - 0.0012, -0.008, 0.02), m["rubber"], 0.0012),
              box("call", (0.003, 0.008, 0.006), (-W / 2 - 0.0012, -0.019, 0.02), m["rubber"], 0.0012),
              box("jack", (0.0015, 0.016, 0.01), (W / 2 + 0.0004, 0.014, 0.018), m["rubber"], 0.001)]
    set_parent(parts, (0.0, -0.04, 0), math.radians(22))
    stage(0.32, 4, look=(0.0, 0.0, 0.015), elev=40)


def removable():
    m = M()
    usb = [box("usb", (0.02, 0.05, 0.0085), (0, 0, 0), m["blackplast"], 0.003, segs=5),
           box("usbm", (0.012, 0.013, 0.0045), (0, 0.031, 0.002), m["alu"], 0.0006),
           plane("usbl", 0.026, 0.0065, (0, -0.006, 0.0086), mat("usbl", tex="usb_label.png", rough=0.4), rot=(0, 0, math.radians(90))),
           cyl("ring", 0.0035, 0.003, (0, -0.028, 0.004), m["chrome"])]
    set_parent(usb, (-0.04, 0.0, 0), math.radians(-25))
    tbox("simc", (0.0856, 0.054, 0.0008), (0.035, 0.035, 0), mat("simc", tex="simcard.png", rough=0.4), 0.002,
         rot=(0, 0, math.radians(12)), edge=(0.8, 0.3, 0.15))
    tbox("sd", (0.011, 0.015, 0.0009), (0.03, -0.035, 0), mat("sd", tex="microsd.png", rough=0.35), 0.0006,
         rot=(0, 0, math.radians(-20)), edge=(0.03, 0.03, 0.03))
    adap = box("sda", (0.024, 0.032, 0.0021), (0.065, -0.03, 0), mat("sda", (0.04, 0.04, 0.045), 0.4), 0.0015)
    adap.rotation_euler = (0, 0, math.radians(8))
    stage(0.2, 5, look=(0.01, 0, 0.003))


def hdd():
    m = M()
    parts = [box("hdd", (0.085, 0.125, 0.021), (0, 0, 0), m["rubber"], 0.008, segs=6),
             box("top", (0.07, 0.105, 0.001), (0, 0, 0.0205), mat("hddtop", (0.03, 0.03, 0.032), 0.3), 0.004),
             plane("lab", 0.05, 0.05, (0, 0.0, 0.0216), mat("hddl", tex="hdd_label.png", rough=0.35)),
             box("port", (0.012, 0.004, 0.006), (0.02, 0.0625, 0.007), mat("port", (0.1, 0.25, 0.7), 0.4)),
             box("led", (0.004, 0.002, 0.0015), (-0.02, 0.0625, 0.012), mat("led", (0.2, 0.5, 1), 0.3, emit=4))]
    set_parent(parts, (-0.02, -0.01, 0), math.radians(-15))
    cab = mat("cable", (0.02, 0.02, 0.022), 0.45)
    curve_cable("c", [(0.0, 0.055, 0.007), (0.02, 0.1, 0.005), (0.08, 0.11, 0.004), (0.11, 0.04, 0.004), (0.08, -0.03, 0.004),
                      (0.11, -0.08, 0.005)], 0.0022, cab)
    box("plug", (0.012, 0.022, 0.007), (0.11, -0.095, 0), m["blackplast"], 0.002)
    box("plugm", (0.012, 0.012, 0.0045), (0.11, -0.112, 0.001), m["alu"], 0.0005)
    stage(0.28, 6, look=(0.02, 0, 0.01))


def laptop():
    m = M()
    shell = mat("lshell", (0.17, 0.17, 0.18), 0.35, 0.8, bump=0.2)
    base = box("base", (0.33, 0.225, 0.017), (0, 0, 0), shell, 0.006, segs=5)
    deck = box("deck", (0.32, 0.215, 0.001), (0, 0, 0.0165), mat("deck", (0.12, 0.12, 0.125), 0.4, 0.6), 0.004)
    kb = []
    keym = mat("key", (0.03, 0.03, 0.032), 0.55)
    for r in range(5):
        n = 14
        for c in range(n):
            w = 0.0175
            x = -0.135 + c * 0.0205 + (r % 2) * 0.004
            if r == 4 and 4 <= c <= 9:
                if c == 4:
                    kb.append(box("space", (0.118, 0.016, 0.002), (-0.135 + 4 * 0.0205 + 0.05, 0.035 - r * 0.0205 + 0.008, 0.017), keym, 0.002))
                continue
            kb.append(box("k", (w, 0.016, 0.002), (x, 0.06 - r * 0.0205, 0.017), keym, 0.002))
    pad = box("pad", (0.1, 0.06, 0.0004), (0, -0.07, 0.0175), mat("pad", (0.1, 0.1, 0.105), 0.25, 0.6), 0.004)
    lid_parts = [box("lid", (0.33, 0.215, 0.007), (0, 0.1075, -0.0035), shell, 0.005, segs=5),
                 box("bezel", (0.322, 0.207, 0.0008), (0, 0.1075, -0.0045), mat("bezel", (0.01, 0.01, 0.01), 0.2), 0.004),
                 plane("scr", 0.3, 0.1875, (0, 0.11, -0.00455), mat("lscr", tex="laptop_screen.png", rough=0.12, emit=1.0), rot=(math.pi, 0, math.pi))]
    hinge = set_parent(lid_parts, (0, 0, 0))
    hinge.location = (0, 0.1125, 0.017)
    hinge.rotation_euler = (math.radians(-110 + 180), 0, 0)
    # retourne : l'écran fait face à l'utilisateur
    hinge.rotation_euler = (math.radians(-70), 0, 0)
    hinge.scale = (1, -1, 1)
    grp = set_parent([base, deck, pad] + kb, (0, 0, 0))
    hinge.parent = grp
    grp.rotation_euler = (0, 0, math.radians(14))
    curve_cable("pwr", [(0.16, 0.0, 0.008), (0.22, 0.02, 0.004), (0.26, -0.08, 0.004), (0.2, -0.16, 0.004)], 0.0025,
                mat("cable", (0.02, 0.02, 0.022), 0.45))
    stage(0.55, 7, look=(0, 0.03, 0.07), elev=28, azim=-22)


def notebook():
    m = M()
    cover = mat("ncover", tex="notebook_cover.png", rough=0.7, bump=0.5)
    box("cover", (0.3, 0.215, 0.003), (0, 0, 0), cover, 0.003)
    box("block", (0.29, 0.205, 0.006), (0, 0, 0.002), m["paper"], 0.002)
    pg = mat("pages", tex="notebook_pages.png", rough=0.8)
    s = sheet("pages", 0.288, 0.204, (0, 0, 0.0085), pg, curl=0.012, sub=40)
    # creux central de la reliure
    for v in s.data.vertices:
        u = v.co.x / 0.288
        v.co.z += 0.010 * (abs(u) * 2) ** 0.5 - 0.004
    box("elastic", (0.006, 0.23, 0.0015), (0.135, 0, -0.0002), mat("elas", (0.02, 0.02, 0.02), 0.6))
    cyl("pen", 0.0045, 0.14, (0.05, -0.135, 0.0045), mat("penb", (0.02, 0.05, 0.25), 0.3, coat=1), rot=(0, math.radians(90), math.radians(8)))
    cyl("penc", 0.0048, 0.045, (0.135, -0.129, 0.0046), m["chrome"], rot=(0, math.radians(90), math.radians(8)))
    for o in bpy.data.objects:
        if o.type == "MESH" and o.name not in ("pen", "penc"):
            o.rotation_euler.z += math.radians(-6)
    stage(0.42, 8, look=(0, 0, 0.008), elev=50, azim=-15)


def passport():
    m = M()
    cov = mat("pcov", (0.05, 0.11, 0.07), 0.55, bump=0.6)
    parts = [box("pc", (0.128, 0.18, 0.003), (0, 0, 0), cov, 0.003),
             sheet("p1", 0.124, 0.088, (0, 0.045, 0.0042), mat("pblank", tex="passport_data.png", rough=0.8), curl=0.0015),
             sheet("p2", 0.124, 0.088, (0, -0.045, 0.0042), mat("pdata", tex="passport_data.png", rough=0.55, coat=0.3), curl=0.0015)]
    # page du haut : visa vierge (on recouvre avec du papier uni)
    parts[1].data.materials[0] = mat("pvisa", (0.78, 0.8, 0.74), 0.85)
    set_parent(parts, (-0.04, 0.0, 0), math.radians(-8))
    c = box("closed", (0.088, 0.125, 0.006), (0.0, 0.0, 0), cov, 0.003)
    plane("closedf", 0.084, 0.12, (0.0, 0.0, 0.00605), mat("pcovt", tex="passport_cover.png", rough=0.5, metal=0.0))
    g = set_parent([c, bpy.data.objects["closedf"]], (0.085, 0.045, 0), math.radians(18))
    tbox("id", (0.0856, 0.054, 0.0009), (0.075, -0.075, 0.0), mat("idc", tex="idcard.png", rough=0.25, coat=0.6), 0.003,
         rot=(0, 0, math.radians(-10)))
    stage(0.3, 9, look=(0.01, 0, 0.003), elev=55, azim=-10)


def documents():
    folder = mat("folder", tex="folder.png", rough=0.8, bump=0.4)
    box("f", (0.24, 0.32, 0.0025), (0, 0, 0), folder, 0.001)
    doc = mat("doc", tex="document.png", rough=0.8)
    s1 = sheet("d1", 0.21, 0.297, (0.03, -0.03, 0.004), doc, curl=0.002, rot_z=math.radians(-9), wave=0.0015)
    s0 = sheet("d0", 0.21, 0.297, (-0.01, 0.0, 0.0032), mat("doc2", (0.83, 0.82, 0.78), 0.85), rot_z=math.radians(4))
    # photo tirée
    ph = mat("photo", tex="laptop_screen.png", rough=0.2, coat=0.8)
    tbox("ph", (0.1, 0.07, 0.0004), (0.12, 0.12, 0.0055), ph, 0.001, rot=(0, 0, math.radians(22)), edge=(0.95, 0.95, 0.95))
    cyl("clip", 0.0012, 0.035, (0.07, 0.13, 0.006), mat("clipm", (0.7, 0.7, 0.72), 0.25, 1.0), rot=(0, math.radians(90), math.radians(22)))
    stage(0.48, 10, look=(0.02, 0.0, 0.004), elev=58, azim=-12)


def evidence_bag():
    m = M()
    plast = mat("bag", (0.92, 0.94, 0.95), 0.32, transmission=0.8, coat=0.4)
    b = sheet("bag", 0.23, 0.31, (0, 0, 0.012), plast, curl=0.006, wave=0.002, sub=40)
    sol = b.modifiers.new("s", "SOLIDIFY")
    sol.thickness = 0.0005
    lab = sheet("lab", 0.1, 0.122, (-0.05, -0.06, 0.0185), mat("elab", tex="evidence_label.png", rough=0.6), curl=0.002)
    tape = sheet("tape", 0.23, 0.018, (0, 0.14, 0.0185), mat("tape", tex="seal_tape.png", rough=0.4), sub=20)
    # contenu : clé USB et carte SIM
    usb = [box("usb", (0.02, 0.05, 0.0085), (0, 0, 0), m["blackplast"], 0.003, segs=5),
           box("usbm", (0.012, 0.013, 0.0045), (0, 0.031, 0.002), m["alu"], 0.0006)]
    set_parent(usb, (0.05, 0.03, 0.001), math.radians(30))
    stage(0.42, 11, look=(0, 0, 0.01), elev=60, azim=-10)


def wallet():
    leather = mat("leather", (0.16, 0.08, 0.04), 0.55, bump=1.0)
    box("w", (0.115, 0.092, 0.012), (0, 0, 0), leather, 0.006, segs=6)
    box("stitch", (0.107, 0.084, 0.0003), (0, 0, 0.0121), mat("stitchm", (0.5, 0.42, 0.3), 0.8))
    box("w2", (0.103, 0.08, 0.0004), (0, 0, 0.01215), leather)
    note = mat("note", tex="banknote.png", rough=0.8)
    for i in range(3):
        sheet(f"n{i}", 0.155, 0.072, (0.04 + i * 0.006, 0.05 + i * 0.004, 0.0016 + i * 0.0006), note,
              rot_z=math.radians(8 + i * 5), curl=0.004, wave=0.001)
    tbox("card", (0.0856, 0.054, 0.0008), (-0.06, -0.055, 0), mat("idc2", tex="idcard.png", rough=0.25, coat=0.6), 0.003,
         rot=(0, 0, math.radians(-20)))
    tbox("sim", (0.025, 0.015, 0.0008), (0.07, -0.05, 0), mat("sim", tex="simcard.png", rough=0.4), 0.001,
         rot=(0, 0, math.radians(35)))
    stage(0.3, 12, look=(0.01, 0, 0.006), elev=48, azim=-18)



# ------------------------------------------------------------------ caches : armes, munitions, explosifs
def steel():
    return mat("blued", (0.035, 0.036, 0.04), 0.38, 0.85, bump=0.4)


def stockwood():
    return mat("stockwood", tex="stock_wood.png", rough=0.45, coat=0.25, bump=0.4)


def magazine(name, loc, rot, m):
    """Chargeur courbe : 4 segments légèrement tournés."""
    segs = []
    x, z, a = 0.0, 0.0, 0.0
    for i in range(4):
        b = box(f"{name}{i}", (0.034, 0.024, 0.052), (0, 0, -0.026), m, 0.003)
        g = set_parent([b], (x, 0, z))
        g.rotation_euler = (0, a, 0)
        segs.append(g)
        x += math.sin(a) * -0.048 + 0.006
        z -= math.cos(a) * 0.046
        a -= math.radians(8)
    return set_parent(segs, loc, 0) if False else _place(segs, loc, rot)


def _place(objs, loc, rot):
    e = bpy.data.objects.new("grp", None)
    bpy.context.collection.objects.link(e)
    for o in objs:
        o.parent = e
    e.location = loc
    e.rotation_euler = rot
    return e


def rifle(loc, rot):
    """Fusil d'assaut générique type AK (silhouette de preview) : construit debout, axe +x, puis posé sur le flanc."""
    st, wd = steel(), stockwood()
    parts = [box("rcv", (0.24, 0.032, 0.052), (0.0, 0, -0.026), st, 0.004),
             box("cover", (0.22, 0.03, 0.014), (0.01, 0, 0.026), st, 0.006),
             box("hg_lo", (0.17, 0.042, 0.034), (0.205, 0, -0.03), wd, 0.009),
             box("hg_up", (0.15, 0.03, 0.022), (0.195, 0, 0.018), wd, 0.009),
             cyl("barrel", 0.0085, 0.24, (0.4, 0, 0.002), st, rot=(0, math.radians(90), 0)),
             cyl("gastube", 0.008, 0.13, (0.33, 0, 0.026), st, rot=(0, math.radians(90), 0)),
             box("gasblk", (0.03, 0.022, 0.04), (0.3, 0, -0.012), st, 0.003),
             box("fsight", (0.022, 0.018, 0.04), (0.47, 0, -0.012), st, 0.002),
             cyl("muzzle", 0.011, 0.045, (0.5, 0, 0.002), st, rot=(0, math.radians(90), 0)),
             box("rsight", (0.04, 0.026, 0.018), (0.11, 0, 0.026), st, 0.002),
             box("tguard", (0.07, 0.008, 0.004), (-0.02, 0, -0.06), st, 0.0015),
             box("grip", (0.032, 0.026, 0.085), (-0.07, 0, -0.105), wd, 0.008, rot=(0, math.radians(-18), 0))]
    # crosse : cube dont l'arrière est élargi et abaissé
    stock = box("stock", (0.25, 0.034, 0.05), (-0.245, 0, -0.035), wd, 0.008)
    for v in stock.data.vertices:
        if v.co.x < -0.3:
            v.co.z = v.co.z * 2.1 - 0.035
            v.co.y *= 1.25
    parts.append(stock)
    parts.append(box("butt", (0.012, 0.042, 0.11), (-0.372, 0, -0.112), st, 0.004))
    mg = magazine("mag", (0.06, 0, -0.04), (0, 0, 0), st)
    g = _place(parts + [mg], (0, 0, 0), (0, 0, 0))
    # posé sur le flanc gauche
    g2 = _place([g], loc, rot)
    g.rotation_euler = (math.radians(90), 0, 0)
    g.location = (0, 0, 0.021)
    return g2


def crate_open(L, W, H, t, side_m, wood_m, in_m, loc, rz):
    parts = [box("cb", (L, W, t), (0, 0, 0), in_m, 0.002),
             box("cf", (L, t, H), (0, -W / 2 + t / 2, 0), wood_m, 0.002),
             box("ck", (L, t, H), (0, W / 2 - t / 2, 0), wood_m, 0.002),
             box("cl", (t, W, H), (-L / 2 + t / 2, 0, 0), wood_m, 0.002),
             box("cr", (t, W, H), (L / 2 - t / 2, 0, 0), wood_m, 0.002),
             plane("cfs", L - 0.02, H - 0.02, (0, -W / 2 - 0.0004, H / 2), side_m, rot=(math.radians(90), 0, 0))]
    for x in (-L / 2 + 0.05, L / 2 - 0.05):
        parts.append(box("bat", (0.05, 0.012, H), (x, -W / 2 - 0.006, 0), wood_m, 0.002))
    for x in (-L / 2 + 0.008, L / 2 - 0.008):
        parts.append(box("rope", (0.012, 0.09, 0.012), (x + (0.012 if x > 0 else -0.012), 0, H * 0.6), mat("rope", (0.35, 0.28, 0.18), 0.9, bump=1.5), 0.005))
    return _place(parts, loc, (0, 0, rz))


def weapons_crate():
    side = mat("crateside", tex="crate_side.png", rough=0.8, bump=0.6)
    wd = mat("cratewood", tex="crate_wood.png", rough=0.8, bump=0.6)
    raw = mat("rawwood", tex="wood_raw.png", rough=0.85, bump=0.6)
    crate_open(0.98, 0.4, 0.24, 0.02, side, wd, raw, (0.0, 0.05, 0), math.radians(-4))
    # papier huilé au fond, deux fusils côte à côte, couvercle posé à plat derrière
    sheet("paper", 0.9, 0.34, (0.0, 0.05, 0.022), mat("oilpaper", (0.3, 0.22, 0.12), 0.55, coat=0.2), curl=0.03, wave=0.004)
    rifle((0.07, -0.05, 0.03), (0, 0, math.radians(-4)))
    rifle((0.05, 0.13, 0.03), (0, 0, math.radians(-4)))
    lid = box("lid", (0.98, 0.4, 0.02), (0, 0, 0), wd, 0.002)
    _place([lid], (0.08, 0.5, 0.0), (0, 0, math.radians(2)))
    rifle((0.05, -0.38, 0.0), (0, 0, math.radians(8)))
    stage(1.35, 13, look=(0.03, 0.0, 0.08), elev=48, azim=-20)


def ammo_lot():
    olive = mat("can", tex="ammocan.png", rough=0.55, metal=0.4, bump=0.5)
    oliveplain = mat("canp", (0.075, 0.085, 0.045), 0.55, 0.4, bump=0.5)
    for i, (x, y, rz) in enumerate([(-0.25, 0.1, 6), (0.07, 0.14, -4)]):
        b = box(f"can{i}", (0.28, 0.12, 0.18), (0, 0, 0), oliveplain, 0.006)
        f = plane(f"canf{i}", 0.26, 0.15, (0, -0.0605, 0.09), olive, rot=(math.radians(90), 0, 0))
        lid = box(f"canl{i}", (0.29, 0.13, 0.025), (0, 0, 0.18), oliveplain, 0.006)
        h = box(f"canh{i}", (0.12, 0.012, 0.008), (0, 0, 0.207), oliveplain, 0.003)
        latch = box(f"canlt{i}", (0.03, 0.016, 0.06), (-0.12, 0.0, 0.14), oliveplain, 0.003)
        _place([b, f, lid, h, latch], (x, y, 0), (0, 0, math.radians(rz)))
    zinc = mat("zinc", tex="zinc.png", rough=0.4, metal=0.8)
    zb = box("zinc", (0.38, 0.24, 0.085), (0, 0, 0), mat("zincp", (0.5, 0.51, 0.5), 0.35, 0.9, bump=0.3), 0.003)
    zt = plane("zinct", 0.37, 0.23, (0, 0, 0.0855), zinc)
    _place([zb, zt], (0.32, -0.12, 0), (0, 0, math.radians(-14)))
    wd = mat("cratewood2", tex="crate_wood.png", rough=0.8, bump=0.6)
    sd = mat("crateside2", tex="crate_ammo_side.png", rough=0.8, bump=0.6)
    c = box("acrate", (0.52, 0.3, 0.17), (0, 0, 0), wd, 0.003)
    cf = plane("acf", 0.5, 0.15, (0, -0.1505, 0.085), sd, rot=(math.radians(90), 0, 0))
    _place([c, cf], (-0.2, -0.22, 0), (0, 0, math.radians(3)))
    # chargeurs en vrac
    st = steel()
    magazine("m1", (0.2, -0.4, 0.013), (math.radians(90), 0, math.radians(30)), st)
    magazine("m2", (0.36, -0.36, 0.013), (math.radians(90), 0, math.radians(-50)), st)
    stage(1.1, 14, look=(0.02, -0.03, 0.08), elev=44, azim=-22)


def explosives():
    lab = mat("expl", tex="explosive.png", rough=0.5, coat=0.1)
    film = mat("film", (0.09, 0.1, 0.065), 0.3, coat=0.7, bump=0.8)
    for i, (x, y, z, rz) in enumerate([(-0.1, 0.05, 0, 8), (0.02, 0.07, 0, -5), (-0.04, 0.06, 0.052, 2)]):
        b = box(f"blk{i}", (0.21, 0.1, 0.05), (0, 0, 0), film, 0.008)
        t = plane(f"blkt{i}", 0.18, 0.085, (0, 0, 0.0503), lab)
        _place([b, t], (x, y, z), (0, 0, math.radians(rz)))
    tbox("detbox", (0.11, 0.11, 0.035), (0.17, 0.08, 0), mat("detb", tex="detbox.png", rough=0.8), 0.003,
         rot=(0, 0, math.radians(-12)), edge=(0.6, 0.5, 0.35))
    # bobine de fil
    red = mat("wire", (0.45, 0.04, 0.03), 0.4, bump=0.8)
    sp = [cyl("spool", 0.032, 0.05, (0, 0, 0.0), red, verts=64),
          cyl("fl1", 0.05, 0.004, (0, 0, -0.026), mat("spf", (0.02, 0.02, 0.02), 0.5), verts=64),
          cyl("fl2", 0.05, 0.004, (0, 0, 0.026), mat("spf", (0.02, 0.02, 0.02), 0.5), verts=64)]
    _place(sp, (0.2, -0.08, 0.05), (math.radians(90), 0, math.radians(20)))
    curve_cable("w", [(0.16, -0.06, 0.004), (0.08, -0.1, 0.003), (0.0, -0.06, 0.003), (-0.08, -0.11, 0.003), (-0.15, -0.07, 0.003)],
                0.0015, red)
    # ruban adhésif noir, télécommande générique, piles
    tape = cyl("tape", 0.05, 0.048, (-0.2, -0.05, 0.024), mat("tape", (0.015, 0.015, 0.015), 0.4), verts=64)
    hole = cyl("tapeh", 0.037, 0.05, (-0.2, -0.05, 0.0245), mat("card", (0.55, 0.45, 0.3), 0.9), verts=64)
    cyl("tapeh2", 0.033, 0.051, (-0.2, -0.05, 0.0246), mat("holedark", (0.01, 0.01, 0.01), 0.9), verts=64)
    rc = [box("rc", (0.05, 0.1, 0.022), (0, 0, 0), mat("rcp", (0.04, 0.04, 0.045), 0.5), 0.006),
          cyl("rcb", 0.007, 0.004, (0, 0.015, 0.023), mat("rcred", (0.6, 0.05, 0.03), 0.4)),
          cyl("rca", 0.0025, 0.12, (0.015, 0.105, 0.016), m_chrome(), rot=(math.radians(90), 0, 0))]
    _place(rc, (0.05, -0.12, 0), (0, 0, math.radians(-30)))
    for i in range(2):
        b = box(f"bat{i}", (0.026, 0.048, 0.017), (0, 0, 0), mat("bat9", (0.05, 0.05, 0.06), 0.4), 0.002)
        t = plane(f"batt{i}", 0.024, 0.046, (0, 0, 0.0171), mat("batlab", tex="hdd_label.png", rough=0.4))
        _place([b, t], (-0.1 + i * 0.035, -0.15, 0), (0, 0, math.radians(20 + i * 10)))
    stage(0.62, 15, look=(0.0, 0.0, 0.03), elev=46, azim=-18)


def m_chrome():
    return mat("chrome", (0.9, 0.9, 0.9), 0.12, 1.0)


def shells_ied():
    sh = mat("shell", tex="shell.png", rough=0.55, metal=0.3, bump=0.6)
    band = mat("band", (0.55, 0.3, 0.15), 0.35, 1.0)
    fuze = mat("plug", (0.3, 0.3, 0.28), 0.45, 0.8)
    for i, (x, y, rz) in enumerate([(-0.05, 0.08, 4), (0.0, -0.09, -3)]):
        body = cyl(f"sb{i}", 0.076, 0.42, (0, 0, 0), sh, verts=64)
        bpy.ops.mesh.primitive_cone_add(radius1=0.076, radius2=0.022, depth=0.2, vertices=64, location=(0, 0, 0.31))
        og = bpy.context.object
        bpy.ops.object.shade_smooth()
        for v in og.data.vertices:
            t = (v.co.z + 0.1) / 0.2
            r = math.hypot(v.co.x, v.co.y)
            if r > 1e-5:
                k = (0.022 + (0.076 - 0.022) * math.sqrt(max(0, 1 - t * t))) / r
                v.co.x *= k
                v.co.y *= k
        og.data.materials.append(sh)
        bd = cyl(f"bd{i}", 0.0785, 0.03, (0, 0, -0.17), band, verts=64)
        pl = cyl(f"pl{i}", 0.024, 0.03, (0, 0, 0.42), fuze, verts=32)
        base = cyl(f"base{i}", 0.0755, 0.004, (0, 0, -0.21), mat("basem", (0.2, 0.2, 0.19), 0.5, 0.7), verts=64)
        tps = [cyl(f"tp{i}{k}", 0.0772, 0.03, (0, 0, zz), mat("tape", (0.015, 0.015, 0.015), 0.4), verts=64) for k, zz in enumerate((-0.06, 0.12))]
        g = _place([body, og, bd, pl, base] + tps, (0, 0, 0), (0, math.radians(90), 0))
        _place([g], (x, y, 0.076), (0, 0, math.radians(rz)))
    # bidon jaune 20 L
    jc = mat("jerry", (0.55, 0.42, 0.05), 0.45, bump=0.6)
    j = [box("jb", (0.36, 0.17, 0.46), (0, 0, 0), jc, 0.03),
         box("jh", (0.12, 0.04, 0.05), (-0.06, 0, 0.45), jc, 0.012),
         cyl("jcap", 0.025, 0.03, (0.12, 0, 0.47), mat("cap", (0.03, 0.03, 0.03), 0.5))]
    for k in range(3):
        j.append(box(f"jx{k}", (0.3, 0.004, 0.02), (0, -0.086, 0.12 + k * 0.1), jc, 0.002))
    _place(j, (-0.3, 0.42, 0), (0, 0, math.radians(-8)))
    # fils et ruban
    red = mat("wire", (0.45, 0.04, 0.03), 0.4, bump=0.8)
    curve_cable("w1", [(0.18, 0.08, 0.13), (0.24, 0.16, 0.08), (0.12, 0.3, 0.02), (-0.02, 0.3, 0.06)], 0.002, red)
    curve_cable("w2", [(0.19, -0.09, 0.13), (0.3, 0.0, 0.03), (0.22, 0.12, 0.02), (0.18, 0.08, 0.13)], 0.002,
                mat("wireb", (0.02, 0.02, 0.025), 0.4))
    stage(1.3, 16, look=(-0.05, 0.05, 0.08), elev=44, azim=-24)

ITEMS = [("01-smartphone", smartphone), ("02-telephone-basique", basic_phone), ("03-telephone-satellite", satphone),
         ("04-radio-portative", radio), ("05-supports-amovibles", removable), ("06-disque-dur", hdd),
         ("07-ordinateur-portable", laptop), ("08-carnet", notebook), ("09-passeport-identite", passport),
         ("10-dossier-documents", documents), ("11-sac-a-scelles", evidence_bag), ("12-portefeuille", wallet),
         ("13-caisse-armes", weapons_crate), ("14-munitions", ammo_lot), ("15-explosifs", explosives),
         ("16-obus-engin-improvise", shells_ied)]

if __name__ == "__main__":
    want = sys.argv[1:]
    for name, fn in ITEMS:
        if want and not any(w in name for w in want):
            continue
        reset()
        fn()
        render(name)
