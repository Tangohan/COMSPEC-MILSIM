#!/usr/bin/env python3
"""
Symboles militaires (MIL-STD-2525D / APP-6D) et curseurs GPS du téléphone ATAK, dessinés ici (Pillow, aucune image tierce).

    python gen_milstd.py                  PNG dans un dossier temporaire, puis PAA (ImageToPAA de BI, sinon HEMTT) dans
                                          Sources/addons/items/data/milstd ; l'ancien PAA est supprimé avant chaque conversion
    python gen_milstd.py --png DOSSIER    garde aussi les PNG
    python gen_milstd.py --sheet FICHIER  planche de contrôle (PNG) en couleurs, sans conversion

Toutes les textures sont blanches sur fond transparent (suffixe _ca : DXT5 avec alpha) et partagent le même carré de
128 px, qui reprend l'espace de 200 x 200 unités de la norme (cadre ami de 25,50 à 175,150, centre 100,100) : le jeu
superpose les couches (fond, contour, pictogramme, échelon, hampe de QG, barre d'état, flèche de cap) au même point et à
la même taille, chacune colorée par drawIcon (couleurs par thème : clair, sombre, nuit ; fn_milDraw). Aucune texture par
thème : la couleur vient du jeu.

Couches :
  f_<cadre>_fill / _line / _dash   fond, contour plein, contour en tirets (prévu ; supposé ami, suspect)
  p_<picto>_<cadre>                pictogramme découpé à l'intérieur du cadre (traits qui vont d'un bord à l'autre)
  e_<échelon>_<cadre>              marque d'échelon au-dessus du cadre (cadres terrestres)
  hq_<cadre>, bar_<cadre>          hampe de poste de commandement, barre d'état (capacité réduite, détruit)
  dir                              flèche de direction du mouvement (tournée par le jeu)
  g_*                              curseurs GPS (flèche routière, chevron et cône, rose des vents, réticule, point)
Cadres : fl hl nl ul (terrestre ami, hostile, neutre, inconnu), fa ha na ua (air, ouverts en bas), fs (surface ami ;
hostile, neutre et inconnu de surface ont le cadre terrestre, comme dans la norme).
"""
import math
import os
import re
import shutil
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "Sources", "addons", "items", "data", "milstd")
S = 128
K = 4
B = S * K
U = B / 200.0  # unité de la norme -> pixel suréchantillonné

LINE = 8  # contour du cadre (unités)
PEN = 7  # traits des pictogrammes, échelons, hampe

FONT = None
for f in (r"C:\Windows\Fonts\arialbd.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"):
    if os.path.exists(f):
        FONT = f
        break


# ---------------------------------------------------------------- chemins (M L C Z, coordonnées absolues)
def path_points(d):
    """Chemin SVG simple -> liste de sous-chemins [(points, fermé)] en unités de la norme."""
    toks = re.findall(r"[MLHVCZmlhvcz]|-?\d+(?:\.\d+)?", d)
    subs, cur, i, cmd = [], [], 0, None
    pos = (0.0, 0.0)
    while i < len(toks):
        t = toks[i]
        if t.isalpha():
            cmd = t
            i += 1
            if cmd in "Zz":
                if cur:
                    subs.append((cur, True))
                cur = []
                continue
        if cmd == "M":
            if cur:
                subs.append((cur, False))
            pos = (float(toks[i]), float(toks[i + 1]))
            cur = [pos]
            i += 2
            cmd = "L"
        elif cmd == "L":
            pos = (float(toks[i]), float(toks[i + 1]))
            cur.append(pos)
            i += 2
        elif cmd == "H":
            pos = (float(toks[i]), pos[1])
            cur.append(pos)
            i += 1
        elif cmd == "V":
            pos = (pos[0], float(toks[i]))
            cur.append(pos)
            i += 1
        elif cmd == "C":
            p0 = pos
            p1 = (float(toks[i]), float(toks[i + 1]))
            p2 = (float(toks[i + 2]), float(toks[i + 3]))
            p3 = (float(toks[i + 4]), float(toks[i + 5]))
            for k in range(1, 25):
                s = k / 24
                a = (1 - s) ** 3
                b = 3 * (1 - s) ** 2 * s
                c = 3 * (1 - s) * s * s
                e = s ** 3
                cur.append((a * p0[0] + b * p1[0] + c * p2[0] + e * p3[0], a * p0[1] + b * p1[1] + c * p2[1] + e * p3[1]))
            pos = p3
            i += 6
        else:
            raise ValueError(d)
    if cur:
        subs.append((cur, False))
    return subs


