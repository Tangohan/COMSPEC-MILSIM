"""
Rendus de contrôle et aperçus Eden du mobilier de TOC (model_toc_furniture.py) et des postes PC (model_toc_pc.py), dans
Blender (Cycles) : mêmes géométries et mêmes textures que le jeu. Ce sont des RENDUS, pas des captures du jeu.

    python render_toc_furniture.py DOSSIER_DE_SORTIE
        BLENDER=chemin de blender.exe (sinon « blender » du PATH) ; WORK=dossier de travail (OBJ, PNG) ;
        NO_PREVIEW=1 : sans réécrire les aperçus Eden (data/toc/*_preview.jpg) ; ONLY=planche,salle,detail

Sorties : bureaux_planche.png (chaque modèle seul, postes montés), poste_vue_operateur.png (salle moyenne vue de derrière
les opérateurs : les moniteurs font face aux fauteuils, le mur d'images est devant), salle_vue_dessus.png (plan annoté),
poste_detail.png (gros plan d'un poste).
La salle est montée à partir des tables de fn_tocHallBuild.sqf (_rooms, _k, _facing, _deskPlan, _deskProps), lues telles
quelles : la vue prouve la règle d'orientation du constructeur, pas une mise en scène à part.

Le même fichier est le script exécuté dans Blender (blender -b --python render_toc_furniture.py -- travail.json).
"""
import json
import math
import os
import re
import shutil
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
try:
    import bpy
    from mathutils import Vector
    IN_BLENDER = True
except ImportError:
    IN_BLENDER = False


# --------------------------------------------------------------------------------------------------------------------
# Dans Blender
# --------------------------------------------------------------------------------------------------------------------
def b_setup(samples):
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    try:
        prefs = bpy.context.preferences.addons["cycles"].preferences
        for kind in ("OPTIX", "HIP", "CUDA"):
            try:
                prefs.compute_device_type = kind
                prefs.get_devices()
                ok = False
                for dv in prefs.devices:
                    dv.use = dv.type != "CPU"
                    ok = ok or dv.use
                if ok:
                    sc.cycles.device = "GPU"
                    break
            except Exception:
                continue
    except Exception:
        pass
    sc.cycles.samples = samples
    sc.cycles.use_denoising = True
    sc.cycles.max_bounces = 6
    try:
        sc.view_settings.view_transform = "AgX"
        sc.view_settings.look = "AgX - Punchy"
    except Exception:
        pass
    return sc


def b_image(path, non_color=False):
    img = bpy.data.images.load(path, check_existing=True)
    if non_color:
        img.colorspace_settings.name = "Non-Color"
    return img


def b_material(name, s):
    mat = bpy.data.materials.new(name)
    mat.use_nodes = True
    nt = mat.node_tree
    bsdf = nt.nodes["Principled BSDF"]
    bsdf.inputs["Roughness"].default_value = s.get("rough", 0.6)
    bsdf.inputs["Metallic"].default_value = s.get("metal", 0.0)
    if "emit_png" in s:
        tex = nt.nodes.new("ShaderNodeTexImage")
        tex.image = b_image(s["emit_png"])
        bsdf.inputs["Base Color"].default_value = (0.01, 0.01, 0.01, 1)
        nt.links.new(tex.outputs["Color"], bsdf.inputs["Emission Color"])
        bsdf.inputs["Emission Strength"].default_value = s.get("emit", 2.2)
        return mat
    if "flat" in s:
        r, g, b = s["flat"]
        bsdf.inputs["Base Color"].default_value = (r, g, b, 1)
        if s.get("glow"):
            bsdf.inputs["Emission Color"].default_value = (r, g, b, 1)
            bsdf.inputs["Emission Strength"].default_value = s["glow"]
        return mat
    tex = nt.nodes.new("ShaderNodeTexImage")
    tex.image = b_image(s["co"])
    nt.links.new(tex.outputs["Color"], bsdf.inputs["Base Color"])
    if s.get("glow"):
        nt.links.new(tex.outputs["Color"], bsdf.inputs["Emission Color"])
        bsdf.inputs["Emission Strength"].default_value = s["glow"]
    if s.get("nohq"):
        nm = nt.nodes.new("ShaderNodeTexImage")
        nm.image = b_image(s["nohq"], True)
        nmap = nt.nodes.new("ShaderNodeNormalMap")
        nmap.inputs["Strength"].default_value = 0.6
        nt.links.new(nm.outputs["Color"], nmap.inputs["Color"])
        nt.links.new(nmap.outputs["Normal"], bsdf.inputs["Normal"])
    return mat


