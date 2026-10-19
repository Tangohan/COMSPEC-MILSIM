"""
Objets d'inventaire ATAK en vraie 3D (au sol, dans l'éditeur, icônes d'inventaire), à la place de la sacoche
générique d'Arma (mag_univ.p3d) :

  item3d_battery     batterie BP-385 : pack durci, étiquette, contacts laiton, verrou, nervures de prise
  item3d_powerbank   batterie externe PB-10 10 000 mAh : boîtier olive, coins caoutchouc, voyants, prises USB-C
  item3d_camera      caméra IP durcie IPC-2 : boîtier khaki, pare-chocs noirs, visière, objectif, projecteurs IR, antenne,
                     rotule à boule sur base magnétique
  item3d_nvgfilter   filtre d'écran JVN : film vert dans un cadre caoutchouc, languette orange
  item3d_repairkit   kit de réparation : pochette ranger green (fermeture éclair, tirettes paracorde, poignée, découpe
                     laser MOLLE, écusson COMSPEC)
  item3d_vehiclekit  kit terminal véhicule VMK-1 : berceau khaki à pinces, bras à rotules, platine, boîtier d'antenne
                     (SMA laiton, fouet), coaxial, convertisseur, fiche allume-cigare
  item3d_stub        antenne courte SMA : écrou laiton moleté, manchon caoutchouc
  item3d_whip        antenne fouet longue : ressort de pied, fouet conique, embout
  item3d_patch       antenne patch 9 dBi : radôme khaki, joint caoutchouc, queue de cochon et SMA
  item3d_datacable   câble DL-7 : boîtier adaptateur, câble spiralé, fiche 7 broches, fiche USB-C
  item3d_garminwatch montre GPS Garmin : boîtier rond 47 mm noir, lunette graduée, 5 poussoirs acier (START cerclé de
                     rouge), écran MIP (heure, Body Battery, fréquence cardiaque), bracelet 22 mm ouvert à plat (boucle, passants)
  item3d_dock        socle CS-10 plié : valise étanche (couvercle nervuré, 4 grenouillères, poignées, roues, valve)
  camera_ip          la caméra posée (COMSPEC_ATAK_CameraObj) : origine au centre du boîtier, base 9,2 cm dessous

Modèles, atlas et rvmat dans le PBO items (addons/items) ; icônes item_*.paa dans main.
Chaque modèle : LOD visuelle 1, ombre 0 (une enveloppe convexe fermée par pièce, rentrée de 0,4 mm), géométrie
(enveloppe, masse, autocenter=0), géométrie de tir. Une seule texture (atlas gen_item_textures.py), shader Super
(items.rvmat) ; voyants : items_glow.rvmat (lumineux la nuit). Dos au sol (z = 0), avant vers +Y.
Icônes d'inventaire (item_*.paa, 256 px) et aperçus Eden (item3d_*_preview.jpg) : rendus Cycles des mêmes modèles.

    python3 gen_item_textures.py && python3 model_items.py     (NO_RENDER=1 : modèles seuls ; ONLY=nom)
    puis  hemtt utils paa convert render/items_co.png ../Sources/addons/items/data/items_co.paa  (_nohq, _smdi)
          et  render/icon_<nom>.png -> ../Sources/addons/main/data/item_<nom>.paa (hemtt ne remplace pas un PAA existant : l'effacer avant)
Extension Blender « Arma 3 Object Builder » requise (voir model_vest_phone.py).
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
import gen_item_textures as tx  # noqa: E402

DATA = os.path.join(HERE, "..", "Sources", "addons", "items", "data")
RENDER = os.path.join(HERE, "render")
TEX = "z\\comspec_atak_native\\addons\\items\\data\\"
SAMPLES = int(os.environ.get("RENDER_SAMPLES", "96"))
GLOW = ("led_g", "led_r", "led_b")


def R(x=0.0, y=0.0, z=0.0):
    """Rotation (degrés) X puis Y puis Z."""
    return (Matrix.Rotation(math.radians(z), 3, "Z") @ Matrix.Rotation(math.radians(y), 3, "Y")
            @ Matrix.Rotation(math.radians(x), 3, "X"))


class Part:
    def __init__(self, verts, faces, mat, smooth=0, uvs=None):
        self.verts, self.faces, self.mat, self.smooth, self.uvs = verts, faces, mat, smooth, uvs


class Model:
    """Pièces d'un objet : chaque pièce est fermée (ombre et géométrie = son enveloppe convexe)."""

    def __init__(self):
        self.parts = []
        self.xf = Matrix.Identity(4)   # transformation courante (pose de l'objet)

    def add(self, verts, faces, mat, smooth=0, uvs=None):
        m = self.xf
        self.parts.append(Part([m @ Vector(v) for v in verts], faces, mat, smooth, uvs))

    def loft(self, rings, mat, caps=True, smooth=True, closed=True):
        """Anneaux de même nombre de points (listes de Vector) reliés en flancs ; bouchons plats aux extrémités."""
        n = len(rings[0])
        verts = [v for r in rings for v in r]
        faces = []
        m = n if closed else n - 1
        for k in range(len(rings) - 1):
            o = k * n
            faces += [(o + i, o + (i + 1) % n, o + n + (i + 1) % n, o + n + i) for i in range(m)]
        sides = len(faces) if smooth else 0
        if caps:
            faces.append(tuple(reversed(range(n))))
            faces.append(tuple(range((len(rings) - 1) * n, len(rings) * n)))
        self.add(verts, faces, mat, sides)

    def slab(self, c, size, r=0.0, bev=0.0, mat="black", rot=None, seg=4):
        """Pavé à coins verticaux arrondis (rayon r) et arêtes haute / basse chanfreinées (bev)."""
        c = Vector(c)
        rot = rot or Matrix.Identity(3)
        sx, sy, sz = (v / 2 for v in size)

        def prof(inset):
            rr = max(0.0, r - inset * 0.5)
            ax, ay = sx - inset, sy - inset
            if rr <= 1e-5:
                return [(ax, -ay), (ax, ay), (-ax, ay), (-ax, -ay)]
            pts = []
            for cx, cy, a0 in ((ax - rr, -ay + rr, -90), (ax - rr, ay - rr, 0), (-ax + rr, ay - rr, 90), (-ax + rr, -ay + rr, 180)):
                for i in range(seg + 1):
                    a = math.radians(a0 + 90 * i / seg)
                    pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
            return pts

        levels = [(-sz, bev), (-sz + bev, 0.0), (sz - bev, 0.0), (sz, bev)] if bev > 0 else [(-sz, 0.0), (sz, 0.0)]
        rings = [[c + rot @ Vector((x, y, z)) for x, y in prof(ins)] for z, ins in levels]
        self.loft(rings, mat, smooth=r > 0)

    def box(self, c, size, mat, rot=None):
        self.slab(c, size, 0.0, 0.0, mat, rot)

    def lathe(self, base, axis, prof, n=20, mat="black", smooth=True):
        """Révolution d'un profil [(rayon, hauteur)] autour de l'axe ; rayons nuls fermés en pointe."""
        base, ax = Vector(base), Vector(axis).normalized()
        u = ax.orthogonal().normalized()
        v = ax.cross(u)
        rings = []
        for r, h in prof:
            rings.append([base + ax * h + (u * math.cos(2 * math.pi * i / n) + v * math.sin(2 * math.pi * i / n)) * max(r, 1e-5) for i in range(n)])
        self.loft(rings, mat, caps=True, smooth=smooth)

    def cyl(self, p0, p1, r, n=16, mat="black"):
        p0, p1 = Vector(p0), Vector(p1)
        self.lathe(p0, p1 - p0, [(r, 0.0), (r, (p1 - p0).length)], n, mat)

    def sweep(self, path, r, n=10, mat="cable"):
        """Tube le long d'une polyligne (repère transporté, pas de vrille)."""
        path = [Vector(p) for p in path]
        t0 = (path[1] - path[0]).normalized()
        u = t0.orthogonal().normalized()
        rings = []
        for i, p in enumerate(path):
            if i == 0:
                t = t0
            elif i == len(path) - 1:
                t = (path[i] - path[i - 1]).normalized()
            else:
                t = (path[i + 1] - path[i - 1]).normalized()
            u = (u - t * t.dot(u)).normalized()
            v = t.cross(u)
            rings.append([p + (u * math.cos(2 * math.pi * k / n) + v * math.sin(2 * math.pi * k / n)) * r for k in range(n)])
        self.loft(rings, mat)

    def label(self, c, nrm, up, w, h, name, uv=(0, 0, 1, 1)):
        """Étiquette : quad posé à 0,3 mm de la surface (normale nrm), UV dans le rectangle name de l'atlas."""
        c, nrm, up = Vector(c), Vector(nrm).normalized(), Vector(up).normalized()
        right = up.cross(nrm).normalized()
        c = c + nrm * 0.0003
        u0, v0, u1, v1 = uv
        vs = [c - right * w / 2 - up * h / 2, c + right * w / 2 - up * h / 2, c + right * w / 2 + up * h / 2, c - right * w / 2 + up * h / 2]
        uvs = [tx.label_uv(name, u0, v0), tx.label_uv(name, u1, v0), tx.label_uv(name, u1, v1), tx.label_uv(name, u0, v1)]
        self.add(vs, [(0, 1, 2, 3)], "label", 0, [uvs])

    def pose(self, m):
        self.xf = m


