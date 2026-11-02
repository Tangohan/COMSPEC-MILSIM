"""
Écrans TOC, vidéoprojecteurs et toiles de projection COMSPEC (modèles MLOD du PBO items), sans Blender :
géométrie simple (pavés, poutres, cylindres) écrite directement au format MLOD (P3DM 28.256), lu tel quel par Arma
(PBO -packonly, comme les autres modèles du mod).

  comspec_screen_tripod   grand écran 16:9 sur trépied, dalle 1,39 x 0,78 m (diagonale 1,6 m), centre à 1,55 m
  comspec_screen_desk     écran de table 16:9 (diagonale 1,2 m) sur pied plat, origine sous le pied
  comspec_screen_wall     écran mural 16:9 (diagonale 1,2 m), dos plat (y = 0), origine au bas du dos
  comspec_screen_dual     deux écrans 16:9 (diagonale 1,2 m) côte à côte sur trépied, même image (recopie)
  comspec_projector       vidéoprojecteur de table 32 x 25 x 10 cm, objectif à l'avant (+Y)
  comspec_projector_ceil  même boîtier sous une potence de plafond (platine 40 cm au-dessus)
  comspec_projscreen      toile de projection 16:9 2,0 x 1,125 m sur trépied (enrouleur en bas, bras en haut)
  comspec_projscreen_wall toile murale 16:9 2,0 x 1,125 m, carter enrouleur en haut, dos y = 0, origine au bas

Chaque modèle : LOD visuelle 1 (une seule surface d'affichage, sélection « screen », UV 0-1 plein cadre ; projecteur :
sélection « lens », la lentille), ombre 0 (pavés fermés), géométrie (une composante convexe par pièce, masse,
autocenter=0), géométrie de vue et de tir (bloquent la vue et les tirs : lineIntersectsSurfaces de fn_projector),
mémoire (screen_c : centre de l'image ; projecteur : objectif et objectif_dir, axe optique).
Textures : couleurs procédurales pour le boîtier, comspec_screen_off_co.paa (écran éteint, logo), toile blanche
(procédurale). Aperçus Eden (data/*_preview.jpg) : petit rendu à facettes (PIL) des mêmes faces.

    python model_screens.py            écrit ../Sources/addons/items/data/comspec_*.p3d, *_preview.jpg,
                                       render/comspec_screen_off_co.png
    puis  hemtt utils paa convert render/comspec_screen_off_co.png ../Sources/addons/items/data/comspec_screen_off_co.paa
          (hemtt ne remplace pas un PAA existant : l'effacer avant)
Repère : X à droite, Y vers l'avant (face de l'écran, objectif), Z vers le haut, sol z = 0 (écrit X, Z, Y dans le P3D).
"""
import math
import os
import struct

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "items", "data")
RENDER = os.path.join(HERE, "render")
TEX = "z\\comspec_atak_native\\addons\\items\\data\\"

# Matières (texture, rvmat) des faces.
MATS = {
    "frame": ("#(argb,8,8,3)color(0.07,0.075,0.08,1,co)", TEX + "comspec_frame.rvmat"),
    "metal": ("#(argb,8,8,3)color(0.32,0.33,0.34,1,co)", TEX + "comspec_frame.rvmat"),
    "rubber": ("#(argb,8,8,3)color(0.03,0.03,0.03,1,co)", TEX + "comspec_frame.rvmat"),
    "case": ("#(argb,8,8,3)color(0.78,0.79,0.8,1,co)", TEX + "comspec_frame.rvmat"),
    "vent": ("#(argb,8,8,3)color(0.02,0.02,0.022,1,co)", TEX + "comspec_frame.rvmat"),
    "screen": (TEX + "comspec_screen_off_co.paa", TEX + "comspec_screen_off.rvmat"),
    "canvas": ("#(argb,8,8,3)color(0.93,0.93,0.91,1,co)", TEX + "comspec_canvas.rvmat"),
    "lens": ("#(argb,8,8,3)color(0.05,0.06,0.08,1,co)", TEX + "comspec_lens.rvmat"),
}
# Couleurs de l'aperçu (RVB 0-1).
PREVIEW = {"frame": (0.1, 0.105, 0.11), "metal": (0.42, 0.43, 0.45), "rubber": (0.05, 0.05, 0.05), "case": (0.8, 0.81, 0.82),
           "vent": (0.03, 0.03, 0.03), "screen": None, "canvas": (0.92, 0.92, 0.9), "lens": (0.12, 0.16, 0.24)}