_MATS = {}


def b_load(path, loc, heading):
    """OBJ posé en loc, cap du jeu (degrés, sens horaire depuis +Y) : rotation Blender de -cap."""
    before = set(bpy.data.objects)
    bpy.ops.wm.obj_import(filepath=path, forward_axis="Y", up_axis="Z")
    objs = [o for o in bpy.data.objects if o not in before]
    with open(path + ".json") as f:
        spec = json.load(f)
    for o in objs:
        for slot in o.material_slots:
            base = slot.material.name.split(".")[0]
            key = path + "|" + base
            if key not in _MATS:
                _MATS[key] = b_material(os.path.basename(path) + "_" + base, spec.get(base, {}))
            slot.material = _MATS[key]
        o.location = loc
        o.rotation_euler = (0, 0, math.radians(-heading))
        # Normales de l'OBJ (arêtes vives des boîtes, surfaces lissées des coques) : gardées telles quelles.
        for poly in o.data.polygons:
            poly.use_smooth = True
    return objs


def b_box(name, lo, hi, color, rough=0.9, emit=0.0):
    bpy.ops.mesh.primitive_cube_add(size=1.0, location=[(a + b) / 2 for a, b in zip(lo, hi)])
    o = bpy.context.object
    o.scale = [max(1e-4, b - a) for a, b in zip(lo, hi)]
    mat = bpy.data.materials.new(name)
    mat.use_nodes = True
    bsdf = mat.node_tree.nodes["Principled BSDF"]
    bsdf.inputs["Base Color"].default_value = tuple(color) + (1,)
    bsdf.inputs["Roughness"].default_value = rough
    if emit:
        bsdf.inputs["Emission Color"].default_value = tuple(color) + (1,)
        bsdf.inputs["Emission Strength"].default_value = emit
    o.data.materials.append(mat)
    return o


def b_bbox(objs):
    lo, hi = Vector((1e9, 1e9, 1e9)), Vector((-1e9, -1e9, -1e9))
    bpy.context.view_layer.update()
    for o in objs:
        for corner in o.bound_box:
            w = o.matrix_world @ Vector(corner)
            lo = Vector((min(lo.x, w.x), min(lo.y, w.y), min(lo.z, w.z)))
            hi = Vector((max(hi.x, w.x), max(hi.y, w.y), max(hi.z, w.z)))
    return lo, hi


def b_camera(sc, eye, target, fov=None, ortho=None, res=(1600, 900)):
    bpy.ops.object.camera_add(location=eye)
    cam = bpy.context.object
    sc.camera = cam
    sc.render.resolution_x, sc.render.resolution_y = res
    d = Vector(target) - Vector(eye)
    cam.rotation_euler = d.to_track_quat("-Z", "Y").to_euler()
    if ortho:
        cam.data.type = "ORTHO"
        cam.data.ortho_scale = ortho
    else:
        cam.data.sensor_fit = "HORIZONTAL"
        cam.data.angle = math.radians(fov)
    cam.data.clip_start, cam.data.clip_end = 0.02, 200
    return cam


def b_world(sc, color, strength):
    w = bpy.data.worlds.new("w")
    sc.world = w
    w.use_nodes = True
    bg = w.node_tree.nodes["Background"]
    bg.inputs[0].default_value = tuple(color) + (1,)
    bg.inputs[1].default_value = strength


def b_light(kind, loc, energy, size=1.0, rot=(0, 0, 0), color=(1, 1, 1)):
    bpy.ops.object.light_add(type=kind, location=loc)
    lt = bpy.context.object
    lt.data.energy = energy
    lt.data.color = color
    lt.rotation_euler = [math.radians(a) for a in rot]
    if kind == "AREA":
        lt.data.size = size
    elif kind == "SUN":
        lt.data.angle = math.radians(6)
    return lt