def lin(a, b, t):
    return a + (b - a) * t


# --------------------------------------------------------------------------------------------------------------------
# Les objets
# --------------------------------------------------------------------------------------------------------------------
def sma(M, base, axis, female=False):
    """Connecteur SMA : écrou hexagonal laiton, isolant blanc, âme ; base au plan de pose, vers axis."""
    base, ax = Vector(base), Vector(axis).normalized()
    M.lathe(base, ax, [(0.0042, 0.0), (0.0042, 0.0075)], 6, "brass", smooth=False)
    M.lathe(base + ax * 0.0075, ax, [(0.0034, 0.0), (0.0034, 0.004), (0.0028, 0.0055)], 16, "brass")
    if female:
        M.lathe(base - ax * 0.0005, ax, [(0.0021, 0.0), (0.0021, 0.001)], 12, "white")


def battery(M):
    """Batterie BP-385 (9 x 6 x 1,4 cm)."""
    M.slab((0, 0, 0.007), (0.09, 0.06, 0.014), 0.006, 0.0012, "black")
    M.label((0.004, 0, 0.014), (0, 0, 1), (0, 1, 0), 0.07, 0.046, "battery")
    # Bloc de contacts au bout (+x) et quatre plots laiton.
    M.slab((0.043, 0, 0.0055), (0.008, 0.032, 0.008), 0.001, 0.0005, "grey")
    for k in range(4):
        M.box((0.0472, -0.0105 + k * 0.007, 0.0055), (0.0012, 0.0035, 0.004), "brass")
    # Verrou à glissière sur l'autre bout, nervures de prise sur les flancs.
    M.slab((-0.0445, 0, 0.0075), (0.004, 0.022, 0.006), 0.001, 0.0006, "olive")
    for s in (-1, 1):
        for k in range(6):
            M.box((-0.025 + k * 0.006, s * 0.0302, 0.007), (0.0022, 0.0012, 0.008), "black")
    M.label((-0.03, -0.0302, 0.007), (0, -1, 0), (0, 0, 1), 0.024, 0.006, "serial")


def powerbank(M):
    """Batterie externe PB-10 (15,5 x 8 x 2,6 cm) : boîtier olive, embouts caoutchouc, voyants, USB-C, dragonne."""
    M.slab((0, 0, 0.013), (0.13, 0.08, 0.026), 0.008, 0.002, "olive")
    for s in (-1, 1):
        M.slab((s * 0.0675, 0, 0.0135), (0.022, 0.084, 0.027), 0.01, 0.003, "rubber")
    M.label((-0.006, 0, 0.0262), (0, 0, 1), (0, 1, 0), 0.098, 0.04, "powerbank")
    # Voyants de charge (4) et bouton.
    for k in range(4):
        M.cyl((0.03 + k * 0.0055, -0.027, 0.0255), (0.03 + k * 0.0055, -0.027, 0.0267), 0.0012, 10, "led_g" if k < 3 else "grey")
    M.cyl((0.03, 0.026, 0.0255), (0.03, 0.026, 0.028), 0.0035, 14, "rubber")
    # Prises USB-C dans l'embout +x.
    for y in (-0.014, 0.014):
        M.slab((0.0786, y, 0.0135), (0.002, 0.009, 0.0034), 0.0015, 0.0, "steel", seg=3)
    # Dragonne (anneau) côté -x.
    M.sweep([Vector((-0.078 - 0.012 * math.sin(math.pi * k / 10), 0.012 * math.cos(math.pi * k / 10), 0.0135)) for k in range(11)], 0.0022, 8, "webbing")


