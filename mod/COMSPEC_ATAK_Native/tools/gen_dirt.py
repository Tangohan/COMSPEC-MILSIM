#!/usr/bin/env python3
"""Textures de saleté de l'écran du téléphone (poussière, traces de doigts, sang, gouttes d'eau).

Usage : python3 gen_dirt.py <hemtt>
Sortie : Sources/addons/main/data/dirt_*.paa (blanches sur fond transparent, teintées en jeu par ctrlSetTextColor).
Dépendances : Pillow, HEMTT (hemtt utils paa convert).
"""
import math
import os
import random
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter

HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Sources", "addons", "main", "data")


def white(alpha):
    img = Image.new("RGBA", alpha.size, (255, 255, 255, 0))
    img.putalpha(alpha)
    return img


def dust(seed):
    """Poussière fine : grains minuscules et quelques débris, un voile à peine visible, deux essuyages légers.
    Retours de jeu : les grosses taches floues (effet bokeh) noircissaient tout l'écran, c'était laid."""
    rnd = random.Random(seed)
    w = h = 1024
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    # Voile très léger, irrégulier (jamais une tache visible).
    for _ in range(40):
        x, y, r = rnd.randint(0, w), rnd.randint(0, h), rnd.randint(80, 220)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(4, 10))
    a = a.filter(ImageFilter.GaussianBlur(70))
    d = ImageDraw.Draw(a)
    # Grains : un pixel la plupart, rarement deux ; plus denses par plaques (là où la main n'a pas essuyé).
    for _ in range(9000):
        x, y = rnd.randint(0, w - 1), rnd.randint(0, h - 1)
        d.point((x, y), fill=rnd.randint(70, 170))
    for _ in range(500):
        x, y = rnd.randint(0, w - 1), rnd.randint(0, h - 1)
        r = rnd.choice([1, 1, 1, 2])
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(90, 190))
    # Fibres et petits débris.
    for _ in range(25):
        x, y = rnd.randint(0, w), rnd.randint(0, h)
        ang, ln = rnd.uniform(0, math.pi), rnd.randint(6, 18)
        d.line([x, y, x + math.cos(ang) * ln, y + math.sin(ang) * ln], fill=rnd.randint(70, 130), width=1)
    # Essuyages : traînées fines et pâles.
    for _ in range(2):
        x, y = rnd.randint(0, w), rnd.randint(0, h)
        ang = rnd.uniform(0, math.pi)
        for k in range(200):
            px, py = x + math.cos(ang) * k * 2.5, y + math.sin(ang) * k * 2.5
            d.ellipse([px - 10, py - 1, px + 10, py + 1], fill=rnd.randint(8, 16))
    return white(a.filter(ImageFilter.GaussianBlur(0.4)))


