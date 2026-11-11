"""
Infrastructure réseau intérieure (salle serveur, local technique) : baies 19", coffrets muraux, serveur tour, onduleur,
switch de table, chemins de câbles, goulottes, climatiseur de salle, armoire électrique.
Modèles MLOD en pur Python (relay3d.py), atlas procédural « infra », dans Sources/addons/items/data/infra/.

  infra_rack_patch     baie de brassage 42U 600 x 1000 x 2000 : panneaux de brassage, switchs, passe-câbles, jarretières
                       RJ45 / fibre colorées, onduleur en bas, porte vitrée fumée
  infra_rack_server    baie serveur 42U : switchs de tête, serveurs 1U / 2U, baies de stockage, console, PDU, onduleurs ;
                       sans porte avant (porte arrière perforée)
  infra_wall_9u / _12u coffret mural 600 x 450 : origine au plan du mur (Y = 0), +Y sortant, milieu du coffret
  infra_server_tower   serveur tour 200 x 550 x 450          infra_ups           onduleur tour 190 x 420 x 330
  infra_switch_desk    switch / routeur de table 440 x 260 x 44, trois antennes
  infra_tray_straight  chemin de câbles en échelle 300 mm, 2 m (bouts en X = -1 et +1 : s'aboutent sans jour)
  infra_tray_corner    angle 90° (bouts en Y = -0,5 et X = +0,5)     infra_tray_tee   té (X = -0,5, X = +0,5, Y = -0,5)
  infra_trunk_straight goulotte PVC murale 60 x 40, 2 m (plan du mur Y = 0)   infra_trunk_corner   angle de goulotte
  infra_crac           climatiseur de salle 1000 x 850 x 1950       infra_elec_cabinet  armoire électrique 800 x 300 x 1900

Origine au sol, au centre (sauf muraux) ; face avant vers -Y. Unités : 1U = 44,45 mm, façade 19" = 482,6 mm.
Sélections lumineuses (hiddenSelections, setObjectTexture, matière infra_glow) : led (vert : alimentation, liens),
led2 (ambre : activité, clignote), led3 (bleu / rouge : état, défaut). Verre fumé : « glass » (faces alpha en fin de LOD).
LOD : visuelles 1 / 4 / 12 / 30 / 80 (ou moins pour les petits objets), ombre, géométrie, tir, vue = boîtes.

    python model_net_infra.py          # modèles, textures (hemtt utils paa convert), rvmat, model.cfg
    NO_TEX=1 : géométrie seule     NO_PAA=1 : PNG sans conversion     OBJ_DIR=d : exporte les OBJ (rendus Blender)
"""
import json
import math
import os
import random
import sys
import tempfile

import numpy as np
from PIL import Image

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import relay3d as r3  # noqa: E402
from relay3d import Atlas, Mesh, beam, box, cable, font, lathe, noise, rot, tube  # noqa: E402
from relay_tex import base, lin, louvres, srgb8, stamp, weather  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "items", "data", "infra")
TEX = "z\\comspec_atak_native\\addons\\items\\data\\infra\\"
U = 0.04445
W19 = 0.4826
MM = 1024 / 482.6            # pixels par mm sur une façade 19" (1024 px de large)

STRIPS = (("rail", 0.4445), ("tray", 1.0), ("pvc", 1.0), ("c_blue", 0.5), ("c_yellow", 0.5), ("c_red", 0.5), ("c_green", 0.5),
          ("c_grey", 0.5), ("c_orange", 0.5), ("c_aqua", 0.5), ("c_black", 0.5))
FACES = (("f_patch24", 1), ("f_sw48", 1), ("f_sw24", 1), ("f_srv1", 1), ("f_srv2", 2), ("f_sto2", 2), ("f_ups2", 2), ("f_pdu1", 1),
         ("f_blank1", 1), ("f_mgr1", 1), ("f_kvm1", 1), ("f_rtr1", 1))
RECTS = {"side": (1024, 320, 512, 704), "crac_front": (1536, 320, 512, 704), "perf": (1024, 1024, 512, 512),
         "rack_top": (1536, 1024, 512, 512), "elec_front": (1024, 1536, 384, 512), "tower_front": (1408, 1536, 256, 512),
         "tower_side": (1664, 1536, 384, 512), "blk": (0, 1760, 256, 256), "gry": (256, 1760, 256, 256),
         "ups_front": (512, 1760, 256, 288), "rtr_front": (768, 1760, 256, 96), "rtr_top": (768, 1856, 256, 192)}
CABLES = ("c_blue", "c_yellow", "c_red", "c_green", "c_grey", "c_orange", "c_aqua")