def px(pts):
    return [(x * U, y * U) for x, y in pts]


def new():
    return Image.new("L", (B, B), 0)


def stroke(img, pts, w=PEN, closed=False):
    d = ImageDraw.Draw(img)
    p = px(pts + ([pts[0]] if closed else []))
    d.line(p, fill=255, width=max(1, int(round(w * U))), joint="curve")
    r = w * U / 2
    # Bouts et angles arrondis.
    for x, y in p:
        d.ellipse((x - r, y - r, x + r, y + r), fill=255)


def stroke_path(img, d, w=PEN):
    for pts, closed in path_points(d):
        stroke(img, pts, w, closed)


def fill_path(img, d):
    dr = ImageDraw.Draw(img)
    for pts, _ in path_points(d):
        dr.polygon(px(pts), fill=255)


def circle(img, cx, cy, r, w=None):
    d = ImageDraw.Draw(img)
    box = ((cx - r) * U, (cy - r) * U, (cx + r) * U, (cy + r) * U)
    if w is None:
        d.ellipse(box, fill=255)
    else:
        d.ellipse(box, outline=255, width=max(1, int(round(w * U))))


def dashes(pts, on=15, off=10, closed=True):
    """Tirets le long d'un tracé (longueurs en unités)."""
    seq = pts + ([pts[0]] if closed else [])
    out, cur, acc, drawing = [], [seq[0]], 0.0, True
    for a, b in zip(seq, seq[1:]):
        seg = math.dist(a, b)
        t0 = 0.0
        while seg - t0 > 1e-6:
            lim = on if drawing else off
            step = min(lim - acc, seg - t0)
            t1 = t0 + step
            p = (a[0] + (b[0] - a[0]) * t1 / seg, a[1] + (b[1] - a[1]) * t1 / seg)
            if drawing:
                cur.append(p)
            acc += step
            t0 = t1
            if acc >= lim - 1e-6:
                if drawing and len(cur) > 1:
                    out.append(cur)
                drawing = not drawing
                acc = 0.0
                cur = [p]
    if drawing and len(cur) > 1:
        out.append(cur)
    return out


def text(img, s, cx, cy, h):
    if FONT is None:
        return
    f = ImageFont.truetype(FONT, int(h * U))
    d = ImageDraw.Draw(img)
    d.text((cx * U, cy * U), s, font=f, fill=255, anchor="mm")


def mask_and(a, b):
    from PIL import ImageChops
    return ImageChops.multiply(a, b)


def mask_sub(a, b):
    from PIL import ImageChops
    return ImageChops.subtract(a, b)


