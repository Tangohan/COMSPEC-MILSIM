"""
Grenade assourdissante multi-détonations COMSPEC (mod COMSPEC Flashbang) : modèles MLOD, icônes, logo, aperçus.

  fb_<V>.p3d        grenade complète (levier, goupille, anneau) : inventaire, au sol, en main
  fb_<V>_spent.p3d  grenade sans levier ni goupille : projectile en vol et engin au sol pendant les détonations
                    V = 1..9 (nombre de détonations) et drill (grenade d'exercice bleue)

Référence réelle : Rheinmetall « 6 Bang » (corps acier Ø40 x 112 mm, 563 g, évents de fond, version
d'entraînement bleue) et Primetake PT4523C (Ø40 mm, 115 mm, 600 g). Modèle original (aucune pièce reprise).
Dimensions retenues : corps Ø40 mm x 86 mm, fusée 28 mm, longueur totale 114 mm, masse 0,56 kg.

Repère : axe du cylindre selon +Y (fusée vers +Y, culot vers -Y), levier côté +Z, goupille et anneau côté +X ;
origine au centre de la grenade (le projectile tourne autour de son centre, l'objet posé repose couché sur le
flanc, levier vers le haut). Corps déroulé en UV cylindriques (gen_fb_textures.py) : marquages sur les flancs ±X.

LODs : visuels 1 (40 segments, goupille et anneau), 2 (20 segments), 4 (10 segments, sans goupille ni anneau) ;
ombre 0 (enveloppe convexe fermée) ; géométrie (enveloppe convexe, 0,56 kg, autocenter=0) ; Geometry PhysX
(même enveloppe : l'engin au sol est un ThingX) ; géométrie de tir. Sélection retexturable « camo » (corps).

    python gen_fb_textures.py
    blender -b -P model_flashbang.py            (ONLY=nom ; NO_EXPORT=1 ; NO_RENDER=1 ; RENDER_SAMPLES=n ;
                                                 WORKSHOP=1 : seulement les rendus de la page Workshop)
Extension Blender « Arma 3 Object Builder » requise (comme mod/COMSPEC_ATAK_Native/tools/model_items.py).
Les rendus (icônes, logo, aperçu Steam) sont écrits dans tools/render/ ; conversion PAA : voir README du mod.
"""
import math
import os
import sys

import bpy  # noqa: I001 (bmesh et mathutils après bpy)
import addon_utils
import bmesh
from mathutils import Matrix, Vector

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")
TEX = "z\\comspec_flashbang\\addons\\main\\data\\"
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "96"))
VARIANTS = [str(i) for i in range(1, 10)] + ["drill"]
MASS = 0.56

R = 0.020            # rayon du corps
Y0 = -0.057          # culot
HB = 0.086           # hauteur du corps
YT = Y0 + HB         # haut du corps (0,029)
SMOOTH_ANGLE = math.radians(38)

# Disposition de fb_body_<V>_co (voir gen_fb_textures.py), en UV Blender (v = 0 en bas de l'image).
SIDE_V0, SIDE_V1 = 1 - 639 / 1024, 1.0
BOTTOM_UV, BOTTOM_RUV = (192 / 1024, 1 - 832 / 1024), 176 / 1024
TOP_UV = (704 / 1024, 1 - 832 / 1024)
# Atlas fb_metal_co (2 x 2 pastilles de 256 px).
SWATCH = {"fuze": (0.25, 0.75), "lever": (0.75, 0.75), "steel": (0.25, 0.25), "black": (0.75, 0.25)}
SW_HALF = 0.25 * 0.9


class Part:
    def __init__(self, verts, faces, mat, uvs, groups=()):
        self.verts, self.faces, self.mat, self.uvs, self.groups = verts, faces, mat, uvs, set(groups)


# ------------------------------------------------------------------------------------------------- géométrie
def ring_pts(c, ax, u, v, r, n):
    return [c + (u * math.cos(2 * math.pi * i / n) + v * math.sin(2 * math.pi * i / n)) * max(r, 1e-5) for i in range(n)]


