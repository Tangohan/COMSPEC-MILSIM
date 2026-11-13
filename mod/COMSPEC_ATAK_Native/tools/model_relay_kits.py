"""
Kits d'antennes déployables et antennes murales du réseau ATAK (mêmes atlas que les pylônes : relay_tex.py).

Kits posés par le joueur (fn_antennaKit ; remplacent les modèles d'Arma, mêmes classes) :
  relay_kit_sat     terminal satellite de campagne : parabole 1,2 m sur trépied, bras de source, BUC / LNB, caisses
  relay_kit_field   relais de terrain portable : mât emboîtable 4,5 m sur trépied, haubans, radio, batterie en caisse
  relay_kit_mini    mini-antenne de bâtiment : radio MANET et fouet sur équerre universelle (mur ou toit).
                    Origine à 5 cm de la surface visée (fn_antennaKit pose l'objet à 5 cm), +Y sortant du mur.
Antennes murales (Eden / Zeus). Origine au plan du mur (Y = 0), +Y sortant du mur, verticale = Z :
  relay_wall_sector antenne panneau de secteur sur bras de déport, radio et descente de câble sous tube
  relay_wall_omni   fouet colinéaire sur équerre, radio
  relay_wall_dish   petite parabole hertzienne 0,4 m sur déport
  relay_wall_yagi   antenne Yagi 8 éléments sur potence
  relay_wall_radio  boîtier radio extérieur étanche (presse-étoupes, deux antennes), boîte de jonction
  relay_pole_node   nœud sur poteau ou lampadaire : colliers à bande, potence, fouet et radio (origine sur l'axe du poteau)
  relay_roof_mast   mât de toit court 2,8 m sur châssis lesté (dalles béton, toit non percé), panneau, fouet, radio

    python model_relay_kits.py        (NO_TEX=1 : les textures sont celles de model_relay_towers.py ; OBJ_DIR=dossier)
"""
import json
import math
import os
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import relay3d as r3  # noqa: E402
import relay_tex  # noqa: E402
from model_relay_towers import DATA, _declare_only, finish, telescopic  # noqa: E402
from relay3d import Mesh, basis, beam, bolt, box, cable, lathe, plate, rot, tube, vadd, vlerp, vmul  # noqa: E402
from relay_parts import UP, dish, earth_rod, guy, obstruction_light, omni, panel_antenna, radio_box, sandbag, stake, transit_case  # noqa: E402


def wall_plate(m, w=0.22, h=0.22, z=0.0, x=0.0):
    """Platine murale chevillée (quatre tirefonds), dans le plan du mur."""
    with m.push(t=(x, 0, z)):
        box(m, -w / 2, w / 2, 0.0, 0.008, -h / 2, h / 2, "st_plate", skip="-y")
        if m.d == 0:
            for sx in (-1, 1):
                for sz in (-1, 1):
                    bolt(m, (sx * (w / 2 - 0.03), 0.008, sz * (h / 2 - 0.03)), (0, 1, 0), 0.011, 0.01, "st_pipe")


def conduit(m, top, length=1.0, junction=True, r=0.011):
    """Descente de câble sous tube gris le long du mur (colliers), boîte de jonction en pied."""
    d = m.d
    if d >= 3:
        return
    x, z = top
    tube(m, (x, r + 0.004, z), (x, r + 0.004, z - length), r, "cb_grey", n=(6, 5, 3)[d], caps=(False, False))
    if d == 0:
        k = 0.15
        while k < length:
            box(m, x - 0.03, x + 0.03, 0.0, 2 * r + 0.008, z - k - 0.008, z - k + 0.008, "st_pipe", skip="-y")
            k += 0.4
    if junction:
        box(m, x - 0.06, x + 0.06, 0.0, 0.055, z - length - 0.16, z - length, "plastic", skip="-y", bevel=0.008 if d == 0 else 0, S="s")