# ---------------------------------------------------------------- cadres (espace 200 x 200 de la norme)
# [contour, fermé, boîte des traits d'un bord à l'autre (x0, y0, x1, y1), haut, bas, gauche (hampe)]
FRAMES = {
    "fl": ("M25,50 L175,50 L175,150 L25,150 Z", (25, 50, 175, 150), 50, 150, (25, 150)),
    "hl": ("M100,28 L172,100 L100,172 L28,100 Z", (28, 28, 172, 172), 28, 172, (28, 100)),
    "nl": ("M45,45 L155,45 L155,155 L45,155 Z", (45, 45, 155, 155), 45, 155, (45, 155)),
    "ul": ("M63,63 C63,20 137,20 137,63 C180,63 180,137 137,137 C137,180 63,180 63,137 C20,137 20,63 63,63 Z",
           (40, 40, 160, 160), 32, 168, (31, 100)),
    # Air : ouverts en bas (2525D).
    "fa": ("M25,150 C25,33 175,33 175,150", (40, 70, 160, 150), 62, 150, (25, 150)),
    "ha": ("M45,150 L45,70 L100,20 L155,70 L155,150", (45, 45, 155, 150), 20, 150, (45, 150)),
    "na": ("M45,150 L45,30 L155,30 L155,150", (45, 30, 155, 150), 30, 150, (45, 150)),
    "ua": ("M65,150 C10,150 15,60 65,75 C70,45 130,45 135,75 C185,60 190,150 135,150", (45, 50, 155, 150), 50, 150, (40, 150)),
    # Surface ami : cercle.
    "fs": ("M100,40 C133,40 160,67 160,100 C160,133 133,160 100,160 C67,160 40,133 40,100 C40,67 67,40 100,40 Z",
           (58, 58, 142, 142), 40, 160, (40, 100)),
}
LAND = ["fl", "hl", "nl", "ul"]
AIR = ["fa", "ha", "na", "ua"]


def frame_fill(key):
    img = new()
    d = FRAMES[key][0]
    for pts, _ in path_points(d):
        ImageDraw.Draw(img).polygon(px(pts), fill=255)
    return img


def frame_line(key, dashed=False):
    img = new()
    for pts, closed in path_points(FRAMES[key][0]):
        if dashed:
            for seg in dashes(pts, closed=closed):
                stroke(img, seg, LINE)
        else:
            stroke(img, pts, LINE, closed)
    return img


def interior(key):
    """Intérieur du cadre, trait du contour retiré : zone où le pictogramme peut aller."""
    return mask_sub(frame_fill(key), frame_line(key))


# ---------------------------------------------------------------- pictogrammes
def p_inf_exact(img, b):
    x0, y0, x1, y1 = b
    stroke(img, [(x0, y0), (x1, y1)])
    stroke(img, [(x0, y1), (x1, y0)])


def p_arm(img, b):
    stroke_path(img, "M75,80 L125,80 C152,80 152,120 125,120 L75,120 C48,120 48,80 75,80 Z")


def p_rec(img, b):
    x0, y0, x1, y1 = b
    stroke(img, [(x0, y1), (x1, y0)])


def p_art(img, b):
    circle(img, 100, 100, 16)


def p_mor(img, b):
    circle(img, 100, 116, 11, PEN)
    stroke(img, [(100, 104), (100, 74)])
    fill_path(img, "M100,62 L91,80 L109,80 Z")


def p_eng(img, b):
    stroke_path(img, "M60,118 L60,83 L140,83 L140,118 M100,83 L100,110")


def p_sig(img, b):
    x0, y0, x1, y1 = b
    stroke(img, [(x0, y0), (100, 108), (100, 92), (x1, y1)])


def p_med(img, b):
    x0, y0, x1, y1 = b
    stroke(img, [(100, y0), (100, y1)])
    stroke(img, [(x0, 100), (x1, 100)])


def p_sup(img, b):  # maintenance (soutien)
    stroke_path(img, "M70,88 C83,88 83,112 70,112 M80,100 L120,100 M130,88 C117,88 117,112 130,112")


def p_log(img, b):  # ravitaillement (logistique) : trait bas d'un bord à l'autre
    x0, y0, x1, y1 = b
    y = y1 - (y1 - y0) * 0.2
    stroke(img, [(x0, y), (x1, y)])


def p_avf(img, b):  # aviation, voilure fixe
    fill_path(img, "M100,100 L128,87 C147,87 147,113 128,113 Z M100,100 L72,87 C53,87 53,113 72,113 Z")


def p_avr(img, b):  # aviation, voilure tournante
    fill_path(img, "M58,84 L100,100 L142,84 L142,116 L100,100 L58,116 Z")


def p_uav(img, b):
    fill_path(img, "M58,82 L100,104 L142,82 L142,96 L100,118 L58,96 Z")


def p_sni(img, b):
    stroke(img, [(60, 86), (88, 86)])
    stroke(img, [(112, 86), (140, 86)])
    stroke(img, [(100, 92), (100, 118)])


