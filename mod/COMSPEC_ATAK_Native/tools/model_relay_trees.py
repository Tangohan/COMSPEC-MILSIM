"""
Pylônes relais déguisés en arbre (« arbre de loin, antenne de près », comme les vrais faux pins des opérateurs) :

  relay_tree_pine_28   faux pin 28 m : fût d'acier habillé d'écorce (trop droit, trop régulier), bride boulonnée sur dalle,
                       échelons, gaine de câbles, verticilles de branches artificielles de plus en plus courtes, antennes
                       panneaux peintes en vert parmi les branches, RRU, petite parabole, armoires dans un enclos
  relay_tree_pine_15   même faux pin, 15 m
  relay_tree_palm_22   faux palmier 22 m (terrains arides, Altis) : stipe annelé, couronne de palmes, antennes sous la couronne
  + ruines : fût cassé, partie haute couchée, branches artificielles éparpillées

Feuillage : cartes en alpha test (aucun tri), petit atlas relay_tree (1024, écorces en bandes, rameaux, palme, antenne
peinte). Ombre : prismes fermés (fût et houppier), collision : fût, dalle, armoires. Mêmes sélections et points mémoire
que les autres pylônes (model_relay_towers.py).

    python model_relay_trees.py        (NO_TEX=1 : sans textures ; OBJ_DIR=dossier : OBJ + PNG pour les rendus)
"""
import json
import math
import os
import random
import sys
import tempfile

import numpy as np
from PIL import Image, ImageDraw

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import relay3d as r3  # noqa: E402
import relay_tex  # noqa: E402
import model_relay_towers as towers  # noqa: E402
from model_relay_towers import DATA, collapse, finish  # noqa: E402
from relay3d import Atlas, Mesh, basis, box, cable, lathe, noise, plate, rot, tube, vadd  # noqa: E402
from relay_parts import cabinet, cable_bridge, dish, earth_rod, fence, obstruction_light  # noqa: E402

TEX = relay_tex.TEX
STRIPS = (("bark_pine", 128, 2.0), ("bark_palm", 128, 1.0))
RECTS = {"frond_a": (0, 256, 512, 256), "frond_b": (512, 256, 512, 256), "palm": (0, 512, 1024, 224),
         "tree_panel": (0, 736, 96, 288), "tree_panel_side": (96, 736, 48, 288), "tree_cap": (144, 736, 128, 128)}


def declare(at=None):
    at = at or Atlas("relay_tree", 1024, 1024, TEX, "relay_tree.rvmat")
    at.tex = TEX + "relay_tree_ca.paa"
    y = 0
    for key, h, tile in STRIPS:
        at.region(key, 0, y, 1024, h, "strip", tile)
        y += h
    for key, (x, y_, w, h) in RECTS.items():
        at.region(key, x, y_, w, h, "rect", pad=2)
    return at


