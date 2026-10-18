#!/usr/bin/env python3
"""
Schémas du guide « Réseau et simulation » (fn_netGuide), en français et en anglais :
  netguide_chain_<fr|en>.paa    chaîne téléphone -> répéteur -> relais raccordé -> satellite -> Athena, maillage
  netguide_budget_<fr|en>.paa   bilan radio : marge en espace libre, pertes (relief, feuillage, bâtiments), paliers MCS
  netguide_fresnel_<fr|en>.paa  profil : zone de Fresnel, crête, diffraction, relais en hauteur
  netguide_jam_<fr|en>.paa      brouilleur : le signal fort près du relais passe, le signal faible tombe
  netguide_queue_<fr|en>.paa    file d'envoi : urgents devant, canal partagé, renvoi puis retour en file
Fond opaque sombre (fenêtre du guide), 1024x512. Dessins originaux, générés ici.

Usage : python3 gen_net_guide.py <hemtt>     (Pillow ; hemtt pour la conversion en .paa)
        python3 gen_net_guide.py --png DOSSIER   (aperçus PNG seulement)
"""
import math
import os
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_R = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"

W, H, K = 1024, 512, 2
BG = (30, 33, 31, 255)
PANEL = (40, 44, 41, 255)
TXT = (228, 232, 228, 255)
MUTED = (150, 160, 152, 255)
GREEN = (92, 199, 107, 255)
AMBER = (232, 172, 64, 255)
RED = (222, 86, 72, 255)
BLUE = (96, 160, 232, 255)
GROUND = (92, 82, 64, 255)

L = {
    "fr": {
        "phone": "Téléphone", "rep": "Répéteur", "root": "Relais raccordé", "sat": "Satellite / fibre", "athena": "Athena",
        "mesh": "Maillage Wave Relay", "hop": "bond radio", "chain_t": "Chaque bond est un vrai lien radio",
        "budget_t": "Bilan radio d'un lien", "free": "Espace libre", "relief": "Relief", "foliage": "Feuillage",
        "bld": "Bâtiments", "fade": "Évanouiss.", "margin": "Marge", "thr": "seuil 0 dB", "mcs": "Débit (MCS)",
        "rx": "réception seule", "full": "plein débit",
        "fres_t": "Zone de Fresnel et diffraction", "fres": "zone de Fresnel", "ridge": "crête : 6 à 30 dB",
        "high": "relais en hauteur", "low": "téléphone au sol",
        "jam_t": "Brouillage : rapport brouilleur / signal", "jammer": "Brouilleur", "relay": "Relais",
        "ok": "signal fort : passe", "ko": "signal faible : coupé", "zone": "zone brouillée",
        "q_t": "File d'envoi et canal partagé", "queue": "File (sans signal)", "chan": "Canal radio",
        "medevac": "MEDEVAC", "alert": "Alerte", "msg": "Message", "photo": "Photo 300 Ko", "pos": "Position",
        "retry": "perdu : renvoi (3 essais)", "back": "3e échec : retour en file", "time": "temps",
        "lat": "latence + gigue", "air": "taille / débit",
    },
    "en": {
        "phone": "Phone", "rep": "Repeater", "root": "Connected relay", "sat": "Satellite / fibre", "athena": "Athena",
        "mesh": "Wave Relay mesh", "hop": "radio hop", "chain_t": "Every hop is a real radio link",
        "budget_t": "Link budget", "free": "Free space", "relief": "Terrain", "foliage": "Foliage",
        "bld": "Buildings", "fade": "Fading", "margin": "Margin", "thr": "0 dB threshold", "mcs": "Rate (MCS)",
        "rx": "receive only", "full": "full rate",
        "fres_t": "Fresnel zone and diffraction", "fres": "Fresnel zone", "ridge": "ridge: 6 to 30 dB",
        "high": "high relay", "low": "phone on the ground",
        "jam_t": "Jamming: jammer-to-signal ratio", "jammer": "Jammer", "relay": "Relay",
        "ok": "strong signal: gets through", "ko": "weak signal: cut", "zone": "jammed area",
        "q_t": "Send queue and shared channel", "queue": "Queue (no signal)", "chan": "Radio channel",
        "medevac": "MEDEVAC", "alert": "Alert", "msg": "Message", "photo": "Photo 300 KB", "pos": "Position",
        "retry": "lost: resend (3 tries)", "back": "3rd failure: back in queue", "time": "time",
        "lat": "latency + jitter", "air": "size / rate",
    },
}


