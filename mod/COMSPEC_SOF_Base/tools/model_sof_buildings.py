"""
Bâtiments SOF explorables (mod COMSPEC Base SOF), dans l'esprit des QG de commandement US récents : façades en
panneaux de pierre claire, soubassement brique, bandeaux vitrés, auvent d'entrée, toits plats à acrotère.

  sof_hq        QG 3 niveaux 24 x 14 m : hall vitré, couloir central, 13 bureaux, cage d'escalier jusqu'au toit
  sof_annex     annexe 2 niveaux 18 x 14 m : même construction, sans hall

Chaque bâtiment est praticable : étages et escaliers (LOD roadway), portes animées (Door_N, actions Ouvrir/Fermer),
vitres cassables une à une (points Glass_N, éclats affichés une fois brisée), collision et occultation (géométrie,
géométrie de vue, de tir), ombres, et ruine (DestructBuilding) : dalles effondrées et pans de murs.

    python3 model_sof_buildings.py     (ONLY=nom ; NO_PREVIEW=1 ; RENDER_SAMPLES=n ; NO_EXPORT=1)
Réutilise le constructeur et l'export de model_sof_base.py (Blender bpy + Arma 3 Object Builder).
"""
import math
import os
import random
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import model_sof_base as base  # noqa: E402
import bpy  # noqa: E402
import addon_utils  # noqa: E402
from mathutils import Matrix, Vector  # noqa: E402

TEX = base.TEX
DATA = base.DATA
RENDER = base.RENDER
OUT = os.environ.get("PREVIEW_OUT", RENDER)

base.MATS.update({
    "stone": (TEX + "sof_stone_co.paa", "sof_matte", 2.4, None, "sof_stone_co", 0.7, 0.0),
    "brick": (TEX + "sof_brick_co.paa", "sof_matte", 1.6, None, "sof_brick_co", 0.85, 0.0),
    "plaster": (TEX + "sof_plaster_co.paa", "sof_matte", 2.0, None, "sof_plaster_co", 0.8, 0.0),
    "vinyl": (TEX + "sof_vinyl_co.paa", "sof_paint", 1.5, None, "sof_vinyl_co", 0.45, 0.0),
    "concrete": (TEX + "sof_concrete_co.paa", "sof_matte", 2.0, None, "sof_concrete_co", 0.85, 0.0),
    "roof": (TEX + "sof_roof_co.paa", "sof_matte", 2.0, None, "sof_roof_co", 0.95, 0.0),
    "mullion": ("#(argb,8,8,3)color(0.13,0.15,0.16,1,co)", "sof_frame", 1.0, None, (0.04, 0.05, 0.055), 0.35, 0.6),
    "door": (TEX + "sof_door_co.paa", "sof_paint", 0.0, None, "sof_door_co", 0.5, 0.0),
    "glass": ("#(argb,8,8,3)color(0.45,0.6,0.62,0.35,ca)", "sof_glass", 0.0, None, (0.4, 0.55, 0.58), 0.05, 0.0),
    "shards": (TEX + "sof_glass_broken_ca.paa", "sof_glass", 0.0, None, "sof_glass_broken_ca", 0.05, 0.0),
    "rail": ("#(argb,8,8,3)color(0.5,0.52,0.54,1,co)", "sof_galva", 1.0, None, (0.5, 0.52, 0.54), 0.3, 0.9),
})

FH = 3.6      # hauteur d'étage
SLAB = 0.3    # épaisseur de dalle
BASE = 0.3    # rez-de-chaussée surélevé
WT = 0.3      # mur extérieur
PT = 0.12     # cloison
CORR = 1.2    # demi-largeur du couloir
DOOR_W, DOOR_H = 1.0, 2.1


class BB(base.B):
    """Constructeur de bâtiment : en plus du visuel et de la géométrie, sols praticables (roadway), points de
    vitres (hit-points), boîtes de tir propres aux vitres, compteurs de portes et de vitres."""

    def __init__(self):
        super().__init__()
        self.road, self.hits, self.firebox = [], [], []
        self.doors, self.glass = 0, 0
        self.ruin = False

    def road_quad(self, pts):
        self.road.append([self._t(Vector(p)) for p in pts])

    def floor(self, x0, x1, y0, y1, ztop, mat_top="vinyl", mat_under="plaster", walk=True):
        """Dalle : face supérieure praticable, sous-face en plâtre (plafond de l'étage du dessous)."""
        cx, cy, w, d = (x0 + x1) / 2, (y0 + y1) / 2, x1 - x0, y1 - y0
        if w <= 0.01 or d <= 0.01:
            return
        # Visuel rentré de 5 mm : les chants de dalle ne doivent pas être confondus avec la face extérieure des murs.
        self.box((cx, cy, ztop - SLAB), (w - 0.01, d - 0.01, SLAB - 0.02), mat_under)
        self.box((cx, cy, ztop - 0.02), (w - 0.01, d - 0.01, 0.02), mat_top)
        self.geobox((cx, cy, ztop - SLAB), (w, d, SLAB))
        if walk:
            self.road_quad([(x0, y0, ztop), (x1, y0, ztop), (x1, y1, ztop), (x0, y1, ztop)])