def b_run(job):
    for scene in job["scenes"]:
        sc = b_setup(scene.get("samples", 48))
        _MATS.clear()
        objs = []
        for it in scene["objs"]:
            objs += b_load(it["obj"], it.get("loc", (0, 0, 0)), it.get("heading", 0.0))
        lo, hi = b_bbox(objs)
        if scene["kind"] == "studio":
            b_world(sc, (0.72, 0.76, 0.82), 0.9)
            b_box("floor", (-30, -30, lo.z - 0.02), (30, 30, lo.z), (0.50, 0.51, 0.53), 0.75)
            b_light("SUN", (0, 0, 5), 3.2, rot=(38, 0, 150), color=(1, 0.97, 0.93))
            b_light("AREA", (lo.x - 1.5, hi.y + 2.0, hi.z + 1.5), 250, size=3.0, rot=(-50, 0, -140))
        else:
            r = scene["room"]
            b_world(sc, (0.02, 0.025, 0.035), 1.0)
            b_box("floor", (r["x0"], r["y0"], -0.05), (r["x1"], r["y1"], 0.0), (0.045, 0.05, 0.06), 0.55)
            b_box("wall_n", (r["x0"], r["y1"], 0.0), (r["x1"], r["y1"] + 0.2, r["h"]), (0.10, 0.11, 0.125), 0.85)
            b_box("wall_w", (r["x0"] - 0.2, r["y0"], 0.0), (r["x0"], r["y1"], r["h"]), (0.13, 0.14, 0.155), 0.85)
            b_box("wall_e", (r["x1"], r["y0"], 0.0), (r["x1"] + 0.2, r["y1"], r["h"]), (0.13, 0.14, 0.155), 0.85)
            if scene.get("ceiling"):
                b_box("wall_s", (r["x0"], r["y0"] - 0.2, 0.0), (r["x1"], r["y0"], r["h"]), (0.13, 0.14, 0.155), 0.85)
                b_box("ceil", (r["x0"], r["y0"], r["h"]), (r["x1"], r["y1"], r["h"] + 0.1), (0.07, 0.075, 0.08), 0.9)
                for lx in (-2.6, 0.0, 2.6):
                    b_light("AREA", (lx, 2.2, r["h"] - 0.05), 150, size=0.9, color=(1.0, 0.95, 0.88))
                    b_box("lum", (lx - 0.45, 1.75, r["h"] - 0.03), (lx + 0.45, 2.65, r["h"]), (1.0, 0.95, 0.88), 0.5, emit=2.0)
                b_light("AREA", (0, 0.2, r["h"] - 0.3), 120, size=4.0)
            else:
                b_light("AREA", (0, 3.0, 9.0), 4500, size=9.0)
                b_light("SUN", (0, 0, 5), 1.2, rot=(25, 0, 200))
        for shot in scene["shots"]:
            for o in [o for o in bpy.data.objects if o.type == "CAMERA"]:
                bpy.data.objects.remove(o)
            if shot.get("fit"):
                # Distance à laquelle les huit coins de la boîte englobante tiennent dans l'image.
                c = (lo + hi) / 2
                d = Vector(shot["fit"]).normalized()
                right = Vector((0, 0, 1)).cross(d).normalized()
                up = d.cross(right)
                th = math.tan(math.radians(shot["fov"]) / 2)
                tv = th * shot["res"][1] / shot["res"][0]
                dist = 0.1
                for cx in (lo.x, hi.x):
                    for cy in (lo.y, hi.y):
                        for cz in (lo.z, hi.z):
                            v = Vector((cx, cy, cz)) - c
                            dist = max(dist, v.dot(d) + max(abs(v.dot(right)) / th, abs(v.dot(up)) / tv) * shot.get("margin", 1.1))
                b_camera(sc, c + d * dist, c, fov=shot["fov"], res=shot["res"])
            else:
                b_camera(sc, shot["eye"], shot["target"], fov=shot.get("fov"), ortho=shot.get("ortho"), res=shot["res"])
            sc.view_settings.exposure = shot.get("exposure", 0.0)
            sc.render.filepath = shot["out"]
            sc.render.image_settings.file_format = "PNG"
            bpy.ops.render.render(write_still=True)
            print("rendu", shot["out"])