# ----------------------------------------------------------------------------------------------------------------------
# Géométrie
# ----------------------------------------------------------------------------------------------------------------------
def sub(a, b):
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def add(a, b):
    return (a[0] + b[0], a[1] + b[1], a[2] + b[2])


def mul(a, k):
    return (a[0] * k, a[1] * k, a[2] * k)


def dot(a, b):
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


def cross(a, b):
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def norm(a):
    n = math.sqrt(dot(a, a)) or 1.0
    return (a[0] / n, a[1] / n, a[2] / n)


def face_normal(pts):
    n = (0.0, 0.0, 0.0)
    for i in range(len(pts)):
        n = add(n, cross(pts[i], pts[(i + 1) % len(pts)]))
    return norm(n)


class Part:
    """Pièce fermée et convexe (sauf surfaces seules) : sommets, faces [(indices, matière, uvs, lisse)]."""

    def __init__(self, solid=True, sel=None):
        self.verts, self.faces, self.solid, self.sel = [], [], solid, sel
        self.visual = True

    def center(self):
        n = len(self.verts)
        return tuple(sum(v[i] for v in self.verts) / n for i in range(3))


class Model:
    def __init__(self, name):
        self.name = name
        self.parts = []
        self.memory = {}
        self.mass = 10.0

    def _orient(self, part):
        """Faces tournées vers l'extérieur (pièce convexe) : sens inverse si la normale rentre."""
        c = part.center()
        out = []
        for idx, mat, uvs, smooth in part.faces:
            pts = [part.verts[i] for i in idx]
            fc = tuple(sum(p[k] for p in pts) / len(pts) for k in range(3))
            if dot(face_normal(pts), sub(fc, c)) < 0:
                idx, uvs = list(reversed(idx)), list(reversed(uvs))
            out.append((list(idx), mat, list(uvs), smooth))
        part.faces = out

    def box(self, c, size, mat="frame", axes=None, visual=True):
        """Pavé centré en c, demi-côtés size/2, repère (ax, ay, az) facultatif ; visual=False : ombre et géométries
        seulement (toile : plaque fine derrière la surface affichée)."""
        ax, ay, az = axes or ((1, 0, 0), (0, 1, 0), (0, 0, 1))
        hx, hy, hz = (s / 2 for s in size)
        p = Part()
        for sz in (-1, 1):
            for sy in (-1, 1):
                for sx in (-1, 1):
                    p.verts.append(add(c, add(mul(ax, sx * hx), add(mul(ay, sy * hy), mul(az, sz * hz)))))
        quads = [(0, 1, 3, 2), (4, 6, 7, 5), (0, 4, 5, 1), (2, 3, 7, 6), (0, 2, 6, 4), (1, 5, 7, 3)]
        uv = [(0, 0), (1, 0), (1, 1), (0, 1)]
        for q in quads:
            p.faces.append((q, mat, uv, False))
        self._orient(p)
        p.visual = visual
        self.parts.append(p)
        return p

    def beam(self, p0, p1, w, d=None, mat="metal", up=(0, 0, 1)):
        """Poutre de section w x d entre deux points."""
        d = d or w
        ay = norm(sub(p1, p0))
        ref = up if abs(dot(ay, up)) < 0.95 else (1, 0, 0)
        ax = norm(cross(ay, ref))
        az = cross(ax, ay)
        length = math.sqrt(dot(sub(p1, p0), sub(p1, p0)))
        return self.box(mul(add(p0, p1), 0.5), (w, length, d), mat, (ax, ay, az))

    def cyl(self, p0, p1, r, n=12, mat="metal", cap_mat=None, cap_sel=None):
        """Cylindre lisse ; bouchon avant (côté p1) en matière / sélection à part si demandé."""
        a = norm(sub(p1, p0))
        u = norm(cross(a, (0, 0, 1) if abs(a[2]) < 0.9 else (1, 0, 0)))
        v = cross(a, u)
        p = Part()
        for base in (p0, p1):
            for i in range(n):
                t = 2 * math.pi * i / n
                p.verts.append(add(base, add(mul(u, r * math.cos(t)), mul(v, r * math.sin(t)))))
        for i in range(n):
            j = (i + 1) % n
            p.faces.append(((i, j, n + j, n + i), mat, [(i / n, 1), (j / n, 1), (j / n, 0), (i / n, 0)], True))
        disc = [(0.5 + 0.5 * math.cos(2 * math.pi * i / n), 0.5 + 0.5 * math.sin(2 * math.pi * i / n)) for i in range(n)]
        p.faces.append((tuple(range(n)), mat, disc, False))
        p.faces.append((tuple(range(n, 2 * n)), mat, disc, False))      # bouchon avant (ombre fermée)
        self._orient(p)
        self.parts.append(p)
        if cap_mat:
            # Lentille : disque à part, 0,5 mm devant le bouchon (sélection propre, normale vers l'avant).
            c = Part(solid=False, sel=cap_sel)
            front = add(p1, mul(a, 0.0005))
            for i in range(n):
                t = 2 * math.pi * i / n
                c.verts.append(add(front, add(mul(u, r * 0.86 * math.cos(t)), mul(v, r * 0.86 * math.sin(t)))))
            idx = list(range(n))
            if dot(face_normal(c.verts), a) < 0:
                idx.reverse()
            c.faces.append((tuple(idx), cap_mat, [disc[i] for i in idx], False))
            self.parts.append(c)
        return p

    def surface(self, c, w, h, normal, mat, sel, mirror=False):
        """Surface d'affichage : un quad w x h centré en c, normale horizontale (avant ou arrière), UV 0-1 plein cadre
        (haut de l'image en haut ; mirror : vue de dos, image retournée comme à travers une toile)."""
        # Vu de face (en regardant vers -normal, haut = Z), la droite de l'image est up x normal.
        up = (0, 0, 1)
        right = norm(cross(up, normal))
        hw, hh = w / 2, h / 2
        corners = [add(c, add(mul(right, sx * hw), mul(up, sz * hh))) for sx, sz in ((-1, 1), (1, 1), (1, -1), (-1, -1))]
        uvs = [(0, 0), (1, 0), (1, 1), (0, 1)]
        if mirror:
            uvs = [(1, 0), (0, 0), (0, 1), (1, 1)]
        p = Part(solid=False, sel=sel)
        p.verts = corners
        idx = [0, 1, 2, 3]
        if dot(face_normal(corners), normal) < 0:
            idx.reverse()
            uvs = list(reversed(uvs))
        p.faces.append((tuple(idx), mat, uvs, False))
        self.parts.append(p)
        return p