def camera_body(M, o):
    """Caméra IP durcie IPC-2 (7 x 6 x 6,5 cm), objectif vers +Y ; o = centre du boîtier. Coque khaki, pare-chocs
    d'angle et façade noirs, visière, deux projecteurs IR, antenne, trappe latérale, plaque arrière ; rotule à boule
    sur base magnétique (dessous de la base 9,2 cm sous le centre)."""
    o = Vector(o)
    W, D, H = 0.07, 0.062, 0.064
    M.slab(o, (W, D, H), 0.009, 0.004, "khaki")
    for sx in (-1, 1):
        for sy in (-1, 1):
            M.slab(o + Vector((sx * (W / 2 - 0.004), sy * (D / 2 - 0.006), 0)), (0.012, 0.016, H * 0.94), 0.004, 0.002, "black")
    # Façade (cadre noir), patte basse.
    M.slab(o + Vector((0, D / 2 + 0.002, -0.002)), (W * 0.86, 0.006, H * 0.8), 0.006, 0.0015, "black")
    M.slab(o + Vector((0, D / 2 - 0.004, -H / 2 - 0.002)), (0.026, 0.012, 0.006), 0.002, 0.001, "black")
    # Visière inclinée débordant à l'avant, joues latérales.
    M.slab(o + Vector((0, D / 2 - 0.004, H / 2 + 0.003)), (W + 0.008, 0.044, 0.006), 0.004, 0.0015, "black", R(x=-8))
    M.slab(o + Vector((0, -0.006, H / 2 + 0.001)), (0.03, 0.02, 0.004), 0.002, 0.001, "black")
    for sx in (-1, 1):
        M.slab(o + Vector((sx * (W / 2 + 0.002), D / 2 + 0.006, H / 2 - 0.006)), (0.004, 0.022, 0.016), 0.002, 0.0, "black")
    # Objectif : fût, bague anodisée, lentille bombée.
    lc = o + Vector((0, D / 2 + 0.005, 0.0))
    M.lathe(lc, (0, 1, 0), [(0.019, 0.0), (0.019, 0.006), (0.017, 0.008), (0.0135, 0.009)], 28, "black")
    M.lathe(lc + Vector((0, 0.006, 0)), (0, 1, 0), [(0.0156, 0.0), (0.0156, 0.0014)], 28, "anodized")
    M.lathe(lc + Vector((0, 0.0085, 0)), (0, 1, 0), [(0.0135, 0.0), (0.0105, 0.0035), (0.0, 0.005)], 28, "lens")
    # Projecteurs IR (rouge sombre, lumineux la nuit) et deux capteurs ronds.
    for sx in (-1, 1):
        p = o + Vector((sx * 0.024, D / 2 + 0.0052, 0.008))
        M.slab(p, (0.009, 0.0012, 0.017), 0.001, 0.0, "ir")
        for k in range(3):
            M.cyl(p + Vector((0, 0.0005, -0.005 + k * 0.005)), p + Vector((0, 0.0011, -0.005 + k * 0.005)), 0.0017, 8, "led_r")
        M.lathe(o + Vector((sx * 0.024, D / 2 + 0.005, -0.019)), (0, 1, 0), [(0.0045, 0.0), (0.0045, 0.0012), (0.003, 0.0018), (0.0, 0.002)], 14, "lens")
    for x in (-0.009, 0.0, 0.009):
        M.cyl(o + Vector((x, D / 2 + 0.0048, -0.024)), o + Vector((x, D / 2 + 0.0058, -0.024)), 0.0012, 8, "steel")
    # Antenne caoutchouc (dessus, arrière droit).
    a0 = o + Vector((W / 2 - 0.007, -D / 2 + 0.012, H / 2 + 0.002))
    M.lathe(a0, (0, 0, 1), [(0.0055, 0.0), (0.0055, 0.008), (0.004, 0.012)], 12, "black")
    M.lathe(a0 + Vector((0, 0, 0.012)), (0, 0, 1), [(0.0042, 0.0), (0.0042, 0.05), (0.0034, 0.058), (0.0, 0.06)], 12, "rubber")
    # Flanc -x : trappe étanche et bouton.
    M.slab(o + Vector((-W / 2 - 0.001, -0.004, 0.006)), (0.003, 0.026, 0.032), 0.004, 0.001, "black")
    M.lathe(o + Vector((-W / 2 - 0.0005, -0.004, -0.019)), (-1, 0, 0), [(0.0045, 0.0), (0.0045, 0.002), (0.0035, 0.0028)], 14, "black")
    # Dos : plaque, étiquette (normes, QR), rail.
    M.slab(o + Vector((0, -D / 2 - 0.001, -0.004)), (W * 0.72, 0.004, H * 0.62), 0.004, 0.001, "black")
    M.label(o + Vector((0, -D / 2 - 0.003, -0.006)), (0, -1, 0), (0, 0, 1), 0.034, 0.034, "camera_back")
    M.slab(o + Vector((0, -D / 2 - 0.002, 0.024)), (0.03, 0.006, 0.01), 0.002, 0.001, "black")
    # Rotule (douille, boule, molette) et base magnétique caoutchouc.
    M.slab(o + Vector((0, 0, -H / 2 - 0.004)), (0.026, 0.026, 0.008), 0.004, 0.001, "black")
    ball = o + Vector((0, 0, -0.051))
    M.lathe(ball, (0, 0, 1), [(0.0, -0.011), (0.0078, -0.0078), (0.011, 0.0), (0.0078, 0.0078), (0.0, 0.011)], 16, "black")
    M.lathe(ball, (0, 0, -1), [(0.0055, 0.0), (0.0055, 0.022)], 12, "black")
    M.lathe(o + Vector((0, 0, -0.081)), (0, 0, 1), [(0.0125, 0.0), (0.0125, 0.01)], 12, "rubber", smooth=False)
    M.lathe(o + Vector((0, 0, -0.092)), (0, 0, 1), [(0.0, 0.0), (0.039, 0.0), (0.041, 0.003), (0.039, 0.007), (0.028, 0.011), (0.015, 0.012), (0.0, 0.0125)], 32, "black")
    M.lathe(o + Vector((0.012, 0, -H / 2 - 0.006)), (1, 0, 0), [(0.003, 0.0), (0.003, 0.006)], 10, "black")
    M.lathe(o + Vector((0.018, 0, -H / 2 - 0.006)), (1, 0, 0), [(0.0075, 0.0), (0.0075, 0.006), (0.0, 0.0065)], 12, "rubber", smooth=False)


CAM_FOOT = 0.092


def camera_item(M):
    camera_body(M, (0, 0.0, CAM_FOOT))


def camera_obj(M):
    camera_body(M, (0, 0.0, 0.0))


def nvgfilter(M):
    """Filtre d'écran JVN (15 x 8 cm) : film vert, cadre caoutchouc, languette orange."""
    M.slab((0, 0, 0.0016), (0.15, 0.08, 0.0032), 0.007, 0.0006, "rubber")
    M.slab((0, 0, 0.0034), (0.138, 0.068, 0.0012), 0.004, 0.0, "filter")
    M.slab((0.0825, 0.018, 0.0012), (0.026, 0.026, 0.0024), 0.004, 0.0, "orange")
    M.label((0.0835, 0.018, 0.0024), (0, 0, 1), (-1, 0, 0), 0.024, 0.0085, "nvg", (0.0, 0.42, 1.0, 1.0))
    for k in range(3):
        M.box((-0.04 + k * 0.04, 0, 0.00405), (0.0008, 0.066, 0.0002), "glass")


def repairkit(M):
    """Pochette de réparation ranger green (20 x 15 x 7,5 cm) couchée face en l'air : coque en deux demi-coquilles,
    fermeture éclair périphérique, tirettes paracorde, poignée, champ velcro et écusson COMSPEC, découpe laser MOLLE."""
    L, Wd = 0.2, 0.15
    M.slab((0, 0, 0.018), (L, Wd, 0.036), 0.03, 0.01, "ranger")
    M.slab((0, 0, 0.0565), (L - 0.004, Wd - 0.004, 0.037), 0.03, 0.012, "ranger")
    M.slab((0, 0, 0.037), (L + 0.004, Wd + 0.004, 0.005), 0.032, 0.0, "webbing")
    # Panneau avant (dessus) : champ velcro et écusson, deux rangées de découpes laser.
    M.slab((0, 0.026, 0.0752), (0.15, 0.062, 0.0016), 0.004, 0.0, "velcro")
    M.label((0, 0.026, 0.0762), (0, 0, 1), (0, 1, 0), 0.084, 0.04, "eagle")
    for row in (-0.022, -0.042):
        for k in range(4):
            M.box((-0.054 + k * 0.036, row, 0.0753), (0.028, 0.0045, 0.0008), "webbing")
    # Deux tirettes (curseur, boucle de paracorde, embout) sur le bord avant (-y).
    for x in (-0.05, 0.05):
        M.slab((x, -Wd / 2 - 0.003, 0.037), (0.01, 0.006, 0.008), 0.0015, 0.0, "anodized")
        M.sweep([Vector((x, -Wd / 2 - 0.006, 0.037)), Vector((x + 0.003, -Wd / 2 - 0.016, 0.03)), Vector((x + 0.004, -Wd / 2 - 0.028, 0.02)),
                 Vector((x + 0.004, -Wd / 2 - 0.036, 0.012))], 0.0018, 6, "webbing")
        M.lathe(Vector((x + 0.004, -Wd / 2 - 0.035, 0.013)), (0, -1, -0.6), [(0.0035, 0.0), (0.0045, 0.006), (0.0035, 0.016), (0.0, 0.017)], 10, "black")
    # Poignée de transport (sangle) sur le bord arrière (+y), retombée sur le côté.
    M.sweep([Vector((-0.04 + 0.08 * k / 12, Wd / 2 + 0.002 + 0.02 * math.sin(math.pi * k / 12), 0.052 - 0.012 * math.sin(math.pi * k / 12))) for k in range(13)],
            0.0045, 8, "webbing")
    for x in (-0.042, 0.042):
        M.box((x, Wd / 2 + 0.001, 0.052), (0.02, 0.004, 0.016), "webbing")
    # Dos (au sol) : deux sangles MOLLE à pressions.
    for x in (-0.05, 0.05):
        M.box((x, 0, 0.0008), (0.026, Wd - 0.03, 0.0026), "ranger")
        M.cyl((x, -0.05, 0.0), (x, -0.05, -0.0001), 0.004, 10, "anodized")