def font(size, bold=True):
    try:
        return ImageFont.truetype(FONT if bold else FONT_R, int(size * K))
    except OSError:
        return ImageFont.load_default()


def canvas():
    img = Image.new("RGBA", (W * K, H * K), BG)
    return img, ImageDraw.Draw(img)


def P(*xy):
    return [v * K for v in xy]


def title(d, t):
    d.text(P(28, 22), t, font=font(26), fill=TXT)
    d.line(P(28, 60, 300, 60), fill=GREEN, width=3 * K)


def text(d, x, y, t, size=16, fill=TXT, anchor="la", bold=True):
    d.text(P(x, y), t, font=font(size, bold), fill=fill, anchor=anchor)


def phone(d, x, y, col=GREEN):
    d.rounded_rectangle(P(x - 11, y - 20, x + 11, y + 20), radius=4 * K, outline=col, width=3 * K)
    d.rectangle(P(x - 7, y - 14, x + 7, y + 10), fill=col)


def tower(d, x, base, h, col=TXT):
    d.line(P(x - 14, base, x, base - h), fill=col, width=3 * K)
    d.line(P(x + 14, base, x, base - h), fill=col, width=3 * K)
    for f in (0.3, 0.6):
        yy = base - h * f
        dx = 14 * (1 - f)
        d.line(P(x - dx, yy, x + dx, yy), fill=col, width=2 * K)
    d.ellipse(P(x - 5, base - h - 5, x + 5, base - h + 5), fill=col)
    for r in (12, 20):
        d.arc(P(x - r, base - h - r, x + r, base - h + r), 300, 60, fill=col, width=2 * K)
        d.arc(P(x - r, base - h - r, x + r, base - h + r), 120, 240, fill=col, width=2 * K)


def dashed(d, a, b, col, w=3, dash=10):
    (x1, y1), (x2, y2) = a, b
    n = max(1, int(math.hypot(x2 - x1, y2 - y1) / dash))
    for i in range(0, n, 2):
        t0, t1 = i / n, min(1, (i + 1) / n)
        d.line(P(x1 + (x2 - x1) * t0, y1 + (y2 - y1) * t0, x1 + (x2 - x1) * t1, y1 + (y2 - y1) * t1), fill=col, width=w * K)


def chain(lang):
    s = L[lang]
    img, d = canvas()
    title(d, s["chain_t"])
    base = 420
    d.rectangle(P(0, base, W, H), fill=GROUND)
    # Maillage de téléphones à gauche.
    ph = [(80, 380), (150, 330), (200, 395)]
    for i, a in enumerate(ph):
        for b in ph[i + 1:]:
            dashed(d, a, b, BLUE, 2, 8)
    for x, y in ph:
        phone(d, x, y)
    text(d, 140, 440, s["mesh"], 15, BLUE, "ma")
    text(d, 140, 470, s["phone"], 15, MUTED, "ma")
    # Répéteur puis relais raccordé.
    tower(d, 430, base, 150, AMBER)
    text(d, 430, 440, s["rep"], 17, AMBER, "ma")
    tower(d, 700, base, 220, GREEN)
    text(d, 700, 440, s["root"], 17, GREEN, "ma")
    dashed(d, (205, 360), (430, base - 150), TXT)
    text(d, 320, 250, s["hop"], 14, MUTED, "ma")
    dashed(d, (430, base - 150), (700, base - 220), TXT)
    text(d, 565, 160, s["hop"], 14, MUTED, "ma")
    # Satellite et Athena.
    d.rectangle(P(860, 90, 900, 120), outline=TXT, width=3 * K)
    d.rectangle(P(820, 98, 856, 112), fill=BLUE)
    d.rectangle(P(904, 98, 940, 112), fill=BLUE)
    text(d, 760, 105, s["sat"], 15, TXT, "rm")
    d.line(P(705, base - 225, 860, 120), fill=TXT, width=3 * K)
    d.rounded_rectangle(P(820, 300, 960, 380), radius=10 * K, fill=PANEL, outline=GREEN, width=3 * K)
    text(d, 890, 340, s["athena"], 22, GREEN, "mm")
    d.line(P(880, 150, 880, 300), fill=TXT, width=3 * K)
    return img


