"""
Modèle 3D du téléphone porté sur le gilet (vest_phone.p3d) et ses textures (atlas face / dos par couleur de coque).
Coque arrondie 16,5 x 9 x 1,8 cm (une LOD, sans ombre ni géométrie : objet simple accroché au joueur), face texturée par la photo du téléphone (écran noirci), dos par le rendu du
panneau arrière (render_3d.py), tranches à la couleur du rebord. Sélections : camo (coque, couleur choisie) et
patch (velcro du dos, patch choisi). Export MLOD par l'extension Blender « Arma 3 Object Builder » (GPL-3, non
livrée) : pip install bpy, puis copier son dossier Arma3ObjectBuilder dans les add-ons de bpy.

    python3 model_vest_phone.py            # écrit data/vest_phone.p3d ; textures vest_phone*_co.paa : gen_assets.vest_phone_atlas
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
# Coque dans la texture 2048 de la face (hors support et prises) ; même rectangle retourné pour le dos.
X0, Y0, X1, Y1 = 270, 630, 1720, 1425
R_PX = 120
M_PER_PX = 0.165 / (X1 - X0)
DEPTH = 0.018
# Patch posé sur le velcro du dos (texture du dos) : 300 x 200 px, au centre d'une texture 512 (lignes 96 à 416).
PATCH = (450, 850, 750, 1050)


def outline(seg=6):
    """Contour arrondi de la coque, en pixels de la face, sens horaire vu de face (repère image : y vers le bas)."""
    pts = []
    for cx, cy, a0 in ((X1 - R_PX, Y0 + R_PX, -90), (X1 - R_PX, Y1 - R_PX, 0), (X0 + R_PX, Y1 - R_PX, 90), (X0 + R_PX, Y0 + R_PX, 180)):
        for i in range(seg + 1):
            a = math.radians(a0 + i * 90 / seg)
            pts.append((cx + R_PX * math.cos(a), cy + R_PX * math.sin(a)))
    return pts


def to_model(u, v):
    """Pixel de la face -> (x, z) Blender en mètres, centré (z vers le haut)."""
    cx, cy = (X0 + X1) / 2, (Y0 + Y1) / 2
    return ((u - cx) * M_PER_PX, (cy - v) * M_PER_PX)


def uv_front(u, v):
    return ((u - X0) / (X1 - X0), 1 - 0.5 * (v - Y0) / (Y1 - Y0))


def uv_back(u, v):
    # Dos : image miroir de la face (render_3d / gen_assets.phone_back), rectangle retourné.
    ub = 2048 - u
    return ((ub - (2048 - X1)) / (X1 - X0), 0.5 - 0.5 * (v - Y0) / (Y1 - Y0))


def build():
    me = bpy.data.meshes.new("vest_phone")
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    pts = outline()
    # Écran vers -Y (face), dos vers +Y.
    fr = [bm.verts.new((*(lambda p: (p[0], -DEPTH / 2, p[1]))(to_model(u, v)),)) for u, v in pts]
    bk = [bm.verts.new((*(lambda p: (p[0], DEPTH / 2, p[1]))(to_model(u, v)),)) for u, v in pts]
    n = len(pts)
    # Face : vue depuis -Y, sens anti-horaire pour Blender (normale vers -Y).
    f = bm.faces.new(list(reversed(fr)))
    for loop in f.loops:
        i = fr.index(loop.vert)
        loop[uvl].uv = uv_front(*pts[i])
    b = bm.faces.new(bk)
    for loop in b.loops:
        i = bk.index(loop.vert)
        loop[uvl].uv = uv_back(*pts[i])
    # Tranches : pixel uni du rebord de la face.
    rim = uv_front(1000, 652)
    for i in range(n):
        j = (i + 1) % n
        s = bm.faces.new((fr[i], fr[j], bk[j], bk[i]))
        for loop in s.loops:
            loop[uvl].uv = rim
    bm.normal_update()
    for face in bm.faces:
        face.normal_update()
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bmesh.ops.triangulate(bm, faces=[f for f in bm.faces if len(f.verts) > 4])
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new("vest_phone", me)
    bpy.context.collection.objects.link(ob)
    # Patch : quadrilatère 0,4 mm devant le dos.
    pm = bpy.data.meshes.new("patch")
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    x0b, y0b, x1b, y1b = PATCH
    # Coins dans la texture du dos -> face : u_face = 2048 - u_dos.
    corners = [(x0b, y0b, 16 / 512, 1 - 96 / 512), (x1b, y0b, 496 / 512, 1 - 96 / 512), (x1b, y1b, 496 / 512, 1 - 416 / 512), (x0b, y1b, 16 / 512, 1 - 416 / 512)]
    vs = []
    for ub, vb, tu, tv in corners:
        x, z = to_model(2048 - ub, vb)
        vs.append((bm.verts.new((x, DEPTH / 2 + 0.0004, z)), (tu, tv)))
    q = bm.faces.new([v for v, _ in vs])
    for loop in q.loops:
        loop[uvl].uv = dict((v, t) for v, t in vs)[loop.vert]
    bm.normal_update()
    if q.normal.y < 0:
        q.normal_flip()
    bm.to_mesh(pm)
    bm.free()
    po = bpy.data.objects.new("patch", pm)
    bpy.context.collection.objects.link(po)
    return ob, po


def material(name, tex):
    m = bpy.data.materials.new(name)
    m.a3ob_properties_material.texture_type = "TEX"
    m.a3ob_properties_material.texture_path = tex
    return m


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    bpy.ops.wm.read_homefile(app_template="")
    for o in list(bpy.data.objects):
        bpy.data.objects.remove(o)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    ob, po = build()
    ob.data.materials.append(material("camo", TEX_PREFIX + "vest_phone_co.paa"))
    po.data.materials.append(material("patch", TEX_PREFIX + "patch_fr.paa"))
    g = ob.vertex_groups.new(name="camo")
    g.add([v.index for v in ob.data.vertices], 1.0, "REPLACE")
    g = po.vertex_groups.new(name="patch")
    g.add([v.index for v in po.data.vertices], 1.0, "REPLACE")
    # Une seule LOD de rendu : les deux objets fusionnés.
    bpy.ops.object.select_all(action="DESELECT")
    ob.select_set(True)
    po.select_set(True)
    bpy.context.view_layer.objects.active = ob
    bpy.ops.object.join()
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = "0"
    p.resolution = 1
    prop = p.properties.add()
    prop.name, prop.value = "lodnoshadow", "1"
    out = os.path.join(DATA, "vest_phone.p3d")
    res = bpy.ops.a3ob.export_p3d(filepath=out)
    print("export", res, out)


if __name__ == "__main__":
    main()