# Positions des ports (mm depuis le bord gauche de la façade) : partagées par les textures et la géométrie.
def patch_x(i):
    return 45.3 + i * 16 + (i // 6) * 8


def sw_x(c):
    return 69.0 + c * 14 + (c // 6) * 6


SFP_X = (424.0, 436.0, 448.0, 460.0)


def declare(paint=True):
    """Déclare les régions (matières) ; paint : peint l'atlas. Renvoie l'atlas."""
    at = Atlas("infra", 2048, 2048, TEX, "infra.rvmat", base=(0.02, 0.02, 0.022))
    for i, (key, tile) in enumerate(STRIPS):
        at.region(key, 0, i * 28, 2048, 28, "strip", tile, pad=3)
    y = 320
    for key, nu in FACES:
        at.region(key, 0, y, 1024, 96 * nu, "rect", pad=1)
        y += 96 * nu
    for key, (x, yy, w, h) in RECTS.items():
        at.region(key, x, yy, w, h, "rect", pad=3)
    r3.flat_mat("led_g", (0.10, 1.0, 0.25), TEX + "infra_glow.rvmat")
    r3.flat_mat("led_a", (1.0, 0.55, 0.05), TEX + "infra_glow.rvmat")
    r3.flat_mat("led_b", (0.15, 0.45, 1.0), TEX + "infra_glow.rvmat")
    r3.MATS["glass"] = r3.Mat("glass", "#(argb,8,8,3)color(0.02,0.025,0.03,0.5,ca)", TEX + "infra_glass.rvmat", "flat")
    r3.MATS["glass"].atlas = (0.02, 0.025, 0.03)
    if paint:
        paint_atlas(at)
    return at


# --------------------------------------------------------------------------------------------------------------------
# Atlas
# --------------------------------------------------------------------------------------------------------------------
BLK = lin(26, 27, 29)
DGR = lin(58, 60, 64)
SIL = lin(132, 136, 140)
RAL7035 = lin(200, 204, 198)
INK = (226, 228, 224, 255)
HOLE = (8, 8, 9, 255)


def _ears(d, w, h, col=(18, 18, 20, 255)):
    """Oreilles 19" : deux vis cage par oreille et par U."""
    e = int(20 * MM)
    nu = max(1, round(h / 96))
    for x0 in (0, w - e):
        d.rectangle([x0, 0, x0 + e, h], fill=col)
        for k in range(nu):
            for fy in (0.2, 0.8):
                cy = (k + fy) * h / nu
                cx = x0 + e / 2
                d.ellipse([cx - 7, cy - 7, cx + 7, cy + 7], fill=(150, 152, 150, 255), outline=(40, 40, 42, 255))
                d.line([cx - 4, cy, cx + 4, cy], fill=(50, 50, 52, 255), width=2)
                d.line([cx, cy - 4, cx, cy + 4], fill=(50, 50, 52, 255), width=2)
    d.line([0, 0, w, 0], fill=(60, 62, 64, 255), width=1)
    d.line([0, h - 1, w, h - 1], fill=(4, 4, 5, 255), width=2)


def _rj45(d, cx, cy, up=True, lit=None):
    """Prise RJ45 (13 x 11 mm) : cavité, contacts dorés, encoche du verrou."""
    pw, ph = 13 * MM / 2, 10.5 * MM / 2
    d.rectangle([cx - pw, cy - ph, cx + pw, cy + ph], fill=(70, 72, 74, 255))
    d.rectangle([cx - pw + 2, cy - ph + 2, cx + pw - 2, cy + ph - 2], fill=HOLE)
    for k in range(8):
        x = cx - pw + 5 + k * (2 * pw - 10) / 7
        y0 = cy - ph + 3 if up else cy + ph - 9
        d.line([x, y0, x, y0 + 6], fill=(196, 160, 60, 255), width=1)
    nw = pw * 0.45
    if up:
        d.rectangle([cx - nw, cy + ph - 4, cx + nw, cy + ph], fill=HOLE)
    else:
        d.rectangle([cx - nw, cy - ph, cx + nw, cy - ph + 4], fill=HOLE)


def _vent(d, x0, y0, x1, y1, pitch=7, col=HOLE):
    """Grille en nid d'abeille (ronds en quinconce)."""
    r = pitch * 0.33
    j = 0
    y = y0 + pitch / 2
    while y < y1 - r:
        x = x0 + pitch / 2 + (pitch / 2 if j % 2 else 0)
        while x < x1 - r:
            d.ellipse([x - r, y - r, x + r, y + r], fill=col)
            x += pitch
        y += pitch * 0.87
        j += 1


def paint_atlas(at):
    rng = np.random.default_rng(1942)
    W = 2048
    # --- Bandes : montant perforé, acier du chemin de câbles, PVC, cordons ---
    cols = {"c_blue": lin(30, 90, 190), "c_yellow": lin(226, 190, 30), "c_red": lin(190, 36, 30), "c_green": lin(40, 150, 70),
            "c_grey": lin(150, 152, 150), "c_orange": lin(236, 120, 20), "c_aqua": lin(60, 190, 200), "c_black": lin(22, 22, 24)}
    for key, tile in STRIPS:
        s = at.sl(key)
        n = noise(28, W, 1.5, rng=rng)
        xx = np.arange(W)[None, :] * np.ones((28, 1))
        yy = np.arange(28)[:, None] * np.ones((1, W))
        if key == "rail":
            at.rgb[s] = lin(120, 124, 126)[None, None, :] * (0.85 + 0.3 * n[..., None])
            hole = (np.mod(xx, W / 30.0) > W / 30.0 * 0.28) & (np.mod(xx, W / 30.0) < W / 30.0 * 0.72) & (yy > 8) & (yy < 20)
            at.rgb[s] = np.where(hole[..., None], 0.004, at.rgb[s])
            at.height[s] = np.where(hole, 0.1, 0.5)
            at.spec[s], at.gloss[s], at.metal[s] = 0.5, 0.5, 1.0
        elif key == "tray":
            at.rgb[s] = lin(150, 154, 158)[None, None, :] * (0.8 + 0.4 * n[..., None])
            at.spec[s], at.gloss[s], at.metal[s] = 0.5, 0.45, 1.0
        elif key == "pvc":
            at.rgb[s] = lin(226, 226, 220)[None, None, :] * (0.92 + 0.12 * n[..., None])
            seam = (np.abs(yy - 14) < 0.8)
            at.rgb[s] = at.rgb[s] * np.where(seam, 0.6, 1.0)[..., None]
            at.height[s] = np.where(seam, 0.3, 0.5)
            at.spec[s], at.gloss[s] = 0.25, 0.4
        else:
            at.rgb[s] = cols[key][None, None, :] * (0.85 + 0.3 * n[..., None])
            mark = (np.mod(xx, 512) > 60) & (np.mod(xx, 512) < 200) & (np.abs(yy - 14) < 2) & (np.mod(xx, 9) < 6)
            at.rgb[s] = np.where(mark[..., None], at.rgb[s] * 0.45, at.rgb[s])
            at.spec[s], at.gloss[s] = 0.3, 0.5

    # --- Façades 19" ---
    def face(key, col, draw, spec=0.3, gloss=0.4, metal=0.3, dirt=0.15):
        base(at, key, col, rng, var=0.05, grain=0.03, spec=spec, gloss=gloss, metal=metal, hamp=0.02)

        def fn(d, w, h):
            draw(d, w, h)
            _ears(d, w, h)
        stamp(at, key, fn, height=0.42, shadow=0.25)
        s = at.sl(key)
        hh, ww = at.rgb[s].shape[:2]
        dust = noise(min(256, hh), 512, 2.0, rng=rng)
        dust = np.asarray(Image.fromarray((dust * 255).astype(np.uint8)).resize((ww, hh), Image.BICUBIC), dtype=np.float64) / 255
        at.rgb[s] = at.rgb[s] * (1 - dirt * 0.5 * dust[..., None]) + dirt * 0.04 * dust[..., None]

    f8, f9, f11, f13 = font(8, False), font(9, False), font(11), font(13)

    def patch(d, w, h):
        for i in range(24):
            cx = patch_x(i) * MM
            _rj45(d, cx, h * 0.60, up=False)
            d.text((cx - 5, 8), "%d" % (i + 1), fill=INK, font=f9)
        for g in range(4):
            x0 = (patch_x(g * 6) - 8) * MM
            d.rectangle([x0, 22, x0 + (6 * 16) * MM, 30], fill=(224, 224, 216, 255))
            d.text((x0 + 4, 21), "BAIE A%d  1-6" % (g + 1), fill=(20, 20, 20, 255), font=f8)
        d.text((30 * MM - 20, 78), "CAT6A", fill=(150, 152, 150, 255), font=f8)
    face("f_patch24", BLK, patch)

    def sw(d, w, h, cols_=24, sfp=True, name="CS-NET 48G"):
        d.rectangle([22 * MM, 6, 60 * MM, h - 6], outline=(70, 72, 76, 255))
        d.text((24 * MM, 12), name, fill=INK, font=f9)
        for k, t in enumerate(("PWR", "SYS", "STK")):
            d.text((24 * MM, 34 + k * 17), t, fill=(150, 152, 150, 255), font=f8)
            d.rectangle([24 * MM + 30, 37 + k * 17, 24 * MM + 38, 43 + k * 17], fill=(20, 60, 24, 255))
        for c in range(cols_):
            cx = sw_x(c) * MM
            _rj45(d, cx, h * 0.31, up=True)
            _rj45(d, cx, h * 0.69, up=False)
            if c % 6 == 0:
                d.text((cx - 12, 0), "%d" % (c * 2 + 1), fill=(170, 172, 170, 255), font=f8)
        if sfp:
            for x in SFP_X if cols_ == 24 else (270.0, 284.0, 298.0, 312.0):
                for fy in (0.31, 0.69):
                    d.rectangle([x * MM - 13, h * fy - 10, x * MM + 13, h * fy + 10], fill=(150, 152, 154, 255))
                    d.rectangle([x * MM - 10, h * fy - 7, x * MM + 10, h * fy + 7], fill=HOLE)
        if cols_ == 12:
            d.rectangle([340 * MM, 22, 372 * MM, 74], outline=(70, 72, 76, 255))
            d.text((342 * MM, 26), "CONSOLE", fill=(150, 152, 150, 255), font=f8)
            _rj45(d, 356 * MM, 56, up=False)
            _vent(d, 384 * MM, 12, 458 * MM, h - 12, 8)
    face("f_sw48", lin(34, 36, 40), lambda d, w, h: sw(d, w, h))
    face("f_sw24", lin(34, 36, 40), lambda d, w, h: sw(d, w, h, 12, True, "CS-NET 24G"))

    def bay(d, x0, y0, x1, y1, vertical=False):
        """Tiroir de disque : poignée grillagée, verrou, deux voyants éteints."""
        d.rectangle([x0, y0, x1, y1], fill=(46, 48, 52, 255), outline=(12, 12, 14, 255), width=2)
        _vent(d, x0 + 4, y0 + 4, x1 - (4 if vertical else 26), y1 - (22 if vertical else 4), 6)
        if vertical:
            d.rectangle([x0 + 5, y1 - 18, x1 - 5, y1 - 6], fill=(92, 96, 100, 255))
        else:
            d.rectangle([x1 - 22, y0 + 6, x1 - 6, y1 - 6], fill=(92, 96, 100, 255))

    def srv1(d, w, h):
        for k in range(4):
            x0 = (24 + k * 100) * MM
            bay(d, x0, 10, x0 + 96 * MM, h - 10)
        x = 428 * MM
        d.rectangle([x, 10, x + 32 * MM, h - 10], fill=(40, 42, 46, 255), outline=(12, 12, 14, 255))
        d.ellipse([x + 10, 18, x + 30, 38], fill=(70, 72, 76, 255), outline=(160, 162, 160, 255))
        d.rectangle([x + 38, 20, x + 58, 34], fill=HOLE)
        d.text((x + 8, 60), "SRV", fill=(150, 152, 150, 255), font=f9)
    face("f_srv1", lin(40, 42, 46), srv1)

    def srv2(d, w, h):
        for r_ in range(3):
            for k in range(4):
                x0 = (24 + k * 100) * MM
                y0 = 8 + r_ * (h - 16) / 3
                bay(d, x0, y0, x0 + 96 * MM, y0 + (h - 16) / 3 - 4)
        x = 428 * MM
        d.rectangle([x, 8, x + 32 * MM, h - 8], fill=(40, 42, 46, 255), outline=(12, 12, 14, 255))
        d.ellipse([x + 10, 18, x + 30, 38], fill=(70, 72, 76, 255), outline=(160, 162, 160, 255))
        for k in range(2):
            d.rectangle([x + 10, 60 + k * 26, x + 36, 76 + k * 26], fill=HOLE)
        d.text((x + 8, h - 40), "SRV-2U", fill=(150, 152, 150, 255), font=f9)
    face("f_srv2", lin(40, 42, 46), srv2)

    def sto2(d, w, h):
        for k in range(24):
            x0 = (22 + k * 18.3) * MM
            bay(d, x0, 10, x0 + 17.3 * MM, h - 10, vertical=True)
            d.text((x0 + 8, 0), "%d" % k, fill=(120, 122, 120, 255), font=f8)
    face("f_sto2", lin(40, 42, 46), sto2)

    def ups2(d, w, h):
        _vent(d, 26 * MM, 14, 170 * MM, h - 14, 9)
        _vent(d, 312 * MM, 14, 458 * MM, h - 14, 9)
        d.rounded_rectangle([188 * MM, 30, 296 * MM, h - 30], 10, fill=(14, 14, 16, 255), outline=(90, 92, 96, 255), width=2)
        d.rectangle([198 * MM, 46, 256 * MM, h - 46], fill=(26, 60, 110, 255))
        d.text((202 * MM, 54), "230 V", fill=(190, 220, 250, 255), font=f13)
        d.text((202 * MM, 78), "BATT 100 %", fill=(190, 220, 250, 255), font=f11)
        d.text((202 * MM, 100), "CHARGE 41 %", fill=(150, 190, 230, 255), font=f9)
        for k in range(3):
            d.ellipse([264 * MM, 50 + k * 30, 264 * MM + 20, 70 + k * 30], fill=(60, 62, 66, 255), outline=(120, 122, 126, 255))
        d.text((30 * MM, h - 14), "ONDULEUR 3 kVA", fill=(150, 152, 150, 255), font=f8)
    face("f_ups2", BLK, ups2)

    def pdu(d, w, h):
        for k in range(8):
            x0 = (70 + k * 40) * MM
            d.rounded_rectangle([x0, 22, x0 + 30 * MM, h - 22], 6, fill=(12, 12, 14, 255), outline=(80, 82, 86, 255), width=2)
            for dx in (0.25, 0.5, 0.75):
                d.rectangle([x0 + 30 * MM * dx - 3, 38, x0 + 30 * MM * dx + 3, 56], fill=(60, 60, 62, 255))
        d.rectangle([26 * MM, 26, 58 * MM, h - 26], fill=(150, 24, 20, 255), outline=(20, 20, 20, 255), width=2)
        d.text((28 * MM, h - 22), "16 A", fill=INK, font=f8)
        d.rectangle([400 * MM, 30, 452 * MM, h - 30], fill=(20, 20, 22, 255), outline=(90, 92, 96, 255))
        d.text((404 * MM, 38), "12.4 A", fill=(220, 60, 40, 255), font=f11)
    face("f_pdu1", BLK, pdu)

    def blank(d, w, h):
        for k in range(18):
            x0 = (30 + k * 24) * MM
            d.rounded_rectangle([x0, 26, x0 + 16 * MM, h - 26], 6, fill=HOLE)
    face("f_blank1", BLK, blank)

    def mgr(d, w, h):
        d.rectangle([22 * MM, h * 0.36, 460 * MM, h * 0.64], fill=(14, 14, 16, 255))
        for k in range(5):
            cx = (60 + k * 90.6) * MM
            d.rectangle([cx - 20, 10, cx + 20, h - 10], outline=(60, 62, 64, 255), width=3)
    face("f_mgr1", BLK, mgr)

    def kvm(d, w, h):
        d.rounded_rectangle([170 * MM, 34, 312 * MM, 62], 10, fill=(60, 62, 66, 255), outline=(10, 10, 12, 255), width=2)
        d.text((30 * MM, 38), "CONSOLE KVM 17\"", fill=(150, 152, 150, 255), font=f9)
        for x in (26 * MM, 452 * MM):
            d.rectangle([x, 20, x + 14, h - 20], fill=(70, 72, 76, 255))
    face("f_kvm1", lin(34, 36, 40), kvm)

    def rtr(d, w, h):
        d.text((24 * MM, 12), "CS-RTR", fill=INK, font=f11)
        d.text((24 * MM, 34), "WAN / LAN", fill=(150, 152, 150, 255), font=f8)
        for k in range(8):
            _rj45(d, (90 + k * 16) * MM, h * 0.55, up=False)
        for k, x in enumerate((240.0, 256.0)):
            d.rectangle([x * MM - 13, h * 0.55 - 10, x * MM + 13, h * 0.55 + 10], fill=(150, 152, 154, 255))
            d.rectangle([x * MM - 10, h * 0.55 - 7, x * MM + 10, h * 0.55 + 7], fill=HOLE)
        d.rectangle([286 * MM, 30, 306 * MM, 60], fill=(20, 20, 22, 255), outline=(120, 122, 126, 255))   # USB
        _vent(d, 330 * MM, 12, 458 * MM, h - 12, 8)
    face("f_rtr1", lin(34, 36, 40), rtr)

    # --- Tôles ---
    base(at, "blk", BLK, rng, var=0.07, grain=0.05, spec=0.25, gloss=0.4, hamp=0.03, hbeta=1.0)
    base(at, "gry", RAL7035, rng, var=0.04, grain=0.02, spec=0.25, gloss=0.4)
    base(at, "side", BLK, rng, var=0.06, grain=0.04, spec=0.25, gloss=0.4)

    def side(d, w, h):
        d.rectangle([8, 8, w - 8, h * 0.5 - 4], outline=(52, 54, 58, 255), width=3)
        d.rectangle([8, h * 0.5 + 4, w - 8, h - 8], outline=(52, 54, 58, 255), width=3)
        for yy_ in (0.25, 0.75):
            d.ellipse([w * 0.5 - 14, h * yy_ - 14, w * 0.5 + 14, h * yy_ + 14], fill=(110, 112, 116, 255), outline=(20, 20, 22, 255), width=2)
            d.line([w * 0.5 - 7, h * yy_, w * 0.5 + 7, h * yy_], fill=(20, 20, 22, 255), width=3)
    stamp(at, "side", side, height=0.42, shadow=0.2)
    weather(at, "side", rng, dirt=0.25, streaks=0.15, edge=0.5, grime_col=(0.12, 0.11, 0.10))
    # Porte perforée : trous hexagonaux denses (tôle noire, fond sombre)
    base(at, "perf", lin(34, 35, 38), rng, var=0.05, grain=0.03, spec=0.3, gloss=0.4)

    def perf(d, w, h):
        _vent(d, 26, 26, w - 26, h - 26, 9, (6, 6, 7, 255))
        d.rectangle([0, 0, w - 1, h - 1], outline=(20, 20, 22, 255), width=6)
        d.rounded_rectangle([w - 60, h * 0.42, w - 28, h * 0.58], 6, fill=(24, 24, 26, 255), outline=(110, 112, 116, 255), width=2)
    stamp(at, "perf", perf, height=0.35)
    base(at, "rack_top", BLK, rng, var=0.06, grain=0.04, spec=0.25, gloss=0.4)

    def rtop(d, w, h):
        for cx in (0.28, 0.72):
            d.ellipse([w * cx - 96, h * 0.35 - 96, w * cx + 96, h * 0.35 + 96], fill=(10, 10, 11, 255), outline=(70, 72, 76, 255), width=4)
            for r_ in (30, 55, 80):
                d.ellipse([w * cx - r_, h * 0.35 - r_, w * cx + r_, h * 0.35 + r_], outline=(90, 92, 96, 255), width=3)
            for a in range(4):
                an = a * math.pi / 4
                d.line([w * cx - 92 * math.cos(an), h * 0.35 - 92 * math.sin(an), w * cx + 92 * math.cos(an), h * 0.35 + 92 * math.sin(an)], fill=(90, 92, 96, 255), width=3)
        d.rectangle([w * 0.12, h * 0.74, w * 0.88, h * 0.86], fill=(8, 8, 9, 255), outline=(70, 72, 76, 255), width=3)   # passage de câbles à brosse
        for x in range(int(w * 0.13), int(w * 0.87), 4):
            d.line([x, h * 0.75, x, h * 0.85], fill=(30, 30, 32, 255), width=1)
    stamp(at, "rack_top", rtop, height=0.4, shadow=0.2)

    # --- Climatiseur de salle ---
    base(at, "crac_front", RAL7035, rng, var=0.04, grain=0.015, spec=0.28, gloss=0.42)

    def crac(d, w, h):
        ln = (120, 124, 120, 255)
        d.rectangle([0, 0, w, 18], fill=srgb8(RAL7035 * 0.7) + (255,))
        d.rectangle([0, h - 44, w, h], fill=(52, 54, 56, 255))
        d.rectangle([10, 26, w * 0.5 - 4, h - 52], outline=ln, width=3)
        d.rectangle([w * 0.5 + 4, 26, w - 10, h - 52], outline=ln, width=3)
        louvres(d, 30, 50, w * 0.5 - 24, 250, 13, (40, 42, 42, 255), (224, 226, 220, 255))
        louvres(d, w * 0.5 + 24, 50, w - 30, 250, 13, (40, 42, 42, 255), (224, 226, 220, 255))
        louvres(d, 30, h - 250, w * 0.5 - 24, h - 80, 13, (40, 42, 42, 255), (224, 226, 220, 255))
        louvres(d, w * 0.5 + 24, h - 250, w - 30, h - 80, 13, (40, 42, 42, 255), (224, 226, 220, 255))
        d.rounded_rectangle([w * 0.56, 290, w * 0.90, 400], 8, fill=(22, 24, 26, 255), outline=(90, 92, 96, 255), width=3)
        d.rectangle([w * 0.585, 306, w * 0.80, 362], fill=(24, 64, 60, 255))
        d.text((w * 0.60, 310), "21.5 °C", fill=(170, 240, 220, 255), font=font(20))
        d.text((w * 0.60, 338), "HR 45 %  SOUFFLAGE", fill=(130, 200, 180, 255), font=f9)
        for k in range(4):
            d.rectangle([w * 0.585 + k * 30, 372, w * 0.585 + k * 30 + 20, 388], fill=(70, 72, 76, 255))
        d.rounded_rectangle([w * 0.44, 300, w * 0.48, 420], 6, fill=(30, 30, 32, 255))
        d.rectangle([w * 0.08, 300, w * 0.36, 350], fill=(226, 226, 220, 255), outline=ln)
        d.text((w * 0.095, 308), "CLIMATISATION", fill=(30, 30, 30, 255), font=f13)
        d.text((w * 0.095, 328), "DE SALLE  CS-CR 20", fill=(60, 60, 60, 255), font=f9)
    stamp(at, "crac_front", crac, height=0.42, shadow=0.3)
    weather(at, "crac_front", rng, dirt=0.3, streaks=0.3, edge=0.5, grime_col=(0.14, 0.13, 0.11))

    # --- Armoire électrique ---
    base(at, "elec_front", RAL7035, rng, var=0.04, grain=0.015, spec=0.28, gloss=0.42)

    def elec(d, w, h):
        ln = (120, 124, 120, 255)
        d.rectangle([8, 8, w - 8, h - 30], outline=ln, width=3)
        d.rectangle([0, h - 24, w, h], fill=(52, 54, 56, 255))
        # éclair d'avertissement (dessin original)
        cx, cy, r = w * 0.5, h * 0.24, 46
        d.polygon([(cx, cy - r), (cx + r * 0.92, cy + r * 0.62), (cx - r * 0.92, cy + r * 0.62)], fill=(18, 18, 18, 255))
        k = 0.8
        d.polygon([(cx, cy - r * k + 3), (cx + r * 0.92 * k, cy + r * 0.62 * k + 1), (cx - r * 0.92 * k, cy + r * 0.62 * k + 1)], fill=(244, 196, 22, 255))
        d.polygon([(cx + 5, cy - 22), (cx - 10, cy + 6), (cx - 1, cy + 6), (cx - 7, cy + 26), (cx + 11, cy - 2), (cx + 2, cy - 2)], fill=(18, 18, 18, 255))
        d.rectangle([w * 0.2, h * 0.36, w * 0.8, h * 0.42], fill=(244, 196, 22, 255), outline=(18, 18, 18, 255))
        d.text((w * 0.24, h * 0.37), "DANGER 400 V", fill=(18, 18, 18, 255), font=font(15))
        d.rounded_rectangle([w - 60, h * 0.46, w - 30, h * 0.60], 6, fill=(30, 30, 32, 255))
        d.rectangle([w * 0.18, h * 0.07, w * 0.82, h * 0.115], fill=(226, 226, 220, 255), outline=ln)
        d.text((w * 0.2, h * 0.076), "TGBT SALLE SERVEUR", fill=(30, 30, 30, 255), font=f11)
        for k2 in range(3):
            cx2 = w * (0.3 + 0.2 * k2)
            d.ellipse([cx2 - 15, h * 0.66 - 15, cx2 + 15, h * 0.66 + 15], fill=(60, 62, 64, 255), outline=(150, 152, 150, 255), width=3)
        d.ellipse([w * 0.5 - 24, h * 0.76, w * 0.5 + 24, h * 0.76 + 48], fill=(180, 26, 22, 255), outline=(244, 196, 22, 255), width=5)
        for yy_ in (0.12, 0.5, 0.86):
            d.rectangle([6, h * yy_ - 18, 16, h * yy_ + 18], fill=(130, 132, 130, 255), outline=ln)
    stamp(at, "elec_front", elec, height=0.42, shadow=0.3)
    weather(at, "elec_front", rng, dirt=0.3, streaks=0.3, edge=0.5, grime_col=(0.14, 0.13, 0.11))

    # --- Serveur tour, onduleur tour, switch de table ---
    base(at, "tower_front", BLK, rng, var=0.06, grain=0.04, spec=0.3, gloss=0.45)

    def tfront(d, w, h):
        d.rounded_rectangle([18, 16, w - 18, h - 16], 12, outline=(60, 62, 66, 255), width=3)
        d.rectangle([34, 34, w - 34, 74], fill=(44, 46, 50, 255), outline=(10, 10, 12, 255), width=2)     # lecteur
        d.line([40, 54, w - 40, 54], fill=(10, 10, 12, 255), width=2)
        for k in range(4):
            y0 = 96 + k * 52
            d.rectangle([34, y0, w - 34, y0 + 44], fill=(46, 48, 52, 255), outline=(10, 10, 12, 255), width=2)
            _vent(d, 40, y0 + 4, w - 70, y0 + 40, 6)
            d.rectangle([w - 62, y0 + 8, w - 42, y0 + 36], fill=(92, 96, 100, 255))
        _vent(d, 34, 320, w - 34, h - 80, 8)
        d.ellipse([w * 0.5 - 14, h - 66, w * 0.5 + 14, h - 38], fill=(70, 72, 76, 255), outline=(160, 162, 160, 255), width=2)
        for x in (44, 70):
            d.rectangle([x, h - 60, x + 18, h - 46], fill=HOLE)
    stamp(at, "tower_front", tfront, height=0.42, shadow=0.25)
    base(at, "tower_side", BLK, rng, var=0.06, grain=0.04, spec=0.25, gloss=0.4)

    def tside(d, w, h):
        d.rectangle([10, 10, w - 10, h - 10], outline=(48, 50, 54, 255), width=3)
        _vent(d, w * 0.25, h * 0.3, w * 0.75, h * 0.7, 8)
    stamp(at, "tower_side", tside, height=0.4)
    weather(at, "tower_side", rng, dirt=0.3, streaks=0.1, edge=0.5, grime_col=(0.12, 0.11, 0.10))
    base(at, "ups_front", BLK, rng, var=0.06, grain=0.04, spec=0.3, gloss=0.45)

    def upsf(d, w, h):
        d.rounded_rectangle([14, 12, w - 14, h - 12], 12, outline=(60, 62, 66, 255), width=3)
        d.rounded_rectangle([40, 34, w - 40, 138], 8, fill=(14, 14, 16, 255), outline=(90, 92, 96, 255), width=2)
        d.rectangle([54, 48, w - 54, 108], fill=(26, 60, 110, 255))
        d.text((62, 52), "230 V", fill=(190, 220, 250, 255), font=f13)
        d.text((62, 74), "BATT 100 %", fill=(190, 220, 250, 255), font=f11)
        for k in range(3):
            d.ellipse([70 + k * 44, 114, 90 + k * 44, 134], fill=(60, 62, 66, 255), outline=(120, 122, 126, 255))
        _vent(d, 34, 160, w - 34, h - 40, 9)
        d.text((40, h - 34), "ONDULEUR 1500 VA", fill=(150, 152, 150, 255), font=f9)
    stamp(at, "ups_front", upsf, height=0.42, shadow=0.25)
    base(at, "rtr_front", lin(30, 32, 36), rng, var=0.05, grain=0.04, spec=0.3, gloss=0.45)

    def rfront(d, w, h):
        s_ = w / 440.0
        for k in range(8):
            cx = (60 + k * 30) * s_
            d.rectangle([cx - 9, h * 0.35, cx + 9, h * 0.85], fill=(70, 72, 74, 255))
            d.rectangle([cx - 7, h * 0.40, cx + 7, h * 0.80], fill=HOLE)
        d.text((6, 6), "CS-RTR 8", fill=INK, font=f9)
        d.rectangle([330 * s_, h * 0.4, 350 * s_, h * 0.8], fill=(30, 60, 150, 255))
    stamp(at, "rtr_front", rfront, height=0.42)
    base(at, "rtr_top", lin(30, 32, 36), rng, var=0.06, grain=0.05, spec=0.3, gloss=0.45, hamp=0.05, hbeta=1.0)

    def rtop2(d, w, h):
        _vent(d, w * 0.1, h * 0.2, w * 0.9, h * 0.7, 9)
        d.text((w * 0.1, h * 0.8), "CS-RTR 8  ROUTEUR / SWITCH", fill=(150, 152, 150, 255), font=f9)
    stamp(at, "rtr_top", rtop2, height=0.35)


def write_rvmats():
    r3.write_rvmat_super(os.path.join(DATA, "infra.rvmat"), "Infrastructure réseau (tools/model_net_infra.py) : atlas des baies et équipements.",
                         TEX + "infra_nohq.paa", TEX + "infra_smdi.paa", spec=0.35, power=40, fresnel="1.5,0.5")
    r3.write_rvmat_basic(os.path.join(DATA, "infra_glow.rvmat"),
                         "Infrastructure réseau : voyants (lumineux, la couleur vient de la texture : setObjectTexture).", emissive=(8, 8, 8))
    r3.write_rvmat_super(os.path.join(DATA, "infra_glass.rvmat"), "Infrastructure réseau : verre fumé des portes de baie.",
                         "#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)", "#(argb,8,8,3)color(1,0.8,0.95,1,SMDI)", spec=0.9, power=140, fresnel="1.5,0.9")


# --------------------------------------------------------------------------------------------------------------------
# Pièces
# --------------------------------------------------------------------------------------------------------------------
def led(m, x, z, y, w=0.003, h=0.002, mat="led_g", sel="led"):
    """Voyant : petit rectangle lumineux à 0,6 mm devant la façade (face vers -Y)."""
    with m.push(sels=(sel,)):
        m.face([(x - w / 2, y - 0.0006, z - h / 2), (x + w / 2, y - 0.0006, z - h / 2), (x + w / 2, y - 0.0006, z + h / 2), (x - w / 2, y - 0.0006, z + h / 2)], mat, out=(0, -1, 0))


def device(m, kind, zb, yf, depth=0.5, rng=None, body="blk"):
    """Équipement 19" : bas à zb, façade dans le plan Y = yf (face vers -Y), profondeur vers +Y.
    Renvoie la cote du milieu de la façade."""
    d = m.d
    nu = dict(FACES)["f_" + kind]
    h = nu * U - 0.0012
    z0, z1 = zb + 0.0006, zb + 0.0006 + h
    x0, x1 = -W19 / 2, W19 / 2
    rng = rng or m.rng
    m.face([(x0, yf, z0), (x1, yf, z0), (x1, yf, z1), (x0, yf, z1)], "f_" + kind, uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(0, -1, 0))
    if d <= 2 and depth > 0.03:
        bw = 0.2215
        box(m, -bw, bw, yf + 0.002, yf + depth, z0, z1, body, skip="-y" if d <= 1 else "-y+y-z")
    zc = (z0 + z1) / 2

    def fx(mm):
        return x0 + mm / 1000.0
    if d <= 2:
        if kind in ("sw48", "sw24"):
            cols_ = 24 if kind == "sw48" else 12
            if d == 0:
                for c in range(cols_):
                    if rng.random() < 0.72:
                        led(m, fx(sw_x(c)) - 0.003, zc + 0.0195, yf)
                        if rng.random() < 0.6:
                            led(m, fx(sw_x(c)) + 0.003, zc + 0.0195, yf, mat="led_a", sel="led2")
                    if rng.random() < 0.6:
                        led(m, fx(sw_x(c)) - 0.003, zc - 0.0195, yf)
                        if rng.random() < 0.5:
                            led(m, fx(sw_x(c)) + 0.003, zc - 0.0195, yf, mat="led_a", sel="led2")
            else:
                for g in range(cols_ // 6):
                    xa, xb = fx(sw_x(g * 6)) - 0.005, fx(sw_x(g * 6 + 5)) + 0.005
                    led(m, (xa + xb) / 2, zc + 0.0195, yf, w=xb - xa, h=0.0015)
                    if d == 1:
                        led(m, (xa + xb) / 2, zc - 0.0195, yf, w=xb - xa, h=0.0015, mat="led_a", sel="led2")
            if d <= 1:
                led(m, fx(41.5), zc + 0.008, yf, w=0.004, h=0.003)
                led(m, fx(41.5), zc + 0.0, yf, w=0.004, h=0.003, mat="led_b", sel="led3")
        elif kind in ("srv1", "srv2", "sto2"):
            if kind == "sto2":
                n = 24 if d == 0 else 0
                for k in range(n):
                    x = fx(22 + k * 18.3 + 8.6)
                    led(m, x - 0.003, z0 + 0.012, yf, w=0.003, h=0.0025)
                    if rng.random() < 0.5:
                        led(m, x + 0.003, z0 + 0.012, yf, w=0.003, h=0.0025, mat="led_a", sel="led2")
                if d >= 1:
                    led(m, 0.0, z0 + 0.012, yf, w=0.40, h=0.002)
            else:
                rows = 1 if kind == "srv1" else 3
                if d == 0:
                    for r_ in range(rows):
                        for k in range(4):
                            x = fx(24 + k * 100 + 89)
                            z = z0 + (r_ + 0.5) * h / rows
                            led(m, x, z + 0.006, yf, w=0.003, h=0.003)
                            if rng.random() < 0.6:
                                led(m, x, z - 0.006, yf, w=0.003, h=0.003, mat="led_a", sel="led2")
                led(m, fx(437), z1 - 0.012, yf, w=0.008, h=0.004, mat="led_b", sel="led3")
                if d >= 1:
                    led(m, fx(445), zc, yf, w=0.004, h=h * 0.5)
        elif kind == "ups2":
            led(m, fx(272), zc + 0.014, yf, w=0.006, h=0.006)
            if d <= 1:
                led(m, fx(240), zc, yf + 0.0003, w=0.03, h=0.012, mat="led_b", sel="led3")
        elif kind == "pdu1":
            led(m, fx(42), zc, yf, w=0.012, h=0.008, mat="led_a", sel="led2")
        elif kind == "rtr1":
            if d <= 1:
                for k in range(8):
                    if rng.random() < 0.7:
                        led(m, fx(90 + k * 16), zc + 0.012, yf)
            led(m, fx(40), zc - 0.008, yf, w=0.005, h=0.003, mat="led_b", sel="led3")
        elif kind == "mgr1" and d <= 1:
            # anneaux passe-câbles en saillie
            for k in range(5):
                x = fx(60 + k * 90.6)
                for sx in (-1, 1):
                    beam(m, (x + sx * 0.018, yf, zc), (x + sx * 0.018, yf - 0.06, zc), 0.004, 0.03, "blk", a=(1, 0, 0), caps=False)
                beam(m, (x - 0.018, yf - 0.06, zc), (x + 0.018, yf - 0.06, zc), 0.004, 0.03, "blk", a=(0, 1, 0), caps=False)
        elif kind == "kvm1" and d == 0:
            box(m, -0.07, 0.07, yf - 0.02, yf, zc - 0.006, zc + 0.006, "blk", skip="+y")
    elif d == 3 and kind in ("sw48", "srv2", "sto2", "ups2"):
        led(m, 0.0, zc, yf, w=0.3, h=0.004)
    return zc


RING_X = [-W19 / 2 + (60 + k * 90.6) / 1000.0 for k in range(5)]


def patch_cord(m, x_a, z_a, x_b, z_b, yf, z_mgr, col, r=0.0028):
    """Jarretière de la prise (x_a, z_a) à la prise (x_b, z_b) : elle descend dans l'anneau du passe-câbles (cote
    z_mgr) le plus proche de son départ, court dans le passe-câbles, ressort par l'anneau le plus proche de l'arrivée."""
    d = m.d
    ra = min(RING_X, key=lambda x: abs(x - x_a)) + m.rng.uniform(-0.009, 0.009)
    rb = min(RING_X, key=lambda x: abs(x - x_b)) + m.rng.uniform(-0.009, 0.009)
    zo = z_mgr + m.rng.uniform(-0.007, 0.007)
    yo = yf - 0.03 - m.rng.uniform(0, 0.02)
    sag_a = yf - 0.035 - 0.25 * abs(z_a - z_mgr) - m.rng.uniform(0, 0.012)
    sag_b = yf - 0.035 - 0.25 * abs(z_b - z_mgr) - m.rng.uniform(0, 0.012)
    pts = [(x_a, yf + 0.004, z_a), (x_a, yf - 0.024, z_a), ((x_a + ra) / 2, sag_a, (z_a + zo) / 2), (ra, yo, zo)]
    if abs(ra - rb) > 0.02:
        pts.append((rb, yo, zo))
    pts += [((x_b + rb) / 2, sag_b, (z_b + zo) / 2), (x_b, yf - 0.024, z_b), (x_b, yf + 0.004, z_b)]
    if d >= 1:
        pts = [pts[0], pts[1], pts[3], pts[-4], pts[-2], pts[-1]] if len(pts) == 8 else [pts[0], pts[1], pts[3], pts[-2], pts[-1]]
    cable(m, pts, r if d == 0 else r * 1.3, col, n=4 if d == 0 else 3)
    if d == 0:
        for x, z in ((x_a, z_a), (x_b, z_b)):
            box(m, x - 0.006, x + 0.006, yf - 0.022, yf, z - 0.005, z + 0.005, "c_grey", skip="+y")


def rack_shell(m, w=0.6, dp=1.0, h=2.0, front="open", rear="perf", glass=False):
    """Carcasse de baie : socle, toit, quatre poteaux, flancs, montants 19" perforés, porte arrière.
    front : "open" | "glass" | "perf". Renvoie (y de la façade des équipements, cote du premier U)."""
    d = m.d
    x0, x1, y0, y1 = -w / 2, w / 2, -dp / 2, dp / 2
    yf = y0 + 0.11
    zb = 0.105
    r3.solid_box(m, x0, x1, y0, y1, 0.0, h, "sSgfv")
    if d >= 4:
        box(m, x0, x1, y0, y1, 0.0, h, {"-y": "perf" if front != "open" else "blk", "+y": "perf", "side": "side", "*": "rack_top"}, skip="-z")
        return yf, zb
    # flancs, toit, socle, fond
    m.face([(x0, y1, 0.02), (x0, y0, 0.02), (x0, y0, h), (x0, y1, h)], "side", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(-1, 0, 0))
    m.face([(x1, y0, 0.02), (x1, y1, 0.02), (x1, y1, h), (x1, y0, h)], "side", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(1, 0, 0))
    m.face([(x0, y0, h), (x1, y0, h), (x1, y1, h), (x0, y1, h)], "rack_top", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(0, 0, 1))
    m.face([(x1, y1, 0.02), (x0, y1, 0.02), (x0, y1, h), (x1, y1, h)], "perf" if rear == "perf" else "side", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(0, 1, 0))
    if d <= 2:
        # intérieur visible : flancs, plafond et plancher vus de l'intérieur, fond sombre
        m.face([(x0 + 0.02, y0, 0.06), (x0 + 0.02, y1, 0.06), (x0 + 0.02, y1, h - 0.03), (x0 + 0.02, y0, h - 0.03)], "blk", out=(1, 0, 0))
        m.face([(x1 - 0.02, y1, 0.06), (x1 - 0.02, y0, 0.06), (x1 - 0.02, y0, h - 0.03), (x1 - 0.02, y1, h - 0.03)], "blk", out=(-1, 0, 0))
        m.face([(x0, y0, h - 0.03), (x1, y0, h - 0.03), (x1, y1, h - 0.03), (x0, y1, h - 0.03)], "blk", out=(0, 0, -1))
        m.face([(x0, y0, 0.06), (x1, y0, 0.06), (x1, y1, 0.06), (x0, y1, 0.06)], "blk", out=(0, 0, 1))
        m.face([(x0, y1 - 0.02, 0.06), (x1, y1 - 0.02, 0.06), (x1, y1 - 0.02, h - 0.03), (x0, y1 - 0.02, h - 0.03)], "blk", out=(0, -1, 0))
        # poteaux avant, traverses haute et basse
        for sx in (-1, 1):
            box(m, sx * (w / 2) - (0.045 if sx > 0 else 0), sx * (w / 2) + (0.045 if sx < 0 else 0), y0, y0 + 0.05, 0.02, h, "blk", skip="-z+z")
        box(m, x0 + 0.045, x1 - 0.045, y0, y0 + 0.05, h - 0.06, h, "blk", skip="+z")
        box(m, x0 + 0.045, x1 - 0.045, y0, y0 + 0.05, 0.02, 0.10, "blk", skip="-z")
        # montants 19" perforés
        for sx in (-1, 1):
            xa = sx * (W19 / 2 - 0.0155)
            m.face([(xa - 0.0155, yf + 0.001, zb - 0.01), (xa + 0.0155, yf + 0.001, zb - 0.01), (xa + 0.0155, yf + 0.001, h - 0.04), (xa - 0.0155, yf + 0.001, h - 0.04)],
                   "rail", st=[(0, 0), (0, 1), (h - 0.04 - zb, 1), (h - 0.04 - zb, 0)], out=(0, -1, 0))
            if d <= 1:
                xo = sx * (W19 / 2)
                m.face([(xo, yf + 0.001, zb - 0.01), (xo, yf + 0.08, zb - 0.01), (xo, yf + 0.08, h - 0.04), (xo, yf + 0.001, h - 0.04)], "blk", out=(sx, 0, 0))
                m.face([(xo, yf + 0.001, zb - 0.01), (xo, yf + 0.08, zb - 0.01), (xo, yf + 0.08, h - 0.04), (xo, yf + 0.001, h - 0.04)], "blk", out=(-sx, 0, 0))
    # socle et pieds vérins
    box(m, x0 + 0.01, x1 - 0.01, y0 + 0.01, y1 - 0.01, 0.02, 0.06, "blk", skip="+z" if d <= 2 else "+z-z")
    if d <= 1:
        for sx in (-1, 1):
            for sy in (-1, 1):
                with m.push(t=(sx * (w / 2 - 0.06), sy * (dp / 2 - 0.06), 0.0)):
                    lathe(m, [(0.028, 0.0), (0.028, 0.008), (0.008, 0.012), (0.008, 0.022)], "rail", n=8 if d == 0 else 5)
    if front == "perf":
        m.face([(x0, y0, 0.06), (x1, y0, 0.06), (x1, y0, h), (x0, y0, h)], "perf", uvs=[(0, 1), (1, 1), (1, 0), (0, 0)], out=(0, -1, 0))
    return yf, zb


def glass_door(m, x0, x1, y, z0, z1, frame=0.045):
    """Porte vitrée fumée dans le plan Y = y (face vers -Y) : cadre tôle, poignée, vitre (alpha, posée en dernier)."""
    d = m.d
    if d >= 4:
        return
    yy = y - 0.018
    for a, b, c, e in ((x0, x0 + frame, z0, z1), (x1 - frame, x1, z0, z1), (x0 + frame, x1 - frame, z0, z0 + frame), (x0 + frame, x1 - frame, z1 - frame, z1)):
        if d <= 1:
            box(m, a, b, yy, y, c, e, "blk")
        else:
            m.face([(a, yy, c), (b, yy, c), (b, yy, e), (a, yy, e)], "blk", out=(0, -1, 0))
    if d <= 1:
        box(m, x1 - frame * 0.8, x1 - frame * 0.25, yy - 0.014, yy, (z0 + z1) / 2 - 0.07, (z0 + z1) / 2 + 0.07, "tray", skip="+y")
    with m.push(sels=("glass",)):
        m.face([(x0 + frame, yy + 0.008, z0 + frame), (x1 - frame, yy + 0.008, z0 + frame), (x1 - frame, yy + 0.008, z1 - frame), (x0 + frame, yy + 0.008, z1 - frame)], "glass", out=(0, -1, 0))


def side_bundle(m, x, yf, z0, z1, n=4):
    """Torons verticaux de câbles le long d'un montant."""
    if m.d > 1:
        return
    for k in range(n):
        col = CABLES[(k * 3) % len(CABLES)]
        xx = x + (k % 2) * 0.012 - 0.006
        yy = yf + 0.03 + (k // 2) * 0.014
        cable(m, [(xx, yy, z0), (xx + m.rng.uniform(-0.004, 0.004), yy, (z0 + z1) / 2), (xx, yy, z1)], 0.006, col, n=4 if m.d == 0 else 3)


# --------------------------------------------------------------------------------------------------------------------
# Modèles
# --------------------------------------------------------------------------------------------------------------------
def rack_patch(d):
    m = Mesh(d, seed=421)
    rng = random.Random(77)      # mêmes voyants allumés à toutes les LOD
    yf, zb = rack_shell(m, front="glass")
    if d >= 4:
        return m
    # du bas (U1) vers le haut (U42)
    plan = [("ups2", 2), ("ups2", 2), ("pdu1", 1), ("blank1", 1), ("kvm1", 1), ("blank1", 1), ("srv1", 1), ("srv1", 1), ("blank1", 1),
            ("rtr1", 1), ("mgr1", 1), ("sw24", 1), ("patch24", 1), ("mgr1", 1), ("patch24", 1), ("sw48", 1), ("mgr1", 1),
            ("sw48", 1), ("patch24", 1), ("mgr1", 1), ("patch24", 1), ("patch24", 1), ("mgr1", 1), ("sw48", 1), ("patch24", 1),
            ("mgr1", 1), ("patch24", 1), ("sw48", 1), ("mgr1", 1), ("patch24", 1), ("patch24", 1), ("blank1", 1), ("pdu1", 1), ("blank1", 1),
            ("sw24", 1), ("mgr1", 1), ("patch24", 1), ("blank1", 1), ("blank1", 1), ("blank1", 1)]
    z = zb
    pos = []
    for kind, nu in plan:
        zc = device(m, kind, z, yf, depth={"ups2": 0.6, "srv1": 0.7, "pdu1": 0.1, "blank1": 0.0, "mgr1": 0.0, "patch24": 0.06, "kvm1": 0.5}.get(kind, 0.3), rng=rng)
        pos.append((kind, zc))
        z += nu * U
    # jarretières : chaque panneau de brassage vers le switch le plus proche, par le passe-câbles voisin
    if d <= 1:
        sws = [(k, zc) for k, zc in pos if k in ("sw48", "sw24")]
        mgrs = [zc for k, zc in pos if k == "mgr1"]
        pi = 0
        for kind, zc in pos:
            if kind != "patch24":
                continue
            sk, sz = min(sws, key=lambda s: abs(s[1] - zc))
            zm = min(mgrs, key=lambda q: abs(q - (zc + sz) / 2))
            ports = list(range(24))
            m.rng.shuffle(ports)
            cnt = 10 if d == 0 else 5
            col = CABLES[pi % 5] if pi % 4 != 3 else "c_orange"
            for i in sorted(ports[:cnt]):
                c = m.rng.randrange(24 if sk == "sw48" else 12)
                up = m.rng.random() < 0.5
                xa = -W19 / 2 + patch_x(i) / 1000.0
                xb = -W19 / 2 + sw_x(c) / 1000.0
                patch_cord(m, xa, zc - 0.004, xb, sz + (0.0085 if up else -0.0085), yf, zm, col if m.rng.random() < 0.8 else CABLES[m.rng.randrange(7)])
            pi += 1
    for sx in (-1, 1):
        side_bundle(m, sx * 0.27, yf, zb + 0.2, 1.95)
    glass_door(m, -0.3, 0.3, -0.5, 0.06, 2.0)
    return m


def rack_server(d):
    m = Mesh(d, seed=422)
    rng = random.Random(78)
    yf, zb = rack_shell(m, front="open")
    if d >= 4:
        return m
    plan = [("ups2", 2), ("ups2", 2), ("pdu1", 1), ("sto2", 2), ("sto2", 2), ("sto2", 2), ("blank1", 1), ("srv2", 2), ("srv2", 2),
            ("srv2", 2), ("srv2", 2), ("blank1", 1), ("kvm1", 1), ("srv1", 1), ("srv1", 1), ("srv1", 1), ("srv1", 1), ("srv1", 1),
            ("srv1", 1), ("srv1", 1), ("srv1", 1), ("blank1", 1), ("srv2", 2), ("srv2", 2), ("blank1", 1), ("mgr1", 1), ("sw48", 1),
            ("sw48", 1), ("patch24", 1), ("pdu1", 1), ("blank1", 1), ("blank1", 1)]
    z = zb
    top = []
    for kind, nu in plan:
        zc = device(m, kind, z, yf, depth={"ups2": 0.6, "srv1": 0.72, "srv2": 0.72, "sto2": 0.6, "pdu1": 0.1, "blank1": 0.0, "mgr1": 0.0, "patch24": 0.06, "kvm1": 0.5}.get(kind, 0.3), rng=rng)
        top.append((kind, zc))
        z += nu * U
    if d <= 1:
        sw = [zc for k, zc in top if k == "sw48"]
        zp = [zc for k, zc in top if k == "patch24"][0]
        zm = [zc for k, zc in top if k == "mgr1"][0]
        for i in range(10 if d == 0 else 5):
            xa = -W19 / 2 + patch_x(m.rng.randrange(24)) / 1000.0
            xb = -W19 / 2 + sw_x(m.rng.randrange(24)) / 1000.0
            patch_cord(m, xa, zp - 0.004, xb, sw[i % 2] + 0.0085, yf, zm, "c_aqua" if i % 3 == 0 else "c_blue")
    for sx in (-1, 1):
        side_bundle(m, sx * 0.27, yf, zb + 0.1, 1.95, n=3)
    return m


def wall_cab(d, nu=9):
    """Coffret mural : dos au mur (Y = 0), porte vitrée vers +Y. Origine au milieu du dos."""
    m = Mesh(d, seed=430 + nu)
    rng = random.Random(90 + nu)
    w, dp = 0.6, 0.45
    h = nu * U + 0.1
    r3.solid_box(m, -w / 2, w / 2, 0.0, dp, -h / 2, h / 2, "sSgfv")
    if d >= 4:
        box(m, -w / 2, w / 2, 0.0, dp, -h / 2, h / 2, {"+y": "blk", "*": "blk"}, skip="-y")
        return m
    box(m, -w / 2, w / 2, 0.0, dp, -h / 2, h / 2, "blk", skip="+y-y")
    if d <= 2:
        # intérieur
        for sx in (-1, 1):
            m.face([(sx * (w / 2 - 0.015), 0.02, -h / 2 + 0.015), (sx * (w / 2 - 0.015), dp, -h / 2 + 0.015), (sx * (w / 2 - 0.015), dp, h / 2 - 0.015), (sx * (w / 2 - 0.015), 0.02, h / 2 - 0.015)], "blk", out=(-sx, 0, 0))
        m.face([(-w / 2, 0.02, -h / 2 + 0.015), (w / 2, 0.02, -h / 2 + 0.015), (w / 2, dp, -h / 2 + 0.015), (-w / 2, dp, -h / 2 + 0.015)], "blk", out=(0, 0, 1))
        m.face([(-w / 2, 0.02, h / 2 - 0.015), (w / 2, 0.02, h / 2 - 0.015), (w / 2, dp, h / 2 - 0.015), (-w / 2, dp, h / 2 - 0.015)], "blk", out=(0, 0, -1))
        m.face([(-w / 2, 0.02, -h / 2), (w / 2, 0.02, -h / 2), (w / 2, 0.02, h / 2), (-w / 2, 0.02, h / 2)], "blk", out=(0, 1, 0))
    plan = {9: ["pdu1", "blank1", "rtr1", "mgr1", "sw24", "patch24", "mgr1", "patch24", "blank1"],
            12: ["pdu1", "blank1", "srv1", "rtr1", "mgr1", "sw24", "patch24", "mgr1", "sw48", "patch24", "patch24", "blank1"]}[nu]
    # équipements : construits face vers -Y puis retournés (façade à 7 cm derrière la porte)
    with m.push(R=rot("z", 180), t=(0.0, 0.0, 0.0)):
        yf = -(dp - 0.07)
        z = -nu * U / 2
        pos = []
        for kind in plan:
            zc = device(m, kind, z, yf, depth={"srv1": 0.3, "pdu1": 0.08, "blank1": 0.0, "mgr1": 0.0, "patch24": 0.05}.get(kind, 0.22), rng=rng)
            pos.append((kind, zc))
            z += U
        if d <= 2:
            for sx in (-1, 1):
                xa = sx * (W19 / 2 - 0.0155)
                m.face([(xa - 0.0155, yf + 0.001, -nu * U / 2), (xa + 0.0155, yf + 0.001, -nu * U / 2), (xa + 0.0155, yf + 0.001, nu * U / 2), (xa - 0.0155, yf + 0.001, nu * U / 2)],
                       "rail", st=[(0, 0), (0, 1), (nu * U, 1), (nu * U, 0)], out=(0, -1, 0))
        if d <= 1:
            sws = [(k, zc) for k, zc in pos if k in ("sw48", "sw24")]
            mgrs = [zc for k, zc in pos if k == "mgr1"]
            pi = 0
            for kind, zc in pos:
                if kind != "patch24":
                    continue
                sk, sz = min(sws, key=lambda s: abs(s[1] - zc))
                zm = min(mgrs, key=lambda q: abs(q - (zc + sz) / 2))
                for i in sorted(m.rng.sample(range(24), 9 if d == 0 else 4)):
                    c = m.rng.randrange(24 if sk == "sw48" else 12)
                    patch_cord(m, -W19 / 2 + patch_x(i) / 1000.0, zc - 0.004, -W19 / 2 + sw_x(c) / 1000.0, sz - 0.0085, yf, zm, CABLES[pi % 4] if m.rng.random() < 0.8 else "c_orange")
                pi += 1
        glass_door(m, -w / 2, w / 2, -dp, -h / 2, h / 2, frame=0.04)
    # arrivée des câbles : goulotte PVC montant au-dessus du coffret, boîtier de serrure
    if d <= 2:
        box(m, 0.14, 0.20, 0.0, 0.04, h / 2, h / 2 + 0.6, "pvc", skip="-z-y")
        box(m, -0.22, -0.18, 0.0, 0.025, h / 2, h / 2 + 0.6, "pvc", skip="-z-y")
    return m


def server_tower(d):
    m = Mesh(d, seed=440)
    w, dp, h = 0.2, 0.55, 0.45
    mats = {"-y": "tower_front", "+y": "tower_side", "+x": "tower_side", "-x": "tower_side", "*": "blk"}
    r3.solid_box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.0, h, "sSgfv")
    if d >= 2:
        box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.012, h, mats)
    else:
        box(m, -w / 2, w / 2, -dp / 2 + 0.018, dp / 2, 0.012, h, mats, skip="-y")
        box(m, -w / 2 - 0.004, w / 2 + 0.004, -dp / 2, -dp / 2 + 0.018, 0.008, h + 0.004, {"-y": "tower_front", "*": "blk"}, bevel=0.006 if d == 0 else 0.0)
        for sy in (-1, 1):
            box(m, -w / 2 - 0.012, w / 2 + 0.012, sy * (dp / 2 - 0.09) - 0.02, sy * (dp / 2 - 0.09) + 0.02, 0.0, 0.012, "c_black", skip="+z")
        cable(m, [(0.05, dp / 2, 0.38), (0.05, dp / 2 + 0.07, 0.3), (0.06, dp / 2 + 0.1, 0.02), (0.2, dp / 2 + 0.2, 0.006)], 0.005, "c_black", n=4 if d == 0 else 3)
        cable(m, [(-0.04, dp / 2, 0.2), (-0.04, dp / 2 + 0.06, 0.12), (-0.06, dp / 2 + 0.12, 0.006), (-0.25, dp / 2 + 0.16, 0.006)], 0.003, "c_blue", n=4 if d == 0 else 3)
    yf = -dp / 2
    led(m, 0.0, 0.012 + h * 0.085, yf, w=0.012, h=0.012, mat="led_b", sel="led3")
    if d <= 1:
        for k in range(4):
            z = 0.012 + h * (1 - (96 + k * 52 + 22) / 512.0)
            led(m, w / 2 - 0.047, z + 0.006, yf, w=0.004, h=0.003)
            led(m, w / 2 - 0.047, z - 0.006, yf, w=0.004, h=0.003, mat="led_a", sel="led2")
    return m


def ups_tower(d):
    m = Mesh(d, seed=441)
    w, dp, h = 0.19, 0.42, 0.33
    mats = {"-y": "ups_front", "*": "blk"}
    box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.01, h, mats, S="sSgfv", bevel=0.008 if d == 0 else 0.0)
    yf = -dp / 2
    zt = 0.01 + (h - 0.01)
    led(m, -0.008, zt - (h - 0.01) * (124 / 288.0), yf, w=0.008, h=0.008)
    if d <= 1:
        led(m, 0.0, zt - (h - 0.01) * (78 / 288.0), yf + 0.0003, w=0.10, h=0.05, mat="led_b", sel="led3")
        # cordons à l'arrière
        cable(m, [(0.04, dp / 2, 0.08), (0.04, dp / 2 + 0.06, 0.05), (0.06, dp / 2 + 0.16, 0.008), (0.2, dp / 2 + 0.22, 0.008)], 0.005, "c_black", n=4 if d == 0 else 3)
    return m


def switch_desk(d):
    m = Mesh(d, seed=442)
    w, dp, h = 0.44, 0.26, 0.044
    mats = {"-y": "rtr_front", "+z": "rtr_top", "*": "blk"}
    box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.006, 0.006 + h, mats, S="sSgfv")
    yf = -dp / 2
    for k in range(8):
        if d == 0 and m.rng.random() < 0.75:
            led(m, -w / 2 + (60 + k * 30) / 440.0 * w, 0.006 + h * 0.80, yf, w=0.004, h=0.002)
    led(m, -w / 2 + 340 / 440.0 * w, 0.006 + h * 0.4, yf, w=0.014, h=0.012, mat="led_b", sel="led3")
    if d >= 1:
        led(m, -0.07, 0.006 + h * 0.80, yf, w=0.2, h=0.002)
    if d <= 1:
        for x in (-0.17, 0.0, 0.17):
            tube(m, (x, dp / 2 - 0.012, 0.03), (x, dp / 2 + 0.012, 0.03), 0.007, "blk", n=6 if d == 0 else 4, caps=(False, False))
            tube(m, (x, dp / 2 + 0.012, 0.03), (x + 0.02 * (1 if x > 0 else -1 if x < 0 else 0), dp / 2 + 0.03, 0.21), 0.006, "blk", n=6 if d == 0 else 4, r1=0.0045)
        for sx in (-1, 1):
            for sy in (-1, 1):
                box(m, sx * 0.19 - 0.012, sx * 0.19 + 0.012, sy * 0.1 - 0.012, sy * 0.1 + 0.012, 0.0, 0.006, "c_black", skip="+z")
        cable(m, [(-0.12, dp / 2, 0.02), (-0.12, dp / 2 + 0.05, 0.012), (-0.2, dp / 2 + 0.12, 0.004), (-0.36, dp / 2 + 0.14, 0.004)], 0.0035, "c_blue", n=4 if d == 0 else 3)
    return m