def p_at(img, b):
    x0, y0, x1, y1 = b
    stroke(img, [(x0, y1), (100, y0), (x1, y1)])


def p_ad(img, b):
    x0, y0, x1, y1 = b
    h = (y1 - y0) * 0.42
    stroke_path(img, "M%f,%f C%f,%f %f,%f %f,%f" % (x0, y1, x0, y1 - h, x1, y1 - h, x1, y1))


def p_mot(img, b):
    p_inf_exact(img, b)
    x0, y0, x1, y1 = b
    stroke(img, [(100, y0), (100, y1)])


def p_txt(s, h):
    return lambda img, b: text(img, s, 100, 101, h)


PICTOS = {
    "inf": p_inf_exact,
    "mech": lambda img, b: (p_inf_exact(img, b), p_arm(img, b)),
    "arm": p_arm,
    "rec": p_rec,
    "art": p_art,
    "mor": p_mor,
    "eng": p_eng,
    "sig": p_sig,
    "med": p_med,
    "c2": p_txt("C2", 40),
    "sup": p_sup,
    "log": p_log,
    "avf": p_avf,
    "avr": p_avr,
    "uav": p_uav,
    "sni": p_sni,
    "at": p_at,
    "sof": p_txt("SF", 40),
    "jtac": p_txt("JFS", 32),
    "mp": p_txt("MP", 40),
    "civ": p_txt("CIV", 32),
    "mot": p_mot,
    "ad": p_ad,
}
AIR_PICTOS = ["avf", "avr", "uav"]


def picto(name, key):
    img = new()
    PICTOS[name](img, FRAMES[key][1])
    return mask_and(img, interior(key))


# ---------------------------------------------------------------- échelons, hampe, barre, direction
def echelon(code, key):
    img = new()
    t = FRAMES[key][2] - 8  # bas de la marque
    if code == "tm":
        circle(img, 100, t - 13, 10, PEN)
        stroke(img, [(86, t + 1), (114, t - 27)])
    elif code in ("sq", "sc", "pl"):
        n = {"sq": 1, "sc": 2, "pl": 3}[code]
        for i in range(n):
            circle(img, 100 + (i - (n - 1) / 2) * 22, t - 10, 8)
    elif code in ("co", "bn", "rg"):
        n = {"co": 1, "bn": 2, "rg": 3}[code]
        for i in range(n):
            xx = 100 + (i - (n - 1) / 2) * 16
            stroke(img, [(xx, t - 26), (xx, t)])
    elif code in ("bd", "dv"):
        n = {"bd": 1, "dv": 2}[code]
        for i in range(n):
            xx = 100 + (i - (n - 1) / 2) * 24
            stroke(img, [(xx - 10, t - 24), (xx + 10, t)])
            stroke(img, [(xx - 10, t), (xx + 10, t - 24)])
    return img


ECHELONS = ["tm", "sq", "sc", "pl", "co", "bn", "rg", "bd", "dv"]


def hq(key):
    img = new()
    x, y = FRAMES[key][4]
    stroke(img, [(x, y), (x, 196)])
    return img


def bar(key):
    img = new()
    bot = FRAMES[key][3]
    x0, _, x1, _ = FRAMES[key][1]
    if key == "fl":
        x0, x1 = 25, 175
    ImageDraw.Draw(img).rectangle(((x0 + 4) * U, (bot + 8) * U, (x1 - 4) * U, (bot + 20) * U), fill=255)
    return img


def direction():
    img = new()
    stroke(img, [(100, 100), (100, 22)])
    fill_path(img, "M100,4 L89,26 L111,26 Z")
    return img


# ---------------------------------------------------------------- curseurs GPS (centre = position)
def g_nav_fill():
    img = new()
    fill_path(img, "M100,18 L162,172 L100,138 L38,172 Z")
    return img


def g_nav_line():
    img = new()
    stroke_path(img, "M100,18 L162,172 L100,138 L38,172 Z", 9)
    return img