def body_part(n):
    """Corps : profil de révolution (fond embouti, chanfrein, flanc, bourrelet, couronne) avec UV cylindriques."""
    ax, u, v = Vector((0, 1, 0)), Vector((0, 0, 1)), Vector((1, 0, 0))
    # (rayon, hauteur depuis le culot, zone UV)
    prof = [(0.0, 0.0012, "bot"), (0.0178, 0.0012, "bot"), (0.0192, 0.0, "side"), (0.0200, 0.0014, "side"),
            (0.0200, HB - 0.0014, "side"), (0.0192, HB, "side"), (0.0168, HB, "top"), (0.0, HB, "top")]
    verts, faces, uvs = [], [], []
    for r, h, _z in prof:
        verts += ring_pts(Vector((0, Y0 + h, 0)), ax, u, v, r, n)

    def polar(k, i, zone):
        """Fond (disque du culot) ou couronne : projection polaire dans la zone de la texture."""
        a = 2 * math.pi * i / n
        if zone == "bot":
            f = prof[k][0] / 0.0192 * BOTTOM_RUV
            return (BOTTOM_UV[0] + f * math.sin(a), BOTTOM_UV[1] + f * math.cos(a))
        f = prof[k][0] / 0.0192 * 0.15
        return (TOP_UV[0] + f * math.sin(a), TOP_UV[1] + f * math.cos(a))

    for k in range(len(prof) - 1):
        o = k * n
        z0, z1 = prof[k][2], prof[k + 1][2]
        for i in range(n):
            j = i + 1
            faces.append((o + i, o + j % n, o + n + j % n, o + n + i))
            if z0 == "side" and z1 == "side":
                # Flanc : u de i/n à (i+1)/n sans repli à la couture ; v selon la hauteur de chaque anneau.
                v0 = SIDE_V0 + (prof[k][1] / HB) * (SIDE_V1 - SIDE_V0)
                v1 = SIDE_V0 + (prof[k + 1][1] / HB) * (SIDE_V1 - SIDE_V0)
                uvs.append([(i / n, v0), (j / n, v0), (j / n, v1), (i / n, v1)])
            else:
                # Chanfrein du culot ou couronne : rattachés au disque correspondant.
                zone = "bot" if "bot" in (z0, z1) else "top"
                uvs.append([polar(k, i, zone), polar(k, j, zone), polar(k + 1, j, zone), polar(k + 1, i, zone)])
    return Part(verts, faces, "body", uvs, ("camo",))


def swatch_uv(name, a, b):
    cu, cv = SWATCH[name]
    return (cu + (a - 0.5) * 2 * SW_HALF, cv + (b - 0.5) * 2 * SW_HALF)


def lathe_metal(base, axis, prof, n, sw, ref=None):
    """Révolution d'un profil [(rayon, hauteur)] (pièce de la tête) ; UV cylindriques dans la pastille sw."""
    base, ax = Vector(base), Vector(axis).normalized()
    u = Vector(ref).normalized() if ref else ax.orthogonal().normalized()
    u = (u - ax * ax.dot(u)).normalized()
    v = ax.cross(u)
    verts, faces, uvs = [], [], []
    for r, h in prof:
        verts += ring_pts(base + ax * h, ax, u, v, r, n)
    m = len(prof)
    for k in range(m - 1):
        o = k * n
        for i in range(n):
            j = i + 1
            faces.append((o + i, o + j % n, o + n + j % n, o + n + i))
            uvs.append([swatch_uv(sw, i / n, k / (m - 1)), swatch_uv(sw, j / n, k / (m - 1)),
                        swatch_uv(sw, j / n, (k + 1) / (m - 1)), swatch_uv(sw, i / n, (k + 1) / (m - 1))])
    # Bouchons plats aux extrémités (si le rayon n'est pas nul).
    for k, rev in ((0, True), (m - 1, False)):
        if prof[k][0] > 1e-4:
            idx = list(range(k * n, k * n + n))
            faces.append(tuple(reversed(idx)) if rev else tuple(idx))
            uvs.append([swatch_uv(sw, 0.5 + 0.45 * math.cos(2 * math.pi * i / n), 0.5 + 0.45 * math.sin(2 * math.pi * i / n))
                        for i in (reversed(range(n)) if rev else range(n))])
    return Part(verts, faces, "metal", uvs)


