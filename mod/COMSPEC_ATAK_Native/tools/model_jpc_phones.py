"""
Modèles 3D des téléphones portés ouverts sur le JPC (fn_jpcScreen) : un par modèle de terminal du profil
(COMSPEC_ATAK_PhoneModel), le S7 restant le modèle par défaut. La tablette à touches garde son propre modèle
(model_jpc_tablet.py, jpc_tablet.p3d).
Chaque modèle : face et dos en quadrilatères texturés par un atlas à transparence (gen_assets.py <hemtt> jpc :
jpc_<modèle>_ca.paa, face en haut, dos en bas ; la silhouette exacte, fixation comprise, vient de l'alpha), tranches
sur le rectangle du corps, écran (sélection « screen ») sur l'ouverture commune à tous les modèles.
Même repère que la tablette : origine au bas du corps, côté dos (autocenter 0) ; écran vers -Y Blender.
Export MLOD par l'extension Blender « Arma 3 Object Builder » (GPL-3, non livrée) : pip install bpy, puis copier
son dossier Arma3ObjectBuilder dans les add-ons de bpy.

    python3 model_jpc_phones.py            # écrit data/jpc_<modèle>.p3d
"""
import math
import os
import sys

import bpy  # noqa: I001 (bmesh après bpy)
import addon_utils
import bmesh

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
TEX_PREFIX = "z\\comspec_atak_native\\addons\\main\\data\\"
HOLE = (455, 716, 1547, 1334)  # écran commun (bords extérieurs du trou des coques)
# modèle : (rectangle du corps, rectangle recadré dans l'atlas, rayon des coins en pixels) — mêmes valeurs que
# gen_assets.JPC_PHONES (corps mesuré sur l'alpha des rendus, recadrage = boîte englobante avec fixations).
PHONES = {
    "s7": ((270, 630, 1720, 1425), (206, 569, 1901, 1752), 90),
    "rugged": ((310, 625, 1691, 1424), (305, 558, 1697, 1555), 80),
    "iphone": ((393, 654, 1608, 1395), (388, 643, 1614, 1407), 110),
    "plate": ((150, 610, 1897, 1429), (145, 605, 1903, 1645), 40),
    "flip": ((200, 550, 1847, 1453), (195, 543, 1853, 1459), 50),
    "ribbed": ((245, 610, 1802, 1429), (240, 605, 1808, 1435), 70),
}
M_PER_PX = 0.113 / (HOLE[2] - HOLE[0])  # écran 5,1 pouces 16:9 : 11,3 cm de large
DEPTH = 0.016


def rounded(rect, r, seg=6):
    x0, y0, x1, y1 = rect
    pts = []
    for cx, cy, a0 in ((x1 - r, y0 + r, -90), (x1 - r, y1 - r, 0), (x0 + r, y1 - r, 90), (x0 + r, y0 + r, 180)):
        for i in range(seg + 1):
            a = math.radians(a0 + i * 90 / seg)
            pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return pts