def tree_atlas():
    rng = np.random.default_rng(2811)
    pr = random.Random(2811)
    at = declare()
    at.alpha[:] = 0
    # --- écorce de pin : plaques brun-gris, crevasses dans la longueur (U), reflets roux ---
    s = at.sl("bark_pine")
    a = noise(128, 1024, 1.6, aniso=(0.25, 1.0), rng=rng)
    b = noise(128, 1024, 0.8, rng=rng)
    crack = np.clip((a - 0.52) * 9, 0, 1)
    col = np.array([0.050, 0.030, 0.018])[None, None, :] * (1 - crack[..., None]) + np.array([0.20, 0.115, 0.065])[None, None, :] * crack[..., None]
    at.rgb[s] = col * (0.7 + 0.6 * b[..., None])
    at.alpha[s] = 1
    # --- stipe de palmier : cicatrices annulaires serrées (en travers de U), fibres ---
    s = at.sl("bark_palm")
    xx = np.arange(1024)[None, :] + 6 * np.sin(np.arange(128)[:, None] * 2 * math.pi / 128 * 3)
    ring = 0.5 + 0.5 * np.sin(xx * 2 * math.pi / 24.0)
    fib = noise(128, 1024, 1.2, aniso=(1.0, 0.2), rng=rng)
    g = 0.55 + 0.45 * ring ** 3
    at.rgb[s] = np.array([0.17, 0.125, 0.085])[None, None, :] * g[..., None] * (0.7 + 0.6 * fib[..., None])
    at.alpha[s] = 1

    def paste(key, img, base):
        sl = at.sl(key)
        arr = np.asarray(img, dtype=np.float64) / 255
        al = arr[..., 3]
        at.rgb[sl] = np.array(base)[None, None, :] * (1 - al[..., None]) + (arr[..., :3] ** 2.2) * al[..., None]
        at.alpha[sl] = (al > 0.3).astype(np.float64)

    # --- rameaux de pin artificiel : tige, brins d'aiguilles en brosse, deux variantes ---
    for key in ("frond_a", "frond_b"):
        w, h = 512, 256
        img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        d = ImageDraw.Draw(img)
        twigs = [((6, h / 2), (w - 10, h / 2 + pr.uniform(-10, 10)))]
        for k in range(11):
            x0 = 24 + k * 42 + pr.uniform(-8, 8)
            sgn = 1 if k % 2 else -1
            twigs.append(((x0, h / 2), (x0 + pr.uniform(60, 110), h / 2 + sgn * pr.uniform(60, 112))))
        for (x0, y0), (x1, y1) in twigs:
            n = int(math.hypot(x1 - x0, y1 - y0) / 1.3)
            ang = math.atan2(y1 - y0, x1 - x0)
            for i in range(n):
                t = i / max(1, n - 1)
                px, py = x0 + (x1 - x0) * t, y0 + (y1 - y0) * t
                for sgn in (-1, 1):
                    a_ = ang + sgn * pr.uniform(0.5, 1.15)
                    L = pr.uniform(16, 34) * (1 - 0.3 * t)
                    gcol = pr.choice(((44, 86, 44), (56, 104, 52), (36, 72, 40), (70, 118, 60), (48, 96, 58)))
                    d.line([px, py, px + L * math.cos(a_), py + L * math.sin(a_)], fill=gcol + (255,), width=3)
            d.line([x0, y0, x1, y1], fill=(66, 44, 26, 255), width=4 if (x0, y0) == twigs[0][0] else 3)
        paste(key, img, (0.03, 0.09, 0.035))
    # --- palme : rachis, folioles serrées effilées des deux côtés ---
    w, h = 1024, 224
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    for i in range(170):
        t = i / 169
        px = 30 + t * (w - 50)
        L = (h * 0.46) * math.sin(math.pi * min(1.0, 0.12 + t * 0.88)) ** 0.6 * (1 - 0.25 * t)
        for sgn in (-1, 1):
            gcol = pr.choice(((40, 78, 30), (52, 96, 38), (34, 66, 28), (64, 104, 44)))
            lean = pr.uniform(14, 30)
            d.polygon([(px - 3, h / 2), (px + 3, h / 2), (px + lean, h / 2 + sgn * L)], fill=gcol + (255,))
    d.line([4, h / 2, w - 16, h / 2], fill=(92, 88, 40, 255), width=7)
    paste("palm", img, (0.03, 0.07, 0.02))
    # --- antenne panneau peinte (vert sombre taché de brun, éclats) et capuchon ---
    for key, col in (("tree_panel", (0.035, 0.065, 0.03)), ("tree_panel_side", (0.03, 0.055, 0.028)), ("tree_cap", (0.06, 0.045, 0.028))):
        sl = at.sl(key)
        hh, ww = at.rgb[sl].shape[:2]
        n1 = noise(256, 256, 2.2, rng=rng)[:hh, :ww] if hh <= 256 and ww <= 256 else np.asarray(Image.fromarray((noise(256, 256, 2.2, rng=rng) * 255).astype(np.uint8)).resize((ww, hh)), dtype=np.float64) / 255
        blot = np.clip((n1 - 0.55) * 6, 0, 1)
        c = np.array(col)[None, None, :] * (1 - blot[..., None]) + np.array([0.075, 0.05, 0.03])[None, None, :] * blot[..., None]
        at.rgb[sl] = c * (0.8 + 0.4 * rng.random((hh, ww))[..., None])
        at.alpha[sl] = 1
    sl = at.sl("tree_panel")
    at.rgb[sl][:10] *= 0.5
    at.rgb[sl][-14:] *= 0.5
    at.spec[:], at.gloss[:], at.metal[:] = 0.08, 0.15, 0.0
    return at