def tray_path(m, path, width=0.3, cables=True, seed=0, sides=(-1, 1), rungs=True):
    """Chemin de câbles en échelle le long d'une polyligne horizontale (z = 0 : dessous des longerons) : deux
    longerons en U de 60 mm, barreaux tous les 250 mm, câbles couchés dedans."""
    d = m.d
    rr = random.Random(seed)
    n = len(path)

    def offs(i, o):
        """Point décalé de o à gauche de la polyligne au sommet i (onglet aux coudes)."""
        p = path[i]
        d0 = r3.vnorm(r3.vsub(p, path[i - 1])) if i > 0 else None
        d1 = r3.vnorm(r3.vsub(path[i + 1], p)) if i < n - 1 else None
        dv = d0 or d1
        if d0 and d1:
            b = r3.vnorm(r3.vadd(d0, d1))
            nb = (-b[1], b[0], 0.0)
            k = 1.0 / max(0.3, r3.vdot(nb, (-d0[1], d0[0], 0.0)))
            return (p[0] + nb[0] * o * k, p[1] + nb[1] * o * k, p[2])
        return (p[0] - dv[1] * o, p[1] + dv[0] * o, p[2])
    for i in range(n - 1):
        for sgn in sides:
            a, b = offs(i, sgn * width / 2), offs(i + 1, sgn * width / 2)
            if d <= 2:
                beam(m, (a[0], a[1], 0.03), (b[0], b[1], 0.03), 0.012 if d <= 1 else 0.02, 0.06, "tray", a=(-(b[1] - a[1]), b[0] - a[0], 0), caps=False)
            else:
                m.face([(a[0], a[1], 0.0), (b[0], b[1], 0.0), (b[0], b[1], 0.06), (a[0], a[1], 0.06)], "tray")
                m.face([(b[0], b[1], 0.0), (a[0], a[1], 0.0), (a[0], a[1], 0.06), (b[0], b[1], 0.06)], "tray")
        a, b = path[i], path[i + 1]
        L = r3.vlen(r3.vsub(b, a))
        dv = r3.vnorm(r3.vsub(b, a))
        nv = (-dv[1], dv[0], 0.0)
        if d <= 2 and rungs:
            k = int(L / 0.25)
            step = 1 if d <= 1 else 2
            for j in range(0, k + 1, step):
                c = r3.vadd(a, r3.vmul(dv, min(L - 0.02, 0.02 + j * (L - 0.04) / max(1, k))))
                beam(m, (c[0] - nv[0] * width / 2, c[1] - nv[1] * width / 2, 0.012), (c[0] + nv[0] * width / 2, c[1] + nv[1] * width / 2, 0.012), 0.03, 0.012, "tray", a=dv, caps=False)
        if d >= 3 and rungs:
            a0, a1, b0, b1 = offs(i, -width / 2), offs(i, width / 2), offs(i + 1, -width / 2), offs(i + 1, width / 2)
            m.face([(a0[0], a0[1], 0.02), (b0[0], b0[1], 0.02), (b1[0], b1[1], 0.02), (a1[0], a1[1], 0.02)], "c_grey", out=(0, 0, 1))
            m.face([(a0[0], a0[1], 0.0), (b0[0], b0[1], 0.0), (b1[0], b1[1], 0.0), (a1[0], a1[1], 0.0)], "tray", out=(0, 0, -1))
        r3.solid_hull8(m, [offs(i, -width / 2), offs(i + 1, -width / 2), offs(i + 1, width / 2), offs(i, width / 2)],
                       [r3.vadd(q, (0, 0, 0.07)) for q in (offs(i, -width / 2), offs(i + 1, -width / 2), offs(i + 1, width / 2), offs(i, width / 2))], "sSgf")
    if cables and d <= 2:
        cnt = (14, 8, 4)[d]
        for k in range(cnt):
            o = (k + 0.5) / cnt * (width - 0.06) - (width - 0.06) / 2 + rr.uniform(-0.004, 0.004)
            r = 0.0045 + 0.0025 * (k % 3)
            z = 0.018 + r + (0.011 if k % 3 == 1 else 0.0)
            pts = []
            for i in range(n):
                q = offs(i, o)
                pts.append((q[0], q[1], z))
            cable(m, pts, r if d <= 1 else r * 1.6, ("c_black", "c_blue", "c_grey", "c_blue", "c_black", "c_orange", "c_grey", "c_blue", "c_yellow", "c_black", "c_aqua", "c_grey", "c_green", "c_blue")[k % 14], n=(6, 4, 3)[d])