def g_chev_fill():
    img = new()
    fill_path(img, "M100,46 L148,150 L100,126 L52,150 Z")
    return img


def g_chev_line():
    img = new()
    stroke_path(img, "M100,46 L148,150 L100,126 L52,150 Z", 8)
    return img


def g_cone():
    # Cône de cap : de la position vers le haut, ±30°, transparent au loin.
    img = Image.new("L", (B, B), 0)
    pix = img.load()
    cx = cy = B / 2
    for y in range(0, int(cy)):
        for x in range(B):
            dx, dy = x - cx, cy - y
            r = math.hypot(dx, dy)
            if r < 1 or r > cy:
                continue
            ang = math.degrees(math.atan2(abs(dx), dy))
            if ang > 30:
                continue
            edge = min(1.0, (30 - ang) / 4)
            pix[x, y] = int(200 * (1 - r / cy) ** 0.8 * edge)
    return img


def g_rose():
    img = new()
    circle(img, 100, 100, 78, 6)
    for i in range(0, 360, 30):
        a = math.radians(i)
        r0 = 60 if i % 90 == 0 else 68
        stroke(img, [(100 + r0 * math.sin(a), 100 - r0 * math.cos(a)), (100 + 84 * math.sin(a), 100 - 84 * math.cos(a))], 6 if i % 90 == 0 else 4)
    fill_path(img, "M100,0 L88,22 L112,22 Z")
    return img


def g_needle():
    img = new()
    fill_path(img, "M100,30 L113,100 L100,116 L87,100 Z")
    circle(img, 100, 100, 8)
    return img


def g_ret():
    img = new()
    circle(img, 100, 100, 30, 7)
    for a, b in (((100, 22), (100, 58)), ((100, 142), (100, 178)), ((22, 100), (58, 100)), ((142, 100), (178, 100))):
        stroke(img, [a, b], 7)
    circle(img, 100, 100, 6)
    return img


def g_dot():
    img = new()
    circle(img, 100, 100, 40)
    return img


def g_dot_ring():
    img = new()
    circle(img, 100, 100, 47, 13)
    return img


GPS = {"g_nav_fill": g_nav_fill, "g_nav_line": g_nav_line, "g_chev_fill": g_chev_fill, "g_chev_line": g_chev_line,
       "g_cone": g_cone, "g_rose": g_rose, "g_needle": g_needle, "g_ret": g_ret, "g_dot": g_dot, "g_dot_ring": g_dot_ring}


# ---------------------------------------------------------------- sortie
def masks():
    out = {}
    for k in FRAMES:
        out[f"f_{k}_fill"] = frame_fill(k)
        out[f"f_{k}_line"] = frame_line(k)
        out[f"f_{k}_dash"] = frame_line(k, True)
    for k in LAND:
        for p in PICTOS:
            out[f"p_{p}_{k}"] = picto(p, k)
        for e in ECHELONS:
            out[f"e_{e}_{k}"] = echelon(e, k)
        out[f"hq_{k}"] = hq(k)
        out[f"bar_{k}"] = bar(k)
    for k in AIR + ["fs"]:
        for p in AIR_PICTOS:
            out[f"p_{p}_{k}"] = picto(p, k)
    out["dir"] = direction()
    for n, f in GPS.items():
        out[n] = f()
    return out


def to_rgba(mask):
    small = mask.resize((S, S), Image.LANCZOS)
    im = Image.new("RGBA", (S, S), (255, 255, 255, 0))
    im.putalpha(small)
    return im


def image_to_paa():
    try:
        import winreg
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, r"Software\Bohemia Interactive\ImageToPAA") as k:
            tool = winreg.QueryValueEx(k, "tool")[0]
            return tool if os.path.exists(tool) else None
    except OSError:
        return None