# ----------------------------------------------------------------------------------------------------------------------
# Modèles
# ----------------------------------------------------------------------------------------------------------------------
def panel(m, cx, cz, w, h, bezel=0.025, depth=0.06, y0=0.0, vesa=True):
    """Écran plat : boîtier noir, dalle « screen » 1 mm devant la face avant (y = y0 + depth/2)."""
    m.box((cx, y0, cz), (w + 2 * bezel, depth, h + 2 * bezel), "frame")
    m.surface((cx, y0 + depth / 2 + 0.001, cz), w, h, (0, 1, 0), "screen", "screen")
    if vesa:
        m.box((cx, y0 - depth / 2 - 0.015, cz), (0.3, 0.03, 0.25), "frame")
    return y0 - depth / 2 - 0.03


def tripod(m, mast_top, y=0.0, spread=0.55, hub=0.62):
    """Mât vertical et trois pieds en tube carré, patins caoutchouc."""
    m.box((0, y, mast_top / 2 + 0.04), (0.05, 0.05, mast_top - 0.08), "metal")
    m.box((0, y, hub), (0.09, 0.09, 0.08), "frame")
    for k in range(3):
        a = math.radians(90 + 120 * k)
        foot = (spread * math.cos(a), y + spread * math.sin(a), 0.02)
        m.beam((0, y, hub), foot, 0.03, mat="metal")
        m.box((foot[0], foot[1], 0.012), (0.07, 0.07, 0.024), "rubber")


