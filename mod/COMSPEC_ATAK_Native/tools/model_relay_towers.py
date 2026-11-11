"""
Pylônes et mâts relais du réseau ATAK (relais posés dans Eden / Zeus, fn_relayTower) et leurs ruines.
Modèles MLOD écrits en pur Python (relay3d.py, relay_parts.py, relay_tex.py) : plus besoin de Blender pour les produire.

  relay_tower_30    pylône treillis carré autostable 30 m, jambes en cornières, K puis croix de Saint-André
  relay_tower_40    pylône treillis triangulaire autostable 40 m, jambes tubulaires à brides, plate-forme de tête
  relay_tower_50    pylône treillis carré lourd 50 m, trois plates-formes, abri technique, groupe électrogène, clôture
  relay_tower_m12   mât tactique télescopique 12 m sur trépied, haubané (piquets, sacs de sable), caisses, panneau solaire
  relay_tower_m20   mât télescopique 20 m sur remorque (béquilles, timon), haubané, coffret et tête à trois secteurs
  relay_monopole_30 monopôle tubulaire 30 m (trois tronçons emboîtés, bride à 16 tiges), tête à bras, enclos
  relay_guyed_60    pylône haubané fin 60 m (treillis triangulaire de 0,6 m, cinq nappes de haubans, massifs d'ancrage)
  relay_rooftop_8   pylône de toit 8 m sur châssis lesté (non percé), origine au niveau du toit
  + un modèle *_ruin par pylône (tronçon resté debout, pylône plié et couché, équipements arrachés, câbles pendants)

Sélections : camo (tout l'acier : variantes galvanisé / rouge-blanc / vert OTAN / sable par hiddenSelections),
led (voyant d'état des armoires et des radios), lights (globes des feux), eq1 / eq2 (équipement léger / complet :
masqués par animation « hide », model.cfg). Points mémoire : light_top, light_mid1, light_mid2 (MarkerLights).
LOD : visuelles 1 / 4 / 12 / 30 / 80, ombres 0 et 10 (prismes fermés), géométrie (jambes, massifs, armoires,
plates-formes), tir, vue, chemin praticable (dalles, plates-formes).

    python model_relay_towers.py            # modèles + textures (hemtt utils paa convert) + rvmat + model.cfg
    NO_TEX=1   : sans textures        ONLY=30,m12 : une partie des modèles
    OBJ_DIR=d  : exporte aussi les OBJ + PNG de rendu (tools/render_relay_blender.py)
"""
import json
import math
import os
import random
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import relay3d as r3  # noqa: E402
import relay_tex  # noqa: E402
from relay3d import IDENT, Mesh, basis, beam, bolt, box, cable, catenary, lathe, mat_mul, mat_vec, plate, quad, rot, tube, vadd, vcross, vdot, vlen, vlerp, vmul, vnorm, vsub  # noqa: E402
from relay_parts import (UP, Lattice, band_mat, cabinet, cable_bridge, cable_ladder, collar, dish, dish_mount, earth_rod, fence, genset, guy,  # noqa: E402
                         guy_anchor, jerrycan, ladder, lightning_rod, obstruction_light, omni, panel_antenna, pier, pipe_mount, radio_box,
                         railing, ring_platform, sandbag, sector_ring, shelter, solar_array, stake, transit_case)

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "towers", "data")


# --------------------------------------------------------------------------------------------------------------------
# Pièces propres aux pylônes
# --------------------------------------------------------------------------------------------------------------------
def spine(m, z0, z1, r=0.045, light="light_top", rod=2.5, big=True):
    """Fût de tête au centre du pylône : feu d'obstacle au sommet, paratonnerre déporté."""
    tube(m, (0, 0, z0), (0, 0, z1), r, "st_pipe", n=(8, 6, 4, 3, 3)[m.d], caps=(False, True), S="s")
    if light:
        with m.push(t=(0, 0, z1)):
            obstruction_light(m, light, big=big)
    if rod and m.d <= 3:
        if m.d <= 2:
            tube(m, (0, 0, z1 - 0.5), (0.28, 0.0, z1 - 0.5), 0.02, "st_pipe", n=4, caps=(False, False))
        with m.push(t=(0.28, 0, z1 - 0.5)):
            lightning_rod(m, rod)


def spine_brace(m, lat, z_top, z_low):
    """Attaches du fût de tête : croisillons horizontaux du treillis vers l'axe à deux niveaux."""
    if m.d > 2:
        return
    for z in (z_top, z_low):
        for i in range(lat.n):
            tube(m, lat.corner(i, z), (0, 0, z), 0.022, "st_pipe", n=(5, 4, 3)[m.d], caps=(False, False))


def mid_lights(m, lat, z, legs=(0, 2)):
    """Feux de mi-hauteur (basse intensité) sur consoles, points mémoire light_mid1 / light_mid2."""
    for k, i in enumerate(legs):
        c = lat.corner(i, z)
        o = lat.out(i)
        p = vadd(c, vmul(o, 0.45))
        if m.d <= 2:
            tube(m, c, p, 0.02, "st_pipe", n=(5, 4, 3)[m.d], caps=(False, False))
        with m.push(t=p):
            obstruction_light(m, "light_mid%d" % (k + 1))


def anti_climb(m, p0, p1, out, width=0.5, h=2.5):
    """Trappe anti-escalade cadenassée devant le bas de l'échelle (tôle pleine, plaque d'avertissement)."""
    if m.d > 2:
        return
    up = vnorm(vsub(p1, p0))
    out = vnorm(vsub(out, vmul(up, vdot(out, up))))
    side = vcross(up, out)
    a = vadd(p0, vmul(out, 0.09))
    with m.push(R=basis(x=side, z=up), t=a):
        box(m, -width / 2, width / 2, 0.0, 0.012, 0.1, h, "st_pipe")
        m.face([(-0.17, 0.016, 1.35), (0.17, 0.016, 1.35), (0.17, 0.016, 1.69), (-0.17, 0.016, 1.69)], "sign_rf", out=(0, 1, 0))
        if m.d == 0:
            box(m, 0.16, 0.22, 0.012, 0.04, 1.1, 1.2, "st_rust")


def earthing(m, lat, z0=0.0):
    """Mise à la terre : tresse de chaque jambe vers un piquet, ceinturage en cuivre nu."""
    if m.d > 1:
        return
    for i in range(lat.n):
        c = lat.corner(i, z0)
        o = lat.out(i)
        earth_rod(m, (c[0] + o[0] * 1.1, c[1] + o[1] * 1.1), (c[0] + o[0] * 0.12, c[1] + o[1] * 0.12, z0 + 0.45))


def drop_cables(m, top, n=4, mat="cb_black"):
    """Câbles descendant de la passerelle dans le toit d'une armoire."""
    if m.d > 1:
        return
    for k in range(n):
        y = (k - (n - 1) / 2) * 0.06
        cable(m, [(top[0] - 0.25, top[1] + y, top[2] + 0.06), (top[0], top[1] + y, top[2] + 0.04), (top[0] + 0.08, top[1] + y, top[2] - 0.12), (top[0] + 0.08, top[1] + y, top[2] - 0.5)], 0.014, mat, n=4)


