"""
Caméras IP du réseau ATAK (fn_camAction, app Vidéo live) : famille complète en pur Python (relay3d.py), un atlas.

  camera_ip / item3d_camera   caméra durcie IPC-2 sur rotule magnétique (objet posé par le joueur et objet d'inventaire) :
                              MÊMES chemins et même repère qu'avant (origine au centre du boîtier, +Y = visée, semelle
                              magnétique 9,2 cm dessous, platine arrière à 4,5 cm : fn_camAction « placeNow »)
  cam_ptz_wall / _pole        dôme PTZ extérieur sous col de cygne (mur) ou sur collier de poteau ; os pan / tilt
  cam_bullet_wall / _ceil / _pole / _corner   caméra tube à visière, couronne de LED infrarouges, boîtier de jonction
  cam_thermal_wall / _pole    caméra thermique bi-spectre (optique germanium + optique visible), pare-soleil
  cam_minidome / _wall        mini-dôme d'intérieur (plafond ou mur)
  cam_fisheye                 caméra 360° de plafond
  cam_tactical                caméra tactique autonome sur trépied (batterie, antenne) : version terrain
  cam_covert                  caméra dissimulée compacte (boîtier discret, sténopé)

Repères de pose : mur = origine au plan du mur, +Y sortant (comme les écrans TOC) ; plafond = origine au plafond, caméra
vers -Z, visée +Y ; poteau = origine sur la surface du poteau (colliers pour un fût de 14 cm) ; angle = origine sur l'arête,
+Y sur la bissectrice ; trépied = origine au sol.
Sélections : camo (atlas : variante sombre par hiddenSelections), led (voyant d'état), ir (LED infrarouges), dome (verre),
pan / tilt (PTZ, model.cfg). Points mémoire : eye, dir (œil du flux et visée), pan_axis, tilt_axis (2 points chacun).
LOD : visuelles 1 / 3 / 7 / 15, ombres, géométrie, tir, vue, mémoire.

    python model_ip_cameras.py     (NO_TEX=1 : sans textures ; OBJ_DIR=d : OBJ + PNG pour render_relay_blender.py)
"""
import json
import math
import os
import sys
import tempfile

import numpy as np
from PIL import Image

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import relay3d as r3  # noqa: E402
import relay_tex as rt  # noqa: E402
from relay3d import Mesh, basis, beam, bolt, box, cable, lathe, plate, rot, solid_box, tube, vadd, vmul  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
ITEMS = os.path.join(HERE, "..", "Sources", "addons", "items", "data")
DATA = os.path.join(ITEMS, "cam")
TEX = "z\\comspec_atak_native\\addons\\items\\data\\cam\\"
UP = (0.0, 0.0, 1.0)
AIM = basis(x=(-1, 0, 0), z=(0, 1, 0))      # repère « objectif » : Z local = visée (+Y), Y local = haut

STRIPS = (("cm_white", 0.5), ("cm_dark", 0.5), ("cm_olive", 0.5), ("cm_metal", 0.5), ("cm_cable", 0.5), ("cm_rubber", 0.5))
RECTS = {
    "cm_bullet": (0, 384, 256, 256), "cm_thermal": (256, 384, 384, 256), "cm_fisheye": (640, 384, 256, 256),
    "cm_lens": (896, 384, 128, 128), "cm_ball": (896, 512, 128, 128),
    "cm_jbox": (0, 640, 192, 192), "cm_label": (192, 640, 192, 96), "cm_vent": (192, 736, 192, 96),
    "cm_ipc_front": (384, 640, 192, 192), "cm_ipc_side": (576, 640, 256, 192), "cm_batt": (832, 640, 192, 192),
    "cm_plate": (0, 832, 192, 192), "cm_covert": (192, 832, 192, 192), "cm_ptz_cap": (384, 832, 192, 192),
    "cm_ptz_ring": (576, 832, 448, 96), "cm_sticker": (576, 928, 448, 96),
}
PAL = {
    "white": dict(body=rt.lin(214, 216, 212), jbox=rt.lin(206, 208, 204)),
    "dark": dict(body=rt.lin(46, 48, 50), jbox=rt.lin(52, 54, 56)),
}


def declare(at):
    for i, (key, tile) in enumerate(STRIPS):
        at.region(key, 0, i * 64, at.w, 64, "strip", tile, pad=3)
    for key, (x, y, w, h) in RECTS.items():
        at.region(key, x, y, w, h, "rect", pad=3)