def wall_radio_unit(m, z=0.0, x=0.0, ants=2, down=0.9):
    """Radio MANET en coffret étanche plaquée au mur sur sa platine, câbles vers le bas."""
    wall_plate(m, 0.24, 0.3, z, x)
    with m.push(R=rot("z", 90), t=(x, 0.008, z)):
        radio_box(m, ants=ants)
    if down:
        conduit(m, (x, z - 0.2), down)


def build_wall_sector(d):
    m = Mesh(d, seed=101)
    for z in (-0.5, 0.5):
        wall_plate(m, z=z)
        if d <= 3:
            beam(m, (0, 0.008, z), (0, 0.40, z), 0.05, 0.05, "st_a", a=UP, S="s")
        if d <= 1:
            beam(m, (0, 0.02, z - 0.09), (0, 0.33, z - 0.01), 0.03, 0.03, "st_a", a=(1, 0, 0), caps=False)
    with m.push(R=rot("z", 90), t=(0, 0.40, 0.0)):
        panel_antenna(m, h=1.3, w=0.26, t=0.11, pipe=1.7, rru=False, tilt=3)
    r3.solid_box(m, -0.14, 0.14, 0.0, 0.62, -0.85, 0.85, "gfv")
    wall_radio_unit(m, z=-1.15, x=0.0, ants=0, down=0.8)
    if d <= 1:
        for x in (-0.05, 0.05):
            cable(m, [(x, 0.06, -0.98), (x, 0.2, -0.85), (x * 0.8, 0.5, -0.78), (x * 0.8, 0.56, -0.66)], 0.007, "cb_black", n=4)
    return m


def build_wall_omni(d):
    m = Mesh(d, seed=102)
    wall_plate(m, 0.16, 0.36, 0.0)
    if d <= 3:
        beam(m, (0, 0.008, 0.12), (0, 0.34, 0.12), 0.04, 0.04, "st_a", a=UP, S="s")
    if d <= 2:
        beam(m, (0, 0.01, -0.13), (0, 0.30, 0.10), 0.03, 0.03, "st_a", a=(1, 0, 0), caps=False)
        tube(m, (0, 0.32, 0.02), (0, 0.32, 0.30), 0.022, "st_pipe", n=(8, 6, 4)[d], caps=(True, True))
    with m.push(t=(0, 0.32, 0.30)):
        omni(m, L=1.25)
    r3.solid_box(m, -0.05, 0.05, 0.0, 0.36, -0.2, 1.55, "gfv")
    wall_radio_unit(m, z=-0.55, ants=0, down=0.7)
    if d <= 1:
        cable(m, [(0.0, 0.06, -0.38), (0.0, 0.2, -0.25), (0.02, 0.33, -0.1), (0.025, 0.345, 0.05)], 0.007, "cb_black", n=4)
    return m


def build_wall_dish(d):
    m = Mesh(d, seed=103)
    for z in (-0.25, 0.25):
        wall_plate(m, 0.18, 0.18, z)
        if d <= 3:
            beam(m, (0, 0.008, z), (0, 0.24, z), 0.045, 0.045, "st_a", a=UP, S="s")
    with m.push(R=rot("z", 78), t=(0, 0.24, 0.0)):
        dish(m, D=0.4, pipe=0.8, pipe_r=0.028)
    r3.solid_box(m, -0.22, 0.22, 0.0, 0.6, -0.4, 0.4, "gfv")
    conduit(m, (0.1, -0.3), 0.8)
    if d <= 1:
        cable(m, [(0.0, 0.2, -0.42), (0.04, 0.12, -0.44), (0.1, 0.03, -0.36), (0.1, 0.02, -0.3)], 0.007, "cb_black", n=4)
    return m


