"""
Téléphone posé au sol (ground_phone.p3d) : le Samsung S7 en coque tactique du gilet et du socle de recharge
(model_vest_phone.build, même texture photo vest_phone_co.paa), couché écran vers le ciel, le dos à z = 0.
Sert de modèle à l'objet COMSPEC_ATAK_Phone (inventaire jeté au sol) et à Item_COMSPEC_ATAK_Phone (éditeur),
à la place de la sacoche générique d'Arma. Sélection camo (coque). Pas de patch : le dos touche le sol.

    python3 model_ground_phone.py          # écrit data/ground_phone.p3d (extension Arma 3 Object Builder, voir model_vest_phone.py)
"""
import math
import os
import sys

import bpy  # noqa: I001
import addon_utils
from mathutils import Matrix

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import model_vest_phone as vp  # noqa: E402


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    bpy.ops.wm.read_homefile(app_template="")
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    ob, po = vp.build()
    bpy.data.objects.remove(po)
    # Face (-Y) vers le haut (+Z), dos posé au sol.
    ob.data.transform(Matrix.Translation((0, 0, vp.DEPTH / 2)) @ Matrix.Rotation(math.radians(-90), 4, "X"))
    ob.data.update()
    ob.data.materials.append(vp.material("camo", vp.TEX_PREFIX + "vest_phone_co.paa"))
    g = ob.vertex_groups.new(name="camo")
    g.add([v.index for v in ob.data.vertices], 1.0, "REPLACE")
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = "0"
    p.resolution = 1
    out = os.path.join(vp.DATA, "ground_phone.p3d")
    bpy.context.view_layer.objects.active = ob
    res = bpy.ops.a3ob.export_p3d(filepath=out)
    print("export", res, out)


if __name__ == "__main__":
    main()