def build_textures(tmp, data_dir, convert=True):
    at = tree_atlas()
    p = at.save(tmp, suffixes=("ca",), has_alpha=True)
    if data_dir:
        if convert:
            r3.to_paa(p["co"], os.path.join(data_dir, "relay_tree_ca.paa"))
        r3.write_rvmat_basic(os.path.join(data_dir, "relay_tree.rvmat"), "Relais en arbre : écorce, rameaux et palmes (alpha test).",
                             spec=0.05, power=20, extra="renderFlags[]={\"AlphaTest64\"};\n")
    return {at.tex: {"co": p["co"], "alpha": True, "foliage": True}}


# --------------------------------------------------------------------------------------------------------------------
def card(m, pts, mat, flip=False):
    """Carte de feuillage visible des deux côtés ; U le long de la branche (0 au fût)."""
    uv = [(0, 1), (1, 1), (1, 0), (0, 0)] if not flip else [(0, 0), (1, 0), (1, 1), (0, 1)]
    m.face(list(pts), mat, uvs=uv)
    m.face(list(reversed(pts)), mat, uvs=list(reversed(uv)))


def pine_branch(m, L, droop, w, k):
    """Branche artificielle (repère local : +X vers l'extérieur) : bras d'acier brun, deux cartes de rameaux croisées."""
    d = m.d
    x0 = 0.05
    mat = "frond_a" if k % 2 else "frond_b"
    if d <= 1:
        tube(m, (0, 0, 0), (L * 0.8, 0, -droop * 0.64), 0.022, "bark_pine", n=3, r1=0.008, caps=(False, False))
    card(m, [(x0, -w / 2, 0.02), (L, -w / 2, -droop), (L, w / 2, -droop), (x0, w / 2, 0.02)], mat)
    if d <= 1:
        card(m, [(x0, 0, -w * 0.32), (L, 0, -droop - w * 0.32), (L, 0, -droop + w * 0.32), (x0, 0, w * 0.32)], "frond_b" if k % 2 else "frond_a", flip=True)


def trunk(m, H, r0, r1, mat, z0=0.3, seg=4.0):
    d = m.d
    n = (14, 10, 8, 6, 5)[d]
    zs = [z0]
    while zs[-1] + seg < H - 0.5:
        zs.append(zs[-1] + seg)
    zs.append(H)
    if d >= 3:
        zs = [z0, H]
    for za, zb in zip(zs, zs[1:]):
        ra, rb = r0 + (r1 - r0) * (za - z0) / (H - z0), r0 + (r1 - r0) * (zb - z0) / (H - z0)
        tube(m, (0, 0, za), (0, 0, zb), ra, mat, n=n, r1=rb, caps=(False, zb == H), uoff=za)
    r3.add_prism_solid(m, (0, 0, z0), (0, 0, H), (1, 0, 0), (0, 1, 0), [(r0 * 0.85, 0), (0, r0 * 0.85), (-r0 * 0.85, 0), (0, -r0 * 0.85)], "sSgfv")