def rbox(c, size, r, sw, seg=3, rot=None):
    """Pavé à arêtes verticales arrondies (rayon r) ; size = (x, y, z), arrondi dans le plan XZ, hauteur selon Y."""
    c = Vector(c)
    rot = rot or Matrix.Identity(3)
    sx, sy, sz = (s / 2 for s in size)
    pts = []
    if r <= 1e-5:
        pts = [(sx, -sz), (sx, sz), (-sx, sz), (-sx, -sz)]
    else:
        for cx, cz, a0 in ((sx - r, -sz + r, -90), (sx - r, sz - r, 0), (-sx + r, sz - r, 90), (-sx + r, -sz + r, 180)):
            for i in range(seg + 1):
                a = math.radians(a0 + 90 * i / seg)
                pts.append((cx + r * math.cos(a), cz + r * math.sin(a)))
    n = len(pts)
    verts = [c + rot @ Vector((x, y, z)) for y in (-sy, sy) for x, z in pts]
    faces, uvs = [], []
    for i in range(n):
        j = (i + 1) % n
        faces.append((i, j, n + j, n + i))
        uvs.append([swatch_uv(sw, i / n, 0), swatch_uv(sw, (i + 1) / n, 0), swatch_uv(sw, (i + 1) / n, 1), swatch_uv(sw, i / n, 1)])
    for rev, o in ((True, 0), (False, n)):
        idx = [o + i for i in range(n)]
        faces.append(tuple(reversed(idx)) if rev else tuple(idx))
        q = [swatch_uv(sw, 0.5 + x / (2.2 * sx), 0.5 + z / (2.2 * sz)) for x, z in pts]
        uvs.append(list(reversed(q)) if rev else q)
    return Part(verts, faces, "metal", uvs)


def strip(path, width, thick, sw):
    """Bande plate (levier) le long d'une polyligne du plan YZ ; largeur selon X."""
    path = [Vector(p) for p in path]
    wdir = Vector((1, 0, 0))
    rings = []
    for i, p in enumerate(path):
        if i == 0:
            t = path[1] - path[0]
        elif i == len(path) - 1:
            t = path[i] - path[i - 1]
        else:
            t = path[i + 1] - path[i - 1]
        t.normalize()
        nrm = t.cross(wdir).normalized()
        w, h = width / 2, thick / 2
        rings.append([p - wdir * w - nrm * h, p + wdir * w - nrm * h, p + wdir * w + nrm * h, p - wdir * w + nrm * h])
    verts = [v for r in rings for v in r]
    faces, uvs = [], []
    m = len(rings)
    for k in range(m - 1):
        o = k * 4
        for i in range(4):
            j = (i + 1) % 4
            faces.append((o + i, o + j, o + 4 + j, o + 4 + i))
            uvs.append([swatch_uv(sw, i / 4, k / (m - 1)), swatch_uv(sw, (i + 1) / 4, k / (m - 1)),
                        swatch_uv(sw, (i + 1) / 4, (k + 1) / (m - 1)), swatch_uv(sw, i / 4, (k + 1) / (m - 1))])
    faces.append((3, 2, 1, 0))
    uvs.append([swatch_uv(sw, 0, 0), swatch_uv(sw, 1, 0), swatch_uv(sw, 1, 0.05), swatch_uv(sw, 0, 0.05)])
    o = (m - 1) * 4
    faces.append((o, o + 1, o + 2, o + 3))
    uvs.append([swatch_uv(sw, 0, 0.95), swatch_uv(sw, 1, 0.95), swatch_uv(sw, 1, 1), swatch_uv(sw, 0, 1)])
    return Part(verts, faces, "metal", uvs)