def tray_straight(d):
    m = Mesh(d, seed=450)
    tray_path(m, [(-1.0, 0, 0), (1.0, 0, 0)], seed=1)
    return m


def tray_corner(d):
    m = Mesh(d, seed=451)
    tray_path(m, [(0, -0.5, 0), (0, -0.12, 0), (0.12, 0.0, 0), (0.5, 0, 0)], seed=1)
    return m


def tray_tee(d):
    """Té : le longeron du chemin principal est ouvert au droit du piquage (les câbles y passent)."""
    m = Mesh(d, seed=452)
    tray_path(m, [(-0.5, 0, 0), (0.5, 0, 0)], seed=1, sides=(1,))
    tray_path(m, [(-0.5, 0, 0), (-0.15, 0, 0)], seed=1, sides=(-1,), rungs=False, cables=False)
    tray_path(m, [(0.15, 0, 0), (0.5, 0, 0)], seed=1, sides=(-1,), rungs=False, cables=False)
    tray_path(m, [(0, -0.5, 0), (0, -0.15, 0)], seed=1, cables=False)
    if d <= 2:
        cols = ("c_blue", "c_grey", "c_black", "c_blue", "c_orange")[: (5, 3, 2)[d]]
        for k, col in enumerate(cols):
            x = (k - (len(cols) - 1) / 2) * 0.04
            yy = -0.09 + k * 0.035
            cable(m, [(x, -0.5, 0.036), (x, -0.24, 0.036), (x + 0.1, yy - 0.02, 0.04), (0.28, yy, 0.042), (0.5, yy, 0.042)], 0.006, col, n=(6, 4, 3)[d])
    return m