def yagi(m, boom=0.95, n=8):
    """Antenne Yagi : bras carré le long de +Y, réflecteur, dipôle replié sous capot, directeurs décroissants."""
    d = m.d
    with m.push(tag="equip"):
        beam(m, (0, 0, 0), (0, boom, 0), 0.02, 0.02, "st_pipe", a=UP, S="s")
        ys = [0.03] + [0.14 + (boom - 0.19) * k / (n - 2) for k in range(n - 1)]
        Ls = [0.36, 0.33] + [0.30 - 0.012 * k for k in range(n - 2)]
        for k, (y, L) in enumerate(zip(ys, Ls)):
            if d <= 3:
                tube(m, (-L / 2, y, 0.012), (L / 2, y, 0.012), 0.005 if d <= 1 else 0.008, "st_pipe", n=(6, 4, 3, 3)[d], caps=(d == 0, d == 0))
            if k == 1 and d <= 2:
                box(m, -0.035, 0.035, y - 0.02, y + 0.02, -0.03, 0.03, "plastic")


def build_wall_yagi(d):
    m = Mesh(d, seed=104)
    wall_plate(m, 0.16, 0.3, 0.0)
    if d <= 3:
        beam(m, (0, 0.008, 0.0), (0, 0.3, 0.0), 0.04, 0.04, "st_a", a=UP, S="s")
        tube(m, (0, 0.3, -0.12), (0, 0.3, 0.55), 0.02, "st_pipe", n=(8, 6, 4, 3)[d], caps=(True, True))
    if d <= 2:
        beam(m, (0, 0.01, -0.12), (0, 0.27, -0.02), 0.025, 0.025, "st_a", a=(1, 0, 0), caps=False)
    with m.push(R=rot("z", -12), t=(0, 0.32, 0.45)):
        with m.push(R=rot("x", 4)):
            yagi(m)
    r3.solid_box(m, -0.19, 0.19, 0.0, 1.3, -0.15, 0.56, "gfv")
    conduit(m, (0.0, -0.16), 0.9)
    if d <= 1:
        cable(m, [(0.02, 0.48, 0.43), (0.03, 0.36, 0.3), (0.025, 0.31, 0.0), (0.0, 0.03, -0.1), (0.0, 0.02, -0.16)], 0.006, "cb_black", n=4)
    return m


def build_wall_radio(d):
    m = Mesh(d, seed=105)
    wall_radio_unit(m, z=0.0, ants=2, down=0.75)
    r3.solid_box(m, -0.14, 0.14, 0.0, 0.13, -0.2, 0.5, "gfv")
    return m


def build_kit_mini(d):
    """Mini-antenne de bâtiment. Origine à 5 cm de la surface : équerre dont le dos (Y = -0,05) plaque au mur et la
    semelle (Z = -0,05) pose sur un toit ; radio debout, fouet court."""
    m = Mesh(d, seed=106)
    with m.push(t=(0, -0.05, 0)):
        box(m, -0.11, 0.11, 0.0, 0.006, -0.05, 0.36, "st_plate")
        box(m, -0.11, 0.11, 0.0, 0.2, -0.05, -0.044, "st_plate")
        if d == 0:
            for x in (-0.08, 0.08):
                bolt(m, (x, 0.006, 0.3), (0, 1, 0), 0.01, 0.008, "st_pipe")
                bolt(m, (x, 0.15, -0.044), (0, 0, 1), 0.01, 0.008, "st_pipe")
            plate(m, [(-0.108, 0.006, -0.044), (-0.108, 0.1, -0.044), (-0.108, 0.006, 0.06)], 0.004, "st_plate", (1, 0, 0))
            plate(m, [(0.104, 0.006, -0.044), (0.104, 0.1, -0.044), (0.104, 0.006, 0.06)], 0.004, "st_plate", (1, 0, 0))
        with m.push(R=rot("z", 90), t=(0, 0.006, 0.15)):
            radio_box(m, w=0.2, t=0.09, h=0.27, ants=1)
        if d <= 2:
            tube(m, (0.07, 0.05, 0.285), (0.07, 0.05, 0.33), 0.012, "st_pipe", n=(6, 5, 4)[d], caps=(False, False))
        with m.push(t=(0.07, 0.05, 0.33)):
            omni(m, L=0.55, r=0.009, base=0.08)
        r3.solid_box(m, -0.11, 0.11, 0.0, 0.2, -0.05, 0.36, "sSgfv")
    return m