def torus(c, nrm, R_, r, n, m, sw):
    """Anneau de goupille : tore de rayon R_, section r, dans le plan normal à nrm."""
    c, nrm = Vector(c), Vector(nrm).normalized()
    u = nrm.orthogonal().normalized()
    v = nrm.cross(u)
    verts, faces, uvs = [], [], []
    for i in range(n):
        a = 2 * math.pi * i / n
        d = u * math.cos(a) + v * math.sin(a)
        for k in range(m):
            b = 2 * math.pi * k / m
            verts.append(c + d * (R_ + r * math.cos(b)) + nrm * (r * math.sin(b)))
    for i in range(n):
        for k in range(m):
            i1, k1 = (i + 1) % n, (k + 1) % m
            faces.append((i * m + k, i1 * m + k, i1 * m + k1, i * m + k1))
            uvs.append([swatch_uv(sw, i / n, k / m), swatch_uv(sw, (i + 1) / n, k / m),
                        swatch_uv(sw, (i + 1) / n, (k + 1) / m), swatch_uv(sw, i / n, (k + 1) / m)])
    return Part(verts, faces, "metal", uvs)


YROT = Matrix(((1, 0, 0), (0, 0, 1), (0, 1, 0)))   # pavé : hauteur locale Z -> axe Y du modèle