def vehiclekit(M):
    """Kit terminal véhicule VMK-1 : berceau khaki à pinces (format paysage), bras à double rotule, platine,
    boîtier d'antenne noir avec SMA laiton et fouet, coaxial, convertisseur 12-32 V et fiche allume-cigare."""
    M.lathe((0, 0, 0), (0, 0, 1), [(0.0, 0.0), (0.035, 0.0), (0.035, 0.005), (0.03, 0.007), (0.009, 0.008)], 28, "black")
    for k in range(4):
        a = math.pi / 4 + k * math.pi / 2
        M.cyl((0.025 * math.cos(a), 0.025 * math.sin(a), 0.0055), (0.025 * math.cos(a), 0.025 * math.sin(a), 0.0075), 0.0028, 8, "steel")
    b0 = Vector((0, 0, 0.026))
    M.lathe((0, 0, 0.006), (0, 0, 1), [(0.008, 0.0), (0.008, 0.006), (0.0, 0.007)], 14, "black")
    M.lathe(b0, (0, 0, 1), [(0.0, -0.019), (0.016, -0.012), (0.019, 0.0), (0.016, 0.012), (0.0, 0.019)], 20, "rubber")
    b1 = Vector((0, 0.03, 0.105))
    M.sweep([b0, b1], 0.011, 14, "black")
    mid = (b0 + b1) / 2
    M.lathe(mid + Vector((0.012, 0, 0)), (1, 0, 0), [(0.0, 0.0), (0.014, 0.002), (0.014, 0.012), (0.0, 0.014)], 14, "black")
    for e in (b0, b1):
        d = (b1 - b0).normalized() * (1 if e is b0 else -1)
        M.lathe(e + d * 0.012, d, [(0.0135, 0.0), (0.0135, 0.016), (0.0115, 0.02)], 16, "black")
    M.lathe(b1, (0, 0, 1), [(0.0, -0.019), (0.016, -0.012), (0.019, 0.0), (0.016, 0.012), (0.0, 0.019)], 20, "rubber")
    # Berceau incliné de 15 deg vers l'arrière, écran vers -y.
    t = R(x=-15)
    c = b1 + t @ Vector((0, -0.034, 0.0))

    def at(x, y, z):
        return c + t @ Vector((x, y, z))

    M.slab(at(0, 0.019, 0), (0.024, 0.014, 0.024), 0.004, 0.001, "black", t)
    M.slab(at(0, 0, 0), (0.182, 0.02, 0.104), 0.012, 0.004, "khaki", t)
    M.slab(at(0, -0.0105, 0), (0.16, 0.003, 0.084), 0.008, 0.0, "rubber", t)
    M.slab(at(0, -0.016, -0.047), (0.15, 0.016, 0.01), 0.003, 0.002, "khaki", t)
    for sx in (-1, 1):
        for sz in (-1, 1):
            M.slab(at(sx * 0.083, -0.014, sz * 0.04), (0.014, 0.014, 0.024), 0.003, 0.0015, "khaki", t)
        M.slab(at(sx * 0.092, -0.004, 0.0), (0.006, 0.02, 0.05), 0.002, 0.001, "black", t)
    M.slab(at(0, -0.008, 0.056), (0.034, 0.026, 0.014), 0.004, 0.002, "black", t)
    M.lathe(at(0, 0.0, 0.063), t @ Vector((0, 0, 1)), [(0.0, 0.0), (0.008, 0.0), (0.008, 0.006), (0.0, 0.007)], 12, "rubber", smooth=False)
    # Boîtier d'antenne (flanc droit), SMA laiton, fouet caoutchouc.
    M.slab(at(0.112, 0.002, -0.006), (0.03, 0.032, 0.062), 0.005, 0.002, "black", t)
    M.label(at(0.112, -0.0142, -0.004), t @ Vector((0, -1, 0)), t @ Vector((0, 0, 1)), 0.026, 0.05, "vehicle", (0.0, 0.0, 0.35, 1.0))
    up = t @ Vector((0, 0, 1))
    top = at(0.112, 0.002, 0.025)
    sma(M, top, up)
    M.lathe(top + up * 0.0115, up, [(0.0055, 0.0), (0.0075, 0.004), (0.0075, 0.03), (0.006, 0.034)], 16, "rubber")
    M.lathe(top + up * 0.045, up, [(0.006, 0.0), (0.0055, 0.03), (0.004, 0.14), (0.0, 0.142)], 14, "rubber")
    # Coaxial du boîtier vers le convertisseur posé au sol.
    conv = Vector((0.17, -0.04, 0.0))
    p0 = at(0.112, 0.002, -0.037)
    path = [p0]
    for k in range(1, 16):
        s = k / 15
        path.append(Vector((lin(p0.x, conv.x - 0.036, s) + 0.02 * math.sin(math.pi * s), lin(p0.y, conv.y, s) + 0.03 * math.sin(math.pi * s),
                            max(0.0035, lin(p0.z, 0.012, s) - 0.05 * math.sin(math.pi * s)))))
    M.sweep(path, 0.0032, 8, "cable")
    M.slab(conv + Vector((0, 0, 0.014)), (0.07, 0.045, 0.028), 0.006, 0.0015, "black")
    M.label(conv + Vector((0, 0, 0.028)), (0, 0, 1), (0, 1, 0), 0.062, 0.023, "vehicle")
    M.cyl(conv + Vector((0.026, -0.0225, 0.022)), conv + Vector((0.026, -0.0235, 0.022)), 0.0015, 8, "led_b")
    tail = [conv + Vector((0.036, 0, 0.012))]
    for k in range(1, 12):
        a = k / 11 * math.pi * 1.4
        tail.append(conv + Vector((0.036 + 0.04 * math.sin(a), -0.04 * (1 - math.cos(a)), 0.0035)))
    M.sweep(tail, 0.0028, 8, "cable")
    end = tail[-1]
    d = (tail[-1] - tail[-2]).normalized()
    M.lathe(end, d, [(0.0035, 0.0), (0.011, 0.01), (0.011, 0.03), (0.0085, 0.05), (0.0085, 0.055), (0.0, 0.058)], 16, "black")
    M.lathe(end + d * 0.058, d, [(0.004, 0.0), (0.004, 0.005), (0.0, 0.006)], 10, "steel")