def base_and_site(m, r0, fence_box=(-3.4, 5.6, -3.6, 3.6), cabinets=2):
    d = m.d
    with m.push(tag="ground"):
        box(m, -1.4, 1.4, -1.4, 1.4, -2.6, 0.3, "concrete", S="gfv", skip="-z")
        m.road([(-1.4, -1.4, 0.3), (1.4, -1.4, 0.3), (1.4, 1.4, 0.3), (-1.4, 1.4, 0.3)])
    if d <= 2:
        with m.push(t=(0, 0, 0.3)):
            lathe(m, [(r0, 0.0), (r0 + 0.2, 0.0), (r0 + 0.2, 0.05), (r0, 0.05)], "st_plate", n=(20, 14, 8)[d])
            if d == 0:
                for k in range(12):
                    a = k * math.pi / 6
                    p = ((r0 + 0.12) * math.cos(a), (r0 + 0.12) * math.sin(a), 0.05)
                    tube(m, p, vadd(p, (0, 0, 0.12)), 0.017, "st_rust", n=6, caps=(False, True))
                    tube(m, p, vadd(p, (0, 0, 0.04)), 0.034, "st_pipe", n=6, caps=(False, True))
            if d <= 1:
                for k in range(6):
                    with m.push(R=rot("z", k * 60 + 30)):
                        plate(m, [(r0 - 0.005, -0.006, 0.05), (r0 + 0.18, -0.006, 0.05), (r0 - 0.005, -0.006, 0.32)], 0.012, "st_plate", (0, 1, 0))
        box(m, r0 - 0.02, r0 + 0.03, -0.15, 0.15, 1.0, 1.55, "st_plate", skip="-x")      # trappe de visite
    with m.push(t=(3.6, 0.5, 0.0), R=rot("z", 90)):
        with m.push(t=(-0.75, 0, 0)):
            cabinet(m)
        if cabinets > 1:
            with m.push(t=(0.75, 0, 0)):
                cabinet(m, w=1.0)
    cable_bridge(m, (r0 + 0.05, 0.0), (3.1, 0.0), 2.3, ncab=5)
    earth_rod(m, (1.2, -1.9), (r0 + 0.15, -0.4, 0.36))
    x0, x1, y0, y1 = fence_box
    fence(m, x0, x1, y0, y1, gate=(-1.0, 1.2), bay=2.4)


def green_panel(m, h=1.9, w=0.3, t=0.12, arm=0.55, rru=True):
    """Antenne panneau peinte en vert (repère local : +X = tir, origine contre le fût) : déport, tube, RRU derrière."""
    d = m.d
    with m.push(tag="equip"):
        if d <= 2:
            for dz in (-h * 0.3, h * 0.3):
                tube(m, (0, 0, dz), (arm, 0, dz), 0.022, "bark_pine", n=(5, 4, 3)[d], caps=(False, False))
            tube(m, (arm, 0, -h / 2 - 0.2), (arm, 0, h / 2 + 0.2), 0.028, "bark_pine", n=(6, 5, 3)[d], caps=(False, d == 0))
        if d <= 3:
            box(m, arm + 0.1, arm + 0.1 + t, -w / 2, w / 2, -h / 2, h / 2, {"+x": "tree_panel", "-x": "tree_panel", "+z": "tree_cap", "-z": "tree_cap", "*": "tree_panel_side"}, S="s")
        if rru and d <= 2:
            box(m, arm - 0.17, arm - 0.04, -0.15, 0.15, -h / 2 - 0.15, -h / 2 + 0.28, {"-x": "rru_front", "*": "rru_side"})
            if d <= 1:
                cable(m, [(arm - 0.1, 0.05, -h / 2 - 0.15), (arm - 0.05, 0.08, -h / 2 - 0.35), (arm + 0.15, 0.05, -h / 2 - 0.2), (arm + 0.16, 0.04, -h / 2)], 0.008, "cb_black", n=4)


