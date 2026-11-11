"""
Rendus de contrôle des modèles « relais » dans Blender (Cycles), à partir des OBJ exportés par les générateurs
(OBJ_DIR=dossier python model_relay_towers.py) : mêmes géométries et mêmes textures que le jeu.

    blender -b --python render_relay_blender.py -- travail.json

travail.json : {"scenes": [{"objs": [{"obj": "x.obj", "loc": [x, y, z], "rot": deg}], "soldier": [x, y] ou null,
                            "ground": true, "wall": null ou {"y": 0, "w": 6, "h": 4},
                            "shots": [{"out": "a.png", "mode": "day" | "dusk" | "studio", "res": [w, h], "az": deg, "el": deg,
                                       "target": [x, y, z] ou null (centre de la boîte), "span": mètres ou null, "fov": deg}]}]}
"""
import json
import math
import os
import sys

import bpy
from mathutils import Vector


def clear():
    bpy.ops.wm.read_factory_settings(use_empty=True)


def setup_cycles(samples=48):
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    try:
        prefs = bpy.context.preferences.addons["cycles"].preferences
        for kind in ("HIPRT", "HIP", "OPTIX", "CUDA"):
            try:
                prefs.compute_device_type = kind if kind != "HIPRT" else "HIP"
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
    sc.cycles.transparent_max_bounces = 16
    sc.render.film_transparent = False
    try:
        sc.view_settings.view_transform = "AgX"
        sc.view_settings.look = "AgX - Punchy"
    except Exception:
        pass


def image(path, non_color=False):
    img = bpy.data.images.load(path, check_existing=True)
    if non_color:
        img.colorspace_settings.name = "Non-Color"
    return img


def make_material(name, spec, mode):
    mat = bpy.data.materials.new(name)
    mat.use_nodes = True
    nt = mat.node_tree
    nt.nodes.clear()
    out = nt.nodes.new("ShaderNodeOutputMaterial")
    bsdf = nt.nodes.new("ShaderNodeBsdfPrincipled")
    nt.links.new(bsdf.outputs[0], out.inputs[0])
    if "flat" in spec:
        r, g, b = spec["flat"]
        bsdf.inputs["Base Color"].default_value = (r, g, b, 1)
        if "glow" in spec.get("rvmat", ""):
            night = mode == "dusk"
            bsdf.inputs["Emission Color"].default_value = (r, g, b, 1)
            bsdf.inputs["Emission Strength"].default_value = 60.0 if night else 6.0
        elif "glass" in spec.get("rvmat", ""):
            bsdf.inputs["Roughness"].default_value = 0.06
            bsdf.inputs["Metallic"].default_value = 0.0
            try:
                bsdf.inputs["Coat Weight"].default_value = 1.0
            except Exception:
                pass
        else:
            bsdf.inputs["Roughness"].default_value = 0.5
        return mat
    if not spec.get("co"):
        bsdf.inputs["Base Color"].default_value = (0.5, 0.5, 0.5, 1)
        return mat
    tex = nt.nodes.new("ShaderNodeTexImage")
    tex.image = image(spec["co"])
    nt.links.new(tex.outputs["Color"], bsdf.inputs["Base Color"])
    if spec.get("alpha"):
        # alpha test (grillage, caillebotis)
        mix = nt.nodes.new("ShaderNodeMixShader")
        tr = nt.nodes.new("ShaderNodeBsdfTransparent")
        gt = nt.nodes.new("ShaderNodeMath")
        gt.operation = "GREATER_THAN"
        gt.inputs[1].default_value = 0.35
        nt.links.new(tex.outputs["Alpha"], gt.inputs[0])
        nt.links.new(gt.outputs[0], mix.inputs[0])
        nt.links.new(tr.outputs[0], mix.inputs[1])
        nt.links.new(bsdf.outputs[0], mix.inputs[2])
        nt.links.new(mix.outputs[0], out.inputs[0])
        bsdf.inputs["Metallic"].default_value = 0.0 if spec.get("foliage") else 0.9
        bsdf.inputs["Roughness"].default_value = 0.85 if spec.get("foliage") else 0.45
        return mat
    if spec.get("rm"):
        rm = nt.nodes.new("ShaderNodeTexImage")
        rm.image = image(spec["rm"], True)
        sep = nt.nodes.new("ShaderNodeSeparateColor")
        nt.links.new(rm.outputs["Color"], sep.inputs[0])
        nt.links.new(sep.outputs[0], bsdf.inputs["Roughness"])
        nt.links.new(sep.outputs[1], bsdf.inputs["Metallic"])
    else:
        bsdf.inputs["Roughness"].default_value = spec.get("rough", 0.55)
        bsdf.inputs["Metallic"].default_value = spec.get("metal", 0.0)
    if spec.get("nohq"):
        nm = nt.nodes.new("ShaderNodeTexImage")
        nm.image = image(spec["nohq"], True)
        nmap = nt.nodes.new("ShaderNodeNormalMap")
        nmap.inputs["Strength"].default_value = 1.0
        nt.links.new(nm.outputs["Color"], nmap.inputs["Color"])
        nt.links.new(nmap.outputs["Normal"], bsdf.inputs["Normal"])
    return mat