def build_pole_node(d):
    """Nœud sur poteau : origine sur l'axe du poteau (Ø 12 à 20 cm), colliers à bande, potence vers +Y."""
    m = Mesh(d, seed=107)
    rp = 0.085
    for z in (-0.18, 0.18):
        if d <= 2:
            with m.push(t=(0, 0, z)):
                lathe(m, [(rp, -0.02), (rp + 0.004, -0.02), (rp + 0.004, 0.02), (rp, 0.02)], "st_pipe", n=(16, 10, 6)[d])
                box(m, -0.03, 0.03, rp, rp + 0.03, -0.03, 0.03, "st_plate")
    if d <= 3:
        beam(m, (0, rp + 0.02, 0.18), (0, rp + 0.36, 0.18), 0.04, 0.04, "st_a", a=UP, S="s")
    if d <= 2:
        beam(m, (0, rp + 0.03, -0.18), (0, rp + 0.33, 0.16), 0.03, 0.03, "st_a", a=(1, 0, 0), caps=False)
        tube(m, (0, rp + 0.34, 0.1), (0, rp + 0.34, 0.36), 0.022, "st_pipe", n=(8, 6, 4)[d], caps=(True, True))
    with m.push(t=(0, rp + 0.34, 0.36)):
        omni(m, L=1.1)
    with m.push(R=rot("z", 90), t=(0, rp + 0.03, -0.05)):
        radio_box(m, ants=1)
    if d <= 1:
        cable(m, [(0.02, rp + 0.09, 0.12), (0.03, rp + 0.2, 0.2), (0.025, rp + 0.33, 0.14)], 0.007, "cb_black", n=4)
        cable(m, [(-0.05, rp + 0.09, -0.24), (-0.06, rp + 0.05, -0.4), (-0.06, rp + 0.012, -0.5), (-0.06, rp + 0.012, -1.4)], 0.008, "cb_black", n=4)
    r3.solid_box(m, -0.14, 0.14, rp, rp + 0.4, -0.25, 0.4, "gfv")
    return m


def build_roof_mast(d):
    m = Mesh(d, seed=108)
    with m.push(tag="ground"):
        # châssis en cornières, tapis caoutchouc, dalles de lestage aux angles
        for a in (0, 90, 180, 270):
            with m.push(R=rot("z", a)):
                if d <= 3:
                    r3.angle(m, (-0.65, -0.65, 0.03), (0.65, -0.65, 0.03), 0.05, "st_a", (0, 1, 0), (0, 0, 1), S="s")
                with m.push(t=(0.45, 0.45, 0.0)):
                    box(m, -0.24, 0.24, -0.24, 0.24, 0.0, 0.02, "cb_rubber", skip="-z")
                    box(m, -0.2, 0.2, -0.2, 0.2, 0.035, 0.105, "concrete", S="sSgfv")
                    if d <= 2:
                        box(m, -0.2, 0.2, -0.2, 0.2, 0.108, 0.178, "concrete")
                if d <= 2:
                    beam(m, (0.0, 0.0, 0.05), (0.62, 0.62, 0.05), 0.05, 0.04, "st_a", a=UP, caps=False)
                    tube(m, (0.5, 0.5, 0.07), (0.03, 0.03, 1.1), 0.014, "st_pipe", n=(6, 4, 3)[d], caps=(False, False))
        if d <= 2:
            lathe(m, [(0.1, 0.03), (0.1, 0.045), (0.04, 0.07)], "st_plate", n=(12, 8, 6)[d])
    tube(m, (0, 0, 0.03), (0, 0, 2.85), 0.03, "st_a", n=(10, 8, 6, 4, 3)[d], caps=(False, True), S="sSgf")
    with m.push(R=rot("z", 90), t=(0, 0.035, 2.1)):
        panel_antenna(m, h=1.0, w=0.22, t=0.09, pipe=1.2, rru=False, pipe_r=0.02, tilt=2)
    with m.push(t=(0, 0, 2.85)):
        omni(m, L=0.9, r=0.011, base=0.12)
    with m.push(R=rot("z", -90), t=(0, -0.03, 1.25)):
        radio_box(m)
    if d <= 1:
        cable(m, [(0.02, -0.08, 1.05), (0.03, -0.05, 0.4), (0.2, -0.3, 0.06), (0.62, -0.66, 0.04), (0.75, -0.8, 0.03)], 0.008, "cb_black", n=4)
    return m