def screen_tripod(m):
    w, h = 1.394, 0.784                       # 16:9, diagonale 1,60 m
    cz = 1.55
    back = panel(m, 0, cz, w, h)
    tripod(m, 1.72, y=back - 0.03)
    m.box((0, back - 0.015, cz), (0.12, 0.03, 0.12), "metal")     # rotule VESA sur le mât
    m.memory["screen_c"] = (0, 0.032, cz)
    m.mass = 38


def screen_desk(m):
    w, h = 1.046, 0.588                       # 16:9, diagonale 1,20 m
    cz = 0.11 + h / 2 + 0.02
    back = panel(m, 0, cz, w, h, bezel=0.02, depth=0.05, vesa=False)
    m.box((0, -0.01, 0.06), (0.07, 0.03, 0.12), "frame")           # col
    m.box((0, -0.01, 0.008), (0.42, 0.22, 0.016), "frame")         # pied plat
    m.memory["screen_c"] = (0, 0.027, cz)
    m.mass = 18


def screen_wall(m):
    w, h = 1.046, 0.588
    depth = 0.045
    cz = h / 2 + 0.02
    panel(m, 0, cz, w, h, bezel=0.02, depth=depth, y0=0.03 + depth / 2, vesa=False)
    m.box((0, 0.015, cz), (0.4, 0.03, 0.3), "metal")              # support mural plat
    m.memory["screen_c"] = (0, 0.03 + depth + 0.001, cz)
    m.mass = 16


def screen_dual(m):
    w, h = 1.046, 0.588
    cz = 1.45
    gap = 0.05
    for sx in (-1, 1):
        cx = sx * (w / 2 + 0.02 + gap / 2)
        m.box((cx, 0, cz), (w + 0.04, 0.05, h + 0.04), "frame")
        m.surface((cx, 0.026, cz), w, h, (0, 1, 0), "screen", "screen")
    m.box((0, -0.05, cz), (2 * w + 0.2, 0.04, 0.06), "metal")      # barre transversale
    tripod(m, 1.5, y=-0.09, spread=0.65)
    m.box((0, -0.07, cz), (0.1, 0.04, 0.1), "metal")
    m.memory["screen_c"] = (0, 0.03, cz)
    m.mass = 42


def projector_body(m, z0):
    """Boîtier 32 x 25 x 10 cm posé à z0, objectif à droite de la face avant (+Y)."""
    m.box((0, 0, z0 + 0.055), (0.32, 0.25, 0.09), "case")
    m.box((0, 0, z0 + 0.104), (0.3, 0.23, 0.008), "case")          # capot
    m.box((-0.06, 0.1255, z0 + 0.055), (0.12, 0.002, 0.05), "vent")  # grille avant
    m.box((0.1605, -0.02, z0 + 0.055), (0.002, 0.14, 0.05), "vent")  # grille latérale
    m.box((0, -0.1255, z0 + 0.05), (0.2, 0.002, 0.04), "vent")      # connectique arrière
    for sx in (-1, 1):
        for sy in (-1, 1):
            m.box((sx * 0.13, sy * 0.095, z0 + 0.005), (0.03, 0.03, 0.01), "rubber")
    lens_c = (0.075, 0.125, z0 + 0.055)
    m.cyl(lens_c, add(lens_c, (0, 0.03, 0)), 0.036, 16, "frame", cap_mat="lens", cap_sel="lens")
    m.box((-0.07, 0.03, z0 + 0.109), (0.06, 0.06, 0.003), "frame")  # clavier
    m.memory["objectif"] = add(lens_c, (0, 0.032, 0))
    m.memory["objectif_dir"] = add(lens_c, (0, 1.032, 0))
    m.memory["screen_c"] = (0, 0, z0 + 0.06)


def projector(m):
    projector_body(m, 0.0)
    m.mass = 3.2


def projector_ceil(m):
    projector_body(m, 0.0)
    m.box((0, 0, 0.125), (0.16, 0.12, 0.022), "metal")            # platine du boîtier
    m.box((0, 0, 0.29), (0.04, 0.04, 0.32), "metal")              # potence
    m.box((0, 0, 0.455), (0.22, 0.22, 0.012), "metal")            # platine de plafond
    m.mass = 5.5