def cam_atlas(variant="white", declare_mats=True):
    rng = np.random.default_rng(606)
    W = H = 1024
    name = "cam" if variant == "white" else "cam_" + variant
    at = r3.Atlas(name, W, H, TEX, "cam.rvmat", base=(0.2, 0.2, 0.2))
    saved = dict(r3.MATS)
    declare(at)
    if not declare_mats:
        r3.MATS.clear()
        r3.MATS.update(saved)
    P = PAL[variant]
    body = P["body"]
    LINE = (40, 42, 44, 255)
    # --- bandes ---
    for key, tile in STRIPS:
        s = at.sl(key)
        n = rt.noise(64, W, 1.6, rng=rng)
        f = rng.random((64, W))
        if key == "cm_white":
            col, sp, gl, me = body, 0.32, 0.5, 0.0
        elif key == "cm_dark":
            col, sp, gl, me = rt.lin(40, 42, 44), 0.3, 0.45, 0.0
        elif key == "cm_olive":
            col, sp, gl, me = rt.lin(72, 78, 54), 0.15, 0.25, 0.0
        elif key == "cm_metal":
            col, sp, gl, me = rt.lin(150, 154, 156), 0.6, 0.55, 1.0
        elif key == "cm_cable":
            col, sp, gl, me = rt.lin(26, 27, 28), 0.25, 0.4, 0.0
        else:
            col, sp, gl, me = rt.lin(22, 22, 23), 0.1, 0.18, 0.0
        g = 1 + 0.10 * (n - 0.5) + 0.03 * (f - 0.5)
        at.rgb[s] = col[None, None, :] * g[..., None]
        at.height[s] = 0.5 + 0.04 * (rt.noise(64, W, 2.6, rng=rng) - 0.5) + (0.03 * (f - 0.5) if key in ("cm_rubber", "cm_olive") else 0)
        at.spec[s], at.gloss[s], at.metal[s] = sp, gl, me
        if key in ("cm_white", "cm_olive", "cm_dark"):
            # poussière et salissures légères, micro-rayures
            dust = rt.smoothstep(0.55, 0.9, rt.noise(64, W, 2.2, rng=rng)) * 0.22
            at.rgb[s] = rt.mix(at.rgb[s], np.broadcast_to(np.array([0.20, 0.17, 0.13]), (64, W, 3)), dust)
            at.gloss[s] = at.gloss[s] * (1 - dust)
        if key == "cm_metal":
            brush = rt.noise(64, W, 1.2, aniso=(1.0, 0.04), rng=rng)
            at.rgb[s] = at.rgb[s] * (0.85 + 0.3 * brush[..., None])
            at.height[s] = 0.5 + 0.08 * (brush - 0.5)
    for key in RECTS:
        rt.base(at, key, body, rng, var=0.04, grain=0.012, spec=0.32, gloss=0.5, hamp=0.015)
    GLASS = (10, 11, 13, 255)

    def lens(d, cx, cy, r, tint=(18, 24, 44)):
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(16, 16, 18, 255), outline=(60, 62, 64, 255), width=max(2, int(r * 0.08)))
        d.ellipse([cx - r * 0.78, cy - r * 0.78, cx + r * 0.78, cy + r * 0.78], fill=(8, 8, 10, 255), outline=(44, 46, 48, 255), width=2)
        d.ellipse([cx - r * 0.55, cy - r * 0.55, cx + r * 0.55, cy + r * 0.55], fill=tint + (255,))
        d.ellipse([cx - r * 0.30, cy - r * 0.30, cx + r * 0.30, cy + r * 0.30], fill=(4, 4, 8, 255))
        d.ellipse([cx - r * 0.42, cy - r * 0.46, cx - r * 0.18, cy - r * 0.22], fill=(150, 160, 200, 110))

    # --- face de la caméra tube : verre noir, objectif, couronne de 8 LED IR, capteur de lumière ---
    def bullet(d, w, h):
        d.ellipse([2, 2, w - 2, h - 2], fill=(52, 54, 56, 255))
        d.ellipse([10, 10, w - 10, h - 10], fill=GLASS)
        lens(d, w / 2, h / 2, w * 0.19)
        for k in range(8):
            a = k * math.pi / 4 + math.pi / 8
            cx, cy = w / 2 + w * 0.33 * math.cos(a), h / 2 + h * 0.33 * math.sin(a)
            d.ellipse([cx - 13, cy - 13, cx + 13, cy + 13], fill=(26, 12, 14, 255), outline=(60, 40, 44, 255), width=2)
            d.ellipse([cx - 5, cy - 5, cx + 5, cy + 5], fill=(90, 26, 30, 255))
        d.ellipse([w / 2 - 6, h * 0.86 - 6, w / 2 + 6, h * 0.86 + 6], fill=(40, 44, 52, 255))
    rt.stamp(at, "cm_bullet", bullet, spec=0.9, gloss=0.9)

    # --- face de la bi-spectre : optique thermique (germanium, reflet doré), optique visible, deux projecteurs IR ---
    def thermal(d, w, h):
        d.rounded_rectangle([3, 3, w - 3, h - 3], 22, fill=(50, 52, 54, 255))
        d.rounded_rectangle([14, 14, w - 14, h - 14], 16, fill=GLASS)
        lens(d, w * 0.30, h * 0.50, h * 0.33, tint=(120, 84, 30))
        lens(d, w * 0.70, h * 0.42, h * 0.22)
        for cx in (w * 0.60, w * 0.80):
            d.rounded_rectangle([cx - 20, h * 0.76 - 12, cx + 20, h * 0.76 + 12], 5, fill=(30, 12, 14, 255), outline=(66, 44, 46, 255), width=2)
            for k in (-1, 0, 1):
                d.ellipse([cx + k * 12 - 4, h * 0.76 - 4, cx + k * 12 + 4, h * 0.76 + 4], fill=(96, 28, 30, 255))
    rt.stamp(at, "cm_thermal", thermal, spec=0.9, gloss=0.9)

    # --- fisheye : collerette, bulle centrale, micros, LED ---
    def fisheye(d, w, h):
        d.ellipse([2, 2, w - 2, h - 2], outline=(150, 152, 150, 255), width=3)
        d.ellipse([w * 0.26, h * 0.26, w * 0.74, h * 0.74], fill=(30, 31, 33, 255), outline=(90, 92, 94, 255), width=3)
        lens(d, w / 2, h / 2, w * 0.16)
        for k in range(6):
            a = k * math.pi / 3
            cx, cy = w / 2 + w * 0.36 * math.cos(a), h / 2 + h * 0.36 * math.sin(a)
            d.ellipse([cx - 7, cy - 7, cx + 7, cy + 7], fill=(34, 14, 16, 255))
        for k in range(3):
            d.ellipse([w * 0.5 - 14 + k * 12, h * 0.90, w * 0.5 - 8 + k * 12, h * 0.90 + 6], fill=(60, 62, 64, 255))
    rt.stamp(at, "cm_fisheye", fisheye)

    def lens_only(d, w, h):
        d.rectangle([0, 0, w, h], fill=GLASS)
        lens(d, w / 2, h / 2, w * 0.44)
    rt.stamp(at, "cm_lens", lens_only, spec=0.9, gloss=0.9)

    # --- boule PTZ : fenêtre plate de l'objectif sur fond noir brillant (UV polaires) ---
    def ball(d, w, h):
        d.rectangle([0, 0, w, h], fill=(12, 12, 14, 255))
        d.ellipse([w * 0.14, h * 0.14, w * 0.86, h * 0.86], fill=(6, 6, 8, 255), outline=(52, 54, 58, 255), width=3)
        lens(d, w * 0.5, h * 0.45, w * 0.22)
        for cx in (w * 0.3, w * 0.7):
            d.ellipse([cx - 7, h * 0.72 - 7, cx + 7, h * 0.72 + 7], fill=(70, 22, 24, 255))
    rt.stamp(at, "cm_ball", ball, spec=0.95, gloss=0.95)

    # --- boîtier de jonction : couvercle à 4 vis, joint, presse-étoupe ---
    def jbox(d, w, h):
        d.rounded_rectangle([4, 4, w - 4, h - 4], 14, outline=LINE, width=3)
        d.rounded_rectangle([14, 14, w - 14, h - 14], 10, outline=(150, 152, 150, 200), width=2)
        for cx, cy in ((26, 26), (w - 26, 26), (26, h - 26), (w - 26, h - 26)):
            d.ellipse([cx - 9, cy - 9, cx + 9, cy + 9], fill=(150, 154, 156, 255), outline=LINE, width=2)
            d.line([cx - 5, cy, cx + 5, cy], fill=LINE, width=2)
            d.line([cx, cy - 5, cx, cy + 5], fill=LINE, width=2)
    rt.stamp(at, "cm_jbox", jbox, height=0.6, shadow=0.3)

    def label(d, w, h):
        d.rectangle([6, 6, w - 6, h - 6], fill=(232, 232, 226, 255), outline=LINE)
        d.text((12, 10), "CS-CAM  IP67", fill=(20, 20, 20, 255), font=r3.font(15))
        d.text((12, 32), "PoE 802.3at  12 V DC", fill=(50, 50, 50, 255), font=r3.font(10, False))
        d.text((12, 48), "S/N CS%07d" % 4117062, fill=(50, 50, 50, 255), font=r3.font(10, False))
        for x in range(12, w - 14, 5):
            if (x * 7) % 3:
                d.line([x, 66, x, h - 12], fill=(20, 20, 20, 255), width=2)
    rt.stamp(at, "cm_label", label)

    def vent(d, w, h):
        rt.louvres(d, 14, 12, w - 14, h - 10, 9, (30, 32, 34, 255), (190, 192, 188, 255))
    rt.stamp(at, "cm_vent", vent, height=0.4, shadow=0.3)

    # --- IPC-2 (caméra posée par le joueur) : face et flancs nervurés, vert olive ---
    for key in ("cm_ipc_front", "cm_ipc_side", "cm_batt", "cm_covert"):
        rt.base(at, key, rt.lin(72, 78, 54) if key != "cm_covert" else rt.lin(58, 56, 52), rng, var=0.07, grain=0.03, spec=0.18, gloss=0.28)

    def ipc_front(d, w, h):
        d.rounded_rectangle([4, 4, w - 4, h - 4], 16, outline=(34, 38, 26, 255), width=4)
        d.rounded_rectangle([18, 18, w - 18, h - 18], 12, fill=GLASS)
        lens(d, w * 0.5, h * 0.5, h * 0.27)
        for cx, cy in ((w * 0.2, h * 0.3), (w * 0.8, h * 0.3), (w * 0.2, h * 0.7), (w * 0.8, h * 0.7)):
            d.ellipse([cx - 11, cy - 11, cx + 11, cy + 11], fill=(30, 12, 14, 255), outline=(64, 42, 44, 255), width=2)
            d.ellipse([cx - 4, cy - 4, cx + 4, cy + 4], fill=(96, 28, 30, 255))
    rt.stamp(at, "cm_ipc_front", ipc_front, spec=0.85, gloss=0.85)

    def ipc_side(d, w, h):
        for x in range(26, w - 80, 16):
            d.rounded_rectangle([x, 26, x + 7, h - 26], 3, fill=(40, 44, 30, 255))
        d.rectangle([w - 70, h * 0.3, w - 14, h * 0.7], fill=(210, 204, 150, 255), outline=(30, 30, 24, 255))
        d.text((w - 66, h * 0.34), "IPC-2", fill=(20, 20, 16, 255), font=r3.font(15))
        d.text((w - 66, h * 0.52), "CAM IP", fill=(40, 40, 30, 255), font=r3.font(11, False))
    rt.stamp(at, "cm_ipc_side", ipc_side, height=0.4)

    def batt(d, w, h):
        d.rounded_rectangle([5, 5, w - 5, h - 5], 12, outline=(34, 38, 26, 255), width=4)
        d.rectangle([w * 0.14, h * 0.18, w * 0.86, h * 0.50], fill=(210, 204, 150, 255), outline=(30, 30, 24, 255))
        d.text((w * 0.18, h * 0.21), "BATT 12 V", fill=(20, 20, 16, 255), font=r3.font(17))
        d.text((w * 0.18, h * 0.36), "LiFePO4  20 Ah", fill=(40, 40, 30, 255), font=r3.font(11, False))
        for k in range(4):
            d.rectangle([w * 0.18 + k * w * 0.16, h * 0.62, w * 0.18 + k * w * 0.16 + w * 0.11, h * 0.72], fill=(60, 150, 70, 255) if k < 3 else (50, 56, 44, 255), outline=(20, 22, 16, 255))
        d.ellipse([w * 0.70, h * 0.78, w * 0.84, h * 0.78 + w * 0.14], fill=(24, 24, 26, 255), outline=(120, 122, 110, 255), width=2)
    rt.stamp(at, "cm_batt", batt, height=0.42)

    def covert(d, w, h):
        d.rounded_rectangle([4, 4, w - 4, h - 4], 10, outline=(30, 30, 28, 255), width=3)
        d.ellipse([w * 0.5 - 9, h * 0.34 - 9, w * 0.5 + 9, h * 0.34 + 9], fill=(8, 8, 10, 255), outline=(44, 44, 42, 255), width=2)
        for x in range(int(w * 0.25), int(w * 0.75), 9):
            d.line([x, h * 0.74, x, h * 0.86], fill=(36, 36, 34, 255), width=3)
    rt.stamp(at, "cm_covert", covert, height=0.45)

    # --- platine murale (4 chevilles), calotte et bague du PTZ, autocollant ---
    def plate_(d, w, h):
        d.rounded_rectangle([4, 4, w - 4, h - 4], 10, outline=LINE, width=3)
        for cx, cy in ((24, 24), (w - 24, 24), (24, h - 24), (w - 24, h - 24)):
            d.ellipse([cx - 11, cy - 11, cx + 11, cy + 11], fill=(120, 124, 126, 255), outline=LINE, width=2)
            hexa = [(cx + 7 * math.cos(k * math.pi / 3), cy + 7 * math.sin(k * math.pi / 3)) for k in range(6)]
            d.polygon(hexa, fill=(170, 174, 176, 255), outline=LINE)
    rt.stamp(at, "cm_plate", plate_, height=0.62, shadow=0.35)

    def cap(d, w, h):
        d.ellipse([3, 3, w - 3, h - 3], outline=(150, 152, 150, 255), width=3)
        d.ellipse([w * 0.3, h * 0.3, w * 0.7, h * 0.7], outline=(160, 162, 160, 255), width=2)
        for k in range(3):
            a = k * 2 * math.pi / 3 + 0.5
            cx, cy = w / 2 + w * 0.40 * math.cos(a), h / 2 + h * 0.40 * math.sin(a)
            d.ellipse([cx - 6, cy - 6, cx + 6, cy + 6], fill=(130, 134, 136, 255), outline=LINE)
    rt.stamp(at, "cm_ptz_cap", cap)

    def ring(d, w, h):
        d.rectangle([0, h * 0.62, w, h * 0.70], fill=(36, 38, 40, 255))
        d.rectangle([0, 0, w, 6], fill=(150, 152, 150, 255))
        for x in range(20, w, 56):
            d.rounded_rectangle([x, h * 0.2, x + 30, h * 0.34], 3, fill=(40, 42, 44, 255))
    rt.stamp(at, "cm_ptz_ring", ring, height=0.42)

    def sticker(d, w, h):
        d.rectangle([w * 0.04, h * 0.2, w * 0.40, h * 0.8], fill=(244, 196, 22, 255), outline=(18, 18, 18, 255), width=2)
        d.text((w * 0.06, h * 0.30), "ZONE SOUS", fill=(18, 18, 18, 255), font=r3.font(15))
        d.text((w * 0.06, h * 0.52), "VIDÉOSURVEILLANCE", fill=(18, 18, 18, 255), font=r3.font(12))
        d.rectangle([w * 0.5, h * 0.25, w * 0.92, h * 0.75], fill=(232, 232, 226, 255), outline=LINE)
        d.text((w * 0.52, h * 0.34), "CS-PTZ 25x  IP66  IK10", fill=(30, 30, 30, 255), font=r3.font(11, False))
        d.text((w * 0.52, h * 0.52), "24 V AC / PoE++   MAC 02:C5:41:17:06:2A", fill=(60, 60, 60, 255), font=r3.font(8, False))
    rt.stamp(at, "cm_sticker", sticker)
    for key in ("cm_jbox", "cm_plate", "cm_ptz_cap", "cm_ptz_ring", "cm_sticker", "cm_label", "cm_vent"):
        rt.weather(at, key, rng, dirt=0.35, streaks=0.6, edge=0.5, rust=0.08 if key == "cm_plate" else 0.0, grime_col=(0.16, 0.14, 0.11))
    for key in ("cm_ipc_side", "cm_batt", "cm_covert"):
        rt.weather(at, key, rng, dirt=0.5, streaks=0.2, edge=0.7, grime_col=(0.20, 0.16, 0.11))
    return at