def to_paa(png, paa, tool):
    # L'ancien fichier est supprimé d'abord : un échec de conversion ne laisse jamais une texture périmée en place.
    if os.path.exists(paa):
        os.remove(paa)
    if tool:
        subprocess.run([tool, png, paa], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    else:
        subprocess.run([shutil.which("hemtt") or "hemtt", "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)
    if not os.path.exists(paa):
        raise SystemExit(f"conversion ratée : {paa}")
    with open(paa, "rb") as f:
        tag = f.read(2)
    # 0xFF05 : DXT5 (alpha) ; tout autre format est refusé (cf. 4.1.0, texture refusée par un mauvais en-tête).
    if tag != b"\x05\xff":
        raise SystemExit(f"{paa} : format inattendu {tag.hex()} (DXT5 attendu)")


# Couleurs de la planche de contrôle (thème clair de fn_milDraw).
FILL = {"f": (128, 224, 255), "h": (255, 128, 128), "n": (170, 255, 170), "u": (255, 255, 128)}


def sheet(path, m):
    cell = 96
    rows = []
    for k in LAND:
        rows.append([(k, p) for p in PICTOS])
    for k in AIR + ["fs"]:
        rows.append([(k, p) for p in AIR_PICTOS])
    cols = max(len(r) for r in rows) + 1
    W = cols * cell
    H = (len(rows) + 3) * cell
    img = Image.new("RGBA", (W, H), (46, 56, 48, 255))

    def put(name, color, x, y):
        layer = Image.new("RGBA", (S, S), color + (0,))
        layer.putalpha(m[name].resize((S, S), Image.LANCZOS))
        img.alpha_composite(layer.resize((cell, cell), Image.LANCZOS), (x, y))

    for ri, r in enumerate(rows):
        for ci, (k, p) in enumerate(r):
            x, y = ci * cell, ri * cell
            put(f"f_{k}_fill", FILL[k[0]], x, y)
            put(f"f_{k}_line", (0, 0, 0), x, y)
            put(f"p_{p}_{k}", (0, 0, 0), x, y)
    y = len(rows) * cell
    for i, e in enumerate(ECHELONS):
        put("f_fl_fill", FILL["f"], i * cell, y)
        put("f_fl_dash" if i % 3 == 2 else "f_fl_line", (0, 0, 0), i * cell, y)
        put("p_inf_fl", (0, 0, 0), i * cell, y)
        put(f"e_{e}_fl", (255, 255, 255), i * cell, y)
        if i == 0:
            put("hq_fl", (255, 255, 255), i * cell, y)
        if i == 1:
            put("bar_fl", (255, 210, 0), i * cell, y)
    y += cell
    for i, g in enumerate(["g_nav", "g_chev", "g_rose", "g_ret", "g_dot"]):
        x = i * cell * 2
        if g in ("g_nav", "g_chev"):
            if g == "g_chev":
                put("g_cone", (0, 200, 255), x, y)
            put(g + "_fill", (0, 200, 255), x, y)
            put(g + "_line", (255, 255, 255), x, y)
        elif g == "g_rose":
            put("g_rose", (255, 255, 255), x, y)
            put("g_needle", (255, 80, 60), x, y)
        elif g == "g_ret":
            put("g_ret", (0, 200, 255), x, y)
        else:
            put("g_dot_ring", (255, 255, 255), x, y)
            put("g_dot", (0, 150, 255), x, y)
    img.save(path)


def main():
    m = masks()
    if "--sheet" in sys.argv:
        sheet(sys.argv[sys.argv.index("--sheet") + 1], m)
        return
    keep = sys.argv[sys.argv.index("--png") + 1] if "--png" in sys.argv else None
    tmp = keep or tempfile.mkdtemp(prefix="comspec_milstd_")
    os.makedirs(tmp, exist_ok=True)
    os.makedirs(OUT, exist_ok=True)
    tool = image_to_paa()
    try:
        for name, mask in m.items():
            png = os.path.join(tmp, name + "_ca.png")
            to_rgba(mask).save(png)
            to_paa(png, os.path.join(OUT, name + "_ca.paa"), tool)
    finally:
        if keep is None:
            shutil.rmtree(tmp, ignore_errors=True)
    print(f"{len(m)} textures -> {OUT} ({'ImageToPAA' if tool else 'HEMTT'})")


if __name__ == "__main__":
    main()