def build_kit_field(d):
    """Relais de terrain portable : mât emboîtable 4,5 m, trépied, trois haubans sur sardines, radio, batterie en caisse."""
    m = Mesh(d, seed=109)
    H = 4.5
    with m.push(tag="ground"):
        for az in (90, 210, 330):
            a = math.radians(az)
            foot = (0.6 * math.cos(a), 0.6 * math.sin(a), 0.02)
            hub = (0.05 * math.cos(a), 0.05 * math.sin(a), 0.75)
            if d <= 3:
                tube(m, foot, hub, 0.014, "st_a", n=(6, 5, 4, 3)[d], caps=(False, False), S="s")
            if d <= 1:
                tube(m, vlerp(foot, hub, 0.5), (0.03 * math.cos(a), 0.03 * math.sin(a), 0.2), 0.008, "st_a", n=4, caps=(False, False))
        with m.push(t=(0.6 * math.cos(math.radians(210)) - 0.05, 0.6 * math.sin(math.radians(210)), 0.02), R=rot("z", 120)):
            if d <= 3:
                sandbag(m)
    telescopic(m, 0.02, H, 0.03, 0.016, 4, S="sSgf")
    for az in (30, 150, 270):
        a = math.radians(az)
        g = (2.4 * math.cos(a), 2.4 * math.sin(a), 0.0)
        top = stake(m, g, (0, 0, 0), sandbags=0)
        guy(m, (0.02 * math.cos(a), 0.02 * math.sin(a), 3.6), top, r=0.003, marker=False, turnbuckle=False)
    with m.push(t=(0, 0, H)):
        if d <= 2:
            tube(m, (-0.3, 0, -0.1), (0.3, 0, -0.1), 0.012, "st_pipe", n=(6, 4, 3)[d], caps=(d == 0, d == 0))
        for s in (-1, 1):
            with m.push(t=(s * 0.3, 0, -0.1)):
                omni(m, L=0.8, r=0.009, base=0.1)
    with m.push(R=rot("z", -90), t=(0.0, -0.022, 3.85)):
        radio_box(m, w=0.2, t=0.09, h=0.27, ants=0)
    if d <= 2:
        cable(m, [(0.01, -0.04, 3.7), (0.02, -0.045, 1.0), (0.05, -0.2, 0.05), (0.5, -0.62, 0.03), (0.62, -0.7, 0.2)], 0.006, "cb_black", n=(5, 4, 3)[d])
    with m.push(R=rot("z", 20), t=(0.85, -0.85, 0.0)):
        transit_case(m, l=0.62, w=0.45, h=0.32)
    earth_rod(m, (-0.5, -0.6), (-0.02, -0.03, 0.3))
    return m