def canvas(m, cz, y):
    w, h = 2.0, 1.125                         # 16:9
    m.surface((0, y + 0.002, cz), w, h, (0, 1, 0), "canvas", "screen")
    # Dos de la toile : même sélection, image vue à travers (retournée).
    m.surface((0, y - 0.002, cz), w, h, (0, -1, 0), "canvas", "screen", mirror=True)
    # Ombre, collisions, vue et tirs : plaque de 8 mm juste derrière la toile (hors LOD visuelle).
    m.box((0, y - 0.009, cz), (w, 0.008, h), "canvas", visual=False)
    return w, h


def projscreen(m):
    y = 0.06
    cz0 = 0.95
    w, h = canvas(m, cz0 + 1.125 / 2, y)
    m.box((0, y, cz0 - 0.05), (w + 0.12, 0.09, 0.09), "case")       # carter enrouleur
    for sx in (-1, 1):
        m.box((sx * (w / 2 + 0.07), y, cz0 - 0.05), (0.02, 0.1, 0.1), "rubber")
    m.box((0, y, cz0 + h + 0.015), (w + 0.04, 0.025, 0.03), "frame")  # barre haute de la toile
    tripod(m, cz0 + h + 0.25, y=-0.02, spread=0.6, hub=0.5)
    m.box((0, 0.02, cz0 + h + 0.12), (0.03, 0.08, 0.03), "metal")   # bras de crochet
    m.box((0, 0.02, cz0 - 0.05), (0.08, 0.08, 0.06), "metal")       # collier du carter
    m.memory["screen_c"] = (0, y + 0.003, cz0 + h / 2)
    m.mass = 14


def projscreen_wall(m):
    y = 0.05
    w, h = canvas(m, 0.03 + 1.125 / 2, y)
    m.box((0, y, 0.015), (w + 0.04, 0.025, 0.03), "frame")           # barre de lestage
    m.box((0, 0.05, 0.03 + h + 0.06), (w + 0.12, 0.1, 0.1), "case")  # carter enrouleur
    for sx in (-1, 1):
        m.box((sx * (w / 2 - 0.1), 0.01, 0.03 + h + 0.06), (0.06, 0.02, 0.08), "metal")  # pattes murales
    m.memory["screen_c"] = (0, y + 0.003, 0.03 + h / 2)
    m.mass = 9


MODELS = {
    "comspec_screen_tripod": screen_tripod,
    "comspec_screen_desk": screen_desk,
    "comspec_screen_wall": screen_wall,
    "comspec_screen_dual": screen_dual,
    "comspec_projector": projector,
    "comspec_projector_ceil": projector_ceil,
    "comspec_projscreen": projscreen,
    "comspec_projscreen_wall": projscreen_wall,
}


# ----------------------------------------------------------------------------------------------------------------------
# Écriture MLOD (P3DM 28.256) : sommets (x, z, y), normales opposées, faces dans l'ordre des sommets (repère direct ->
# indirect par l'échange y / z), TAGG (#SharpEdges#, #Property#, #Mass#, #UVSet#, sélections), résolution.
# ----------------------------------------------------------------------------------------------------------------------
VFLAG = 0x02000000
RES = {"visual": 1.0, "shadow": 10000.0, "geometry": 1e13, "memory": 1e15, "view": 6e15, "fire": 7e15}


def asciiz(s):
    return s.encode("ascii") + b"\0"


def tagg(name, data):
    return b"\x01" + asciiz(name) + struct.pack("<I", len(data)) + data


