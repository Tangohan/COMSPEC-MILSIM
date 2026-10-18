"""
Icônes d'inventaire des items SSE (Arsenal ACE, inventaire, éditeur) : rendus Cycles photo-réels sur fond
transparent, cadrés automatiquement, puis réduits en 256 x 256 et convertis en PAA (_ca : couleur + alpha).
Les modèles reprennent ceux des previews (render_items.py) ; les items sans preview sont modélisés ici.

    pip install bpy pillow
    python3 icon_textures.py
    python3 render_icons.py <hemtt> [nom ...]      # RENDER_SAMPLES=n (défaut 128)
Sortie : tools/icons/png/<nom>.png (512 px, contrôle) et addons/evidence/data/icons/<nom>_ca.paa
"""
import math
import os
import subprocess
import sys

import bpy  # noqa: I001
from mathutils import Vector

import render_items as R
from render_items import box, cyl, curve_cable, mat, plane, set_parent, sheet, tbox

HERE = os.path.dirname(os.path.abspath(__file__))
PNG = os.path.join(HERE, "png")
PAA = os.path.join(HERE, "..", "..", "addons", "evidence", "data", "icons")
RES = 512
ICON = 256
R.SAMPLES = int(os.environ.get("RENDER_SAMPLES", "128"))
R.stage = lambda *a, **k: None  # les previews posent un décor : les icônes n'en veulent pas


# ------------------------------------------------------------------ studio
def bounds():
    dg = bpy.context.evaluated_depsgraph_get()
    pts = []
    for o in bpy.context.scene.objects:
        if o.type not in ("MESH", "CURVE") or o.hide_render:
            continue
        ev = o.evaluated_get(dg)
        if o.type == "CURVE":  # bound_box d'une courbe n'est pas fiable : on passe par son maillage
            me = ev.to_mesh()
            pts += [ev.matrix_world @ v.co for v in me.vertices]
            ev.to_mesh_clear()
        else:
            pts += [ev.matrix_world @ Vector(c) for c in ev.bound_box]
    return pts


def studio(elev=40, azim=-28, pad=1.1, exposure=0.0):
    """Fond transparent, éclairage trois points doux, caméra orthographique cadrée sur l'objet."""
    sc = bpy.context.scene
    sc.render.resolution_x = sc.render.resolution_y = RES
    sc.render.film_transparent = True
    sc.render.image_settings.file_format = "PNG"
    sc.render.image_settings.color_mode = "RGBA"
    sc.view_settings.exposure = exposure
    sc.world.node_tree.nodes["Background"].inputs[0].default_value = (0.32, 0.33, 0.34, 1)
    sc.world.node_tree.nodes["Background"].inputs[1].default_value = 0.45
    pts = bounds()
    lo = Vector((min(p.x for p in pts), min(p.y for p in pts), min(p.z for p in pts)))
    hi = Vector((max(p.x for p in pts), max(p.y for p in pts), max(p.z for p in pts)))
    c = (lo + hi) / 2
    size = max((hi - lo).length, 0.02)
    el, az = math.radians(elev), math.radians(azim)
    view = Vector((math.sin(az) * math.cos(el), -math.cos(az) * math.cos(el), math.sin(el)))
    rot = (-view).to_track_quat("-Z", "Y")
    inv = rot.to_matrix().inverted()
    loc = [inv @ (p - c) for p in pts]
    x0, x1 = min(v.x for v in loc), max(v.x for v in loc)
    y0, y1 = min(v.y for v in loc), max(v.y for v in loc)
    shift = rot.to_matrix() @ Vector(((x0 + x1) / 2, (y0 + y1) / 2, 0))
    cam = bpy.data.cameras.new("cam")
    cam.type = "ORTHO"
    cam.ortho_scale = max(x1 - x0, y1 - y0) * pad
    cam.clip_end = size * 20
    co = bpy.data.objects.new("cam", cam)
    bpy.context.collection.objects.link(co)
    co.location = c + shift + view * size * 4
    co.rotation_euler = rot.to_euler()
    sc.camera = co

    def area(direction, energy, scale, color=(1, 1, 1)):
        d = Vector(direction).normalized()
        bpy.ops.object.light_add(type="AREA", location=c + d * size * 3)
        L = bpy.context.object
        L.data.energy = energy * size * size
        L.data.size = size * scale
        L.data.color = color
        L.rotation_euler = (-d).to_track_quat("-Z", "Y").to_euler()

    side = Vector((math.cos(az), math.sin(az), 0))
    area(view + side * -0.9 + Vector((0, 0, 0.9)), 260, 1.4, (1.0, 0.97, 0.92))   # principale, haut gauche
    area(view + side * 1.2 + Vector((0, 0, 0.1)), 70, 2.0, (0.9, 0.95, 1.0))      # débouchage, droite
    area(-view + Vector((0, 0, 0.9)), 220, 1.0)                                     # contre-jour (détoure le bord)