def wall_segments(a0, a1, z0, z1, openings):
    """Découpe un mur [a0,a1]x[z0,z1] autour d'ouvertures (oa0, oa1, oz0, oz1) : liste de rectangles pleins."""
    cuts = sorted({a0, a1} | {o[0] for o in openings} | {o[1] for o in openings})
    rects = []
    for u0, u1 in zip(cuts, cuts[1:]):
        if u1 - u0 < 1e-4:
            continue
        holes = sorted((o[2], o[3]) for o in openings if o[0] <= u0 + 1e-4 and o[1] >= u1 - 1e-4)
        z = z0
        for h0, h1 in holes:
            if h0 > z + 1e-4:
                rects.append((u0, u1, z, h0))
            z = max(z, h1)
        if z1 > z + 1e-4:
            rects.append((u0, u1, z, z1))
    return rects


def wall(b, axis, c, a0, a1, z0, z1, t, mat_out, mat_in=None, openings=(), out_sign=1, geo=True, mat_fn=None):
    """Mur plein le long de x (axis='x', plan y=c) ou de y (axis='y', plan x=c), épaisseur t centrée sur c.
    mat_in : parement intérieur (côté opposé à out_sign) ; mat_fn(z0, z1) choisit le parement extérieur par bande."""
    for u0, u1, zz0, zz1 in wall_segments(a0, a1, z0, z1, list(openings)):
        mid, ln, h = (u0 + u1) / 2, u1 - u0, zz1 - zz0
        m = mat_fn(zz0, zz1) if mat_fn else mat_out
        if axis == "x":
            b.box((mid, c, zz0), (ln, t, h), m)
            if mat_in:
                b.box((mid, c - out_sign * (t / 2 + 0.005), zz0), (ln, 0.01, h), mat_in)
            if geo:
                b.geobox((mid, c, zz0), (ln, t, h))
        else:
            b.box((c, mid, zz0), (t, ln, h), m)
            if mat_in:
                b.box((c - out_sign * (t / 2 + 0.005), mid, zz0), (0.01, ln, h), mat_in)
            if geo:
                b.geobox((c, mid, zz0), (t, ln, h))


def door(b, axis, c, a, z, w=DOOR_W, h=DOOR_H, hinge=-1, swing=1, mat="door", glass=False):
    """Battant Door_N dans une baie (axis : direction du mur), charnière côté hinge (-1 : a - w/2)."""
    b.doors += 1
    n = b.doors
    name = f"Door_{n}"
    hx = a + hinge * (w / 2 - 0.02)
    lw = w - 0.06
    if axis == "x":
        b.push((hx, c, z), 0.0 if hinge < 0 else math.pi, groups=(name,))
    else:
        b.push((c, hx, z), math.pi / 2 if hinge < 0 else -math.pi / 2, groups=(name,))
    if glass:
        for xx in (0.03, lw - 0.03):
            b.box((xx, 0, 0), (0.06, 0.05, h - 0.02), "mullion")
        for zz in (0.0, h - 0.08):
            b.box((lw / 2, 0, zz), (lw, 0.05, 0.08), "mullion")
        b.quad([(0.06, 0, 0.08), (lw - 0.06, 0, 0.08), (lw - 0.06, 0, h - 0.08), (0.06, 0, h - 0.08)],
               [(0, 0), (1, 0), (1, 1), (0, 1)], "glass")
        b.box((lw - 0.15, -0.05, 1.0), (0.03, 0.04, 0.3), "rail")
    else:
        b.box((lw / 2, 0, 0.0), (lw, 0.045, h - 0.02), mat)
        for s in (-1, 1):
            b.box((lw - 0.1, s * 0.045, 1.0), (0.12, 0.03, 0.02), "rail")
    b.geobox((lw / 2, 0, 0.0), (lw, 0.05, h - 0.02))
    b.pop()
    if axis == "x":
        b.point(name + "_axis", (hx, c, z + 0.1))
        b.point(name + "_axis", (hx, c, z + h - 0.1))
        b.point(name + "_trigger", (a, c, z + 1.0))
    else:
        b.point(name + "_axis", (c, hx, z + 0.1))
        b.point(name + "_axis", (c, hx, z + h - 0.1))
        b.point(name + "_trigger", (c, a, z + 1.0))
    # Encadrement
    if axis == "x":
        for s in (-1, 1):
            b.box((a + s * (w / 2 + 0.03), c, z), (0.06, 0.16, h + 0.03), "mullion")
        b.box((a, c, z + h), (w + 0.12, 0.16, 0.06), "mullion")
    else:
        for s in (-1, 1):
            b.box((c, a + s * (w / 2 + 0.03), z), (0.16, 0.06, h + 0.03), "mullion")
        b.box((c, a, z + h), (0.16, w + 0.12, 0.06), "mullion")
    _ = swing
    return n