def stub(M):
    """Antenne courte SMA (6,5 cm) couchée au sol : écrou moleté laiton, manchon et corps caoutchouc."""
    ax = Vector((0, 1, 0))
    o = Vector((0, -0.032, 0.0062))
    sma(M, o, ax, True)
    M.lathe(o + ax * 0.0115, ax, [(0.0045, 0.0), (0.0062, 0.003), (0.0062, 0.012), (0.0055, 0.016), (0.0048, 0.045), (0.0042, 0.052), (0.0, 0.0545)], 20, "rubber")
    M.lathe(o + ax * 0.016, ax, [(0.00628, 0.0), (0.00628, 0.0015)], 20, "black")


def whip(M):
    """Antenne fouet longue (42 cm) couchée : SMA, ressort de pied, fouet conique, embout de sécurité."""
    ax = Vector((0, 1, 0))
    o = Vector((0, -0.21, 0.0075))
    sma(M, o, ax, True)
    M.lathe(o + ax * 0.0115, ax, [(0.0055, 0.0), (0.0075, 0.004), (0.0075, 0.03), (0.006, 0.034)], 20, "rubber")
    # Ressort (spire) sur 3,5 cm.
    base = o + ax * 0.045
    coil = [base + Vector((0.0048 * math.cos(t), 0.035 * t / (2 * math.pi * 9), 0.0048 * math.sin(t))) for t in [k * 0.4 for k in range(int(2 * math.pi * 9 / 0.4) + 1)]]
    M.sweep(coil, 0.0011, 6, "steel")
    M.cyl(base, base + ax * 0.036, 0.0036, 12, "rubber")
    M.lathe(base + ax * 0.035, ax, [(0.0045, 0.0), (0.0042, 0.006), (0.0032, 0.1), (0.0024, 0.25), (0.0019, 0.33), (0.0, 0.331)], 12, "rubber")
    M.lathe(base + ax * 0.362, ax, [(0.0, -0.002), (0.0035, 0.002), (0.0035, 0.008), (0.0, 0.011)], 12, "black")
    M.label(o + ax * 0.025 + Vector((0, 0, 0.0076)), (0, 0, 1), (0, 1, 0), 0.008, 0.02, "warn", (0.33, 0.0, 1.0, 1.0))


def patch(M):
    """Antenne patch 9 dBi (12 x 12 cm) : radôme khaki, joint caoutchouc, queue de cochon RG-316 et SMA."""
    M.slab((0, 0, 0.003), (0.124, 0.124, 0.006), 0.012, 0.001, "rubber")
    M.slab((0, 0, 0.0155), (0.12, 0.12, 0.021), 0.016, 0.006, "khaki")
    M.label((0, 0, 0.026), (0, 0, 1), (0, 1, 0), 0.098, 0.098, "patch")
    # Presse-étoupe et queue de cochon vers l'avant, SMA au bout.
    M.lathe((0, -0.06, 0.012), (0, -1, 0), [(0.006, 0.0), (0.006, 0.006), (0.004, 0.012)], 6, "black", smooth=False)
    path = []
    for k in range(17):
        t = k / 16
        path.append(Vector((0.06 * math.sin(t * math.pi * 1.2), -0.067 - 0.11 * t, max(0.0025, 0.009 - 0.02 * t))))
    M.sweep(path, 0.0025, 8, "cable")
    d = (path[-1] - path[-2]).normalized()
    M.lathe(path[-1], d, [(0.003, 0.0), (0.0045, 0.006), (0.0045, 0.016)], 12, "black")
    sma(M, path[-1] + d * 0.016, d)


def datacable(M):
    """Câble DL-7 : boîtier adaptateur, câble spiralé, fiche ronde 7 broches et fiche USB-C."""
    box = Vector((-0.05, 0.0, 0.0))
    M.slab(box + Vector((0, 0, 0.011)), (0.08, 0.05, 0.022), 0.007, 0.002, "olive")
    M.label(box + Vector((0, 0, 0.022)), (0, 0, 1), (0, 1, 0), 0.07, 0.026, "dl7")
    for k, mat in enumerate(("led_g", "led_b", "led_g")):
        M.cyl(box + Vector((-0.03 + k * 0.013, -0.025, 0.0075)), box + Vector((-0.03 + k * 0.013, -0.0256, 0.0075)), 0.0016, 8, mat)
    # Fiche 7 broches à baïonnette sur le boîtier (côté -x), moletée.
    M.lathe(box + Vector((-0.04, 0, 0.011)), (-1, 0, 0), [(0.009, 0.0), (0.011, 0.004), (0.011, 0.02), (0.0095, 0.023)], 6 * 3, "anodized")
    M.lathe(box + Vector((-0.063, 0, 0.011)), (-1, 0, 0), [(0.0065, 0.0), (0.0065, 0.003)], 16, "brass")
    # Câble spiralé (boucles à plat) entre le boîtier (+x) et la fiche USB-C.
    start = box + Vector((0.04, 0, 0.011))
    path = [start, start + Vector((0.012, 0, -0.004))]
    loops, rad = 5, 0.012
    for k in range(loops * 16 + 1):
        t = k / 16 * 2 * math.pi
        x = start.x + 0.024 + 0.0075 * t
        path.append(Vector((x + rad * math.sin(t), -rad + rad * math.cos(t), 0.0045 + 0.0035 * (1 + math.cos(t)) / 2)))
    endp = path[-1]
    for k in range(1, 8):
        path.append(endp + Vector((0.008 * k, 0.003 * k, 0.0)))
    M.sweep(path, 0.0022, 8, "cable")
    d = (path[-1] - path[-2]).normalized()
    M.lathe(path[-1], d, [(0.0028, 0.0), (0.0055, 0.008), (0.0055, 0.026)], 14, "black")
    end = path[-1] + d * 0.026
    rot = Matrix.Rotation(math.atan2(d.y, d.x), 3, "Z")
    M.slab(end + d * 0.0045, (0.009, 0.0084, 0.0026), 0.0012, 0.0, "steel", rot)


def _band(M, path, w, th, mat):
    """Bracelet : section rectangulaire (largeur w selon X, épaisseur th) le long d'un chemin [(y, z)] dans le plan YZ."""
    pts = [Vector((0.0, y, z)) for y, z in path]
    rings = []
    for i, p in enumerate(pts):
        t = (pts[min(i + 1, len(pts) - 1)] - pts[max(i - 1, 0)]).normalized()
        n = Vector((0.0, -t.z, t.y))
        if n.z < 0:
            n = -n
        rings.append([p + Vector((-w / 2, 0, 0)) - n * th / 2, p + Vector((w / 2, 0, 0)) - n * th / 2,
                      p + Vector((w / 2, 0, 0)) + n * th / 2, p + Vector((-w / 2, 0, 0)) + n * th / 2])
    M.loft(rings, mat, smooth=False)