def load_obj(path, loc=(0, 0, 0), rot=0.0, mode="day"):
    before = set(bpy.data.objects)
    bpy.ops.wm.obj_import(filepath=path, forward_axis="Y", up_axis="Z")
    objs = [o for o in bpy.data.objects if o not in before]
    spec = {}
    if os.path.exists(path + ".json"):
        with open(path + ".json") as f:
            spec = json.load(f)
    lights = []
    for o in objs:
        for slot in o.material_slots:
            base = slot.material.name.split(".")[0]
            s = spec.get("materials", {}).get(base, {})
            slot.material = make_material(os.path.basename(path) + "_" + base, s, mode)
        o.location = loc
        o.rotation_euler = (0, 0, math.radians(rot))
        try:
            for p in o.data.polygons:
                p.use_smooth = True
        except Exception:
            pass
    c, s_ = math.cos(math.radians(rot)), math.sin(math.radians(rot))
    for name, plist in spec.get("memory", {}).items():
        if name.startswith("light") or name.startswith("ir"):
            for p in plist:
                lights.append((name, (loc[0] + c * p[0] - s_ * p[1], loc[1] + s_ * p[0] + c * p[1], loc[2] + p[2])))
    return objs, lights


def bbox(objs):
    lo, hi = Vector((1e9, 1e9, 1e9)), Vector((-1e9, -1e9, -1e9))
    bpy.context.view_layer.update()
    for o in objs:
        for corner in o.bound_box:
            w = o.matrix_world @ Vector(corner)
            lo = Vector((min(lo.x, w.x), min(lo.y, w.y), min(lo.z, w.z)))
            hi = Vector((max(hi.x, w.x), max(hi.y, w.y), max(hi.z, w.z)))
    return lo, hi


def ground(size=600.0, z=0.0, kind="grass"):
    bpy.ops.mesh.primitive_plane_add(size=size, location=(0, 0, z))
    g = bpy.context.object
    mat = bpy.data.materials.new("ground")
    mat.use_nodes = True
    nt = mat.node_tree
    bsdf = nt.nodes["Principled BSDF"]
    n1 = nt.nodes.new("ShaderNodeTexNoise")
    n1.inputs["Scale"].default_value = 0.35 if kind == "grass" else 2.0
    n1.inputs["Detail"].default_value = 12
    n2 = nt.nodes.new("ShaderNodeTexNoise")
    n2.inputs["Scale"].default_value = 40.0
    n2.inputs["Detail"].default_value = 6
    ramp = nt.nodes.new("ShaderNodeValToRGB")
    if kind == "grass":
        ramp.color_ramp.elements[0].color = (0.035, 0.06, 0.015, 1)
        ramp.color_ramp.elements[1].color = (0.13, 0.10, 0.055, 1)
        ramp.color_ramp.elements[0].position, ramp.color_ramp.elements[1].position = 0.42, 0.68
    elif kind == "roof":
        ramp.color_ramp.elements[0].color = (0.10, 0.10, 0.10, 1)
        ramp.color_ramp.elements[1].color = (0.17, 0.165, 0.16, 1)
    else:
        ramp.color_ramp.elements[0].color = (0.28, 0.28, 0.27, 1)
        ramp.color_ramp.elements[1].color = (0.38, 0.375, 0.36, 1)
    mixn = nt.nodes.new("ShaderNodeMath")
    mixn.operation = "ADD"
    sc = nt.nodes.new("ShaderNodeMath")
    sc.operation = "MULTIPLY"
    sc.inputs[1].default_value = 0.25
    nt.links.new(n2.outputs[0], sc.inputs[0])
    nt.links.new(n1.outputs[0], mixn.inputs[0])
    nt.links.new(sc.outputs[0], mixn.inputs[1])
    nt.links.new(mixn.outputs[0], ramp.inputs[0])
    nt.links.new(ramp.outputs[0], bsdf.inputs["Base Color"])
    bump = nt.nodes.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.6
    nt.links.new(n2.outputs[0], bump.inputs["Height"])
    nt.links.new(bump.outputs[0], bsdf.inputs["Normal"])
    bsdf.inputs["Roughness"].default_value = 0.95 if kind != "floor" else 0.6
    g.data.materials.append(mat)
    return g