def lod_bytes(res, verts, faces, sels=None, props=(), masses=None, smooth_edges=None):
    """faces : [(indices, normales [(x,y,z)], uvs [(u,v)], texture, rvmat)] ; sels : {nom: (sommets, faces)}."""
    normals, nidx = [], {}
    fbytes = b""
    uvset = b""
    for idx, ns, uvs, tex, mat in faces:
        fbytes += struct.pack("<I", len(idx))
        for i, n, uv in zip(idx, ns, uvs):
            key = tuple(round(c, 5) for c in n)
            if key not in nidx:
                nidx[key] = len(normals)
                normals.append(key)
            fbytes += struct.pack("<IIff", i, nidx[key], uv[0], uv[1])
            uvset += struct.pack("<ff", uv[0], uv[1])
        if len(idx) < 4:
            fbytes += b"\0" * 16
        fbytes += struct.pack("<I", 0) + asciiz(tex) + asciiz(mat)
    out = b"P3DM" + struct.pack("<IIIIII", 28, 256, len(verts), len(normals), len(faces), 0)
    for v in verts:
        out += struct.pack("<fffI", v[0], v[2], v[1], VFLAG)
    for n in normals:
        out += struct.pack("<fff", -n[0], -n[2], -n[1])
    out += fbytes + b"TAGG"
    # Arêtes vives : toutes, sauf celles entre deux faces lisses (flancs des cylindres).
    edges = set()
    for idx, *_ in faces:
        for k in range(len(idx)):
            a, b = idx[k], idx[(k + 1) % len(idx)]
            e = (min(a, b), max(a, b))
            if not smooth_edges or e not in smooth_edges:
                edges.add(e)
    if edges:
        out += tagg("#SharpEdges#", b"".join(struct.pack("<II", a, b) for a, b in sorted(edges)))
    for k, v in props:
        out += tagg("#Property#", k.encode().ljust(64, b"\0") + v.encode().ljust(64, b"\0"))
    if masses:
        out += tagg("#Mass#", b"".join(struct.pack("<f", x) for x in masses))
    if faces and res < 1000:
        out += tagg("#UVSet#", struct.pack("<I", 0) + uvset)
    for name, (vs, fs) in (sels or {}).items():
        vb = bytearray(len(verts))
        for i in vs:
            vb[i] = 1
        fb = bytearray(len(faces))
        for i in fs:
            fb[i] = 1
        out += tagg(name, bytes(vb) + bytes(fb))
    # Fin des TAGG marquée active (octet 1) : sinon Binarize lit la suite comme une sélection sans nom (modèle refusé).
    out += b"\x01" + asciiz("#EndOfFile#") + struct.pack("<I", 0)
    out += struct.pack("<f", res)
    return out


def visual_lod(m):
    verts, faces, sels, smooth = [], [], {}, set()
    for p in [p for p in m.parts if p.visual]:
        o = len(verts)
        verts += p.verts
        # Normale par sommet des faces lisses (moyenne des faces lisses voisines), sinon normale de la face.
        vn = {}
        for idx, mat, uvs, sm in p.faces:
            if sm:
                n = face_normal([p.verts[i] for i in idx])
                for i in idx:
                    vn[i] = add(vn.get(i, (0, 0, 0)), n)
        for idx, mat, uvs, sm in p.faces:
            fn = face_normal([p.verts[i] for i in idx])
            ns = [norm(vn[i]) if sm else fn for i in idx]
            if sm:
                for k in range(len(idx)):
                    a, b = o + idx[k], o + idx[(k + 1) % len(idx)]
                    smooth.add((min(a, b), max(a, b)))
            tex, rv = MATS[mat]
            # MLOD : 3 ou 4 sommets par face ; bouchons des cylindres (polygones) en éventail de triangles.
            if len(idx) <= 4:
                pieces = [list(range(len(idx)))]
            else:
                pieces = [[0, k, k + 1] for k in range(1, len(idx) - 1)]
            for pc in pieces:
                fi = len(faces)
                faces.append(([o + idx[k] for k in pc], [ns[k] for k in pc], [uvs[k] for k in pc], tex, rv))
                if p.sel:
                    vs, fs = sels.setdefault(p.sel, (set(), set()))
                    vs.update(o + idx[k] for k in pc)
                    fs.add(fi)
    # Une arête est vive dès qu'une face plate (bouchon) la touche.
    for idx, ns, uvs, tex, rv in faces:
        if all(n == ns[0] for n in ns):
            for k in range(len(idx)):
                a, b = idx[k], idx[(k + 1) % len(idx)]
                smooth.discard((min(a, b), max(a, b)))
    return lod_bytes(RES["visual"], verts, faces, {k: (sorted(v[0]), sorted(v[1])) for k, v in sels.items()}, smooth_edges=smooth)