def build(name, body, crop, r):
    bx0, by0, bx1, by1 = body
    cxm = (bx0 + bx1) / 2

    def to_model(u, v):
        return ((u - cxm) * M_PER_PX, (by1 - v) * M_PER_PX)

    def uv_front(u, v):
        cx0, cy0, cx1, cy1 = crop
        return ((u - cx0) / (cx1 - cx0), 1 - 0.5 * (v - cy0) / (cy1 - cy0))

    def uv_back(u, v):
        # Dos vu de derrière : le point de la face en u se lit en (x1 - u) dans la moitié basse de l'atlas.
        cx0, cy0, cx1, cy1 = crop
        return ((cx1 - u) / (cx1 - cx0), 0.5 - 0.5 * (v - cy0) / (cy1 - cy0))

    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    cx0, cy0, cx1, cy1 = crop
    quad = [(cx0, cy0), (cx1, cy0), (cx1, cy1), (cx0, cy1)]
    # Face et dos : tout le rectangle recadré, découpé par l'alpha de la texture (fixations, boutons, câble).
    fr = [bm.verts.new((to_model(u, v)[0], -DEPTH, to_model(u, v)[1])) for u, v in quad]
    bk = [bm.verts.new((to_model(u, v)[0], 0.0, to_model(u, v)[1])) for u, v in quad]
    f = bm.faces.new(list(reversed(fr)))
    for loop in f.loops:
        loop[uvl].uv = uv_front(*quad[fr.index(loop.vert)])
    b = bm.faces.new(bk)
    for loop in b.loops:
        loop[uvl].uv = uv_back(*quad[bk.index(loop.vert)])
    # Tranches : contour arrondi du corps, 0,3 mm en retrait des faces (pas de scintillement avec elles).
    pts = rounded(body, r)
    e = 0.0003
    rf = [bm.verts.new((to_model(u, v)[0], -DEPTH + e, to_model(u, v)[1])) for u, v in pts]
    rb = [bm.verts.new((to_model(u, v)[0], -e, to_model(u, v)[1])) for u, v in pts]
    rim = uv_front(bx0 + 30, (by0 + by1) / 2)
    n = len(pts)
    for i in range(n):
        j = (i + 1) % n
        s = bm.faces.new((rf[i], rf[j], rb[j], rb[i]))
        for loop in s.loops:
            loop[uvl].uv = rim
    bm.normal_update()
    for s in bm.faces[2:]:
        # Normales des tranches vers l'extérieur du contour.
        c = s.calc_center_median()
        if s.normal.x * c.x + s.normal.z * (c.z - (by1 - (by0 + by1) / 2) * M_PER_PX) < 0:
            s.normal_flip()
    if f.normal.y > 0:
        f.normal_flip()
    if b.normal.y < 0:
        b.normal_flip()
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    sm = bpy.data.meshes.new(name + "_screen")
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    hx0, hy0, hx1, hy1 = HOLE
    vs = []
    for u, v, tu, tv in ((hx0, hy0, 0, 1), (hx1, hy0, 1, 1), (hx1, hy1, 1, 0), (hx0, hy1, 0, 0)):
        x, z = to_model(u, v)
        vs.append((bm.verts.new((x, -DEPTH - 0.0005, z)), (tu, tv)))
    q = bm.faces.new([v for v, _ in vs])
    for loop in q.loops:
        loop[uvl].uv = dict((v, t) for v, t in vs)[loop.vert]
    bm.normal_update()
    if q.normal.y > 0:
        q.normal_flip()
    bm.to_mesh(sm)
    bm.free()
    so = bpy.data.objects.new(name + "_screen", sm)
    bpy.context.collection.objects.link(so)
    return ob, so


def material(name, tex, rvmat=""):
    m = bpy.data.materials.new(name)
    m.a3ob_properties_material.texture_type = "TEX"
    m.a3ob_properties_material.texture_path = tex
    m.a3ob_properties_material.material_path = rvmat
    return m


def export(model):
    body, crop, r = PHONES[model]
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o)
    ob, so = build("jpc_" + model, body, crop, r)
    ob.data.materials.append(material("camo_" + model, TEX_PREFIX + f"jpc_{model}_ca.paa"))
    so.data.materials.append(material("screen_" + model, TEX_PREFIX + "jpc_screen_static_co.paa", TEX_PREFIX + "jpc_screen.rvmat"))
    for o, sel in ((ob, "camo"), (so, "screen")):
        g = o.vertex_groups.new(name=sel)
        g.add([v.index for v in o.data.vertices], 1.0, "REPLACE")
    bpy.ops.object.select_all(action="DESELECT")
    ob.select_set(True)
    so.select_set(True)
    bpy.context.view_layer.objects.active = ob
    bpy.ops.object.join()
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = "0"
    p.resolution = 1
    prop = p.properties.add()
    prop.name, prop.value = "lodnoshadow", "1"
    gm = bpy.data.meshes.new("geo_" + model)
    gm.from_pydata([(0.0, 0.0, 0.0)], [], [])
    go = bpy.data.objects.new("geometry", gm)
    bpy.context.collection.objects.link(go)
    gp = go.a3ob_properties_object
    gp.is_a3_lod = True
    gp.lod = "6"
    prop = gp.properties.add()
    prop.name, prop.value = "autocenter", "0"
    out = os.path.join(DATA, f"jpc_{model}.p3d")
    print("export", bpy.ops.a3ob.export_p3d(filepath=out), out)


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    bpy.ops.wm.read_homefile(app_template="")
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    for m in (sys.argv[1:] or PHONES):
        export(m)


if __name__ == "__main__":
    main()