def window(b, axis, c, a0, a1, z0, z1, mullions=1):
    """Vitre cassable Glass_N (visuel, éclats, boîte de tir, point d'impact) + menuiseries ; plan du mur à c."""
    b.glass += 1
    n = b.glass
    hide, unhide = f"Glass_{n}_hide", f"Glass_{n}_unhide"
    am = (a0 + a1) / 2
    pts = lambda u0, u1, w0, w1, off=0.0: (  # noqa: E731
        [(u0, c + off, w0), (u1, c + off, w0), (u1, c + off, w1), (u0, c + off, w1)] if axis == "x"
        else [(c + off, u1, w0), (c + off, u0, w0), (c + off, u0, w1), (c + off, u1, w1)])
    b.quad(pts(a0, a1, z0, z1), [(0, 0), (1, 0), (1, 1), (0, 1)], "glass", groups=(hide,))
    # Éclats : restes de verre accrochés au cadre (texture à trou), visibles une fois la vitre brisée.
    b.quad(pts(a0, a1, z0, z1, 0.004), [(0, 0), (1, 0), (1, 1), (0, 1)], "shards", groups=(unhide,))
    if axis == "x":
        b.firebox.append((b._t(Vector((am, c, z0))), (a1 - a0, 0.03, z1 - z0), b.stack[-1].to_3x3(), {hide}))
        b.hits.append((f"Glass_{n}", b._t(Vector((am, c, (z0 + z1) / 2)))))
    else:
        b.firebox.append((b._t(Vector((c, am, z0))), (0.03, a1 - a0, z1 - z0), b.stack[-1].to_3x3(), {hide}))
        b.hits.append((f"Glass_{n}", b._t(Vector((c, am, (z0 + z1) / 2)))))
    t = 0.07
    for k in range(mullions + 1):
        u = a0 + (a1 - a0) * k / mullions
        if axis == "x":
            b.box((u, c, z0), (t, 0.12, z1 - z0), "mullion")
        else:
            b.box((c, u, z0), (0.12, t, z1 - z0), "mullion")
    for zz in (z0 - t / 2, z1 - t / 2):
        if axis == "x":
            b.box((am, c, zz), (a1 - a0 + t, 0.12, t), "mullion")
        else:
            b.box((c, am, zz), (0.12, a1 - a0 + t, t), "mullion")
    return n


def stair_flight(b, x0, x1, ya, yb, za, zb, steps=10):
    """Volée droite de ya (bas, za) à yb (haut, zb) : marches visuelles, rampe praticable et collision en pente."""
    w = x1 - x0
    cx = (x0 + x1) / 2
    run = (yb - ya) / steps
    rise = (zb - za) / steps
    for i in range(steps):
        y = ya + run * (i + 0.5)
        z = za + rise * i
        b.box((cx, y, z), (w, abs(run), rise), "concrete")
    # Paillasse (sous-face) et limons
    L = math.hypot(yb - ya, zb - za)
    ang = math.atan2(zb - za, abs(yb - ya)) * (1 if yb > ya else -1)
    rot = Matrix.Rotation(-ang if yb > ya else ang, 3, "X")
    b.box((cx, (ya + yb) / 2, (za + zb) / 2 - 0.2), (w, L, 0.15), "plaster", rot=rot)
    # Rampe praticable et collision
    b.road_quad([(x0, ya, za), (x1, ya, za), (x1, yb, zb), (x0, yb, zb)])
    b.geo.append((b._t(Vector((cx, (ya + yb) / 2, (za + zb) / 2 - 0.12))), (w, L, 0.12), b.stack[-1].to_3x3() @ rot, set()))
    # Main courante
    b.beam((x1 - 0.04, ya, za + 0.95), (x1 - 0.04, yb, zb + 0.95), 0.04, "rail")