def wall(y=0.0, w=6.0, h=4.0, x=0.0, kind="plaster", corner=False):
    """Mur de mise en situation (antennes murales, caméras) : plan Y = y, face visible vers +Y."""
    bpy.ops.mesh.primitive_cube_add(size=1.0, location=(x, y - 0.15, h / 2))
    o = bpy.context.object
    o.scale = (w, 0.3, h)
    mat = bpy.data.materials.new("wall")
    mat.use_nodes = True
    nt = mat.node_tree
    bsdf = nt.nodes["Principled BSDF"]
    n = nt.nodes.new("ShaderNodeTexNoise")
    n.inputs["Scale"].default_value = 3.0
    n.inputs["Detail"].default_value = 10
    ramp = nt.nodes.new("ShaderNodeValToRGB")
    if kind == "plaster":
        ramp.color_ramp.elements[0].color = (0.52, 0.47, 0.38, 1)
        ramp.color_ramp.elements[1].color = (0.66, 0.61, 0.52, 1)
    else:
        ramp.color_ramp.elements[0].color = (0.30, 0.30, 0.29, 1)
        ramp.color_ramp.elements[1].color = (0.42, 0.42, 0.40, 1)
    nt.links.new(n.outputs[0], ramp.inputs[0])
    nt.links.new(ramp.outputs[0], bsdf.inputs["Base Color"])
    bump = nt.nodes.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.25
    n2 = nt.nodes.new("ShaderNodeTexNoise")
    n2.inputs["Scale"].default_value = 90.0
    nt.links.new(n2.outputs[0], bump.inputs["Height"])
    nt.links.new(bump.outputs[0], bsdf.inputs["Normal"])
    bsdf.inputs["Roughness"].default_value = 0.9
    o.data.materials.append(mat)
    return o


def soldier(x, y, z=0.0, h=1.8):
    """Silhouette de 1,80 m pour l'échelle (volumes simples, pas un personnage)."""
    mat = bpy.data.materials.new("soldier")
    mat.use_nodes = True
    mat.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value = (0.11, 0.13, 0.08, 1)
    mat.node_tree.nodes["Principled BSDF"].inputs["Roughness"].default_value = 0.9
    parts = [("cyl", (0.10, 0.0, 0.45), (0.085, 0.085, 0.90)), ("cyl", (-0.10, 0.0, 0.45), (0.085, 0.085, 0.90)),
             ("cube", (0.0, 0.0, 1.18), (0.40, 0.24, 0.60)), ("cyl", (0.26, 0.0, 1.12), (0.06, 0.06, 0.66)),
             ("cyl", (-0.26, 0.0, 1.12), (0.06, 0.06, 0.66)), ("sph", (0.0, 0.0, 1.66), (0.115, 0.125, 0.14)),
             ("cube", (0.0, -0.17, 1.22), (0.30, 0.12, 0.42))]
    out = []
    for kind, loc, sc in parts:
        p = (x + loc[0], y + loc[1], z + loc[2] * h / 1.8)
        if kind == "cyl":
            bpy.ops.mesh.primitive_cylinder_add(radius=1, depth=1, location=p, vertices=16)
        elif kind == "cube":
            bpy.ops.mesh.primitive_cube_add(size=1, location=p)
        else:
            bpy.ops.mesh.primitive_uv_sphere_add(radius=1, location=p, segments=16, ring_count=10)
        o = bpy.context.object
        o.scale = sc
        o.data.materials.append(mat)
        for poly in o.data.polygons:
            poly.use_smooth = kind != "cube"
        out.append(o)
    return out


def world(mode):
    w = bpy.data.worlds.new("w")
    bpy.context.scene.world = w
    w.use_nodes = True
    nt = w.node_tree
    bg = nt.nodes["Background"]
    for o in [o for o in bpy.data.objects if o.type == "LIGHT" and o.name.startswith("key")]:
        bpy.data.objects.remove(o)
    if mode == "studio":
        bg.inputs[0].default_value = (0.62, 0.66, 0.72, 1)
        bg.inputs[1].default_value = 0.9
        elev, az, strength, col = 48, 215, 3.2, (1, 0.97, 0.92)
    elif mode == "dusk":
        # Nuit tombante : ciel bleu profond, reste de lueur froide ; les feux et les voyants font l'image.
        bg.inputs[0].default_value = (0.012, 0.022, 0.06, 1)
        bg.inputs[1].default_value = 1.0
        elev, az, strength, col = 30, 250, 0.12, (0.55, 0.65, 1.0)
    else:
        try:
            sky = nt.nodes.new("ShaderNodeTexSky")
            for t in ("MULTIPLE_SCATTERING", "NISHITA", "HOSEK_WILKIE"):
                try:
                    sky.sky_type = t
                    break
                except Exception:
                    continue
            try:
                sky.sun_disc = False
                sky.sun_elevation = math.radians(42)
                sky.sun_rotation = math.radians(-40)
            except Exception:
                pass
            nt.links.new(sky.outputs[0], bg.inputs[0])
            bg.inputs[1].default_value = 0.28
        except Exception:
            bg.inputs[0].default_value = (0.35, 0.5, 0.8, 1)
            bg.inputs[1].default_value = 1.0
        elev, az, strength, col = 42, 220, 4.0, (1, 0.96, 0.9)
    bpy.ops.object.light_add(type="SUN")
    sun = bpy.context.object
    sun.name = "key_sun"
    sun.data.energy = strength
    sun.data.color = col
    sun.data.angle = math.radians(1.2)
    sun.rotation_euler = (math.radians(90 - elev), 0, math.radians(az))
    bpy.context.scene.view_settings.exposure = 2.2 if mode == "dusk" else 0.0