def flat_mats():
    r3.flat_mat("cm_glass", (0.012, 0.014, 0.018), TEX + "cam_glass.rvmat")
    r3.flat_mat("cm_led", (0.1, 0.9, 0.2), TEX + "cam_glow.rvmat")
    # LED infrarouges : sombres de jour ; IR_ON=1 : allumées (rendus de nuit seulement)
    r3.flat_mat("cm_ir", (0.9, 0.04, 0.03) if os.environ.get("IR_ON") else (0.035, 0.004, 0.004), TEX + "cam_glow.rvmat")


def build_textures(tmp, convert=True):
    os.makedirs(DATA, exist_ok=True)
    out = {}
    at = cam_atlas()
    p = at.save(tmp, suffixes=("co", "nohq", "smdi", "as"), nstrength=4.0)
    out[at.tex] = p
    dk = cam_atlas("dark", declare_mats=False)
    pd = dk.save(tmp, suffixes=("co",))
    out[TEX + "cam_dark_co.paa"] = dict(p, co=pd["co"])
    if convert:
        def small(src, size):
            dst = src.replace(".png", "_s.png")
            Image.open(src).resize(size, Image.LANCZOS).save(dst)
            return dst
        r3.to_paa(p["co"], os.path.join(DATA, "cam_co.paa"))
        r3.to_paa(pd["co"], os.path.join(DATA, "cam_dark_co.paa"))
        r3.to_paa(small(p["nohq"], (512, 512)), os.path.join(DATA, "cam_nohq.paa"))
        r3.to_paa(small(p["smdi"], (512, 512)), os.path.join(DATA, "cam_smdi.paa"))
        r3.to_paa(small(p["as"], (256, 256)), os.path.join(DATA, "cam_as.paa"))
    r3.write_rvmat_super(os.path.join(DATA, "cam.rvmat"), "Caméras IP (tools/model_ip_cameras.py) : atlas des boîtiers.",
                         TEX + "cam_nohq.paa", TEX + "cam_smdi.paa", spec=0.35, power=40, fresnel="1.5,0.5", as_=TEX + "cam_as.paa")
    r3.write_rvmat_basic(os.path.join(DATA, "cam_glow.rvmat"), "Caméras IP : voyant d'état et LED infrarouges (lumineux, couleur = texture).", emissive=(5, 5, 5))
    r3.write_rvmat_super(os.path.join(DATA, "cam_glass.rvmat"), "Caméras IP : verre de dôme et d'objectif, noir brillant.",
                         "#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)", "#(argb,8,8,3)color(1,0.85,0.95,1,SMDI)", spec=0.95, power=140, fresnel="1.5,0.95")
    r3.write_rvmat_basic(os.path.join(DATA, "cam_dead.rvmat"), "Caméras IP : verre brisé / noirci (caméra détruite), mat.", spec=0.02, power=10)
    return out