def fingerprint(seed):
    """Trace de pouce : ovale allongé, crêtes en boucle déformées, grasse et partielle (un bord appuyé, l'autre effacé)."""
    import numpy as np
    rnd = random.Random(seed)
    w, h = 128, 256  # PAA : côtés en puissances de 2 ; l'ovale occupe ~200 px de haut
    ys, xs = np.mgrid[0:h, 0:w].astype(np.float32)
    u = (xs - w / 2) / (w / 2)
    v = (ys - h * 0.56) / (h * 0.40)
    # Bruit lisse pour tordre les crêtes (pas de cercles parfaits).
    noise = Image.effect_noise((4, 6), 70).resize((w, h), Image.BICUBIC).filter(ImageFilter.GaussianBlur(12))
    warp = (np.asarray(noise, dtype=np.float32) - 128) / 128
    # Boucle : distance elliptique, crêtes qui s'ouvrent vers le bas.
    r = np.sqrt((u * 1.0) ** 2 + (np.minimum(v, 0) * 1.25) ** 2 + (np.maximum(v, 0) * 0.35) ** 2)
    r = r + 0.025 * warp + 0.012 * np.sin(u * 3 + rnd.uniform(0, 6))
    period = 0.062 + rnd.uniform(-0.005, 0.005)
    ridge = 0.5 + 0.5 * np.sin(2 * np.pi * r / period)
    ridge = np.clip((ridge - 0.35) * 2.2, 0, 1)
    # Contour du doigt : ovale flou ; pression inégale (un côté plus marqué), lacunes de crêtes.
    shape = np.clip(1 - ((u / 0.92) ** 2 + ((ys - h * 0.5) / (h * 0.39)) ** 2), 0, 1) ** 0.6
    ang = rnd.uniform(0, 2 * np.pi)
    press = np.clip(0.35 + 0.9 * (u * np.cos(ang) + v * np.sin(ang)), 0, 1) ** 1.4
    gaps = np.clip(1.4 - 1.0 * (np.asarray(Image.effect_noise((w // 6, h // 6), 90).resize((w, h), Image.BICUBIC), dtype=np.float32) / 255), 0, 1)
    a = ridge * shape * press * gaps
    # Voile gras sous les crêtes.
    a = 0.7 * a + 0.3 * shape * press
    img = Image.fromarray(np.uint8(np.clip(a, 0, 1) * 210), "L").filter(ImageFilter.GaussianBlur(0.7))
    return white(img)


def smear(seed):
    rnd = random.Random(seed)
    w, h = 256, 128
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    # Doigts qui glissent : quatre traînées parallèles, plus chargées au départ.
    base = rnd.uniform(-0.25, 0.25)
    for f in range(rnd.randint(3, 4)):
        y0 = 24 + f * 22 + rnd.uniform(-5, 5)
        length = rnd.uniform(140, 230)
        width = rnd.uniform(13, 20)
        for k in range(int(length)):
            t = k / length
            x = 14 + k
            y = y0 + math.sin(t * 3 + f) * 3 + base * k * 0.3
            ww = width * (1 - 0.6 * t)
            d.ellipse([x - ww / 2, y - ww / 2, x + ww / 2, y + ww / 2], fill=int(230 * (1 - 0.75 * t)))
        # Gouttes au bout de la traînée.
        for _ in range(rnd.randint(0, 3)):
            x, y, r = 14 + length + rnd.uniform(-10, 10), y0 + rnd.uniform(-8, 8), rnd.uniform(1, 3)
            d.ellipse([x - r, y - r, x + r, y + r], fill=180)
    a = a.filter(ImageFilter.GaussianBlur(1.2))
    # Stries (sang séché, rides de la peau).
    d = ImageDraw.Draw(a)
    for _ in range(40):
        x, y = rnd.randint(10, w - 10), rnd.randint(10, h - 10)
        d.line([x, y, x + rnd.randint(8, 30), y + rnd.randint(-2, 2)], fill=0, width=1)
    return white(a.filter(ImageFilter.GaussianBlur(0.4)))


def splat(seed):
    rnd = random.Random(seed)
    s = 128
    a = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(a)
    r = rnd.uniform(16, 24)
    d.ellipse([s / 2 - r, s / 2 - r, s / 2 + r, s / 2 + r], fill=235)
    for _ in range(rnd.randint(10, 18)):
        ang, dist = rnd.uniform(0, 2 * math.pi), rnd.uniform(r * 0.8, s / 2 - 6)
        rr = rnd.uniform(1.5, 6) * (1 - dist / s)
        x, y = s / 2 + math.cos(ang) * dist, s / 2 + math.sin(ang) * dist
        d.ellipse([x - rr, y - rr, x + rr, y + rr], fill=220)
        d.line([s / 2, s / 2, x, y], fill=160, width=max(1, int(rr)))
    return white(a.filter(ImageFilter.GaussianBlur(1.0)))


def blood_noise(w, h, seed, scale=8, blur=6):
    """Bruit lisse 0..1 (bords irréguliers du sang, caillots)."""
    import numpy as np
    rnd = random.Random(seed)
    n = Image.effect_noise((max(2, w // scale), max(2, h // scale)), 80 + rnd.randint(0, 20)).resize((w, h), Image.BICUBIC)
    return np.asarray(n.filter(ImageFilter.GaussianBlur(blur)), dtype=np.float32) / 255


def blood_body(a, seed, rim=0.45):
    """Donne du relief à un masque de sang : bord plus sombre (sang séché sur le pourtour), coeur un peu moins
    dense, petites bulles et caillots. Entrée / sortie : tableau numpy 0..1."""
    import numpy as np
    h, w = a.shape
    m = Image.fromarray(np.uint8(np.clip(a, 0, 1) * 255), "L")
    inner = np.asarray(m.filter(ImageFilter.MinFilter(5)).filter(ImageFilter.GaussianBlur(3)), dtype=np.float32) / 255
    edge = np.clip(a - inner, 0, 1)
    clot = blood_noise(w, h, seed + 7, 6, 2)
    body = a * (0.72 + 0.22 * clot) + rim * edge
    return np.clip(body, 0, 1)


def hand(seed):
    """Paume en sang posée sur l'écran : contact partiel et irrégulier (paume, bouts de doigts qui ont glissé)."""
    import numpy as np
    rnd = random.Random(seed)
    w = h = 256
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    cx, cy = w * 0.5 + rnd.uniform(-10, 10), h * 0.68
    # Paume : plusieurs appuis (talon de la main, base des doigts), pas un disque.
    for _ in range(9):
        r = rnd.uniform(18, 34)
        x, y = cx + rnd.uniform(-46, 46), cy + rnd.uniform(-38, 40)
        d.ellipse([x - r, y - r * 0.8, x + r, y + r * 0.8], fill=230)
    # Doigts : quatre empreintes en éventail, séparées de la paume, bout arrondi plus chargé ; certaines ont glissé.
    for f in range(4):
        ang = math.radians(-24 + f * 16 + rnd.uniform(-4, 4))
        length = [52, 62, 58, 44][f] * rnd.uniform(0.85, 1.1)
        width = [19, 21, 20, 17][f]
        bx, by = cx - 36 + f * 24, cy - 50
        slide = rnd.uniform(0, 22) if rnd.random() < 0.5 else 0
        for k in range(int(length + slide)):
            t = k / (length + slide)
            x, y = bx + math.sin(ang) * k, by - 8 - math.cos(ang) * k
            ww = width * (0.85 + 0.15 * math.sin(t * math.pi))
            d.ellipse([x - ww / 2, y - ww / 2, x + ww / 2, y + ww / 2], fill=int(200 + 35 * t))
        # Pli entre deux phalanges : un trait sans contact.
        kx, ky = bx + math.sin(ang) * length * 0.45, by - 8 - math.cos(ang) * length * 0.45
        d.line([kx - width / 2, ky, kx + width / 2, ky], fill=60, width=3)
    # Pouce sur le côté, plus court et incliné.
    side = rnd.choice([-1, 1])
    for k in range(48):
        x, y = cx + side * (58 + k * 0.55), cy - 4 - k * 0.6
        d.ellipse([x - 12, y - 12, x + 12, y + 12], fill=215)
    a = np.asarray(a.filter(ImageFilter.GaussianBlur(3)), dtype=np.float32) / 255
    # Contact inégal : seuil sur un bruit, des zones de la paume ne touchent pas l'écran.
    n = blood_noise(w, h, seed, 7, 5)
    a = np.clip((a * (0.35 + 0.9 * n) - 0.18) * 2.4, 0, 1)
    # Peau : grain fin et quelques plis (pas de rayures régulières).
    grain = blood_noise(w, h, seed + 5, 2, 0.8)
    a = a * (0.7 + 0.35 * grain)
    return white(Image.fromarray(np.uint8(np.clip(blood_body(a, seed), 0, 1) * 235), "L").filter(ImageFilter.GaussianBlur(0.6)))


def drip(seed):
    """Coulure : goutte en haut, une à trois traînées qui descendent et finissent en perle."""
    import numpy as np
    rnd = random.Random(seed)
    w, h = 128, 256
    a = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(a)
    cx = w / 2 + rnd.uniform(-8, 8)
    d.ellipse([cx - 26, 6, cx + 26, 50], fill=230)
    for r in range(rnd.randint(1, 3)):
        x = cx + rnd.uniform(-16, 16)
        y = 30
        width = rnd.uniform(6, 11)
        length = rnd.uniform(110, 210)
        for k in range(int(length)):
            t = k / length
            x += rnd.uniform(-0.35, 0.35)
            ww = width * (1 - 0.55 * t)
            d.ellipse([x - ww / 2, y + k - ww / 2, x + ww / 2, y + k + ww / 2], fill=225)
        br = width * 0.85
        d.ellipse([x - br, y + length - br, x + br, y + length + br * 1.3], fill=240)
    a = np.asarray(a.filter(ImageFilter.GaussianBlur(1.2)), dtype=np.float32) / 255
    return white(Image.fromarray(np.uint8(np.clip(blood_body(a, seed, 0.35), 0, 1) * 240), "L"))


def spatter(seed):
    """Projection fine (impact proche) : nuée de gouttelettes étirées dans le sens du jet, quelques grosses gouttes."""
    import numpy as np
    rnd = random.Random(seed)
    s = 256
    a = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(a)
    ang = rnd.uniform(0, 2 * math.pi)
    ox, oy = s / 2 - math.cos(ang) * s * 0.35, s / 2 - math.sin(ang) * s * 0.35
    for _ in range(rnd.randint(220, 320)):
        spread = rnd.gauss(0, 0.45)
        dist = abs(rnd.gauss(0.45, 0.25)) * s * 0.8
        a2 = ang + spread
        x, y = ox + math.cos(a2) * dist, oy + math.sin(a2) * dist
        r = max(0.7, rnd.expovariate(1 / 1.6)) * (1.4 - dist / (s * 0.9))
        r = min(r, 9)
        tail = r * rnd.uniform(1.5, 4)
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(170, 245))
        d.line([x, y, x + math.cos(a2) * tail, y + math.sin(a2) * tail], fill=rnd.randint(120, 200), width=max(1, int(r * 0.7)))
    a = np.asarray(a.filter(ImageFilter.GaussianBlur(0.7)), dtype=np.float32) / 255
    return white(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 245), "L"))


def pool(seed):
    """Nappe épaisse (gore) : sang qui a coulé sur l'écran et commence à coaguler, bords nets et foncés, coulures."""
    import numpy as np
    rnd = random.Random(seed)
    s = 256
    ys, xs = np.mgrid[0:s, 0:s].astype(np.float32)
    u, v = (xs - s / 2) / (s * 0.40), (ys - s * 0.42) / (s * 0.34)
    n = blood_noise(s, s, seed, 10, 6)
    field = (1 - (u ** 2 + v ** 2)) + (n - 0.5) * 1.3
    a = np.clip(field * 9, 0, 1)
    img = Image.fromarray(np.uint8(a * 255), "L")
    d = ImageDraw.Draw(img)
    for _ in range(rnd.randint(2, 4)):
        x = s / 2 + rnd.uniform(-70, 70)
        y = s * 0.6
        length = rnd.uniform(40, 95)
        for k in range(int(length)):
            ww = 8 * (1 - 0.5 * k / length)
            d.ellipse([x - ww, y + k - ww, x + ww, y + k + ww], fill=235)
    for _ in range(rnd.randint(8, 16)):
        ang, dist = rnd.uniform(0, 2 * math.pi), rnd.uniform(s * 0.38, s * 0.48)
        x, y, r = s / 2 + math.cos(ang) * dist, s * 0.42 + math.sin(ang) * dist * 0.85, rnd.uniform(2, 6)
        d.ellipse([x - r, y - r, x + r, y + r], fill=230)
    a = np.asarray(img.filter(ImageFilter.GaussianBlur(0.9)), dtype=np.float32) / 255
    a = blood_body(a, seed, 0.6)
    # Caillots sombres et zones plus fines.
    cl = blood_noise(s, s, seed + 11, 6, 3)
    a = a * (0.72 + 0.35 * cl)
    return white(Image.fromarray(np.uint8(np.clip(a, 0, 1) * 248), "L"))

def drop():
    s = 64
    a = Image.new("L", (s, s), 0)
    d = ImageDraw.Draw(a)
    d.ellipse([6, 6, s - 6, s - 6], fill=60)
    d.ellipse([6, 6, s - 6, s - 6], outline=210, width=3)
    d.ellipse([18, 14, 30, 24], fill=255)
    return white(a.filter(ImageFilter.GaussianBlur(1.2)))


def case_dust(src, seed):
    """Saleté de la coque d'un modèle (même cadre que sa texture) : poussière plus dense vers les bords et les
    creux, éclaboussures de boue ; masquée par la silhouette de la coque (l'ouverture de l'écran reste propre)."""
    rnd = random.Random(seed)
    S = 1024
    shape = Image.open(src).convert("RGBA").getchannel("A").resize((S, S), Image.LANCZOS)
    shape = shape.point(lambda v: 255 if v > 200 else 0).filter(ImageFilter.MinFilter(3))
    # Bords : 1 - flou de la silhouette (la saleté s'accumule contre les arêtes et dans les angles).
    soft = shape.filter(ImageFilter.GaussianBlur(26))
    edge = Image.eval(soft, lambda v: 255 - v)
    base = dust(seed).getchannel("A").resize((S, S), Image.LANCZOS)
    a = Image.new("L", (S, S), 0)
    d = ImageDraw.Draw(a)
    # Taches de boue séchée, surtout près des bords.
    for _ in range(30):
        x, y, r = rnd.randint(0, S), rnd.randint(0, S), rnd.randint(2, 8)
        d.ellipse([x - r, y - r * 0.7, x + r, y + r * 0.7], fill=rnd.randint(50, 110))
    for _ in range(900):
        x, y = rnd.randint(0, S - 1), rnd.randint(0, S - 1)
        r = rnd.choice([0, 1, 1, 2])
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(60, 160))
    a = a.filter(ImageFilter.GaussianBlur(0.8))
    from PIL import ImageChops
    mix = ImageChops.add(ImageChops.add(base, Image.eval(edge, lambda v: int(v * 0.35))), ImageChops.multiply(a, Image.eval(edge, lambda v: min(255, 40 + v))))
    return white(ImageChops.multiply(mix, shape))


CASE_SRC = {
    "s7": os.path.join(os.path.dirname(os.path.abspath(__file__)), "src", "android_s7_ca.png"),
    "rugged": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_rugged_front.png"),
    "iphone": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_iphone_front.png"),
    "tablet": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_tablet_front.png"),
    "sonim": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_sonim_front.png"),
    "keys": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_keys_front.png"),
    "s23": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_s23_front.png"),
    "ip17": os.path.join(os.path.dirname(os.path.abspath(__file__)), "render", "phone_ip17_front.png"),
}


def case_assets(tmp, only=None):
    for i, (m, src) in enumerate(CASE_SRC.items()):
        if (only and m not in only) or not os.path.exists(src):
            continue
        land = case_dust(src, 51 + i)
        convert(land, f"dirt_case_{m}_land", tmp)
        convert(land.rotate(-90, expand=True), f"dirt_case_{m}_port", tmp)


def convert(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    paa = os.path.join(OUT, name + ".paa")
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def blood_assets(tmp):
    """Anciennes textures de sang blanches (teinte unique), remplacées en jeu par bloodc_* (gen_screen_fx.py, en couleur)."""
    """Sang sur l'écran (1.97.0) : éclaboussures, mains, coulures, projections fines, nappes (gore)."""
    for i in range(2):
        convert(splat(42 + i), f"blood_splat_{i}", tmp)
        convert(hand(61 + i), f"blood_hand_{i}", tmp)
        convert(drip(71 + i), f"blood_drip_{i}", tmp)
        convert(spatter(81 + i), f"blood_spatter_{i}", tmp)
        convert(pool(91 + i), f"blood_pool_{i}", tmp)


def main():
    with tempfile.TemporaryDirectory() as tmp:
        # « blood » : seulement les textures de sang ajoutées en 1.97.0.
        if len(sys.argv) > 2 and sys.argv[2] == "blood":
            blood_assets(tmp)
            return
        if len(sys.argv) > 2 and sys.argv[2] == "prints":
            for i in range(4):
                convert(fingerprint(21 + i), f"dirt_print_{i}", tmp)
            return
        # « dust » : poussière de l'écran et de toutes les coques seulement.
        if len(sys.argv) > 2 and sys.argv[2] == "dust":
            for i in range(2):
                convert(dust(11 + i), f"dirt_dust_{i}", tmp)
            case_assets(tmp)
            return
        # « case keys » : seulement la poussière de la coque de la tablette à touches.
        if len(sys.argv) > 3 and sys.argv[2] == "case":
            case_assets(tmp, only=sys.argv[3:])
            return
        for i in range(2):
            convert(dust(11 + i), f"dirt_dust_{i}", tmp)
        for i in range(4):
            convert(fingerprint(21 + i), f"dirt_print_{i}", tmp)
        for i in range(3):
            convert(smear(31 + i), f"dirt_smear_{i}", tmp)
        # Sang : textures en couleur bloodc_* (gen_screen_fx.py) ; « blood » refait encore les anciennes si besoin.
        convert(drop(), "dirt_drop", tmp)
        case_assets(tmp)


if __name__ == "__main__":
    main()