# --------------------------------------------------------------------------------------------------------------------
# Hors Blender : export, composition de la salle à partir des tables du constructeur, planches
# --------------------------------------------------------------------------------------------------------------------
MAIN = os.path.join(HERE, "..", "Sources", "addons", "main")


def sqf_table(src, name):
    m = re.search(r"private " + re.escape(name) + r" = (\[.*?\]);\r?\n// ---- FIN", src, re.S)
    return json.loads(m.group(1))


def cfg_classes():
    """classe -> (modèle, mapSize, comspec_tocDesk), héritage suivi (toc_screens3d, toc_screens_legacy, toc_pc, toc_furniture)."""
    src = ""
    for f in ("toc_screens3d.hpp", "toc_screens_legacy.hpp", "toc_pc.hpp", "toc_furniture.hpp"):
        with open(os.path.join(MAIN, f), encoding="utf-8") as fh:
            src += fh.read() + "\n"
    raw = {}
    for m in re.finditer(r"class (\w+)\s*:\s*(\w+)\s*\{", src):
        depth, i = 1, m.end()
        while depth and i < len(src):
            depth += {"{": 1, "}": -1}.get(src[i], 0)
            i += 1
        raw[m.group(1)] = (m.group(2), src[m.end():i - 1])

    def get(cls, key, rx):
        while cls in raw:
            mm = re.search(rx, raw[cls][1])
            if mm:
                return mm.group(1)
            cls = raw[cls][0]
        return ""
    out = {}
    for cls in raw:
        model = get(cls, "model", r'\bmodel="[^"]*\\(\w+)\.p3d"')
        out[cls] = (model, float(get(cls, "mapSize", r"\bmapSize=([\d.]+)") or 0), get(cls, "desk", r'comspec_tocDesk="(\w+)"'))
    return out


def resolve(size, post, has_wall=True, ceil=3.4):
    """Miroir de fn_tocHallBuild « _resolve » et du plan (cap par _facing) : [rôle, classe, x, y, z, cap pour une salle
    de direction 0]. Mobilier du mod présent (cas du jeu avec le mod)."""
    with open(os.path.join(MAIN, "functions", "toc", "fn_tocHallBuild.sqf"), encoding="utf-8") as f:
        src = f.read()
    rooms, k = sqf_table(src, "_rooms"), dict(sqf_table(src, "_k"))
    furn = dict(sqf_table(src, "_furniture"))
    facing = {r[0]: r[1] for r in sqf_table(src, "_facing")}
    plan, props = dict(sqf_table(src, "_deskPlan")), sqf_table(src, "_deskProps")
    cfg = cfg_classes()
    _, wall_y, seats, entries = next(r for r in rooms if r[0] == size)
    rows = []
    for role, cls, x0, y0, mount, view, name, extra, alt, ax, ay, av in entries:
        ok = has_wall if mount == "wall" else (k["ceilMin"] <= ceil <= k["ceilMax"]) if mount == "ceiling" else True
        if ok:
            rows.append(["screen", cls, x0, y0, ceil if mount == "ceiling" else 0.0, facing["screen"]])
        elif alt:
            rows.append(["screen", alt, ax, ay, 0.0, facing["screen"]])
    desk, top = furn["desk"][0]
    chair = furn["chair"][0][0]
    fixed = bool(post)
    pc = post or "COMSPEC_ATAK_TocLaptop"
    pc_w = cfg[pc][1] + 0.1 if fixed else 0
    arc = cfg[pc][2]
    if arc == desk or arc not in cfg:
        arc = ""
    step = max(k["arcStep"], pc_w) if arc else k["seat"]
    if not arc:
        for cls, xu in plan[seats]:
            rows.append(["desk", cls, xu * step, k["deskY"], 0.0, facing["desk"]])
    for i in range(seats):
        sx = -((seats - 1) * step) / 2 + i * step
        if arc:
            rows.append(["desk", arc, sx, k["deskY"], 0.0, facing["desk"]])
        rows.append(["chair", chair, sx, k["chairY"], 0.0, facing["chair"]])
        rows.append(["pc", pc, sx, k["pcY"] if fixed else k["laptopY"], top, facing["pc"]])
        for pcls, px, py, pd in props[i % len(props)]:
            rows.append(["prop", pcls, sx - px, k["deskY"] - py, top, facing["prop"] + pd])
    return rows, wall_y, cfg