def monopine(d, H=28.0, ruin=False, seed=28):
    m = Mesh(d, seed=seed)
    rnd = random.Random(seed)
    r0, r1 = 0.30 + H * 0.007, 0.12
    trunk(m, H, r0, r1, "bark_pine")
    rz = lambda z: r0 + (r1 - r0) * (z - 0.3) / (H - 0.3)
    base_and_site(m, r0, cabinets=2 if H > 20 else 1, fence_box=(-3.4, 5.6, -3.6, 3.6))
    # échelons alternés et gaine de câbles (le déguisement ne tient pas de près)
    if d <= 1:
        z, k = 3.0, 0
        while z < H * 0.42:
            s = 1 if k % 2 else -1
            r = rz(z)
            tube(m, (-r * 0.7, s * r * 0.7, z), (-r * 0.7 - 0.02, s * (r * 0.7 + 0.19), z), 0.011, "st_pipe", n=4, caps=(False, True))
            z += 0.4 if d == 0 else 0.8
            k += 1
    if d <= 2:
        box(m, r0 * 0.93, r0 * 0.93 + 0.07, -0.11, 0.11, 2.3, H * 0.62, "cb_black")
    # verticilles de branches : du premier tiers au sommet, de plus en plus courtes
    zb0 = H * 0.36
    step = 0.62 if H > 20 else 0.55
    levels = int((H - 0.6 - zb0) / step)
    Lmax = 3.4 if H > 20 else 2.6
    for lv in range(levels + 1):
        t = lv / max(1, levels)
        z = zb0 + lv * step
        L = Lmax * (1 - 0.72 * t ** 1.25) * (0.75 if lv == 0 else 1.0)
        nb = 6 if t < 0.75 else 5
        off = lv * 31.0
        if d >= 4 and lv % 2:
            continue
        if d >= 3:
            # de loin : trois grandes cartes traversantes par verticille
            with m.push(tag="equip", t=(0, 0, z)):
                for k in range(3 if d == 3 else 2):
                    with m.push(R=rot("z", off + k * (60 if d == 3 else 90))):
                        w = L * 0.95
                        card(m, [(-L, -w / 2, -L * 0.14), (L, -w / 2, -L * 0.14), (L, w / 2, -L * 0.14), (-L, w / 2, -L * 0.14)], "frond_a" if k % 2 else "frond_b")
            continue
        for k in range(nb):
            az = off + k * 360.0 / nb + rnd.uniform(-9, 9)
            Lk = L * rnd.uniform(0.86, 1.1)
            with m.push(tag="equip", R=rot("z", az), t=(rz(z) * 0.8 * math.cos(math.radians(az)), rz(z) * 0.8 * math.sin(math.radians(az)), z + rnd.uniform(-0.12, 0.12))):
                pine_branch(m, Lk, Lk * rnd.uniform(0.12, 0.26), Lk * 0.8 + 0.3, lv + k)
    # cime : bouquet dressé
    if d <= 3:
        for k in range(5 if d <= 2 else 3):
            with m.push(tag="equip", R=basis(x=(math.cos(k * 1.26) * 0.35, math.sin(k * 1.26) * 0.35, 1.0), z=(math.sin(k * 1.26), -math.cos(k * 1.26), 0.0)), t=(0, 0, H - 0.5)):
                pine_branch(m, 1.5, 0.0, 0.85, k)
    # houppier : volumes d'ombre et de vue (trois troncs de cône empilés)
    for i in range(3):
        za, zb = zb0 + (H - zb0) * i / 3, zb0 + (H - zb0) * (i + 1) / 3
        Ra = Lmax * (1 - 0.72 * (i / 3) ** 1.25) * 0.8
        r3.add_prism_solid(m, (0, 0, za), (0, 0, zb), (1, 0, 0), (0, 1, 0), [(Ra * math.cos(a * math.pi / 3), Ra * math.sin(a * math.pi / 3)) for a in range(6)], "sSv")
    with m.push(t=(0, 0, H)):
        obstruction_light(m, "light_top")
    # antennes parmi les branches
    zA = H - (5.8 if H > 20 else 3.6)
    with m.push(sels=("eq1",)):
        for az in (90, 210, 330):
            with m.push(R=rot("z", az), t=(rz(zA) * 0.9 * math.cos(math.radians(az)), rz(zA) * 0.9 * math.sin(math.radians(az)), zA)):
                green_panel(m)
    with m.push(sels=("eq2",)):
        zB = zA - 3.4
        if H > 20:
            for az in (30, 150, 270):
                with m.push(R=rot("z", az), t=(rz(zB) * 0.9 * math.cos(math.radians(az)), rz(zB) * 0.9 * math.sin(math.radians(az)), zB)):
                    green_panel(m, h=1.6, rru=(d <= 1))
        zD = zA - (6.0 if H > 20 else 2.6)
        with m.push(R=rot("z", 200), t=((rz(zD) + 0.06) * math.cos(math.radians(200)), (rz(zD) + 0.06) * math.sin(math.radians(200)), zD)):
            dish(m, D=0.3, pipe=0.7, pipe_r=0.024)
    if ruin:
        collapse(m, H * 0.2, H, math.radians(235), None, width=2 * r0, kink=104)
    return m