# --------------------------------------------------------------------------------------------------------------------
# Pièces
# --------------------------------------------------------------------------------------------------------------------
def nn(m, *vals):
    return vals[min(m.d, len(vals) - 1)]


def disc(m, r, z, mat, n, up=True, polar=True):
    """Disque plein à la cote z (repère courant), face vers +Z (up) ou -Z."""
    lathe(m, [(r, z), (0.0, z)] if up else [(0.0, z), (r, z)], mat, n=n, polar=polar)


def sphere(m, r, mat, n, rings, z0=-1.0, z1=1.0, polar=False):
    """Calotte de sphère de centre l'origine entre les cotes z0*r et z1*r (profil montant)."""
    a0, a1 = math.asin(max(-1, min(1, z0))), math.asin(max(-1, min(1, z1)))
    prof = [(max(0.0, r * math.cos(a0 + (a1 - a0) * k / rings)), r * math.sin(a0 + (a1 - a0) * k / rings)) for k in range(rings + 1)]
    lathe(m, prof, mat, n=n, polar=polar)


def led(m, p, r=0.004, axis=(0, 1, 0)):
    """Voyant d'état : petite pastille lumineuse (sélection « led »)."""
    if m.d > 1:
        return
    with m.push(sels=("led",)):
        tube(m, p, vadd(p, vmul(axis, 0.0015)), r * 0.8, "cm_led", n=8, caps=(False, True))


def screws(m, pts, axis, r=0.004):
    if m.d == 0:
        for p in pts:
            bolt(m, p, axis, r, 0.003, "cm_metal", n=6)


def gland_cable(m, p, pts, r=0.0045):
    """Presse-étoupe en p et câble PoE le long des points pts."""
    if m.d <= 1:
        tube(m, p, pts[0], 0.009, "cm_rubber", n=nn(m, 8, 5), caps=(False, False))
    if m.d <= 2:
        cable(m, [p] + list(pts), r if m.d < 2 else r * 1.5, "cm_cable", n=nn(m, 6, 4, 3))


def junction_box(m, r=0.058, t=0.038, mat="cm_white"):
    """Boîtier de jonction rond contre la paroi (plan z local = 0, vers +Z) : embase, couvercle à vis."""
    n = nn(m, 20, 12, 8, 6)
    lathe(m, [(r, 0.0), (r, t * 0.8), (r * 0.94, t)], mat, n=n)
    lathe(m, [(r * 0.94, t), (0.0, t)], "cm_jbox" if m.d <= 2 else mat, n=n, polar=True)
    if m.d == 0:
        for k in range(4):
            a = math.pi / 4 + k * math.pi / 2
            bolt(m, (r * 0.72 * math.cos(a), r * 0.72 * math.sin(a), t), UP, 0.005, 0.003, "cm_metal")


def wall_plate(m, w=0.12, h=0.16, t=0.006, mat="cm_plate"):
    """Platine murale rectangulaire (plan y = 0, vers +Y), quatre chevilles."""
    box(m, -w / 2, w / 2, 0.0, t, -h / 2, h / 2, {"+y": mat, "*": "cm_white"}, skip="-y")
    screws(m, [(sx * (w / 2 - 0.016), t, sz * (h / 2 - 0.016)) for sx in (-1, 1) for sz in (-1, 1)], (0, 1, 0), 0.006)


def pole_clamp(m, w=0.10, h=0.16, pole_r=0.07):
    """Fixation sur poteau : platine cintrée, deux colliers inox à vis sans fin autour d'un fût de 14 cm.
    Origine sur la surface du poteau (axe du poteau en y = -pole_r), +Y sortant."""
    box(m, -w / 2, w / 2, 0.0, 0.008, -h / 2, h / 2, {"+y": "cm_plate", "*": "cm_metal"})
    if m.d <= 2:
        n = nn(m, 16, 10, 6)
        for z in (-h * 0.3, h * 0.3):
            with m.push(t=(0, -pole_r, z)):
                lathe(m, [(pole_r + 0.002, -0.008), (pole_r + 0.002, 0.008)], "cm_metal", n=n)
                if m.d == 0:
                    lathe(m, [(pole_r + 0.0005, 0.008), (pole_r + 0.0005, -0.008)], "cm_metal", n=n)
                    box(m, pole_r * 0.5, pole_r * 0.5 + 0.024, pole_r * 0.86, pole_r * 0.86 + 0.014, -0.008, 0.008, "cm_metal")


def corner_bracket(m, w=0.10, h=0.18):
    """Équerre d'angle sortant : deux ailes plaquées sur les deux murs, face avant à 45° portant la caméra.
    Origine sur l'arête du bâtiment, +Y sur la bissectrice ; renvoie la cote y de la face avant."""
    s = 0.10
    yf = 0.035
    for sx in (-1, 1):
        # aile plaquée sur le mur qui fuit vers (sx, -1)
        a, b = (sx * 0.004, 0.006, 0.0), (sx * (s * 0.7071), 0.006 - s * 0.7071, 0.0)
        nrm = (sx * 0.7071, 0.7071, 0.0)
        plate(m, [vadd(a, (0, 0, -h / 2)), vadd(b, (0, 0, -h / 2)), vadd(b, (0, 0, h / 2)), vadd(a, (0, 0, h / 2))], 0.005, "cm_metal", nrm)
        if m.d == 0:
            for z in (-h * 0.35, h * 0.35):
                p = vadd(vadd(a, vmul(vadd(b, vmul(a, -1)), 0.7)), (0, 0, z))
                bolt(m, vadd(p, vmul(nrm, 0.005)), nrm, 0.006, 0.004, "cm_metal")
    box(m, -w / 2, w / 2, 0.012, yf, -h / 2, h / 2, {"+y": "cm_plate", "*": "cm_metal"})
    return yf