def trunk(m, pts, w=0.06, t=0.04):
    """Goulotte PVC murale (dos au mur Y = 0, sortant vers +Y) le long d'une polyligne du plan XZ."""
    d = m.d
    for a, b in zip(pts, pts[1:]):
        dv = r3.vnorm(r3.vsub(b, a))
        e = (0.0, 1.0, 0.0)
        beam(m, (a[0], t / 2, a[2]), (b[0], t / 2, b[2]), w, t, "pvc", a=r3.vcross(e, dv), caps=True, S="sSgf")
        if d <= 1:
            # couvercle clipsé en léger relief, colliers de fixation
            L = r3.vlen(r3.vsub(b, a))
            k = max(1, int(L / 0.5))
            for j in range(k + 1):
                c = r3.vadd(a, r3.vmul(dv, 0.04 + j * (L - 0.08) / k))
                beam(m, (c[0] - dv[0] * 0.006, t + 0.0005, c[2] - dv[2] * 0.006), (c[0] + dv[0] * 0.006, t + 0.0005, c[2] + dv[2] * 0.006), w + 0.004, 0.003, "gry", a=r3.vcross(e, dv), caps=False)


def trunk_straight(d):
    m = Mesh(d, seed=460)
    trunk(m, [(-1.0, 0, 0), (1.0, 0, 0)])
    return m


