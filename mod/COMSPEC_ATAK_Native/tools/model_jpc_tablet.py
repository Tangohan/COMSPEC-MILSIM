"""
Modèle 3D de la tablette durcie à touches portée sur le rabat du JPC (jpc_tablet.p3d, fn_jpcScreen).
Corps 23,7 x 12 x 2,2 cm (patte de fixation comprise), une LOD sans ombre ni géométrie (objet simple accroché au
joueur). Face et dos texturés par les rendus de render_phones.py (appareil « keys »), atlas jpc_tablet_co.paa
(gen_assets.jpc_tablet_atlas : face en haut, dos en bas), tranches à la couleur du cadre.
Sélections : camo (coque) et screen (écran : la texture UI vivante de l'ATAK, posée par fn_jpcScreen).
Origine : charnière du rabat, au bas de la tablette côté dos (autocenter 0) ; écran vers -Y Blender (arrière du
modèle dans Arma, vectorDir = dos). Export MLOD par l'extension Blender « Arma 3 Object Builder » (GPL-3, non
livrée) : pip install bpy, puis copier son dossier Arma3ObjectBuilder dans les add-ons de bpy.

    python3 model_jpc_tablet.py            # écrit data/jpc_tablet.p3d
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
# Cadre dans le rendu 2048 de la face (render_phones.keys) ; patte de fixation au-dessus.
X0, Y0, X1, Y1 = 190, 600, 1860, 1440
R_PX = 70
TAB = (900, 512, 1150, 600)
HOLE = (455, 716, 1547, 1334)  # écran (bords extérieurs du trou de render_phones)
# Zone recadrée dans l'atlas (gen_assets.JPC_CROP) : doit rester identique des deux côtés.
CROP = (186, 508, 1864, 1474)
M_PER_PX = 0.155 / (HOLE[2] - HOLE[0])  # écran 7 pouces 16:9 : 15,5 cm de large
DEPTH = 0.022
CX = (X0 + X1) / 2


def outline(seg=6):
    """Contour (pixels de la face, sens horaire vu de face, y vers le bas) : coins arrondis et patte en haut."""
    pts = []
    for cx, cy, a0 in ((X1 - R_PX, Y0 + R_PX, -90), (X1 - R_PX, Y1 - R_PX, 0), (X0 + R_PX, Y1 - R_PX, 90), (X0 + R_PX, Y0 + R_PX, 180)):
        for i in range(seg + 1):
            a = math.radians(a0 + i * 90 / seg)
            pts.append((cx + R_PX * math.cos(a), cy + R_PX * math.sin(a)))
    tx0, ty0, tx1, ty1 = TAB
    pts += [(tx0, ty1), (tx0, ty0), (tx1, ty0), (tx1, ty1)]
    return pts


def to_model(u, v):
    """Pixel de la face -> (x, z) Blender en mètres ; z = 0 au bas du cadre (charnière)."""
    return ((u - CX) * M_PER_PX, (Y1 - v) * M_PER_PX)


def uv_front(u, v):
    cx0, cy0, cx1, cy1 = CROP
    return ((u - cx0) / (cx1 - cx0), 1 - 0.5 * (v - cy0) / (cy1 - cy0))


def uv_back(u, v):
    # Dos : rendu miroir de la face ; le même rectangle, retourné, est recadré dans l'atlas.
    cx0, cy0, cx1, cy1 = CROP
    ub = 2048 - u
    return ((ub - (2048 - cx1)) / (cx1 - cx0), 0.5 - 0.5 * (v - cy0) / (cy1 - cy0))


def build():
    me = bpy.data.meshes.new("jpc_tablet")
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    pts = outline()
    # Face (écran) vers -Y, dos sur le plan y = 0.
    fr = [bm.verts.new((to_model(u, v)[0], -DEPTH, to_model(u, v)[1])) for u, v in pts]
    bk = [bm.verts.new((to_model(u, v)[0], 0.0, to_model(u, v)[1])) for u, v in pts]
    n = len(pts)
    f = bm.faces.new(list(reversed(fr)))
    for loop in f.loops:
        loop[uvl].uv = uv_front(*pts[fr.index(loop.vert)])
    b = bm.faces.new(bk)
    for loop in b.loops:
        loop[uvl].uv = uv_back(*pts[bk.index(loop.vert)])
    rim = uv_front(1000, 1400)
    for i in range(n):
        j = (i + 1) % n
        s = bm.faces.new((fr[i], fr[j], bk[j], bk[i]))
        for loop in s.loops:
            loop[uvl].uv = rim
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bmesh.ops.triangulate(bm, faces=[x for x in bm.faces if len(x.verts) > 4])
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new("jpc_tablet", me)
    bpy.context.collection.objects.link(ob)
    # Écran : quadrilatère 0,5 mm devant la face, UV 0..1 sur toute la texture UI (haut de l'image en haut).
    sm = bpy.data.meshes.new("screen")
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    hx0, hy0, hx1, hy1 = HOLE
    corners = [(hx0, hy0, 0, 1), (hx1, hy0, 1, 1), (hx1, hy1, 1, 0), (hx0, hy1, 0, 0)]
    vs = []
    for u, v, tu, tv in corners:
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
    so = bpy.data.objects.new("screen", sm)
    bpy.context.collection.objects.link(so)
    return ob, so


def material(name, tex, rvmat=""):
    m = bpy.data.materials.new(name)
    m.a3ob_properties_material.texture_type = "TEX"
    m.a3ob_properties_material.texture_path = tex
    m.a3ob_properties_material.material_path = rvmat
    return m


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    bpy.ops.wm.read_homefile(app_template="")
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    ob, so = build()
    ob.data.materials.append(material("camo", TEX_PREFIX + "jpc_tablet_co.paa"))
    so.data.materials.append(material("screen", TEX_PREFIX + "jpc_screen_static_co.paa", TEX_PREFIX + "jpc_screen.rvmat"))
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
    # LOD géométrie réduite à un point (aucune collision avec le porteur) : elle porte autocenter = 0, qui garde
    # l'origine sur la charnière (sinon le moteur recentre le modèle sur sa boîte englobante).
    gm = bpy.data.meshes.new("geo")
    gm.from_pydata([(0.0, 0.0, 0.0)], [], [])
    go = bpy.data.objects.new("geometry", gm)
    bpy.context.collection.objects.link(go)
    gp = go.a3ob_properties_object
    gp.is_a3_lod = True
    gp.lod = "6"
    prop = gp.properties.add()
    prop.name, prop.value = "autocenter", "0"
    out = os.path.join(DATA, "jpc_tablet.p3d")
    res = bpy.ops.a3ob.export_p3d(filepath=out)
    print("export", res, out)


if __name__ == "__main__":
    main()