def driver():
    import numpy as np
    from PIL import Image, ImageDraw
    sys.path.insert(0, HERE)
    import model_toc_screens as T
    import model_toc_furniture as F
    import model_toc_pc as PC
    import model_toc_legacy as LG

    out = os.path.abspath(sys.argv[1])
    os.makedirs(out, exist_ok=True)
    work = os.environ.get("WORK") or tempfile.mkdtemp(prefix="toc_furn_render_")
    os.makedirs(work, exist_ok=True)
    blender = os.environ.get("BLENDER") or shutil.which("blender")
    if not blender:
        sys.exit("Blender introuvable : BLENDER=chemin de blender.exe")
    only = [x for x in os.environ.get("ONLY", "").split(",") if x]
    W, O = work.replace("\\", "/"), out.replace("\\", "/")
    T.textures(work)
    F.textures(work)
    LG  # noqa: B018 (matières du PC portable enregistrées à l'import)

    def content(seed, wide=False):
        p = "%s/_content_%d%s.png" % (W, seed, "w" if wide else "")
        if not os.path.exists(p):
            Image.fromarray((T.demo_content(seed=seed) * 255).astype(np.uint8)).save(p)
        return p

    exported = {}

    def obj(name):
        """OBJ d'un modèle par son nom de fichier (mobilier, poste PC, écran)."""
        if name in exported:
            return exported[name]
        if name in F.MODELS:
            m = F.build_model(name, 0)[0]
        elif name in PC.MODELS:
            m = PC.MODELS[name][0](0)
        elif name in T.MODELS:
            m = T.MODELS[name][0](0)
        else:
            m = LG.MODELS[name][0](0)
        sels = sorted({s for f in m.faces for s in f[4]})
        cont = {s: content(i + (sum(map(ord, name)) % 5)) for i, s in enumerate(sels)}
        path = "%s/%s.obj" % (W, name)
        F.export_obj(m, path, work, cont)
        exported[name] = path
        return path

    VIEW = (0.62, 1.0, 0.52)
    scenes = []
    tiles = []      # (fichier, légende)
    # 1. Chaque modèle seul (aperçu Eden et planche), puis les postes montés.
    for name, info in F.MODELS.items():
        png = "%s/_tile_%s.png" % (W, name)
        tiles.append((png, info[5].replace(" (COMSPEC)", "")))
        scenes.append({"kind": "studio", "objs": [{"obj": obj(name)}], "shots": [{"out": png, "fit": VIEW, "fov": 30, "res": [910, 512]}]})
    for name in PC.MODELS:
        png = "%s/_tile_%s.png" % (W, name)
        scenes.append({"kind": "studio", "objs": [{"obj": obj(name)}], "shots": [{"out": png, "fit": VIEW, "fov": 30, "res": [910, 512]}]})
    combos = [
        ("Console 1 poste, poste 1 écran, fauteuil", [("toc_furn_desk_single", 0, 0, 0, 0), ("toc_pc_1", 0, 0, F.TOP_Z, 0), ("toc_furn_chair", 0, F.CHAIR_Y, 0, 180),
                                                      ("toc_furn_headset", 0.56, 0.21, F.TOP_Z, 25), ("toc_furn_mug", -0.66, 0.02, F.TOP_Z, 40)]),
        ("Console en arc, poste 3 écrans", [("toc_furn_desk_curved", 0, 0, 0, 0), ("toc_pc_3", 0, 0, F.TOP_Z, 0), ("toc_furn_chair", 0, F.CHAIR_Y, 0, 180)]),
        ("Console en arc, écran incurvé", [("toc_furn_desk_curved", 0, 0, 0, 0), ("toc_pc_curved", 0, 0, F.TOP_Z, 0), ("toc_furn_chair", 0, F.CHAIR_Y, 0, 180),
                                           ("toc_furn_handset", 0.6, 0.2, F.TOP_Z, 10)]),
        ("Console 2 postes, postes 2 écrans", [("toc_furn_desk_double", 0, 0, 0, 0), ("toc_pc_2", -0.8, 0, F.TOP_Z, 0), ("toc_pc_2", 0.8, 0, F.TOP_Z, 0),
                                               ("toc_furn_chair", -0.8, F.CHAIR_Y, 0, 180), ("toc_furn_chair", 0.8, F.CHAIR_Y, 0, 180),
                                               ("toc_furn_notepad", -0.8 - 0.63, 0.2, F.TOP_Z, -6)]),
    ]
    combo_tiles = []
    for ci, (label, items) in enumerate(combos):
        png = "%s/_combo_%d.png" % (W, ci)
        combo_tiles.append((png, label))
        scenes.append({"kind": "studio", "samples": 64,
                       "objs": [{"obj": obj(n), "loc": [x, y, z], "heading": h} for n, x, y, z, h in items],
                       "shots": [{"out": png, "fit": VIEW, "fov": 30, "res": [1365, 768], "margin": 1.05}]})
    if only and "planche" not in only:
        scenes = []
    # 2. Salle moyenne montée par les tables du constructeur (postes à deux écrans), direction de la salle : 0 (+Y).
    rows, wall_y, cfg = resolve("M", "COMSPEC_ATAK_TocPC_2")
    room_objs = [{"obj": obj(cfg[cls][0]), "loc": [x, y, z], "heading": h} for role, cls, x, y, z, h in rows]
    room = {"x0": -5.5, "x1": 5.5, "y0": -1.2, "y1": wall_y + 0.02, "h": 3.4}
    if not only or "salle" in only:
        scenes.append({"kind": "room", "ceiling": True, "samples": 96, "room": room, "objs": room_objs, "shots": [
            {"out": O + "/poste_vue_operateur.png", "eye": [1.15, -0.55, 1.78], "target": [-0.1, 6.0, 1.25], "fov": 74, "res": [1920, 1080], "exposure": 0.6}]})
        scenes.append({"kind": "room", "ceiling": False, "samples": 64, "room": room, "objs": room_objs, "shots": [
            {"out": W + "/_top.png", "eye": [0, 2.9, 20], "target": [0, 2.9, 0], "ortho": 13.0, "res": [1820, 1340], "exposure": 0.3}]})
    # 3. Gros plan d'un poste (console droite, deux écrans, accessoires), vu de l'épaule de l'opérateur.
    if not only or "detail" in only:
        det = [("toc_furn_desk_single", 0, 0, 0, 0), ("toc_pc_2", 0, 0, F.TOP_Z, 0), ("toc_furn_chair", 0.05, F.CHAIR_Y + 0.12, 0, 172),
               ("toc_furn_headset", 0.56, 0.21, F.TOP_Z, 25), ("toc_furn_mug", -0.66, 0.02, F.TOP_Z, 40), ("toc_furn_notepad", -0.63, 0.24, F.TOP_Z, -6)]
        scenes.append({"kind": "studio", "samples": 96, "objs": [{"obj": obj(n), "loc": [x, y, z], "heading": h} for n, x, y, z, h in det],
                       "shots": [{"out": O + "/poste_detail.png", "eye": [-0.78, 1.02, 1.42], "target": [0.02, -0.02, 0.84], "fov": 58, "res": [1920, 1080]},
                                 {"out": W + "/_detail_kb.png", "eye": [-0.30, 0.62, 1.08], "target": [-0.10, 0.20, 0.75], "fov": 50, "res": [1280, 720]}]})
    job = "%s/job.json" % W
    with open(job, "w") as f:
        json.dump({"scenes": scenes}, f)
    subprocess.run([blender, "-b", "--python", os.path.abspath(__file__), "--", job], check=True, stdout=subprocess.DEVNULL)

    def font(size):
        try:
            return F.font(size)
        except Exception:
            return None

    # Planche : postes montés en grand, puis chaque modèle.
    if not only or "planche" in only:
        tw, th, pad, cap = 455, 256, 10, 26
        cols = 6
        big_w = (cols * tw + (cols - 1) * pad - pad) // 2
        big_h = int(big_w * 768 / 1365)
        n_rows = math.ceil(len(tiles) / cols)
        Wd = pad + cols * (tw + pad)
        Hd = 70 + 2 * (big_h + cap + pad) + n_rows * (th + cap + pad) + pad
        sheet = Image.new("RGB", (Wd, Hd), (24, 26, 30))
        d = ImageDraw.Draw(sheet)
        d.text((pad, 14), "Mobilier et périphériques de TOC COMSPEC : rendus Blender des modèles du mod (pas des captures du jeu)", fill=(235, 238, 242), font=font(26))
        y = 70
        for i, (png, label) in enumerate(combo_tiles):
            x = pad + (i % 2) * (big_w + pad)
            sheet.paste(Image.open(png).convert("RGB").resize((big_w, big_h), Image.LANCZOS), (x, y))
            d.text((x + 4, y + big_h + 3), label, fill=(210, 214, 220), font=font(17))
            if i % 2 == 1:
                y += big_h + cap + pad
        for i, (png, label) in enumerate(tiles):
            x, yy = pad + (i % cols) * (tw + pad), y + (i // cols) * (th + cap + pad)
            sheet.paste(Image.open(png).convert("RGB").resize((tw, th), Image.LANCZOS), (x, yy))
            d.text((x + 4, yy + th + 3), label, fill=(210, 214, 220), font=font(15))
        sheet.save(os.path.join(out, "bureaux_planche.png"))
        if not os.environ.get("NO_PREVIEW"):
            for name in list(F.MODELS) + list(PC.MODELS):
                Image.open("%s/_tile_%s.png" % (W, name)).convert("RGB").resize((455, 256), Image.LANCZOS).save(
                    os.path.join(T.DATA, name + "_preview.jpg"), quality=88)
    # Plan annoté : 1820 px pour 13 m, centre (0 ; 2,9).
    if not only or "salle" in only:
        top = Image.open(W + "/_top.png").convert("RGB")
        d = ImageDraw.Draw(top)
        s = 1820 / 13.0

        def px(x, y):
            return (910 + x * s, 670 - (y - 2.9) * s)

        def arrow(x0, y0, x1, y1, col, label, lx=8, ly=-10):
            a, b = px(x0, y0), px(x1, y1)
            d.line([a, b], fill=col, width=5)
            ang = math.atan2(b[1] - a[1], b[0] - a[0])
            for da in (2.6, -2.6):
                d.line([b, (b[0] + 18 * math.cos(ang + da), b[1] + 18 * math.sin(ang + da))], fill=col, width=5)
            d.text((b[0] + lx, b[1] + ly), label, fill=col, font=font(20))
        k_chair = next(r for r in rows if r[0] == "chair")
        for role, cls, x, y, z, h in rows:
            if role == "chair":
                arrow(x, y, x + 0.9 * math.sin(math.radians(h)), y + 0.9 * math.cos(math.radians(h)), (255, 196, 60), "")
            if role == "pc":
                arrow(x + 0.45, y, x + 0.45 + 0.6 * math.sin(math.radians(h)), y + 0.6 * math.cos(math.radians(h)), (90, 220, 255), "")
        arrow(0, -0.9, 0, 0.1, (120, 255, 140), "module : direction de la salle = regard des opérateurs", 14, -4)
        d.text((16, 14), "Salle moyenne, vue de dessus (rendu Blender, pas une capture du jeu) : tables de fn_tocHallBuild", fill=(235, 238, 242), font=font(24))
        d.text((16, 48), "jaune : regard des fauteuils (vers le mur d'images)   bleu : face des moniteurs (vers les fauteuils)", fill=(235, 238, 242), font=font(20))
        a = px(-5.3, wall_y)
        d.text((a[0], a[1] + 8), "MUR D'IMAGES (y = %g m)" % wall_y, fill=(255, 255, 255), font=font(22))
        del k_chair
        top.save(os.path.join(out, "salle_vue_dessus.png"))
    if not os.environ.get("WORK"):
        shutil.rmtree(work, ignore_errors=True)
    print("rendus dans", out)


if __name__ == "__main__":
    if IN_BLENDER:
        with open(sys.argv[sys.argv.index("--") + 1]) as fh:
            b_run(json.load(fh))
    else:
        driver()