def trunk_corner(d):
    m = Mesh(d, seed=461)
    trunk(m, [(-0.5, 0, 0), (0.03, 0, 0)])
    trunk(m, [(0, 0, -0.03), (0, 0, -0.5)])
    return m


def front_slab(m, x0, x1, z0, z1, yf, mat, W, H, th=0.012, zb=0.0):
    """Panneau de porte en saillie de th devant la façade : sa face reprend la partie de la texture « mat » qu'il
    recouvre (façade complète : largeur W, hauteur H depuis zb)."""
    def uv(x, z):
        return ((x + W / 2) / W, 1 - (z - zb) / H)
    y = yf - th
    m.face([(x0, y, z0), (x1, y, z0), (x1, y, z1), (x0, y, z1)], mat, uvs=[uv(x0, z0), uv(x1, z0), uv(x1, z1), uv(x0, z1)], out=(0, -1, 0))
    for pts, out in (([(x0, y, z0), (x0, yf, z0), (x0, yf, z1), (x0, y, z1)], (-1, 0, 0)), ([(x1, y, z0), (x1, yf, z0), (x1, yf, z1), (x1, y, z1)], (1, 0, 0)),
                     ([(x0, y, z1), (x1, y, z1), (x1, yf, z1), (x0, yf, z1)], (0, 0, 1)), ([(x0, y, z0), (x1, y, z0), (x1, yf, z0), (x0, yf, z0)], (0, 0, -1))):
        m.face(pts, "gry", out=out)