def building(b, nb=8, floors=3, lobby=True):
    Lx, W = nb * 3.0, 14.0
    X0, X1, Y0, Y1 = -Lx / 2, Lx / 2, -W / 2, W / 2
    xi0, xi1, yi0, yi1 = X0 + WT, X1 - WT, Y0 + WT, Y1 - WT
    top = BASE + floors * FH
    # Cage d'escalier (côté sud-est) : volée A montante vers le sud, palier, volée B qui revient vers le couloir.
    sx0, sx1 = xi1 - 3.0, xi1
    ya, yl = -CORR - 0.8, -CORR - 0.8 - 3.0  # départ des volées, début du palier
    # -- dalles
    b.box((0, 0, 0), (Lx, W, BASE - 0.02), "concrete")
    b.geobox((0, 0, 0), (Lx, W, BASE))
    for k in range(floors + 1):
        z = BASE + k * FH
        mat_top = "roof" if k == floors else ("vinyl" if k else "vinyl")
        if k == 0:
            b.box((0, 0, z - 0.02), (xi1 - xi0, yi1 - yi0, 0.02), "vinyl")
            b.road_quad([(xi0, yi0, z), (xi1, yi0, z), (xi1, yi1, z), (xi0, yi1, z)])
            continue
        # trémie au-dessus de la cage (hors bande d'arrivée)
        b.floor(X0, sx0, Y0, Y1, z, mat_top)
        b.floor(sx0, X1, ya, Y1, z, mat_top)
        b.floor(sx0, X1, Y0, Y0 + WT, z, mat_top, walk=False)
        b.floor(X1 - WT, X1, Y0 + WT, ya, z, mat_top, walk=False)
    # -- façades
    def facade_mat(z0, z1):
        return "brick" if z1 <= BASE + 0.95 else "stone"

    for k in range(floors):
        z = BASE + k * FH
        zs, zt = z + 0.9, z + 2.6
        for side in (-1, 1):
            yc = side * (W / 2 - WT / 2)
            ops = []
            for i in range(nb):
                a0, a1 = X0 + i * 3 + 0.35, X0 + (i + 1) * 3 - 0.35
                is_lobby = lobby and k == 0 and side < 0 and X0 + i * 3 >= -6 - 1e-6 and X0 + (i + 1) * 3 <= 6 + 1e-6
                if is_lobby:
                    a0, a1 = X0 + i * 3 + 0.05, X0 + (i + 1) * 3 - 0.05
                    ops.append((a0, a1, z, z + 3.1))
                    mid = (a0 + a1) / 2
                    if abs(mid) < 1.6:  # baie d'entrée : deux portes vitrées
                        door(b, "x", yc, mid - 0.7, z, w=1.35, h=2.4, hinge=-1, glass=True)
                        door(b, "x", yc, mid + 0.7, z, w=1.35, h=2.4, hinge=1, glass=True)
                        window(b, "x", yc, a0, a1, z + 2.5, z + 3.05)
                    else:
                        window(b, "x", yc, a0, a1, z + 0.08, z + 3.05, mullions=2)
                    continue
                ops.append((a0, a1, zs, zt))
                window(b, "x", yc, a0, a1, zs, zt)
            wall(b, "x", yc, X0, X1, z, z + FH, WT, "stone", "plaster", ops, out_sign=side, mat_fn=facade_mat)
        for side in (-1, 1):
            xc = side * (Lx / 2 - WT / 2)
            ops = []
            for yy in (-3.5, 3.5):
                ops.append((yy - 1.2, yy + 1.2, zs, zt))
                window(b, "y", xc, yy - 1.2, yy + 1.2, zs, zt)
            if side < 0 and k == 0:  # issue de secours au bout du couloir
                ops.append((-0.5, 0.5, z, z + DOOR_H))
                door(b, "y", xc, 0.0, z, hinge=-1)
            wall(b, "y", xc, Y0 + WT, Y1 - WT, z, z + FH, WT, "stone", "plaster", ops, out_sign=side, mat_fn=facade_mat)
    # acrotère et corniche
    for side in (-1, 1):
        b.box((0, side * (W / 2 - 0.15), top), (Lx, 0.3, 1.0), "stone")
        b.geobox((0, side * (W / 2 - 0.15), top), (Lx, 0.3, 1.0))
        b.box((side * (Lx / 2 - 0.15), 0, top), (0.3, W - 0.6, 1.0), "stone")
        b.geobox((side * (Lx / 2 - 0.15), 0, top), (0.3, W - 0.6, 1.0))
        b.box((0, side * (W / 2 + 0.1), top + 0.85), (Lx + 0.4, 0.2, 0.15), "stone")
    # bandeaux horizontaux entre étages (relief de façade)
    for k in range(1, floors):
        z = BASE + k * FH - 0.35
        for side in (-1, 1):
            b.box((0, side * (W / 2 + 0.06), z), (Lx + 0.12, 0.12, 0.35), "stone")
            b.box((side * (Lx / 2 + 0.06), 0, z), (0.12, W, 0.35), "stone")
    # -- intérieur
    offices = [(X0 + WT, X0 + 6), (X0 + 6, X0 + 12), (X0 + 12, X0 + 18), (X0 + 18, X1 - WT)] if nb == 8 else \
              [(X0 + WT, X0 + 6), (X0 + 6, X0 + 12), (X0 + 12, X1 - WT)]
    for k in range(floors):
        z = BASE + k * FH
        zc = z + FH - SLAB
        for side in (-1, 1):
            yc = side * CORR
            ops = []
            rooms = list(offices)
            for i, (r0, r1) in enumerate(rooms):
                if side < 0 and r1 > sx0 + 1e-6:
                    r1 = sx0 - PT / 2
                    rooms[i] = (r0, r1)
                if side < 0 and lobby and k == 0 and r0 >= -6 - 1e-6 and r1 <= 6 + 1e-6:
                    continue  # hall ouvert
                dx = r1 - 0.9
                ops.append((dx - DOOR_W / 2, dx + DOOR_W / 2, z, z + DOOR_H))
                door(b, "x", yc, dx, z, hinge=1 if side > 0 else -1)
            if side < 0:
                ops.append((sx0 + 0.1, sx1 - 0.1, z, z + 2.4))  # accès escalier
                if lobby and k == 0:
                    ops.append((-6 + 0.2, 6 - 0.2, z, z + 2.8))  # hall
            wall(b, "x", yc, xi0, xi1, z, zc, PT, "plaster", None, ops)
            # cloisons entre bureaux
            for (r0, r1) in rooms[:-1]:
                if side < 0 and lobby and k == 0 and -6 - 1e-6 <= r1 <= 6 + 1e-6 and r1 != -6 and r1 != 6:
                    continue
                y_a, y_b = (CORR, yi1) if side > 0 else (yi0, -CORR)
                wall(b, "y", r1, y_a, y_b, z, zc, PT, "plaster")
        # cloison de la cage d'escalier
        wall(b, "y", sx0, yi0, -CORR, z, zc, PT, "plaster")
        # plafonniers (bandes claires)
        for x in range(int(X0) + 3, int(X1) - 1, 6):
            b.box((x, 0, zc - 0.03), (1.2, 0.3, 0.02), "plaster")
    # -- escaliers jusqu'au toit
    xa0, xa1 = sx0 + 0.1, sx0 + 1.45
    xb0, xb1 = sx1 - 1.45, sx1 - 0.1
    for k in range(floors):
        z = BASE + k * FH
        stair_flight(b, xa0, xa1, ya, yl, z, z + FH / 2)
        b.floor(sx0, sx1, yi0, yl, z + FH / 2, "concrete", "plaster")
        stair_flight(b, xb0, xb1, yl, ya, z + FH / 2, z + FH)
        b.box(((xa1 + xb0) / 2, (ya + yl) / 2, z), (xb0 - xa1, abs(yl - ya), FH), "plaster")
        b.geobox(((xa1 + xb0) / 2, (ya + yl) / 2, z), (xb0 - xa1, abs(yl - ya), FH))
    # édicule d'accès au toit
    zt = top
    ops = [(xb0, xb1, zt, zt + DOOR_H)]
    wall(b, "x", -CORR - 0.06, sx0 - 0.06, X1, zt, zt + 3.0, PT, "stone", "plaster", ops, out_sign=1)
    door(b, "x", -CORR - 0.06, (xb0 + xb1) / 2, zt, w=xb1 - xb0, hinge=1)
    wall(b, "y", sx0 - 0.06, Y0 + WT, -CORR, zt, zt + 3.0, PT, "stone", "plaster", out_sign=-1)
    # côtés est et sud : dans le prolongement de l'acrotère (1 m), qui tient lieu de bas de mur
    wall(b, "y", X1 - WT / 2, Y0, -CORR, zt + 1.0, zt + 3.0, WT, "stone", "plaster", out_sign=1)
    wall(b, "x", Y0 + WT / 2, sx0 - 0.06, X1 - WT, zt + 1.0, zt + 3.0, WT, "stone", "plaster", out_sign=-1)
    b.floor(sx0 - 0.12, X1, Y0, -CORR, zt + 3.2, "roof", "plaster", walk=True)
    # -- auvent d'entrée, perron, équipements de toit
    if lobby:
        b.box((0, Y0 - 1.6, BASE + 3.5), (9.0, 3.4, 0.4), "stone")
        for x in (-4.2, 4.2):
            b.box((x, Y0 - 3.0, 0), (0.4, 0.4, BASE + 3.5), "stone")
            b.geobox((x, Y0 - 3.0, 0), (0.4, 0.4, BASE + 3.5))
        b.box((0, Y0 - 1.0, 0), (7.0, 2.0, BASE - 0.15), "concrete")
        b.box((0, Y0 - 2.4, 0), (7.0, 0.8, 0.15), "concrete")
        b.geobox((0, Y0 - 1.0, 0), (7.0, 2.0, BASE - 0.02))
        b.road_quad([(-3.5, Y0 - 2.0, BASE - 0.02), (3.5, Y0 - 2.0, BASE - 0.02), (3.5, Y0, BASE - 0.02), (-3.5, Y0, BASE - 0.02)])
        b.road_quad([(-3.5, Y0 - 2.8, 0.15), (3.5, Y0 - 2.8, 0.15), (3.5, Y0 - 2.0, 0.15), (-3.5, Y0 - 2.0, 0.15)])
    b.box((X0 - 0.9, 0, 0), (1.8, 2.0, BASE - 0.02), "concrete")
    b.geobox((X0 - 0.9, 0, 0), (1.8, 2.0, BASE - 0.02))
    b.road_quad([(X0 - 1.8, -1, BASE - 0.02), (X0, -1, BASE - 0.02), (X0, 1, BASE - 0.02), (X0 - 1.8, 1, BASE - 0.02)])
    for x, y in ((X0 + 4, 3.0), (X0 + 7, 3.0)):
        b.box((x, y, top), (1.6, 1.2, 1.2), "rail")
        b.geobox((x, y, top), (1.6, 1.2, 1.2))