def monopalm(d, H=22.0, ruin=False):
    m = Mesh(d, seed=22)
    rnd = random.Random(22)
    r0, r1 = 0.36, 0.25
    trunk(m, H - 0.8, r0, r1, "bark_palm", seg=3.0)
    base_and_site(m, r0, cabinets=1, fence_box=(-3.0, 5.4, -3.2, 3.2))
    if d <= 2:
        box(m, r0 * 0.93, r0 * 0.93 + 0.06, -0.09, 0.09, 2.3, H - 5.5, "cb_black")
    if d <= 1:
        z, k = 3.0, 0
        while z < H - 6:
            s = 1 if k % 2 else -1
            tube(m, (-r0 * 0.66, s * r0 * 0.66, z), (-r0 * 0.66 - 0.02, s * (r0 * 0.66 + 0.19), z), 0.011, "st_pipe", n=4, caps=(False, True))
            z += 0.4 if d == 0 else 0.8
            k += 1
    # renflement sous la couronne (cache les têtes d'antennes), bourgeon
    zc = H - 0.9
    if d <= 3:
        with m.push(t=(0, 0, zc - 1.5)):
            lathe(m, [(r1, 0.0), (0.5, 0.5), (0.56, 1.1), (0.4, 1.7), (0.12, 2.2)], "bark_palm", n=(12, 9, 7, 5)[d])
    # couronne : palmes en arc, dressées au centre, retombantes autour
    fronds = 16 if d <= 1 else (12 if d == 2 else (8 if d == 3 else 6))
    for k in range(fronds):
        az = k * 360.0 / fronds + rnd.uniform(-8, 8)
        up = (0.85, 0.35, -0.25)[k % 3] + rnd.uniform(-0.1, 0.1)       # pente de départ : dressée, étalée, tombante
        L = rnd.uniform(3.6, 4.6)
        w = 1.25
        with m.push(tag="equip", R=rot("z", az), t=(0.25 * math.cos(math.radians(az)), 0.25 * math.sin(math.radians(az)), zc + 0.3)):
            segs = 4 if d <= 1 else (2 if d == 2 else 1)
            pts = []
            for i in range(segs + 1):
                t = i / segs
                x = L * (t - 0.12 * t * t)
                z = L * (up * t - (0.55 + 0.35 * up) * t * t)
                pts.append((x, z))
            for i in range(segs):
                (xa, za), (xb, zb) = pts[i], pts[i + 1]
                ua, ub = i / segs, (i + 1) / segs
                q = [(xa, -w / 2, za - 0.12), (xb, -w / 2, zb - 0.12), (xb, w / 2, zb - 0.12), (xa, w / 2, za - 0.12)]
                if d <= 1:
                    # palme en V : deux demi-cartes relevées de part et d'autre du rachis
                    for sgn, (va, vb) in ((-1, (1.0, 0.5)), (1, (0.5, 0.0))):
                        qq = [(xa, 0, za), (xb, 0, zb), (xb, sgn * w / 2, zb + 0.16), (xa, sgn * w / 2, za + 0.16)]
                        uv = [(ua, 0.5), (ub, 0.5), (ub, 1.0 if sgn < 0 else 0.0), (ua, 1.0 if sgn < 0 else 0.0)]
                        m.face(qq, "palm", uvs=uv)
                        m.face(list(reversed(qq)), "palm", uvs=list(reversed(uv)))
                else:
                    uv = [(ua, 1), (ub, 1), (ub, 0), (ua, 0)]
                    m.face(q, "palm", uvs=uv)
                    m.face(list(reversed(q)), "palm", uvs=list(reversed(uv)))
    r3.add_prism_solid(m, (0, 0, zc - 0.6), (0, 0, zc + 2.2), (1, 0, 0), (0, 1, 0), [(2.6 * math.cos(a * math.pi / 3), 2.6 * math.sin(a * math.pi / 3)) for a in range(6)], "sSv")
    with m.push(t=(0, 0, zc + 0.7)):
        obstruction_light(m, "light_top")
    zA = H - 3.4
    with m.push(sels=("eq1",)):
        for az in (90, 210, 330):
            with m.push(R=rot("z", az), t=(r1 * 0.9 * math.cos(math.radians(az)), r1 * 0.9 * math.sin(math.radians(az)), zA)):
                green_panel(m, h=1.7, arm=0.42)
    with m.push(sels=("eq2",)):
        with m.push(R=rot("z", 160), t=((r1 + 0.08) * math.cos(math.radians(160)), (r1 + 0.08) * math.sin(math.radians(160)), zA - 2.3)):
            dish(m, D=0.3, pipe=0.7, pipe_r=0.024)
    if ruin:
        collapse(m, H * 0.22, H, math.radians(215), None, width=2 * r0, kink=104)
    return m