def garminwatch(M):
    """Montre GPS Garmin (boîtier 47 mm, bracelet 22 mm) couchée cadran vers le ciel, bracelet ouvert à plat selon Y."""
    R0, H = 0.0235, 0.0135
    # Boîtier : fond acier, carrure polymère noir, lunette anodisée à dessus plat (graduations peintes sur le cadran).
    M.lathe((0, 0, 0), (0, 0, 1), [(0.0, 0.0), (0.019, 0.0), (0.021, 0.0012), (0.021, 0.0022)], 32, "steel")
    M.lathe((0, 0, 0), (0, 0, 1), [(0.0, 0.0018), (0.0215, 0.0018), (R0, 0.0035), (R0, 0.0095), (0.0228, 0.0105), (0.0, 0.0105)], 40, "black")
    M.lathe((0, 0, 0), (0, 0, 1), [(0.0, 0.0102), (0.0232, 0.0102), (0.0236, 0.0112), (0.0232, H - 0.0004), (0.0226, H), (0.0, H)], 48, "anodized")
    # Cadran (verre et écran MIP, lunette graduée) : disque à UV dans l'étiquette « garmin ».
    n, rf = 40, 0.0224
    vs = [(rf * math.cos(2 * math.pi * k / n), rf * math.sin(2 * math.pi * k / n), H + 0.0003) for k in range(n)]
    uvs = [tx.label_uv("garmin", 0.5 + 0.5 * math.cos(2 * math.pi * k / n), 0.5 + 0.5 * math.sin(2 * math.pi * k / n)) for k in range(n)]
    M.add(vs, [tuple(range(n))], "label", 0, [uvs])
    # Cornes (4) et barrettes.
    for sy in (-1, 1):
        for sx in (-1, 1):
            M.slab((sx * 0.0098, sy * 0.0235, 0.0062), (0.0036, 0.011, 0.0062), 0.0012, 0.0006, "black")
        M.cyl((-0.0085, sy * 0.0262, 0.0062), (0.0085, sy * 0.0262, 0.0062), 0.0009, 10, "steel")
    # Poussoirs : 3 à gauche (LIGHT, UP, DOWN), 2 à droite (START cerclé de rouge, BACK). Stries de prise.
    for a, start in ((150, False), (180, False), (210, False), (30, True), (-30, False)):
        d = Vector((math.cos(math.radians(a)), math.sin(math.radians(a)), 0))
        c0 = d * (R0 - 0.0006) + Vector((0, 0, 0.0065))
        M.lathe(c0, d, [(0.0024, 0.0), (0.0024, 0.0026), (0.0021, 0.0034), (0.0, 0.0035)], 16, "steel")
        if start:
            M.lathe(c0 + d * 0.0006, d, [(0.0027, 0.0), (0.0027, 0.0008)], 16, "red")
    # Bracelet : côté +Y long (trous, passants), côté -Y court (boucle acier, ardillon).
    w, th = 0.022, 0.0026
    up = [(0.0262, 0.0062), (0.031, 0.0058), (0.036, 0.0042), (0.041, 0.0024), (0.047, 0.0014), (0.055, 0.0013), (0.098, 0.0013)]
    _band(M, up, w, th, "rubber")
    for k in range(5):
        y = 0.068 + k * 0.0065
        M.cyl((0, y, 0.0026), (0, y, 0.0028), 0.0011, 10, "cable")
    M.slab((0, 0.061, 0.0013), (w + 0.003, 0.006, th + 0.0022), 0.001, 0.0005, "rubber")
    M.slab((0, 0.1, 0.0013), (w, 0.016, th), 0.0075, 0.0006, "rubber", seg=6)
    lo = [(-0.0262, 0.0062), (-0.031, 0.0058), (-0.036, 0.0042), (-0.041, 0.0024), (-0.047, 0.0014), (-0.055, 0.0013), (-0.072, 0.0013)]
    _band(M, lo, w, th, "rubber")
    M.slab((0, -0.062, 0.0013), (w + 0.003, 0.006, th + 0.0022), 0.001, 0.0005, "rubber")
    # Boucle : cadre acier (4 barres), ardillon couché.
    bx, by0, by1, bz = 0.0135, -0.069, -0.083, 0.0018
    M.cyl((-bx, by0, bz), (bx, by0, bz), 0.0011, 10, "steel")
    M.cyl((-bx, by1, bz), (bx, by1, bz), 0.0013, 10, "steel")
    for sx in (-1, 1):
        M.cyl((sx * bx, by0, bz), (sx * bx, by1, bz), 0.0012, 10, "steel")
    M.cyl((0, by0, bz + 0.0004), (0, by1 - 0.001, bz + 0.0004), 0.0007, 8, "steel")


def dock(M):
    """Valise du socle CS-10 (62 x 42 x 24 cm) : coque olive, couvercle nervuré, grenouillères, poignées, roues."""
    L, W = 0.62, 0.42
    # Coque basse (cuve), joint du couvercle, couvercle.
    M.slab((0, 0, 0.082), (L, W, 0.16), 0.03, 0.012, "olive")
    M.slab((0, 0, 0.163), (L + 0.006, W + 0.006, 0.008), 0.032, 0.0, "olive_dark")
    M.slab((0, 0, 0.198), (L, W, 0.062), 0.03, 0.014, "olive")
    # Nervures du couvercle et bourrelets de pied.
    for y in (-0.12, 0.12):
        M.slab((0, y, 0.231), (L - 0.12, 0.05, 0.008), 0.02, 0.003, "olive")
    M.slab((0, 0, 0.231), (L - 0.12, 0.09, 0.008), 0.02, 0.003, "olive")
    M.label((0.0, 0.0, 0.2352), (0, 0, 1), (0, 1, 0), 0.2, 0.1, "dock")
    for x in (-0.25, 0.25):
        for y in (-0.16, 0.16):
            M.slab((x, y, 0.004), (0.05, 0.05, 0.008), 0.012, 0.002, "rubber")
    # Ceinture de renfort horizontale (évite le rendu « savon »).
    M.slab((0, 0, 0.11), (L + 0.008, W + 0.008, 0.014), 0.034, 0.003, "olive")
    # Grenouillères : 3 à l'avant (-y), 2 sur le flanc arrière : corps sombre à double action.
    for x in (-0.2, 0.0, 0.2):
        M.slab((x, -W / 2 - 0.007, 0.162), (0.06, 0.014, 0.07), 0.006, 0.002, "olive_dark")
        M.slab((x, -W / 2 - 0.0148, 0.17), (0.044, 0.006, 0.04), 0.004, 0.001, "black")
        M.slab((x, -W / 2 - 0.004, 0.193), (0.04, 0.012, 0.012), 0.003, 0.0, "olive_dark")
    # Poignée avant repliée, poignée de flanc, porte-cadenas.
    M.sweep([Vector((-0.07, -W / 2 - 0.006, 0.12)), Vector((-0.07, -W / 2 - 0.03, 0.12)), Vector((0.07, -W / 2 - 0.03, 0.12)), Vector((0.07, -W / 2 - 0.006, 0.12))], 0.008, 10, "black")
    for x in (-0.11, 0.11):
        M.slab((x, -W / 2 - 0.005, 0.157), (0.012, 0.01, 0.03), 0.002, 0.0, "steel")
    for s in (-1, 1):
        M.sweep([Vector((s * (L / 2 + 0.004), -0.06, 0.13)), Vector((s * (L / 2 + 0.025), -0.06, 0.13)), Vector((s * (L / 2 + 0.025), 0.06, 0.13)), Vector((s * (L / 2 + 0.004), 0.06, 0.13))], 0.007, 10, "black")
    M.label((L / 2 + 0.0045, 0.12, 0.08), (1, 0, 0), (0, 0, 1), 0.04, 0.12, "dock_side")
    # Charnière arrière (+y), roues et logements à l'arrière, valve de pression.
    M.cyl((-L / 2 + 0.04, W / 2 + 0.006, 0.168), (L / 2 - 0.04, W / 2 + 0.006, 0.168), 0.009, 12, "olive_dark")
    for x in (-0.24, 0.24):
        M.lathe((x, W / 2 - 0.02, 0.04), (1 if x > 0 else -1, 0, 0), [(0.0, -0.012), (0.036, -0.012), (0.04, -0.006), (0.04, 0.012), (0.03, 0.016), (0.0, 0.016)], 20, "rubber")
        M.lathe((x + (0.018 if x > 0 else -0.018), W / 2 - 0.02, 0.04), (1 if x > 0 else -1, 0, 0), [(0.012, 0.0), (0.012, 0.003)], 12, "steel")
    M.lathe((0.24, 0.0, 0.229), (0, 0, 1), [(0.016, 0.0), (0.016, 0.006), (0.012, 0.01), (0.0, 0.011)], 6, "black", smooth=False)
    M.label((-0.2, -W / 2 - 0.0002, 0.075), (0, -1, 0), (0, 0, 1), 0.14, 0.032, "serial")
    # Prise secteur étanche sur le flanc -x.
    M.lathe((-L / 2 - 0.002, -0.12, 0.08), (-1, 0, 0), [(0.016, 0.0), (0.016, 0.012), (0.012, 0.016), (0.0, 0.017)], 16, "black")