def m_hq(b):
    building(b, 8, 3, True)


def m_annex(b):
    building(b, 6, 2, False)


BUILDINGS = {"sof_hq": (m_hq, 60000.0), "sof_annex": (m_annex, 40000.0)}


# ---------------------------------------------------------------------------------------------------------- ruine
def ruin(name):
    """Ruine : dalles affaissées en mille-feuille inclinées, pans de façade debout, gravats."""
    fn, _m = BUILDINGS[name]
    b = BB()
    fn(b)
    r = random.Random(sum(map(ord, name)))
    out = BB()
    keep = [p for p in b.parts if p.mat not in ("glass", "shards", "door", "rail") and not any(g.startswith("Door_") for g in p.groups)]
    for p in keep:
        c = sum(p.verts, Vector()) / len(p.verts)
        edge = max(abs(c.x) / 12.0, abs(c.y) / 7.0)
        if edge > 0.93 and c.z < 4.5 and r.random() < 0.55:
            vs = [Vector((v.x, v.y, v.z)) for v in p.verts]  # pan de façade resté debout
        else:
            if r.random() < 0.25:
                continue
            s = 0.18
            tilt = Matrix.Rotation(r.uniform(-0.12, 0.12), 3, "X") @ Matrix.Rotation(r.uniform(-0.1, 0.1), 3, "Y")
            vs = [Vector((v.x, v.y, v.z * s)) for v in p.verts]
            cc = sum(vs, Vector()) / len(vs)
            vs = [cc + tilt @ (v - cc) for v in vs]
        out.parts.append(base.Part(vs, p.faces, p.mat))
    for _i in range(40):
        x, y = r.uniform(-13, 13), r.uniform(-8, 8)
        out.box((x, y, 0), (r.uniform(0.5, 2.0), r.uniform(0.5, 2.0), r.uniform(0.2, 0.9)), r.choice(["concrete", "stone", "brick"]),
                rot=Matrix.Rotation(r.uniform(0, 3), 3, "Z") @ Matrix.Rotation(r.uniform(-0.3, 0.3), 3, "X"))
    out.geobox((0, 0, 0), (24.0 if name == "sof_hq" else 18.0, 14.0, 2.2))
    return out


# ---------------------------------------------------------------------------------------------------------- export
def road_mesh(name, quads):
    me = bpy.data.meshes.new(name)
    vs, fs = [], []
    for q in quads:
        i = len(vs)
        vs += [tuple(v) for v in q]
        fs.append((i, i + 1, i + 2, i + 3))
    me.from_pydata(vs, [], fs)
    me.update()
    for p in me.polygons:
        if p.normal.z < 0:
            p.flip()
    ob = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(ob)
    return ob