def build_kit_sat(d):
    """Terminal satellite de campagne : réflecteur 1,2 m, monture azimut / site sur trépied, bras de source, BUC / LNB."""
    m = Mesh(d, seed=110)
    el = 38.0
    with m.push(tag="ground"):
        for az in (90, 210, 330):
            a = math.radians(az)
            foot = (0.85 * math.cos(a), 0.85 * math.sin(a), 0.03)
            hub = (0.07 * math.cos(a), 0.07 * math.sin(a), 0.78)
            if d <= 3:
                tube(m, foot, hub, 0.022, "st_a", n=(8, 6, 4, 3)[d], caps=(False, False), S="sg")
            if d <= 2:
                tube(m, vlerp(foot, hub, 0.5), (0.04 * math.cos(a), 0.04 * math.sin(a), 0.22), 0.012, "st_a", n=(6, 4, 3)[d], caps=(False, False))
                with m.push(t=(foot[0], foot[1], 0.0)):
                    lathe(m, [(0.09, 0.0), (0.08, 0.025), (0.0, 0.04)], "st_pipe", n=(10, 6, 5)[d])
            with m.push(R=rot("z", az + 90), t=(foot[0] * 1.03, foot[1] * 1.03, 0.04)):
                if d <= 3:
                    sandbag(m)
        if d <= 3:
            tube(m, (0, 0, 0.2), (0, 0, 1.02), 0.04, "st_a", n=(10, 8, 6, 4)[d], caps=(True, True), S="sSgf")
        if d <= 2:
            box(m, -0.09, 0.09, -0.08, 0.08, 1.02, 1.2, "olive", S="s")       # monture azimut / site
            tube(m, (-0.13, 0, 1.11), (0.13, 0, 1.11), 0.02, "st_pipe", n=(8, 6, 4)[d])
    # réflecteur : axe local Z = visée (vers +Y, relevée de el)
    c, s = math.cos(math.radians(el)), math.sin(math.radians(el))
    axis = (0.0, c, s)
    R = 0.6
    f = 0.45
    n = (24, 16, 10, 8, 6)[d]
    with m.push(tag="equip"):
        with m.push(R=basis(x=(1, 0, 0), z=axis), t=(0.0, 0.22 * c, 1.11 + 0.22 * s + 0.12)):
            ks = (0.12, 0.35, 0.6, 0.82, 1.0) if d <= 1 else (0.12, 0.6, 1.0)
            prof = [(R * k, (R * k) ** 2 / (4 * f)) for k in ks]
            lathe(m, [(0.0, 0.0)] + prof if False else prof, "dish_back", n=n, polar=True, closed=(True, False))
            lathe(m, [(r, z + 0.012) for r, z in reversed(prof)], "dish_front", n=n, polar=True)
            if d <= 2:
                lathe(m, [(R, R * R / (4 * f)), (R + 0.012, R * R / (4 * f) + 0.006), (R, R * R / (4 * f) + 0.012)], "st_pipe", n=n)
                lathe(m, [(0.09, -0.14), (0.09, 0.012)], "olive", n=(10, 6, 5)[d], closed=(True, False))
            zf = f
            # bras de source : trois jambes du bord vers la source, cornet, LNB et BUC
            if d <= 2:
                for a in (-90, 30, 150):
                    ar = math.radians(a)
                    tube(m, (R * 0.97 * math.cos(ar), R * 0.97 * math.sin(ar), R * R / (4 * f)), (0.04 * math.cos(ar), 0.04 * math.sin(ar), zf + 0.02), 0.008, "st_pipe", n=(5, 4, 3)[d], caps=(False, False))
                lathe(m, [(0.02, zf + 0.06), (0.05, zf - 0.04)] if False else [(0.05, zf - 0.04), (0.022, zf + 0.06)], "st_pipe", n=(10, 6, 5)[d], closed=(True, True))
                box(m, -0.035, 0.035, -0.03, 0.03, zf + 0.06, zf + 0.2, "plastic")
                box(m, 0.035, 0.11, -0.04, 0.04, zf + 0.03, zf + 0.21, {"+x": "rru_front", "*": "rru_side"})
            r3.add_prism_solid(m, (0, 0, -0.02), (0, 0, 0.2), (1, 0, 0), (0, 1, 0), [(R * 0.9, 0), (0, R * 0.9), (-R * 0.9, 0), (0, -R * 0.9)], "sSgfv")
    # caisses : modem (voyant), alimentation ; câbles
    with m.push(R=rot("z", 15), t=(1.25, -0.5, 0.0)):
        transit_case(m, l=0.75, w=0.5, h=0.38)
        with m.push(sels=("led",)):
            if d <= 2:
                box(m, -0.2, -0.14, -0.262, -0.25, 0.26, 0.29, "led", skip="+y")
    with m.push(R=rot("z", -20), t=(1.2, 0.35, 0.0)):
        transit_case(m, l=0.55, w=0.4, h=0.28)
    if d <= 1:
        cable(m, [(0.03, 0.2, 1.3), (0.06, 0.02, 1.0), (0.07, -0.05, 0.3), (0.4, -0.3, 0.03), (0.95, -0.48, 0.03), (1.0, -0.5, 0.2)], 0.007, "cb_black", n=4)
        cable(m, [(1.2, -0.3, 0.2), (1.25, -0.05, 0.03), (1.2, 0.2, 0.15)], 0.007, "cb_fibre", n=4)
    earth_rod(m, (-0.7, -0.9), (-0.03, -0.03, 0.4))
    return m