def camera(target, span, az, el, fov, res):
    sc = bpy.context.scene
    cam = sc.camera
    if cam is None:
        bpy.ops.object.camera_add()
        cam = bpy.context.object
        sc.camera = cam
    sc.render.resolution_x, sc.render.resolution_y = res
    cam.data.sensor_fit = "VERTICAL"
    cam.data.angle_y = math.radians(fov)
    cam.data.clip_start, cam.data.clip_end = 0.02, 5000
    dist = span / (2 * math.tan(math.radians(fov) / 2))
    a, e = math.radians(az), math.radians(el)
    d = Vector((math.cos(e) * math.cos(a), math.cos(e) * math.sin(a), math.sin(e)))
    pos = Vector(target) + d * dist
    pos.z = max(pos.z, 0.25)
    cam.location = pos
    cam.rotation_euler = (Vector(target) - pos).to_track_quat("-Z", "Y").to_euler()


def main():
    argv = sys.argv[sys.argv.index("--") + 1:]
    with open(argv[0]) as f:
        job = json.load(f)
    for scn in job["scenes"]:
        modes = sorted({s.get("mode", "day") for s in scn["shots"]})
        for mode in modes:
            clear()
            setup_cycles(job.get("samples", 48))
            objs, lights = [], []
            for o in scn["objs"]:
                a, l_ = load_obj(o["obj"], o.get("loc", (0, 0, 0)), o.get("rot", 0.0), mode)
                objs += a
                lights += l_
            lo, hi = bbox(objs)
            if scn.get("ground", True):
                ground(z=scn.get("ground_z", 0.0), kind=scn.get("ground_kind", "grass"))
            if scn.get("wall"):
                wv = scn["wall"]
                wall(wv.get("y", 0.0), wv.get("w", 6.0), wv.get("h", 4.0), wv.get("x", 0.0), wv.get("kind", "plaster"))
            if scn.get("pole"):
                pv = scn["pole"]
                bpy.ops.mesh.primitive_cylinder_add(radius=pv.get("r", 0.08), depth=pv.get("h", 6.0), location=(0, 0, pv.get("h", 6.0) / 2), vertices=24)
                po = bpy.context.object
                pm = bpy.data.materials.new("pole")
                pm.use_nodes = True
                pm.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value = (0.25, 0.27, 0.28, 1)
                pm.node_tree.nodes["Principled BSDF"].inputs["Metallic"].default_value = 0.8
                pm.node_tree.nodes["Principled BSDF"].inputs["Roughness"].default_value = 0.5
                po.data.materials.append(pm)
                for poly in po.data.polygons:
                    poly.use_smooth = True
            for sp in scn.get("soldiers", []):
                soldier(sp[0], sp[1], sp[2] if len(sp) > 2 else 0.0)
            world(mode)
            if mode == "dusk":
                for name, p in lights:
                    bpy.ops.object.light_add(type="POINT", location=p)
                    lg = bpy.context.object
                    lg.data.color = (1.0, 0.08, 0.03)
                    lg.data.energy = 900 if "top" in name else 350
                    lg.data.shadow_soft_size = 0.12
            for shot in scn["shots"]:
                if shot.get("mode", "day") != mode:
                    continue
                c = (lo + hi) / 2
                size = hi - lo
                target = shot.get("target") or (c.x, c.y, c.z)
                res = shot.get("res", (900, 1200))
                span = shot.get("span") or max(size.z, max(size.x, size.y) * res[1] / res[0] * 0.8) * 1.12
                camera(target, span, shot.get("az", -60), shot.get("el", 8), shot.get("fov", 30), res)
                bpy.context.scene.render.filepath = shot["out"]
                bpy.context.scene.render.image_settings.file_format = "PNG"
                bpy.ops.render.render(write_still=True)
                print("RENDU", shot["out"])


main()