def bullet_head(m, L=0.21, r=0.041, shield=True, mat="cm_white"):
    """Corps de caméra tube dans le repère objectif (Z local = visée, Y local = haut), origine au milieu du corps."""
    n = nn(m, 20, 12, 8, 6)
    z0, z1 = -L / 2, L / 2
    lathe(m, [(r * 0.7, z0), (r, z0 + 0.012), (r, z1 - 0.014), (r * 1.06, z1 - 0.012), (r * 1.06, z1)] if m.d <= 1 else [(r, z0), (r, z1)], mat, n=n)
    disc(m, r * 1.06 if m.d <= 1 else r, z1, "cm_bullet", n)
    disc(m, r * 0.7 if m.d <= 1 else r, z0, mat, n, up=False, polar=False)
    if m.d <= 1:
        lathe(m, [(r + 0.0015, z0 + 0.03), (r + 0.0015, z0 + 0.036)], "cm_dark", n=n)
        m.face([(r + 0.0006, -0.012, -0.03), (r + 0.0006, -0.012, 0.05), (r + 0.0006, 0.012, 0.05), (r + 0.0006, 0.012, -0.03)], "cm_label", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(1, 0, 0))
    if m.d <= 1:
        with m.push(sels=("ir",)):
            for k in range(8):
                a = k * math.pi / 4 + math.pi / 8
                with m.push(t=(r * 0.70 * math.cos(a), r * 0.70 * math.sin(a), z1)):
                    disc(m, 0.0032, 0.0006, "cm_ir", 8, polar=False)
    if shield and m.d <= 2:
        # visière : demi-coquille au-dessus, débordant de 4 cm à l'avant
        rs = r * 1.16
        lathe(m, [(rs, z0 + 0.03), (rs, z1 + 0.04)], mat, n=n // 2, arc=0.5)
        lathe(m, [(rs - 0.002, z1 + 0.04), (rs - 0.002, z0 + 0.03)], "cm_dark", n=n // 2, arc=0.5)
    solid_box(m, -r, r, -r, r, z0, z1, "sSgfv")


def knuckle_arm(m, base, head, r=0.011, mat="cm_white"):
    """Bras articulé : rotule à la base, bras, rotule sous le corps."""
    n = nn(m, 10, 6, 4, 3)
    if m.d <= 2:
        tube(m, base, head, r, mat, n=n, caps=(False, False), S="s")
    if m.d <= 1:
        for p in (base, head):
            with m.push(t=p):
                sphere(m, r * 1.7, mat, n, nn(m, 6, 4))
        if m.d == 0:
            with m.push(t=head):
                bolt(m, (r * 1.7, 0, 0), (1, 0, 0), 0.005, 0.004, "cm_metal")


def bullet_assembly(m, mount="wall", tilt=12.0):
    """Caméra tube complète. mount : wall (origine au plan du mur), ceil (origine au plafond), pole, corner."""
    y0 = 0.0
    if mount == "pole":
        pole_clamp(m)
        y0 = 0.008
    elif mount == "corner":
        y0 = corner_bracket(m)
    if mount == "ceil":
        # boîtier au plafond, corps suspendu dessous
        with m.push(R=rot("x", 180)):
            junction_box(m)
        base, c = (0.0, 0.0, -0.045), (0.0, 0.055, -0.115)
        gp, gpts = (0.0, -0.02, -0.03), [(0.0, -0.035, -0.07), (0.0, -0.045, -0.105)]
    else:
        with m.push(R=basis(x=(-1, 0, 0), z=(0, 1, 0)), t=(0, y0, 0)):
            junction_box(m)
        base, c = (0.0, y0 + 0.045, 0.0), (0.0, y0 + 0.165, 0.035)
        gp, gpts = (0.0, y0 + 0.03, -0.045), [(0.0, y0 + 0.045, -0.075), (0.0, y0 + 0.07, -0.05), (0.0, y0 + 0.075, 0.0)]
    under = vadd(c, (0.0, -0.05, -0.045) if mount != "ceil" else (0.0, -0.045, 0.045))
    knuckle_arm(m, base, under)
    with m.push(R=r3.mat_mul(rot("x", -tilt), AIM), t=c):
        bullet_head(m)
        if m.d <= 1:
            with m.push(R=basis(x=(1, 0, 0), z=(0, 1, 0))):
                pass
    led(m, vadd(c, (0.0, 0.085, -0.052)), axis=(0, 0, -1))
    gland_cable(m, gp, gpts)
    eye = vadd(c, (0.0, 0.125 * math.cos(math.radians(tilt)), -0.125 * math.sin(math.radians(tilt))))
    m.memory("eye", eye)
    m.memory("dir", vadd(eye, (0.0, 0.1 * math.cos(math.radians(tilt)), -0.1 * math.sin(math.radians(tilt)))))
    return eye


def cam_bullet(d, mount="wall"):
    m = Mesh(d, seed=71)
    bullet_assembly(m, mount)
    return m


def thermal_head(m):
    """Bi-spectre dans le repère objectif : caisson, face à deux optiques, pare-soleil, ailettes arrière."""
    w, h, L = 0.15, 0.10, 0.27
    z0, z1 = -L / 2, L / 2
    if m.d <= 1:
        box(m, -w / 2, w / 2, -h / 2, h / 2, z0, z1, {"+z": "cm_thermal", "-z": "cm_vent", "-y": "cm_white", "*": "cm_white"}, S="sSgfv", bevel=0.0)
    else:
        box(m, -w / 2, w / 2, -h / 2, h / 2, z0, z1, {"+z": "cm_thermal", "*": "cm_white"}, S="sSgfv")
    if m.d <= 2:
        # pare-soleil : tôle débordante au-dessus, relevée de 6 mm
        box(m, -w / 2 - 0.004, w / 2 + 0.004, h / 2 + 0.005, h / 2 + 0.008, z0 + 0.03, z1 + 0.03, "cm_white")
    if m.d <= 1:
        for sx in (-1, 1):
            box(m, sx * (w / 2 + 0.004) - 0.0015, sx * (w / 2 + 0.004) + 0.0015, h * 0.22, h / 2 + 0.008, z0 + 0.03, z1 + 0.03, "cm_white")
        # optique thermique en saillie (barillet), étiquette au flanc
        with m.push(t=(-w * 0.20, 0.0, z1)):
            lathe(m, [(0.036, 0.0), (0.036, 0.018), (0.031, 0.022)], "cm_dark", n=nn(m, 16, 10))
            disc(m, 0.031, 0.022, "cm_glass", nn(m, 16, 10), polar=False)
        m.face([(w / 2 + 0.0005, -0.03, -0.04), (w / 2 + 0.0005, -0.03, 0.06), (w / 2 + 0.0005, 0.02, 0.06), (w / 2 + 0.0005, 0.02, -0.04)], "cm_label",
               uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(1, 0, 0))
        with m.push(sels=("ir",)):
            for x in (w * 0.10, w * 0.30):
                box(m, x - 0.012, x + 0.012, -h * 0.30, -h * 0.22, z1, z1 + 0.0008, "cm_ir", skip="-z")


def cam_thermal(d, mount="wall"):
    m = Mesh(d, seed=72)
    y0 = 0.0
    if mount == "pole":
        pole_clamp(m, w=0.12, h=0.2)
        y0 = 0.008
    else:
        with m.push(t=(0, 0, 0)):
            wall_plate(m, 0.13, 0.19)
        y0 = 0.006
    # console : bras en caisson incliné, tête de réglage
    if m.d <= 2:
        beam(m, (0, y0, -0.03), (0, y0 + 0.17, 0.02), 0.045, 0.035, "cm_white", a=(1, 0, 0), S="s")
        beam(m, (0, y0, -0.085), (0, y0 + 0.12, 0.0), 0.012, 0.02, "cm_white", a=(1, 0, 0), caps=False)
    c = (0.0, y0 + 0.22, 0.085)
    if m.d <= 1:
        with m.push(t=(0, y0 + 0.17, 0.028)):
            lathe(m, [(0.028, 0.0), (0.028, 0.012)], "cm_metal", n=nn(m, 12, 8), closed=(True, True))
    tilt = 10.0
    with m.push(R=r3.mat_mul(rot("x", -tilt), AIM), t=c):
        thermal_head(m)
    led(m, vadd(c, (0.06, 0.13, -0.055)), axis=(0, 1, 0))
    gland_cable(m, (0.0, y0 + 0.11, 0.005), [(0.02, y0 + 0.09, -0.03), (0.03, y0 + 0.03, -0.06), (0.03, y0 + 0.004, -0.115)])
    eye = vadd(c, (0.0, 0.16, -0.03))
    m.memory("eye", eye)
    m.memory("dir", vadd(eye, (0.0, 0.0985, -0.0174)))
    return m


def cam_ptz(d, mount="wall"):
    """Dôme PTZ extérieur : col de cygne mural (ou collier de poteau), carter pare-soleil fixe, couronne tournante
    (os pan) et boule optique (os tilt)."""
    m = Mesh(d, seed=73)
    n = nn(m, 24, 14, 8, 6)
    y0 = 0.006
    if mount == "pole":
        pole_clamp(m, w=0.13, h=0.22)
        y0 = 0.008
    else:
        wall_plate(m, 0.13, 0.22)
    # col de cygne : tube coudé partant de la platine, descendant sur la calotte
    ax = 0.36
    pts = [(0, y0, 0.03), (0, y0 + 0.10, 0.06), (0, y0 + 0.24, 0.07), (0, ax - 0.03 + y0, 0.045), (0, ax + y0, -0.01), (0, ax + y0, -0.06)]
    if m.d <= 2:
        cable(m, pts, 0.024, "cm_white", n=nn(m, 12, 8, 5), S="s")
        with m.push(R=AIM, t=(0, y0, 0.03)):
            lathe(m, [(0.045, 0.0), (0.045, 0.012), (0.026, 0.03)], "cm_white", n=nn(m, 16, 10, 6))
    else:
        beam(m, (0, y0, 0.05), (0, ax + y0, 0.05), 0.04, 0.04, "cm_white", a=(1, 0, 0))
    if m.d <= 1:
        beam(m, (0, y0, -0.09), (0, y0 + 0.2, 0.05), 0.008, 0.03, "cm_white", a=(1, 0, 0), caps=False)
    zc = -0.215         # centre de la boule
    with m.push(t=(0, ax + y0, 0)):
        # carter fixe : calotte bombée, jupe pare-soleil
        lathe(m, [(0.105, -0.155), (0.110, -0.15), (0.110, -0.085), (0.098, -0.062), (0.06, -0.05), (0.03, -0.05)] if m.d <= 1 else [(0.11, -0.155), (0.11, -0.07), (0.03, -0.05)], "cm_white", n=n)
        if m.d <= 1:
            lathe(m, [(0.03, -0.05), (0.03, -0.06)] if False else [(0.105, -0.155), (0.086, -0.155)], "cm_dark", n=n)
            m.face([(-0.045, -0.1105, -0.14), (0.045, -0.1105, -0.14), (0.045, -0.1105, -0.095), (-0.045, -0.1105, -0.095)] if False else
                   [(0.1105, -0.045, -0.14), (0.1105, 0.045, -0.14), (0.1105, 0.045, -0.095), (0.1105, -0.045, -0.095)], "cm_sticker", uvs=[(0.5, 1), (1, 1), (1, 0), (0.5, 0)], out=(1, 0, 0))
        solid_box(m, -0.10, 0.10, -0.10, 0.10, -0.29, -0.05, "sSgfv")
        with m.push(sels=("pan",)):
            # couronne tournante et fourche
            lathe(m, [(0.082, -0.20), (0.088, -0.185), (0.088, -0.155)] if m.d <= 2 else [(0.085, -0.2), (0.085, -0.155)], "cm_ptz_ring" if m.d <= 1 else "cm_dark", n=n)
            with m.push(sels=("tilt",), t=(0, 0, zc)):
                # boule : hémisphère avant portant la fenêtre de l'objectif (visée +Y, inclinée de 15° vers le bas au repos)
                with m.push(R=r3.mat_mul(rot("x", -15), AIM)):
                    sphere(m, 0.078, "cm_ball", n, nn(m, 8, 5, 3, 2), z0=0.25, z1=1.0, polar=True)
                    with m.push(sels=("dome",)):
                        sphere(m, 0.078, "cm_glass", n, nn(m, 8, 5, 3, 2), z0=-1.0, z1=0.25)
                    if m.d <= 1:
                        with m.push(sels=("ir",)):
                            for sx in (-1, 1):
                                with m.push(t=(sx * 0.03, -0.035, 0.064)):
                                    disc(m, 0.004, 0.0, "cm_ir", 8, polar=False)
            led(m, (0.0, 0.0885, -0.17), axis=(0, 1, 0))
        m.memory("pan_axis", (0, 0, zc))
        m.memory("pan_axis", (0, 0, zc + 0.1))
        m.memory("tilt_axis", (-0.05, 0, zc))
        m.memory("tilt_axis", (0.05, 0, zc))
        m.memory("eye", (0, 0.0, zc - 0.085))
        m.memory("dir", (0, 0.1, zc - 0.085))
    gland_cable(m, (0.035, y0 + 0.004, -0.085), [(0.035, y0 + 0.014, -0.10), (0.035, y0 + 0.014, -0.16), (0.035, y0 + 0.002, -0.2)])
    return m


def minidome_body(m):
    """Mini-dôme dans un repère où la paroi est le plan z = 0 et la caméra regarde vers +Z."""
    n = nn(m, 24, 14, 8, 6)
    lathe(m, [(0.062, 0.0), (0.062, 0.022), (0.056, 0.034), (0.046, 0.038)] if m.d <= 1 else [(0.062, 0.0), (0.06, 0.03), (0.046, 0.038)], "cm_white", n=n)
    with m.push(sels=("dome",), t=(0, 0, 0.034)):
        sphere(m, 0.046, "cm_glass", n, nn(m, 6, 4, 3, 2), z0=0.08, z1=1.0)
    if m.d <= 1:
        m.face([(-0.022, -0.0625, 0.004), (0.022, -0.0625, 0.004), (0.022, -0.0625, 0.02), (-0.022, -0.0625, 0.02)], "cm_label", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(0, -1, 0))
    solid_box(m, -0.06, 0.06, -0.06, 0.06, 0.0, 0.078, "sSgfv")


def cam_minidome(d, wall=False):
    m = Mesh(d, seed=74)
    if wall:
        with m.push(R=basis(x=(-1, 0, 0), z=(0, 1, 0))):
            minidome_body(m)
        led(m, (0.0, 0.0345, -0.052), axis=(0, 1, 0))
        m.memory("eye", (0, 0.10, 0.0))
        m.memory("dir", (0, 0.20, 0.0))
        gland_cable(m, (0.05, 0.012, -0.045), [(0.06, 0.012, -0.08), (0.06, 0.004, -0.12)])
    else:
        with m.push(R=rot("x", 180)):
            minidome_body(m)
        led(m, (0.0, 0.052, -0.0345), axis=(0, 0, -1))
        m.memory("eye", (0, 0.03, -0.10))
        m.memory("dir", (0, 0.117, -0.15))
    return m


def cam_fisheye(d):
    m = Mesh(d, seed=75)
    n = nn(m, 28, 16, 8, 6)
    with m.push(R=rot("x", 180)):
        lathe(m, [(0.075, 0.0), (0.075, 0.03), (0.068, 0.043)] if m.d <= 1 else [(0.075, 0.0), (0.068, 0.043)], "cm_white", n=n)
        disc(m, 0.068, 0.043, "cm_fisheye", n)
        with m.push(sels=("dome",), t=(0, 0, 0.043)):
            sphere(m, 0.022, "cm_glass", nn(m, 14, 10, 6, 4), nn(m, 4, 3, 2, 2), z0=0.2, z1=1.0)
        solid_box(m, -0.07, 0.07, -0.07, 0.07, 0.0, 0.05, "sSgfv")
    led(m, (0.0, 0.058, -0.0435), axis=(0, 0, -1))
    m.memory("eye", (0, 0.0, -0.09))
    m.memory("dir", (0, 0.05, -0.18))
    return m


def ipc2(m, stand=True):
    """IPC-2 : boîtier durci vert, objectif en barillet, 4 LED IR, antenne, platine magnétique arrière, rotule et
    semelle magnétique dessous. Origine au centre du boîtier, +Y = visée."""
    w, L, h = 0.076, 0.094, 0.060
    box(m, -w / 2, w / 2, -L / 2 + 0.006, L / 2 - 0.004, -h / 2, h / 2,
        {"+y": "cm_ipc_front", "+x": "cm_ipc_side", "-x": "cm_ipc_side", "*": "cm_olive"}, bevel=0.008 if m.d == 0 else 0.0, S="sSgfv")
    n = nn(m, 16, 10, 6, 5)
    with m.push(R=AIM, t=(0, L / 2 - 0.004, 0)):
        if m.d <= 2:
            lathe(m, [(0.0185, 0.0), (0.0185, 0.008), (0.016, 0.011)], "cm_dark", n=n)
            disc(m, 0.016, 0.011, "cm_lens", n)
        if m.d <= 1:
            with m.push(sels=("ir",)):
                for sx in (-1, 1):
                    for sy in (-1, 1):
                        with m.push(t=(sx * 0.0228, sy * 0.012, 0.0006)):
                            disc(m, 0.0028, 0.0, "cm_ir", 8, polar=False)
    # platine magnétique arrière (plan y = -0.045) et son col
    with m.push(R=AIM, t=(0, -0.045, 0)):
        lathe(m, [(0.024, 0.0), (0.024, 0.004), (0.012, 0.007), (0.012, 0.012)] if m.d <= 1 else [(0.024, 0.0), (0.024, 0.012)], "cm_metal", n=n)
        disc(m, 0.024, 0.0, "cm_metal", n, up=False, polar=False)
    if m.d <= 2:
        # antenne Wi-Fi courte, rabattable
        tube(m, (w * 0.3, -L / 2 + 0.02, h / 2), (w * 0.3, -L / 2 + 0.02, h / 2 + 0.012), 0.006, "cm_metal", n=nn(m, 8, 6, 4), caps=(False, False))
        tube(m, (w * 0.3, -L / 2 + 0.02, h / 2 + 0.012), (w * 0.3, -L / 2 + 0.015, h / 2 + 0.075), 0.0045, "cm_rubber", n=nn(m, 8, 6, 4), r1=0.0035, caps=(False, True))
    led(m, (-w * 0.32, L / 2 - 0.0035, h * 0.34), r=0.003)
    if stand:
        # rotule sous le boîtier, tige et semelle magnétique (z = -0.092)
        if m.d <= 2:
            with m.push(t=(0, -0.005, -h / 2 - 0.010)):
                sphere(m, 0.011, "cm_metal", nn(m, 10, 8, 5), nn(m, 6, 4, 2))
            tube(m, (0, -0.005, -h / 2 - 0.018), (0, -0.005, -0.084), 0.006, "cm_dark", n=nn(m, 8, 6, 4), caps=(False, False))
        with m.push(t=(0, -0.005, -0.092)):
            lathe(m, [(0.036, 0.0), (0.036, 0.006), (0.012, 0.010)], "cm_rubber", n=n)
            r3.add_prism_solid(m, (0, 0, 0), (0, 0, 0.03), (1, 0, 0), (0, 1, 0), [(0.03, 0), (0, 0.03), (-0.03, 0), (0, -0.03)], "sg")
    m.memory("eye", (0, 0.12, 0.03))
    m.memory("dir", (0, 0.22, 0.03))


def cam_ipc(d):
    m = Mesh(d, seed=70)
    ipc2(m)
    return m


def cam_tactical(d):
    """Caméra tactique autonome : trépied vert à entretoises, colonne, tourelle panoramique, caméra bi-capteur
    compacte à visière, batterie suspendue sous la tête, antenne fouet, câble. Origine au sol, visée +Y."""
    m = Mesh(d, seed=76)
    hub = 0.98
    for az in (90, 210, 330):
        a = math.radians(az)
        foot = (0.48 * math.cos(a), 0.48 * math.sin(a), 0.015)
        top = (0.035 * math.cos(a), 0.035 * math.sin(a), hub)
        mid = r3.vlerp(foot, top, 0.52)
        if m.d <= 2:
            tube(m, top, mid, 0.014, "cm_olive", n=nn(m, 8, 6, 4), caps=(False, False), S="sg")
            tube(m, mid, foot, 0.011, "cm_metal", n=nn(m, 8, 6, 4), caps=(False, False))
            tube(m, r3.vlerp(foot, top, 0.40), (0.02 * math.cos(a), 0.02 * math.sin(a), 0.42), 0.006, "cm_metal", n=nn(m, 6, 4, 3), caps=(False, False))
        else:
            tube(m, top, foot, 0.014, "cm_olive", n=3, caps=(False, False), S="sg")
        if m.d <= 1:
            with m.push(t=mid):
                lathe(m, [(0.017, -0.02), (0.017, 0.02)], "cm_rubber", n=nn(m, 8, 6), closed=(True, True))
            with m.push(t=(foot[0], foot[1], 0.0)):
                lathe(m, [(0.024, 0.0), (0.02, 0.02), (0.0, 0.026)], "cm_rubber", n=nn(m, 8, 6))
    n = nn(m, 14, 10, 6, 4)
    tube(m, (0, 0, 0.36), (0, 0, hub + 0.12), 0.016, "cm_metal", n=nn(m, 10, 6, 4, 3), caps=(False, False), S="sSg")
    if m.d <= 2:
        with m.push(t=(0, 0, hub - 0.04)):
            lathe(m, [(0.05, 0.0), (0.05, 0.06), (0.024, 0.08)], "cm_olive", n=n, closed=(True, False))
        with m.push(t=(0, 0, 0.42)):
            lathe(m, [(0.03, -0.015), (0.03, 0.015)], "cm_olive", n=n, closed=(True, True))
    # tourelle et caméra
    zc = hub + 0.245
    with m.push(sels=("pan",)):
        with m.push(t=(0, 0, hub + 0.12)):
            lathe(m, [(0.05, 0.0), (0.05, 0.05), (0.035, 0.06)], "cm_dark", n=n, closed=(True, True))
            if m.d <= 2:
                box(m, -0.107, 0.107, -0.03, 0.03, 0.05, 0.062, "cm_dark")
        with m.push(sels=("tilt",)):
            with m.push(t=(0, 0.01, zc)):
                box(m, -0.095, 0.095, -0.11, 0.11, -0.065, 0.065, {"+y": "cm_thermal", "-y": "cm_vent", "*": "cm_olive"}, S="sSgfv", bevel=0.01 if m.d == 0 else 0)
                if m.d <= 2:
                    box(m, -0.10, 0.10, -0.09, 0.15, 0.069, 0.073, "cm_olive")
                    for sx in (-1, 1):
                        box(m, sx * 0.103 - 0.004, sx * 0.103 + 0.004, -0.03, 0.03, -0.12, 0.02, "cm_dark")
                if m.d <= 1:
                    with m.push(sels=("ir",)):
                        for x in (-0.019, -0.057):
                            box(m, x - 0.012, x + 0.012, 0.11, 0.1108, -0.042, -0.032, "cm_ir", skip="-y")
            led(m, (0.075, 0.1205, zc + 0.045), r=0.003)
        if m.d <= 2:
            tube(m, (-0.07, -0.06, zc + 0.073), (-0.07, -0.06, zc + 0.10), 0.007, "cm_metal", n=nn(m, 8, 6, 4), caps=(False, False))
            tube(m, (-0.07, -0.06, zc + 0.10), (-0.07, -0.06, zc + 0.46), 0.004, "cm_rubber", n=nn(m, 6, 4, 3), r1=0.003, caps=(False, True))
    m.memory("pan_axis", (0, 0, hub))
    m.memory("pan_axis", (0, 0, hub + 0.1))
    m.memory("tilt_axis", (-0.05, 0.01, zc))
    m.memory("tilt_axis", (0.05, 0.01, zc))
    # batterie suspendue sous la tête, sangle, câble spiralé
    with m.push(t=(0, -0.01, 0.74)):
        box(m, -0.085, 0.085, -0.055, 0.055, -0.075, 0.075, {"+y": "cm_batt", "-y": "cm_batt", "*": "cm_olive"}, S="sS", bevel=0.008 if m.d == 0 else 0)
        if m.d <= 1:
            box(m, -0.03, 0.03, -0.058, 0.058, 0.075, 0.09, "cm_rubber")
    if m.d <= 1:
        tube(m, (0, -0.01, 0.83), (0, -0.01, hub - 0.04), 0.004, "cm_rubber", n=4, caps=(False, False))
    if m.d <= 2:
        cable(m, [(0.06, -0.03, 0.80), (0.085, -0.04, 0.95), (0.06, -0.04, 1.12), (0.05, -0.06, zc - 0.03), (0.04, -0.075, zc)], 0.004, "cm_cable", n=nn(m, 6, 4, 3))
    m.memory("eye", (0, 0.17, zc))
    m.memory("dir", (0, 0.27, zc))
    return m


def cam_covert(d):
    """Caméra dissimulée : petit boîtier mat brun-gris, sténopé, ouïes, fil d'antenne. Origine au dos (plan du mur)."""
    m = Mesh(d, seed=77)
    box(m, -0.035, 0.035, 0.0, 0.026, -0.024, 0.024, {"+y": "cm_covert", "*": "cm_rubber"}, S="sSgfv", bevel=0.004 if d == 0 else 0, skip="")
    if d <= 1:
        with m.push(R=AIM, t=(0, 0.026, 0.0078)):
            with m.push(sels=("dome",)):
                disc(m, 0.0035, 0.0004, "cm_glass", 8, polar=False)
        cable(m, [(0.03, 0.013, -0.024), (0.032, 0.012, -0.05), (0.028, 0.004, -0.09)], 0.0012, "cm_cable", n=3)
    led(m, (-0.026, 0.0262, -0.016), r=0.0018)
    m.memory("eye", (0, 0.06, 0.008))
    m.memory("dir", (0, 0.16, 0.008))
    return m


MODELS = {
    # nom : (constructeur, masse kg, dossier, os PTZ)
    "camera_ip": (cam_ipc, 0.6, ITEMS, False), "item3d_camera": (cam_ipc, 0.6, ITEMS, False),
    "cam_ptz_wall": (lambda d: cam_ptz(d, "wall"), 4.5, DATA, True), "cam_ptz_pole": (lambda d: cam_ptz(d, "pole"), 5.0, DATA, True),
    "cam_bullet_wall": (lambda d: cam_bullet(d, "wall"), 1.1, DATA, False), "cam_bullet_ceil": (lambda d: cam_bullet(d, "ceil"), 1.1, DATA, False),
    "cam_bullet_pole": (lambda d: cam_bullet(d, "pole"), 1.4, DATA, False), "cam_bullet_corner": (lambda d: cam_bullet(d, "corner"), 1.5, DATA, False),
    "cam_thermal_wall": (lambda d: cam_thermal(d, "wall"), 3.2, DATA, False), "cam_thermal_pole": (lambda d: cam_thermal(d, "pole"), 3.5, DATA, False),
    "cam_minidome": (lambda d: cam_minidome(d), 0.5, DATA, False), "cam_minidome_wall": (lambda d: cam_minidome(d, True), 0.5, DATA, False),
    "cam_fisheye": (cam_fisheye, 0.6, DATA, False), "cam_tactical": (cam_tactical, 9.0, DATA, True), "cam_covert": (cam_covert, 0.15, DATA, False),
}
SECTIONS = ("camo", "led", "ir", "dome")


def finish(m):
    out = []
    for idx, uvs, nrm, mat, sels in m.faces:
        if r3.MATS[mat].atlas == "cam" and "camo" not in sels:
            sels = tuple(sels) + ("camo",)
        out.append((idx, uvs, nrm, mat, sels))
    m.faces = out
    return m


MODEL_CFG = """// Caméras IP (tools/model_ip_cameras.py) : sections retexturables (camo, led, ir, dome), tête PTZ (pan, tilt).
class CfgSkeletons {
    class Default { isDiscrete=1; skeletonInherit=""; skeletonBones[]={}; };
    class comspec_cam_ptz_skel: Default { skeletonBones[]={"pan","","tilt","pan"}; };
};
class CfgModels {
    class Default { sectionsInherit=""; sections[]={}; skeletonName=""; };
    class comspec_cam_fixed: Default { sections[]={%(sections)s}; };
    class comspec_cam_ptz: comspec_cam_fixed {
        skeletonName="comspec_cam_ptz_skel";
        class Animations {
            // phase = cap relatif / 360 (-0.5 .. 0.5), + vers la droite ; site : 0 (repos) .. 1 (90° vers le bas)
            class pan { type="rotation"; source="pan"; selection="pan"; axis="pan_axis"; memory=1; minValue=-0.5; maxValue=0.5; angle0="rad -180"; angle1="rad 180"; };
            class tilt { type="rotation"; source="tilt"; selection="tilt"; axis="tilt_axis"; memory=1; minValue=-1; maxValue=1; angle0="rad -90"; angle1="rad 90"; };
        };
    };
%(models)s};
"""


def write_model_cfg(folder, names):
    lines = "".join("    class %s: %s {};\n" % (n, "comspec_cam_ptz" if MODELS[n][3] else "comspec_cam_fixed") for n in names)
    with open(os.path.join(folder, "model.cfg"), "w", encoding="utf-8", newline="\n") as f:
        f.write(MODEL_CFG % {"sections": ",".join('"%s"' % s for s in SECTIONS), "models": lines})


def main():
    only = [s for s in os.environ.get("ONLY", "").split(",") if s]
    obj_dir = os.environ.get("OBJ_DIR", "")
    tmp = obj_dir or tempfile.mkdtemp(prefix="cam_tex_")
    os.makedirs(DATA, exist_ok=True)
    flat_mats()
    if os.environ.get("NO_TEX"):
        declare(r3.Atlas("cam", 1024, 1024, TEX, "cam.rvmat"))
        png = {}
    else:
        png = build_textures(os.path.join(tmp, "tex"), convert=not os.environ.get("NO_PAA"))
    report = {}
    for name, (fn, mass, folder, ptz) in MODELS.items():
        if only and name not in only:
            continue
        m0 = r3.write_p3d(os.path.join(folder, name + ".p3d"), lambda d, fn=fn: finish(fn(d)), mass, (), lods=4, shadow_from=1,
                          res=(1.0, 3.0, 7.0, 15.0), report=report)
        r3.check_mesh(m0, name)
        st = report[name + ".p3d"]
        print("%-20s %s  sections %s  %.0f ko" % (name, " / ".join("%d" % t for _, _, t, _ in st["lods"]), "/".join("%d" % s for _, _, _, s in st["lods"]), st["size"] / 1e3))
        if obj_dir:
            r3.export_obj(m0, os.path.join(obj_dir, name + ".obj"), png)
    if not only:
        write_model_cfg(DATA, [n for n in MODELS if MODELS[n][2] == DATA])
        # camera_ip.p3d et item3d_camera.p3d vivent dans items/data : même model.cfg, dans leur dossier
        with open(os.path.join(ITEMS, "model.cfg"), "w", encoding="utf-8", newline="\n") as f:
            f.write("// Caméra IP posée et objet d'inventaire (tools/model_ip_cameras.py) : sections retexturables.\n"
                    "class CfgSkeletons {\n    class Default { isDiscrete=1; skeletonInherit=\"\"; skeletonBones[]={}; };\n};\n"
                    "class CfgModels {\n    class Default { sectionsInherit=\"\"; sections[]={}; skeletonName=\"\"; };\n"
                    "    class camera_ip: Default { sections[]={%s}; };\n    class item3d_camera: camera_ip {};\n};\n" % ",".join('"%s"' % s for s in SECTIONS))
    if obj_dir:
        # accessoires des rendus de mise en situation : plafond, poteau, angle de bâtiment
        pm = Mesh(0)
        box(pm, -1.5, 1.5, -1.5, 1.5, 0.0, 0.2, "cm_white")
        r3.export_obj(pm, os.path.join(obj_dir, "prop_ceiling.obj"), png)
        pm = Mesh(0)
        tube(pm, (0, -0.07, -3.2), (0, -0.07, 1.6), 0.07, "cm_metal", n=24)
        r3.export_obj(pm, os.path.join(obj_dir, "prop_pole.obj"), png)
        pm = Mesh(0)
        with pm.push(R=rot("z", 45)):
            box(pm, -2.5, 0.0, -2.5, 0.0, -3.0, 1.5, "cm_white")
        r3.export_obj(pm, os.path.join(obj_dir, "prop_corner.obj"), png)
        pm = Mesh(0)
        box(pm, -20.0, 20.0, 14.0, 14.5, 0.0, 9.0, "cm_dark")
        box(pm, -20.0, -19.5, -2.0, 14.0, 0.0, 9.0, "cm_dark")
        box(pm, 19.5, 20.0, -2.0, 14.0, 0.0, 9.0, "cm_dark")
        r3.export_obj(pm, os.path.join(obj_dir, "prop_shade.obj"), png)
    with open(os.path.join(tmp, "report_cameras.json"), "w") as f:
        json.dump(report, f, indent=1)


if __name__ == "__main__":
    main()