def hit_mesh(name, hits):
    return base.mem_mesh(name, hits)


def export_building(name, mats):
    fn, mass = BUILDINGS[name]
    base.clear_scene()
    b = BB()
    fn(b)
    vis = base.set_lod(base.make_mesh(name + "_lod1", b.parts, mats, ()), 0, 1)
    sh = base.make_mesh(name + "_shadow", b.parts, mats, (), with_uv=False, shadow=True)
    sh.data.materials.clear()
    base.set_lod(sh, 4, 0)
    geo = base.geo_mesh(name + "_geo", b.geo, mass)
    base.set_lod(geo, 6, 0, [("autocenter", "0"), ("class", "house"), ("map", "building"), ("damage", "building")])
    base.set_lod(base.geo_mesh(name + "_view", b.geo, mass), 14, 0)
    base.set_lod(base.geo_mesh(name + "_fire", b.geo + b.firebox, mass), 15, 0)
    base.set_lod(road_mesh(name + "_road", b.road), 11, 0)
    base.set_lod(base.mem_mesh(name + "_mem", b.mem), 9, 0)
    base.set_lod(hit_mesh(name + "_hit", b.hits), 13, 0)
    out = os.path.join(DATA, name + ".p3d")
    bpy.context.view_layer.objects.active = vis
    res = bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    print("export", res, out, len(vis.data.polygons), "faces", b.doors, "doors", b.glass, "glass")
    # Ruine
    base.clear_scene()
    rb = ruin(name)
    vis = base.set_lod(base.make_mesh(name + "_ruin_lod1", rb.parts, mats, ()), 0, 1)
    sh = base.make_mesh(name + "_ruin_shadow", rb.parts, mats, (), with_uv=False, shadow=True)
    sh.data.materials.clear()
    base.set_lod(sh, 4, 0)
    base.set_lod(base.geo_mesh(name + "_ruin_geo", rb.geo, mass), 6, 0, [("autocenter", "0")])
    base.set_lod(base.geo_mesh(name + "_ruin_fire", rb.geo, mass), 15, 0)
    out = os.path.join(DATA, name + "_ruin.p3d")
    bpy.context.view_layer.objects.active = vis
    bpy.ops.a3ob.export_p3d(filepath=out, generate_components=True)
    return b


MODEL_CFG_HEAD = """// COMSPEC Base SOF : sélections retexturables, portes et vitres animées.
// Généré par tools/model_sof_buildings.py (partie bâtiments) : ne pas modifier à la main, relancer le script.
class CfgSkeletons {
    class Default { isDiscrete=1; skeletonInherit=""; skeletonBones[]={}; };
    class SOF_Door_Skel: Default { skeletonBones[]={"door",""}; };
    class SOF_Locker_Skel: Default { skeletonBones[]={"door_l","","door_r",""}; };
%SKELETONS%
};
class CfgModels {
    class Default { sectionsInherit=""; sections[]={}; skeletonName=""; };
    class sof_cage_panel: Default { sections[]={"frame","mesh"}; };
    class sof_cage_door: Default {
        sections[]={"frame","mesh"}; skeletonName="SOF_Door_Skel";
        class Animations {
            // Porte vers l'extérieur (côté -y du modèle) ; si elle s'ouvre vers l'intérieur en jeu, inverser angle1.
            class door { type="rotation"; source="door"; selection="door"; axis="door_axis"; memory=1; minValue=0; maxValue=1; angle0=0; angle1=-1.75; };
        };
    };
    class sof_cage_4x3: sof_cage_door {};
    class sof_mesh_locker: Default {
        sections[]={"frame","mesh"}; skeletonName="SOF_Locker_Skel";
        class Animations {
            class door_l { type="rotation"; source="door"; selection="door_l"; axis="door_l_axis"; memory=1; minValue=0; maxValue=1; angle0=0; angle1=-1.75; };
            class door_r: door_l { selection="door_r"; axis="door_r_axis"; angle1=1.75; };
        };
    };
    class sof_sign_large: Default { sections[]={"sign"}; };
    class sof_sign_small: sof_sign_large {};
    class sof_sign_stand: sof_sign_large {};
    class sof_wall_flag: Default { sections[]={"flag"}; };
%MODELS%
};
"""

CLASSES = {
    "sof_hq": ("COMSPEC_SOF_HQ", "$STR_COMSPEC_SOF_HQ", 30),
    "sof_annex": ("COMSPEC_SOF_Annex", "$STR_COMSPEC_SOF_ANNEX", 22),
}