def crac(d):
    """Climatiseur de salle (armoire à soufflage) : deux portes en saillie, afficheur, socle, raccords en toiture."""
    m = Mesh(d, seed=470)
    w, dp, h = 1.0, 0.85, 1.95
    yf = -dp / 2
    r3.solid_box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.0, h, "sSgfv")
    if d >= 2:
        box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.0, h, {"-y": "crac_front", "side": "gry", "+z": "rack_top", "*": "gry"}, skip="-z")
    else:
        zs = 0.12
        box(m, -w / 2 + 0.02, w / 2 - 0.02, -dp / 2 + 0.03, dp / 2 - 0.02, 0.0, zs, "blk", skip="-z+z")          # socle en retrait
        box(m, -w / 2, w / 2, -dp / 2 + 0.014, dp / 2, zs, h, {"+z": "rack_top", "-y": "blk", "*": "gry"})
        for x0, x1 in ((-w / 2 + 0.004, -0.003), (0.003, w / 2 - 0.004)):
            front_slab(m, x0, x1, zs + 0.004, h - 0.004, yf + 0.014, "crac_front", w, h, th=0.014)
        # poignée
        box(m, -w / 2 + w * 0.44, -w / 2 + w * 0.48, yf - 0.03, yf, h * (1 - 420 / 704.0), h * (1 - 300 / 704.0), "blk", skip="+y")
        if d == 0:
            for sx in (-1, 1):
                for sy in (-1, 1):
                    with m.push(t=(sx * (w / 2 - 0.08), sy * (dp / 2 - 0.08), 0.0)):
                        lathe(m, [(0.03, -0.0), (0.03, 0.008), (0.01, 0.012)], "rail", n=8)
    if d <= 2:
        # afficheur allumé, voyants marche / défaut
        led(m, -w / 2 + w * (0.585 + 0.80) / 2, h * (1 - 334 / 704.0), yf, w=w * 0.215, h=h * 56 / 704.0, mat="led_b", sel="led3")
        led(m, -w / 2 + w * 0.60, h * (1 - 380 / 704.0), yf, w=0.02, h=0.012)
        led(m, -w / 2 + w * 0.66, h * (1 - 380 / 704.0), yf, w=0.02, h=0.012, mat="led_a", sel="led2")
    if d <= 1:
        # raccords frigorifiques calorifugés, évacuation des condensats, caisson de reprise d'air en toiture
        box(m, -0.36, 0.36, -0.2, 0.3, h, h + 0.1, "gry", skip="-z")
        box(m, -0.33, 0.33, -0.17, 0.27, h + 0.1, h + 0.104, "perf", skip="-z")
        for x, r, col in ((-0.44, 0.022, "c_black"), (-0.44, 0.014, "c_orange")):
            yy = 0.36 if r > 0.02 else 0.3
            tube(m, (x, yy, h), (x, yy, h + 0.5), r, col, n=8 if d == 0 else 5, caps=(False, False))
            if d == 0:
                with m.push(t=(x, yy, h)):
                    lathe(m, [(r + 0.012, 0.0), (r + 0.012, 0.01), (r, 0.014)], "tray", n=8)
    return m


