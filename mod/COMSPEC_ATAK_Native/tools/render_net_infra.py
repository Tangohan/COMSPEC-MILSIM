"""
Rendus Blender de l'infrastructure réseau (tools/model_net_infra.py) : même moteur que render_relay_blender.py, avec en
plus le verre fumé des portes de baie rendu translucide (comme en jeu : texture alpha) et, en mode « dusk », une salle
sombre (pas de ciel : seuls les voyants et une veilleuse éclairent).

    blender -b --python render_net_infra.py -- travail.json      (même format que render_relay_blender.py)
"""
import os

_src = open(os.path.join(os.path.dirname(os.path.abspath(__file__)), "render_relay_blender.py"), encoding="utf-8").read()
_src = _src[:_src.rindex("\nmain()")]
exec(compile(_src, "render_relay_blender.py", "exec"))

FLIP = bool(os.environ.get("INFRA_ROOM"))   # salle du côté +Y du mur (mise en situation) : éclairage retourné
_make = make_material  # noqa: F821
_world = world  # noqa: F821


def make_material(name, spec, mode):  # noqa: F811
    mat = _make(name, spec, mode)
    if "flat" in spec and "glass" in spec.get("rvmat", ""):
        nt = mat.node_tree
        bsdf = [n for n in nt.nodes if n.type == "BSDF_PRINCIPLED"][0]
        out = [n for n in nt.nodes if n.type == "OUTPUT_MATERIAL"][0]
        mix = nt.nodes.new("ShaderNodeMixShader")
        tr = nt.nodes.new("ShaderNodeBsdfTransparent")
        tr.inputs[0].default_value = (0.55, 0.57, 0.60, 1)
        mix.inputs[0].default_value = 0.16
        nt.links.new(tr.outputs[0], mix.inputs[1])
        nt.links.new(bsdf.outputs[0], mix.inputs[2])
        nt.links.new(mix.outputs[0], out.inputs[0])
    elif "flat" in spec and "glow" in spec.get("rvmat", ""):
        bsdf = [n for n in mat.node_tree.nodes if n.type == "BSDF_PRINCIPLED"][0]
        bsdf.inputs["Emission Strength"].default_value = 26.0 if mode == "dusk" else 9.0
    return mat


def world(mode):  # noqa: F811
    _world(mode)
    if mode == "studio":
        # salle éclairée : plafonniers devant les façades (-Y), appoint latéral
        for loc, energy, size, rx, rz in (((-1.2, -3.2, 3.0), 800, 2.5, 52, -20), ((2.2, -2.6, 2.8), 450, 2.0, 50, 35), ((0.0, -0.2, 3.4), 200, 3.0, 0, 0)):
            if FLIP:
                loc, rx, rz = (loc[0], -loc[1] + 1.0, loc[2]), -rx, -rz
            bpy.ops.object.light_add(type="AREA", location=loc)  # noqa: F821
            lg = bpy.context.object  # noqa: F821
            lg.data.energy = energy
            lg.data.size = size
            lg.data.color = (1.0, 0.98, 0.95)
            lg.rotation_euler = (math.radians(rx), 0, math.radians(rz))  # noqa: F821
        for o in bpy.data.objects:  # noqa: F821
            if o.type == "LIGHT" and o.name.startswith("key"):
                o.data.energy = 0.6
    if mode == "dusk":
        w = bpy.context.scene.world  # noqa: F821
        bg = w.node_tree.nodes["Background"]
        for l_ in list(bg.inputs[0].links):
            w.node_tree.links.remove(l_)
        bg.inputs[0].default_value = (0.02, 0.025, 0.04, 1)
        bg.inputs[1].default_value = 0.6
        for o in bpy.data.objects:  # noqa: F821
            if o.type == "LIGHT" and o.name.startswith("key"):
                o.data.energy = 0.0
        bpy.ops.object.light_add(type="AREA", location=(0.5, 4.0 if FLIP else -3.0, 2.6))  # noqa: F821
        lg = bpy.context.object  # noqa: F821
        lg.data.energy = 18
        lg.data.size = 1.5
        lg.data.color = (0.6, 0.75, 1.0)
        lg.rotation_euler = (math.radians(-55 if FLIP else 55), 0, 0)  # noqa: F821
        bpy.context.scene.view_settings.exposure = 1.6  # noqa: F821


main()  # noqa: F821