def write_config_fragment(stats):
    """Écrit data/model.cfg (complet) et buildings.hpp (classes CfgVehicles des bâtiments, inclus par config.cpp)."""
    skels, models, veh = [], [], ["// Généré par tools/model_sof_buildings.py : portes animées, vitres cassables, ruines."]
    for name, (doors, glass) in stats.items():
        bones, anims = [], []
        for i in range(1, doors + 1):
            bones += [f'"Door_{i}"', '""']
            anims.append(f'            class Door_{i}_rot {{ type="rotation"; source="Door_{i}_source"; selection="Door_{i}"; axis="Door_{i}_axis"; memory=1; minValue=0; maxValue=1; angle0=0; angle1=-1.75; }};')
        for i in range(1, glass + 1):
            bones += [f'"Glass_{i}_hide"', '""', f'"Glass_{i}_unhide"', '""']
            anims.append(f'            class Glass_{i}_hide {{ type="hide"; source="Glass_{i}_source"; selection="Glass_{i}_hide"; minValue=0; maxValue=1; hideValue=0.99; }};')
            anims.append(f'            class Glass_{i}_unhide {{ type="hide"; source="Glass_{i}_source"; selection="Glass_{i}_unhide"; minValue=0; maxValue=1; hideValue=0; unHideValue=0.99; }};')
        skels.append(f"    class {name}_skel: Default {{ skeletonBones[]={{{','.join(bones)}}}; }};")
        models.append(f"    // {name} : {doors} portes, {glass} vitres")
        models.append(f'    class {name}: Default {{\n        skeletonName="{name}_skel";\n        class Animations {{')
        models += anims
        models.append("        };\n    };")
        models.append(f"    class {name}_ruin: Default {{}};")
        cls, disp, msize = CLASSES[name]
        p = "\\z\\comspec_sof\\addons\\main\\data\\"
        v = [f"class {cls}: COMSPEC_SOF_BuildingBase {{",
             f'    scope=2; scopeCurator=2; displayName="{disp}"; mapSize={msize}; numberOfDoors={doors}; numberOfWindows={glass};',
             f'    model="{p}{name}.p3d"; editorPreview="{p}{name}_preview.jpg";',
             "    class DestructionEffects: DestructionEffects {",
             f'        class Ruin1 {{ simulation="ruin"; type="{p}{name}_ruin.p3d"; position=""; intensity=1; interval=1; lifeTime=1; }};',
             "    };",
             "    class AnimationSources {"]
        for i in range(1, doors + 1):
            v.append(f'        class Door_{i}_source {{ source="user"; initPhase=0; animPeriod=1; sound="GenericDoorsSound"; soundPosition="Door_{i}_trigger"; }};')
        for i in range(1, glass + 1):
            v.append(f'        class Glass_{i}_source {{ source="Hit"; hitpoint="Glass_{i}_hitpoint"; raw=1; }};')
        v.append("    };")
        v.append("    class UserActions {")
        for i in range(1, doors + 1):
            v.append(f'        class OpenDoor_{i} {{ displayName="$STR_COMSPEC_SOF_OPEN"; position="Door_{i}_trigger"; radius=1.75; onlyForPlayer=0; priority=4; '
                     f"condition=\"((this animationSourcePhase 'Door_{i}_source') < 0.5) && (cameraOn isKindOf 'CAManBase')\"; "
                     f"statement=\"this animateSource ['Door_{i}_source', 1]\"; }};")
            v.append(f'        class CloseDoor_{i}: OpenDoor_{i} {{ displayName="$STR_COMSPEC_SOF_CLOSE"; '
                     f"condition=\"((this animationSourcePhase 'Door_{i}_source') >= 0.5) && (cameraOn isKindOf 'CAManBase')\"; "
                     f"statement=\"this animateSource ['Door_{i}_source', 0]\"; }};")
        v.append("    };")
        v.append("    class Hitpoints {")
        for i in range(1, glass + 1):
            v.append(f'        class Glass_{i}_hitpoint {{ armor=0.001; material=-1; name="Glass_{i}"; visual="Glass_{i}_hide"; passThrough=0; radius=0.25; '
                     f'convexComponent="Glass_{i}_hide"; explosionShielding=3; minimalHit=0; }};')
        v.append("    };")
        v.append("};")
        v.append(f'class {cls}_ruins: Ruins_F {{ scope=1; scopeCurator=0; author="COMSPEC"; displayName="{disp}"; model="{p}{name}_ruin.p3d"; }};')
        veh += v
    with open(os.path.join(DATA, "model.cfg"), "w", encoding="utf-8", newline="\n") as fh:
        fh.write(MODEL_CFG_HEAD.replace("%SKELETONS%", "\n".join(skels)).replace("%MODELS%", "\n".join(models)))
    with open(os.path.join(DATA, "..", "buildings.hpp"), "w", encoding="utf-8", newline="\n") as fh:
        fh.write("\n".join(veh) + "\n")