def elec_cabinet(d):
    """Armoire électrique (TGBT) : porte en saillie, charnières, poignée, trois voyants, arrêt d'urgence, goulotte."""
    m = Mesh(d, seed=471)
    w, dp, h = 0.8, 0.3, 1.9
    yf = -dp / 2
    r3.solid_box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.0, h, "sSgfv")
    if d >= 2:
        box(m, -w / 2, w / 2, -dp / 2, dp / 2, 0.0, h, {"-y": "elec_front", "*": "gry"}, skip="-z")
    else:
        box(m, -w / 2 + 0.015, w / 2 - 0.015, -dp / 2 + 0.03, dp / 2 - 0.01, 0.0, 0.1, "blk", skip="-z+z")
        box(m, -w / 2, w / 2, -dp / 2 + 0.016, dp / 2, 0.1, h, "gry")
        front_slab(m, -w / 2 + 0.006, w / 2 - 0.006, 0.106, h - 0.006, yf + 0.016, "elec_front", w, h, th=0.016)
        box(m, w / 2 - 0.058, w / 2 - 0.03, yf - 0.03, yf, h * 0.40, h * 0.54, "blk", skip="+y")                      # poignée
        for zz in (0.14, 0.5, 0.88):
            box(m, -w / 2 - 0.006, -w / 2 + 0.012, yf - 0.006, yf + 0.02, h * zz - 0.035, h * zz + 0.035, "tray")       # charnières
        for k in range(3):
            x = -w / 2 + w * (0.3 + 0.2 * k)
            tube(m, (x, yf, h * 0.34), (x, yf - 0.014, h * 0.34), 0.017, "tray", n=10 if d == 0 else 6, caps=(False, False), a=(1, 0, 0))
        tube(m, (0.0, yf, h * 0.19), (0.0, yf - 0.02, h * 0.19), 0.03, "blk", n=10 if d == 0 else 6, caps=(False, False), a=(1, 0, 0))
        tube(m, (0.0, yf - 0.02, h * 0.19), (0.0, yf - 0.042, h * 0.19), 0.024, "c_red", n=10 if d == 0 else 6, caps=(False, True), a=(1, 0, 0), r1=0.02)
    if d <= 2:
        for k, (mat, sel) in enumerate((("led_g", "led"), ("led_a", "led2"), ("led_b", "led3"))):
            x = -w / 2 + w * (0.3 + 0.2 * k)
            if d <= 1:
                with m.push(sels=(sel,)):
                    tube(m, (x, yf - 0.014, h * 0.34), (x, yf - 0.022, h * 0.34), 0.013, mat, n=10 if d == 0 else 6, caps=(False, True), a=(1, 0, 0), r1=0.009)
            else:
                led(m, x, h * 0.34, yf, w=0.024, h=0.024, mat=mat, sel=sel)
    if d <= 1:
        # départs de câbles en goulotte au-dessus, larmier
        box(m, -0.25, 0.25, -0.03, 0.07, h, h + 0.5, "pvc", skip="-z")
        box(m, -w / 2 - 0.004, w / 2 + 0.004, yf - 0.004, dp / 2 + 0.004, h, h + 0.02, "gry", skip="-z")
    return m


MODELS = {
    # nom : (constructeur, masse kg, nombre de LOD visuelles)
    "infra_rack_patch": (rack_patch, 260.0, 5), "infra_rack_server": (rack_server, 520.0, 5),
    "infra_wall_9u": (lambda d: wall_cab(d, 9), 38.0, 4), "infra_wall_12u": (lambda d: wall_cab(d, 12), 52.0, 4),
    "infra_server_tower": (server_tower, 24.0, 3), "infra_ups": (ups_tower, 19.0, 3), "infra_switch_desk": (switch_desk, 2.5, 3),
    "infra_tray_straight": (tray_straight, 14.0, 4), "infra_tray_corner": (tray_corner, 8.0, 4), "infra_tray_tee": (tray_tee, 9.0, 4),
    "infra_trunk_straight": (trunk_straight, 2.0, 3), "infra_trunk_corner": (trunk_corner, 1.0, 3),
    "infra_crac": (crac, 340.0, 4), "infra_elec_cabinet": (elec_cabinet, 110.0, 4),
}
SECTIONS = ("led", "led2", "led3", "glass")


def finish(m):
    """Faces alpha (verre) en fin de LOD : le moteur les trie après l'opaque."""
    m.faces = [f for f in m.faces if f[3] != "glass"] + [f for f in m.faces if f[3] == "glass"]
    return m


def write_model_cfg():
    lines = ["// Infrastructure réseau (tools/model_net_infra.py) : voyants et vitres retexturables (hiddenSelections).",
             "class CfgSkeletons {", "    class Default { isDiscrete=1; skeletonInherit=\"\"; skeletonBones[]={}; };", "};",
             "class CfgModels {", "    class Default { sectionsInherit=\"\"; sections[]={}; skeletonName=\"\"; };",
             "    class comspec_infra_model: Default { sections[]={%s}; };" % ",".join('"%s"' % s for s in SECTIONS)]
    lines += ["    class %s: comspec_infra_model {};" % n for n in MODELS]
    lines.append("};")
    with open(os.path.join(DATA, "model.cfg"), "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(lines) + "\n")


def main():
    os.makedirs(DATA, exist_ok=True)
    obj_dir = os.environ.get("OBJ_DIR", "")
    tmp = obj_dir or tempfile.mkdtemp(prefix="infra_tex_")
    png = {}
    if os.environ.get("NO_TEX"):
        declare(paint=False)
    else:
        at = declare(paint=True)
        p = at.save(os.path.join(tmp, "tex"), nstrength=3.5)
        png[at.tex] = p
        if not os.environ.get("NO_PAA"):
            def small(src, size):
                dst = src.replace(".png", "_s.png")
                Image.open(src).resize(size, Image.LANCZOS).save(dst)
                return dst
            # couleur 2048 (étiquettes et prises lisibles de près), normales et spéculaire 1024
            r3.to_paa(p["co"], os.path.join(DATA, "infra_co.paa"))
            r3.to_paa(small(p["nohq"], (1024, 1024)), os.path.join(DATA, "infra_nohq.paa"))
            r3.to_paa(small(p["smdi"], (1024, 1024)), os.path.join(DATA, "infra_smdi.paa"))
    write_rvmats()
    report = {}
    only = [s for s in os.environ.get("ONLY", "").split(",") if s]
    for name, (fn, mass, lods) in MODELS.items():
        if only and not any(o in name for o in only):
            continue
        m0 = r3.write_p3d(os.path.join(DATA, name + ".p3d"), lambda d, fn=fn: finish(fn(d)), mass, (("prefershadowvolume", "1"), ("sbsource", "shadowvolume")),
                          lods=lods, shadow_from=min(2, lods - 1), report=report)
        r3.check_mesh(m0, name)
        st = report[name + ".p3d"]
        print("%-22s %-34s sections %s  %.2f Mo" % (name, " / ".join("%d" % t for _, _, t, _ in st["lods"]), "/".join("%d" % s for _, _, _, s in st["lods"]), st["size"] / 1e6))
        if obj_dir:
            r3.export_obj(m0, os.path.join(obj_dir, name + ".obj"), png)
    if not only:
        write_model_cfg()
    with open(os.path.join(tmp, "report_infra.json"), "w") as f:
        json.dump(report, f, indent=1)
    print("rapport :", os.path.join(tmp, "report_infra.json"))


if __name__ == "__main__":
    main()