def budget(lang):
    s = L[lang]
    img, d = canvas()
    title(d, s["budget_t"])
    x0, y0, sc = 70, 110, 9.0  # y0 = +30 dB
    zero = y0 + 30 * sc
    d.line(P(50, zero, 640, zero), fill=RED, width=2 * K)
    text(d, 50, zero + 40, s["thr"], 13, RED, "la")
    steps = [(s["free"], 30, GREEN), (s["relief"], -8, AMBER), (s["foliage"], -9, AMBER), (s["bld"], -4, AMBER), (s["fade"], -2, MUTED)]
    lvl = 0
    x = x0
    for name, v, col in steps:
        top, bot = (lvl + v, lvl) if v > 0 else (lvl, lvl + v)
        d.rectangle(P(x, zero - top * sc, x + 78, zero - bot * sc), fill=col)
        text(d, x + 39, zero - top * sc - 6, ("+" if v > 0 else "") + f"{v} dB", 14, TXT, "md")
        text(d, x + 39, zero + 14, name, 14, MUTED, "ma")
        lvl += v
        x += 98
    d.rectangle(P(x, zero - lvl * sc, x + 78, zero), outline=GREEN, width=3 * K)
    text(d, x + 39, zero - lvl * sc - 6, f"{lvl} dB", 15, GREEN, "md")
    text(d, x + 39, zero + 14, s["margin"], 15, GREEN, "ma")
    # Échelle MCS à droite.
    mx = 740
    text(d, mx + 100, 90, s["mcs"], 17, TXT, "ma")
    marg = [0, 2, 4, 6, 9, 12, 16, 20]
    rate = [6, 12, 25, 40, 55, 70, 85, 100]
    for i in range(8):
        y = 440 - i * 40
        d.rectangle(P(mx, y - 30, mx + 1.4 * rate[i], y - 4), fill=GREEN if i >= 5 else (AMBER if i >= 2 else RED))
        text(d, mx - 8, y - 17, f"{marg[i]} dB", 13, MUTED, "rm")
        text(d, mx + 1.4 * rate[i] + 6, y - 17, f"MCS {i} · {rate[i]} %", 13, TXT, "lm")
    text(d, mx + 100, 470, f"< 0 dB : {s['rx']}", 13, RED, "ma")
    return img


def fresnel(lang):
    s = L[lang]
    img, d = canvas()
    title(d, s["fres_t"])
    # Terrain avec une crête au milieu.
    pts = [(0, 430)]
    for i in range(0, W + 1, 16):
        hill = 140 * math.exp(-((i - 560) / 120) ** 2)
        pts.append((i, 430 - hill - 10 * math.sin(i / 40)))
    pts.append((W, H))
    pts.append((0, H))
    d.polygon([(x * K, y * K) for x, y in pts], fill=GROUND)
    tower(d, 120, 430, 200, GREEN)
    text(d, 120, 450, s["high"], 15, GREEN, "ma")
    phone(d, 900, 400)
    text(d, 900, 450, s["low"], 15, TXT, "ma")
    a, b = (120, 230), (900, 400)
    # Ellipse de Fresnel le long de la ligne.
    ang = math.atan2(b[1] - a[1], b[0] - a[0])
    ln = math.hypot(b[0] - a[0], b[1] - a[1])
    poly = []
    for i in range(0, 61):
        t = i / 60
        r = 55 * math.sqrt(t * (1 - t)) * 2
        poly.append((a[0] + ln * t * math.cos(ang) - r * math.sin(ang), a[1] + ln * t * math.sin(ang) + r * math.cos(ang)))
    for i in range(60, -1, -1):
        t = i / 60
        r = 55 * math.sqrt(t * (1 - t)) * 2
        poly.append((a[0] + ln * t * math.cos(ang) + r * math.sin(ang), a[1] + ln * t * math.sin(ang) - r * math.cos(ang)))
    over = Image.new("RGBA", img.size, (0, 0, 0, 0))
    od = ImageDraw.Draw(over)
    od.polygon([(x * K, y * K) for x, y in poly], fill=(96, 160, 232, 70), outline=BLUE)
    img.alpha_composite(over)
    d = ImageDraw.Draw(img)
    d.line(P(*a, *b), fill=TXT, width=2 * K)
    text(d, 330, 220, s["fres"], 16, BLUE, "ma")
    # Crête qui entre dans la zone : diffraction.
    d.ellipse(P(548, 270, 572, 294), outline=RED, width=3 * K)
    text(d, 560, 240, s["ridge"], 16, RED, "md")
    for r in (40, 70, 100):
        d.arc(P(560 - r, 282 - r, 560 + r, 282 + r), 20, 70, fill=AMBER, width=2 * K)
    return img