# --------------------------------------------------------------------------------------------------------------------
# Pylônes fixes
# --------------------------------------------------------------------------------------------------------------------
def tower_30(d, ruin=False):
    m = Mesh(d, seed=30)
    H = 30.0
    P = [0.35, 3.5, 6.5, 9.5, 12.5, 15.25, 18.0] + [18.0 + 2.0 * k for k in range(1, 7)]
    lat = Lattice(4, [(0.35, 3.4), (18.0, 1.5), (30.0, 1.1)], P, ("angle", 0.16, 0.085), (0.075, 0.05),
                  pattern=lambda k, z: "K" if z < 9 else "X", H=H)
    lat.build(m, top_plate=False)
    # échelle à crinoline sur la face -Y, chemin de câbles sur la face +X
    p0, p1 = lat.face_mid(2, 0.4, 0.10), lat.face_mid(2, 29.7, 0.10)
    ladder(m, p0, p1, lat.face_n(2))
    anti_climb(m, p0, p1, lat.face_n(2))
    c0, c1 = lat.face_mid(3, 2.75, 0.06), lat.face_mid(3, 28.6, 0.06)
    cable_ladder(m, c0, c1, lat.face_n(3), ncab=5, stops=[0.97, 0.97, 0.78, 0.97, 0.74])
    spine_brace(m, lat, 29.9, 28.2)
    spine(m, 28.2, 32.4)
    with m.push(sels=("eq1",)):
        sector_ring(m, lat, 27.3, (90, 210, 330), standoff=0.65)
        for s in (-1, 1):
            if d <= 2:
                tube(m, (0, 0, 31.2), (0, s * 0.9, 31.2), 0.02, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
            with m.push(t=(0, s * 0.9, 31.2)):
                omni(m, L=1.5)
        with m.push(R=rot("z", 180), t=(-0.045, 0, 30.6)):
            radio_box(m)
    with m.push(sels=("eq2",)):
        dish_mount(m, lat, 22.5, 25, D=0.6)
        dish_mount(m, lat, 24.6, 205, D=0.3)
    # site : armoire sur dalle, passerelle de câbles, mise à la terre
    with m.push(t=(4.6, 0.0, 0.0), R=rot("z", 0)):
        top = cabinet(m)
    cable_bridge(m, (c0[0] + 0.02, 0.0), (4.6, 0.0), 2.72)
    drop_cables(m, (4.5, 0.0, 2.72 - 0.6) if False else (4.45, 0.0, 2.55))
    earthing(m, lat, 0.35)
    if ruin:
        collapse(m, 6.5, H, math.radians(200), lat)
    return m


def tower_40(d, ruin=False):
    m = Mesh(d, seed=40)
    H = 40.0
    P = [0.35, 4.3, 8.2, 12.1, 16.0, 20.0, 24.0] + [24.0 + 16.0 / 6 * k for k in range(1, 7)]
    P = [round(z, 3) for z in P]
    lat = Lattice(3, [(0.35, 4.4), (24.0, 1.9), (40.0, 1.4)], P, ("tube", 0.085, 0.045), (0.075, 0.05),
                  pattern=lambda k, z: "K" if z < 12 else "X", H=H)
    lat.build(m, top_plate=False)
    # face 1 (entre les jambes 210° et 330°... côté -Y) : échelle ; face 2 : câbles
    fl, fc = 1, 2
    p0, p1 = lat.face_mid(fl, 0.4, 0.12), lat.face_mid(fl, 36.6, 0.12)
    ladder(m, p0, p1, lat.face_n(fl))
    anti_climb(m, p0, p1, lat.face_n(fl))
    c0, c1 = lat.face_mid(fc, 2.75, 0.07), lat.face_mid(fc, 38.5, 0.07)
    cable_ladder(m, c0, c1, lat.face_n(fc), ncab=6, stops=[0.97, 0.97, 0.84, 0.84, 0.72, 0.66])
    ring_platform(m, lat, 35.4, over=0.9, gap_face=fl)
    spine_brace(m, lat, 39.9, 37.8)
    spine(m, 37.8, 42.6)
    mid_lights(m, lat, 20.0, legs=(0, 1))
    with m.push(sels=("eq1",)):
        sector_ring(m, lat, 37.9, (90, 210, 330), standoff=0.75)
        with m.push(t=(0, 0.0, 41.6)):
            if d <= 2:
                tube(m, (-0.8, 0, 0), (0.8, 0, 0), 0.02, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
        for s in (-1, 1):
            with m.push(t=(s * 0.8, 0, 41.6)):
                omni(m, L=1.6)
        dish_mount(m, lat, 29.6, 250, D=0.6)
    with m.push(sels=("eq2",)):
        sector_ring(m, lat, 32.3, (30, 150, 270), standoff=0.7)
        dish_mount(m, lat, 27.0, 60, D=1.2)
        with m.push(R=rot("z", 90), t=(0.0, 0.05, 40.8)):
            radio_box(m)
    # enclos : deux armoires, groupe électrogène
    n3 = lat.face_n(fc)
    cx, cy = n3[0] * 5.6, n3[1] * 5.6
    az = math.degrees(math.atan2(n3[1], n3[0]))
    with m.push(R=rot("z", az - 90), t=(cx, cy, 0)):
        with m.push(t=(-0.8, 0, 0)):
            cabinet(m)
        with m.push(t=(0.8, 0, 0)):
            cabinet(m, w=1.0)
        with m.push(t=(0.2, 2.2, 0.0)):
            genset(m)
    cable_bridge(m, (c0[0], c0[1]), (cx - n3[0] * 0.55, cy - n3[1] * 0.55), 2.72, ncab=6)
    earthing(m, lat, 0.35)
    fence(m, -5.2, 5.2 + cx * 0.75, -5.0, 5.0 + max(0.0, cy) * 0.9, gate=(0.0, 1.3))
    if ruin:
        collapse(m, 8.2, H, math.radians(150), lat)
    return m


def tower_50(d, ruin=False):
    m = Mesh(d, seed=50)
    H = 50.0
    P = [0.4, 5.2, 10.1, 15.0, 18.75, 22.5, 26.25, 30.0, 33.0, 36.0, 39.0, 42.0, 44.0, 46.0, 48.0, 50.0]
    lat = Lattice(4, [(0.4, 6.2), (15.0, 4.1), (30.0, 2.5), (42.0, 1.8), (50.0, 1.8)], P, ("angle", 0.22, 0.11), (0.10, 0.06),
                  pattern=lambda k, z: "K" if z < 15 else "X", H=H)
    lat.build(m, top_plate=False)
    p0, p1 = lat.face_mid(2, 0.45, 0.12), lat.face_mid(2, 46.9, 0.12)
    ladder(m, p0, p1, lat.face_n(2))
    anti_climb(m, p0, p1, lat.face_n(2))
    c0, c1 = lat.face_mid(3, 2.9, 0.07), lat.face_mid(3, 48.6, 0.07)
    cable_ladder(m, c0, c1, lat.face_n(3), ncab=8, stops=[0.97, 0.97, 0.97, 0.83, 0.83, 0.72, 0.68, 0.9], width=0.45)
    ring_platform(m, lat, 45.8, over=1.0, gap_face=2)
    ring_platform(m, lat, 38.8, over=0.9, gap_face=2)
    ring_platform(m, lat, 29.8, over=0.8, gap_face=2)
    spine_brace(m, lat, 49.9, 48.0)
    spine(m, 48.0, 53.0)
    mid_lights(m, lat, 25.0, legs=(0, 2))
    with m.push(sels=("eq1",)):
        sector_ring(m, lat, 47.9, (90, 210, 330), standoff=0.8, h=2.2)
        for s in (-1, 1):
            if d <= 2:
                tube(m, (0, 0, 51.8), (s * 0.9, 0, 51.8), 0.02, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
            with m.push(t=(s * 0.9, 0, 51.8)):
                omni(m, L=1.8)
        dish_mount(m, lat, 43.2, 300, D=0.6)
    with m.push(sels=("eq2",)):
        sector_ring(m, lat, 41.0, (30, 150, 270), standoff=0.75)
        dish_mount(m, lat, 34.0, 45, D=1.2)
        dish_mount(m, lat, 36.2, 200, D=1.2)
        with m.push(R=rot("z", 90), t=(0.0, 0.05, 51.0)):
            radio_box(m)
    with m.push(t=(7.6, 0.0, 0.0), R=rot("z", 90)):
        top = shelter(m)
    with m.push(t=(7.4, -3.6, 0.0)):
        genset(m, jerry=3)
    cable_bridge(m, (c0[0], 0.0), (6.35, 0.0), 2.6, ncab=8)
    earthing(m, lat, 0.4)
    fence(m, -5.4, 10.4, -5.8, 5.4, gate=(1.2, 1.3), bay=2.7)
    if ruin:
        collapse(m, 10.1, H, math.radians(215), lat)
    return m


# --------------------------------------------------------------------------------------------------------------------
# Mâts tactiques
# --------------------------------------------------------------------------------------------------------------------
def telescopic(m, z0, z1, r0, r1, n, mat="st_a", S="sSgf"):
    """Mât télescopique : n tronçons de rayon décroissant, bague de verrouillage à chaque emboîtement."""
    d = m.d
    L = (z1 - z0) / n
    for k in range(n):
        ra = r0 + (r1 - r0) * k / max(1, n - 1)
        za, zb = z0 + k * L, z0 + (k + 1) * L
        if d >= 3 and k % 2:
            continue
        if d >= 3:
            zb = min(z1, za + 2 * L)
        tube(m, (0, 0, za), (0, 0, zb), ra, mat, n=(12, 8, 6, 4, 3)[d], caps=(False, k == n - 1 or d >= 3), S=S if k % 2 == 0 else S.replace("g", "").replace("f", ""))
        if d <= 2 and k > 0:
            with m.push(t=(0, 0, za)):
                lathe(m, [(ra + 0.004, -0.05), (ra + 0.014, -0.05), (ra + 0.014, 0.03), (ra + 0.004, 0.03)], "st_pipe", n=(12, 8, 6)[d], closed=(True, True))
                if d == 0:
                    box(m, ra + 0.012, ra + 0.045, -0.012, 0.012, -0.03, 0.01, "plastic")


def tower_m12(d, ruin=False):
    """Mât tactique 12 m : trépied à jambes réglables, mât télescopique vert, deux nappes de trois haubans."""
    m = Mesh(d, seed=12)
    H = 11.6
    # trépied
    with m.push(tag="ground" if ruin else "struct"):
        for az in (90, 210, 330):
            a = math.radians(az)
            foot = (1.25 * math.cos(a), 1.25 * math.sin(a), 0.04)
            hub = (0.10 * math.cos(a), 0.10 * math.sin(a), 1.45)
            if d <= 3:
                tube(m, foot, hub, 0.024, "st_a", n=(8, 6, 4, 3)[d], caps=(False, False), S="sg")
            if d <= 2:
                tube(m, vlerp(foot, hub, 0.45), (0.07 * math.cos(a), 0.07 * math.sin(a), 0.32), 0.014, "st_a", n=(6, 4, 3)[d], caps=(False, False))
                with m.push(t=(foot[0], foot[1], 0.0)):
                    lathe(m, [(0.0, 0.045), (0.09, 0.03), (0.1, 0.0)], "st_pipe", n=(10, 6, 5)[d]) if False else lathe(m, [(0.1, 0.0), (0.09, 0.03), (0.0, 0.045)], "st_pipe", n=(10, 6, 5)[d])
            with m.push(R=rot("z", az + 90), t=(foot[0] * 1.02, foot[1] * 1.02, 0.045)):
                if d <= 3:
                    sandbag(m)
        if d <= 2:
            with m.push(t=(0, 0, 1.45)):
                lathe(m, [(0.085, -0.07), (0.13, -0.07), (0.13, 0.07), (0.085, 0.07)], "st_pipe", n=(10, 6, 5)[d], closed=(True, True))
            lathe(m, [(0.16, 0.0), (0.16, 0.025), (0.08, 0.05)], "st_pipe", n=(10, 6, 5)[d])
    telescopic(m, 0.03, H, 0.075, 0.03, 6)
    # haubans : deux nappes, trois piquets communs
    if not ruin:
        for az in (30, 150, 270):
            a = math.radians(az)
            g = (7.0 * math.cos(a), 7.0 * math.sin(a), 0.0)
            top = stake(m, g, (0, 0, 0))
            for z, r in ((5.85, 0.06), (11.0, 0.036)):
                guy(m, (r * math.cos(a), r * math.sin(a), z), top, r=0.004, marker=(z < 6), turnbuckle=False)
                if d == 0:
                    q = vlerp((r * math.cos(a), r * math.sin(a), z), top, 0.93)
                    with m.push(t=q):
                        box(m, -0.012, 0.012, -0.05, 0.05, -0.02, 0.02, "cb_hazard")
        for z, r in ((5.85, 0.06), (11.0, 0.036)):
            if d <= 1:
                with m.push(t=(0, 0, z)):
                    lathe(m, [(r, -0.012), (r + 0.045, -0.012), (r + 0.045, 0.012), (r, 0.012)], "st_pipe", n=8, closed=(True, True))
    # tête : feu, bras d'antennes
    with m.push(t=(0, 0, H)):
        obstruction_light(m, "light_top")
    with m.push(sels=("eq1",)):
        if d <= 2:
            tube(m, (-0.55, 0, 11.25), (0.55, 0, 11.25), 0.016, "st_pipe", n=(6, 4, 3)[d], caps=(d == 0, d == 0))
        for s in (-1, 1):
            with m.push(t=(s * 0.55, 0, 11.25)):
                omni(m, L=1.1, r=0.012, base=0.16)
        with m.push(R=rot("z", -90), t=(0.0, -0.05, 10.55)):
            radio_box(m)
        # câble le long du mât jusqu'à la caisse
        if d <= 2:
            pts = [(0.03, -0.06, 10.4), (0.05, -0.085, 6.0), (0.06, -0.095, 0.6), (0.3, -0.35, 0.05), (1.0, -0.9, 0.03), (1.25, -1.05, 0.2)]
            cable(m, pts, 0.008, "cb_black", n=(5, 4, 3)[d])
        with m.push(R=rot("z", 25), t=(1.55, -1.25, 0.0)):
            transit_case(m)
    with m.push(sels=("eq2",)):
        with m.push(R=rot("z", 90), t=(0.0, 0.035, 9.6)):
            panel_antenna(m, h=0.75, w=0.2, t=0.07, pipe=0.9, rru=False, pipe_r=0.02, tilt=2)
        with m.push(R=rot("z", -160), t=(2.4, 1.1, 0.0)):
            solar_array(m, w=1.1, h=0.65)
        with m.push(R=rot("z", -12), t=(1.7, -0.45, 0.0)):
            transit_case(m, l=0.6, w=0.42, h=0.3)
        if d <= 1:
            cable(m, [(2.25, 0.95, 0.3), (2.1, 0.5, 0.03), (1.8, -0.3, 0.03), (1.75, -0.45, 0.15)], 0.006, "cb_black", n=4)
    earth_rod(m, (-0.9, -1.3), (-0.05, -0.08, 0.35))
    if ruin:
        collapse(m, 1.6, H, math.radians(70), None, width=0.3, kink=115)
    return m


def wheel(m, r=0.33, w=0.21):
    """Roue de remorque (axe X local : l'essieu) : flancs en UV polaires, bande de roulement."""
    d = m.d
    n = (16, 12, 8, 6, 5)[d]
    with m.push(R=basis(x=(0, 1, 0), z=(1, 0, 0))):
        lathe(m, [(r * 0.94, -w / 2), (r, -w * 0.3), (r, w * 0.3), (r * 0.94, w / 2)], "cb_rubber", n=n)
        lathe(m, [(0.0, w / 2 + 0.01), (r * 0.5, w / 2 + 0.01), (r * 0.94, w / 2)] if False else [(r * 0.94, w / 2), (r * 0.5, w / 2 + 0.012), (0.0, w / 2 + 0.012)], "tyre", n=n, polar=True)
        lathe(m, [(0.0, -w / 2 - 0.012), (r * 0.5, -w / 2 - 0.012), (r * 0.94, -w / 2)], "tyre", n=n, polar=True)


def tower_m20(d, ruin=False):
    """Mât télescopique 20 m sur remorque : châssis, béquilles stabilisatrices, coffret, haubans."""
    m = Mesh(d, seed=20)
    H = 19.4
    zd = 0.62      # plateau
    with m.push(tag="ground"):
        # châssis : longerons, traverses, plateau en tôle larmée
        for x in (-0.75, 0.75):
            beam(m, (x, -1.6, zd - 0.08), (x, 1.6, zd - 0.08), 0.08, 0.14, "st_a", a=(1, 0, 0), S="s")
        if d <= 2:
            for y in (-1.55, -0.5, 0.5, 1.55):
                beam(m, (-0.75, y, zd - 0.08), (0.75, y, zd - 0.08), 0.06, 0.12, "st_a", a=(0, 1, 0))
        box(m, -0.85, 0.85, -1.65, 1.65, zd - 0.012, zd, {"+z": "deck", "*": "st_a"}, S="Sgfv")
        m.road([(-0.85, -1.65, zd), (0.85, -1.65, zd), (0.85, 1.65, zd), (-0.85, 1.65, zd)])
        # essieu, roues, garde-boue
        if d <= 3:
            tube(m, (-1.0, -0.35, 0.34), (1.0, -0.35, 0.34), 0.035, "st_rust", n=(6, 5, 4, 3)[d], caps=(False, False))
        for s in (-1, 1):
            with m.push(t=(s * 1.02, -0.35, 0.34)):
                if d <= 3:
                    wheel(m)
                r3.solid_box(m, -0.11, 0.11, -0.33, 0.33, -0.33, 0.33, "sSgf")
            if d <= 2:
                box(m, s * 1.02 - 0.14, s * 1.02 + 0.14, -0.78, 0.08, 0.72, 0.745, "st_a")
                box(m, s * 1.02 - 0.14, s * 1.02 + 0.14, -0.80, -0.78, 0.5, 0.745, "st_a")
                box(m, s * 1.02 - 0.14, s * 1.02 + 0.14, 0.08, 0.10, 0.5, 0.745, "st_a")
        # timon en A, tête d'attelage, roue jockey
        for s in (-1, 1):
            if d <= 3:
                beam(m, (s * 0.75, 1.6, zd - 0.1), (0.0, 3.0, zd - 0.1), 0.07, 0.1, "st_a", a=UP, S="s")
        if d <= 2:
            box(m, -0.06, 0.06, 2.95, 3.25, zd - 0.16, zd - 0.04, "st_pipe")
            tube(m, (0.0, 3.3, zd - 0.1), (0.0, 3.3, zd - 0.02), 0.045, "st_pipe", n=(8, 6, 4)[d])
            tube(m, (0.18, 2.6, 0.1), (0.18, 2.6, zd + 0.25), 0.028, "st_pipe", n=(6, 5, 3)[d], caps=(False, True))
            with m.push(t=(0.18, 2.6, 0.11)):
                wheel(m, r=0.11, w=0.07)
        # quatre béquilles sur bras déployés
        for sx in (-1, 1):
            for sy in (-1, 1):
                a = (sx * 0.8, sy * 1.5, zd - 0.1)
                b = (sx * 2.0, sy * 2.3, zd - 0.1)
                if d <= 3:
                    beam(m, a, b, 0.07, 0.07, "st_a", a=UP, S="s")
                    tube(m, (b[0], b[1], 0.03), (b[0], b[1], zd + 0.28), 0.03, "st_pipe", n=(6, 5, 4, 3)[d], caps=(False, True), S="sg")
                if d <= 2:
                    with m.push(t=(b[0], b[1], 0.0)):
                        lathe(m, [(0.13, 0.0), (0.13, 0.02), (0.04, 0.04)], "st_pipe", n=(8, 6, 5)[d])
                    if d <= 1:
                        tube(m, (b[0], b[1], zd + 0.28), (b[0] + sx * 0.12, b[1], zd + 0.28), 0.01, "st_pipe", n=4)
        # coffret radio / énergie et touret de câble sur le plateau
        with m.push(t=(0.0, -1.0, zd), R=rot("z", 180)):
            cabinet(m, w=1.1, dp=0.55, h=1.0, slab=False)
        with m.push(t=(0.0, 1.05, zd)):
            if d <= 2:
                with m.push(R=basis(x=(0, 1, 0), z=(1, 0, 0)), t=(0, 0, 0.3)):
                    lathe(m, [(0.3, -0.25), (0.3, -0.22), (0.12, -0.22), (0.12, 0.22), (0.3, 0.22), (0.3, 0.25)], "olive" if False else "plastic", n=(12, 8, 6)[d], closed=(True, True))
                    lathe(m, [(0.2, -0.21), (0.2, 0.21)], "cb_black", n=(12, 8, 6)[d])
    # mât : embase, tronçons
    with m.push(t=(0, 0, zd)):
        if d <= 2:
            lathe(m, [(0.19, 0.0), (0.19, 0.03), (0.135, 0.06), (0.135, 0.25)], "st_plate", n=(12, 8, 6)[d])
    telescopic(m, zd + 0.02, H, 0.12, 0.042, 8)
    if not ruin:
        for az in (90, 210, 330):
            a = math.radians(az)
            g = (11.0 * math.cos(a), 11.0 * math.sin(a), 0.0)
            top = stake(m, g, (0, 0, 0), sandbags=3)
            for z, r in ((9.9, 0.09), (17.2, 0.055)):
                guy(m, (r * math.cos(a), r * math.sin(a), z), top, r=0.005, marker=(z < 10), turnbuckle=(z < 10))
        for z, r in ((9.9, 0.09), (17.2, 0.055)):
            if d <= 1:
                with m.push(t=(0, 0, z)):
                    lathe(m, [(r, -0.015), (r + 0.05, -0.015), (r + 0.05, 0.015), (r, 0.015)], "st_pipe", n=8, closed=(True, True))
    # tête
    with m.push(t=(0, 0, H)):
        if d <= 2:
            tube(m, (0, 0, 0), (0, 0, 0.9), 0.025, "st_pipe", n=(6, 5, 3)[d], caps=(False, False))
        with m.push(t=(0, 0, 0.9)):
            obstruction_light(m, "light_top")
    with m.push(sels=("eq1",)):
        for az in (90, 210, 330):
            a = math.radians(az)
            p = (0.42 * math.cos(a), 0.42 * math.sin(a), 18.3)
            if d <= 2:
                for dz in (-0.45, 0.45):
                    tube(m, (0, 0, 18.3 + dz), vadd(p, (0, 0, dz)), 0.016, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
            with m.push(R=rot("z", az), t=p):
                panel_antenna(m, h=1.3, w=0.26, t=0.1, pipe=1.6, rru=(d <= 2), pipe_r=0.024)
        with m.push(t=(0.0, 0.0, H + 0.2)):
            if d <= 2:
                tube(m, (0, 0, 0), (0.0, 0.5, 0.0), 0.014, "st_pipe", n=4, caps=(False, False))
            with m.push(t=(0, 0.5, 0)):
                omni(m, L=1.3, r=0.013)
        if d <= 2:
            cable(m, [(0.06, -0.09, 17.6), (0.1, -0.12, 9.0), (0.13, -0.15, zd + 0.4), (0.1, -0.5, zd + 0.06), (0.0, -0.8, zd + 0.3)], 0.012, "cb_black", n=(5, 4, 3)[d])
    with m.push(sels=("eq2",)):
        with m.push(R=rot("z", 60), t=(0.07 * math.cos(math.radians(60)), 0.07 * math.sin(math.radians(60)), 16.2)):
            dish(m, D=0.3, pipe=0.6, pipe_r=0.022)
        with m.push(R=rot("z", -90), t=(0.0, -0.06, 15.2)):
            radio_box(m)
        with m.push(R=rot("z", 10), t=(2.6, -1.6, 0.0)):
            genset(m, jerry=2)
        if d <= 1:
            cable(m, [(1.9, -1.5, 0.2), (1.4, -1.45, 0.03), (0.6, -1.5, 0.03), (0.3, -1.3, zd + 0.1)], 0.012, "cb_black", n=4)
    earth_rod(m, (-1.4, -1.9), (-0.8, -1.6, zd - 0.1))
    if ruin:
        collapse(m, zd + 2.4, H, math.radians(140), None, width=0.4, kink=112)
    return m


# --------------------------------------------------------------------------------------------------------------------
# Variantes : monopôle, haubané, pylône de toit
# --------------------------------------------------------------------------------------------------------------------
def monopole_30(d, ruin=False):
    m = Mesh(d, seed=31)
    H = 30.0
    secs = [(0.35, 10.6, 0.56, 0.455), (10.0, 20.5, 0.47, 0.36), (19.9, 30.0, 0.375, 0.26)]
    n = (18, 12, 8, 6, 5)[d]
    with m.push(tag="ground"):
        box(m, -1.5, 1.5, -1.5, 1.5, -2.6, 0.3, "concrete", S="gfv", skip="-z")
        m.road([(-1.5, -1.5, 0.3), (1.5, -1.5, 0.3), (1.5, 1.5, 0.3), (-1.5, 1.5, 0.3)])
    for k, (z0, z1, r0, r1) in enumerate(secs):
        # bandes de peinture : un tronçon par bande
        zs = [z0] + [H * i / 7 for i in range(1, 7) if z0 < H * i / 7 < z1] + [z1]
        for za, zb in zip(zs, zs[1:]):
            ra, rb = r0 + (r1 - r0) * (za - z0) / (z1 - z0), r0 + (r1 - r0) * (zb - z0) / (z1 - z0)
            tube(m, (0, 0, za), (0, 0, zb), ra, band_mat((za + zb) / 2, H), n=n, r1=rb, caps=(False, k == 2 and zb == z1))
        r3.add_prism_solid(m, (0, 0, z0), (0, 0, z1), (1, 0, 0), (0, 1, 0), [(r0, 0), (0, r0), (-r0, 0), (0, -r0)], "sSgfv")
    # bride d'embase, 16 tiges d'ancrage, raidisseurs
    if d <= 2:
        with m.push(t=(0, 0, 0.3)):
            lathe(m, [(0.56, 0.0), (0.78, 0.0), (0.78, 0.05), (0.56, 0.05)], "st_plate", n=(24, 16, 10)[d])
            if d == 0:
                for k in range(16):
                    a = k * math.pi / 8
                    p = (0.69 * math.cos(a), 0.69 * math.sin(a), 0.05)
                    tube(m, p, vadd(p, (0, 0, 0.13)), 0.018, "st_rust", n=6, caps=(False, True))
                    tube(m, p, vadd(p, (0, 0, 0.045)), 0.036, "st_pipe", n=6, caps=(False, True))
            if d <= 1:
                for k in range(8):
                    with m.push(R=rot("z", k * 45 + 22.5)):
                        plate(m, [(0.555, -0.006, 0.05), (0.76, -0.006, 0.05), (0.555, -0.006, 0.36)], 0.012, "st_plate", (0, 1, 0))
        # trappe de visite au pied, sortie des câbles
        with m.push(R=rot("z", 0)):
            box(m, 0.54, 0.575, -0.16, 0.16, 1.0, 1.6, "st_plate", skip="-x")
    # échelons vissés alternés et câble de sécurité
    if d <= 1:
        z = 3.0
        k = 0
        while z < 28.6:
            r = 0.56 - (0.56 - 0.26) * (z / H)
            for s in ((-1,), (1,))[k % 2]:
                tube(m, (-r * 0.7, s * r * 0.7, z), (-r * 0.7 - 0.02, s * (r * 0.7 + 0.2), z), 0.011, "st_pipe", n=4, caps=(False, True))
            z += 0.38 if d == 0 else 0.76
            k += 1
        tube(m, (-0.60, 0, 2.8), (-0.31, 0, 28.8), 0.005, "st_guy", n=3, caps=(False, False))
    # tête : trois bras en T portant chacun deux antennes ; couronne basse plaquée au fût
    with m.push(sels=("eq1",)):
        for az in (90, 210, 330):
            with m.push(R=rot("z", az)):
                if d <= 2:
                    for z in (26.4, 27.9):
                        tube(m, (0.2, 0, z), (1.25, 0, z), 0.035, "st_pipe", n=(6, 5, 3)[d], caps=(False, False), S="s")
                        tube(m, (1.25, -1.2, z), (1.25, 1.2, z), 0.035, "st_pipe", n=(6, 5, 3)[d], caps=(d == 0, d == 0))
                    tube(m, (0.3, 0, 26.4), (1.0, 0, 27.9), 0.02, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
                with m.push(t=(1.25, -0.8, 27.15)):
                    panel_antenna(m, h=2.0, pipe=2.4)
        if d <= 2:
            with m.push(t=(0, 0, 26.4)):
                lathe(m, [(0.30, -0.06), (0.33, -0.06), (0.33, 0.06), (0.30, 0.06)], "st_plate", n=(12, 8, 6)[d])
            with m.push(t=(0, 0, 27.9)):
                lathe(m, [(0.285, -0.06), (0.315, -0.06), (0.315, 0.06), (0.285, 0.06)], "st_plate", n=(12, 8, 6)[d])
    with m.push(sels=("eq2",)):
        for az in (90, 210, 330):
            with m.push(R=rot("z", az)):
                with m.push(t=(1.25, 0.8, 27.15)):
                    panel_antenna(m, h=2.0, pipe=2.4)
        for az in (30, 150, 270):
            with m.push(R=rot("z", az), t=(0.0, 0.0, 22.6)):
                if d <= 2:
                    for dz in (-0.7, 0.7):
                        tube(m, (0.3, 0, dz), (0.52, 0, dz), 0.025, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
                with m.push(t=(0.52, 0, 0)):
                    panel_antenna(m, h=1.6, w=0.26, pipe=2.0)
        with m.push(R=rot("z", 190), t=(0.42 * math.cos(math.radians(190)), 0.42 * math.sin(math.radians(190)), 18.2)):
            dish(m, D=0.6)
    with m.push(t=(0, 0, H)):
        obstruction_light(m, "light_top", big=True)
    with m.push(t=(0.18, 0, H - 0.6)):
        if d <= 2:
            tube(m, (-0.4, 0, 0), (0, 0, 0), 0.016, "st_pipe", n=4, caps=(False, False))
        lightning_rod(m, 2.6)
    for k, az in enumerate((0, 180)):
        a = math.radians(az)
        with m.push(t=(0.43 * math.cos(a), 0.43 * math.sin(a), 15.0)):
            obstruction_light(m, "light_mid%d" % (k + 1))
    # enclos : deux armoires, passerelle
    with m.push(t=(3.6, 0.5, 0.0), R=rot("z", 90)):
        with m.push(t=(-0.75, 0, 0)):
            cabinet(m)
        with m.push(t=(0.75, 0, 0)):
            cabinet(m, w=1.0)
    cable_bridge(m, (0.6, 0.0), (3.1, 0.0), 2.3, ncab=6)
    if d <= 1:
        cable(m, [(0.57, 0.0, 1.5), (0.66, 0.0, 1.9), (0.7, 0.0, 2.3)], 0.05, "cb_black", n=6)
    earth_rod(m, (1.2, -1.9), (0.7, -0.5, 0.36))
    fence(m, -3.4, 5.6, -3.6, 3.6, gate=(-1.0, 1.2), bay=2.4)
    if ruin:
        collapse(m, 4.3, H, math.radians(240), None, width=0.9, kink=100)
    return m


def guyed_60(d, ruin=False):
    m = Mesh(d, seed=60)
    H = 60.0
    z0 = 1.6
    P = [z0 + (H - z0) * k / 73 for k in range(74)]
    P = [round(z, 4) for z in P]
    lat = Lattice(3, [(z0, 0.62), (H, 0.62)], P, ("tube", 0.03, 0.03), (0.018, 0.018), pattern="Z", H=H, z0=z0, rod=True)
    lat.build(m, footing=False)
    # embase pivot : les trois jambes convergent sur une rotule posée sur le massif
    with m.push(tag="ground"):
        box(m, -0.7, 0.7, -0.7, 0.7, -2.6, 0.45, "concrete", S="gfv", skip="-z")
        if d <= 2:
            lathe(m, [(0.22, 0.45), (0.22, 0.48), (0.07, 0.55), (0.07, 0.62)], "st_plate", n=(12, 8, 6)[d])
    for i in range(3):
        if d <= 3:
            tube(m, (0, 0, 0.62), lat.corner(i, z0), 0.03, band_mat(1.0, H), n=(8, 6, 4, 3)[d], caps=(False, False), S="s")
    # haubans : cinq nappes, deux couronnes de massifs
    levels = [(12.0, 0), (24.0, 0), (36.0, 1), (48.0, 1), (58.6, 1)]
    radii = (24.0, 42.0)
    if not ruin:
        for i in range(3):
            o = lat.out(i)
            tops = [guy_anchor(m, (o[0] * r, o[1] * r, 0.0), (0, 0, 0)) for r in radii]
            for z, ring in levels:
                guy(m, lat.corner(i, z), tops[ring], r=0.007)
    for z, ring in levels:
        if d <= 1:
            collar(m, lat, z, over=0.03, r=0.022)
    c0, c1 = lat.face_mid(2, 2.2, 0.03), lat.face_mid(2, 58.0, 0.03)
    cable_ladder(m, c0, c1, lat.face_n(2), ncab=4, stops=[0.98, 0.98, 0.93, 0.86], width=0.22, rails=False)
    spine(m, H - 0.6, H + 2.4, r=0.03)
    if d <= 2:
        for i in range(3):
            tube(m, lat.corner(i, H), (0, 0, H + 0.5), 0.02, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
    mid_lights(m, lat, 30.0, legs=(0, 1))
    with m.push(sels=("eq1",)):
        sector_ring(m, lat, 56.6, (90, 210, 330), standoff=0.5, h=1.4, w=0.24, rru=(d <= 2))
        for s in (-1, 1):
            if d <= 2:
                tube(m, (0, 0, H + 1.2), (s * 0.7, 0, H + 1.2), 0.016, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
            with m.push(t=(s * 0.7, 0, H + 1.2)):
                omni(m, L=1.6)
    with m.push(sels=("eq2",)):
        dish_mount(m, lat, 51.0, 40, D=0.6, standoff=0.3)
        dish_mount(m, lat, 46.0, 200, D=0.6, standoff=0.3)
        sector_ring(m, lat, 53.4, (30, 150, 270), standoff=0.45, h=1.4, w=0.24, rru=False)
    n2 = lat.face_n(2)
    cx, cy = n2[0] * 3.0, n2[1] * 3.0
    with m.push(R=rot("z", math.degrees(math.atan2(n2[1], n2[0])) - 90), t=(cx, cy, 0)):
        cabinet(m)
    cable_bridge(m, (c0[0], c0[1]), (cx - n2[0] * 0.5, cy - n2[1] * 0.5), 2.2, ncab=4, posts=False)
    earth_rod(m, (-1.2, -1.2), (-0.2, -0.2, 0.5))
    fence(m, -2.6, 2.6 + max(0, cx), -2.6 + min(0, cy) * 1.4, 2.6, gate=(0.0, 1.0), bay=2.2)
    if ruin:
        collapse(m, 12.0, H, math.radians(100), lat, kink=105, wave=1.0)
    return m


def rooftop_8(d, ruin=False):
    """Pylône de toit 8 m : treillis carré de 0,9 m sur châssis en croix lesté de blocs béton (toit non percé)."""
    m = Mesh(d, seed=8)
    H = 8.3
    z0 = 0.3
    P = [z0 + k * 1.0 for k in range(9)]
    lat = Lattice(4, [(z0, 0.9), (H, 0.9)], P, ("angle", 0.07, 0.07), (0.045, 0.045), pattern="X", H=H, z0=z0)
    lat.build(m, footing=False, bands=5)
    with m.push(tag="ground"):
        for a in (0, 90):
            with m.push(R=rot("z", a)):
                for y in (-0.45, 0.45):
                    beam(m, (-1.9, y, 0.2), (1.9, y, 0.2), 0.12, 0.16, "st_a", a=UP, S="sg")
                for s in (-1, 1):
                    with m.push(t=(s * 1.55, 0, 0.0)):
                        box(m, -0.35, 0.35, -0.6, 0.6, 0.02, 0.12, "cb_rubber", skip="-z")       # tapis anti-poinçonnement
                        box(m, -0.3, 0.3, -0.55, 0.55, 0.28, 0.58, "concrete", S="sSgfv")        # lest béton
        for i in range(4):
            c = lat.corner(i, z0)
            if d <= 2:
                box(m, c[0] - 0.12, c[0] + 0.12, c[1] - 0.12, c[1] + 0.12, 0.28, 0.3, "st_plate")
        r3.solid_box(m, -0.45, 0.45, -0.45, 0.45, 0.3, 2.2, "g")
    p0, p1 = lat.face_mid(2, 0.5, 0.06), lat.face_mid(2, 8.0, 0.06)
    ladder(m, p0, p1, lat.face_n(2), cage=False, width=0.36)
    c0, c1 = lat.face_mid(3, 0.6, 0.04), lat.face_mid(3, 7.6, 0.04)
    cable_ladder(m, c0, c1, lat.face_n(3), ncab=4, width=0.2, rails=False)
    spine(m, 7.3, 9.6, r=0.03, big=False, rod=1.6)
    spine_brace(m, lat, 8.28, 7.3)
    with m.push(sels=("eq1",)):
        sector_ring(m, lat, 6.9, (90, 210, 330), standoff=0.45, h=1.4, w=0.26)
        with m.push(t=(0.75, -0.9, 0.0), R=rot("z", 0)):
            cabinet(m, w=0.7, dp=0.45, h=1.1, slab=False)
        if d <= 1:
            cable(m, [(c0[0] + 0.05, 0.0, 0.7), (0.7, -0.2, 0.25), (0.75, -0.7, 0.25), (0.75, -0.8, 0.6)], 0.03, "cb_black", n=5)
    with m.push(sels=("eq2",)):
        dish_mount(m, lat, 4.6, 45, D=0.6, standoff=0.3)
        with m.push(R=rot("z", 180), t=(-0.03, 0.0, 8.6)):
            radio_box(m)
        with m.push(t=(0.2, 0.0, 9.0)):
            pass
    if ruin:
        collapse(m, 1.3, H, math.radians(30), lat, kink=100)
    return m


# --------------------------------------------------------------------------------------------------------------------
# Ruine : le pylône casse à la cote zb, la partie haute se plie, pend du tronçon resté debout puis repose au sol ;
# les équipements suivent en bloc, une partie est arrachée et retombe à côté ; câbles pendants, débris.
# --------------------------------------------------------------------------------------------------------------------
def subdivide_long(m, step=1.5):
    """Recoupe les longues pièces de structure (jambes, montants, câbles) pour qu'elles suivent le pliage."""
    cache = {}

    def mid(i, j, k, n):
        if k == 0:
            return i
        if k == n:
            return j
        key = (i, j, k, n)
        if key not in cache:
            m.pts.append(vlerp(m.pts[i], m.pts[j], k / n))
            m.ptag.append(m.ptag[i])
            cache[key] = len(m.pts) - 1
        return cache[key]
    out = []
    for face in m.faces:
        idx, uvs, nrm, mat, sels = face
        if len(idx) != 4 or m.ptag[idx[0]][0] != "struct":
            out.append(face)
            continue
        P = [m.pts[i] for i in idx]
        e = [vlen(vsub(P[(k + 1) % 4], P[k])) for k in range(4)]
        # arêtes longues : (1, 3) ou (0, 2)
        o = 0 if e[1] + e[3] >= e[0] + e[2] else 1
        L = max(e[(1 + o) % 4], e[(3 + o) % 4])
        n = int(L / step)
        if n < 2:
            out.append(face)
            continue
        a, b, c, d_ = (idx[o], idx[(1 + o) % 4], idx[(2 + o) % 4], idx[(3 + o) % 4])
        ua, ub, uc, ud = (uvs[o], uvs[(1 + o) % 4], uvs[(2 + o) % 4], uvs[(3 + o) % 4])
        na, nb, nc, nd = (nrm[o], nrm[(1 + o) % 4], nrm[(2 + o) % 4], nrm[(3 + o) % 4])
        l2 = lambda p, q, t: (p[0] + (q[0] - p[0]) * t, p[1] + (q[1] - p[1]) * t)
        for k in range(n):
            t0, t1 = k / n, (k + 1) / n
            out.append(((mid(a, d_, k, n), mid(b, c, k, n), mid(b, c, k + 1, n), mid(a, d_, k + 1, n)),
                        (l2(ua, ud, t0), l2(ub, uc, t0), l2(ub, uc, t1), l2(ua, ud, t1)),
                        (vnorm(vlerp(na, nd, t0)), vnorm(vlerp(nb, nc, t0)), vnorm(vlerp(nb, nc, t1)), vnorm(vlerp(na, nd, t1))), mat, sels))
    m.faces = out


def collapse(m, zb, H, az, lat, width=None, kink=118, wave=0.0):
    subdivide_long(m, (1.5, 2.2, 3.5, 6.0, 9.0)[m.d])
    f = (math.cos(az), math.sin(az), 0.0)
    a = vcross(UP, f)
    w = width if width is not None else lat.width(zb)
    u0 = 0.5 * w                      # charnière sur la face du côté de la chute : les jambes arrière sont arrachées
    hw = 0.5 * (width if width is not None else lat.width(min(H, zb + 0.6 * (H - zb))))
    th1 = math.radians(kink)
    cz2 = hw - u0 + 0.04              # cote de l'axe de la partie couchée (sa face basse touche le sol)
    # pli assez fermé pour que la partie haute atteigne le sol avant sa mi-longueur
    th1 = max(th1, math.acos(-min(0.96, (zb - cz2) / (0.5 * (H - zb)))))
    L1 = max(0.5, (zb - cz2) / -math.cos(th1))
    P0 = (f[0] * u0, f[1] * u0, zb)
    C1 = vadd(P0, vmul(vadd(vmul(f, math.sin(th1)), vmul(UP, math.cos(th1))), L1))
    # membrures arrachées : les faces à cheval sur la cassure disparaissent, sauf du côté de la charnière (pliées)
    keep = []
    for face in m.faces:
        idx = face[0]
        if m.ptag[idx[0]][0] == "struct":
            zs = [m.pts[i][2] for i in idx]
            if min(zs) <= zb < max(zs) and sum(vdot(m.pts[i], f) for i in idx) / len(idx) < 0.3 * w:
                continue
        keep.append(face)
    m.faces = keep
    rng = random.Random(int(zb * 100) + int(H))

    def frame(z):
        s = z - zb
        if s <= 0:
            lean = max(0.0, 1 - (zb - z) / max(zb, 0.1)) ** 2 * 0.05
            return (f[0] * lean * (z), f[1] * lean * z, z), IDENT, 0.0
        if s <= L1:
            th = th1
            C = vadd(P0, vmul(vadd(vmul(f, math.sin(th)), vmul(UP, math.cos(th))), s))
        else:
            th = math.pi / 2 + 0.03
            C = vadd(C1, vmul(f, s - L1))
            C = (C[0], C[1], cz2)
            # ondulation de la partie couchée (pylône haubané : longue, elle serpente)
            C = vadd(C, vmul(a, wave * 2.5 * math.sin((s - L1) / 9.0)))
        eu = vadd(vmul(f, math.cos(th)), vmul(UP, -math.sin(th)))
        ax = vadd(vmul(f, math.sin(th)), vmul(UP, math.cos(th)))
        tw = 0.35 * s / max(1.0, H - zb)
        ca, sa = math.cos(tw), math.sin(tw)
        e1 = vadd(vmul(eu, ca), vmul(a, sa))
        e2 = vadd(vmul(eu, -sa), vmul(a, ca))
        # colonnes : images de f, a, UP
        R = ((e1[0] * f[0] + e2[0] * a[0], e1[0] * f[1] + e2[0] * a[1], ax[0]),
             (e1[1] * f[0] + e2[1] * a[0], e1[1] * f[1] + e2[1] * a[1], ax[1]),
             (e1[2] * f[0] + e2[2] * a[0], e1[2] * f[1] + e2[2] * a[1], ax[2]))
        return C, R, s

    def xf(p, pivot_z=None):
        C, R, s = frame(p[2] if pivot_z is None else pivot_z)
        if s <= 0 and pivot_z is None:
            return (p[0] + C[0], p[1] + C[1], p[2]), R
        q = (p[0] - f[0] * u0, p[1] - f[1] * u0, 0.0 if pivot_z is None else p[2] - pivot_z)
        return vadd(C, mat_vec(R, q)), R

    groups = {}

    def group_xf(tag):
        key = tag[1:]
        if key not in groups:
            o = tag[2]
            r = random.Random(hash((round(o[0], 2), round(o[1], 2), round(o[2], 2))) & 0xFFFFFF)
            pos, R = xf(o) if o[2] > zb else (o, IDENT)
            torn = o[2] > zb and r.random() < 0.5
            if torn:
                # arraché : retombe à plat près de la trajectoire de chute, orienté au hasard
                R2 = mat_mul(rot("z", r.uniform(0, 360)), rot((r.uniform(-1, 1), r.uniform(-1, 1), 0.2), r.uniform(60, 120)))
                R = R2
                pos = (pos[0] + r.uniform(-2.5, 2.5), pos[1] + r.uniform(-2.5, 2.5), 0.0)
            groups[key] = (o, pos, R, torn)
        return groups[key]

    Rs = []
    low = {}
    for i, (p, tag) in enumerate(zip(m.pts, m.ptag)):
        if tag[0] == "ground":
            Rs.append(IDENT)
        elif tag[0] == "equip":
            o, pos, R, torn = group_xf(tag)
            q = vadd(pos, mat_vec(R, vsub(p, o)))
            if torn:
                low[tag[1:]] = min(low.get(tag[1:], 1e9), q[2])
            m.pts[i] = q
            Rs.append(R)
        else:
            q, R = xf(p)
            if p[2] > zb:
                k = min(1.0, (p[2] - zb) / 2.0) * 0.07
                q = (q[0] + rng.uniform(-k, k), q[1] + rng.uniform(-k, k), max(0.02, q[2] + rng.uniform(-k, k)))
            m.pts[i] = q
            Rs.append(R)
    for i, tag in enumerate(m.ptag):
        if tag[0] == "equip" and tag[1:] in low:
            p = m.pts[i]
            m.pts[i] = (p[0], p[1], p[2] - low[tag[1:]] + 0.03)
    faces = []
    for idx, uvs, nrm, mat, sels in m.faces:
        flat = all(vdot(nrm[0], n) > 0.9999 for n in nrm)
        if flat:
            n = vnorm(r3.newell([m.pts[i] for i in idx]))
            nrm = (n,) * len(idx)
        else:
            nrm = tuple(mat_vec(Rs[i], n) for i, n in zip(idx, nrm))
        faces.append((idx, uvs, nrm, mat, sels))
    m.faces = faces
    # volumes simples : transformation rigide par pièce
    for s in m.solids:
        if s.tag[0] == "ground":
            continue
        if s.tag[0] == "equip":
            o, pos, R, torn = group_xf(s.tag)
            s.pts = [vadd(pos, mat_vec(R, vsub(p, o))) for p in s.pts]
            if torn:
                zl = min(p[2] for p in s.pts)
                s.pts = [(p[0], p[1], p[2] - zl + 0.03) for p in s.pts]
        else:
            c = tuple(sum(p[k] for p in s.pts) / len(s.pts) for k in range(3))
            if c[2] > zb:
                pos, R = xf((c[0], c[1], 0), pivot_z=None) if False else xf(c)
                s.pts = [vadd(pos, mat_vec(R, vsub(p, c))) for p in s.pts]
                zl = min(p[2] for p in s.pts)
                if zl < 0.02:
                    s.pts = [(p[0], p[1], p[2] - zl + 0.02) for p in s.pts]
    m.roads = [q for q in m.roads if max(p[2] for p in q) < 1.0]
    m.mem = {}
    # câbles pendants du tronçon resté debout et débris au sol
    with m.push(tag="ground"):
        if m.d <= 2:
            for k in range(3):
                ang = az + math.pi + (k - 1) * 0.9
                top = (0.2 * w * math.cos(ang), 0.2 * w * math.sin(ang), zb - 0.2)
                end = (top[0] + (1.5 + k) * math.cos(ang + 0.4), top[1] + (1.5 + k) * math.sin(ang + 0.4), 0.03)
                cable(m, catenary(top, end, 0.8 + 0.3 * k, 6 if m.d == 0 else 3), 0.014, "cb_black", n=(5, 4, 3)[m.d])
        if m.d <= 3:
            for k in range(7):
                x, y = f[0] * (2 + k * 1.7) + rng.uniform(-3, 3), f[1] * (2 + k * 1.7) + rng.uniform(-3, 3)
                ang = rng.uniform(0, math.pi)
                L = rng.uniform(0.8, 2.4)
                r3.angle(m, (x, y, 0.03), (x + L * math.cos(ang), y + L * math.sin(ang), 0.03 + rng.uniform(0, 0.25)), 0.07, "st_b", (0, 0, 1), (-math.sin(ang), math.cos(ang), 0))


# --------------------------------------------------------------------------------------------------------------------
# Écriture
# --------------------------------------------------------------------------------------------------------------------
MODELS = {
    # nom : (constructeur, masse kg)
    "relay_tower_30": (tower_30, 9000.0), "relay_tower_40": (tower_40, 14000.0), "relay_tower_50": (tower_50, 26000.0),
    "relay_tower_m12": (tower_m12, 180.0), "relay_tower_m20": (tower_m20, 1900.0),
    "relay_monopole_30": (monopole_30, 11000.0), "relay_guyed_60": (guyed_60, 7000.0), "relay_rooftop_8": (rooftop_8, 1600.0),
}
KIT_NAMES = ("relay_kit_sat", "relay_kit_field", "relay_kit_mini", "relay_wall_sector", "relay_wall_omni", "relay_wall_dish",
             "relay_wall_yagi", "relay_wall_radio", "relay_pole_node", "relay_roof_mast")   # model_relay_kits.py
TREE_NAMES = ("relay_tree_pine_28", "relay_tree_pine_15", "relay_tree_palm_22")   # model_relay_trees.py
BONES = ("eq1", "eq2")
SECTIONS = ("camo", "led", "lights")


def finish(m):
    """Sélection « camo » : toutes les faces de la planche de profilés (texture remplacée par hiddenSelections)."""
    out = []
    for idx, uvs, nrm, mat, sels in m.faces:
        if r3.MATS[mat].atlas == "relay_steel" and "camo" not in sels:
            sels = tuple(sels) + ("camo",)
        out.append((idx, uvs, nrm, mat, sels))
    m.faces = out
    return m


def write_model_cfg(path, names, bones=BONES, sections=SECTIONS, extra=""):
    names = list(names) + [n for n in TREE_NAMES if n not in names]
    lines = ["// Modèles relais (tools/model_relay_towers.py) : sections retexturables et équipements masquables.",
             "class CfgSkeletons {", "    class Default { isDiscrete=1; skeletonInherit=\"\"; skeletonBones[]={}; };",
             "    class comspec_relay_skel: Default { skeletonBones[]={%s}; };" % ",".join('"%s",""' % b for b in bones), "};",
             "class CfgModels {", "    class Default { sectionsInherit=\"\"; sections[]={}; skeletonName=\"\"; };",
             "    class comspec_relay_model: Default {", "        skeletonName=\"comspec_relay_skel\";",
             "        sections[]={%s};" % ",".join('"%s"' % s for s in sections), "        class Animations {"]
    for b in bones:
        lines.append("            class %s { type=\"hide\"; source=\"%s\"; selection=\"%s\"; minValue=0; maxValue=1; hideValue=0.5; };" % (b, b, b))
    lines += ["        };", "    };"]
    for n in names:
        lines.append("    class %s: comspec_relay_model {};" % n)
    lines.append("    class comspec_relay_ruin: Default { sections[]={\"camo\"}; };")
    for n in names:
        lines.append("    class %s_ruin: comspec_relay_ruin {};" % n)
    lines.append("    class comspec_relay_kit: Default { sections[]={%s}; };" % ",".join('"%s"' % s for s in sections))
    for n in KIT_NAMES:
        lines.append("    class %s: comspec_relay_kit {};" % n)
    lines.append(extra + "};")
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(lines) + "\n")


def main():
    only = [s for s in os.environ.get("ONLY", "").split(",") if s]
    obj_dir = os.environ.get("OBJ_DIR", "")
    tmp = obj_dir or tempfile.mkdtemp(prefix="relay_tex_")
    relay_tex.declare_flat()
    if os.environ.get("NO_TEX"):
        _declare_only()
        png = {}
    else:
        png = relay_tex.build_textures(os.path.join(tmp, "tex"), DATA, convert=not os.environ.get("NO_PAA"))
    report = {}
    for name, (fn, mass) in MODELS.items():
        if only and not any(name.endswith("_" + o) or name == o for o in only):
            continue
        props = (("map", "transmitter"), ("prefershadowvolume", "1"), ("sbsource", "shadowvolume"))
        m0 = r3.write_p3d(os.path.join(DATA, name + ".p3d"), lambda d, fn=fn: finish(fn(d)), mass, props, report=report)
        r3.check_mesh(m0, name)
        r3.write_p3d(os.path.join(DATA, name + "_ruin.p3d"), lambda d, fn=fn: finish(fn(d + 1, ruin=True)), mass,
                     (("prefershadowvolume", "1"), ("sbsource", "shadowvolume")), lods=4, shadow_from=1, res=(1.0, 4.0, 12.0, 30.0), report=report)
        st = report[name + ".p3d"]
        print("%-22s %s  %.2f Mo | ruine %s %.2f Mo" % (name, " / ".join("%d" % t for _, _, t, _ in st["lods"]), st["size"] / 1e6,
              " / ".join("%d" % t for _, _, t, _ in report[name + "_ruin.p3d"]["lods"]), report[name + "_ruin.p3d"]["size"] / 1e6))
        if obj_dir:
            r3.export_obj(m0, os.path.join(obj_dir, name + ".obj"), png)
            r3.export_obj(finish(fn(1, ruin=True)), os.path.join(obj_dir, name + "_ruin.obj"), png)
    if not only:
        write_model_cfg(os.path.join(DATA, "model.cfg"), list(MODELS))
    with open(os.path.join(tmp, "report_towers.json"), "w") as f:
        json.dump(report, f, indent=1)
    print("rapport :", os.path.join(tmp, "report_towers.json"))


def _declare_only():
    """Déclare les régions des atlas sans les peindre (NO_TEX=1 : géométrie seule)."""
    at = r3.Atlas("relay_steel", 2048, 1024, relay_tex.TEX, "relay_steel.rvmat")
    y = 0
    for key, h, tile in relay_tex.STEEL_ROWS:
        at.region(key, 0, y, 2048, h, "strip", tile)
        y += h
    at = r3.Atlas("relay_mesh", 512, 512, relay_tex.TEX, "relay_mesh.rvmat")
    at.tex = relay_tex.TEX + "relay_mesh_ca.paa"
    at.region("fence", 0, 0, 512, 256, "strip", 1.0, pad=0)
    at.region("grating", 0, 256, 512, 256, "strip", 0.5, pad=0)
    at = r3.Atlas("relay_equip", 2048, 2048, relay_tex.TEX, "relay_equip.rvmat")
    for i, (key, tile) in enumerate(relay_tex.EQUIP_STRIPS):
        at.region(key, 0, i * 32, 2048, 32, "strip", tile, pad=3)
    for key, (x, y, w, h) in relay_tex.EQUIP_RECTS.items():
        at.region(key, x, y, w, h, "rect", pad=3)


if __name__ == "__main__":
    main()