# Icônes : azimut de la caméra de rendu (objets dont la face utile regarde vers +Y).
ICON_AZ = {"item3d_camera": 150.0}

ITEMS = {
    # nom : (fonction, masse kg, classe d'objet, icône d'inventaire)
    "item3d_battery": (battery, 0.12, "item_battery"),
    "item3d_powerbank": (powerbank, 0.32, "item_powerbank"),
    "item3d_camera": (camera_item, 0.45, "item_camera"),
    "item3d_nvgfilter": (nvgfilter, 0.04, "item_nvgfilter"),
    "item3d_repairkit": (repairkit, 0.9, "item_repairkit"),
    "item3d_vehiclekit": (vehiclekit, 1.2, "item_vehiclekit"),
    "item3d_stub": (stub, 0.02, "item_antenna_stub"),
    "item3d_whip": (whip, 0.08, "item_antenna_whip"),
    "item3d_patch": (patch, 0.3, "item_antenna_patch"),
    "item3d_datacable": (datacable, 0.2, "item_datacable"),
    "item3d_dock": (dock, 9.0, "item_dock"),
    "item3d_garminwatch": (garminwatch, 0.07, "item_garminwatch"),
    "camera_ip": (camera_obj, 0.45, None),
}


# --------------------------------------------------------------------------------------------------------------------
# Blender
# --------------------------------------------------------------------------------------------------------------------
def build(fn):
    M = Model()
    fn(M)
    return M.parts


def bounds(parts):
    vs = [v for p in parts for v in p.verts]
    lo = Vector([min(v[i] for v in vs) for i in range(3)])
    hi = Vector([max(v[i] for v in vs) for i in range(3)])
    return lo, hi


def swatch_uvs(vs, mat, lo, hi):
    """UV d'une face unie : projection sur le plan dominant, ramenée dans la pastille de la matière."""
    n = Vector()
    for i in range(len(vs)):
        a, b = vs[i], vs[(i + 1) % len(vs)]
        n += Vector(((a.y - b.y) * (a.z + b.z), (a.z - b.z) * (a.x + b.x), (a.x - b.x) * (a.y + b.y)))
    ax = max(range(3), key=lambda i: abs(n[i]))
    (cu, cv), half = tx.swatch_uv(mat)
    ext = max(hi - lo) or 1.0
    c = (lo + hi) / 2
    out = []
    for v in vs:
        q = (v - c) / ext
        a, b = [(q.y, q.z), (q.x, q.z), (q.x, q.y)][ax]
        out.append((cu + a * 2 * half * 0.95, cv + b * 2 * half * 0.95))
    return out


def make_mesh(name, parts, mats):
    """mats : {"items": matière, "glow": matière} (jeu) ou {nom de pastille: matière} (aperçu)."""
    lo, hi = bounds(parts)
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    uvl = bm.loops.layers.uv.new("UVMap")
    keys = list(mats)
    for p in parts:
        bv = [bm.verts.new(v) for v in p.verts]
        for fi, f in enumerate(p.faces):
            try:
                face = bm.faces.new([bv[i] for i in f])
            except ValueError:
                continue
            k = "glow" if p.mat in GLOW else "items"
            if k not in mats:
                k = keys[0]
            face.material_index = keys.index(k)
            face.smooth = fi < p.smooth
            uvs = p.uvs[fi] if p.uvs else swatch_uvs([p.verts[i] for i in f], p.mat, lo, hi)
            for loop, uv in zip(face.loops, uvs):
                loop[uvl].uv = uv
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    for e in bm.edges:
        e.smooth = all(f.smooth for f in e.link_faces)
    bmesh.ops.triangulate(bm, faces=[f for f in bm.faces if len(f.verts) > 4], ngon_method="EAR_CLIP")
    bm.to_mesh(me)
    bm.free()
    for k in keys:
        me.materials.append(mats[k])
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    return ob


def hull_mesh(name, point_sets, inset=0.0):
    """Une enveloppe convexe fermée par jeu de points (composantes séparées)."""
    me = bpy.data.meshes.new(name)
    bm = bmesh.new()
    for pts in point_sets:
        tmp = bmesh.new()
        for v in pts:
            tmp.verts.new(v)
        bmesh.ops.convex_hull(tmp, input=tmp.verts[:], use_existing_faces=False)
        for v in [v for v in tmp.verts if not v.link_faces]:
            tmp.verts.remove(v)
        if len(tmp.faces) < 4:
            tmp.free()
            continue
        bmesh.ops.recalc_face_normals(tmp, faces=tmp.faces)
        if inset > 0:
            lo = Vector([min(v.co[i] for v in tmp.verts) for i in range(3)])
            hi = Vector([max(v.co[i] for v in tmp.verts) for i in range(3)])
            d = min(inset, 0.2 * min(hi - lo))
            moves = [v.normal.copy() * -d for v in tmp.verts]
            for v, m in zip(tmp.verts, moves):
                v.co += m
        bmesh.ops.triangulate(tmp, faces=tmp.faces)
        idx = {}
        for v in tmp.verts:
            idx[v] = bm.verts.new(v.co)
        for f in tmp.faces:
            try:
                bm.faces.new([idx[v] for v in f.verts])
            except ValueError:
                pass
        tmp.free()
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


def a3_materials():
    out = {}
    for k, rv in (("items", "items"), ("glow", "items_glow")):
        m = bpy.data.materials.new("a3_" + k)
        p = m.a3ob_properties_material
        p.texture_type = "TEX"
        p.texture_path = TEX + "items_co.paa"
        p.material_path = TEX + rv + ".rvmat"
        out[k] = m
    return out


def export_item(name, fn, mass, mats):
    clear_scene()
    parts = build(fn)
    vis = set_lod(make_mesh(name + "_lod1", parts, mats), 0, 1)
    # Ombre : pièces de plus de 3 mm, rentrées de 0,4 mm (pas d'ombre propre en bandes).
    big = [p.verts for p in parts if p.mat != "label" and min(bounds([p])[1] - bounds([p])[0]) > 0.0015]
    set_lod(hull_mesh(name + "_shadow", big, 0.0004), 4, 0)
    # Géométrie et tir : l'objet entier pour les petits ; valise et kits : une enveloppe par grosse pièce.
    lo, hi = bounds(parts)
    if max(hi - lo) > 0.25:
        sets = [p.verts for p in parts if max(bounds([p])[1] - bounds([p])[0]) > 0.05]
    else:
        sets = [[v for p in parts for v in p.verts]]
    geo = hull_mesh(name + "_geo", sets)
    layer = geo.data.attributes.new("a3ob_mass", "FLOAT", "POINT")
    for i in range(len(geo.data.vertices)):
        layer.data[i].value = mass / len(geo.data.vertices)
    set_lod(geo, 6, 0, [("autocenter", "0")])
    set_lod(hull_mesh(name + "_fire", sets), 15, 0)
    out = os.path.join(DATA, name + ".p3d")
    bpy.context.view_layer.objects.active = vis
    res = bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    print("export", res, out, len(vis.data.polygons), "faces")