MODELS = {
    "relay_tree_pine_28": (lambda d, ruin=False: monopine(d, 28.0, ruin, 28), 9000.0),
    "relay_tree_pine_15": (lambda d, ruin=False: monopine(d, 15.0, ruin, 15), 4500.0),
    "relay_tree_palm_22": (monopalm, 7000.0),
}


def main():
    obj_dir = os.environ.get("OBJ_DIR", "")
    tmp = obj_dir or tempfile.mkdtemp(prefix="relay_trees_")
    relay_tex.declare_flat()
    towers._declare_only()
    if os.environ.get("NO_TEX"):
        declare()
        png = {}
    else:
        png = build_textures(os.path.join(tmp, "tex"), DATA, convert=not os.environ.get("NO_PAA"))
    if obj_dir:
        t, T = os.path.join(obj_dir, "tex"), relay_tex.TEX
        png.update({T + "relay_steel_co.paa": {k: os.path.join(t, "relay_steel_%s.png" % k) for k in ("co", "nohq", "smdi", "rm")},
                    T + "relay_equip_co.paa": {k: os.path.join(t, "relay_equip_%s.png" % k) for k in ("co", "nohq", "smdi", "rm")},
                    T + "relay_mesh_ca.paa": {"co": os.path.join(t, "relay_mesh_ca.png"), "alpha": True}})
    report = {}
    props = (("map", "tree"), ("prefershadowvolume", "1"), ("sbsource", "shadowvolume"))
    for name, (fn, mass) in MODELS.items():
        m0 = r3.write_p3d(os.path.join(DATA, name + ".p3d"), lambda d, fn=fn: finish(fn(d)), mass, props, report=report)
        r3.check_mesh(m0, name)
        r3.write_p3d(os.path.join(DATA, name + "_ruin.p3d"), lambda d, fn=fn: finish(fn(d + 1, ruin=True)), mass, props[1:], lods=4, shadow_from=1,
                     res=(1.0, 4.0, 12.0, 30.0), report=report)
        st, sr = report[name + ".p3d"], report[name + "_ruin.p3d"]
        print("%-20s %s  %.2f Mo | ruine %s %.2f Mo | ombre %d / %d, collision %d pièces" % (
            name, " / ".join("%d" % t for _, _, t, _ in st["lods"]), st["size"] / 1e6, " / ".join("%d" % t for _, _, t, _ in sr["lods"]), sr["size"] / 1e6,
            st["s"][1], st["S"][1], st["g"][0]))
        if obj_dir:
            r3.export_obj(m0, os.path.join(obj_dir, name + ".obj"), png)
            r3.export_obj(finish(fn(1, ruin=True)), os.path.join(obj_dir, name + "_ruin.obj"), png)
    towers.write_model_cfg(os.path.join(DATA, "model.cfg"), list(towers.MODELS) + list(MODELS))
    with open(os.path.join(tmp, "report_trees.json"), "w") as f:
        json.dump(report, f, indent=1)


if __name__ == "__main__":
    main()