def jam(lang):
    s = L[lang]
    img, d = canvas()
    title(d, s["jam_t"])
    jx, jy = 560, 290
    over = Image.new("RGBA", img.size, (0, 0, 0, 0))
    od = ImageDraw.Draw(over)
    for r, a in ((190, 40), (130, 60), (70, 90)):
        od.ellipse(P(jx - r, jy - r, jx + r, jy + r), fill=(222, 86, 72, a))
    img.alpha_composite(over)
    d = ImageDraw.Draw(img)
    d.polygon(P(jx, jy - 18, jx - 16, jy + 12, jx + 16, jy + 12), fill=RED)
    text(d, jx, jy + 22, s["jammer"], 16, RED, "ma")
    text(d, jx, jy - 200, s["zone"], 14, RED, "md")
    tower(d, 300, 330, 120, GREEN)
    text(d, 300, 345, s["relay"], 16, GREEN, "ma")
    phone(d, 400, 250)
    d.line(P(305, 210, 390, 245), fill=GREEN, width=4 * K)
    text(d, 400, 278, s["ok"], 15, GREEN, "ma")
    phone(d, 720, 200, AMBER)
    dashed(d, (305, 210), (708, 200), AMBER, 2, 8)
    d.line(P(690, 170, 750, 230), fill=RED, width=4 * K)
    d.line(P(750, 170, 690, 230), fill=RED, width=4 * K)
    text(d, 760, 245, s["ko"], 15, AMBER, "la")
    return img


def queue(lang):
    s = L[lang]
    img, d = canvas()
    title(d, s["q_t"])
    text(d, 40, 95, s["queue"], 17, TXT)
    items = [(s["medevac"], RED), (s["alert"], RED), (s["msg"], BLUE), (s["pos"], BLUE), (s["photo"], AMBER)]
    for i, (n, c) in enumerate(items):
        d.rounded_rectangle(P(40 + i * 120, 125, 150 + i * 120, 175), radius=6 * K, fill=PANEL, outline=c, width=3 * K)
        text(d, 95 + i * 120, 150, n, 13, c, "mm")
    d.line(P(40, 195, 640, 195), fill=MUTED, width=2 * K)
    d.polygon(P(40, 195, 56, 187, 56, 203), fill=MUTED)
    # Canal : un envoi après l'autre.
    text(d, 40, 245, s["chan"], 17, TXT)
    segs = [(s["msg"], 70, BLUE), (s["photo"], 330, AMBER), (s["pos"], 60, BLUE)]
    x = 40
    for n, w, c in segs:
        d.rectangle(P(x, 275, x + 30, 325), fill=MUTED)
        d.rectangle(P(x + 30, 275, x + 30 + w, 325), fill=c)
        text(d, x + 30 + w / 2, 300, n, 13, BG, "mm")
        x += 30 + w + 6
    text(d, 40, 335, s["lat"], 12, MUTED, "la")
    text(d, 255, 335, s["air"], 12, MUTED, "ma")
    d.line(P(40, 370, 640, 370), fill=MUTED, width=2 * K)
    d.polygon(P(640, 370, 624, 362, 624, 378), fill=MUTED)
    text(d, 645, 370, s["time"], 13, MUTED, "lm")
    # Renvois.
    d.rounded_rectangle(P(700, 120, 990, 330), radius=10 * K, fill=PANEL)
    for i in range(3):
        y = 150 + i * 50
        d.rectangle(P(715, y, 785, y + 30), fill=BLUE)
        d.line(P(792, y + 5, 812, y + 25), fill=RED, width=3 * K)
        d.line(P(812, y + 5, 792, y + 25), fill=RED, width=3 * K)
    text(d, 820, 165, s["retry"], 12, TXT, "lm")
    text(d, 845, 300, s["back"], 13, AMBER, "ma")
    return img


DRAW = {"chain": chain, "budget": budget, "fresnel": fresnel, "jam": jam, "queue": queue}


def main():
    png_only = len(sys.argv) > 2 and sys.argv[1] == "--png"
    hemtt = sys.argv[1] if len(sys.argv) > 1 and not png_only else "hemtt"
    dst_dir = sys.argv[2] if png_only else OUT
    os.makedirs(dst_dir, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        for key, fn in DRAW.items():
            for lang in ("fr", "en"):
                img = fn(lang).resize((W, H), Image.LANCZOS).convert("RGBA")
                name = f"netguide_{key}_{lang}"
                png = os.path.join(dst_dir if png_only else tmp, name + ".png")
                img.save(png)
                if png_only:
                    continue
                dst = os.path.join(OUT, name + ".paa")
                if os.path.exists(dst):
                    os.remove(dst)
                subprocess.run([hemtt, "utils", "paa", "convert", png, dst], check=True)
                print(dst)


if __name__ == "__main__":
    main()