MODELS = {
    # nom : (constructeur, masse kg, détails des LOD)
    "relay_kit_sat": (build_kit_sat, 60.0, (0, 1, 2, 3, 4)), "relay_kit_field": (build_kit_field, 25.0, (0, 1, 2, 3, 4)),
    "relay_kit_mini": (build_kit_mini, 4.0, (0, 1, 2, 4)),
    "relay_wall_sector": (build_wall_sector, 30.0, (0, 1, 2, 4)), "relay_wall_omni": (build_wall_omni, 8.0, (0, 1, 2, 4)),
    "relay_wall_dish": (build_wall_dish, 12.0, (0, 1, 2, 4)), "relay_wall_yagi": (build_wall_yagi, 6.0, (0, 1, 2, 4)),
    "relay_wall_radio": (build_wall_radio, 4.0, (0, 1, 2, 4)), "relay_pole_node": (build_pole_node, 9.0, (0, 1, 2, 4)),
    "relay_roof_mast": (build_roof_mast, 140.0, (0, 1, 2, 3, 4)),
}


def main():
    only = [s for s in os.environ.get("ONLY", "").split(",") if s]
    obj_dir = os.environ.get("OBJ_DIR", "")
    tmp = obj_dir or tempfile.mkdtemp(prefix="relay_kits_")
    relay_tex.declare_flat()
    _declare_only()
    png = {}
    if obj_dir and os.path.exists(os.path.join(obj_dir, "tex", "relay_steel_co.png")):
        t = os.path.join(obj_dir, "tex")
        T = relay_tex.TEX
        png = {T + "relay_steel_co.paa": {k: os.path.join(t, "relay_steel_%s.png" % k) for k in ("co", "nohq", "smdi", "rm")},
               T + "relay_equip_co.paa": {k: os.path.join(t, "relay_equip_%s.png" % k) for k in ("co", "nohq", "smdi", "rm")},
               T + "relay_mesh_ca.paa": {"co": os.path.join(t, "relay_mesh_ca.png"), "alpha": True}}
    report = {}
    for name, (fn, mass, ds) in MODELS.items():
        if only and not any(name.endswith("_" + o) for o in only):
            continue
        m0 = r3.write_p3d(os.path.join(DATA, name + ".p3d"), lambda i, fn=fn, ds=ds: finish(fn(ds[i])), mass,
                          (("prefershadowvolume", "1"), ("sbsource", "shadowvolume")), lods=len(ds), shadow_from=2,
                          res=(1.0, 4.0, 12.0, 30.0, 80.0)[:len(ds)], report=report)
        r3.check_mesh(m0, name)
        st = report[name + ".p3d"]
        print("%-20s %s  %.2f Mo" % (name, " / ".join("%d" % t for _, _, t, _ in st["lods"]), st["size"] / 1e6))
        if obj_dir:
            r3.export_obj(m0, os.path.join(obj_dir, name + ".obj"), png)
    with open(os.path.join(tmp, "report_kits.json"), "w") as f:
        json.dump(report, f, indent=1)


if __name__ == "__main__":
    main()