# --------------------------------------------------------------------------------------------------------------------
# Icônes d'inventaire et aperçus Eden (Cycles) : mêmes textures que le jeu.
# --------------------------------------------------------------------------------------------------------------------
def preview_materials():
    out = {}
    co = bpy.data.images.load(os.path.join(RENDER, "items_co.png"))
    nq = bpy.data.images.load(os.path.join(RENDER, "items_nohq.png"))
    nq.colorspace_settings.name = "Non-Color"
    sm = bpy.data.images.load(os.path.join(RENDER, "items_smdi.png"))
    sm.colorspace_settings.name = "Non-Color"
    for k in ("items", "glow"):
        m = bpy.data.materials.new("pv_" + k)
        m.use_nodes = True
        nt, n = m.node_tree, m.node_tree.nodes
        b = n["Principled BSDF"]
        i1 = n.new("ShaderNodeTexImage")
        i1.image = co
        i1.interpolation = "Cubic"
        nt.links.new(i1.outputs["Color"], b.inputs["Base Color"])
        i2 = n.new("ShaderNodeTexImage")
        i2.image = nq
        nm = n.new("ShaderNodeNormalMap")
        nm.inputs["Strength"].default_value = 0.6
        nt.links.new(i2.outputs["Color"], nm.inputs["Color"])
        nt.links.new(nm.outputs["Normal"], b.inputs["Normal"])
        i3 = n.new("ShaderNodeTexImage")
        i3.image = sm
        sep = n.new("ShaderNodeSeparateColor")
        nt.links.new(i3.outputs["Color"], sep.inputs["Color"])
        inv = n.new("ShaderNodeMath")
        inv.operation = "SUBTRACT"
        inv.inputs[0].default_value = 1.0
        nt.links.new(sep.outputs["Blue"], inv.inputs[1])
        nt.links.new(inv.outputs[0], b.inputs["Roughness"])
        # Métal (laiton, acier) : pastilles très brillantes et intenses (G > 0.7).
        gt = n.new("ShaderNodeMath")
        gt.operation = "GREATER_THAN"
        gt.inputs[1].default_value = 0.7
        nt.links.new(sep.outputs["Green"], gt.inputs[0])
        nt.links.new(gt.outputs[0], b.inputs["Metallic"])
        if k == "glow":
            nt.links.new(i1.outputs["Color"], b.inputs["Emission Color"])
            b.inputs["Emission Strength"].default_value = 3.0
        out[k] = m
    return out


def studio(sc, ground_col=None):
    sc.render.engine = "CYCLES"
    sc.cycles.samples = SAMPLES
    sc.cycles.use_denoising = True
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.look = "AgX - Medium High Contrast"
    sc.view_settings.exposure = -0.6
    world = bpy.data.worlds.new("w")
    sc.world = world
    world.use_nodes = True
    world.node_tree.nodes["Background"].inputs["Color"].default_value = (0.55, 0.6, 0.68, 1)
    world.node_tree.nodes["Background"].inputs["Strength"].default_value = 0.22
    for name, loc, energy, size in (("key", (-0.6, -0.8, 1.1), 45.0, 0.6), ("fill", (0.9, -0.5, 0.5), 12.0, 1.0),
                                    ("rim", (0.2, 1.0, 0.8), 35.0, 0.4)):
        ld = bpy.data.lights.new(name, "AREA")
        ld.energy = energy
        ld.size = size
        lo = bpy.data.objects.new(name, ld)
        lo.location = loc
        lo.rotation_euler = (-Vector(loc)).to_track_quat("-Z", "Y").to_euler()
        sc.collection.objects.link(lo)
    if ground_col is not None:
        me = bpy.data.meshes.new("ground")
        me.from_pydata([(-5, -5, 0), (5, -5, 0), (5, 5, 0), (-5, 5, 0)], [], [(0, 1, 2, 3)])
        g = bpy.data.objects.new("ground", me)
        sc.collection.objects.link(g)
        m = bpy.data.materials.new("ground")
        m.use_nodes = True
        m.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value = ground_col
        m.node_tree.nodes["Principled BSDF"].inputs["Roughness"].default_value = 0.9
        me.materials.append(m)
    else:
        # Icône : fond transparent, mais ombre portée gardée (attrape-ombre).
        me = bpy.data.meshes.new("catcher")
        me.from_pydata([(-5, -5, 0), (5, -5, 0), (5, 5, 0), (-5, 5, 0)], [], [(0, 1, 2, 3)])
        g = bpy.data.objects.new("catcher", me)
        g.is_shadow_catcher = True
        sc.collection.objects.link(g)
        sc.render.film_transparent = True


def frame(sc, ob, res, elev=38.0, az=-35.0, margin=1.12):
    """Caméra orthographique de trois quarts cadrant la boîte englobante."""
    vs = [ob.matrix_world @ v.co for v in ob.data.vertices]
    lo = Vector([min(v[i] for v in vs) for i in range(3)])
    hi = Vector([max(v[i] for v in vs) for i in range(3)])
    c = (lo + hi) / 2
    d = Vector((math.sin(math.radians(az)) * math.cos(math.radians(elev)), -math.cos(math.radians(az)) * math.cos(math.radians(elev)),
                math.sin(math.radians(elev))))
    cd = bpy.data.cameras.new("cam")
    cd.type = "ORTHO"
    co = bpy.data.objects.new("cam", cd)
    sc.collection.objects.link(co)
    co.location = c + d * 3.0
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
    cd.clip_start, cd.clip_end = 0.01, 20
    sc.camera = co
    sc.render.resolution_x, sc.render.resolution_y = res
    return co


def render_item(name, fn, icon, pm):
    from PIL import Image
    if icon is None:
        return
    for mode in ("icon", "preview"):
        bpy.ops.wm.read_homefile(use_empty=True)
        sc = bpy.context.scene
        pm = preview_materials()
        studio(sc, None if mode == "icon" else (0.16, 0.17, 0.15, 1))
        ob = make_mesh(name, build(fn), pm)
        az = ICON_AZ.get(name, -35.0)
        if mode == "icon":
            frame(sc, ob, (512, 512), 30.0, az)
            sc.render.image_settings.file_format = "PNG"
            sc.render.image_settings.color_mode = "RGBA"
            path = os.path.join(RENDER, f"icon_{icon[5:]}.png")
            sc.render.filepath = path
            bpy.ops.render.render(write_still=True)
            Image.open(path).resize((256, 256), Image.LANCZOS).save(path)
        else:
            frame(sc, ob, (455, 256), 26.0, az, 1.5)
            sc.render.image_settings.file_format = "JPEG"
            sc.render.image_settings.quality = 90
            sc.render.filepath = os.path.join(DATA, name + "_preview.jpg")
            bpy.ops.render.render(write_still=True)
        print("render", mode, name)


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    only = os.environ.get("ONLY")
    todo = {k: v for k, v in ITEMS.items() if not only or only in k}
    if os.environ.get("NO_EXPORT") != "1":
        mats = a3_materials()
        for name, (fn, mass, _icon) in todo.items():
            export_item(name, fn, mass, mats)
    if os.environ.get("NO_RENDER") != "1":
        for name, (fn, _mass, icon) in todo.items():
            render_item(name, fn, icon, None)


if __name__ == "__main__":
    main()