def build(lod=1, spent=False):
    """Pièces de la grenade pour la LOD visuelle lod (1, 2 ou 4)."""
    n = {1: 40, 2: 20, 4: 10}[lod]
    parts = [body_part(n)]
    nf = max(8, n // 2)
    # Bague filetée de la fusée (moletée), sertie dans la couronne du corps.
    parts.append(lathe_metal((0, YT - 0.001, 0), (0, 1, 0), [(0.0150, 0.0), (0.0165, 0.001), (0.0165, 0.0075),
                                                             (0.0150, 0.0088)], nf, "fuze", (0, 0, 1)))
    # Corps de fusée : bloc arrondi, oreilles de pivot du levier côté +Z.
    parts.append(rbox((0, YT + 0.0170, -0.0010), (0.0240, 0.0170, 0.0230), 0.004 if lod < 4 else 0.0, "fuze", 3 if lod == 1 else 1, YROT))
    # Percuteur : capuchon noir au sommet.
    parts.append(lathe_metal((0, YT + 0.0255, -0.002), (0, 1, 0), [(0.0065, 0.0), (0.0065, 0.0025), (0.0045, 0.0042), (0.0, 0.0045)],
                             max(6, n // 3), "black", (0, 0, 1)))
    if lod < 4:
        for x in (-0.0072, 0.0072):
            parts.append(rbox((x, YT + 0.0200, 0.0120), (0.0016, 0.0090, 0.0060), 0.0, "fuze", 1, YROT))
    if spent:
        # Levier parti : seul l'axe du pivot reste entre les oreilles.
        if lod < 4:
            parts.append(lathe_metal((-0.0080, YT + 0.0225, 0.0130), (1, 0, 0), [(0.0010, 0.0), (0.0010, 0.016)], 6, "steel"))
        return parts
    # Levier (cuillère) : part du pivot, passe sur la tête puis longe le corps côté +Z, lèvre en bout.
    lever = [(0, YT + 0.0225, 0.0130), (0, YT + 0.0285, 0.0150), (0, YT + 0.0300, 0.0185), (0, YT + 0.0285, 0.0218),
             (0, YT + 0.0220, 0.0228), (0, YT + 0.0090, 0.0226), (0, YT + 0.0010, 0.0214), (0, YT - 0.0060, 0.0210),
             (0, YT - 0.0580, 0.0210), (0, YT - 0.0640, 0.0222)]
    if lod == 4:
        lever = lever[::3] + [lever[-1]]
    parts.append(strip(lever, 0.0125, 0.0013, "lever"))
    if lod < 4:
        # Goupille à travers la tête (axe X), anneau rabattu vers le bas côté +X.
        py, pz = YT + 0.0180, 0.0040
        parts.append(lathe_metal((-0.0130, py, pz), (1, 0, 0), [(0.0011, 0.0), (0.0011, 0.0268)], 8 if lod == 1 else 5, "steel"))
        d = Vector((0.6, -0.8, 0)).normalized()
        c = Vector((0.0138, py, pz)) + d * 0.0105
        nrm = d.cross(Vector((0, 0, 1)))
        parts.append(torus(c, nrm, 0.0105, 0.0011, 24 if lod == 1 else 12, 8 if lod == 1 else 5, "steel"))
    return parts


def all_points(parts):
    return [v for p in parts for v in p.verts]


# ------------------------------------------------------------------------------------------------- export
def make_mesh(name, parts, mats, groups=True):
    """mats : {"body": matière, "metal": matière}. Normales lissées par angle (arêtes vives > 38°)."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    dl = bm.verts.layers.deform.verify()
    keys = list(mats)
    gnames = sorted({g for p in parts for g in p.groups}) if groups else []
    for p in parts:
        bv = [bm.verts.new(v) for v in p.verts]
        for v in bv:
            for g in p.groups:
                if g in gnames:
                    v[dl][gnames.index(g)] = 1.0
        for fi, f in enumerate(p.faces):
            try:
                face = bm.faces.new([bv[i] for i in f])
            except ValueError:
                continue
            face.material_index = keys.index(p.mat)
            face.smooth = True
            for loop, uv in zip(face.loops, p.uvs[fi]):
                loop[uvl].uv = uv
    # Soude les pointes de révolution (rayon nul) : triangles au lieu de quads dégénérés.
    bmesh.ops.remove_doubles(bm, verts=[v for v in bm.verts], dist=3e-5)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    for e in bm.edges:
        e.smooth = len(e.link_faces) == 2 and e.calc_face_angle(math.pi) < SMOOTH_ANGLE
    bmesh.ops.triangulate(bm, faces=[f for f in bm.faces if len(f.verts) > 4], ngon_method="EAR_CLIP")
    bm.to_mesh(me)
    bm.free()
    for k in keys:
        me.materials.append(mats[k])
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    for g in gnames:
        ob.vertex_groups.new(name=g)
    return ob


def hull_mesh(name, pts, inset=0.0):
    """Enveloppe convexe fermée (ombre, géométries)."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    for v in pts:
        bm.verts.new(v)
    bmesh.ops.convex_hull(bm, input=bm.verts[:], use_existing_faces=False)
    for v in [v for v in bm.verts if not v.link_faces]:
        bm.verts.remove(v)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.normal_update()
    if inset > 0:
        moves = [v.normal.copy() * -inset for v in bm.verts]
        for v, m in zip(bm.verts, moves):
            v.co += m
    bmesh.ops.triangulate(bm, faces=bm.faces)
    bm.to_mesh(me)
    bm.free()
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    return ob


def set_lod(ob, lod, res=0, props=()):
    p = ob.a3ob_properties_object
    p.is_a3_lod = True
    p.lod = str(lod)
    p.resolution = res
    for k, v in props:
        it = p.properties.add()
        it.name, it.value = k, v
    return ob


def clear_scene():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)
    for me in list(bpy.data.meshes):
        bpy.data.meshes.remove(me)


def a3_materials(variant):
    out = {}
    for k, tex, rv in (("body", f"fb_body_{variant}_co.paa", "fb_body"), ("metal", "fb_metal_co.paa", "fb_metal")):
        m = bpy.data.materials.new(f"a3_{k}_{variant}")
        p = m.a3ob_properties_material
        p.texture_type = "TEX"
        p.texture_path = TEX + tex
        p.material_path = TEX + rv + ".rvmat"
        out[k] = m
    return out


def export_model(variant, spent):
    name = f"fb_{variant}" + ("_spent" if spent else "")
    clear_scene()
    mats = a3_materials(variant)
    counts = []
    vis = None
    for lod in (1, 2, 4):
        ob = set_lod(make_mesh(f"{name}_lod{lod}", build(lod, spent), mats), 0, lod)
        counts.append(len(ob.data.polygons))
        vis = vis or ob
    # Enveloppe : corps + tête (le levier, la goupille et l'anneau sont trop fins pour l'ombre et les collisions).
    core = all_points(build(4, True))
    sh = set_lod(hull_mesh(name + "_shadow", core, 0.0003), 4, 0)
    geo = hull_mesh(name + "_geo", core)
    layer = geo.data.attributes.new("a3ob_mass", "FLOAT", "POINT")
    for i in range(len(geo.data.vertices)):
        layer.data[i].value = MASS / len(geo.data.vertices)
    set_lod(geo, 6, 0, [("autocenter", "0")])
    set_lod(hull_mesh(name + "_physx", core), 8, 0)
    set_lod(hull_mesh(name + "_fire", core), 15, 0)
    out = os.path.join(DATA, name + ".p3d")
    bpy.context.view_layer.objects.active = vis
    res = bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    print("export", res, out, "faces LOD 1/2/4 :", counts, "ombre", len(sh.data.polygons), "géométrie", len(geo.data.polygons))


# ------------------------------------------------------------------------------------------------- rendus (Cycles)
def preview_materials(variant):
    out = {}
    for k, co, nq, sm in (("body", f"fb_body_{variant}_co", "fb_body_nohq", "fb_body_smdi"),
                          ("metal", "fb_metal_co", "fb_metal_nohq", "fb_metal_smdi")):
        m = bpy.data.materials.new(f"pv_{k}_{variant}")
        m.use_nodes = True
        nt, nd = m.node_tree, m.node_tree.nodes
        b = nd["Principled BSDF"]
        i1 = nd.new("ShaderNodeTexImage")
        i1.image = bpy.data.images.load(os.path.join(RENDER, co + ".png"), check_existing=True)
        i1.interpolation = "Cubic"
        nt.links.new(i1.outputs["Color"], b.inputs["Base Color"])
        i2 = nd.new("ShaderNodeTexImage")
        i2.image = bpy.data.images.load(os.path.join(RENDER, nq + ".png"), check_existing=True)
        i2.image.colorspace_settings.name = "Non-Color"
        nm = nd.new("ShaderNodeNormalMap")
        nm.inputs["Strength"].default_value = 0.8
        nt.links.new(i2.outputs["Color"], nm.inputs["Color"])
        nt.links.new(nm.outputs["Normal"], b.inputs["Normal"])
        i3 = nd.new("ShaderNodeTexImage")
        i3.image = bpy.data.images.load(os.path.join(RENDER, sm + ".png"), check_existing=True)
        i3.image.colorspace_settings.name = "Non-Color"
        sep = nd.new("ShaderNodeSeparateColor")
        nt.links.new(i3.outputs["Color"], sep.inputs["Color"])
        inv = nd.new("ShaderNodeMath")
        inv.operation = "SUBTRACT"
        inv.inputs[0].default_value = 1.0
        nt.links.new(sep.outputs["Blue"], inv.inputs[1])
        nt.links.new(inv.outputs[0], b.inputs["Roughness"])
        b.inputs["Metallic"].default_value = 0.55 if k == "metal" else 0.15
        out[k] = m
    return out


def studio(sc, transparent=True, ground=(0.1, 0.1, 0.1, 1), key=1.0):
    sc.render.engine = "CYCLES"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.look = "AgX - Medium High Contrast"
    sc.view_settings.exposure = -0.2
    world = bpy.data.worlds.new("w")
    sc.world = world
    world.use_nodes = True
    world.node_tree.nodes["Background"].inputs["Color"].default_value = (0.55, 0.6, 0.68, 1)
    world.node_tree.nodes["Background"].inputs["Strength"].default_value = 0.25
    for name, loc, energy, size in (("key", (-0.5, -0.7, 0.9), 22.0 * key, 0.5), ("fill", (0.8, -0.4, 0.4), 6.0, 0.8),
                                    ("rim", (0.2, 0.9, 0.7), 18.0, 0.3)):
        ld = bpy.data.lights.new(name, "AREA")
        ld.energy = energy
        ld.size = size
        lo = bpy.data.objects.new(name, ld)
        lo.location = loc
        lo.rotation_euler = (-Vector(loc)).to_track_quat("-Z", "Y").to_euler()
        sc.collection.objects.link(lo)
    me = bpy.data.meshes.new("ground")
    me.from_pydata([(-40, -40, 0), (40, -40, 0), (40, 40, 0), (-40, 40, 0)], [], [(0, 1, 2, 3)])
    g = bpy.data.objects.new("ground", me)
    sc.collection.objects.link(g)
    if transparent:
        g.is_shadow_catcher = True
        sc.render.film_transparent = True
    else:
        m = bpy.data.materials.new("ground")
        m.use_nodes = True
        m.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value = ground
        m.node_tree.nodes["Principled BSDF"].inputs["Roughness"].default_value = 0.85
        me.materials.append(m)


def place(ob, loc, upright=True, rz=0.0):
    """Grenade debout (axe Y du modèle -> Z) posée sur le sol, tournée de rz autour de la verticale."""
    ob.rotation_euler = (math.radians(90), 0, rz) if upright else (0, 0, rz)
    ob.location = (loc[0], loc[1], (-Y0 if upright else R) + loc[2])


def frame_ortho(sc, obs, res, elev, az, margin):
    bpy.context.view_layer.update()
    vs = [o.matrix_world @ v.co for o in obs for v in o.data.vertices]
    lo = Vector([min(v[i] for v in vs) for i in range(3)])
    hi = Vector([max(v[i] for v in vs) for i in range(3)])
    c = (lo + hi) / 2
    d = Vector((math.sin(math.radians(az)) * math.cos(math.radians(elev)), -math.cos(math.radians(az)) * math.cos(math.radians(elev)),
                math.sin(math.radians(elev))))
    cd = bpy.data.cameras.new("cam")
    cd.type = "ORTHO"
    co = bpy.data.objects.new("cam", cd)
    sc.collection.objects.link(co)
    co.location = c + d * 2.0
    co.rotation_euler = (-d).to_track_quat("-Z", "Y").to_euler()
    bpy.context.view_layer.update()
    inv = co.matrix_world.inverted()
    pts = [inv @ v for v in vs]
    w = max(p.x for p in pts) - min(p.x for p in pts)
    h = max(p.y for p in pts) - min(p.y for p in pts)
    cx = (max(p.x for p in pts) + min(p.x for p in pts)) / 2
    cy = (max(p.y for p in pts) + min(p.y for p in pts)) / 2
    co.location = co.matrix_world @ Vector((cx, cy, 0))
    aspect = res[0] / res[1]
    cd.ortho_scale = max(w, h * aspect) * margin if aspect >= 1 else max(w / aspect, h) * margin
    cd.clip_start, cd.clip_end = 0.001, 10
    sc.camera = co
    sc.render.resolution_x, sc.render.resolution_y = res


def render(path, fmt="PNG"):
    sc = bpy.context.scene
    sc.render.image_settings.file_format = fmt
    if fmt == "PNG":
        sc.render.image_settings.color_mode = "RGBA"
    else:
        sc.render.image_settings.quality = 92
    sc.render.filepath = path
    bpy.ops.render.render(write_still=True)


def render_icons(variants):
    """Icônes d'inventaire : grenade debout de trois quarts (flanc marqué +X et anneau face caméra, levier à gauche),
    rendu 512 px (icon_raw_<V>.png) ; réduction en 256 et badge du chiffre : make_fb_icons.py (Pillow)."""
    for v in variants:
        bpy.ops.wm.read_homefile(use_empty=True)
        sc = bpy.context.scene
        studio(sc, True)
        ob = make_mesh("g", build(1, False), preview_materials(v), groups=False)
        # Rotation : la caméra regarde le flanc +X (chiffre et anneau) ; levier (+Z modèle) vers la gauche.
        place(ob, (0, 0, 0), True, math.radians(-90))
        frame_ortho(sc, [ob], (512, 512), 12.0, -28.0, 1.26)
        render(os.path.join(RENDER, f"icon_raw_{v}.png"))
        print("icône", v)


def render_lineup():
    """Aperçu Steam : les dix variantes debout en rang, éclairage rasant (1024 x 576, fond sombre)."""
    bpy.ops.wm.read_homefile(use_empty=True)
    sc = bpy.context.scene
    studio(sc, False, (0.035, 0.036, 0.034, 1), key=1.4)
    sc.world.node_tree.nodes["Background"].inputs["Strength"].default_value = 0.05
    obs = []
    for i, v in enumerate(VARIANTS):
        ob = make_mesh(f"g{v}", build(1, False), preview_materials(v), groups=False)
        x = (i - 4.5) * 0.052
        place(ob, (x, 0.012 * (i % 2), 0), True, math.radians(-90 + (i % 3 - 1) * 8))
        obs.append(ob)
    cd = bpy.data.cameras.new("cam")
    cd.lens = 50
    co = bpy.data.objects.new("cam", cd)
    sc.collection.objects.link(co)
    co.location = (0.0, -0.80, 0.30)
    co.rotation_euler = (Vector((0, 0, 0.05)) - Vector(co.location)).to_track_quat("-Z", "Y").to_euler()
    sc.camera = co
    sc.render.resolution_x, sc.render.resolution_y = 1024, 576
    render(os.path.join(RENDER, "steam_raw.png"))


def render_workshop():
    """Rendus de la page Workshop (WORKSHOP=1) : rang des dix variantes en 1920 x 1080 sur fond sombre
    (ws_lineup_dark.png) et en 2400 x 760 sur fond transparent avec ombres (ws_lineup_alpha.png). Compressés ensuite
    par make_workshop_gallery.py en render/workshop_lineup_dark.jpg et render/workshop_lineup_alpha.webp."""
    for name, transparent, res, cam, look in (
            ("ws_lineup_dark", False, (1920, 1080), (0.0, -0.86, 0.26), (0, 0, 0.035)),
            ("ws_lineup_alpha", True, (2400, 760), (0.0, -0.80, 0.16), (0, 0, 0.045))):
        bpy.ops.wm.read_homefile(use_empty=True)
        sc = bpy.context.scene
        if transparent:
            studio(sc, True, key=1.2)
        else:
            studio(sc, False, (0.035, 0.036, 0.034, 1), key=1.4)
            sc.world.node_tree.nodes["Background"].inputs["Strength"].default_value = 0.05
        for i, v in enumerate(VARIANTS):
            ob = make_mesh(f"g{v}", build(1, False), preview_materials(v), groups=False)
            place(ob, ((i - 4.5) * 0.052, 0.012 * (i % 2), 0), True, math.radians(-90 + (i % 3 - 1) * 8))
        cd = bpy.data.cameras.new("cam")
        cd.lens = 50
        co = bpy.data.objects.new("cam", cd)
        sc.collection.objects.link(co)
        co.location = cam
        co.rotation_euler = (Vector(look) - Vector(cam)).to_track_quat("-Z", "Y").to_euler()
        sc.camera = co
        sc.render.resolution_x, sc.render.resolution_y = res
        render(os.path.join(RENDER, name + ".png"))


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    if os.environ.get("WORKSHOP") == "1":
        render_workshop()
        return
    only = os.environ.get("ONLY")
    todo = [v for v in VARIANTS if not only or only == v]
    if os.environ.get("NO_EXPORT") != "1":
        os.makedirs(DATA, exist_ok=True)
        for v in todo:
            for spent in (False, True):
                export_model(v, spent)
    if os.environ.get("NO_RENDER") != "1":
        render_icons(todo)
        if not only:
            render_lineup()


if __name__ == "__main__":
    main()