def hull_lod(m, res, props=(), with_mass=False, min_size=0.0):
    """Ombre / géométries : pièces fermées en triangles, une composante par pièce (géométries)."""
    verts, faces, sels = [], [], {}
    comp = 0
    for p in m.parts:
        if not p.solid:
            continue
        lo = [min(v[i] for v in p.verts) for i in range(3)]
        hi = [max(v[i] for v in p.verts) for i in range(3)]
        if min(hi[i] - lo[i] for i in range(3)) < min_size:
            continue
        o = len(verts)
        verts += p.verts
        fs = []
        for idx, *_ in p.faces:
            pts = [p.verts[i] for i in idx]
            n = face_normal(pts)
            for k in range(1, len(idx) - 1):
                tri = [idx[0], idx[k], idx[k + 1]]
                fs.append(len(faces))
                faces.append(([o + i for i in tri], [n] * 3, [(0, 0)] * 3, "", ""))
        if res >= 1e13:
            comp += 1
            sels["component%02d" % comp] = (list(range(o, len(verts))), fs)
    masses = None
    if with_mass:
        masses = [m.mass / len(verts)] * len(verts)
    return lod_bytes(res, verts, faces, sels, props, masses)


def memory_lod(m):
    names = sorted(m.memory)
    verts = [m.memory[n] for n in names]
    sels = {n: ([i], []) for i, n in enumerate(names)}
    return lod_bytes(RES["memory"], verts, [], sels)


def write_p3d(m, path):
    lods = [
        visual_lod(m),
        # Ombre : pièces de plus de 6 mm (pas les grilles ni les patins plats).
        hull_lod(m, RES["shadow"], min_size=0.006),
        hull_lod(m, RES["geometry"], [("autocenter", "0")], with_mass=True, min_size=0.006),
        memory_lod(m),
        hull_lod(m, RES["view"], min_size=0.006),
        hull_lod(m, RES["fire"], min_size=0.006),
    ]
    with open(path, "wb") as f:
        f.write(b"MLOD" + struct.pack("<II", 257, len(lods)) + b"".join(lods))