def render(name, hemtt):
    from PIL import Image

    os.makedirs(PNG, exist_ok=True)
    os.makedirs(PAA, exist_ok=True)
    big = os.path.join(PNG, name + ".png")
    bpy.context.scene.render.filepath = big
    bpy.ops.render.render(write_still=True)
    im = Image.open(big).convert("RGBA")
    # centre l'objet et laisse 6 % de marge, comme les icônes vanilla
    bb = im.getchannel("A").point(lambda a: 255 if a > 8 else 0).getbbox()
    if bb:
        crop = im.crop(bb)
        side = int(max(crop.size) * 1.12)
        sq = Image.new("RGBA", (side, side), (0, 0, 0, 0))
        sq.paste(crop, ((side - crop.size[0]) // 2, (side - crop.size[1]) // 2))
        im = sq
    im = im.resize((ICON, ICON), Image.LANCZOS)
    small = os.path.join(PNG, name + "_ca.png")
    im.save(small)
    paa = os.path.join(PAA, name + "_ca.paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([hemtt, "utils", "paa", "convert", small, paa], check=True, stdout=subprocess.DEVNULL)
    print("icône", name)


def M():
    return R.M()


# ------------------------------------------------------------------ items repris des previews
def smartphone():
    m = M()
    b = box("body", (0.073, 0.152, 0.0082), (0, 0, 0), m["darkalu"], 0.0045, segs=6)
    g = box("glass", (0.0715, 0.1505, 0.0006), (0, 0, 0.0082), m["glass"], 0.004, segs=6)
    s = plane("screen", 0.0675, 0.1425, (0, 0, 0.00885), mat("scr", tex="smartphone_screen.png", rough=0.1, emit=1.0))
    lens = cyl("lens", 0.0012, 0.001, (0, 0.071, 0.0088), m["glass"])
    btn = box("btn", (0.0012, 0.03, 0.0025), (0.0368, 0.03, 0.003), m["darkalu"], 0.0005)
    set_parent([b, g, s, lens, btn], (0, 0, 0), math.radians(-18))
    return dict(elev=52, azim=-20)


def basic_phone():
    m = M()
    parts = [box("body", (0.05, 0.113, 0.0145), (0, 0, 0), m["greyplast"], 0.006, segs=6),
             box("front", (0.046, 0.108, 0.001), (0, 0, 0.0145), mat("front", (0.06, 0.065, 0.07), 0.35), 0.004),
             plane("lcd", 0.033, 0.026, (0, 0.03, 0.0157), mat("lcd", tex="basic_screen.png", rough=0.2, emit=0.6)),
             plane("keys", 0.04, 0.042, (0, -0.026, 0.0157), mat("keys", tex="basic_keys.png", rough=0.4)),
             box("nav", (0.026, 0.012, 0.0014), (0, 0.006, 0.0153), mat("navk", (0.05, 0.05, 0.055), 0.3, 0.5), 0.004),
             box("ear", (0.012, 0.0015, 0.0003), (0, 0.048, 0.0156), m["blackplast"])]
    set_parent(parts, (0, 0, 0), math.radians(-18))
    return dict(elev=52, azim=-20)


def satphone():
    R.satphone()
    return dict(elev=46, azim=-24)


def usb():
    m = M()
    parts = [box("usb", (0.02, 0.05, 0.0085), (0, 0, 0), m["blackplast"], 0.003, segs=5),
             box("usbm", (0.012, 0.013, 0.0045), (0, 0.031, 0.002), m["alu"], 0.0006),
             box("usbh1", (0.0026, 0.0026, 0.0002), (-0.0026, 0.033, 0.0065), m["blackplast"]),
             box("usbh2", (0.0026, 0.0026, 0.0002), (0.0026, 0.033, 0.0065), m["blackplast"]),
             plane("usbl", 0.026, 0.0065, (0, -0.006, 0.0086), mat("usbl", tex="usb_label.png", rough=0.4), rot=(0, 0, math.radians(90))),
             cyl("ring", 0.0035, 0.003, (0, -0.028, 0.004), m["chrome"])]
    set_parent(parts, (0, 0, 0), math.radians(-35))
    return dict(elev=48, azim=-22)


def sdcard():
    gold = mat("gold", (0.9, 0.7, 0.35), 0.25, 1.0)
    body = mat("sdbody", (0.03, 0.03, 0.035), 0.4)
    sd = [tbox("sd", (0.024, 0.032, 0.0021), (0, 0, 0), mat("sdl", tex="sd_label.png", rough=0.35), 0.0008, edge=(0.03, 0.03, 0.035)),
          box("lock", (0.0012, 0.004, 0.0012), (-0.0122, 0.004, 0.0005), mat("lockw", (0.85, 0.85, 0.85), 0.4))]
    for i in range(8):
        sd.append(box(f"pin{i}", (0.0016, 0.0035, 0.00015), (-0.0085 + i * 0.0024, 0.0135, -0.0001), gold))
    set_parent(sd, (-0.008, 0.004, 0.0), math.radians(-14))
    tbox("msd", (0.011, 0.015, 0.0009), (0.017, -0.012, 0), mat("sd", tex="microsd.png", rough=0.35), 0.0006,
         rot=(0, 0, math.radians(18)), edge=(0.03, 0.03, 0.03))
    _ = body
    return dict(elev=55, azim=-18)


def harddrive():
    m = M()
    parts = [box("hdd", (0.085, 0.125, 0.021), (0, 0, 0), m["rubber"], 0.008, segs=6),
             box("top", (0.07, 0.105, 0.001), (0, 0, 0.0205), mat("hddtop", (0.03, 0.03, 0.032), 0.3), 0.004),
             plane("lab", 0.05, 0.05, (0, 0.0, 0.0216), mat("hddl", tex="hdd_label.png", rough=0.35)),
             box("port", (0.012, 0.004, 0.006), (0.02, 0.0625, 0.007), mat("port", (0.1, 0.25, 0.7), 0.4)),
             box("led", (0.004, 0.002, 0.0015), (-0.02, 0.0625, 0.012), mat("led", (0.2, 0.5, 1), 0.3, emit=4))]
    set_parent(parts, (0, 0, 0), math.radians(-20))
    return dict(elev=44, azim=-24)


def laptop():
    R.laptop()
    for o in list(bpy.data.objects):
        if o.name.startswith("pwr"):
            bpy.data.objects.remove(o)
    return dict(elev=26, azim=-24)


def notebook():
    R.notebook()
    return dict(elev=52, azim=-15)


def documents():
    R.documents()
    return dict(elev=58, azim=-12)


def evidence_bag():
    R.evidence_bag()
    bag = bpy.data.materials["bag"].node_tree.nodes["Principled BSDF"]
    bag.inputs["Transmission Weight"].default_value = 0.95
    bag.inputs["Roughness"].default_value = 0.12
    bag.inputs["Base Color"].default_value = (0.85, 0.9, 0.95, 1)
    bpy.context.scene.cycles.film_transparent_glass = True
    return dict(elev=60, azim=-10)


# ------------------------------------------------------------------ items modélisés pour les icônes
def subsurf(o, lvl=2):
    md = o.modifiers.new("ss", "SUBSURF")
    md.levels = md.render_levels = lvl
    return o


def capsule(name, r, length, loc, rot_z, m, flat=0.75):
    """Doigt : cylindre + bout arrondi, posé à plat (aplati en z), dirigé selon rot_z depuis loc."""
    d = Vector((math.cos(rot_z), math.sin(rot_z), 0))
    mid = Vector(loc) + d * length / 2
    c = cyl(name, r, length, mid, m, rot=(0, math.radians(90), rot_z), verts=24)
    bpy.ops.object.transform_apply(location=False, rotation=True, scale=False)
    bpy.ops.mesh.primitive_uv_sphere_add(radius=r, location=Vector(loc) + d * length, segments=24, ring_count=12)
    tip = bpy.context.object
    bpy.ops.object.shade_smooth()
    tip.data.materials.append(m)
    for o in (c, tip):
        o.scale.z = flat
    return [c, tip]


def glove(m, loc, rot_z, mirror=1):
    palm = subsurf(box("palm", (0.082, 0.092, 0.014), (0, 0, 0), m, 0.012, segs=3))
    palm.scale.z = 0.8
    cuff = subsurf(box("cuff", (0.074, 0.075, 0.006), (0, -0.08, 0.001), m, 0.004))
    roll = cyl("roll", 0.003, 0.076, (0, -0.118, 0.0035), m, rot=(0, math.radians(90), 0), verts=16)
    parts = [palm, cuff, roll]
    # quatre doigts et le pouce (longueur, décalage x, angle)
    for i, (L, x, a) in enumerate(((0.062, -0.028, 98), (0.072, -0.009, 92), (0.068, 0.010, 87), (0.056, 0.028, 80))):
        parts += capsule(f"f{i}", 0.0092, L, (x * mirror, 0.04, 0.006), math.radians(a if mirror > 0 else 180 - a), m)
    parts += capsule("thumb", 0.0105, 0.05, (0.038 * mirror, -0.015, 0.006), math.radians(30 if mirror > 0 else 150), m)
    return set_parent(parts, loc, rot_z)


def gloves():
    m = mat("nitrile", (0.10, 0.26, 0.72), 0.42, coat=0.25, bump=0.4)
    glove(m, (-0.035, 0.0, 0.0), math.radians(12), 1)
    glove(m, (0.05, -0.02, 0.012), math.radians(-18), -1)
    return dict(elev=62, azim=-12)


def camera():
    m = M()
    body = mat("cambody", (0.03, 0.03, 0.032), 0.55, bump=0.6)
    leather = mat("camgrip", (0.018, 0.018, 0.019), 0.8, bump=1.2)
    parts = [box("body", (0.135, 0.072, 0.088), (0, 0, 0), body, 0.012, segs=5),
             box("grip", (0.034, 0.034, 0.084), (0.05, -0.04, 0.0), leather, 0.013, segs=5),
             box("prism", (0.062, 0.058, 0.03), (-0.004, 0.006, 0.08), body, 0.01, segs=4),
             box("shoe", (0.022, 0.02, 0.004), (-0.004, 0.008, 0.11), m["chrome"], 0.001),
             cyl("dial", 0.013, 0.008, (-0.045, 0.01, 0.092), mat("dial", (0.05, 0.05, 0.05), 0.5, 0.6, bump=1.5)),
             cyl("shutter", 0.0065, 0.005, (0.052, -0.035, 0.0865), m["chrome"]),
             cyl("wheel", 0.01, 0.005, (0.03, -0.02, 0.0885), m["blackplast"]),
             box("brand", (0.03, 0.001, 0.006), (-0.004, -0.0234, 0.088), mat("brandw", (0.85, 0.85, 0.85), 0.4)),
             cyl("lug", 0.004, 0.006, (-0.07, -0.01, 0.075), m["chrome"], rot=(0, math.radians(90), 0)),
             cyl("red", 0.003, 0.002, (0.03, -0.0365, 0.08), mat("reddot", (0.7, 0.05, 0.04), 0.4), rot=(math.radians(90), 0, 0))]
    # objectif le long de -y
    ry = (math.radians(90), 0, 0)
    y = -0.036
    for name, r, depth, mm in (("mount", 0.031, 0.006, m["chrome"]), ("barrel", 0.033, 0.03, body),
                               ("zoom", 0.035, 0.026, leather), ("focus", 0.034, 0.016, leather),
                               ("front", 0.034, 0.012, body)):
        parts.append(cyl(name, r, depth, (0, y - depth / 2, 0.042), mm, rot=ry, verts=64, bevel=0.0012))
        y -= depth
    parts.append(cyl("glass", 0.027, 0.002, (0, y + 0.004, 0.042), mat("lensg", (0.02, 0.03, 0.05), 0.02, coat=1.0), rot=ry, verts=64))
    parts.append(cyl("ringtxt", 0.0342, 0.002, (0, y + 0.0015, 0.042), mat("lensring", (0.55, 0.55, 0.55), 0.4, 0.8), rot=ry, verts=64))
    # sangle
    curve_cable("strap", [(-0.068, -0.01, 0.075), (-0.09, -0.03, 0.03), (-0.08, -0.06, 0.002), (-0.03, -0.1, 0.002)],
                0.003, mat("strap", (0.02, 0.02, 0.02), 0.8, bump=1.0))
    set_parent(parts, (0, 0, 0), math.radians(8))
    return dict(elev=24, azim=-34)


def fingerprint_kit():
    m = M()
    case = mat("fpcase", (0.03, 0.032, 0.034), 0.5, bump=0.5)
    foam = mat("foam", (0.05, 0.05, 0.052), 0.95, bump=2.5)
    L, W, H = 0.26, 0.17, 0.045
    parts = [box("base", (L, W, H), (0, 0, 0), case, 0.006, segs=4),
             box("foam", (L - 0.012, W - 0.012, 0.002), (0, 0, H - 0.004), foam, 0.002)]
    for x in (-0.08, 0.08):
        parts.append(box(f"latch{x}", (0.026, 0.006, 0.018), (x, -W / 2 - 0.002, H - 0.016), m["chrome"], 0.002))
    # couvercle ouvert, charnière à l'arrière
    lid = box("lid", (L, W, 0.022), (0, -W / 2, 0.0), case, 0.006, segs=4)
    lidf = box("lidfoam", (L - 0.014, W - 0.014, 0.003), (0, -W / 2, -0.002), mat("eggfoam", (0.045, 0.045, 0.047), 0.95, bump=4), 0.002)
    hg = set_parent([lid, lidf], (0, W / 2, H))
    hg.rotation_euler = (math.radians(-100), 0, 0)
    top = H - 0.002
    # poudre noire et poudre argentée
    for i, (x, col) in enumerate(((-0.085, (0.02, 0.02, 0.02)), (-0.035, (0.55, 0.56, 0.58)))):
        parts += [cyl(f"jar{i}", 0.02, 0.034, (x, 0.035, top + 0.017), mat(f"jar{i}", col, 0.35, 0.5 if i else 0.0)),
                  cyl(f"jarcap{i}", 0.021, 0.008, (x, 0.035, top + 0.038), mat("jarcap", (0.025, 0.025, 0.027), 0.35)),
                  cyl(f"jarlab{i}", 0.0202, 0.014, (x, 0.035, top + 0.016), mat("jarlab", (0.9, 0.9, 0.86), 0.6))]
    # pinceau (manche, virole, poils)
    br = [cyl("bh", 0.0045, 0.11, (0, 0, 0), mat("brushh", (0.25, 0.12, 0.05), 0.4, coat=0.6), rot=(0, math.radians(90), 0)),
          cyl("bf", 0.0055, 0.018, (0.064, 0, 0), m["chrome"], rot=(0, math.radians(90), 0))]
    bpy.ops.mesh.primitive_cone_add(radius1=0.0058, radius2=0.012, depth=0.035, vertices=32, location=(0.09, 0, 0),
                                    rotation=(0, math.radians(90), 0))
    bristle = bpy.context.object
    bpy.ops.object.shade_smooth()
    bristle.data.materials.append(mat("bristle", (0.04, 0.035, 0.03), 0.9, bump=3))
    br.append(bristle)
    set_parent(br, (0.02, -0.04, top + 0.006), math.radians(-6))
    # rouleau encreur et fiches
    parts += [cyl("roller", 0.009, 0.05, (0.06, 0.04, top + 0.009), mat("rubroll", (0.01, 0.01, 0.01), 0.3), rot=(0, math.radians(90), 0)),
              cyl("axle", 0.002, 0.07, (0.06, 0.04, top + 0.009), m["chrome"], rot=(0, math.radians(90), 0))]
    tbox("cards", (0.085, 0.06, 0.004), (0.085, -0.045, top), mat("fpc", tex="fp_card.png", rough=0.7), 0.001, edge=(0.92, 0.91, 0.88),
         rot=(0, 0, math.radians(8)))
    set_parent(parts, (0, 0, 0))
    return dict(elev=44, azim=-20)


def dna_kit():
    box_m = mat("dnabox", (0.86, 0.87, 0.88), 0.6, bump=0.3)
    b = box("box", (0.17, 0.105, 0.032), (0, 0, 0), box_m, 0.002)
    plane("lab", 0.166, 0.101, (0, 0, 0.0322), mat("dnal", tex="dna_label.png", rough=0.5))
    set_parent([b, bpy.data.objects["lab"]], (0, 0.03, 0), math.radians(-8))
    tube = mat("tube", (0.92, 0.94, 0.96), 0.08, transmission=0.9)
    capm = mat("tubecap", (0.08, 0.3, 0.75), 0.4)
    for i, (x, y, a) in enumerate(((-0.03, -0.05, 6), (0.03, -0.07, -10))):
        r90 = (0, math.radians(90), 0)
        t = [cyl(f"tube{i}", 0.0075, 0.11, (0, 0, 0), tube, rot=r90, verts=32),
             cyl(f"cap{i}", 0.0085, 0.024, (0.065, 0, 0), capm, rot=r90, verts=32, bevel=0.001),
             cyl(f"stick{i}", 0.0015, 0.1, (-0.002, 0, 0), mat("stick", (0.9, 0.9, 0.88), 0.6), rot=r90),
             cyl(f"swab{i}", 0.0045, 0.018, (-0.046, 0, 0), mat("cotton", (0.95, 0.95, 0.93), 0.95, bump=2), rot=r90)]
        set_parent(t, (x, y, 0.0085), math.radians(a))
    return dict(elev=50, azim=-18)


def seek():
    m = M()
    shell = mat("seekshell", (0.06, 0.062, 0.058), 0.6, bump=0.7)
    bump_m = mat("seekbump", (0.025, 0.025, 0.024), 0.85, bump=1.2)
    capm = mat("seekkey", (0.12, 0.12, 0.12), 0.5)
    W, L, D = 0.115, 0.215, 0.04
    parts = [box("body", (W, L, D), (0, 0, 0), shell, 0.01, segs=5)]
    for sx in (-1, 1):
        for sy in (-1, 1):
            parts.append(box("bumper", (0.03, 0.03, D + 0.006), (sx * (W / 2 - 0.012), sy * (L / 2 - 0.012), -0.003), bump_m, 0.01, segs=4))
    parts += [box("bezel", (0.098, 0.082, 0.0012), (0, 0.048, D - 0.0004), mat("sbz", (0.005, 0.005, 0.006), 0.1, coat=1), 0.004),
              plane("screen", 0.088, 0.066, (0, 0.048, D + 0.00085), mat("sscr", tex="seek_screen.png", rough=0.1, emit=1.1))]
    for r in range(4):
        for c in range(9):
            parts.append(box("k", (0.0085, 0.0068, 0.0018), (-0.0408 + c * 0.0102, -0.008 - r * 0.0088, D - 0.0004), capm, 0.0014))
    # capteur d'empreinte (vitre verte) en bas, caméra iris en haut
    parts += [box("fpw", (0.05, 0.042, 0.004), (0, -0.075, D - 0.001), bump_m, 0.004),
              box("fpg", (0.036, 0.03, 0.0008), (0, -0.075, D + 0.0028), mat("fpglass", (0.1, 0.7, 0.3), 0.05, emit=0.6, coat=1)),
              cyl("iris", 0.012, 0.012, (0.02, L / 2 + 0.002, D * 0.55), bump_m, rot=(math.radians(90), 0, 0)),
              cyl("irisg", 0.008, 0.002, (0.02, L / 2 + 0.008, D * 0.55), mat("irisl", (0.02, 0.02, 0.03), 0.02, coat=1), rot=(math.radians(90), 0, 0)),
              cyl("irled", 0.0025, 0.002, (-0.01, L / 2 + 0.0005, D * 0.6), mat("irled", (0.5, 0.05, 0.05), 0.2, emit=0.8), rot=(math.radians(90), 0, 0)),
              box("strap", (0.008, 0.12, 0.012), (-W / 2 - 0.004, 0.0, D * 0.3), mat("webbing", (0.03, 0.03, 0.028), 0.9, bump=1.5), 0.003)]
    set_parent(parts, (0, 0, 0), math.radians(-16))
    return dict(elev=52, azim=-20)


def terminal():
    m = M()
    shell = mat("tshell", (0.045, 0.047, 0.044), 0.55, bump=0.6)
    rub = mat("trub", (0.02, 0.02, 0.019), 0.85, bump=1.2)
    W, L, D = 0.29, 0.205, 0.024
    parts = [box("body", (W, L, D), (0, 0, 0), shell, 0.008, segs=5)]
    for sx in (-1, 1):
        for sy in (-1, 1):
            parts.append(box("bumper", (0.04, 0.04, D + 0.006), (sx * (W / 2 - 0.015), sy * (L / 2 - 0.015), -0.003), rub, 0.012, segs=4))
    parts += [box("bezel", (0.25, 0.17, 0.001), (0, 0, D - 0.0003), mat("tbz", (0.005, 0.005, 0.006), 0.1, coat=1), 0.004),
              plane("screen", 0.236, 0.157, (0, 0, D + 0.00072), mat("tscr", tex="terminal_screen.png", rough=0.1, emit=1.1))]
    for i in range(4):
        parts.append(box(f"fk{i}", (0.006, 0.014, 0.002), (W / 2 - 0.009, 0.045 - i * 0.025, D - 0.0005), rub, 0.0015))
    # poignée de transport sur le bord haut
    curve_cable("handle", [(-0.07, L / 2 - 0.004, D * 0.5), (-0.05, L / 2 + 0.028, D * 0.5), (0.05, L / 2 + 0.028, D * 0.5),
                           (0.07, L / 2 - 0.004, D * 0.5)], 0.0065, rub)
    set_parent(parts, (0, 0, 0))
    for o in bpy.data.objects:
        if o.name == "handle":
            o.parent = None
    return dict(elev=50, azim=-18)


def headbag():
    """Sac de tête en toile kaki posé à plat : sac froissé, col resserré par un cordon de serrage, collerette froncée."""
    import random

    rnd = random.Random(4)
    cloth = mat("hood", (0.10, 0.095, 0.065), 0.95, bump=2.0)
    bpy.ops.mesh.primitive_cube_add(size=1, location=(0, 0, 0))
    o = bpy.context.object
    o.scale = (0.19, 0.30, 0.05)
    bpy.ops.object.transform_apply(scale=True)
    md = o.modifiers.new("ss", "SUBSURF")
    md.levels = md.render_levels = 5
    bpy.ops.object.modifier_apply(modifier="ss")
    waves = [(rnd.uniform(25, 70), rnd.uniform(15, 50), rnd.uniform(0, 6.3), rnd.uniform(0.0015, 0.004)) for _ in range(9)]
    for v in o.data.vertices:
        t = (v.co.y + 0.15) / 0.30  # 0 = fond du sac, 1 = col
        n = max(0.0, t - 0.62) / 0.38
        v.co.x *= 1.0 + 0.06 * (1 - t) - 0.82 * n ** 1.4
        v.co.z *= 1.0 - 0.6 * n ** 1.2
        for fx, fy, ph, a in waves:
            v.co.z += a * math.sin(v.co.x * fx + v.co.y * fy + ph) * (1.0 if v.co.z > 0 else 0.3) * (1 - 0.7 * n)
        v.co.z = max(v.co.z, -0.012) + 0.012
    bpy.ops.object.shade_smooth()
    o.data.materials.append(cloth)
    ry = (math.radians(-90), 0, 0)
    neck = cyl("neck", 0.011, 0.024, (0, 0.146, 0.012), cloth, rot=ry, verts=32)
    # collerette froncée : cône évasé dont le bord ondule
    bpy.ops.mesh.primitive_cone_add(radius1=0.011, radius2=0.032, depth=0.03, vertices=64, end_fill_type="NOTHING",
                                    location=(0, 0.171, 0.012), rotation=ry)
    ruf = bpy.context.object
    for v in ruf.data.vertices:
        a = math.atan2(v.co.y, v.co.x)
        if v.co.z > 0:
            k = 1 + 0.18 * math.sin(a * 11)
            v.co.x *= k
            v.co.y *= k
            v.co.z += 0.004 * math.sin(a * 7)
    ruf.modifiers.new("sol", "SOLIDIFY").thickness = 0.0015
    bpy.ops.object.shade_smooth()
    ruf.data.materials.append(cloth)
    ruf.scale = (1.0, 1.0, 1.0)
    cord = mat("cord", (0.02, 0.02, 0.018), 0.7, bump=2)
    bpy.ops.mesh.primitive_torus_add(major_radius=0.0118, minor_radius=0.0026, location=(0, 0.15, 0.012), rotation=ry,
                                     major_segments=32, minor_segments=8)
    wrap = bpy.context.object
    bpy.ops.object.shade_smooth()
    wrap.data.materials.append(cord)
    c1 = curve_cable("cord1", [(0.011, 0.15, 0.012), (0.04, 0.16, 0.006), (0.07, 0.15, 0.003), (0.09, 0.11, 0.003)], 0.0024, cord)
    c2 = curve_cable("cord2", [(0.011, 0.152, 0.014), (0.045, 0.175, 0.006), (0.08, 0.18, 0.003), (0.11, 0.15, 0.003)], 0.0024, cord)
    tog = cyl("toggle", 0.0058, 0.016, (0.035, 0.162, 0.008), mat("toggle", (0.03, 0.03, 0.03), 0.4), rot=(0, math.radians(90), math.radians(20)))
    set_parent([o, neck, ruf, wrap, c1, c2, tog], (0, 0, 0), math.radians(-28))
    return dict(elev=50, azim=-18)


ITEMS = [("evidencebag", evidence_bag), ("gloves", gloves), ("camera", camera), ("fingerprintkit", fingerprint_kit),
         ("dnakit", dna_kit), ("seekii", seek), ("usb", usb), ("sdcard", sdcard), ("harddrive", harddrive),
         ("laptop", laptop), ("phone", basic_phone), ("smartphone", smartphone), ("documentbag", documents),
         ("notebook", notebook), ("satphone", satphone), ("terminal", terminal), ("headbag", headbag)]

if __name__ == "__main__":
    hemtt = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
    want = sys.argv[2:]
    for name, fn in ITEMS:
        if want and name not in want:
            continue
        R.reset()
        view = fn() or {}
        studio(**view)
        render(name, hemtt)