# ---------------------------------------------------------------------------------------------------------- aperçus
def previews(names):
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.samples = int(os.environ.get("RENDER_SAMPLES", "64"))
    sc.cycles.use_denoising = True
    sc.cycles.transparent_max_bounces = 32
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.look = "AgX - Medium High Contrast"
    sc.view_settings.exposure = -2.6
    world = bpy.data.worlds.new("sky")
    sc.world = world
    world.use_nodes = True
    nt = world.node_tree
    sky = nt.nodes.new("ShaderNodeTexSky")
    for t in ("MULTIPLE_SCATTERING", "SINGLE_SCATTERING", "NISHITA", "HOSEK_WILKIE"):
        try:
            sky.sky_type = t
            break
        except TypeError:
            continue
    try:
        sky.sun_elevation = math.radians(40)
        sky.sun_rotation = math.radians(150)
    except AttributeError:
        pass
    nt.links.new(sky.outputs["Color"], nt.nodes["Background"].inputs["Color"])
    nt.nodes["Background"].inputs["Strength"].default_value = 0.4
    sun = bpy.data.lights.new("sun", "SUN")
    sun.energy = 4.0
    so = bpy.data.objects.new("sun", sun)
    so.rotation_euler = (math.radians(50), 0, math.radians(20))
    sc.collection.objects.link(so)
    pm = {k: base.preview_material(k) for k in base.MATS}
    g = pm["glass"]
    bs = g.node_tree.nodes["Principled BSDF"]
    bs.inputs["Base Color"].default_value = (0.32, 0.42, 0.46, 1)
    bs.inputs["Metallic"].default_value = 0.75
    bs.inputs["Roughness"].default_value = 0.04
    bs.inputs["Coat Weight"].default_value = 1.0
    me = bpy.data.meshes.new("ground")
    me.from_pydata([(-300, -300, 0), (300, -300, 0), (300, 300, 0), (-300, 300, 0)], [], [(0, 1, 2, 3)])
    gr = bpy.data.objects.new("ground", me)
    sc.collection.objects.link(gr)
    gm = bpy.data.materials.new("lawn")
    gm.use_nodes = True
    gm.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value = (0.08, 0.14, 0.04, 1)
    me.materials.append(gm)
    cd = bpy.data.cameras.new("cam")
    cam = bpy.data.objects.new("cam", cd)
    sc.collection.objects.link(cam)
    sc.camera = cam
    shots = {
        "sof_hq": [("exterieur", (22, -30, 5.5), (0, 0, 4.5), 30, (1600, 900)),
                   ("hall", (-4.5, -4.8, 1.9), (5.0, 0.0, 1.6), 18, (1600, 900)),
                   ("couloir", (-11.0, 0.0, BASE + FH + 1.6), (8, 0, BASE + FH + 1.2), 20, (1600, 900)),
                   ("escalier", (10.3, -1.5, BASE + FH + 1.75), (9.4, -5.6, BASE + FH + 0.2), 14, (1600, 900)),
                   ("eden", (22, -30, 5.5), (0, 0, 4.5), 30, (455, 256))],
        "sof_annex": [("exterieur", (-18, -24, 4.0), (0, 0, 3.5), 30, (1600, 900)),
                      ("eden", (-18, -24, 4.0), (0, 0, 3.5), 30, (455, 256))],
    }
    if os.environ.get("CAM"):  # CAM="x,y,z;tx,ty,tz" : vue de contrôle unique
        loc, tgt = ([float(v) for v in part.split(",")] for part in os.environ["CAM"].split(";"))
        shots = {n: [("controle", tuple(loc), tuple(tgt), 30, (800, 450))] for n in shots}
    only_shot = os.environ.get("SHOT")
    if only_shot:
        shots = {n: [s for s in v if s[0] == only_shot] for n, v in shots.items()}
    for name in names:
        for ob in [o for o in sc.objects if o.name.startswith("pv_")]:
            bpy.data.objects.remove(ob, do_unlink=True)
        b = BB()
        BUILDINGS[name][0](b)
        parts, seen = [], set()
        for p in b.parts:
            if any(gg.endswith("_unhide") for gg in p.groups):
                continue
            # Les quads double face (vitres) sont deux faces confondues : Cycles les rend noires, on n'en garde qu'une.
            key = (p.mat, frozenset(tuple(round(x, 3) for x in v) for v in p.verts))
            if len(p.verts) == 4 and key in seen:
                continue
            seen.add(key)
            parts.append(p)
        base.make_mesh("pv_" + name, parts, pm, ())
        # éclairage intérieur : un plafonnier par pièce (grille 3 x 3 m), invisible à la caméra
        for k in range(3):
            for x in range(-10, 11, 4):
                for y in (-4.5, 0.0, 4.5):
                    il = bpy.data.lights.new("pv_int", "POINT")
                    il.energy = 180
                    il.shadow_soft_size = 0.4
                    lo = bpy.data.objects.new("pv_int", il)
                    lo.location = (x, y, BASE + k * FH + FH - SLAB - 0.35)
                    lo.visible_camera = False
                    sc.collection.objects.link(lo)
        for shot, loc, tgt, lens, res in shots[name]:
            cd.lens = lens
            cam.location = loc
            cam.rotation_euler = (Vector(tgt) - Vector(loc)).to_track_quat("-Z", "Y").to_euler()
            sc.render.resolution_x, sc.render.resolution_y = res
            sc.render.image_settings.file_format = "JPEG"
            sc.render.image_settings.quality = 90
            interior = shot in ("hall", "couloir", "escalier")
            sc.view_settings.exposure = -1.9 if interior else -2.6
            if shot == "eden":
                sc.render.filepath = os.path.join(DATA, name + "_preview.jpg")
            else:
                sc.render.filepath = os.path.join(OUT, f"{name}_{shot}.jpg")
            bpy.ops.render.render(write_still=True)
            print("preview", sc.render.filepath, flush=True)


def main():
    d = bpy.utils.user_resource("SCRIPTS", path="addons", create=True)
    sys.path.insert(0, d)
    if not addon_utils.enable("Arma3ObjectBuilder", default_set=True):
        raise SystemExit("Extension Arma 3 Object Builder introuvable dans " + d)
    only = os.environ.get("ONLY")
    names = [n for n in BUILDINGS if not only or only in n]
    if os.environ.get("NO_EXPORT") != "1":
        mats = base.a3_materials()
        stats = {}
        for name in BUILDINGS:
            b = export_building(name, mats) if name in names else None
            if b is None:
                tmp = BB()
                BUILDINGS[name][0](tmp)
                b = tmp
            stats[name] = (b.doors, b.glass)
        write_config_fragment(stats)
    if os.environ.get("NO_PREVIEW") != "1":
        previews(names)


if __name__ == "__main__":
    main()