# ----------------------------------------------------------------------------------------------------------------------
# Texture de l'écran éteint et aperçus Eden (rendu à facettes, PIL)
# ----------------------------------------------------------------------------------------------------------------------
def screen_off_png(path):
    """1024 x 512 (étirée en 16:9 sur la dalle) : noir bleuté, logo COMSPEC discret (pré-élargi de 12,5 %)."""
    from PIL import Image, ImageDraw
    W, H = 1024, 512
    img = Image.new("RGB", (W, H), (9, 11, 12))
    d = ImageDraw.Draw(img)
    for y in range(H):
        k = 1 - abs(y - H / 2) / (H / 2)
        c = int(9 + 7 * k)
        d.line([(0, y), (W, y)], fill=(c, c + 2, c + 3))
    logo = Image.open(os.path.join(RENDER, "intro_logo.png")).convert("RGBA")
    lw = int(W * 0.5)
    lh = int(lw / 2 / 1.125)
    logo = logo.resize((lw, lh), Image.LANCZOS)
    a = logo.split()[3].point(lambda v: int(v * 0.22))
    logo.putalpha(a)
    img.paste(logo, ((W - lw) // 2, (H - lh) // 2), logo)
    img.save(path)
    return img


def render_preview(m, path, screen_img, az=-32.0, el=16.0, size=(455, 256)):
    from PIL import Image, ImageDraw
    import numpy as np
    W, H = size
    S = 3
    img = Image.new("RGB", (W * S, H * S), (40, 43, 39))
    gd = ImageDraw.Draw(img)
    for y in range(H * S):
        k = y / (H * S)
        gd.line([(0, y), (W * S, y)], fill=(int(58 - 22 * k), int(62 - 22 * k), int(56 - 20 * k)))
    a, e = math.radians(az), math.radians(el)
    fwd = (math.sin(a) * math.cos(e), -math.cos(a) * math.cos(e), -math.sin(e))   # vers l'objet (vu de l'avant-gauche)
    right = norm(cross(fwd, (0, 0, 1)))
    up = cross(right, fwd)
    tris = []
    for p in [p for p in m.parts if p.visual]:
        for idx, mat, uvs, sm in p.faces:
            pts = [p.verts[i] for i in idx]
            tris.append((pts, mat, uvs))
    allp = [q for t in tris for q in t[0]]
    cen = tuple((min(q[i] for q in allp) + max(q[i] for q in allp)) / 2 for i in range(3))
    rad = max(math.sqrt(dot(sub(q, cen), sub(q, cen))) for q in allp)
    dist = rad * 3.2
    eye = sub(cen, mul(fwd, dist))
    f = 1.0 / math.tan(math.radians(17))

    def proj(q):
        r = sub(q, eye)
        z = dot(r, fwd)
        return (W * S / 2 + dot(r, right) / z * f * H * S / 2 * 0.95, H * S / 2 - dot(r, up) / z * f * H * S / 2 * 0.95, z)

    # Tampon de profondeur (triangles en éventail, interpolation correcte en perspective des UV de l'écran).
    light = norm((-0.45, 0.65, 0.75))
    buf = np.asarray(img, dtype=np.float32) / 255.0
    zb = np.full(buf.shape[:2], np.inf, dtype=np.float32)
    tex = np.asarray(screen_img.convert("RGB"), dtype=np.float32) / 255.0 if screen_img is not None else None
    for pts, mat, uvs in tris:
        n = face_normal(pts)
        if dot(n, sub(pts[0], eye)) >= 0:
            continue
        shade = 0.42 + 0.58 * max(0.0, dot(n, light))
        if mat == "canvas":
            shade = 0.75 + 0.25 * shade
        col = np.array(PREVIEW[mat] or (0.1, 0.1, 0.1), dtype=np.float32) * shade
        pp = [proj(q) for q in pts]
        for k in range(1, len(pp) - 1):
            tri = [pp[0], pp[k], pp[k + 1]]
            tuv = [uvs[0], uvs[k], uvs[k + 1]]
            xs = [t[0] for t in tri]
            ys = [t[1] for t in tri]
            x0, x1 = max(0, int(min(xs))), min(buf.shape[1] - 1, int(max(xs)) + 1)
            y0, y1 = max(0, int(min(ys))), min(buf.shape[0] - 1, int(max(ys)) + 1)
            if x1 < x0 or y1 < y0:
                continue
            gx, gy = np.meshgrid(np.arange(x0, x1 + 1) + 0.5, np.arange(y0, y1 + 1) + 0.5)
            (ax, ay, az_), (bx, by, bz), (cx, cy, cz) = tri
            area = (bx - ax) * (cy - ay) - (by - ay) * (cx - ax)
            if abs(area) < 1e-9:
                continue
            w0 = ((bx - gx) * (cy - gy) - (by - gy) * (cx - gx)) / area
            w1 = ((cx - gx) * (ay - gy) - (cy - gy) * (ax - gx)) / area
            w2 = 1 - w0 - w1
            inside = (w0 >= 0) & (w1 >= 0) & (w2 >= 0)
            iz = w0 / az_ + w1 / bz + w2 / cz
            z = 1 / np.where(iz > 0, iz, 1e-9)
            zs = zb[y0:y1 + 1, x0:x1 + 1]
            mask = inside & (z < zs)
            if not mask.any():
                continue
            zs[mask] = z[mask]
            region = buf[y0:y1 + 1, x0:x1 + 1]
            if mat == "screen" and tex is not None:
                u = (w0 * tuv[0][0] / az_ + w1 * tuv[1][0] / bz + w2 * tuv[2][0] / cz) / iz
                v = (w0 * tuv[0][1] / az_ + w1 * tuv[1][1] / bz + w2 * tuv[2][1] / cz) / iz
                th, tw = tex.shape[:2]
                ui = np.clip((u * tw).astype(int), 0, tw - 1)
                vi = np.clip((v * th).astype(int), 0, th - 1)
                region[mask] = tex[vi[mask], ui[mask]] * (0.85 + 0.15 * shade)
            else:
                region[mask] = col
    img = Image.fromarray((np.clip(buf, 0, 1) * 255).astype(np.uint8))
    img.resize(size, Image.LANCZOS).save(path, quality=90)


def main():
    os.makedirs(RENDER, exist_ok=True)
    off = screen_off_png(os.path.join(RENDER, "comspec_screen_off_co.png"))
    for name, fn in MODELS.items():
        m = Model(name)
        fn(m)
        write_p3d(m, os.path.join(DATA, name + ".p3d"))
        render_preview(m, os.path.join(DATA, name + "_preview.jpg"), off)
        nf = sum(len(p.faces) for p in m.parts)
        print(f"{name}: {nf} faces, {m.mass} kg, mémoire {sorted(m.memory)}")


if __name__ == "__main__":
    main()
